<?php

namespace PhpZip\Extra\Fields;

use PhpZip\Exception\ZipException;
use PhpZip\Extra\ExtraField;
use PhpZip\ZipFileInterface;

/**
 * WinZip AES Extra Field.
 *
 * @see http://www.winzip.com/win/en/aes_info.htm AES Encryption Information: Encryption Specification AE-1 and AE-2 (WinZip Computing, S.L.)
 * @see http://www.winzip.com/win/en/aes_tips.htm AES Coding Tips for Developers (WinZip Computing, S.L.)
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class WinZipAesEntryExtraField implements ExtraField
{
    const DATA_SIZE = 7;
    const VENDOR_ID = 17729; // 'A' | ('E' << 8);

    /**
     * Entries of this type <em>do</em> include the standard ZIP CRC-32 value.
     * For use with @see WinZipAesEntryExtraField::setVendorVersion()}/@see WinZipAesEntryExtraField::getVendorVersion().
     */
    const VV_AE_1 = 1;

    /**
     * Entries of this type do <em>not</em> include the standard ZIP CRC-32 value.
     * For use with @see WinZipAesEntryExtraField::setVendorVersion()}/@see WinZipAesEntryExtraField::getVendorVersion().
     */
    const VV_AE_2 = 2;

    const KEY_STRENGTH_128BIT = 128;
    const KEY_STRENGTH_192BIT = 192;
    const KEY_STRENGTH_256BIT = 256;

    protected static $keyStrengths = [
        self::KEY_STRENGTH_128BIT => 0x01,
        self::KEY_STRENGTH_192BIT => 0x02,
        self::KEY_STRENGTH_256BIT => 0x03
    ];

    protected static $encryptionMethods = [
        self::KEY_STRENGTH_128BIT => ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_128,
        self::KEY_STRENGTH_192BIT => ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_192,
        self::KEY_STRENGTH_256BIT => ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_256
    ];

    /**
     * Vendor version.
     *
     * @var int
     */
    protected $vendorVersion = self::VV_AE_1;

    /**
     * Encryption strength.
     *
     * @var int
     */
    protected $encryptionStrength = self::KEY_STRENGTH_256BIT;

    /**
     * Zip compression method.
     *
     * @var int
     */
    protected $method;

    /**
     * Returns the Header ID (type) of this Extra Field.
     * The Header ID is an unsigned short integer (two bytes)
     * which must be constant during the life cycle of this object.
     *
     * @return int
     */
    public static function getHeaderId()
    {
        return 0x9901;
    }

    /**
     * Returns the vendor version.
     *
     * @see WinZipAesEntryExtraField::VV_AE_1
     * @see WinZipAesEntryExtraField::VV_AE_2
     */
    public function getVendorVersion()
    {
        return $this->vendorVersion & 0xffff;
    }

    /**
     * Sets the vendor version.
     *
     * @see    WinZipAesEntryExtraField::VV_AE_1
     * @see    WinZipAesEntryExtraField::VV_AE_2
     * @param  int $vendorVersion the vendor version.
     * @throws ZipException Unsupport vendor version.
     */
    public function setVendorVersion($vendorVersion)
    {
        if ($vendorVersion < self::VV_AE_1 || self::VV_AE_2 < $vendorVersion) {
            throw new ZipException($vendorVersion);
        }
        $this->vendorVersion = $vendorVersion;
    }

    /**
     * Returns vendor id.
     *
     * @return int
     */
    public function getVendorId()
    {
        return self::VENDOR_ID;
    }

    /**
     * @return bool|int
     * @throws ZipException
     */
    public function getKeyStrength()
    {
        return self::keyStrength($this->encryptionStrength);
    }

    /**
     * @param int $encryptionStrength Encryption strength as bits.
     * @return int
     * @throws ZipException If unsupport encryption strength.
     */
    public static function keyStrength($encryptionStrength)
    {
        $flipKeyStrength = array_flip(self::$keyStrengths);
        if (!isset($flipKeyStrength[$encryptionStrength])) {
            throw new ZipException("Unsupport encryption strength " . $encryptionStrength);
        }
        return $flipKeyStrength[$encryptionStrength];
    }

    /**
     * Returns compression method.
     *
     * @return int
     */
    public function getMethod()
    {
        return $this->method & 0xffff;
    }

    /**
     * Internal encryption method.
     *
     * @return int
     * @throws ZipException
     */
    public function getEncryptionMethod()
    {
        return isset(self::$encryptionMethods[$this->getKeyStrength()]) ?
            self::$encryptionMethods[$this->getKeyStrength()] :
            self::$encryptionMethods[self::KEY_STRENGTH_256BIT];
    }

    /**
     * @param int $encryptionMethod
     * @return int
     * @throws ZipException
     */
    public static function getKeyStrangeFromEncryptionMethod($encryptionMethod)
    {
        $flipKey = array_flip(self::$encryptionMethods);
        if (!isset($flipKey[$encryptionMethod])) {
            throw new ZipException("Unsupport encryption method " . $encryptionMethod);
        }
        return $flipKey[$encryptionMethod];
    }

    /**
     * Sets compression method.
     *
     * @param int $compressionMethod Compression method
     * @throws ZipException Compression method out of range.
     */
    public function setMethod($compressionMethod)
    {
        if (0x0000 > $compressionMethod || $compressionMethod > 0xffff) {
            throw new ZipException('Compression method out of range');
        }
        $this->method = $compressionMethod;
    }

    /**
     * Set key strength.
     *
     * @param int $keyStrength
     */
    public function setKeyStrength($keyStrength)
    {
        $this->encryptionStrength = self::encryptionStrength($keyStrength);
    }

    /**
     * Returns encryption strength.
     *
     * @param int $keyStrength Key strength in bits.
     * @return int
     */
    public static function encryptionStrength($keyStrength)
    {
        return isset(self::$keyStrengths[$keyStrength]) ?
            self::$keyStrengths[$keyStrength] :
            self::$keyStrengths[self::KEY_STRENGTH_128BIT];
    }

    /**
     * Serializes a Data Block.
     * @return string
     */
    public function serialize()
    {
        return pack(
            'vvcv',
            $this->vendorVersion,
            self::VENDOR_ID,
            $this->encryptionStrength,
            $this->method
        );
    }

    /**
     * Initializes this Extra Field by deserializing a Data Block.
     * @param string $data
     * @throws ZipException
     */
    public function deserialize($data)
    {
        $size = strlen($data);
        if (self::DATA_SIZE !== $size) {
            throw new ZipException('WinZip AES Extra data invalid size: ' . $size . '. Must be ' . self::DATA_SIZE);
        }

        /**
         * @var int $vendorVersion
         * @var int $vendorId
         * @var int $keyStrength
         * @var int $method
         */
        $unpack = unpack('vvendorVersion/vvendorId/ckeyStrength/vmethod', $data);
        $this->setVendorVersion($unpack['vendorVersion']);
        if (self::VENDOR_ID !== $unpack['vendorId']) {
            throw new ZipException('Vendor id invalid: ' . $unpack['vendorId'] . '. Must be ' . self::VENDOR_ID);
        }
        $this->setKeyStrength(self::keyStrength($unpack['keyStrength'])); // checked
        $this->setMethod($unpack['method']);
    }
}
