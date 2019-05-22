<?php

declare(strict_types=1);

namespace pjmd89\GraphQL\Tests\Utils;

use pjmd89\GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;

class SuggestionListTest extends TestCase
{
    // DESCRIBE: suggestionList
    /**
     * @see it('Returns results when input is empty')
     */
    public function testResturnsResultsWhenInputIsEmpty() : void
    {
        self::assertEquals(
            Utils::suggestionList('', ['a']),
            ['a']
        );
    }

    /**
     * @see it('Returns empty array when there are no options')
     */
    public function testReturnsEmptyArrayWhenThereAreNoOptions() : void
    {
        self::assertEquals(
            Utils::suggestionList('input', []),
            []
        );
    }

    /**
     * @see it('Returns options sorted based on similarity')
     */
    public function testReturnsOptionsSortedBasedOnSimilarity() : void
    {
        self::assertEquals(
            Utils::suggestionList('abc', ['a', 'ab', 'abc']),
            ['abc', 'ab']
        );
    }
}
