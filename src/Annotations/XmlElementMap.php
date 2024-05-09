<?php

namespace Zekfad\Xml\Annotations;

#[\Attribute(\Attribute::TARGET_CLASS)]
class XmlElementMap {
	/**
	 * @param array<string,class-string|callable|object> $map 
	 */
	public function __construct(
		public array $map = [],
	) {}
}