<?php

namespace Zekfad\Xml\Annotations;

#[\Attribute(\Attribute::TARGET_CLASS)]
/**
 * Annotation used to extend current parser element map.
 * Mapped parsers takes precedence over deduced from declared types.
 */
class XmlElementMap {
	/**
	 * @param array<string,class-string|callable|object> $map 
	 */
	public function __construct(
		public array $map = [],
	) {}
}