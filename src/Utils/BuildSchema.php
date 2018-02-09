<?php
namespace GraphQL\Utils;

use GraphQL\Error\Error;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\TypeNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Language\Token;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Introspection;

/**
 * Build instance of `GraphQL\Type\Schema` out of type language definition (string or parsed AST)
 * See [section in docs](type-system/type-language.md) for details.
 */
class BuildSchema
{
    /**
     * @param Type $innerType
     * @param TypeNode $inputTypeNode
     * @return Type
     */
    private function buildWrappedType(Type $innerType, TypeNode $inputTypeNode)
    {
        if ($inputTypeNode->kind == NodeKind::LIST_TYPE) {
            return Type::listOf($this->buildWrappedType($innerType, $inputTypeNode->type));
        }
        if ($inputTypeNode->kind == NodeKind::NON_NULL_TYPE) {
            $wrappedType = $this->buildWrappedType($innerType, $inputTypeNode->type);
            Utils::invariant(!($wrappedType instanceof NonNull), 'No nesting nonnull.');
            return Type::nonNull($wrappedType);
        }
        return $innerType;
    }

    private function getNamedTypeNode(TypeNode $typeNode)
    {
        $namedType = $typeNode;
        while ($namedType->kind === NodeKind::LIST_TYPE || $namedType->kind === NodeKind::NON_NULL_TYPE) {
            $namedType = $namedType->type;
        }
        return $namedType;
    }

    /**
     * This takes the ast of a schema document produced by the parse function in
     * GraphQL\Language\Parser.
     *
     * If no schema definition is provided, then it will look for types named Query
     * and Mutation.
     *
     * Given that AST it constructs a GraphQL\Type\Schema. The resulting schema
     * has no resolve methods, so execution will use default resolvers.
     *
     * Accepts options as a third argument:
     *
     *    - commentDescriptions:
     *        Provide true to use preceding comments as the description.
     *
     *
     * @api
     * @param DocumentNode $ast
     * @param callable $typeConfigDecorator
     * @return Schema
     * @throws Error
     */
    public static function buildAST(DocumentNode $ast, callable $typeConfigDecorator = null, array $options = [])
    {
        $builder = new self($ast, $typeConfigDecorator, $options);
        return $builder->buildSchema();
    }

    private $ast;
    private $innerTypeMap;
    private $nodeMap;
    private $typeConfigDecorator;
    private $loadedTypeDefs;
    private $options;

    public function __construct(DocumentNode $ast, callable $typeConfigDecorator = null, array $options = [])
    {
        $this->ast = $ast;
        $this->typeConfigDecorator = $typeConfigDecorator;
        $this->loadedTypeDefs = [];
        $this->options = $options;
    }

