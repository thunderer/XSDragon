<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Schema;

final class Choice
{
    /** @var string */
    private $namespaceUri;
    /** @var Element[] */
    private $elements;
    private $minOccurs;
    private $maxOccurs;

    public function __construct(string $namespaceUri, array $elements, $minOccurs, $maxOccurs)
    {
        if(empty($elements)) {
            throw new \InvalidArgumentException('Choice elements must not be empty!');
        }
        $validTypes = [Sequence::class, Element::class];
        foreach($elements as $element) {
            if(false === (is_object($element) && in_array(get_class($element), $validTypes, true))) {
                throw new \InvalidArgumentException(sprintf('Choice element must be an object of type %s, %s given!', json_encode($validTypes), get_class($element)));
            }
        }
        if(false === (is_int($minOccurs) && $minOccurs >= 0) && 'unbounded' !== $minOccurs && null !== $minOccurs) {
            throw new \InvalidArgumentException(sprintf('Choice minOccurs can be either null, non-negative integer or string `unbounded`, %s given!', $minOccurs));
        }
        if(false === (is_int($maxOccurs) && $maxOccurs >= 0) && 'unbounded' !== $maxOccurs && null !== $maxOccurs) {
            throw new \InvalidArgumentException(sprintf('Choice maxOccurs can be either null, non-negative integer or string `unbounded`, %s given!', $maxOccurs));
        }

        $this->namespaceUri = $namespaceUri;
        $this->elements = $elements;
        $this->minOccurs = $minOccurs;
        $this->maxOccurs = $maxOccurs;
    }

    public function getNamespaceUri(): string
    {
        return $this->namespaceUri;
    }

    public function countElements(): int
    {
        return count($this->elements);
    }

    public function getElements(): array
    {
        return $this->elements;
    }

    public function getMinOccurs()
    {
        return $this->minOccurs;
    }

    public function getMaxOccurs()
    {
        return $this->maxOccurs;
    }
}
