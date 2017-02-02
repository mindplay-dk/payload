<?php

/**
 * Encode and decode small data-payloads using filename and URL-safe characters.
 */
abstract class Payload
{
    /**
     * @param array $payload    a key/value map (nested strings and arrays)
     * @param int   $max_length maximum encoded string-length
     *
     * @return string encoded payload
     *
     * @throws InvalidArgumentException if the length of the encoded payload exceeds the given max length
     */
    public static function encode(array $payload, $max_length = 120): string
    {
        ksort($payload, SORT_NATURAL); // ensure consistent key-order

        $string = http_build_query($payload); // encode in RFC1738 query-string format
        $string = base64_encode($string);
        $string = strtr($string, '+/', '-_'); // swap out URL-unsafe characters
        $string = rtrim($string, '='); // truncate base64 boundary

        if (strlen($string) > $max_length) {
            throw new InvalidArgumentException("payload exceeds maximum length of: {$max_length}");
        }

        return $string;
    }

    /**
     * @param string $data encoded payload string
     *
     * @return array decoded payload
     *
     * @throws InvalidArgumentException if the
     */
    public static function decode(string $data): array
    {
        $data = @strtr($data, '-_', '+/'); // swap back URL-unsafe characters
        $data = @base64_decode($data);

        @parse_str($data, $payload); // parse RFC1738 query-string

        if (is_array($payload)) {
            return $payload;
        }

        throw new InvalidArgumentException("invalid payload string");
    }
}
