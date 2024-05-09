<?php

namespace Zekfad\Xml\Annotations;

#[\Attribute(\Attribute::TARGET_CLASS|\Attribute::TARGET_PROPERTY|\Attribute::TARGET_PARAMETER)]
class XmlNode {
	/** One or more elements */
	public const REPEATING = [ 1, PHP_INT_MAX, ];

	/** Zero or more elements */
	public const REPEATING_OPTIONAL = [ 0, PHP_INT_MAX, ];

	/**
	 * @param ?string $name
	 * @param null|class-string|class-string[] $type 
	 */
	public function __construct(
		public ?string $name = null,
		public null|string|array $type = null,
		public ?array $repeating = null,
		public ?string $namespace = null,
	) {}

	/**
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

	public function isRepeating(): bool {
		return $this->repeating !== null;
	}
}
