<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Validator\Rules;

use pjmd89\GraphQL\Error\Error;
use pjmd89\GraphQLGraphQL\Language\AST\DirectiveNode;
use pjmd89\GraphQLGraphQL\Language\AST\FieldNode;
use pjmd89\GraphQLGraphQL\Language\AST\NodeKind;
use pjmd89\GraphQLGraphQL\Language\Visitor;
use pjmd89\GraphQLGraphQL\Type\Definition\NonNull;
use pjmd89\GraphQLGraphQL\Validator\ValidationContext;
use function sprintf;

class ProvidedNonNullArguments extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::FIELD     => [
                'leave' => static function (FieldNode $fieldNode) use ($context) {
                    $fieldDef = $context->getFieldDef();

                    if (! $fieldDef) {
                        return Visitor::skipNode();
                    }
                    $argNodes = $fieldNode->arguments ?: [];

                    $argNodeMap = [];
                    foreach ($argNodes as $argNode) {
                        $argNodeMap[$argNode->name->value] = $argNodes;
                    }
                    foreach ($fieldDef->args as $argDef) {
                        $argNode = $argNodeMap[$argDef->name] ?? null;
                        if ($argNode || ! ($argDef->getType() instanceof NonNull)) {
                            continue;
                        }

                        $context->reportError(new Error(
                            self::missingFieldArgMessage($fieldNode->name->value, $argDef->name, $argDef->getType()),
                            [$fieldNode]
                        ));
                    }
                },
            ],
            NodeKind::DIRECTIVE => [
                'leave' => static function (DirectiveNode $directiveNode) use ($context) {
                    $directiveDef = $context->getDirective();
                    if (! $directiveDef) {
                        return Visitor::skipNode();
                    }
                    $argNodes   = $directiveNode->arguments ?: [];
                    $argNodeMap = [];
                    foreach ($argNodes as $argNode) {
                        $argNodeMap[$argNode->name->value] = $argNodes;
                    }

                    foreach ($directiveDef->args as $argDef) {
                        $argNode = $argNodeMap[$argDef->name] ?? null;
                        if ($argNode || ! ($argDef->getType() instanceof NonNull)) {
                            continue;
                        }

                        $context->reportError(new Error(
                            self::missingDirectiveArgMessage(
                                $directiveNode->name->value,
                                $argDef->name,
                                $argDef->getType()
                            ),
                            [$directiveNode]
                        ));
                    }
                },
            ],
        ];
    }

    public static function missingFieldArgMessage($fieldName, $argName, $type)
    {
        return sprintf(
            'Field "%s" argument "%s" of type "%s" is required but not provided.',
            $fieldName,
            $argName,
            $type
        );
    }

    public static function missingDirectiveArgMessage($directiveName, $argName, $type)
    {
        return sprintf(
            'Directive "@%s" argument "%s" of type "%s" is required but not provided.',
            $directiveName,
            $argName,
            $type
        );
    }
}
