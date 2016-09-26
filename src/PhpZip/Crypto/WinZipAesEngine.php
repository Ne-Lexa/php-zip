<?php
namespace PhpZip\Crypto;

use PhpZip\Exception\ZipAuthenticationException;
use PhpZip\Exception\ZipCryptoException;
use PhpZip\Extra\WinZipAesEntryExtraField;
use PhpZip\Model\ZipEntry;
use PhpZip\Util\CryptoUtil;

/**
 * WinZip Aes Encryption Engine.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class WinZipAesEngine
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
     * @param resource $stream Input stream resource
     * @return string
     * @throws ZipAuthenticationException
     * @throws ZipCryptoException
     */
    public function decrypt($stream)
    {
        /**
         * @var WinZipAesEntryExtraField $field
         */
        $field = $this->entry->getExtraField(WinZipAesEntryExtraField::getHeaderId());
        if (null === $field) {
            throw new ZipCryptoException($this->entry->getName() . " (missing extra field for WinZip AES entry)");
        }

        $pos = ftell($stream);

        // Get key strength.
        $keyStrengthBits = $field->getKeyStrength();
        $keyStrengthBytes = $keyStrengthBits / 8;

        $salt = fread($stream, $keyStrengthBytes / 2);
        $passwordVerifier = fread($stream, self::PWD_VERIFIER_BITS / 8);

        $sha1Size = 20;

        // Init start, end and size of encrypted data.
        $endPos = $pos + $this->entry->getCompressedSize();
        $start = ftell($stream);
        $footerSize = $sha1Size / 2;
        $end = $endPos - $footerSize;
        $size = $end - $start;

        if (0 > $size) {
            throw new ZipCryptoException($this->entry->getName() . " (false positive WinZip AES entry is too short)");
        }

        // Load authentication code.
        fseek($stream, $end, SEEK_SET);
        $authenticationCode = fread($stream, $footerSize);
        if (ftell($stream) !== $endPos) {
            // This should never happen unless someone is writing to the
            // end of the file concurrently!
            throw new ZipCryptoException("Expected end of file after WinZip AES authentication code!");
        }

        do {
            assert($this->entry->getPassword() !== null);
            assert(self::AES_BLOCK_SIZE_BITS <= $keyStrengthBits);

            // Here comes the strange part about WinZip AES encryption:
            // Its unorthodox use of the Password-Based Key Derivation
            // Function 2 (PBKDF2) of PKCS #5 V2.0 alias RFC 2898.
            // Yes, the password verifier is only a 16 bit value.
            // So we must use the MAC for password verification, too.
            $keyParam = hash_pbkdf2("sha1", $this->entry->getPassword(), $salt, self::ITERATION_COUNT, (2 * $keyStrengthBits + self::PWD_VERIFIER_BITS) / 8, true);
            $ctrIvSize = self::AES_BLOCK_SIZE_BITS / 8;
            $iv = str_repeat(chr(0), $ctrIvSize);

            $key = substr($keyParam, 0, $keyStrengthBytes);

            $sha1MacParam = substr($keyParam, $keyStrengthBytes, $keyStrengthBytes);
            // Verify password.
        } while (!$passwordVerifier === substr($keyParam, 2 * $keyStrengthBytes));

        $content = stream_get_contents($stream, $size, $start);
        $mac = hash_hmac('sha1', $content, $sha1MacParam, true);

        if ($authenticationCode !== substr($mac, 0, 10)) {
            throw new ZipAuthenticationException($this->entry->getName() . " (authenticated WinZip AES entry content has been tampered with)");
        }

        return self::aesCtrSegmentIntegerCounter(false, $content, $key, $iv);
    }

    /**
     * Decryption or encryption AES-CTR with Segment Integer Count (SIC).
     *
     * @param bool $encrypted If true encryption else decryption
     * @param string $str Data
     * @param string $key Key
     * @param string $iv IV
     * @return string
     */
    private static function aesCtrSegmentIntegerCounter($encrypted = true, $str, $key, $iv)
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
            return openssl_encrypt($data, 'AES-' . $numBits . '-CTR', $key, OPENSSL_RAW_DATA, $iv);
        } elseif (extension_loaded("mcrypt")) {
            return mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $data, "ctr", $iv);
        } else {
            throw new \RuntimeException('Extension openssl or mcrypt not loaded');
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
            return openssl_decrypt($data, 'AES-' . $numBits . '-CTR', $key, OPENSSL_RAW_DATA, $iv);
        } elseif (extension_loaded("mcrypt")) {
            return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $data, "ctr", $iv);
        } else {
            throw new \RuntimeException('Extension openssl or mcrypt not loaded');
        }
    }

    /**
     * Encryption string.
     *
     * @param string $content
     * @return string
     */
    public function encrypt($content)
    {
        // Init key strength.
        $password = $this->entry->getPassword();
        assert($password !== null);

        $keyStrengthBytes = 32;
        $keyStrengthBits = $keyStrengthBytes * 8;

        assert(self::AES_BLOCK_SIZE_BITS <= $keyStrengthBits);

        $salt = CryptoUtil::randomBytes($keyStrengthBytes / 2);

        $keyParam = hash_pbkdf2("sha1", $password, $salt, self::ITERATION_COUNT, (2 * $keyStrengthBits + self::PWD_VERIFIER_BITS) / 8, true);
        $sha1HMacParam = substr($keyParam, $keyStrengthBytes, $keyStrengthBytes);

        // Can you believe they "forgot" the nonce in the CTR mode IV?! :-(
        $ctrIvSize = self::AES_BLOCK_SIZE_BITS / 8;
        $iv = str_repeat(chr(0), $ctrIvSize);

        $key = substr($keyParam, 0, $keyStrengthBytes);

        $content = self::aesCtrSegmentIntegerCounter(true, $content, $key, $iv);

        $mac = hash_hmac('sha1', $content, $sha1HMacParam, true);

        return ($salt .
            substr($keyParam, 2 * $keyStrengthBytes, self::PWD_VERIFIER_BITS / 8) .
            $content .
            substr($mac, 0, 10)
        );
    }
}