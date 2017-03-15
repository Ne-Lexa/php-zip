<?php
namespace PhpZip\Crypto;

use PhpZip\Exception\ZipAuthenticationException;

interface CryptoEngine
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