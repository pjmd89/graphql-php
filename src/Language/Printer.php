<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Language;

use pjmd89\GraphQL\Language\AST\ArgumentNode;
use pjmd89\GraphQLGraphQL\Language\AST\BooleanValueNode;
use pjmd89\GraphQLGraphQL\Language\AST\DirectiveDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\DirectiveNode;
use pjmd89\GraphQLGraphQL\Language\AST\DocumentNode;
use pjmd89\GraphQLGraphQL\Language\AST\EnumTypeDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\EnumTypeExtensionNode;
use pjmd89\GraphQLGraphQL\Language\AST\EnumValueDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\EnumValueNode;
use pjmd89\GraphQLGraphQL\Language\AST\FieldDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\FieldNode;
use pjmd89\GraphQLGraphQL\Language\AST\FloatValueNode;
use pjmd89\GraphQLGraphQL\Language\AST\FragmentDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\FragmentSpreadNode;
use pjmd89\GraphQLGraphQL\Language\AST\InlineFragmentNode;
use pjmd89\GraphQLGraphQL\Language\AST\InputObjectTypeDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\InputObjectTypeExtensionNode;
use pjmd89\GraphQLGraphQL\Language\AST\InputValueDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\InterfaceTypeDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\InterfaceTypeExtensionNode;
use pjmd89\GraphQLGraphQL\Language\AST\IntValueNode;
use pjmd89\GraphQLGraphQL\Language\AST\ListTypeNode;
use pjmd89\GraphQLGraphQL\Language\AST\ListValueNode;
use pjmd89\GraphQLGraphQL\Language\AST\NamedTypeNode;
use pjmd89\GraphQLGraphQL\Language\AST\Node;
use pjmd89\GraphQLGraphQL\Language\AST\NodeKind;
use pjmd89\GraphQLGraphQL\Language\AST\NonNullTypeNode;
use pjmd89\GraphQLGraphQL\Language\AST\NullValueNode;
use pjmd89\GraphQLGraphQL\Language\AST\ObjectFieldNode;
use pjmd89\GraphQLGraphQL\Language\AST\ObjectTypeDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\ObjectTypeExtensionNode;
use pjmd89\GraphQLGraphQL\Language\AST\ObjectValueNode;
use pjmd89\GraphQLGraphQL\Language\AST\OperationDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\OperationTypeDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\ScalarTypeDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\ScalarTypeExtensionNode;
use pjmd89\GraphQLGraphQL\Language\AST\SchemaDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\SchemaTypeExtensionNode;
use pjmd89\GraphQLGraphQL\Language\AST\SelectionSetNode;
use pjmd89\GraphQLGraphQL\Language\AST\StringValueNode;
use pjmd89\GraphQLGraphQL\Language\AST\UnionTypeDefinitionNode;
use pjmd89\GraphQLGraphQL\Language\AST\UnionTypeExtensionNode;
use pjmd89\GraphQLGraphQL\Language\AST\VariableDefinitionNode;
use pjmd89\GraphQLGraphQL\Utils\Utils;
use function count;
use function implode;
use function json_encode;
use function preg_replace;
use function sprintf;
use function str_replace;
use function strpos;

/**
 * Prints AST to string. Capable of printing GraphQL queries and Type definition language.
 * Useful for pretty-printing queries or printing back AST for logging, documentation, etc.
 *
 * Usage example:
 *
 * ```php
 * $query = 'query myQuery {someField}';
 * $ast = GraphQL\Language\Parser::parse($query);
 * $printed = GraphQL\Language\Printer::doPrint($ast);
 * ```
 */
class Printer
{
    /**
     * Prints AST to string. Capable of printing GraphQL queries and Type definition language.
     *
     * @param Node $ast
     *
     * @return string
     *
     * @api
     */
    public static function doPrint($ast)
    {
        static $instance;
        $instance = $instance ?: new static();

        return $instance->printAST($ast);
    }

    protected function __construct()
    {
    }

