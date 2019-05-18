<?php

declare(strict_types=1);

namespace  pjmd89\GraphQL\Executor;

use  pjmd89\GraphQL\Executor\Promise\Promise;

interface ExecutorImplementation
{
    /**
     * Returns promise of {@link ExecutionResult}. Promise should always resolve, never reject.
     */
    public function doExecute() : Promise;
}
