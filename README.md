# zekfad/xml

PHP 8 Annotations based XML (de)serializer working on top of `sabre\xml`.

## Features

* Parse attributes (`float`, `int`, `bool`, `string`).
* Parse child nodes.
  * Support for optional elements.
  * Support for repeating elements (with optional min and max count check).
* Parse text content (and mixed nodes).
* Parse union types (via `XmlPeparsePoint`).
* Specify default value (where PHP's not available), you can mix optional
  and required parameters.
* Automatically implement `Sabre\Xml\XmlSerializable` and
  `Sabre\Xml\XmlDeserializable` with `XmlElementTrait` trait or
  extend `XmlElement`.
