<?php

declare(strict_types=1);

namespace  pjmd89\GraphQL\Language\AST;

class EnumValueNode extends Node implements ValueNode
{
    /** @var string */
    public $kind = NodeKind::ENUM;

    /** @var string */
    public $value;
}