    public function printAST($ast)
    {
        return Visitor::visit(
            $ast,
            [
                'leave' => [
                    NodeKind::NAME => static function (Node $node) {
                        return '' . $node->value;
                    },

                    NodeKind::VARIABLE => static function ($node) {
                        return '$' . $node->name;
                    },

                    NodeKind::DOCUMENT => function (DocumentNode $node) {
                        return $this->join($node->definitions, "\n\n") . "\n";
                    },

                    NodeKind::OPERATION_DEFINITION => function (OperationDefinitionNode $node) {
                        $op           = $node->operation;
                        $name         = $node->name;
                        $varDefs      = $this->wrap('(', $this->join($node->variableDefinitions, ', '), ')');
                        $directives   = $this->join($node->directives, ' ');
                        $selectionSet = $node->selectionSet;

                        // Anonymous queries with no directives or variable definitions can use
                        // the query short form.
                        return ! $name && ! $directives && ! $varDefs && $op === 'query'
                            ? $selectionSet
                            : $this->join([$op, $this->join([$name, $varDefs]), $directives, $selectionSet], ' ');
                    },

                    NodeKind::VARIABLE_DEFINITION => function (VariableDefinitionNode $node) {
                        return $node->variable . ': ' . $node->type . $this->wrap(' = ', $node->defaultValue);
                    },

                    NodeKind::SELECTION_SET => function (SelectionSetNode $node) {
                        return $this->block($node->selections);
                    },

                    NodeKind::FIELD => function (FieldNode $node) {
                        return $this->join(
                            [
                                $this->wrap('', $node->alias, ': ') . $node->name . $this->wrap(
                                    '(',
                                    $this->join($node->arguments, ', '),
                                    ')'
                                ),
                                $this->join($node->directives, ' '),
                                $node->selectionSet,
                            ],
                            ' '
                        );
                    },

                    NodeKind::ARGUMENT => static function (ArgumentNode $node) {
                        return $node->name . ': ' . $node->value;
                    },

                    NodeKind::FRAGMENT_SPREAD => function (FragmentSpreadNode $node) {
                        return '...' . $node->name . $this->wrap(' ', $this->join($node->directives, ' '));
                    },

                    NodeKind::INLINE_FRAGMENT => function (InlineFragmentNode $node) {
                        return $this->join(
                            [
                                '...',
                                $this->wrap('on ', $node->typeCondition),
                                $this->join($node->directives, ' '),
                                $node->selectionSet,
                            ],
                            ' '
                        );
                    },

                    NodeKind::FRAGMENT_DEFINITION => function (FragmentDefinitionNode $node) {
                        // Note: fragment variable definitions are experimental and may be changed or removed in the future.
                        return sprintf('fragment %s', $node->name)
                            . $this->wrap('(', $this->join($node->variableDefinitions, ', '), ')')
                            . sprintf(' on %s ', $node->typeCondition)
                            . $this->wrap('', $this->join($node->directives, ' '), ' ')
                            . $node->selectionSet;
                    },

                    NodeKind::INT => static function (IntValueNode $node) {
                        return $node->value;
                    },

                    NodeKind::FLOAT => static function (FloatValueNode $node) {
                        return $node->value;
                    },

                    NodeKind::STRING => function (StringValueNode $node, $key) {
                        if ($node->block) {
                            return $this->printBlockString($node->value, $key === 'description');
                        }

                        return json_encode($node->value);
                    },

                    NodeKind::BOOLEAN => static function (BooleanValueNode $node) {
                        return $node->value ? 'true' : 'false';
                    },

                    NodeKind::NULL => static function (NullValueNode $node) {
                        return 'null';
                    },

                    NodeKind::ENUM => static function (EnumValueNode $node) {
                        return $node->value;
                    },

                    NodeKind::LST => function (ListValueNode $node) {
                        return '[' . $this->join($node->values, ', ') . ']';
                    },

                    NodeKind::OBJECT => function (ObjectValueNode $node) {
                        return '{' . $this->join($node->fields, ', ') . '}';
                    },

                    NodeKind::OBJECT_FIELD => static function (ObjectFieldNode $node) {
                        return $node->name . ': ' . $node->value;
                    },

                    NodeKind::DIRECTIVE => function (DirectiveNode $node) {
                        return '@' . $node->name . $this->wrap('(', $this->join($node->arguments, ', '), ')');
                    },

                    NodeKind::NAMED_TYPE => static function (NamedTypeNode $node) {
                        return $node->name;
                    },

                    NodeKind::LIST_TYPE => static function (ListTypeNode $node) {
                        return '[' . $node->type . ']';
                    },

                    NodeKind::NON_NULL_TYPE => static function (NonNullTypeNode $node) {
                        return $node->type . '!';
                    },

                    NodeKind::SCHEMA_DEFINITION => function (SchemaDefinitionNode $def) {
                        return $this->join(
                            [
                                'schema',
                                $this->join($def->directives, ' '),
                                $this->block($def->operationTypes),
                            ],
                            ' '
                        );
                    },

                    NodeKind::OPERATION_TYPE_DEFINITION => static function (OperationTypeDefinitionNode $def) {
                        return $def->operation . ': ' . $def->type;
                    },

                    NodeKind::SCALAR_TYPE_DEFINITION => $this->addDescription(function (ScalarTypeDefinitionNode $def) {
                        return $this->join(['scalar', $def->name, $this->join($def->directives, ' ')], ' ');
                    }),

                    NodeKind::OBJECT_TYPE_DEFINITION => $this->addDescription(function (ObjectTypeDefinitionNode $def) {
                        return $this->join(
                            [
                                'type',
                                $def->name,
                                $this->wrap('implements ', $this->join($def->interfaces, ' & ')),
                                $this->join($def->directives, ' '),
                                $this->block($def->fields),
                            ],
                            ' '
                        );
                    }),

                    NodeKind::FIELD_DEFINITION => $this->addDescription(function (FieldDefinitionNode $def) {
                        $noIndent = Utils::every($def->arguments, static function (string $arg) {
                            return strpos($arg, "\n") === false;
                        });

                        return $def->name
                            . ($noIndent
                                ? $this->wrap('(', $this->join($def->arguments, ', '), ')')
                                : $this->wrap("(\n", $this->indent($this->join($def->arguments, "\n")), "\n)"))
                            . ': ' . $def->type
                            . $this->wrap(' ', $this->join($def->directives, ' '));
                    }),

                    NodeKind::INPUT_VALUE_DEFINITION => $this->addDescription(function (InputValueDefinitionNode $def) {
                        return $this->join(
                            [
                                $def->name . ': ' . $def->type,
                                $this->wrap('= ', $def->defaultValue),
                                $this->join($def->directives, ' '),
                            ],
                            ' '
                        );
                    }),

                    NodeKind::INTERFACE_TYPE_DEFINITION => $this->addDescription(
                        function (InterfaceTypeDefinitionNode $def) {
                            return $this->join(
                                [
                                    'interface',
                                    $def->name,
                                    $this->join($def->directives, ' '),
                                    $this->block($def->fields),
                                ],
                                ' '
                            );
                        }
                    ),

                    NodeKind::UNION_TYPE_DEFINITION => $this->addDescription(function (UnionTypeDefinitionNode $def) {
                        return $this->join(
                            [
                                'union',
                                $def->name,
                                $this->join($def->directives, ' '),
                                $def->types
                                    ? '= ' . $this->join($def->types, ' | ')
                                    : '',
                            ],
                            ' '
                        );
                    }),

                    NodeKind::ENUM_TYPE_DEFINITION => $this->addDescription(function (EnumTypeDefinitionNode $def) {
                        return $this->join(
                            [
                                'enum',
                                $def->name,
                                $this->join($def->directives, ' '),
                                $this->block($def->values),
                            ],
                            ' '
                        );
                    }),

                    NodeKind::ENUM_VALUE_DEFINITION => $this->addDescription(function (EnumValueDefinitionNode $def) {
                        return $this->join([$def->name, $this->join($def->directives, ' ')], ' ');
                    }),

                    NodeKind::INPUT_OBJECT_TYPE_DEFINITION => $this->addDescription(function (
                        InputObjectTypeDefinitionNode $def
                    ) {
                        return $this->join(
                            [
                                'input',
                                $def->name,
                                $this->join($def->directives, ' '),
                                $this->block($def->fields),
                            ],
                            ' '
                        );
                    }),

                    NodeKind::SCHEMA_EXTENSION => function (SchemaTypeExtensionNode $def) {
                        return $this->join(
                            [
                                'extend schema',
                                $this->join($def->directives, ' '),
                                $this->block($def->operationTypes),
                            ],
                            ' '
                        );
                    },

                    NodeKind::SCALAR_TYPE_EXTENSION => function (ScalarTypeExtensionNode $def) {
                        return $this->join(
                            [
                                'extend scalar',
                                $def->name,
                                $this->join($def->directives, ' '),
                            ],
                            ' '
                        );
                    },

                    NodeKind::OBJECT_TYPE_EXTENSION => function (ObjectTypeExtensionNode $def) {
                        return $this->join(
                            [
                                'extend type',
                                $def->name,
                                $this->wrap('implements ', $this->join($def->interfaces, ' & ')),
                                $this->join($def->directives, ' '),
                                $this->block($def->fields),
                            ],
                            ' '
                        );
                    },

                    NodeKind::INTERFACE_TYPE_EXTENSION => function (InterfaceTypeExtensionNode $def) {
                        return $this->join(
                            [
                                'extend interface',
                                $def->name,
                                $this->join($def->directives, ' '),
                                $this->block($def->fields),
                            ],
                            ' '
                        );
                    },

                    NodeKind::UNION_TYPE_EXTENSION => function (UnionTypeExtensionNode $def) {
                        return $this->join(
                            [
                                'extend union',
                                $def->name,
                                $this->join($def->directives, ' '),
                                $def->types
                                    ? '= ' . $this->join($def->types, ' | ')
                                    : '',
                            ],
                            ' '
                        );
                    },

                    NodeKind::ENUM_TYPE_EXTENSION => function (EnumTypeExtensionNode $def) {
                        return $this->join(
                            [
                                'extend enum',
                                $def->name,
                                $this->join($def->directives, ' '),
                                $this->block($def->values),
                            ],
                            ' '
                        );
                    },

                    NodeKind::INPUT_OBJECT_TYPE_EXTENSION => function (InputObjectTypeExtensionNode $def) {
                        return $this->join(
                            [
                                'extend input',
                                $def->name,
                                $this->join($def->directives, ' '),
                                $this->block($def->fields),
                            ],
                            ' '
                        );
                    },

                    NodeKind::DIRECTIVE_DEFINITION => $this->addDescription(function (DirectiveDefinitionNode $def) {
                        $noIndent = Utils::every($def->arguments, static function (string $arg) {
                            return strpos($arg, "\n") === false;
                        });

                        return 'directive @'
                            . $def->name
                            . ($noIndent
                                ? $this->wrap('(', $this->join($def->arguments, ', '), ')')
                                : $this->wrap("(\n", $this->indent($this->join($def->arguments, "\n")), "\n"))
                            . ' on ' . $this->join($def->locations, ' | ');
                    }),
                ],
            ]
        );
    }

