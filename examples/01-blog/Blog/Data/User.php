<?php
namespace  pjmd89\GraphQL\Examples\Blog\Data;

use  pjmd89\GraphQL\Utils\Utils;

class User
{
    public $id;

    public $email;

    public $firstName;

    public $lastName;

    public $hasPhoto;

    public function __construct(array $data)
    {
        Utils::assign($this, $data);
    }
}
