<?php
namespace pjmd89\GraphQL\Examples\Blog\Type;

use pjmd89\GraphQL\Examples\Blog\AppContext;
use pjmd89\GraphQL\Examples\Blog\Data\DataSource;
use pjmd89\GraphQL\Examples\Blog\Data\User;
use pjmd89\GraphQL\Examples\Blog\Types;
use pjmd89\GraphQL\Type\Definition\ObjectType;
use pjmd89\GraphQL\Type\Definition\ResolveInfo;

class UserType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'name' => 'User',
            'description' => 'Our blog authors',
            'fields' => function() {
                return [
                    'id' => Types::id(),
                    'email' => Types::email(),
                    'photo' => [
                        'type' => Types::image(),
                        'description' => 'User photo URL',
                        'args' => [
                            'size' => Types::nonNull(Types::imageSizeEnum()),
                        ]
                    ],
                    'firstName' => [
                        'type' => Types::string(),
                    ],
                    'lastName' => [
                        'type' => Types::string(),
                    ],
                    'lastStoryPosted' => Types::story(),
                    'fieldWithError' => [
                        'type' => Types::string(),
                        'resolve' => function() {
                            throw new \Exception("This is error field");
                        }
                    ]
                ];
            },
            'interfaces' => [
                Types::node()
            ],
            'resolveField' => function($value, $args, $context, ResolveInfo $info) {
                $method = 'resolve' . ucfirst($info->fieldName);
                if (method_exists($this, $method)) {
                    return $this->{$method}($value, $args, $context, $info);
                } else {
                    return $value->{$info->fieldName};
                }
            }
        ];
        parent::__construct($config);
    }

    public function resolvePhoto(User $user, $args)
    {
        return DataSource::getUserPhoto($user->id, $args['size']);
    }

    public function resolveLastStoryPosted(User $user)
    {
        return DataSource::findLastStoryFor($user->id);
    }
}
