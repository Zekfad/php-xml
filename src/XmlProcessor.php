<?php

namespace Zekfad\Xml;

use Zekfad\Xml\Annotations;
use Sabre\Xml\LibXMLException;
use Sabre\Xml\ParseException;
use Sabre\Xml\Reader;
use Sabre\Xml\Writer;

/**
 * Main XML processor that used in (de)serializers.
 */
class XmlProcessor {
	/**
	 * Serialize class instance to XML.
	 * @template T of object
	 * @param Writer $writer
	 * @param T $value
	 */
	static function xmlSerialize(Writer $writer, $value): void {
		$reflector = new \ReflectionObject($value);

		$xmlNode = $reflector->getAttributes(Annotations\XmlNode::class)[0] ?? null;
		$currentNamespace = '';

		/** @var array{0:\ReflectionProperty,1:Annotations\XmlNode}[] */
		$nodes = [];
		/** @var ?\ReflectionProperty */
		$contentProperty = null;

		if (null !== $xmlNode)
			$currentNamespace = $xmlNode->newInstance()->namespace ?? '';

		foreach ($reflector->getProperties() as $property) {
			// Attribute
			$xmlAttribute = $property->getAttributes(Annotations\XmlAttribute::class)[0] ?? null;
			if ($xmlAttribute) {
				$attribute = $xmlAttribute->newInstance();

				$name = $attribute->name ?? $property->name;
				if (null !== $attribute->namespace)
					$name = '{' . $attribute->namespace . '}' . $name;

					$val = $property->getValue($value);
				if (null !== $val)
					$writer->writeAttribute(
						$name,
						(string) $property->getValue($value),
					);
				continue;
			}

			// XmlContent
			$xmlContent = $property->getAttributes(Annotations\XmlContent::class)[0] ?? null;
			if ($xmlContent) {
				$contentProperty = $property;
				continue;
			}

			// XmlNode
			$xmlNode = static::getXmlNodeAttribute($property);
			if ($xmlNode) {
				$nodes[] = [ $property, $xmlNode->newInstance(), ];
				continue;
			}
		}

		foreach ($nodes as list($property, $node)) {
			$namespace = '{' . ($node->namespace ?? $currentNamespace) . '}';
			if ($namespace === '{}')
				$namespace = '';
			$name = $namespace . ($node->getName() ?? $property->name);
			$val = $property->getValue($value);
			if (null !== $node->repeating) {
				assert(is_array($val));
				foreach ($val as $v) {
					if ($v !== null) {
						if ($v instanceof XmlText)
							$writer->text($v->text);
						else {
							$writer->writeElement(
								$v instanceof XmlNamedElement
									? ($v->xmlGetElementName() ?? $name)
									: $name,
								$v,
							);
						}
					}
				}
			} else {
				if ($val !== null)
					if ($val instanceof XmlText)
						$writer->text($val->text);
					else
						$writer->writeElement(
							$val instanceof XmlNamedElement
								? ($val->xmlGetElementName() ?? $name)
								: $name,
							$val,
						);
				continue;
			}
		}

		if (null !== $contentProperty) {
			$writer->write((string) $contentProperty->getValue($value));
		}
	}

