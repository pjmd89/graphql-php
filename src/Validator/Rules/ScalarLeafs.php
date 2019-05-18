<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Validator\Rules;

use pjmd89\GraphQL\Error\Error;
use pjmd89\GraphQLGraphQL\Language\AST\FieldNode;
use pjmd89\GraphQLGraphQL\Language\AST\NodeKind;
use pjmd89\GraphQLGraphQL\Type\Definition\Type;
use pjmd89\GraphQLGraphQL\Validator\ValidationContext;
use function sprintf;

class ScalarLeafs extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::FIELD => static function (FieldNode $node) use ($context) {
                $type = $context->getType();
                if (! $type) {
                    return;
                }

                if (Type::isLeafType(Type::getNamedType($type))) {
                    if ($node->selectionSet) {
                        $context->reportError(new Error(
                            self::noSubselectionAllowedMessage($node->name->value, $type),
                            [$node->selectionSet]
                        ));
                    }
                } elseif (! $node->selectionSet) {
                    $context->reportError(new Error(
                        self::requiredSubselectionMessage($node->name->value, $type),
                        [$node]
                    ));
                }
            },
        ];
    }

    public static function noSubselectionAllowedMessage($field, $type)
    {
        return sprintf('Field "%s" of type "%s" must not have a sub selection.', $field, $type);
    }

    public static function requiredSubselectionMessage($field, $type)
    {
        return sprintf('Field "%s" of type "%s" must have a sub selection.', $field, $type);
    }
}
