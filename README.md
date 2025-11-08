# DealNews URL Utilities

Utilities for parsing, normalizing, merging, and building URLs with fine‑grained control over query strings.

## Installation

```bash
composer require dealnews/url
```

## Classes

### DealNews\Url\Url

High level URL helper that stores individual URL parts (`scheme`, `host`, `path`, `query`, etc.) and keeps a `QueryString` helper in sync with the `query` property.

Key capabilities:

- `parse(string $url, bool $reset = true, ?string $separator = null)` – split a URL into parts and validate each component.
- Magic setters/getters (`$url->host`, `$url->path`, `$url->query`, …) – allow direct manipulation of parts with validation.
- `build(?string $separator = null)` – rebuild the URL, omitting default ports and honoring custom separators.
- `normalize()` – lowercases scheme/host, sorts query parameters, and normalizes percent‑encoding.
- `sanitizePathComponent(string $input)` – generate safe slug/path fragments.
- `merge(string $url1, string ...$url2)` – merge one or more URLs into the current instance without mutating the original data.

Example:

```php
use DealNews\Url\Url;

$url = new Url('https://user:pass@example.com:443/products/tv?q=4k#details');
$url->normalize(); // lowercases scheme/host and sorts query parameters

$url->query_string->setParameter('page', '2');
$url->fragment = 'reviews';

echo $url->build(); // https://user:pass@example.com/products/tv?q=4k&page=2#reviews

// Merge additional data without losing the existing state
$combined = $url->merge('http://cdn.example.com/media', '?size=large');
// $combined => http://cdn.example.com/media?size=large
// $url remains unchanged
```

### DealNews\Url\QueryString

Lightweight parser/builder for ordered query strings. Allows unnamed entries, custom separators, front insertion, and selective replacement.

Key methods:

- `parse(string $query_string, string $separator = '&')`
- `build(?string $separator = null): string`
- `addParameter(?string $key, ?string $value, bool $front = false)`
- `setParameter(?string $key, ?string $value, bool $front = false)`
- `removeParameters(array $keys): void`
- `sortParameters(): void`
- `getParameters(): array`

Example:

```php
use DealNews\Url\QueryString;

$qs = new QueryString('foo=1&bar=2');
$qs->setParameter('foo', '3');
$qs->addParameter(null, 'feature'); // unnamed flag
$qs->sortParameters(); // only sorts when all params are named

echo $qs->build(); // foo=3&bar=2&feature
```

Custom separator example:

```php
$qs = new QueryString(null, ';');
$qs->parse('token;user=john;role=admin', ';');
$qs->addParameter('expires', '1700000000');

echo $qs->build(); // token;user=john;role=admin;expires=1700000000
```

### DealNews\Url\Exception\Parse

Custom exception extending `\Exception`. Methods such as `Url::__construct()` and `Url::__set()` throw `Parse` when a URL cannot be parsed or an invalid property is referenced.

```php
use DealNews\Url\Exception\Parse;
use DealNews\Url\Url;

try {
    $url = new Url();
    $url->invalid_property = 'value';
} catch (Parse $e) {
    // Handle invalid assignment
}
```

## Testing

```bash
composer install
./vendor/bin/phpunit
```
