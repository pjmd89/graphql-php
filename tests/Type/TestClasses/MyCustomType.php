<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Tests\Type\TestClasses;

use pjmd89\GraphQL\Type\Definition\ObjectType;
use pjmd89\GraphQL\Type\Definition\Type;

class MyCustomType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'fields' => [
                'a' => Type::string(),
            ],
        ];
        parent::__construct($config);
    }
}
