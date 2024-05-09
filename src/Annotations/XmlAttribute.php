<?php

namespace Zekfad\Xml\Annotations;

#[\Attribute(\Attribute::TARGET_PROPERTY|\Attribute::TARGET_PARAMETER)]
class XmlAttribute {
	public function __construct(
		public ?string $name = null,
	) {}
}