<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Traits;

// FIXME: remove this at the earliest convenience after refactoring class-based Generator, Parser, and Serializer.
trait ComplexTypeTrait
{
    private function attributeToXml(string $name, string $type, $value): string
    {
        $value = is_object($value) ? $value->getValue() : $value;

        switch($type) {
            case 'bool': { $value = [true => 'true', false => 'false'][$value]; break; }
        }

        return $name.'="'.$value.'" ';
    }

    private function propertyToXml(string $name, string $prefix, $value, string $ns, array $xmlns): string
    {
        switch(true) {
            case null === $value: { return ''; }
            case is_scalar($value): { return '<'.($xmlns[$ns] ?? '').$name.'>'.$value.'</'.($xmlns[$ns] ?? '').$name.'>'; }
            case is_object($value): { return '<'.($xmlns[$ns] ?? '').$name.' '.$value->toXmlAttributes().'>'.$value->toXml(array_search($prefix, $xmlns, true) ?: $ns, $xmlns).'</'.($xmlns[$ns] ?? '').$name.'>'; }
            case is_array($value): { return implode('', array_map(function($value) use($name, $prefix, $ns, $xmlns) { return $this->propertyToXml($name, $prefix, $value, $ns, $xmlns); }, $value)); }
            default: { throw new \RuntimeException(sprintf('Cannot convert %s::%s value %s to XML string!', __CLASS__, $name, is_object($value) ? get_class($value) : gettype($value))); }
        }

        var_dump($prefix, $ns);

        switch(true) {
            case null === $value: { $xml = ''; break; }
            case is_scalar($value): { $xml = '<'.$prefix.$name.'>'.$value.'</'.$prefix.$name.'>'; break; }
            case is_object($value): { $xml = '<'.$prefix.$name.' '.$value->toXmlAttributes().'>'.$value->toXml(array_search($prefix, $xmlns, true) ?: $ns, $xmlns).'</'.$prefix.$name.'>'; break; }
            case is_array($value): { $xml = implode('', array_map(function($value) use($name, $prefix, $ns, $xmlns) { return $this->propertyToXml($name, $prefix, $value, $ns, $xmlns); }, $value)); break; }
            default: { throw new \RuntimeException(sprintf('Cannot convert %s::%s value %s to XML string!', __CLASS__, $name, is_object($value) ? get_class($value) : gettype($value))); }
        }

        return $xml;
    }

    private function choiceToXml($value, string $ns, array $xmlns): string
    {
        return is_array($value)
            ? implode('', array_map(function($item) use($ns, $xmlns) { return $item->toXml($ns, $xmlns); }, $value))
            : $value->toXml($ns, $xmlns);
    }

    private function checkCollection(array $collection, string $type)
    {
        foreach($collection as $item) {
            if(false === $item instanceof $type) {
                throw new \InvalidArgumentException(sprintf('Invalid collection type %s, expected %s!', is_object($item) ? get_class($item) : gettype($item), $type));
            }
        }
    }
}
