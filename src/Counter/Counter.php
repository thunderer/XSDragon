<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Counter;

// FIXME: add type parameters to all tick*() method and make them echo a runtime message with \r to display progress or something
// FIXME: it can use tput to manipulate console or just overwrite line with \r
// FIXME: at the end clear line with spaces and display `done`.
final class Counter
{
    /** @var float */
    private $startedAt;
    /** @var float */
    private $finishedAt;
    private $schemasCount = 0;
    private $elementsCount = 0;
    private $simpleTypesCount = 0;
    private $complexTypesCount = 0;
    private $attributesCount = 0;
    private $sequencesCount = 0;
    private $choicesCount = 0;
    private $allsCount = 0;
    private $pseudoTypesCount = 0;
    private $classesCount = 0;

    public function __construct()
    {
    }

    public function start(): void
    {
        if(null !== $this->startedAt) {
            throw new \LogicException('Counter is not reusable!');
        }

        $this->startedAt = microtime(true);
    }

    public function stop(): void
    {
        if(null !== $this->finishedAt) {
            throw new \LogicException('Counter is not reusable!');
        }

        $this->finishedAt = microtime(true);
    }

    public function tickSchema(): void { $this->schemasCount++; }
    public function tickElement(): void { $this->elementsCount++; }
    public function tickSimpleType(): void { $this->simpleTypesCount++; }
    public function tickComplexType(): void { $this->complexTypesCount++; }
    public function tickAttribute(): void { $this->attributesCount++; }
    public function tickSequence(): void { $this->sequencesCount++; }
    public function tickChoice(): void { $this->choicesCount++; }
    public function tickAll(): void { $this->allsCount++; }
    public function tickPseudoTypes(): void { $this->pseudoTypesCount++; }
    public function tickClass(): void { $this->classesCount++; }

    public function getStartedAt(): float { return $this->startedAt; }
    public function getFinishedAt(): float { return $this->finishedAt; }
    public function getSchemasCount(): int { return $this->schemasCount; }
    public function getElementsCount(): int { return $this->elementsCount; }
    public function getSimpleTypesCount(): int { return $this->simpleTypesCount; }
    public function getComplexTypesCount(): int { return $this->complexTypesCount; }
    public function getAttributesCount(): int { return $this->attributesCount; }
    public function getSequencesCount(): int { return $this->sequencesCount; }
    public function getChoicesCount(): int { return $this->choicesCount; }
    public function getAllsCount(): int { return $this->allsCount; }
    public function getPseudoTypesCount(): int { return $this->pseudoTypesCount; }
    public function getClassesCount(): int { return $this->classesCount; }
}
