<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Validator\Rules;

use pjmd89\GraphQL\Error\Error;
use pjmd89\GraphQLGraphQL\Language\AST\NodeKind;
use pjmd89\GraphQLGraphQL\Language\AST\VariableDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\Printer;
use pjmd89\GraphQLGraphQL\Type\Definition\Type;
use pjmd89\GraphQLGraphQL\Utils\TypeInfo;
use pjmd89\GraphQLGraphQL\Validator\ValidationContext;
use function sprintf;

class VariablesAreInputTypes extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::VARIABLE_DEFINITION => static function (VariableDefinitionNode $node) use ($context) {
                $type = TypeInfo::typeFromAST($context->getSchema(), $node->type);

                // If the variable type is not an input type, return an error.
                if (! $type || Type::isInputType($type)) {
                    return;
                }

                $variableName = $node->variable->name->value;
                $context->reportError(new Error(
                    self::nonInputTypeOnVarMessage($variableName, Printer::doPrint($node->type)),
                    [$node->type]
                ));
            },
        ];
    }

    public static function nonInputTypeOnVarMessage($variableName, $typeName)
    {
        return sprintf('Variable "$%s" cannot be non-input type "%s".', $variableName, $typeName);
    }
}
