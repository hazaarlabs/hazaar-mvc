<?php

declare(strict_types=1);

namespace Hazaar\Util;

/**
 * URL utility class.
 *
 * This class provides a number of utility functions for working with URLs.
 */
class URL
{
    /**
     * Encodes data to a Base64 URL-safe string.
     *
     * This function encodes the given data using Base64 encoding and then makes the
     * encoded string URL-safe by replacing '+' with '-' and '/' with '_'. It also
     * removes any trailing '=' characters.
     *
     * @param string $data the data to be encoded
     *
     * @return string the Base64 URL-safe encoded string
     */
    public static function base64Encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodes a base64 URL encoded string.
     *
     * This function takes a base64 URL encoded string and decodes it back to its original form.
     * It replaces URL-safe characters ('-' and '_') with standard base64 characters ('+' and '/'),
     * and pads the string with '=' characters to ensure its length is a multiple of 4.
     *
     * @param string $data the base64 URL encoded string to decode
     *
     * @return string the decoded string
     */
    public static function base64Decode(string $data): string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) + (4 - (strlen($data) % 4) % 4), '=', STR_PAD_RIGHT));
    }

    /**
     * Build a correctly formatted URL from argument list.
     *
     * This function will build a correctly formatted HTTP compliant URL using a list of parameters. If any
     * of the parameters are null then they will be omitted from the formatted output, including any extra values.
     *
     * For example, you can specify a username and a password which will be formatted as username:password\@.  However
     * if you omit the password you will simply get username\@.
     *
     * @param string       $scheme   The protocol to use. Usually http or https.
     * @param string       $host     Hostname
     * @param int          $port     (optional) Port
     * @param string       $user     (optional) Username
     * @param string       $pass     (optional) User password. If set, a username is required.
     * @param string       $path     (optional) Path suffix
     * @param array<mixed> $query    (optional) Array of parameters to send. ie: the stuff after the '?'. Uses http_build_query to generate string.
     * @param string       $fragment (optional) Anything to go after the '#'
     */
    public static function build_url(
        string $scheme = 'http',
        string $host = 'localhost',
        ?int $port = null,
        ?string $user = null,
        ?string $pass = null,
        ?string $path = null,
        array $query = [],
        ?string $fragment = null
    ): string {
        $url = strtolower(trim($scheme)).'://';
        if ($user = trim($user) || ($user && $pass = trim($pass))) {
            $url .= $user.($pass ? ':'.$pass : null).'@';
        }
        $url .= trim($host);
        if (80 != $port) {
            $url .= ':'.$port;
        }
        if ($path = trim($path)) {
            $url .= $path;
        }
        if (count($query) > 0) {
            $url .= '?'.http_build_query($query);
        }
        if ($fragment = trim($fragment)) {
            $url .= '#'.$fragment;
        }

        return $url;
    }
}
