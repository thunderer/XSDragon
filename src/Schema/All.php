<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Schema;

final class All
{
    /** @var string */
    private $namespaceUri;
    /** @var Element[] */
    private $elements;

    public function __construct(string $namespaceUri, array $elements)
    {
        if(empty($elements)) {
            throw new \InvalidArgumentException('All elements must not be empty!');
        }
        $validTypes = [Element::class];
        foreach($elements as $element) {
            if(false === (is_object($element) && in_array(get_class($element), $validTypes, true))) {
                throw new \InvalidArgumentException(sprintf('All element must be an object of type %s, %s given!', json_encode($validTypes), get_class($element)));
            }
        }

        $this->namespaceUri = $namespaceUri;
        $this->elements = $elements;
    }

    public function getNamespaceUri(): string
    {
        return $this->namespaceUri;
    }

    public function getElements(): array
    {
        return $this->elements;
    }

    public function countElements(): int
    {
        return count($this->elements);
    }
}
