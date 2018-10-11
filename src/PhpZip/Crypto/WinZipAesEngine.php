<?php

namespace PhpZip\Crypto;

use PhpZip\Exception\RuntimeException;
use PhpZip\Exception\ZipAuthenticationException;
use PhpZip\Exception\ZipCryptoException;
use PhpZip\Exception\ZipException;
use PhpZip\Extra\Fields\WinZipAesEntryExtraField;
use PhpZip\Model\ZipEntry;
use PhpZip\Util\CryptoUtil;

/**
 * WinZip Aes Encryption Engine.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class WinZipAesEngine implements ZipEncryptionEngine
{
    /**
     * The block size of the Advanced Encryption Specification (AES) Algorithm
     * in bits (AES_BLOCK_SIZE_BITS).
     */
    const AES_BLOCK_SIZE_BITS = 128;
    const PWD_VERIFIER_BITS = 16;
    /**
     * The iteration count for the derived keys of the cipher, KLAC and MAC.
     */
    const ITERATION_COUNT = 1000;
    /**
     * @var ZipEntry
     */
    private $entry;

    /**
     * WinZipAesEngine constructor.
     * @param ZipEntry $entry
     */
    public function __construct(ZipEntry $entry)
    {
        $this->entry = $entry;
    }

    /**
     * Decrypt from stream resource.
     *
     * @param string $content Input stream buffer
     * @return string
     * @throws ZipAuthenticationException
     * @throws ZipCryptoException
     * @throws \PhpZip\Exception\ZipException
     */
    public function decrypt($content)
    {
        $extraFieldsCollection = $this->entry->getExtraFieldsCollection();

        if (!isset($extraFieldsCollection[WinZipAesEntryExtraField::getHeaderId()])) {
            throw new ZipCryptoException($this->entry->getName() . " (missing extra field for WinZip AES entry)");
        }

        /**
         * @var WinZipAesEntryExtraField $field
         */
        $field = $extraFieldsCollection[WinZipAesEntryExtraField::getHeaderId()];

        // Get key strength.
        $keyStrengthBits = $field->getKeyStrength();
        $keyStrengthBytes = $keyStrengthBits / 8;

        $pos = $keyStrengthBytes / 2;
        $salt = substr($content, 0, $pos);
        $passwordVerifier = substr($content, $pos, self::PWD_VERIFIER_BITS / 8);
        $pos += self::PWD_VERIFIER_BITS / 8;

        $sha1Size = 20;

        // Init start, end and size of encrypted data.
        $start = $pos;
        $endPos = strlen($content);
        $footerSize = $sha1Size / 2;
        $end = $endPos - $footerSize;
        $size = $end - $start;

        if (0 > $size) {
            throw new ZipCryptoException($this->entry->getName() . " (false positive WinZip AES entry is too short)");
        }

        // Load authentication code.
        $authenticationCode = substr($content, $end, $footerSize);
        if ($end + $footerSize !== $endPos) {
            // This should never happen unless someone is writing to the
            // end of the file concurrently!
            throw new ZipCryptoException("Expected end of file after WinZip AES authentication code!");
        }

        $password = $this->entry->getPassword();
        assert($password !== null);
        assert(self::AES_BLOCK_SIZE_BITS <= $keyStrengthBits);

        // WinZip 99-character limit
        // @see https://sourceforge.net/p/p7zip/discussion/383044/thread/c859a2f0/
        $password = substr($password, 0, 99);
        $ctrIvSize = self::AES_BLOCK_SIZE_BITS / 8;
        $iv = str_repeat(chr(0), $ctrIvSize);
        do {
            // Here comes the strange part about WinZip AES encryption:
            // Its unorthodox use of the Password-Based Key Derivation
            // Function 2 (PBKDF2) of PKCS #5 V2.0 alias RFC 2898.
            // Yes, the password verifier is only a 16 bit value.
            // So we must use the MAC for password verification, too.
            $keyParam = hash_pbkdf2(
                "sha1",
                $password,
                $salt,
                self::ITERATION_COUNT,
                (2 * $keyStrengthBits + self::PWD_VERIFIER_BITS) / 8,
                true
            );
            $key = substr($keyParam, 0, $keyStrengthBytes);
            $sha1MacParam = substr($keyParam, $keyStrengthBytes, $keyStrengthBytes);
            // Verify password.
        } while (!$passwordVerifier === substr($keyParam, 2 * $keyStrengthBytes));

        $content = substr($content, $start, $size);
        $mac = hash_hmac('sha1', $content, $sha1MacParam, true);

        if (substr($mac, 0, 10) !== $authenticationCode) {
            throw new ZipAuthenticationException($this->entry->getName() .
                " (authenticated WinZip AES entry content has been tampered with)");
        }

        return self::aesCtrSegmentIntegerCounter($content, $key, $iv, false);
    }

    /**
     * Decryption or encryption AES-CTR with Segment Integer Count (SIC).
     *
     * @param string $str Data
     * @param string $key Key
     * @param string $iv IV
     * @param bool $encrypted If true encryption else decryption
     * @return string
     */
    private static function aesCtrSegmentIntegerCounter($str, $key, $iv, $encrypted = true)
    {
        $numOfBlocks = ceil(strlen($str) / 16);
        $ctrStr = '';
        for ($i = 0; $i < $numOfBlocks; ++$i) {
            for ($j = 0; $j < 16; ++$j) {
                $n = ord($iv[$j]);
                if (++$n === 0x100) {
                    // overflow, set this one to 0, increment next
                    $iv[$j] = chr(0);
                } else {
                    // no overflow, just write incremented number back and abort
                    $iv[$j] = chr($n);
                    break;
                }
            }
            $data = substr($str, $i * 16, 16);
            $ctrStr .= $encrypted ?
                self::encryptCtr($data, $key, $iv) :
                self::decryptCtr($data, $key, $iv);
        }
        return $ctrStr;
    }

    /**
     * Encrypt AES-CTR.
     *
     * @param string $data Raw data
     * @param string $key Aes key
     * @param string $iv Aes IV
     * @return string Encrypted data
     */
    private static function encryptCtr($data, $key, $iv)
    {
        if (extension_loaded("openssl")) {
            $numBits = strlen($key) * 8;
            /** @noinspection PhpComposerExtensionStubsInspection */
            return openssl_encrypt($data, 'AES-' . $numBits . '-CTR', $key, OPENSSL_RAW_DATA, $iv);
        } elseif (extension_loaded("mcrypt")) {
            /** @noinspection PhpDeprecationInspection */
            /** @noinspection PhpComposerExtensionStubsInspection */
            return mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $data, "ctr", $iv);
        } else {
            throw new RuntimeException('Extension openssl or mcrypt not loaded');
        }
    }

    /**
     * Decrypt AES-CTR.
     *
     * @param string $data Encrypted data
     * @param string $key Aes key
     * @param string $iv Aes IV
     * @return string Raw data
     */
    private static function decryptCtr($data, $key, $iv)
    {
        if (extension_loaded("openssl")) {
            $numBits = strlen($key) * 8;
            /** @noinspection PhpComposerExtensionStubsInspection */
            return openssl_decrypt($data, 'AES-' . $numBits . '-CTR', $key, OPENSSL_RAW_DATA, $iv);
        } elseif (extension_loaded("mcrypt")) {
            /** @noinspection PhpDeprecationInspection */
            /** @noinspection PhpComposerExtensionStubsInspection */
            return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $data, "ctr", $iv);
        } else {
            throw new RuntimeException('Extension openssl or mcrypt not loaded');
        }
    }

    /**
     * Encryption string.
     *
     * @param string $content
     * @return string
     * @throws \PhpZip\Exception\ZipException
     */
    public function encrypt($content)
    {
        // Init key strength.
        $password = $this->entry->getPassword();
        if ($password === null) {
            throw new ZipException('No password was set for the entry "'.$this->entry->getName().'"');
        }

        // WinZip 99-character limit
        // @see https://sourceforge.net/p/p7zip/discussion/383044/thread/c859a2f0/
        $password = substr($password, 0, 99);

        $keyStrengthBits = WinZipAesEntryExtraField::getKeyStrangeFromEncryptionMethod($this->entry->getEncryptionMethod());
        $keyStrengthBytes = $keyStrengthBits / 8;

        $salt = CryptoUtil::randomBytes($keyStrengthBytes / 2);

        $keyParam = hash_pbkdf2("sha1", $password, $salt, self::ITERATION_COUNT, (2 * $keyStrengthBits + self::PWD_VERIFIER_BITS) / 8, true);
        $sha1HMacParam = substr($keyParam, $keyStrengthBytes, $keyStrengthBytes);

        // Can you believe they "forgot" the nonce in the CTR mode IV?! :-(
        $ctrIvSize = self::AES_BLOCK_SIZE_BITS / 8;
        $iv = str_repeat(chr(0), $ctrIvSize);

        $key = substr($keyParam, 0, $keyStrengthBytes);

        $content = self::aesCtrSegmentIntegerCounter($content, $key, $iv, true);

        $mac = hash_hmac('sha1', $content, $sha1HMacParam, true);

        return ($salt .
            substr($keyParam, 2 * $keyStrengthBytes, self::PWD_VERIFIER_BITS / 8) .
            $content .
            substr($mac, 0, 10)
        );
    }
}
