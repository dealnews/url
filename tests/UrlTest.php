<?php

declare(strict_types=1);

namespace DealNews\Url\Tests;

use DealNews\Url\Exception\Parse;
use DealNews\Url\Url;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Url utility.
 */
class UrlTest extends TestCase {

    /**
     * Ensures parsing and building round-trip and removes default ports.
     *
     * @return void
     */
    public function testParseAndBuildRoundTripRemovesDefaultPort(): void {
        $url = new Url('https://user:pass@example.com:443/path/file?q=1#frag');

        self::assertSame('https://user:pass@example.com/path/file?q=1#frag', $url->build());
        self::assertSame('user', $url->user);
        self::assertSame('pass', $url->pass);
        self::assertSame('example.com', $url->host);
        self::assertSame('/path/file', $url->path);
        self::assertSame('q=1', $url->query);
    }

    /**
     * Ensures setting the query property keeps the QueryString helper in sync.
     *
     * @return void
     */
    public function testSettingQueryUpdatesQueryString(): void {
        $url         = new Url();
        $url->scheme = 'https';
        $url->host   = 'example.com';
        $url->query  = 'foo=1&bar=2';

        self::assertSame(
            [
                ['foo', '1'],
                ['bar', '2'],
            ],
            $url->query_string->getParameters()
        );

        $url->query_string->replaceParameter('foo', '3');

        self::assertSame('https://example.com/?foo=3&bar=2', $url->build());
    }

    /**
     * Ensures normalize lowercases scheme/host, sorts query parameters, and uppercases encoding.
     *
     * @return void
     */
    public function testNormalizeLowercasesAndSortsQuery(): void {
        $url = new Url('HTTP://Example.com:80/%7euser%2fData?b=2&a=1');
        $url->normalize();

        self::assertSame('http', $url->scheme);
        self::assertSame('example.com', $url->host);
        self::assertSame('/%7Euser%2FData', $url->path);
        self::assertSame('a=1&b=2', $url->query);
        self::assertSame('http://example.com/%7Euser%2FData?a=1&b=2', (string)$url);
    }

    /**
     * Ensures path sanitization strips invalid characters and inserts separators.
     *
     * @return void
     */
    public function testSanitizePathComponent(): void {
        $url = new Url();

        self::assertSame('Hello-World-4-k-TV', $url->sanitizePathComponent('Hello World 4kTV'));
    }

    /**
     * Ensures invalid characters are encoded while whitelisted ones remain untouched.
     *
     * @return void
     */
    public function testFixEncoding(): void {
        $url = new Url();

        self::assertSame('deal%20news/%E2%9D%A4', $url->fixEncoding('deal news/❤'));
    }

    /**
     * Ensures merge combines URLs without mutating the original state.
     *
     * @return void
     */
    public function testMergeCombinesUrlsAndRestoresState(): void {
        $url = new Url('https://example.com/path?foo=1');

        $merged_url = $url->merge('?bar=2', 'http://new.example.org:8080/new-path?baz=3');

        self::assertSame('http://new.example.org:8080/new-path?baz=3', $merged_url);
        self::assertSame('https', $url->scheme);
        self::assertSame('example.com', $url->host);
        self::assertSame('/path', $url->path);
        self::assertSame('foo=1', $url->query);
        self::assertSame(
            [
                ['foo', '1'],
            ],
            $url->query_string->getParameters()
        );
    }

    /**
     * Ensures invalid hosts cause parse to fail.
     *
     * @return void
     */
    public function testParseReturnsFalseForInvalidHost(): void {
        $url    = new Url();
        $result = $url->parse('http://exa mple.com');

        self::assertFalse($result);
        self::assertSame('', $url->host);
    }

    /**
     * Ensures a missing scheme can be inferred from the port value.
     *
     * @return void
     */
    public function testBuildInfersSchemeFromPort(): void {
        $url       = new Url();
        $url->host = 'example.com';
        $url->port = 80;
        $url->path = 'path';

        self::assertSame('http://example.com:80/path', $url->build());
    }

    /**
     * Ensures unsetting the query clears internal parameters.
     *
     * @return void
     */
    public function testUnsetQueryClearsParameters(): void {
        $url       = new Url('https://example.com/?foo=1&bar=2');
        $url->__unset('query');

        self::assertSame('', $url->query);
        self::assertSame([], $url->query_string->getParameters());
    }

    /**
     * Ensures invalid property assignments throw Parse exceptions.
     *
     * @return void
     */
    public function testInvalidPropertyThrowsParseException(): void {
        $url = new Url();

        $this->expectException(Parse::class);
        $url->invalid = 'value';
    }

    /**
     * Ensures validate enforces rules for different URL parts.
     *
     * @param string     $part     URL part under test
     * @param string|int $value    Value to validate
     * @param bool       $expected Expected result
     *
     * @return void
     */
    #[DataProvider('validationDataProvider')]
    public function testValidate(string $part, string|int $value, bool $expected): void {
        $url = new Url();

        self::assertSame($expected, $url->validate($part, $value));
    }

    /**
     * Provides valid and invalid combinations for validate().
     *
     * @return array<string, array{0:string, 1:string|int, 2:bool}>
     */
    public static function validationDataProvider(): array {
        return [
            'valid scheme'        => ['scheme', 'https', true],
            'invalid scheme'      => ['scheme', 'ht+tp', false],
            'valid host'          => ['host', 'example.com', true],
            'invalid host'        => ['host', 'exa mple', false],
            'valid port'          => ['port', 8080, true],
            'invalid port'        => ['port', 70000, false],
            'valid user'          => ['user', 'name', true],
            'invalid user'        => ['user', 'na me', false],
            'valid pass'          => ['pass', 'secret', true],
            'invalid pass'        => ['pass', 'pa ss', false],
            'valid path'          => ['path', '/path', true],
            'invalid path'        => ['path', 'bad path', false],
            'valid query'         => ['query', 'foo=bar', true],
            'invalid query'       => ['query', 'bad query', false],
            'valid fragment'      => ['fragment', 'section', true],
            'invalid fragment'    => ['fragment', 'frag ment', false],
        ];
    }
}
