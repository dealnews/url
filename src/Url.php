<?php

namespace DealNews\Url;

use DealNews\Url\Exception\Parse;

/**
 * Parses and builds URLs
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews
 * @package     DealNews\Url
 *
 * @property string $scheme    Scheme (e.g. http)
 * @property string $host      Host
 * @property int    $port      Port
 * @property string $user      Username
 * @property string $pass      Password
 * @property string $path      URL Path
 * @property string $query     Query string
 * @property string $fragment  Hash fragment
 */
class Url {

    /**
     * Known scheme and ports that will
     * be excluded in the final URL when
     * present.
     * @var array
     */
    const PORT_SCHEME_MAP = [
        'http'  => 80,
        'https' => 443,
        'ftp'   => 21,
        'ssh'   => 22,
    ];

    /**
     * Empty array of URL properties
     * @var array
     */
    const EMPTY_DATA = [
        'scheme'          => '',
        'host'            => '',
        'port'            => '',
        'user'            => '',
        'pass'            => '',
        'path'            => '',
        'query'           => '',
        'fragment'        => '',
        'query_separator' => '&',
    ];

    /**
     * Holds the data for the URLs
     * @var array
     */
    protected array $data = [];

    /**
     * @var QueryString
     */
    public QueryString $query_string;

    /**
     * Constructs a new instance.
     *
     * @param array|string|null $url             The url
     * @param null|string       $query_separator The query separator
     *
     * @throws     Parse
     */
    public function __construct(array|string|null $url = null, ?string $query_separator = null) {
        $this->data                    = $this::EMPTY_DATA;
        $this->data['query_separator'] = $query_separator ?? $this->data['query_separator'];
        $this->query_string            = new QueryString(null, $this->data['query_separator']);
        if (!empty($url)) {
            if (is_array($url)) {
                foreach ($url as $key => $value) {
                    $this->__set($key, $value);
                }
            } else {
                if (!$this->parse($url, true, $query_separator)) {
                    throw new Parse('Unable to parse URL.');
                }
            }
        }
    }

    /**
     * Get magic method
     *
     * @param string $var Variable to get
     *
     * @return mixed
     */
    public function __get(string $var) {
        $value = null;
        if (array_key_exists($var, $this->data)) {
            if ($var === 'query') {
                $this->data['query'] = $this->query_string->build();
            }
            $value = $this->data[$var];
        } else {
            $trace = debug_backtrace();
            trigger_error(
                'Undefined property: ' . __CLASS__ . '::' . $var .
                ' in ' . $trace[0]['file'] .
                ' on line ' . $trace[0]['line'],
                E_USER_NOTICE
            );
        }

        return $value;
    }

    /**
     * Set magic method
     *
     * @param string $var   Variable to set
     * @param mixed  $value Value to set
     * @throws Parse
     */
    public function __set(string $var, $value) {
        if (array_key_exists($var, $this->data)) {
            if ($value === '' || $value === null) {
                $this->__unset($var);
            } elseif ($this->validate($var, $value)) {
                $this->data[$var] = $value;
                if ($var === 'query') {
                    $this->query_string->parse($value, $this->data['query_separator']);
                }
            } else {
                $trace = debug_backtrace();
                trigger_error(
                    'Invalid value for ' . __CLASS__ . '::' . $var .
                    ' in ' . $trace[0]['file'] .
                    ' on line ' . $trace[0]['line'],
                    E_USER_NOTICE
                );
            }
        } else {
            throw new Parse("Invalid URL setting `$var`.");
        }
    }

    /**
     * Isset magic method
     *
     * @param string $var Variable to check
     * @return boolean
     */
    public function __isset(string $var): bool {
        return isset($this->data[$var]);
    }

    /**
     * Unset magic method
     *
     * @param string $var Variable to unset
     */
    public function __unset(string $var) {
        if (array_key_exists($var, $this->data)) {
            $this->data[$var] = '';
            if ($var === 'query') {
                $this->query_string->parse('', $this->data['query_separator']);
            }
        } else {
            $trace = debug_backtrace();
            trigger_error(
                'Undefined property: ' . __CLASS__ . '::' . $var .
                ' in ' . $trace[0]['file'] .
                ' on line ' . $trace[0]['line'],
                E_USER_NOTICE
            );
        }
    }

