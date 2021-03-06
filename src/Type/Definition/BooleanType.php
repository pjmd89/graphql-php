<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Type\Definition;

use Exception;
use pjmd89\GraphQL\Error\Error;
use pjmd89\GraphQL\Language\AST\BooleanValueNode;
use pjmd89\GraphQL\Language\AST\Node;
use pjmd89\GraphQL\Utils\Utils;
use function is_bool;

class BooleanType extends ScalarType
{
    /** @var string */
    public $name = Type::BOOLEAN;

    /** @var string */
    public $description = 'The `Boolean` scalar type represents `true` or `false`.';

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public function serialize($value)
    {
        return (bool) $value;
    }

    /**
     * @param mixed $value
     *
     * @return bool
     *
     * @throws Error
     */
    public function parseValue($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        throw new Error('Cannot represent value as boolean: ' . Utils::printSafe($value));
    }

    /**
     * @param Node         $valueNode
     * @param mixed[]|null $variables
     *
     * @return bool|null
     *
     * @throws Exception
     */
    public function parseLiteral($valueNode, ?array $variables = null)
    {
        if (! $valueNode instanceof BooleanValueNode) {
            // Intentionally without message, as all information already in wrapped Exception
            throw new Exception();
        }

        return $valueNode->value;
    }
}
