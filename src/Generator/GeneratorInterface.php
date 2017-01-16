<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Generator;

use Thunder\Xsdragon\Schema\SchemaContainer;

interface GeneratorInterface
{
    public function generate(SchemaContainer $schemas): void;
}
