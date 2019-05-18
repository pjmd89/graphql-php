<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Validator\Rules;

use pjmd89\GraphQL\Error\Error;
use pjmd89\GraphQLGraphQL\Language\AST\DocumentNode;
use pjmd89\GraphQLGraphQL\Language\AST\FragmentDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\Node;
use pjmd89\GraphQLGraphQL\Language\AST\NodeKind;
use pjmd89\GraphQLGraphQL\Language\AST\OperationDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\Visitor;
use pjmd89\GraphQLGraphQL\Validator\ValidationContext;
use function sprintf;

/**
 * Executable definitions
 *
 * A GraphQL document is only valid for execution if all definitions are either
 * operation or fragment definitions.
 */
class ExecutableDefinitions extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::DOCUMENT => static function (DocumentNode $node) use ($context) {
                /** @var Node $definition */
                foreach ($node->definitions as $definition) {
                    if ($definition instanceof OperationDefinitionNode ||
                        $definition instanceof FragmentDefinitionNode
                    ) {
                        continue;
                    }

                    $context->reportError(new Error(
                        self::nonExecutableDefinitionMessage($definition->name->value),
                        [$definition->name]
                    ));
                }

                return Visitor::skipNode();
            },
        ];
    }

    public static function nonExecutableDefinitionMessage($defName)
    {
        return sprintf('The "%s" definition is not executable.', $defName);
    }
}
