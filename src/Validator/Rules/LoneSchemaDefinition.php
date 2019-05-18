<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Validator\Rules;

use pjmd89\GraphQL\Error\Error;
use pjmd89\GraphQLGraphQL\Language\AST\NodeKind;
use pjmd89\GraphQLGraphQL\Language\AST\SchemaDefinitionNode;
use pjmd89\GraphQLGraphQL\Validator\ValidationContext;

/**
 * Lone Schema definition
 *
 * A GraphQL document is only valid if it contains only one schema definition.
 */
class LoneSchemaDefinition extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        $oldSchema      = $context->getSchema();
        $alreadyDefined = $oldSchema !== null ? (
            $oldSchema->getAstNode() ||
            $oldSchema->getQueryType() ||
            $oldSchema->getMutationType() ||
            $oldSchema->getSubscriptionType()
        ) : false;

        $schemaDefinitionsCount = 0;

        return [
            NodeKind::SCHEMA_DEFINITION => static function (SchemaDefinitionNode $node) use ($alreadyDefined, $context, &$schemaDefinitionsCount) {
                if ($alreadyDefined !== false) {
                    $context->reportError(new Error('Cannot define a new schema within a schema extension.', $node));

                    return;
                }

                if ($schemaDefinitionsCount > 0) {
                    $context->reportError(new Error('Must provide only one schema definition.', $node));
                }

                ++$schemaDefinitionsCount;
            },
        ];
    }
}