	/**
	 * Deserialize XML to class instance.
	 * @template T of object
	 * @param Reader $reader 
	 * @param class-string<T> $className 
	 * @return T
	 */
	static function xmlDeserialize(Reader $reader, string $className): object {
		$class = new \ReflectionClass($className);
		$constructor = $class->getConstructor();
		if (null === $constructor)
			throw new ParseException("Class $className has no constructor.");
		$arguments = [];
		/** @var array{0:\ReflectionParameter,1:Annotations\XmlAttribute}[] */
		$attributes = [];
		/** @var array{0:\ReflectionParameter,1:Annotations\XmlNode}[] */
		$nodes = [];
		$mustHaveChildNodes = false;

		/** @var ?array{0:\ReflectionParameter,1:\ReflectionType} */
		$contentParameter = null;
	
		foreach ($constructor->getParameters() as $parameter) {
			// Default value
			$xmlDefaultValue = $parameter->getAttributes(Annotations\XmlDefaultValue::class)[0] ?? null;
			$hasDefaultValue = false;
			if ($xmlDefaultValue) {
				$arguments[$parameter->name] = $xmlDefaultValue->newInstance()->value;
				$hasDefaultValue = true;
			} elseif ($parameter->isDefaultValueAvailable()) {
				$arguments[$parameter->name] = $parameter->getDefaultValue();
				$hasDefaultValue = true;
			}
			
			// XmlAttribute
			$xmlAttribute = $parameter->getAttributes(Annotations\XmlAttribute::class)[0] ?? null;
			if ($xmlAttribute) {
				$type = $parameter->getType();
				if (null === $type )
					throw new ParseException("Class $className constructor parameter $parameter->name declared as attribute and have no type.");
				$attributes[] = [ $parameter, $xmlAttribute->newInstance(), ];
				continue;
			}

			// XmlNode
			$xmlNode = static::getXmlNodeAttribute($parameter);
			if ($xmlNode) {
				$node = $xmlNode->newInstance();
				// For repeating nodes with minimal value of 0 we'll allocate
				// empty array.
				if ($node->isRepeating() && $node->getRepeating()['min'] <= 0) {
					$arguments[$parameter->name] = [];
					$hasDefaultValue = true;
				}

				if (!$hasDefaultValue)
					$mustHaveChildNodes = true;
				$nodes[] = [ $parameter, $node, ];
				continue;
			}

			// XmlContent
			$xmlContent = $parameter->getAttributes(Annotations\XmlContent::class)[0] ?? null;
			if ($xmlContent) {
				if ($contentParameter)
					throw new ParseException("Class $className constructor parameter $parameter->name have multiple content declarations.");
				
				if (!empty($nodes))
					throw new ParseException("Class $className constructor parameter $parameter->name is content declaration, but the type has children.");

				$contentParameter = [ $parameter, $parameter->getType(), ];
				continue;
			}

			if ($hasDefaultValue) {
				continue;
			}

			throw new ParseException("Class $className constructor parameter $parameter->name have no attribute and no default value.");
		}

		// Parse attributes
		$parsedAttributes = $reader->parseAttributes();
		foreach ($attributes as list($parameter, $attribute)) {
			$name = $attribute->name ?? $parameter->name;
			if (null !== $attribute->namespace)
				$name = '{' . $attribute->namespace . '}' . $name;

			if (isset($parsedAttributes[$name])) {
				$value = $parsedAttributes[$name];
				$arguments[$parameter->name] = static::parseTypeValue($parameter->getType(), $value);
				continue;
			}

			if (!array_key_exists($parameter->name, $arguments))
				throw new ParseException("Class $className constructor parameter $parameter->name: no XML attribute \"$name\" found and no default value.");
		}

		/** @var array<string,class-string|callable|object> */
		$elementMap = [ ...$reader->elementMap, ];
		foreach ($class->getAttributes(Annotations\XmlElementMap::class) as $xmlElementMap) {
			$attribute = $xmlElementMap->newInstance();
			$elementMap = [ ...$elementMap, ...$attribute->map, ];
		};

		$xmlNode = $class->getAttributes(Annotations\XmlNode::class)[0] ?? null;
		$currentNamespace = $reader->namespaceURI;
		if (null !== $xmlNode)
			$currentNamespace = $xmlNode->newInstance()->namespace ?? $reader->namespaceURI;

		if ($mustHaveChildNodes && Reader::ELEMENT === $reader->nodeType && $reader->isEmptyElement)
			throw new ParseException("Class $className requires child elements.");

		if ($contentParameter) {
			list($parameter, $type) = $contentParameter;
			if (!empty($nodes))
				throw new ParseException("Class $className constructor have nodes and content.");
			$arguments[$parameter->name] = static::parseTypeValue($type, $reader->readText());
			$reader->read();
		} elseif (!empty($nodes) && !(Reader::ELEMENT === $reader->nodeType && $reader->isEmptyElement)) {
			reset($nodes);

			$reader->pushContext();
			$reader->elementMap = $elementMap;
			try {
				if (!$reader->read()) {
					$errors = libxml_get_errors();
					libxml_clear_errors();
					if ($errors) {
						throw new \Sabre\Xml\LibXMLException($errors);
					}
					throw new ParseException('This should never happen (famous last words)');
				}

				/** @var ?array{current:int,min:int,max:int} */
				$repeats = null;
				for ($position = 0;;$position++) {
					if (!$reader->isValid()) {
						$errors = libxml_get_errors();

						if ($errors) {
							libxml_clear_errors();
							throw new \Sabre\Xml\LibXMLException($errors);
						}
					}

					$textValue = null;
					switch ($reader->nodeType) {
						case Reader::ELEMENT:
							break;
						case Reader::TEXT:
						case Reader::CDATA:
						case Reader::WHITESPACE:
							$textValue = $reader->value;
							$reader->read();
							break;
						case Reader::NONE:
							throw new ParseException('We hit the end of the document prematurely. This likely means that some parser "eats" too many elements. Do not attempt to continue parsing.');
						case Reader::END_ELEMENT:
							// Check remaining nodes
							while (false !== ($node = current($nodes))) {
								list($parameter, $node) = $node;
								$clarkNamespace = '{' . ($node->namespace ?? $currentNamespace) . '}';
								$nodeName = $clarkNamespace . ($node->getName() ?? $parameter->name);
								// Check default value
								if (!array_key_exists($parameter->name, $arguments))
									throw new ParseException("Class $className: not enough elements: missing child $nodeName at position $position.");
								next($nodes);
							}
							$reader->read();
							break 2;
						default:
							$reader->read();
							$position--; // Position tracks only elements
							continue 2;
					}
					$name = $reader->getClark();

					while (true) {
						// Skip all remaining XML subtrees. 
						if (false === ($node = current($nodes))) {
							throw new ParseException(
								"Class $className received unexpected child of" .
								" type $name at position $position.",
							);
							throw new ParseException("$name");
							// $reader->next();
							break 2;
						}

						list($parameter, $node) = $node;

						// Skip text for non mixed nodes
						if ($textValue !== null) {
						// if ($reader->nodeType !== Reader::ELEMENT) {
							if ($node->isMixed())
								break; // parse text
							continue 2; // to next XML node
						}

						$clarkNamespace = '{' . ($node->namespace ?? $currentNamespace) . '}';

						// var_dump("checking " . implode(', ', $node->getNames()) . " <==> $name");

						if (!empty($nodeNames = $node->getNames())) {
							foreach ($nodeNames as $nodeName) {
								$nodeName = $clarkNamespace . $nodeName;
								if ($nodeMatches = ($nodeName === $name))
									break;
							}
						} else {
							$nodeName = $clarkNamespace . $parameter->name;
							$nodeMatches = $nodeName === $name;
						}

						// var_dump("checking $nodeName <==> $name: $nodeMatches");

						if (!$nodeMatches) {
							if ($node->isRepeating()) {
								// Node is repeating, but we now have a different
								// node, so stop repeating and check if min is
								// satisfied
								$repeats ??= $node->getRepeating(0);
								if ($repeats['current'] < $repeats['min'])
									throw new ParseException(
										"Class $className have not enough child" .
										" elements $nodeName at position $position:" .
										" expected at least {$repeats['min']}" .
										" got {$repeats['current']}.",
									);
								// retry with next XmlNode
								next($nodes);
								continue;
							}

							// Have default value, procceed
							if (array_key_exists($parameter->name, $arguments)) {
								next($nodes);
								continue 2;
							}
							throw new ParseException("Class $className missing child element $nodeName at position $position.");
						}

						if (!$node->isRepeating()) {
							next($nodes);
							$repeats = null;
						} else {
							if (null === $repeats)
								$repeats = $node->getRepeating(1); 
							else
								$repeats['current']++;
							if ($repeats['current'] > $repeats['max'])
								throw new ParseException(
									"Class $className have too much child" .
									" elements $nodeName at position $position:" .
									" expected at most {$repeats['max']}.",
								);
						}
						break;
					}

					/** @var class-string[] */
					$types = [];
					if (null !== $node->type) {
						if (is_array($node->type)) {
							$types = [ ...$node->type, ];
						} else {
							$types[] = $node->type;
						}
					} else {
						$type = $parameter->getType();
						if ($type !== null)
							$types = [ ...static::getTypeClasses($type), ];
					}

					// var_dump($types);
					$rawXml = null;

					$value = null;
					$lastException = null;
					foreach ($types as $type) {
						try {
							if ($type == XmlText::class) {
								if (null === $textValue)
									continue; // next type
								$parsedValue = new XmlText($textValue);
							} else {
								unset($deserializer);
								if (!array_key_exists($name, $reader->elementMap)) {
									if ('{}' === substr($name, 0, 2) && array_key_exists(substr($name, 2), $reader->elementMap)) {
										$deserializer = $reader->elementMap[substr($name, 2)];
									} else {
										if (is_subclass_of($type, \Sabre\Xml\XmlDeserializable::class, true)) {
											$deserializer = [ $type, 'xmlDeserialize', ];
										} else {
											$deserializer = [ \Sabre\Xml\Element\Base::class, 'xmlDeserialize', ];
										}
									}
								} else {
									$deserializer = $reader->elementMap[$name];
								}
								$deserializer_debug_name = is_array($deserializer) ? implode('::', $deserializer) : strval($deserializer);
								if (!is_callable($deserializer)) {
									if (is_subclass_of($deserializer, \Sabre\Xml\XmlDeserializable::class, true)) {
										if (!in_array($deserializer, $types))
											throw new ParseException("Class $className have invalid mapped parser type: $deserializer_debug_name; expected one of: " . implode(', ', $types) . ".");
										if ($deserializer !== $type)
											continue;

										/** @var callable */
										$deserializer = [ $type, 'xmlDeserialize'];
									} else {
										throw new ParseException("Class $className have invalid mapped parser type.: $deserializer_debug_name.");
									}
								} else {
									assert((function() use (&$deserializer, &$className){
										if (!is_array($deserializer) || !is_subclass_of($deserializer[0], \Sabre\Xml\XmlDeserializable::class, true))
											trigger_error("$className uses callable parser, which cannot be tested against defined types: $deserializer", E_USER_NOTICE);
									})());
								}

								if (!isset($value))
									$value = call_user_func(
										$deserializer,
										$reader
									);

								if ($value instanceof XmlReparsePoint && $rawXml === null) {
									$rawXml = $value->toRawXml();
								}

								// Do not try to parse reparse point into itself.
								if ($type === XmlReparsePoint::class)
									continue;

								if ($rawXml !== null)
									$parsedValue = static::parseReparsePoint($type, $reader, $name, $rawXml);
								elseif ($value instanceof \Sabre\Xml\XmlDeserializable)
									// Check if value was parsed via external methods.
									$parsedValue = $value;	
								else 
									$parsedValue = static::parseValue($type, $value);

								if ($parsedValue instanceof XmlNamedElement)
									$parsedValue->xmlSetElementName($name);
							}

							if ($node->isRepeating())
								$arguments[$parameter->name][] = $parsedValue;
							else
								$arguments[$parameter->name] = $parsedValue;
							continue 2; // to next xml node
						} catch (LibXMLException $e) {
							throw $e;
						} catch (\Exception $e) {
							$lastException = $e;
						}
					}
					throw new ParseException(
						"Class $className have invalid type of child element" .
						" $nodeName at position $position:" .
						" got " . gettype($value) .
						" expected $type.",
						previous: $lastException,
					);
				}
			} finally {
				$reader->popContext();
			}
		} else {
			$reader->next();
		}
		return $class->newInstanceArgs($arguments);
	}

