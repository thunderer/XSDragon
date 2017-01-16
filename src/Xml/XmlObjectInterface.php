<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Xml;

interface XmlObjectInterface
{
    /**
     * Returns an URI of the XSD schema where this element was defined.
     *
     * @return string
     */
    public function getXmlNamespace(): string;

    /**
     * Returns the name of the source element defined in XSD schema.
     *
     * @return string
     */
    public function getXmlName(): string;
}
