<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Experimental\Executor;

use pjmd89\GraphQL\Language\AST\ValueNode;
use pjmd89\GraphQLGraphQL\Type\Definition\InputType;

/**
 * @internal
 */
interface Runtime
{
    public function evaluate(ValueNode $valueNode, InputType $type);

    public function addError($error);
}
