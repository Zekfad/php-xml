<?php

namespace Zekfad\Xml;

use Sabre\Xml\Element;
use Sabre\Xml\Reader;
use Sabre\Xml\Writer;

/**
 * The XmlReparsePoint element allows you to extract a portion of your xml tree,
 * and get a well-formed xml string.
 *
 * This goes a bit beyond `innerXml` and friends, as we'll also match all the
 * correct namespaces.
 *
 * Please note that the XML fragment:
 *
 * 1. Will not have an <?xml declaration.
 * 2. Or a DTD
 * 3. It will have all the relevant xmlns attributes.
 * 4. It may not have a root element.
 */
class XmlReparsePoint implements Element {
	/**
	 * @param string $namespace Namespace.
	 * @param array<string,class-string|string|null> $namespaceMap Namespace Map.
	 * @param string $localName The local name of the node.
	 * @param array<string,mixed> $attributes Attributes.
	 * @param string $xml Inner XML value.
	 */
	public function __construct(
		public string $namespace,
		public array $namespaceMap,
		public string $localName,
		public array $attributes,
		public string $xml,
	) {}

	/**
	 * The xmlSerialize method is called during xml writing.
	 *
	 * Use the $writer argument to write its own xml serialization.
	 *
	 * An important note: do _not_ create a parent element. Any element
	 * implementing XmlSerializable should only ever write what's considered
	 * its 'inner xml'.
	 *
	 * The parent of the current element is responsible for writing a
	 * containing element.
	 *
	 * This allows serializers to be re-used for different element names.
	 *
	 * If you are opening new elements, you must also close them again.
	 */
	public function xmlSerialize(Writer $writer): void
	{
		$reader = new Reader();
		$reader->namespaceMap = $this->namespaceMap;

		// Wrapping the xml in a container, so root-less values can still be
		// parsed.
		$xml = "<?xml version=\"1.0\"?><{$this->localName} xmlns=\"{$this->namespace}\">{$this->xml}</{$this->localName}>";

		$reader->xml($xml);

		while ($reader->read()) {
			if ($reader->depth < 1) {
				// Skipping the root node.
				continue;
			}

			switch ($reader->nodeType) {
				case Reader::ELEMENT:
					$writer->startElement(
						(string) $reader->getClark()
					);
					$empty = $reader->isEmptyElement;
					while ($reader->moveToNextAttribute()) {
						switch ($reader->namespaceURI) {
							case '':
								$writer->writeAttribute($reader->localName, $reader->value);
								break;
							case 'http://www.w3.org/2000/xmlns/':
								// Skip namespace declarations
								break;
							default:
								$writer->writeAttribute((string) $reader->getClark(), $reader->value);
								break;
						}
					}
					if ($empty) {
						$writer->endElement();
					}
					break;
				case Reader::CDATA:
				case Reader::TEXT:
					$writer->text(
						$reader->value
					);
					break;
				case Reader::END_ELEMENT:
					$writer->endElement();
					break;
			}
		}
	}

	/**
	 * The deserialize method is called during xml parsing.
	 *
	 * This method is called statically, this is because in theory this method
	 * may be used as a type of constructor, or factory method.
	 *
	 * Often you want to return an instance of the current class, but you are
	 * free to return other data as well.
	 *
	 * You are responsible for advancing the reader to the next element. Not
	 * doing anything will result in a never-ending loop.
	 *
	 * If you just want to skip parsing for this element altogether, you can
	 * just call $reader->next();
	 *
	 * $reader->parseInnerTree() will parse the entire sub-tree, and advance to
	 * the next element.
	 */
	public static function xmlDeserialize(Reader $reader)
	{
		$result = new self(
			$reader->namespaceURI,
			[ ...$reader->namespaceMap, ],
			$reader->localName,
			$reader->parseAttributes(),
			$reader->readInnerXml(),
		);
		$reader->next();

		return $result;
	}

	public function toRawXml(): string {
		$writer = new Writer();
		$writer->openMemory();
		$writer->setIndent(true);
		$writer->startDocument();
		$writer->startElementNs(
			$this->namespaceMap[$this->namespace] ?? null,
			$this->localName,
			$this->namespace,
		);
		$writer->writeAttributes($this->attributes);
		$writer->writeRaw($this->xml);
		$writer->endElement();
		return $writer->outputMemory();
	}
}
