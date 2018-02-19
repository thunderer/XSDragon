<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Tests\Schema;

use PHPUnit\Framework\TestCase;
use Thunder\Xsdragon\Exception\InvalidTypeException;
use Thunder\Xsdragon\Schema\Schema;
use Thunder\Xsdragon\Schema\SchemaContainer;

final class SchemaContainerTest extends TestCase
{
    public function testSchemaContainer()
    {
        $schema = new Schema('location', 'ns', ['prefix' => 'xmlns']);
        $container = new SchemaContainer([$schema]);

        $this->assertSame(1, $container->countSchemas());
        $this->assertCount(1, $container->getSchemas());
        $this->assertSame([$schema], $container->getSchemas());
    }

    public function testExceptionWhenEmptyContainer()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema container requires at least one Schema!');
        new SchemaContainer([]);
    }

    public function testExceptionWhenInvalidVariable()
    {
        $this->expectException(InvalidTypeException::class);
        $this->expectExceptionMessage('Invalid variable type stdClass, expected '.Schema::class.'!');
        new SchemaContainer([new \stdClass()]);
    }

    public function testExceptionWhenSchemaNotFound()
    {
        $schema = new Schema('location', 'ns', ['prefix' => 'xmlns']);
        $container = new SchemaContainer([$schema]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Schema with namespace `invalid` not found!');
        $container->findSchemaByNs('invalid');
    }
}
