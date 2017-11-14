<?php

namespace PhpZip\Crypto;

use PhpZip\Exception\ZipAuthenticationException;

/**
 * Encryption Engine
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
interface ZipEncryptionEngine
{
    /**
     * Decryption string.
     *
     * @param string $encryptionContent
     * @return string
     * @throws ZipAuthenticationException
     */
    public function decrypt($encryptionContent);

    /**
     * Encryption string.
     *
     * @param string $content
     * @return string
     */
    public function encrypt($content);
}
