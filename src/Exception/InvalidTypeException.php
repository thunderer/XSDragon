<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Exception;

final class InvalidTypeException extends \InvalidArgumentException
{
    public static function createFromVariable(string $expected, $var)
    {
        $type = is_object($var) ? get_class($var) : gettype($var);
        $type = is_scalar($type) ? $type : gettype($type);

        $self = new self();
        $self->message = sprintf('Invalid variable type %s, expected %s!', $type, $expected);

        return $self;
    }
}
