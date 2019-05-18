<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Validator\Rules;

use pjmd89\GraphQL\Error\Error;
use pjmd89\GraphQLGraphQL\Language\AST\FragmentDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\NodeKind;
use pjmd89\GraphQLGraphQL\Language\AST\OperationDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\Visitor;
use pjmd89\GraphQLGraphQL\Validator\ValidationContext;
use function sprintf;

class NoUnusedFragments extends ValidationRule
{
    /** @var OperationDefinitionNode[] */
    public $operationDefs;

    /** @var FragmentDefinitionNode[] */
    public $fragmentDefs;

    public function getVisitor(ValidationContext $context)
    {
        $this->operationDefs = [];
        $this->fragmentDefs  = [];

        return [
            NodeKind::OPERATION_DEFINITION => function ($node) {
                $this->operationDefs[] = $node;

                return Visitor::skipNode();
            },
            NodeKind::FRAGMENT_DEFINITION  => function (FragmentDefinitionNode $def) {
                $this->fragmentDefs[] = $def;

                return Visitor::skipNode();
            },
            NodeKind::DOCUMENT             => [
                'leave' => function () use ($context) {
                    $fragmentNameUsed = [];

                    foreach ($this->operationDefs as $operation) {
                        foreach ($context->getRecursivelyReferencedFragments($operation) as $fragment) {
                            $fragmentNameUsed[$fragment->name->value] = true;
                        }
                    }

                    foreach ($this->fragmentDefs as $fragmentDef) {
                        $fragName = $fragmentDef->name->value;
                        if (! empty($fragmentNameUsed[$fragName])) {
                            continue;
                        }

                        $context->reportError(new Error(
                            self::unusedFragMessage($fragName),
                            [$fragmentDef]
                        ));
                    }
                },
            ],
        ];
    }

    public static function unusedFragMessage($fragName)
    {
        return sprintf('Fragment "%s" is never used.', $fragName);
    }
}
