<?php
namespace pjmd89\GraphQL\Examples\Blog\Type;

use pjmd89\GraphQL\Examples\Blog\Data\Story;
use pjmd89\GraphQLGraphQL\Examples\Blog\Data\User;
use pjmd89\GraphQLGraphQL\Examples\Blog\Data\Image;
use pjmd89\GraphQLGraphQL\Examples\Blog\Types;
use pjmd89\GraphQLGraphQL\Type\Definition\InterfaceType;

class NodeType extends InterfaceType
{
    public function __construct()
    {
        $config = [
            'name' => 'Node',
            'fields' => [
                'id' => Types::id()
            ],
            'resolveType' => [$this, 'resolveNodeType']
        ];
        parent::__construct($config);
    }

    public function resolveNodeType($object)
    {
        if ($object instanceof User) {
            return Types::user();
        } else if ($object instanceof Image) {
            return Types::image();
        } else if ($object instanceof Story) {
            return Types::story();
        }
    }
}
