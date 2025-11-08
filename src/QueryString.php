<?php

namespace DealNews\Url;

/**
 * Query String Parser and Builder
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DealNews\Url
 */
class QueryString {

    /**
     * Holds query parameters
     *
     * @var array
     */
    protected array $parameters = [];

    /**
     * Query string separator
     *
     * @var string
     */
    protected string $separator = '&';

    /**
     * Constructs a new instance.
     *
     * @param      ?string  $query_string  The query string
     * @param      string   $separator     The separator
     */
    public function __construct(?string $query_string = null, string $separator = '&') {
        $this->separator = $separator;
        if ($query_string !== null) {
            $this->parse($query_string, $separator);
        }
    }

    /**
     * Returns a string representation of the object.
     *
     * @return     string  String representation of the object.
     */
    public function __toString(): string {
        return $this->build();
    }

    /**
     * Sorts query string parameters
     */
    public function sortParameters(): void {
        $all_named = true;
        foreach ($this->parameters as $param) {
            if (count($param) === 1) {
                $all_named = false;
                break;
            }
        }

        if ($all_named) {
            uasort($this->parameters, fn ($a, $b) => $a[0] <=> $b[0]);
        }
    }

    /**
     * Returns an array of query string parameters
     *
     * @return     array  The parameters.
     */
    public function getParameters(): array {
        return $this->parameters;
    }

    /**
     * Adds a parameter.
     *
     * @param      ?string    $key    The key
     * @param      ?string    $value  The value
     * @param      bool       $front  If true, the new parameter will be at
     *                                the beginning of the query string
     */
    public function addParameter(?string $key, ?string $value, bool $front = false): void {
        if ($front) {
            array_unshift($this->parameters, [$key, $value]);
        } else {
            $this->parameters[] = [$key, $value];
        }
    }

    /**
     * Replaces a query string parameters
     *
     * @param      string   $key        The key
     * @param      ?string  $value      The new value
     * @param      ?string  $old_value  If provided, only matching values will be replaced
     *
     * @return     bool     True if the parameter was found and replaced
     */
    public function replaceParameter(string $key, ?string $value, ?string $old_value = null): bool {
        $found = false;
        foreach ($this->parameters as $idx => $params) {
            if ($params[0] === $key) {
                if ($old_value === null || $old_value === $params[1]) {
                    $this->parameters[$idx][1] = $value;
                    $found                     = true;
                }
            }
        }

        return $found;
    }

    /**
     * Adds or replaces a parameter.
     *
     * @param      ?string    $key    The key
     * @param      ?string    $value  The value
     * @param      bool       $front  If true, the new parameter will be at
     *                                the beginning of the query string if it
     *                                is added. If it is replaced, it will
     *                                stay in its current order.
     */
    public function setParameter(?string $key, ?string $value, bool $front = false): void {
        if ($key !== null) {
            $found = $this->replaceParameter($key, $value);
        }
        if (empty($found)) {
            $this->addParameter($key, $value, $front);
        }
    }

    /**
     * Removes parameters.
     *
     * @param      array  $keys   The keys
     */
    public function removeParameters(array $keys): void {
        foreach ($this->parameters as $idx => $params) {
            if (in_array($params[0], $keys)) {
                unset($this->parameters[$idx]);
            }
        }
    }

    /**
     * Adds or replaces multiple named parameters
     *
     * @param      array  $parameters  Array of key value pairs
     * @param      bool   $front       If true, the new parameter will be at
     *                                 the beginning of the query string if it
     *                                 is added. If it is replaced, it will
     *                                 stay in it's current order.
     */
    public function setNamedParameters(array $parameters, bool $front = false): void {
        foreach ($parameters as $key => $value) {
            $this->setParameter($key, $value, $front);
        }
    }

    /**
     * Adds multiple named parameters
     *
     * @param      array  $parameters  Array of key value pairs
     * @param      bool   $front       If true, the new parameter will be at
     *                                 the beginning of the query string if it
     *                                 is added.
     */
    public function addNamedParameters(array $parameters, bool $front = false): void {
        foreach ($parameters as $key => $value) {
            $this->addParameter($key, $value, $front);
        }
    }

    /**
     * Parses a query string
     *
     * @param      string  $query_string  The query string
     * @param      string  $separator     The separator
     */
    public function parse(string $query_string, string $separator = '&'): void {
        $this->separator = $separator;

        $this->parameters = [];

        if ($separator !== '&') {
            $query_string = urldecode($query_string);
        }

        $parts = explode($separator, $query_string);

        foreach ($parts as $part) {
            if (!empty($part)) {
                if (strpos($part, '=') !== false) {
                    [$param, $val]      = explode('=', $part);
                    $this->parameters[] = [urldecode($param), urldecode($val)];
                } else {
                    $this->parameters[] = [urldecode($part)];
                }
            }
        }
    }

    /**
     * Builds and returns a query string
     *
     * @param      ?string      $separator  Optional separator
     *
     * @return     string
     */
    public function build(?string $separator = null): string {
        $separator ??= $this->separator;

        $query_parts = [];

        foreach ($this->parameters as $param) {
            if (count($param) === 1) {
                $key   = null;
                $value = (string)$param[0];
            } else {
                $key   = $param[0];
                $value = (string)$param[1];
            }

            if (strlen($value) > 0) {
                if ($key === null) {
                    $query_parts[] = rawurlencode($value);
                } else {
                    $query_parts[] = rawurlencode($key) . '=' . rawurlencode($value);
                }
            }
        }

        return implode($separator, $query_parts);
    }
}
