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
     */
    final public static function randomBytes($length)
    {
        $length = (int)$length;
        if (function_exists('random_bytes')) {
            try {
                return random_bytes($length);
            } catch (\Exception $e) {
                throw new \RuntimeException("Could not generate a random string.");
            }
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            return openssl_random_pseudo_bytes($length);
        } elseif (function_exists('mcrypt_create_iv')) {
            /** @noinspection PhpDeprecationInspection */
            /** @noinspection PhpComposerExtensionStubsInspection */
            return mcrypt_create_iv($length);
        } else {
            throw new RuntimeException('Extension openssl or mcrypt not loaded');
        }
    }
}
