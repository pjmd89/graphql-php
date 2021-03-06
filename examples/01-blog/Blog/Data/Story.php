<?php
namespace  pjmd89\GraphQL\Examples\Blog\Data;

use  pjmd89\GraphQL\Utils\Utils;

class Story
{
    public $id;

    public $authorId;

    public $title;

    public $body;

    public $isAnonymous = false;

    public function __construct(array $data)
    {
        Utils::assign($this, $data);
    }
}
