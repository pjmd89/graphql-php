<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Validator\Rules;

use pjmd89\GraphQL\Error\Error;
use pjmd89\GraphQLGraphQL\Validator\ValidationContext;

class CustomValidationRule extends ValidationRule
{
    /** @var callable */
    private $visitorFn;

    public function __construct($name, callable $visitorFn)
    {
        $this->name      = $name;
        $this->visitorFn = $visitorFn;
    }

    /**
     * @return Error[]
     */
    public function getVisitor(ValidationContext $context)
    {
        $fn = $this->visitorFn;

        return $fn($context);
    }
}
