<?php

namespace Zekfad\Xml\Annotations;

#[\Attribute(\Attribute::TARGET_PROPERTY|\Attribute::TARGET_PARAMETER)]
class XmlContent {
	public function __construct() {}
}