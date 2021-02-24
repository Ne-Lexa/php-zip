<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Util;

use PhpZip\Exception\RuntimeException;

/**
 * Crypto Utils.
 *
 * @internal
 */
final class CryptoUtil
{
    /**
     * Decrypt AES-CTR.
     *
     * @param string $data Encrypted data
     * @param string $key  Aes key
     * @param string $iv   Aes IV
     *
     * @return string Raw data
     */
    public static function decryptAesCtr(string $data, string $key, string $iv): string
    {
        if (\extension_loaded('openssl')) {
            $numBits = \strlen($key) * 8;
            /** @noinspection PhpComposerExtensionStubsInspection */
            return openssl_decrypt($data, 'AES-' . $numBits . '-CTR', $key, \OPENSSL_RAW_DATA, $iv);
        }

        throw new RuntimeException('Openssl extension not loaded');
    }

    /**
     * Encrypt AES-CTR.
     *
     * @param string $data Raw data
     * @param string $key  Aes key
     * @param string $iv   Aes IV
     *
     * @return string Encrypted data
     */
    public static function encryptAesCtr(string $data, string $key, string $iv): string
    {
        if (\extension_loaded('openssl')) {
            $numBits = \strlen($key) * 8;
            /** @noinspection PhpComposerExtensionStubsInspection */
            return openssl_encrypt($data, 'AES-' . $numBits . '-CTR', $key, \OPENSSL_RAW_DATA, $iv);
        }

        throw new RuntimeException('Openssl extension not loaded');
    }
}
