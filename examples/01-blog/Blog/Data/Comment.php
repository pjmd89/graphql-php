<?php
namespace  pjmd89\GraphQL\Examples\Blog\Data;


use  pjmd89\GraphQL\Utils\Utils;

class Comment
{
    public $id;

    public $authorId;

    public $storyId;

    public $parentId;

    public $body;

    public $isAnonymous;

    public function __construct(array $data)
    {
        Utils::assign($this, $data);
    }
}
