<?php

namespace Zekfad\Xml;

/**
 * Simple implementation of `XmlNamedElement` that preserves original element
 * name as it was encountered in parse process or assumes one from `XmlNode`
 * annotation.
 */
trait XmlPreserveNameTrait {
	private string $xmlPreservedName;
	public function xmlGetElementName(): ?string {
		if (!isset($this->xmlPreservedName)) {
			$reflector = new \ReflectionObject($this);
			/** @var \ReflectionAttribute<Annotations\XmlNode> */
			$xmlNode = $reflector->getAttributes(Annotations\XmlNode::class)[0] ?? null;
			if ($xmlNode !== null)
				return $xmlNode->newInstance()->getName();
		}
		return $this->xmlPreservedName;
	}
	public function xmlSetElementName(string $name): void {
		$this->xmlPreservedName = $name;
	}
}