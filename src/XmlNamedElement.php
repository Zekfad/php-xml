<?php

namespace Zekfad\Xml;

interface XmlNamedElement {
	/**
	 * Get XML element name in Clark notation.
	 * 
	 * It's allowed to return `null` - in that case serialization will proceed
	 * ignoring this interface.
	 * @return ?string XML element name in Clark notation.
	 */
	public function xmlGetElementName(): ?string;
	/**
	 * Set XML element name in Clark notation.
	 * @param string $name Element name in Clark notation
	 * @return void
	 */
	public function xmlSetElementName(string $name): void;
}