	/**
	 * @param \ReflectionParameter|\ReflectionProperty $parameter 
	 * @return ?\ReflectionAttribute<Annotations\XmlNode> 
	 */
	static protected function getXmlNodeAttribute(\ReflectionParameter|\ReflectionProperty $parameter) {
		$xmlNode = $parameter->getAttributes(Annotations\XmlNode::class)[0] ?? null;
		if (!$xmlNode) {
			$type = $parameter->getType()?->getName();
			if (in_array($type, [ 'string', 'int', 'float', 'bool', ]))
				return null;
			if ($type) {
				try {
					$class = new \ReflectionClass($type);
					return $class->getAttributes(Annotations\XmlNode::class)[0] ?? null;
				} catch (\ReflectionException) {
					return null;
				}
			}
		}
		return $xmlNode;
	}

	static protected function parseReparsePoint(
		string $type,
		Reader $protoReader,
		string $name,
		string $xml,
	) {
		$reader = new Reader();
		$reader->elementMap = [ ...$protoReader->elementMap, ];
		$reader->contextUri = $protoReader->contextUri;
		$reader->namespaceMap = [ ...$protoReader->namespaceMap, ];
		$reader->classMap = [ ...$protoReader->classMap, ];
		
		try {
			$originalParser = $protoReader->getDeserializerForElementName($name);
		} catch (\Exception) {
			$originalParser = null;
		}
		try {
			$reader->elementMap[$name] = $type;
			$directParser = $reader->getDeserializerForElementName($name);
		} catch (\Exception) {
			$directParser = null;
		}

		$reader->elementMap[$name] = function (Reader $reader) use (
			$name,
			$type,
			&$originalParser,
			&$directParser,
		) {
			if (null === $originalParser)
				unset($reader->elementMap[$name]);
			else
				$reader->elementMap[$name] = $originalParser;
			// Likely primitives
			if ($directParser === null) {
				$tree = $reader->parseInnerTree();
				$value = $tree['value'] ?? $tree;
				return static::parseValue(
					$type,
					$value,
				);
			}
			return $directParser($reader);
		};

		$reader->XML($xml);
		return $reader->parse()['value'];
	}