    /**
     * Magic method for returning the URL as a string
     *
     * @return string
     */
    public function __toString(): string {
        return $this->build();
    }

    /**
     * Parses a URL into parts
     *
     * @param string      $url        A URL
     * @param bool        $reset_data If true, the objects data will be wiped out
     *                                and replaced from the input URLs data. If
     *                                false, the existing data will be updated
     *                                with the existing URLs data.
     * @param string|null $query_separator
     * @return bool
     */
    public function parse(string $url, bool $reset_data = true, ?string $query_separator = null): bool {
        if ($reset_data) {
            $this->data                    = $this::EMPTY_DATA;
            $this->data['query_separator'] = $query_separator ?? $this->data['query_separator'];
        }
        $url       = trim($url);
        $url_parts = parse_url($url);

        if (!empty($url_parts['path'])) {
            $url_parts['path'] = $this->fixEncoding($url_parts['path']);
        }

        if (!empty($url_parts['query'])) {
            $url_parts['query'] = $this->fixEncoding($url_parts['query']);
        }

        if (is_array($url_parts)) {
            $success = true;
            foreach ($url_parts as $part => $value) {
                if (!empty($value)) {
                    // PHP's built in parse_url function will accept things
                    // in a URL that are not valid. So, we run it's
                    // output through our own validation.
                    $success = $this->validate($part, $value);
                    if (!$success) {
                        break;
                    }
                } else {
                    unset($url_parts[$part]);
                }
            }
            if ($success) {
                $this->data = array_merge($this->data, $url_parts);
                $this->query_string->parse($this->data['query'], $this->data['query_separator']);
            }
        } else {
            $success = false;
        }

        return $success;
    }

    /**
     * Builds a URL and returns it
     *
     * @param string|null $query_separator
     * @return string
     */
    public function build(?string $query_separator = null): string {
        $url = '';
        if (!empty($this->data['scheme'])) {
            $url .= $this->data['scheme'] . ':';
        } else {
            if (!empty($this->data['port'])) {
                $scheme = array_search($this->data['port'], $this::PORT_SCHEME_MAP);
                if (!empty($scheme)) {
                    $url .= $scheme . ':';
                }
            }
        }

        if (!empty($this->data['host'])) {
            $url .= '//';
            if (!empty($this->data['user'])) {
                $url .= $this->data['user'];
                if (!empty($this->data['pass'])) {
                    $url .= ':' . $this->data['pass'];
                }
                $url .= '@';
            }
            $url .= $this->data['host'];

            if (!empty($this->data['port'])) {
                if (empty($this::PORT_SCHEME_MAP[strtolower($this->data['scheme'])]) || $this->data['port'] != $this::PORT_SCHEME_MAP[strtolower($this->data['scheme'])]) {
                    $url .= ':' . $this->data['port'];
                }
            }
        } elseif (strtolower($this->data['scheme']) === 'file') {
            $url .= '//';
        }

        if (!empty($this->data['path'])) {
            if (!empty($this->data['host']) && substr($this->data['path'], 0, 1) !== '/') {
                $url .= '/';
            }
            $url .= $this->data['path'];
        } elseif (!empty($this->data['host'])) {
            $url .= '/';
        }

        $query_separator     ??= $this->data['query_separator'];
        $this->data['query'] = $this->query_string->build($query_separator);
        if (!empty($this->data['query'])) {
            $url .= '?' . $this->data['query'];
        }

        if (!empty($this->data['fragment'])) {
            $url .= '#' . $this->data['fragment'];
        }

        return $url;
    }

