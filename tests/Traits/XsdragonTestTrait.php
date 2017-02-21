<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Tests\Traits;

trait XsdragonTestTrait
{
    private function fixture($path)
    {
        $finalPath = __DIR__.'/../fixture/'.$path;
        if(false === file_exists($finalPath)) {
            throw new \RuntimeException(sprintf('File %s does not exist!', $finalPath));
        }

        return file_get_contents($finalPath);
    }

    private function evalFiles(array $files)
    {
        foreach(array_unique($files) as $file) {
            try {
                eval(implode("\n", array_slice(explode("\n", $file), 2)));
            } catch(\Throwable $e) {
                throw new \RuntimeException(sprintf('Failed to eval() generated PHP class (%s) on line %s: %s', $e->getMessage(), $e->getLine(), $file));
            }
        }
    }

    private function validateXmlSchema(string $xml, string $xsd)
    {
        $use = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        if(false === $dom->schemaValidateSource($xsd)) {
            throw new \RuntimeException('Failed to validate XML according to passed schema: '.var_export(libxml_get_errors(), true));
        }
        libxml_use_internal_errors($use);
    }

    private function assertMultiContains(array $elements)
    {
        foreach($elements as $element => $matches) {
            foreach($matches as $match) {
                $this->assertContains($match, $element);
            }
        }
    }
}
