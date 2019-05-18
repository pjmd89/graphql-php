<?php
namespace pjmd89\GraphQL\Benchmarks;

use pjmd89\GraphQL\Benchmarks\Utils\QueryGenerator;
use pjmd89\GraphQL\Benchmarks\Utils\SchemaGenerator;
use pjmd89\GraphQL\GraphQL;
use pjmd89\GraphQL\Type\Schema;
use pjmd89\GraphQL\Type\SchemaConfig;

/**
 * @BeforeMethods({"setUp"})
 * @OutputTimeUnit("milliseconds", precision=3)
 * @Warmup(1)
 * @Revs(5)
 * @Iterations(1)
 */
class HugeSchemaBench
{
    /** @var SchemaGenerator */
    private $schemaBuilder;

    private $schema;

    /** @var string */
    private $smallQuery;

    public function setUp()
    {
        $this->schemaBuilder = new SchemaGenerator([
            'totalTypes' => 600,
            'fieldsPerType' => 8,
            'listFieldsPerType' => 2,
            'nestingLevel' => 10,
        ]);

        $this->schema = $this->schemaBuilder->buildSchema();

        $queryBuilder     = new QueryGenerator($this->schema, 0.05);
        $this->smallQuery = $queryBuilder->buildQuery();
    }

    public function benchSchema()
    {
        $this->schemaBuilder
            ->buildSchema()
            ->getTypeMap();
    }

    public function benchSchemaLazy()
    {
        $this->createLazySchema();
    }

    public function benchSmallQuery()
    {
        $result = GraphQL::executeQuery($this->schema, $this->smallQuery);
    }

    public function benchSmallQueryLazy()
    {
        $schema = $this->createLazySchema();
        $result = GraphQL::executeQuery($schema, $this->smallQuery);
    }

    private function createLazySchema()
    {
        return new Schema(
            SchemaConfig::create()
                ->setQuery($this->schemaBuilder->buildQueryType())
                ->setTypeLoader(function ($name) {
                    return $this->schemaBuilder->loadType($name);
                })
        );
    }
}