    public function buildSchema()
    {
        /** @var SchemaDefinitionNode $schemaDef */
        $schemaDef = null;
        $typeDefs = [];
        $this->nodeMap = [];
        $directiveDefs = [];
        foreach ($this->ast->definitions as $d) {
            switch ($d->kind) {
                case NodeKind::SCHEMA_DEFINITION:
                    if ($schemaDef) {
                        throw new Error('Must provide only one schema definition.');
                    }
                    $schemaDef = $d;
                    break;
                case NodeKind::SCALAR_TYPE_DEFINITION:
                case NodeKind::OBJECT_TYPE_DEFINITION:
                case NodeKind::INTERFACE_TYPE_DEFINITION:
                case NodeKind::ENUM_TYPE_DEFINITION:
                case NodeKind::UNION_TYPE_DEFINITION:
                case NodeKind::INPUT_OBJECT_TYPE_DEFINITION:
                    $typeName = $d->name->value;
                    if (!empty($this->nodeMap[$typeName])) {
                        throw new Error("Type \"$typeName\" was defined more than once.");
                    }
                    $typeDefs[] = $d;
                    $this->nodeMap[$typeName] = $d;
                    break;
                case NodeKind::DIRECTIVE_DEFINITION:
                    $directiveDefs[] = $d;
                    break;
            }
        }

        $operationTypes = $schemaDef
            ? $this->getOperationTypes($schemaDef)
            : [
                'query' => isset($this->nodeMap['Query']) ? 'Query' : null,
                'mutation' => isset($this->nodeMap['Mutation']) ? 'Mutation' : null,
                'subscription' => isset($this->nodeMap['Subscription']) ? 'Subscription' : null,
            ];

        $this->innerTypeMap = [
            'String' => Type::string(),
            'Int' => Type::int(),
            'Float' => Type::float(),
            'Boolean' => Type::boolean(),
            'ID' => Type::id(),
            '__Schema' => Introspection::_schema(),
            '__Directive' => Introspection::_directive(),
            '__DirectiveLocation' => Introspection::_directiveLocation(),
            '__Type' => Introspection::_type(),
            '__Field' => Introspection::_field(),
            '__InputValue' => Introspection::_inputValue(),
            '__EnumValue' => Introspection::_enumValue(),
            '__TypeKind' => Introspection::_typeKind(),
        ];

        $directives = array_map([$this, 'getDirective'], $directiveDefs);

        // If specified directives were not explicitly declared, add them.
        $skip = array_reduce($directives, function ($hasSkip, $directive) {
            return $hasSkip || $directive->name == 'skip';
        });
        if (!$skip) {
            $directives[] = Directive::skipDirective();
        }

        $include = array_reduce($directives, function ($hasInclude, $directive) {
            return $hasInclude || $directive->name == 'include';
        });
        if (!$include) {
            $directives[] = Directive::includeDirective();
        }

        $deprecated = array_reduce($directives, function ($hasDeprecated, $directive) {
            return $hasDeprecated || $directive->name == 'deprecated';
        });
        if (!$deprecated) {
            $directives[] = Directive::deprecatedDirective();
        }

        if (!isset($operationTypes['query'])) {
            throw new Error(
                'Must provide schema definition with query type or a type named Query.'
            );
        }

        $schema = new Schema([
            'query' => $this->getObjectType($operationTypes['query']),
            'mutation' => isset($operationTypes['mutation']) ?
                $this->getObjectType($operationTypes['mutation']) :
                null,
            'subscription' => isset($operationTypes['subscription']) ?
                $this->getObjectType($operationTypes['subscription']) :
                null,
            'typeLoader' => function ($name) {
                return $this->typeDefNamed($name);
            },
            'directives' => $directives,
            'astNode' => $schemaDef,
            'types' => function () {
                $types = [];
                foreach ($this->nodeMap as $name => $def) {
                    if (!isset($this->loadedTypeDefs[$name])) {
                        $types[] = $this->typeDefNamed($def->name->value);
                    }
                }
                return $types;
            }
        ]);

        return $schema;
    }

    /**
     * @param SchemaDefinitionNode $schemaDef
     * @return array
     * @throws Error
     */
    private function getOperationTypes($schemaDef)
    {
        $opTypes = [];

        foreach ($schemaDef->operationTypes as $operationType) {
            $typeName = $operationType->type->name->value;
            $operation = $operationType->operation;

            if (isset($opTypes[$operation])) {
                throw new Error("Must provide only one $operation type in schema.");
            }

            if (!isset($this->nodeMap[$typeName])) {
                throw new Error("Specified $operation type \"$typeName\" not found in document.");
            }

            $opTypes[$operation] = $typeName;
        }

        return $opTypes;
    }

    private function getDirective(DirectiveDefinitionNode $directiveNode)
    {
        return new Directive([
            'name' => $directiveNode->name->value,
            'description' => $this->getDescription($directiveNode),
            'locations' => Utils::map($directiveNode->locations, function ($node) {
                return $node->value;
            }),
            'args' => $directiveNode->arguments ? FieldArgument::createMap($this->makeInputValues($directiveNode->arguments)) : null,
            'astNode' => $directiveNode
        ]);
    }

    /**
     * @param string $name
     * @return CustomScalarType|EnumType|InputObjectType|UnionType
     * @throws Error
     */
    private function getObjectType($name)
    {
        $type = $this->typeDefNamed($name);
        Utils::invariant(
            $type instanceof ObjectType,
            'AST must provide object type.'
        );
        return $type;
    }

    private function produceType(TypeNode $typeNode)
    {
        $typeName = $this->getNamedTypeNode($typeNode)->name->value;
        $typeDef = $this->typeDefNamed($typeName);
        return $this->buildWrappedType($typeDef, $typeNode);
    }

    private function produceInputType(TypeNode $typeNode)
    {
        $type = $this->produceType($typeNode);
        Utils::invariant(Type::isInputType($type), 'Expected Input type.');
        return $type;
    }

    private function produceOutputType(TypeNode $typeNode)
    {
        $type = $this->produceType($typeNode);
        Utils::invariant(Type::isOutputType($type), 'Expected Input type.');
        return $type;
    }

    private function produceObjectType(TypeNode $typeNode)
    {
        $type = $this->produceType($typeNode);
        Utils::invariant($type instanceof ObjectType, 'Expected Object type.');
        return $type;
    }

