<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Type\Definition;

use pjmd89\GraphQL\Error\Error;
use pjmd89\GraphQLGraphQL\Executor\Values;
use pjmd89\GraphQLGraphQL\Language\AST\FieldNode;
use pjmd89\GraphQLGraphQL\Language\AST\FragmentDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\FragmentSpreadNode;
use pjmd89\GraphQLGraphQL\Language\AST\InlineFragmentNode;
use pjmd89\GraphQLGraphQL\Language\AST\SelectionSetNode;
use pjmd89\GraphQLGraphQL\Type\Schema;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_merge_recursive;
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_numeric;

class QueryPlan
{
    /** @var string[][] */
    private $types = [];

    /** @var Schema */
    private $schema;

    /** @var mixed[] */
    private $queryPlan = [];

    /** @var mixed[] */
    private $variableValues;

    /** @var FragmentDefinitionNode[] */
    private $fragments;

    /**
     * @param FieldNode[]              $fieldNodes
     * @param mixed[]                  $variableValues
     * @param FragmentDefinitionNode[] $fragments
     */
    public function __construct(ObjectType $parentType, Schema $schema, iterable $fieldNodes, array $variableValues, array $fragments)
    {
        $this->schema         = $schema;
        $this->variableValues = $variableValues;
        $this->fragments      = $fragments;
        $this->analyzeQueryPlan($parentType, $fieldNodes);
    }

    /**
     * @return mixed[]
     */
    public function queryPlan() : array
    {
        return $this->queryPlan;
    }

    /**
     * @return string[]
     */
    public function getReferencedTypes() : array
    {
        return array_keys($this->types);
    }

    public function hasType(string $type) : bool
    {
        return count(array_filter($this->getReferencedTypes(), static function (string $referencedType) use ($type) {
                return $type === $referencedType;
        })) > 0;
    }

    /**
     * @return string[]
     */
    public function getReferencedFields() : array
    {
        return array_values(array_unique(array_merge(...array_values($this->types))));
    }

    public function hasField(string $field) : bool
    {
        return count(array_filter($this->getReferencedFields(), static function (string $referencedField) use ($field) {
            return $field === $referencedField;
        })) > 0;
    }

    /**
     * @return string[]
     */
    public function subFields(string $typename) : array
    {
        if (! array_key_exists($typename, $this->types)) {
            return [];
        }

        return $this->types[$typename];
    }

    /**
     * @param FieldNode[] $fieldNodes
     */
    private function analyzeQueryPlan(ObjectType $parentType, iterable $fieldNodes) : void
    {
        $queryPlan = [];
        /** @var FieldNode $fieldNode */
        foreach ($fieldNodes as $fieldNode) {
            if (! $fieldNode->selectionSet) {
                continue;
            }

            $type = $parentType->getField($fieldNode->name->value)->getType();
            if ($type instanceof WrappingType) {
                $type = $type->getWrappedType();
            }

            $subfields = $this->analyzeSelectionSet($fieldNode->selectionSet, $type);

            $this->types[$type->name] = array_unique(array_merge(
                array_key_exists($type->name, $this->types) ? $this->types[$type->name] : [],
                array_keys($subfields)
            ));

            $queryPlan = array_merge_recursive(
                $queryPlan,
                $subfields
            );
        }

        $this->queryPlan = $queryPlan;
    }

    /**
     * @return mixed[]
     *
     * @throws Error
     */
    private function analyzeSelectionSet(SelectionSetNode $selectionSet, ObjectType $parentType) : array
    {
        $fields = [];
        foreach ($selectionSet->selections as $selectionNode) {
            if ($selectionNode instanceof FieldNode) {
                $fieldName     = $selectionNode->name->value;
                $type          = $parentType->getField($fieldName);
                $selectionType = $type->getType();

                $subfields = [];
                if ($selectionNode->selectionSet) {
                    $subfields = $this->analyzeSubFields($selectionType, $selectionNode->selectionSet);
                }

                $fields[$fieldName] = [
                    'type' => $selectionType,
                    'fields' => $subfields ?? [],
                    'args' => Values::getArgumentValues($type, $selectionNode, $this->variableValues),
                ];
            } elseif ($selectionNode instanceof FragmentSpreadNode) {
                $spreadName = $selectionNode->name->value;
                if (isset($this->fragments[$spreadName])) {
                    $fragment  = $this->fragments[$spreadName];
                    $type      = $this->schema->getType($fragment->typeCondition->name->value);
                    $subfields = $this->analyzeSubFields($type, $fragment->selectionSet);

                    $fields = $this->arrayMergeDeep(
                        $subfields,
                        $fields
                    );
                }
            } elseif ($selectionNode instanceof InlineFragmentNode) {
                $type      = $this->schema->getType($selectionNode->typeCondition->name->value);
                $subfields = $this->analyzeSubFields($type, $selectionNode->selectionSet);

                $fields = $this->arrayMergeDeep(
                    $subfields,
                    $fields
                );
            }
        }

        return $fields;
    }

    /**
     * @return mixed[]
     */
    private function analyzeSubFields(Type $type, SelectionSetNode $selectionSet) : array
    {
        if ($type instanceof WrappingType) {
            $type = $type->getWrappedType();
        }

        $subfields = [];
        if ($type instanceof ObjectType) {
            $subfields                = $this->analyzeSelectionSet($selectionSet, $type);
            $this->types[$type->name] = array_unique(array_merge(
                array_key_exists($type->name, $this->types) ? $this->types[$type->name] : [],
                array_keys($subfields)
            ));
        }

        return $subfields;
    }

    /**
     * similar to array_merge_recursive this merges nested arrays, but handles non array values differently
     * while array_merge_recursive tries to merge non array values, in this implementation they will be overwritten
     *
     * @see https://stackoverflow.com/a/25712428
     *
     * @param mixed[] $array1
     * @param mixed[] $array2
     *
     * @return mixed[]
     */
    private function arrayMergeDeep(array $array1, array $array2) : array
    {
        $merged = $array1;

        foreach ($array2 as $key => & $value) {
            if (is_numeric($key)) {
                if (! in_array($value, $merged, true)) {
                    $merged[] = $value;
                }
            } elseif (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->arrayMergeDeep($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
