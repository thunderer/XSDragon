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
        foreach($this->schemas as $schema) {
            if($schema->getNamespace() === $ns) {
                return $schema;
            }
        }

        throw new \RuntimeException(sprintf('Schema with namespace `%s` not found!', $ns));
    }

    public function findSchemaByLocation(string $location): Schema
    {
        foreach($this->schemas as $schema) {
            if($schema->getLocation() === $location) {
                return $schema;
            }
        }

        throw new \RuntimeException(sprintf('Schema with location `%s` not found!', $location));
    }

    public function findUrisFor(Schema $schema): array
    {
        $namespaces = $schema->getNamespaces();

        foreach($schema->getImports() as $import) {
            $this->findUrisForInner($this->findSchemaByNs($import), $namespaces);
        }

        return $namespaces;
    }

    private function findUrisForInner(Schema $schema, array &$namespaces)
    {
        foreach($schema->getNamespaces() as $uri => $prefix) {
            $namespaces[$uri] = $prefix;
            foreach($schema->getImports() as $import) {
                $this->findUrisForInner($this->findSchemaByNs($import), $namespaces);
            }
        }
    }
}