    private function produceInterfaceType(TypeNode $typeNode)
    {
        $type = $this->produceType($typeNode);
        Utils::invariant($type instanceof InterfaceType, 'Expected Interface type.');
        return $type;
    }

    private function typeDefNamed($typeName)
    {
        if (isset($this->innerTypeMap[$typeName])) {
            return $this->innerTypeMap[$typeName];
        }

        if (!isset($this->nodeMap[$typeName])) {
            throw new Error('Type "' . $typeName . '" not found in document.');
        }

        $this->loadedTypeDefs[$typeName] = true;

        $config = $this->makeSchemaDefConfig($this->nodeMap[$typeName]);

        if ($this->typeConfigDecorator) {
            $fn = $this->typeConfigDecorator;
            try {
                $config = $fn($config, $this->nodeMap[$typeName], $this->nodeMap);
            } catch (\Exception $e) {
                throw new Error(
                    "Type config decorator passed to " . (static::class) . " threw an error " .
                    "when building $typeName type: {$e->getMessage()}",
                    null,
                    null,
                    null,
                    null,
                    $e
                );
            } catch (\Throwable $e) {
                throw new Error(
                    "Type config decorator passed to " . (static::class) . " threw an error " .
                    "when building $typeName type: {$e->getMessage()}",
                    null,
                    null,
                    null,
                    null,
                    $e
                );
            }
            if (!is_array($config) || isset($config[0])) {
                throw new Error(
                    "Type config decorator passed to " . (static::class) . " is expected to return an array, but got " .
                    Utils::getVariableType($config)
                );
            }
        }

        $innerTypeDef = $this->makeSchemaDef($this->nodeMap[$typeName], $config);

        if (!$innerTypeDef) {
            throw new Error("Nothing constructed for $typeName.");
        }
        $this->innerTypeMap[$typeName] = $innerTypeDef;
        return $innerTypeDef;
    }

    private function makeSchemaDefConfig($def)
    {
        if (!$def) {
            throw new Error('def must be defined.');
        }
        switch ($def->kind) {
            case NodeKind::OBJECT_TYPE_DEFINITION:
                return $this->makeTypeDefConfig($def);
            case NodeKind::INTERFACE_TYPE_DEFINITION:
                return $this->makeInterfaceDefConfig($def);
            case NodeKind::ENUM_TYPE_DEFINITION:
                return $this->makeEnumDefConfig($def);
            case NodeKind::UNION_TYPE_DEFINITION:
                return $this->makeUnionDefConfig($def);
            case NodeKind::SCALAR_TYPE_DEFINITION:
                return $this->makeScalarDefConfig($def);
            case NodeKind::INPUT_OBJECT_TYPE_DEFINITION:
                return $this->makeInputObjectDefConfig($def);
            default:
                throw new Error("Type kind of {$def->kind} not supported.");
        }
    }

    private function makeSchemaDef($def, array $config = null)
    {
        if (!$def) {
            throw new Error('def must be defined.');
        }

        $config = $config ?: $this->makeSchemaDefConfig($def);

        switch ($def->kind) {
            case NodeKind::OBJECT_TYPE_DEFINITION:
                return new ObjectType($config);
            case NodeKind::INTERFACE_TYPE_DEFINITION:
                return new InterfaceType($config);
            case NodeKind::ENUM_TYPE_DEFINITION:
                return new EnumType($config);
            case NodeKind::UNION_TYPE_DEFINITION:
                return new UnionType($config);
            case NodeKind::SCALAR_TYPE_DEFINITION:
                return new CustomScalarType($config);
            case NodeKind::INPUT_OBJECT_TYPE_DEFINITION:
                return new InputObjectType($config);
            default:
                throw new Error("Type kind of {$def->kind} not supported.");
        }
    }

    private function makeTypeDefConfig(ObjectTypeDefinitionNode $def)
    {
        $typeName = $def->name->value;
        return [
            'name' => $typeName,
            'description' => $this->getDescription($def),
            'fields' => function () use ($def) {
                return $this->makeFieldDefMap($def);
            },
            'interfaces' => function () use ($def) {
                return $this->makeImplementedInterfaces($def);
            },
            'astNode' => $def
        ];
    }

    private function makeFieldDefMap($def)
    {
        return Utils::keyValMap(
            $def->fields,
            function ($field) {
                return $field->name->value;
            },
            function ($field) {
                return [
                    'type' => $this->produceOutputType($field->type),
                    'description' => $this->getDescription($field),
                    'args' => $this->makeInputValues($field->arguments),
                    'deprecationReason' => $this->getDeprecationReason($field),
                    'astNode' => $field
                ];
            }
        );
    }

