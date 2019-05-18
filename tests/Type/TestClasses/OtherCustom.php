<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Tests\Type\TestClasses;

use pjmd89\GraphQL\Type\Definition\ObjectType;
use pjmd89\GraphQL\Type\Definition\Type;

/**
 * Note: named OtherCustom vs OtherCustomType intentionally
 */
class OtherCustom extends ObjectType
{
    public function __construct()
    {
        $config = [
            'fields' => [
                'b' => Type::string(),
            ],
        ];
        parent::__construct($config);
    }
}
