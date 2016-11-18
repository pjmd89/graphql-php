# Built-in Scalar Types
GraphQL specification describes several built-in scalar types. In **graphql-php** they are 
exposed as static methods of `GraphQL\Type\Definition\Type` class:

```php
use GraphQL\Type\Definition\Type;

// Built-in Scalar types:
Type::string();  // String type
Type::int();     // Int type
Type::float();   // Float type
Type::boolean(); // Boolean type
Type::id();      // ID type
```
Those methods return instances of `GraphQL\Type\Definition\ScalarType` (actually one of it subclasses).
Use them directly in type definitions, or wrap in your [TypeRegistry](/type-system/#type-registry) 
(if you use one).

# Writing Custom Scalar Types
In addition to built-in scalars, you can define your own scalar types with additional validation. 
Typical examples of such types are: `Email`, `Date`, `Url`, etc.

In order to implement your own type you must understand how scalars are presented in GraphQL.
GraphQL deals with scalars in following cases:

1. When converting **internal representation** of value returned by your app (e.g. stored in database 
or hardcoded in source code) to **serialized** representation included in response.
 
2. When converting **input value** passed by client in variables along with GraphQL query to 
**internal representation** of your app.

3. When converting **input literal value** hardcoded in GraphQL query (e.g. field argument value) to 
**internal representation** of your app.

Those cases are covered by methods `serialize`, `parseValue` and `parseLiteral` of abstract `ScalarType` 
class respectively.

Here is an example of simple `Email` type (using inheritance):

```php
<?php
namespace MyApp;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils;

class EmailType extends ScalarType
{
    // Note: name can be omitted. In this case it will be inferred from class name 
    // (suffix "Type" will be dropped)
    public $name = 'Email';

    /**
     * Serializes an internal value to include in a response.
     *
     * @param string $value
     * @return string
     */
    public function serialize($value)
    {
        // Assuming internal representation of email is always correct:
        return $value;

        // If it might be incorrect and you want to make sure that only correct values are included in response -
        // use following line instead:
        // return $this->parseValue($value);
    }

    /**
     * Parses an externally provided value (query variable) to use as an input
     *
     * @param mixed $value
     * @return mixed
     */
    public function parseValue($value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \UnexpectedValueException("Cannot represent value as email: " . Utils::printSafe($value));
        }
        return $value;
    }

    /**
     * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
     * 
     * E.g. 
     * {
     *   user(email: "user@example.com") 
     * }
     *
     * @param \GraphQL\Language\AST\Node $valueAST
     * @return string
     * @throws Error
     */
    public function parseLiteral($valueAST)
    {
        // Note: throwing GraphQL\Error\Error vs \UnexpectedValueException to benefit from GraphQL
        // error location in query:
        if (!$valueAST instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: ' . $valueAST->kind, [$valueAST]);
        }
        if (!filter_var($valueAST->value, FILTER_VALIDATE_EMAIL)) {
            throw new Error("Not a valid email", [$valueAST]);
        }
        return $valueAST->value;
    }
}
```

Same example, using composition over inheritance:
```php
<?php
namespace MyApp;

use GraphQL\Type\DefinitionContainer;
use GraphQL\Type\Definition\CustomScalarType;

class EmailType implements DefinitionContainer
{
    private $definition;

    public function getDefinition()
    {
        return $this->definition ?: ($this->definition = new CustomScalarType([
            'name' => 'Email',
            'serialize' => function($value) {/* See function body above */},
            'parseValue' => function($value) {/* See function body above */},
            'parseLiteral' => function($valueAST) {/* See function body above */},
        ]));
    }
}
```

Or with inline style:

```php
<?php
use GraphQL\Type\Definition\CustomScalarType;

$emailType = new CustomScalarType([
    'name' => 'Email',
    'serialize' => function($value) {/* See function body above */},
    'parseValue' => function($value) {/* See function body above */},
    'parseLiteral' => function($valueAST) {/* See function body above */},
]);
```