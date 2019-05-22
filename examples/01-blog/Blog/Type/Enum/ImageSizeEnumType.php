<?php
namespace pjmd89\GraphQL\Examples\Blog\Type\Enum;

use pjmd89\GraphQL\Examples\Blog\Data\Image;
use pjmd89\GraphQL\Type\Definition\EnumType;

class ImageSizeEnumType extends EnumType
{
    public function __construct()
    {
        $config = [
            // Note: 'name' option is not needed in this form - it will be inferred from className
            'values' => [
                'ICON' => Image::SIZE_ICON,
                'SMALL' => Image::SIZE_SMALL,
                'MEDIUM' => Image::SIZE_MEDIUM,
                'ORIGINAL' => Image::SIZE_ORIGINAL
            ]
        ];

        parent::__construct($config);
    }
}
