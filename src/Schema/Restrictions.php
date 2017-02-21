<?php
declare(strict_types = 1);
namespace Thunder\Xsdragon\Schema;

final class Restrictions
{
    /** @var string */
    private $base;

    /** @var string[] */
    private $enumerations;
    /** @var string[] */
    private $patterns;
    /** @var ?int */
    private $length;
    /** @var ?int */
    private $minLength;
    /** @var ?int */
    private $maxLength;
    /** @var ?int */
    private $minInclusive;
    /** @var ?int */
    private $maxInclusive;
    /** @var ?int */
    private $fractionDigits;

    public function __construct(string $base, array $enumerations = null, array $patterns = null,
                                int $length = null, int $minLength = null, int $maxLength = null,
                                int $minInclusive = null, int $maxInclusive = null, int $fractionDigits = null)
    {
        $hasAtLeastOneRestriction = count(array_filter(func_get_args())) > 2;
        if(false === $hasAtLeastOneRestriction && null !== $enumerations && empty($enumerations)) {
            throw new \InvalidArgumentException(sprintf('Restriction enumerations must not be empty!'.json_encode(func_get_args())));
        }
        if(false === $hasAtLeastOneRestriction && null !== $patterns && empty($patterns)) {
            throw new \InvalidArgumentException(sprintf('Restriction patterns must not be empty!'));
        }
        if($length < 0) {
            throw new \InvalidArgumentException(sprintf('Restriction length must be >= 0, %s given!', $length));
        }
        if($minLength < 0) {
            throw new \InvalidArgumentException(sprintf('Restriction minLength must be >= 0, %s given!', $minLength));
        }
        if($maxLength < 0) {
            throw new \InvalidArgumentException(sprintf('Restriction maxLength must be >= 0, %s given!', $maxLength));
        }
        if($minInclusive < 0) {
            throw new \InvalidArgumentException(sprintf('Restriction minInclusive must be >= 0, %s given!', $minInclusive));
        }
        if($maxInclusive < 0) {
            throw new \InvalidArgumentException(sprintf('Restriction maxInclusive must be >= 0, %s given!', $maxInclusive));
        }
        if($fractionDigits < 0) {
            throw new \InvalidArgumentException(sprintf('Restriction fractionDigits must be >= 0, %s given!', $fractionDigits));
        }

        $this->base = $base;
        $this->enumerations = $enumerations ?? [];
        $this->patterns = $patterns ?? [];
        $this->length = $length;
        $this->minLength = $minLength;
        $this->maxLength = $maxLength;
        $this->minInclusive = $minInclusive;
        $this->maxInclusive = $maxInclusive;
        $this->fractionDigits = $fractionDigits;
    }

    public static function createFromEnumerations(string $base, array $enumerations)
    {
        return new static($base, $enumerations, null, null, null, null, null, null, null);
    }

    public static function createFromPatterns(string $base, array $patterns)
    {
        return new static($base, null, $patterns, null, null, null, null, null, null);
    }

    public static function createFromLength(string $base, int $length)
    {
        return new static($base, null, null, $length, null, null, null, null, null);
    }

    public static function createFromMinLength(string $base, int $minLength)
    {
        return new static($base, null, null, null, $minLength, null, null, null, null);
    }

    public static function createFromMaxLength(string $base, int $maxLength)
    {
        return new static($base, null, null, null, null, $maxLength, null, null, null);
    }

    public static function createFromMinInclusive(string $base, int $minInclusive)
    {
        return new static($base, null, null, null, null, null, $minInclusive, null, null);
    }

    public static function createFromMaxInclusive(string $base, int $maxInclusive)
    {
        return new static($base, null, null, null, null, null, null, $maxInclusive, null);
    }

    public static function createFromFractionDigits(string $base, int $fractionDigits)
    {
        return new static($base, null, null, null, null, null, null, null, $fractionDigits);
    }

    public function getBase(): string
    {
        return $this->base;
    }

    public function getEnumerations(): array
    {
        return $this->enumerations;
    }

    public function getPatterns(): array
    {
        return $this->patterns;
    }

    public function getLength()
    {
        return $this->length;
    }

    public function getMinLength()
    {
        return $this->minLength;
    }

    public function getMaxLength()
    {
        return $this->maxLength;
    }

    public function getMinInclusive()
    {
        return $this->minInclusive;
    }

    public function getMaxInclusive()
    {
        return $this->maxInclusive;
    }

    public function getFractionDigits()
    {
        return $this->fractionDigits;
    }
}
