<?php

declare(strict_types=1);

namespace  pjmd89\GraphQL\Language\AST;

class IntValueNode extends Node implements ValueNode
{
    /** @var string */
    public $kind = NodeKind::INT;

    /** @var string */
    public $value;
}
