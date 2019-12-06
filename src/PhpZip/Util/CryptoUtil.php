<?php

namespace PhpZip\Util;

/**
 * Crypto Utils.
 *
 * @deprecated
 */
class CryptoUtil
{
    /**
     * Returns random bytes.
     *
     * @param int $length
     *
     * @throws \Exception
     *
     * @return string
     *
     * @deprecated Use random_bytes()
     */
    final public static function randomBytes($length)
    {
        return random_bytes($length);
    }
}
