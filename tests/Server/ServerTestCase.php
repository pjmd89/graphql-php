<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Tests\Server;

use pjmd89\GraphQL\Deferred;
use pjmd89\GraphQL\Error\UserError;
use pjmd89\GraphQL\Type\Definition\ObjectType;
use pjmd89\GraphQL\Type\Definition\Type;
use pjmd89\GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;
use function trigger_error;
use const E_USER_DEPRECATED;
use const E_USER_NOTICE;
use const E_USER_WARNING;

abstract class ServerTestCase extends TestCase
{
    protected function buildSchema()
    {
        return new Schema([
            'query'    => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'f1'                      => [
                        'type'    => Type::string(),
                        'resolve' => static function ($root, $args, $context, $info) {
                            return $info->fieldName;
                        },
                    ],
                    'fieldWithPhpError'       => [
                        'type'    => Type::string(),
                        'resolve' => static function ($root, $args, $context, $info) {
                            trigger_error('deprecated', E_USER_DEPRECATED);
                            trigger_error('notice', E_USER_NOTICE);
                            trigger_error('warning', E_USER_WARNING);
                            $a = [];
                            $a['test']; // should produce PHP notice

                            return $info->fieldName;
                        },
                    ],
                    'fieldWithSafeException' => [
                        'type' => Type::string(),
                        'resolve' => static function () {
                            throw new UserError('This is the exception we want');
                        },
                    ],
                    'fieldWithUnsafeException' => [
                        'type' => Type::string(),
                        'resolve' => static function () {
                            throw new Unsafe('This exception should not be shown to the user');
                        },
                    ],
                    'testContextAndRootValue' => [
                        'type'    => Type::string(),
                        'resolve' => static function ($root, $args, $context, $info) {
                            $context->testedRootValue = $root;

                            return $info->fieldName;
                        },
                    ],
                    'fieldWithArg'            => [
                        'type'    => Type::string(),
                        'args'    => [
                            'arg' => [
                                'type' => Type::nonNull(Type::string()),
                            ],
                        ],
                        'resolve' => static function ($root, $args) {
                            return $args['arg'];
                        },
                    ],
                    'dfd'                     => [
                        'type'    => Type::string(),
                        'args'    => [
                            'num' => [
                                'type' => Type::nonNull(Type::int()),
                            ],
                        ],
                        'resolve' => static function ($root, $args, $context) {
                            $context['buffer']($args['num']);

                            return new Deferred(static function () use ($args, $context) {
                                return $context['load']($args['num']);
                            });
                        },
                    ],
                ],
            ]),
            'mutation' => new ObjectType([
                'name'   => 'Mutation',
                'fields' => [
                    'm1' => [
                        'type' => new ObjectType([
                            'name'   => 'TestMutation',
                            'fields' => [
                                'result' => Type::string(),
                            ],
                        ]),
                    ],
                ],
            ]),
        ]);
    }
}
