<?php

namespace PhpZip\Util;

use PhpZip\Exception\RuntimeException;

/**
 * Crypto Utils
 */
class CryptoUtil
{

    /**
     * Returns random bytes.
     *
     * @param int $length
     * @return string
     * @throws RuntimeException
     */
    final public static function randomBytes($length)
    {
        $length = (int)$length;
        if (function_exists('random_bytes')) {
            return random_bytes($length);
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return openssl_random_pseudo_bytes($length);
        } elseif (function_exists('mcrypt_create_iv')) {
            return mcrypt_create_iv($length);
        } else {
            throw new RuntimeException('Extension openssl or mcrypt not loaded');
        }
    }
}
