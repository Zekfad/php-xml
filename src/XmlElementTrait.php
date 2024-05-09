<?php

namespace Zekfad\Xml;

use Sabre\Xml\Reader;
use Sabre\Xml\Writer;

trait XmlElementTrait {
	public function xmlSerialize(Writer $writer): void {
		XmlProcessor::xmlSerialize($writer, $this);
	}

	/**
	 * @param Reader $reader 
	 * @return static
	 */
	public static function xmlDeserialize(Reader $reader): static {
		return XmlProcessor::xmlDeserialize($reader, static::class);
	}
}
