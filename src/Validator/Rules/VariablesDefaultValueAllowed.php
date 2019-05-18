<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Validator\Rules;

use pjmd89\GraphQL\Error\Error;
use pjmd89\GraphQLGraphQL\Language\AST\FragmentDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\NodeKind;
use pjmd89\GraphQLGraphQL\Language\AST\SelectionSetNode;
use pjmd89\GraphQLGraphQL\Language\AST\VariableDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\Visitor;
use pjmd89\GraphQLGraphQL\Type\Definition\NonNull;
use pjmd89\GraphQLGraphQL\Validator\ValidationContext;
use function sprintf;

/**
 * Variable's default value is allowed
 *
 * A GraphQL document is only valid if all variable default values are allowed
 * due to a variable not being required.
 */
class VariablesDefaultValueAllowed extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::VARIABLE_DEFINITION => static function (VariableDefinitionNode $node) use ($context) {
                $name         = $node->variable->name->value;
                $defaultValue = $node->defaultValue;
                $type         = $context->getInputType();
                if ($type instanceof NonNull && $defaultValue) {
                    $context->reportError(
                        new Error(
                            self::defaultForRequiredVarMessage(
                                $name,
                                $type,
                                $type->getWrappedType()
                            ),
                            [$defaultValue]
                        )
                    );
                }

                return Visitor::skipNode();
            },
            NodeKind::SELECTION_SET       => static function (SelectionSetNode $node) {
                return Visitor::skipNode();
            },
            NodeKind::FRAGMENT_DEFINITION => static function (FragmentDefinitionNode $node) {
                return Visitor::skipNode();
            },
        ];
    }

    public static function defaultForRequiredVarMessage($varName, $type, $guessType)
    {
        return sprintf(
            'Variable "$%s" of type "%s" is required and will not use the default value. Perhaps you meant to use type "%s".',
            $varName,
            $type,
            $guessType
        );
    }
}
