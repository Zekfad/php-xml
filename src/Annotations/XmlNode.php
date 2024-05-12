<?php

namespace Zekfad\Xml\Annotations;

#[\Attribute(\Attribute::TARGET_CLASS|\Attribute::TARGET_PROPERTY|\Attribute::TARGET_PARAMETER)]
class XmlNode {
	/** One or more elements */
	public const REPEATING = [ 1, PHP_INT_MAX, ];

	/** Zero or more elements */
	public const REPEATING_OPTIONAL = [ 0, PHP_INT_MAX, ];

	/**
	 * @param ?string $name Element name.
	 * @param null|class-string|class-string[] $type Type or type union of element.
	 * @param ?array{0:int,1:int} $repeating Min and Max element repetition value.
	//  * @param bool $mixed Whether to allow mixed content (in such case element
	//  *                    must include `string` in `$type` and have `array` PHP type).
	 * @param ?string $namespace Element namespace.
	 */
	public function __construct(
		public ?string $name = null,
		public null|string|array $type = null,
		public ?array $repeating = null,
		// public bool $mixed = false,
		public ?string $namespace = null,
	) {}

	/**
	 * Get repeating value.
	 * 
	 * @param int $current Current value.
	 * @return array{current:int,min:int,max:int}
	 */
	public function getRepeating(int $current = 0): array {
		return [
			'current' => $current,
			'min' => $this->repeating[0] ?? 0,
			'max' => $this->repeating[1] ?? PHP_INT_MAX,
		];
	}

	/**
	 * Whether element is repeating or not.
	 * 
	 * @return bool
	 */
	public function isRepeating(): bool {
		return $this->repeating !== null;
	}

	/**
	 * Whether element is mixed (contains XmlText type).
	 * 
	 * @return bool 
	 */
	public function isMixed(): bool {
		return $this->type === \Zekfad\Xml\XmlText::class || (
			is_array($this->type) && in_array(\Zekfad\Xml\XmlText::class, $this->type)
		);
	}
}
