<?php

declare(strict_types=1);

namespace DealNews\Url\Tests;

use DealNews\Url\QueryString;
use PHPUnit\Framework\TestCase;

/**
 * Tests the QueryString helper.
 */
class QueryStringTest extends TestCase {

    /**
     * Ensures parsing and building works with custom separators.
     *
     * @return void
     */
    public function testParseAndBuildWithCustomSeparator(): void {
        $query_string = new QueryString();
        $query_string->parse('product=4k-tv;flag;city=New%20York', ';');

        self::assertSame(
            [
                ['product', '4k-tv'],
                ['flag'],
                ['city', 'New York'],
            ],
            $query_string->getParameters()
        );

        self::assertSame('product=4k-tv;flag;city=New%20York', $query_string->build());
    }

    /**
     * Ensures setParameter replaces existing values and respects the front flag.
     *
     * @return void
     */
    public function testSetParameterReplacesExistingAndSupportsFrontInsertion(): void {
        $query_string = new QueryString('foo=1&bar=2');

        $query_string->setParameter('foo', '3');
        $query_string->setParameter('baz', '4', true);

        self::assertSame('baz=4&foo=3&bar=2', $query_string->build());
    }

    /**
     * Ensures removing parameters keeps unnamed entries intact.
     *
     * @return void
     */
    public function testRemoveParametersAndUnnamedValues(): void {
        $query_string = new QueryString();
        $query_string->addParameter('foo', '1');
        $query_string->addParameter(null, 'token value');
        $query_string->addParameter('bar', '');
        $query_string->removeParameters(['foo']);

        self::assertSame(
            [
                [null, 'token value'],
                ['bar', ''],
            ],
            array_values($query_string->getParameters())
        );

        self::assertSame('token%20value', $query_string->build());
    }

    /**
     * Ensures parameters only sort when all entries are named.
     *
     * @return void
     */
    public function testSortParametersOnlyWhenAllNamed(): void {
        $named_query = new QueryString();
        $named_query->addNamedParameters([
            'c' => '3',
            'b' => '2',
            'a' => '1',
        ]);
        $named_query->sortParameters();

        self::assertSame('a=1&b=2&c=3', $named_query->build());

        $mixed_query = new QueryString('flag');
        $mixed_query->addNamedParameters([
            'b' => '2',
            'a' => '1',
        ]);
        $mixed_query->sortParameters();

        self::assertSame('flag&b=2&a=1', $mixed_query->build());
    }
}
