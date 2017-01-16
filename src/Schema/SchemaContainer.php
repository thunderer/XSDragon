<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Schema;

use Thunder\Xsdragon\Exception\InvalidTypeException;

final class SchemaContainer
{
    /** @var Schema[] */
    private $schemas = [];

    public function __construct(array $schemas)
    {
        if(empty($schemas)) {
            throw new \InvalidArgumentException('Schema container requires at least one Schema!');
        }

        /** @var Schema $schema */
        foreach($schemas as $schema) {
            if(false === $schema instanceof Schema) {
                throw InvalidTypeException::createFromVariable(Schema::class, $schema);
            }

            $this->schemas[] = $schema;
        }
    }

    public function countSchemas(): int
    {
        return count($this->schemas);
    }

    /** @return Schema[] */
    public function getSchemas(): array
    {
        return array_values($this->schemas);
    }

    public function hasSchemaWithNs(string $ns): bool
    {
        try {
            $this->findSchemaByNs($ns);
            return true;
        } catch(\RuntimeException $e) {
            return false;
        }
    }

    public function findSchemaByNs(string $ns): Schema
    {
        try {
            return $this->takeFirst($this->schemas, function(Schema $schema) use($ns) {
                return $schema->getNamespace() === $ns;
            });
        } catch(\RuntimeException $e) {
            throw new \RuntimeException(sprintf('Schema with namespace `%s` not found!', $ns));
        }
    }

    public function findUrisFor(Schema $schema): array
    {
        $namespaces = $schema->getNamespaces();

        foreach($schema->getImports() as $import) {
            $this->findUrisForInner($this->findSchemaByNs($import), $namespaces);
        }

        return $namespaces;
    }

    private function findUrisForInner(Schema $schema, array &$namespaces): void
    {
        foreach($schema->getNamespaces() as $uri => $prefix) {
            $namespaces[$uri] = $prefix;
            foreach($schema->getImports() as $import) {
                $this->findUrisForInner($this->findSchemaByNs($import), $namespaces);
            }
        }
    }

    private function takeFirst(array $collection, callable $filter)
    {
        foreach($collection as $item) {
            if($filter($item)) {
                return $item;
            }
        }

        throw new \RuntimeException('Failed to find desired element in the collection!');
    }
}
