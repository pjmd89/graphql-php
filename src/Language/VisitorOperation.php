<?php

declare(strict_types=1);

namespace  pjmd89\GraphQL\Language;

class VisitorOperation
{
    /** @var bool */
    public $doBreak;

    /** @var bool */
    public $doContinue;

    /** @var bool */
    public $removeNode;
}
