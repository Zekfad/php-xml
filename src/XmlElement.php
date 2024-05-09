<?php

namespace Zekfad\Xml;

use Sabre\Xml\XmlDeserializable;
use Sabre\Xml\XmlSerializable;

class XmlElement implements XmlSerializable, XmlDeserializable {
	use XmlElementTrait;
}
