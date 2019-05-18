<?php
namespace pjmd89\GraphQL\Benchmarks;

use pjmd89\GraphQL\Language\Lexer;
use pjmd89\GraphQL\Language\Source;
use pjmd89\GraphQL\Language\Token;
use pjmd89\GraphQL\Type\Introspection;

/**
 * @BeforeMethods({"setUp"})
 * @OutputTimeUnit("milliseconds", precision=3)
 */
class LexerBench
{
    private $introQuery;

    public function setUp()
    {
        $this->introQuery = new Source(Introspection::getIntrospectionQuery());
    }

    /**
     * @Warmup(2)
     * @Revs(100)
     * @Iterations(5)
     */
    public function benchIntrospectionQuery()
    {
        $lexer = new Lexer($this->introQuery);

        do {
            $token = $lexer->advance();
        } while ($token->kind !== Token::EOF);
    }
}
