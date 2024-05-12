<?php

namespace Zekfad\Xml;

use Sabre\Xml\Element;
use Sabre\Xml\Reader;
use Sabre\Xml\Writer;

/**
 * XmlText wraps mixed text between nodes.
 * 
 * Value isn't trimmed and stored as is.
 */
class XmlText implements Element {
	/**
	 * @param string $value Value.
	 */
	public function __construct(
		public string $value = '',
	) {}

	public function xmlSerialize(Writer $writer): void {
		$writer->text($this->value);
	}

	public static function xmlDeserialize(Reader $reader) {
		if ($reader->isEmptyElement) {
			$reader->next();
			return new self();
		}

		$result = '';
		$reader->read();
		while (true) {
			switch ($reader->nodeType) {
				case Reader::TEXT:
				case Reader::CDATA:
				case Reader::WHITESPACE:
					$result .= $reader->value;
					$reader->read();
					break;
				default:
					$reader->read();
					break 2;
			}
		}

		return new self($result);
	}
}