	/**
	 * @template T
	 * @param class-string<T> $type Type.
	 * @param mixed $value Value.
	 * @return T 
	 */
	static protected function parseValue(
		string $type,
		$value,
	) {
		$actualType = gettype($value);
		if ($actualType === 'double') // funny, isn't it?
			$actualType = 'float';
		if ($actualType === $type || is_a($value, $type, false) || is_subclass_of($value, $type, false)) {
			return $value;
		}

		return static::parseBuiltInValue($type, false, $value);
	}

	/**
	 * @template T
	 * @param class-string<T> $type Type.
	 * @param bool $nullable Whether type is nullable.
	 * @param mixed $value Value.
	 * @return T 
	 */
	static protected function parseBuiltInValue(string $type, bool $nullable, $value) {
		$parsed = match ($type) {
			'null' => null,
			'string' => is_string($value) ? $value : null,
			'int' => is_numeric($value) ? intval($value) : null,
			'float' => is_numeric($value) ? floatval($value) : null,
			'bool' => is_bool($value) || 'true' === $value || 'false' === $value ? boolval($value) : null,
			'true' => (is_bool($value) && $value) || 'true' === $value ? true : null,
			'false' => (is_bool($value) && !$value) || 'false' === $value ? false : null,
			default => throw new ParseException("Unsupported type $type."),
		};
		if (null === $parsed) {
			if ($nullable)
				return $parsed;
			throw new ParseException("Failed to parse type $type from value: \"$value\".");
		}
		return $parsed;
	}

