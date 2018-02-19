<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Tests\Schema;

use PHPUnit\Framework\TestCase;
use Thunder\Xsdragon\Schema\Restrictions;

final class RestrictionsTest extends TestCase
{
    public function testRestrictions()
    {
        $restrictions = new Restrictions('base', ['enum'], ['pattern'], 1, 2, 3, 4, 5, 6, 7);

        $this->assertSame('base', $restrictions->getBase());
        $this->assertSame(['enum'], $restrictions->getEnumerations());
        $this->assertSame(['pattern'], $restrictions->getPatterns());
        $this->assertSame(1, $restrictions->getLength());
        $this->assertSame(2, $restrictions->getMinLength());
        $this->assertSame(3, $restrictions->getMaxLength());
        $this->assertSame(4, $restrictions->getMinInclusive());
        $this->assertSame(5, $restrictions->getMaxInclusive());
        $this->assertSame(6, $restrictions->getFractionDigits());
        $this->assertSame(7, $restrictions->getTotalDigits());
    }

    public function testExceptionWhenEmptyEnumerations()
    {
        $this->expectException(\InvalidArgumentException::class);
        Restrictions::createFromEnumerations('base', []);
    }

    public function testExceptionWhenEmptyPatterns()
    {
        $this->expectException(\InvalidArgumentException::class);
        Restrictions::createFromPatterns('base', []);
    }

    public function testExceptionWhenNegativeLength()
    {
        $this->expectException(\InvalidArgumentException::class);
        Restrictions::createFromLength('base', -1);
    }

    public function testExceptionWhenNegativeMinLength()
    {
        $this->expectException(\InvalidArgumentException::class);
        Restrictions::createFromMinLength('base', -1);
    }

    public function testExceptionWhenNegativeMaxLength()
    {
        $this->expectException(\InvalidArgumentException::class);
        Restrictions::createFromMaxLength('base', -1);
    }

    public function testExceptionWhenNegativeMinInclusive()
    {
        $this->expectException(\InvalidArgumentException::class);
        Restrictions::createFromMinInclusive('base', -1);
    }

    public function testExceptionWhenNegativeMaxInclusive()
    {
        $this->expectException(\InvalidArgumentException::class);
        Restrictions::createFromMaxInclusive('base', -1);
    }

    public function testExceptionWhenNegativeFractionDigits()
    {
        $this->expectException(\InvalidArgumentException::class);
        Restrictions::createFromFractionDigits('base', -1);
    }

    public function testExceptionWhenNegativeTotalDigits()
    {
        $this->expectException(\InvalidArgumentException::class);
        Restrictions::createFromTotalDigits('base', -1);
    }
}
