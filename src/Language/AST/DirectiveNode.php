<?php

declare(strict_types=1);

namespace  pjmd89\GraphQL\Language\AST;

class DirectiveNode extends Node
{
    /** @var string */
    public $kind = NodeKind::DIRECTIVE;

    /** @var NameNode */
    public $name;

    /** @var ArgumentNode[] */
    public $arguments;
}
