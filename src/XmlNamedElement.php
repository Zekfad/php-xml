<?php

namespace Zekfad\Xml;


/**
 * Elements implementing this interface will have a chance to affect it's name
 * while parent serialized them.
 * 
 * Because serialization of newly created objects lack strong connection
 * to their names in XML (remember that parent decides on a name of children)
 * it is mostly useful for unions of multiple elements, where it allows
 * to assign names during serialization.
 * 
 * NB `XmlNamedElement` is ignored if node has exact single name. This is done
 * to support reuse of XML elements by different name.
 */
interface XmlNamedElement /* extends XmlElement */ {
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
