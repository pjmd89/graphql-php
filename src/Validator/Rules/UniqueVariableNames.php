<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Validator\Rules;

use pjmd89\GraphQL\Error\Error;
use pjmd89\GraphQL\Language\AST\NameNode;
use pjmd89\GraphQL\Language\AST\NodeKind;
use pjmd89\GraphQL\Language\AST\VariableDefinitionNode;
use pjmd89\GraphQL\Validator\ValidationContext;
use function sprintf;

class UniqueVariableNames extends ValidationRule
{
    /** @var NameNode[] */
    public $knownVariableNames;

    public function getVisitor(ValidationContext $context)
    {
        $this->knownVariableNames = [];

        return [
            NodeKind::OPERATION_DEFINITION => function () {
                $this->knownVariableNames = [];
            },
            NodeKind::VARIABLE_DEFINITION  => function (VariableDefinitionNode $node) use ($context) {
                $variableName = $node->variable->name->value;
                if (empty($this->knownVariableNames[$variableName])) {
                    $this->knownVariableNames[$variableName] = $node->variable->name;
                } else {
                    $context->reportError(new Error(
                        self::duplicateVariableMessage($variableName),
                        [$this->knownVariableNames[$variableName], $node->variable->name]
                    ));
                }
            },
        ];
    }

    public static function duplicateVariableMessage($variableName)
    {
        return sprintf('There can be only one variable named "%s".', $variableName);
    }
}
