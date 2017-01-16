<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Serializer;

use Thunder\Xsdragon\Xml\XmlObjectInterface;

interface XmlSerializerInterface
{
    public function serialize(XmlObjectInterface $object): string;
}
