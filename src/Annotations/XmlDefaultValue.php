<?php

namespace Zekfad\Xml\Annotations;

/**
 * @template T
 */
#[\Attribute(\Attribute::TARGET_PROPERTY|\Attribute::TARGET_PARAMETER)]
class XmlDefaultValue {
	/**
	 * @param T $value 
	 */
	public function __construct(
		public $value,
	) {}
}