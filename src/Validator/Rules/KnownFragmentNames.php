<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Validator\Rules;

use pjmd89\GraphQL\Error\Error;
use pjmd89\GraphQLGraphQL\Language\AST\FragmentSpreadNode;
use pjmd89\GraphQLGraphQL\Language\AST\NodeKind;
use pjmd89\GraphQLGraphQL\Validator\ValidationContext;
use function sprintf;

class KnownFragmentNames extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::FRAGMENT_SPREAD => static function (FragmentSpreadNode $node) use ($context) {
                $fragmentName = $node->name->value;
                $fragment     = $context->getFragment($fragmentName);
                if ($fragment) {
                    return;
                }

                $context->reportError(new Error(
                    self::unknownFragmentMessage($fragmentName),
                    [$node->name]
                ));
            },
        ];
    }

    /**
     * @param string $fragName
     */
    public static function unknownFragmentMessage($fragName)
    {
        return sprintf('Unknown fragment "%s".', $fragName);
    }
}
