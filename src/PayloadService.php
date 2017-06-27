<?php

namespace mindplay;

use InvalidArgumentException;

/**
 * This class implements a service to encode and decode small data-payloads
 * using filename and URL-safe characters.
 */
class PayloadService
{
    /**
     * @var int|null
     */
    private $max_length;

    /**
     * @var int
     */
    private $checksum_chars;

    /**
     * @var string
     */
    private $private_salt;

    /**
     * @param int|null $max_length     optional maximum encoded length to enforce
     * @param int      $checksum_chars number of checksum characters to add
     * @param string   $private_salt   private salt used to seed the checksum calculation
     */
    public function __construct(int $max_length = null, int $checksum_chars = 4, $private_salt = "")
    {
        if (! function_exists("msgpack_pack")) {
            require_once __DIR__ . '/_msgpack.php';
        }

        $this->max_length = $max_length;
        $this->checksum_chars = $checksum_chars;
        $this->private_salt = $private_salt;
    }

    /**
     * @param array $payload a key/value map (nested strings and arrays)
     *
     * @return string encoded payload
     *
     * @throws InvalidArgumentException if the length of the encoded payload exceeds the given max length
     */
    public function encode(array $payload): string
    {
        ksort($payload, SORT_NATURAL); // ensure consistent key-order

        $string = msgpack_pack($payload); // encode in MsgPack format
        $string = base64_encode($string);
        $string = rtrim($string, '='); // truncate base64 boundary
        $string = $this->checksum($string) . $string; // prepend checksum chars
        $string = strtr($string, '+/', '-_'); // swap out URL-unsafe characters

        if ($this->max_length && (strlen($string) > $this->max_length)) {
            throw new InvalidArgumentException("payload exceeds maximum length of: {$this->max_length}");
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
    public function decode(string $data): array
    {
        $data = @strtr($data, '-_', '+/'); // swap back URL-unsafe characters

        $checksum = @substr($data, 0, $this->checksum_chars);

        $data = @substr($data, $this->checksum_chars);

        if ($checksum !== self::checksum($data)) {
            throw new InvalidArgumentException("invalid payload checksum");
        }

        $data = @base64_decode($data, true);

        $payload = @msgpack_unpack($data); // parse MsgPack string

        if (is_array($payload)) {
            return $payload;
        }

        throw new InvalidArgumentException("invalid payload string");
    }

    /**
     * Internally create a short hash of the given payload
     *
     * @param string $data
     *
     * @return string
     */
    private function checksum(string $data): string
    {
        return substr(base64_encode(sha1($data . $this->private_salt, true)), 0, $this->checksum_chars);
    }
}
