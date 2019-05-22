<?php

declare(strict_types=1);

namespace  pjmd89\GraphQL\Language\AST;

class DocumentNode extends Node
{
    /** @var string */
    public $kind = NodeKind::DOCUMENT;

    /** @var NodeList|DefinitionNode[] */
    public $definitions;
}
