<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Parser;

interface XmlParserInterface
{
    public function parse(string $xml);
}
