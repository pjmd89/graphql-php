<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Validator\Rules;

use pjmd89\GraphQL\Error\Error;
use pjmd89\GraphQL\Language\AST\DocumentNode;
use pjmd89\GraphQL\Language\AST\Node;
use pjmd89\GraphQL\Language\AST\NodeKind;
use pjmd89\GraphQL\Language\AST\OperationDefinitionNode;
use pjmd89\GraphQL\Utils\Utils;
use pjmd89\GraphQL\Validator\ValidationContext;
use function count;

/**
 * Lone anonymous operation
 *
 * A GraphQL document is only valid if when it contains an anonymous operation
 * (the query short-hand) that it contains only that one operation definition.
 */
class LoneAnonymousOperation extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        $operationCount = 0;

        return [
            NodeKind::DOCUMENT             => static function (DocumentNode $node) use (&$operationCount) {
                $tmp = Utils::filter(
                    $node->definitions,
                    static function (Node $definition) {
                        return $definition->kind === NodeKind::OPERATION_DEFINITION;
                    }
                );

                $operationCount = count($tmp);
            },
            NodeKind::OPERATION_DEFINITION => static function (OperationDefinitionNode $node) use (
                &$operationCount,
                $context
            ) {
                if ($node->name || $operationCount <= 1) {
                    return;
                }

                $context->reportError(
                    new Error(self::anonOperationNotAloneMessage(), [$node])
                );
            },
        ];
    }

    public static function anonOperationNotAloneMessage()
    {
        return 'This anonymous operation must be the only defined operation.';
    }
}
