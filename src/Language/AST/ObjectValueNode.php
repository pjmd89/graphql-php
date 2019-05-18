<?php

declare(strict_types=1);

namespace  pjmd89\GraphQL\Language\AST;

class ObjectValueNode extends Node implements ValueNode
{
    /** @var string */
    public $kind = NodeKind::OBJECT;

    /** @var ObjectFieldNode[]|NodeList */
    public $fields;
}