    /**
     * Normalizes a URL's parts to allow for easier comparison to other URLs
     *
     * @return void
     */
    public function normalize(): void {
        if (!empty($this->data['scheme'])) {
            $this->data['scheme'] = strtolower($this->data['scheme']);
        }
        if (!empty($this->data['host'])) {
            $this->data['host'] = strtolower($this->data['host']);
        }
        if (!empty($this->data['query'])) {
            $this->query_string->sortParameters();
            $this->data['query'] = $this->query_string->build($this->data['query_separator']);
        }
        $escape_parts = [
            'user',
            'pass',
            'path',
            'query',
            'fragment',
        ];
        foreach ($escape_parts as $part) {
            if (!empty($this->data[$part])) {
                $this->data[$part] = preg_replace_callback(
                    '/(%\\w\\w)/',
                    function ($matches) {
                        return strtoupper($matches[0]);
                    },
                    $this->data[$part]
                );
            }
        }
    }

    /**
     * Sanitizes a string for use in a URL path.
     *
     * @param string $input A string
     *
     * @return string
     */
    public function sanitizePathComponent(string $input): string {
        $output = str_replace("'", '', $input);
        $output = preg_replace('/[^a-z0-9\\-]/i', '-', $output);
        $output = preg_replace('/(\\B[a-z])([A-Z])/', '$1-$2', $output);
        $output = preg_replace('/([0-9])([a-z])/i', '$1-$2', $output);
        $output = preg_replace('|\\-+|i', '-', $output);
        $output = trim($output, '-.');

        return $output;
    }

    /**
     * Validates different parts of the URL
     *
     * @param string     $part  Part to validate
     * @param string|int $value Value to validate
     *
     * @return bool
     */
    public function validate(string $part, string|int $value): bool {
        $valid = false;
        switch ($part) {
            case 'scheme':
                if (preg_match('/^[a-z]+$/i', $value)) {
                    $valid = true;
                }
                break;
            case 'host':
                if (filter_var("scheme://{$value}/", \FILTER_VALIDATE_URL)) {
                    $valid = true;
                }
                break;
            case 'port':
                $value = (int)$value;
                if ($value > 0 && $value <= 65536) {
                    $valid = true;
                }
                break;
            case 'user':
                if (filter_var("scheme://{$value}:pass@www.example.com/", \FILTER_VALIDATE_URL)) {
                    $valid = true;
                }
                break;
            case 'pass':
                if (filter_var("scheme://user:{$value}@www.example.com/", \FILTER_VALIDATE_URL)) {
                    $valid = true;
                }
                break;
            case 'path':
                // relative paths are fine, but they won't pass this check
                // without a leading slash
                if (substr($value, 0, 1) !== '/') {
                    $value = '/' . $value;
                }
                if (filter_var("scheme://www.example.com{$value}?foo=1", \FILTER_VALIDATE_URL)) {
                    $valid = true;
                }
                break;
            case 'query':
                if (filter_var("scheme://www.example.com/?{$value}", \FILTER_VALIDATE_URL)) {
                    $valid = true;
                }
                break;
            case 'fragment':
                if (filter_var("scheme://www.example.com/#{$value}", \FILTER_VALIDATE_URL)) {
                    $valid = true;
                }
                break;
        }

        return $valid;
    }

    /**
     * Fixes encoding in a URL in a naive way to handle the
     * most common mistakes in URLs found when scraping or
     * ingesting a file from a 3rd party.
     *
     * @param string $url The URL to fix
     * @return  string
     *
     */
    public function fixEncoding(string $url): string {
        $valid = preg_quote(';/?:@=&$-_.+!*(),#%~\'', '/');

        $buff = preg_replace_callback(
            "/([^0-9a-zA-Z{$valid}]+)/",
            function ($matches) {
                return rawurlencode($matches[0]);
            },
            $url
        );

        return $buff;
    }

    /**
     * Merge additional URLs with the existing URL data
     *
     * @param string $url1    string
     * @param string ...$url2 string
     *
     * @return string
     *
     * @phan-suppress PhanUnusedPublicNoOverrideMethodParameter
     */
    public function merge(string $url1, string ...$url2): string {
        $current_data         = $this->data;
        $current_query_string = clone $this->query_string;

        $args = func_get_args();
        foreach ($args as $arg) {
            $this->parse($arg, false);
        }

        $new_url = $this->build();

        $this->data         = $current_data;
        $this->query_string = $current_query_string;

        return $new_url;
    }
}
