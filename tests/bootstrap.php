<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Tests;

use pjmd89\GraphQL\Executor\Executor;
use pjmd89\GraphQL\Experimental\Executor\CoroutineExecutor;
use function getenv;

require_once __DIR__ . '/../vendor/autoload.php';

if (getenv('EXECUTOR') === 'coroutine') {
    Executor::setImplementationFactory([CoroutineExecutor::class, 'create']);
}
