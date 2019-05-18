<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Tests\Executor\TestClasses;

class NumberHolder
{
    /** @var float */
    public $theNumber;

    public function __construct(float $originalNumber)
    {
        $this->theNumber = $originalNumber;
    }
}
