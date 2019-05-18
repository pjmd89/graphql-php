<?php
namespace pjmd89\GraphQL\Examples\Blog\Type;

use pjmd89\GraphQL\Examples\Blog\Data\Story;
use pjmd89\GraphQLGraphQL\Examples\Blog\Data\User;
use pjmd89\GraphQLGraphQL\Examples\Blog\Types;
use pjmd89\GraphQLGraphQL\Type\Definition\UnionType;

class SearchResultType extends UnionType
{
    public function __construct()
    {
        $config = [
            'name' => 'SearchResultType',
            'types' => function() {
                return [
                    Types::story(),
                    Types::user()
                ];
            },
            'resolveType' => function($value) {
                if ($value instanceof Story) {
                    return Types::story();
                } else if ($value instanceof User) {
                    return Types::user();
                }
            }
        ];
        parent::__construct($config);
    }
}