	static protected function parseTypeValue(\ReflectionType $type, $value) {
		if ($type instanceof \ReflectionNamedType) {
			return static::parseNamedTypeValue($type, $value);
		} elseif ($type instanceof \ReflectionUnionType) {
			$types = static::getUnionTypeClassesSorted($type);
			foreach ($types as $type) {
				try {
					return static::parseTypeValue($type, $value);
				} catch (ParseException) {
					continue;
				}
			}
			throw new ParseException("Failed to parse type $type from value: \"$value\".");
		}
		throw new ParseException("Unsupported type: $type.");
	}

	static protected function parseNamedTypeValue(\ReflectionNamedType $type, $value) {
		if ($type->isBuiltin()) {
			return static::parseBuiltInValue($type->getName(), $type->allowsNull(), $value);
		}
		throw new ParseException("Unsupported type: {$type->getName()}.");
	}

	/**
	 * @param \ReflectionType $type
	 * @return class-string[]
	 */
	static private function getTypeClasses(\ReflectionType $type): array {
		if ($type instanceof \ReflectionNamedType) {
			return  [ $type->getName(), ];
		} elseif ($type instanceof \ReflectionUnionType) {
			$result = [];
			$types = static::getUnionTypeClassesSorted($type);
			foreach ($types as $type) {
				try {
					array_push($result, ...static::getTypeClasses($type));
				} catch (ParseException) {
					continue;
				}
			}
			return $result;
		}
		throw new ParseException("Unsupported type: {$type}.");
	}

	/**
	 * @param \ReflectionUnionType $type 
	 * @return \ReflectionType[] 
	 */
	static private function getUnionTypeClassesSorted(\ReflectionUnionType $type): array {
		$typesOrder = [
			'float' => -3,
			'int' => -2,
			'bool' => -1,
			'true' => -1,
			'false' => -1,
			'string' => 999,
			'null' => 1000,
		];
		$types = $type->getTypes();
		usort(
			$types,
			fn (\ReflectionType $a, \ReflectionType $b) => ($typesOrder["$a"] ?? 0) - ($typesOrder["$b"] ?? 0),
		);
		return $types;
	}

	/**
	 * Convert node namespace and name to Clark notation.
	 * @param string $namespace Namespace name.
	 * @param string $name Node name.
	 * @return string 
	 */
	static public function toClarkNotation(string $namespace, string $name): string {
		return '{ ' .$namespace .  '}' . $name;
	}

	/**
	 * Parse Clark notation node name to namespace and plain name.
	 * @param string $namespace Namespace name.
	 * @param string $name Node name.
	 * @return array{0:string,1:string}
	 */
	static public function parseClarkNotation(string $name): array
	{
		if (!preg_match('/^{([^}]*)}(.*)$/', $name, $matches)) {
			throw new \InvalidArgumentException('\'' . $name . '\' is not a valid clark-notation formatted string');
		}

		return [
			$matches[1],
			$matches[2],
		];
	}
}