    public function addDescription(callable $cb)
    {
        return function ($node) use ($cb) {
            return $this->join([$node->description, $cb($node)], "\n");
        };
    }

    /**
     * If maybeString is not null or empty, then wrap with start and end, otherwise
     * print an empty string.
     */
    public function wrap($start, $maybeString, $end = '')
    {
        return $maybeString ? ($start . $maybeString . $end) : '';
    }

    /**
     * Given array, print each item on its own line, wrapped in an
     * indented "{ }" block.
     */
    public function block($array)
    {
        return $array && $this->length($array)
            ? "{\n" . $this->indent($this->join($array, "\n")) . "\n}"
            : '';
    }

    public function indent($maybeString)
    {
        return $maybeString ? '  ' . str_replace("\n", "\n  ", $maybeString) : '';
    }

    public function manyList($start, $list, $separator, $end)
    {
        return $this->length($list) === 0 ? null : ($start . $this->join($list, $separator) . $end);
    }

    public function length($maybeArray)
    {
        return $maybeArray ? count($maybeArray) : 0;
    }

    public function join($maybeArray, $separator = '')
    {
        return $maybeArray
            ? implode(
                $separator,
                Utils::filter(
                    $maybeArray,
                    static function ($x) {
                        return (bool) $x;
                    }
                )
            )
            : '';
    }

    /**
     * Print a block string in the indented block form by adding a leading and
     * trailing blank line. However, if a block string starts with whitespace and is
     * a single-line, adding a leading blank line would strip that whitespace.
     */
    private function printBlockString($value, $isDescription)
    {
        $escaped = str_replace('"""', '\\"""', $value);

        return ($value[0] === ' ' || $value[0] === "\t") && strpos($value, "\n") === false
            ? ('"""' . preg_replace('/"$/', "\"\n", $escaped) . '"""')
            : ('"""' . "\n" . ($isDescription ? $escaped : $this->indent($escaped)) . "\n" . '"""');
    }
}