    private function makeImplementedInterfaces(ObjectTypeDefinitionNode $def)
    {
        if (isset($def->interfaces)) {
            return Utils::map($def->interfaces, function ($iface) {
                return $this->produceInterfaceType($iface);
            });
        }
        return null;
    }

    private function makeInputValues($values)
    {
        return Utils::keyValMap(
            $values,
            function ($value) {
                return $value->name->value;
            },
            function ($value) {
                $type = $this->produceInputType($value->type);
                $config = [
                    'name' => $value->name->value,
                    'type' => $type,
                    'description' => $this->getDescription($value),
                    'astNode' => $value
                ];
                if (isset($value->defaultValue)) {
                    $config['defaultValue'] = AST::valueFromAST($value->defaultValue, $type);
                }
                return $config;
            }
        );
    }

    private function makeInterfaceDefConfig(InterfaceTypeDefinitionNode $def)
    {
        $typeName = $def->name->value;
        return [
            'name' => $typeName,
            'description' => $this->getDescription($def),
            'fields' => function () use ($def) {
                return $this->makeFieldDefMap($def);
            },
            'astNode' => $def
        ];
    }

    private function makeEnumDefConfig(EnumTypeDefinitionNode $def)
    {
        return [
            'name' => $def->name->value,
            'description' => $this->getDescription($def),
            'astNode' => $def,
            'values' => Utils::keyValMap(
                $def->values,
                function ($enumValue) {
                    return $enumValue->name->value;
                },
                function ($enumValue) {
                    return [
                        'description' => $this->getDescription($enumValue),
                        'deprecationReason' => $this->getDeprecationReason($enumValue),
                        'astNode' => $enumValue
                    ];
                }
            )
        ];
    }

    private function makeUnionDefConfig(UnionTypeDefinitionNode $def)
    {
        return [
            'name' => $def->name->value,
            'description' => $this->getDescription($def),
            'types' => Utils::map($def->types, function ($typeNode) {
                return $this->produceObjectType($typeNode);
            }),
            'astNode' => $def
        ];
    }

    private function makeScalarDefConfig(ScalarTypeDefinitionNode $def)
    {
        return [
            'name' => $def->name->value,
            'description' => $this->getDescription($def),
            'astNode' => $def,
            'serialize' => function($value) {
                return $value;
            },
        ];
    }

    private function makeInputObjectDefConfig(InputObjectTypeDefinitionNode $def)
    {
        return [
            'name' => $def->name->value,
            'description' => $this->getDescription($def),
            'fields' => function () use ($def) {
                return $this->makeInputValues($def->fields);
            },
            'astNode' => $def,
        ];
    }

    /**
     * Given a collection of directives, returns the string value for the
     * deprecation reason.
     *
     * @param EnumValueDefinitionNode | FieldDefinitionNode $node
     * @return string
     */
    private function getDeprecationReason($node)
    {
        $deprecated = Values::getDirectiveValues(Directive::deprecatedDirective(), $node);
        return isset($deprecated['reason']) ? $deprecated['reason'] : null;
    }

    /**
     * Given an ast node, returns its string description.
     */
    public function getDescription($node)
    {
        if ($node->description) {
            return $node->description->value;
        }
        if (isset($this->options['commentDescriptions'])) {
            $rawValue = $this->getLeadingCommentBlock($node);
            if ($rawValue !== null) {
                return BlockString::value("\n" . $rawValue);
            }
        }
    }

    public function getLeadingCommentBlock($node)
    {
        $loc = $node->loc;
        if (!$loc || !$loc->startToken) {
            return;
        }
        $comments = [];
        $token = $loc->startToken->prev;
        while (
            $token &&
            $token->kind === Token::COMMENT &&
            $token->next && $token->prev &&
            $token->line + 1 === $token->next->line &&
            $token->line !== $token->prev->line
        ) {
            $value = $token->value;
            $comments[] = $value;
            $token = $token->prev;
        }

        return implode("\n", array_reverse($comments));
    }

    /**
     * A helper function to build a GraphQLSchema directly from a source
     * document.
     *
     * @api
     * @param DocumentNode|Source|string $source
     * @param callable $typeConfigDecorator
     * @return Schema
     */
    public static function build($source, callable $typeConfigDecorator = null)
    {
        $doc = $source instanceof DocumentNode ? $source : Parser::parse($source);
        return self::buildAST($doc, $typeConfigDecorator);
    }
}
