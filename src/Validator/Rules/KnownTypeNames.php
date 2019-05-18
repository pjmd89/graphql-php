<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Validator\Rules;

use pjmd89\GraphQL\Error\Error;
use pjmd89\GraphQLGraphQL\Language\AST\NamedTypeNode;
use pjmd89\GraphQLGraphQL\Language\AST\NodeKind;
use pjmd89\GraphQLGraphQL\Language\Visitor;
use pjmd89\GraphQLGraphQL\Utils\Utils;
use pjmd89\GraphQLGraphQL\Validator\ValidationContext;
use function array_keys;
use function sprintf;

/**
 * Known type names
 *
 * A GraphQL document is only valid if referenced types (specifically
 * variable definitions and fragment conditions) are defined by the type schema.
 */
class KnownTypeNames extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        $skip = static function () {
            return Visitor::skipNode();
        };

        return [
            // TODO: when validating IDL, re-enable these. Experimental version does not
            // add unreferenced types, resulting in false-positive errors. Squelched
            // errors for now.
            NodeKind::OBJECT_TYPE_DEFINITION       => $skip,
            NodeKind::INTERFACE_TYPE_DEFINITION    => $skip,
            NodeKind::UNION_TYPE_DEFINITION        => $skip,
            NodeKind::INPUT_OBJECT_TYPE_DEFINITION => $skip,
            NodeKind::NAMED_TYPE                   => static function (NamedTypeNode $node) use ($context) {
                $schema   = $context->getSchema();
                $typeName = $node->name->value;
                $type     = $schema->getType($typeName);
                if ($type !== null) {
                    return;
                }

                $context->reportError(new Error(
                    self::unknownTypeMessage(
                        $typeName,
                        Utils::suggestionList($typeName, array_keys($schema->getTypeMap()))
                    ),
                    [$node]
                ));
            },
        ];
    }

    /**
     * @param string   $type
     * @param string[] $suggestedTypes
     */
    public static function unknownTypeMessage($type, array $suggestedTypes)
    {
        $message = sprintf('Unknown type "%s".', $type);
        if (! empty($suggestedTypes)) {
            $suggestions = Utils::quotedOrList($suggestedTypes);

            $message .= sprintf(' Did you mean %s?', $suggestions);
        }

        return $message;
    }
}
