<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Validator\Rules;

use pjmd89\GraphQL\Error\Error;
use pjmd89\GraphQLGraphQL\Language\AST\DirectiveNode;
use pjmd89\GraphQLGraphQL\Language\AST\Node;
use pjmd89\GraphQLGraphQL\Validator\ValidationContext;
use function sprintf;

class UniqueDirectivesPerLocation extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        return [
            'enter' => static function (Node $node) use ($context) {
                if (! isset($node->directives)) {
                    return;
                }

                $knownDirectives = [];
                foreach ($node->directives as $directive) {
                    /** @var DirectiveNode $directive */
                    $directiveName = $directive->name->value;
                    if (isset($knownDirectives[$directiveName])) {
                        $context->reportError(new Error(
                            self::duplicateDirectiveMessage($directiveName),
                            [$knownDirectives[$directiveName], $directive]
                        ));
                    } else {
                        $knownDirectives[$directiveName] = $directive;
                    }
                }
            },
        ];
    }

    public static function duplicateDirectiveMessage($directiveName)
    {
        return sprintf('The directive "%s" can only be used once at this location.', $directiveName);
    }
}
