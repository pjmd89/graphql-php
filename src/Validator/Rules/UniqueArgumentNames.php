<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Validator\Rules;

use pjmd89\GraphQL\Error\Error;
use pjmd89\GraphQLGraphQL\Language\AST\ArgumentNode;
use pjmd89\GraphQLGraphQL\Language\AST\NameNode;
use pjmd89\GraphQLGraphQL\Language\AST\NodeKind;
use pjmd89\GraphQLGraphQL\Language\Visitor;
use pjmd89\GraphQLGraphQL\Validator\ValidationContext;
use function sprintf;

class UniqueArgumentNames extends ValidationRule
{
    /** @var NameNode[] */
    public $knownArgNames;

    public function getVisitor(ValidationContext $context)
    {
        $this->knownArgNames = [];

        return [
            NodeKind::FIELD     => function () {
                $this->knownArgNames = [];
            },
            NodeKind::DIRECTIVE => function () {
                $this->knownArgNames = [];
            },
            NodeKind::ARGUMENT  => function (ArgumentNode $node) use ($context) {
                $argName = $node->name->value;
                if (! empty($this->knownArgNames[$argName])) {
                    $context->reportError(new Error(
                        self::duplicateArgMessage($argName),
                        [$this->knownArgNames[$argName], $node->name]
                    ));
                } else {
                    $this->knownArgNames[$argName] = $node->name;
                }

                return Visitor::skipNode();
            },
        ];
    }

    public static function duplicateArgMessage($argName)
    {
        return sprintf('There can be only one argument named "%s".', $argName);
    }
}
