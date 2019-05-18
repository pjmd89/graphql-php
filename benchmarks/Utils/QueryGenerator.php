<?php
namespace pjmd89\GraphQL\Benchmarks\Utils;

use pjmd89\GraphQL\Language\AST\DocumentNode;
use pjmd89\GraphQL\Language\AST\FieldNode;
use pjmd89\GraphQL\Language\AST\NameNode;
use pjmd89\GraphQL\Language\AST\OperationDefinitionNode;
use pjmd89\GraphQL\Language\AST\SelectionSetNode;
use pjmd89\GraphQL\Language\Printer;
use pjmd89\GraphQL\Type\Definition\FieldDefinition;
use pjmd89\GraphQL\Type\Definition\InterfaceType;
use pjmd89\GraphQL\Type\Definition\ObjectType;
use pjmd89\GraphQL\Type\Definition\WrappingType;
use pjmd89\GraphQL\Type\Schema;
use pjmd89\GraphQL\Utils\Utils;
use function count;
use function max;
use function round;

class QueryGenerator
{
    private $schema;

    private $maxLeafFields;

    private $currentLeafFields;

    public function __construct(Schema $schema, $percentOfLeafFields)
    {
        $this->schema = $schema;

        Utils::invariant(0 < $percentOfLeafFields && $percentOfLeafFields <= 1);

        $totalFields = 0;
        foreach ($schema->getTypeMap() as $type) {
            if (! ($type instanceof ObjectType)) {
                continue;
            }

            $totalFields += count($type->getFields());
        }

        $this->maxLeafFields     = max(1, round($totalFields * $percentOfLeafFields));
        $this->currentLeafFields = 0;
    }

    public function buildQuery()
    {
        $qtype = $this->schema->getQueryType();

        $ast = new DocumentNode([
            'definitions' => [new OperationDefinitionNode([
                'name' => new NameNode(['value' => 'TestQuery']),
                'operation' => 'query',
                'selectionSet' => $this->buildSelectionSet($qtype->getFields()),
            ]),
            ],
        ]);

        return Printer::doPrint($ast);
    }

    /**
     * @param FieldDefinition[] $fields
     *
     * @return SelectionSetNode
     */
    public function buildSelectionSet($fields)
    {
        $selections[] = new FieldNode([
            'name' => new NameNode(['value' => '__typename']),
        ]);
        $this->currentLeafFields++;

        foreach ($fields as $field) {
            if ($this->currentLeafFields >= $this->maxLeafFields) {
                break;
            }

            $type = $field->getType();

            if ($type instanceof WrappingType) {
                $type = $type->getWrappedType(true);
            }

            if ($type instanceof ObjectType || $type instanceof InterfaceType) {
                $selectionSet = $this->buildSelectionSet($type->getFields());
            } else {
                $selectionSet = null;
                $this->currentLeafFields++;
            }

            $selections[] = new FieldNode([
                'name' => new NameNode(['value' => $field->name]),
                'selectionSet' => $selectionSet,
            ]);
        }

        $selectionSet = new SelectionSetNode([
            'selections' => $selections,
        ]);

        return $selectionSet;
    }
}
