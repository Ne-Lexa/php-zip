<?php
namespace PhpZip\Extra;

use PhpZip\Exception\ZipException;

/**
 * WinZip AES Extra Field.
 *
 * @see http://www.winzip.com/win/en/aes_info.htm AES Encryption Information: Encryption Specification AE-1 and AE-2 (WinZip Computing, S.L.)
 * @see http://www.winzip.com/win/en/aes_tips.htm AES Coding Tips for Developers (WinZip Computing, S.L.)
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class WinZipAesEntryExtraField extends ExtraField
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

    private static $keyStrengths = [
        self::KEY_STRENGTH_128BIT => 0x01,
        self::KEY_STRENGTH_192BIT => 0x02,
        self::KEY_STRENGTH_256BIT => 0x03
    ];

    /**
     * Vendor version.
     *
     * @var int
     */
    private $vendorVersion = self::VV_AE_1;

    /**
     * Encryption strength.
     *
     * @var int
     */
    private $encryptionStrength = self::KEY_STRENGTH_256BIT;

    /**
     * Zip compression method.
     *
     * @var int
     */
    private $method;

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
     * Returns the Data Size of this Extra Field.
     * The Data Size is an unsigned short integer (two bytes)
     * which indicates the length of the Data Block in bytes and does not
     * include its own size in this Extra Field.
     * This property may be initialized by calling ExtraField::readFrom.
     *
     * @return int The size of the Data Block in bytes
     *         or 0 if unknown.
     */
    public function getDataSize()
    {
        return self::DATA_SIZE;
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
     * Initializes this Extra Field by deserializing a Data Block of
     * size bytes $size from the resource $handle at the zero based offset $off.
     *
     * @param resource $handle
     * @param int $off Offset bytes
     * @param int $size Size
     * @throws ZipException
     */
    public function readFrom($handle, $off, $size)
    {
        if (self::DATA_SIZE != $size)
            throw new ZipException();

        fseek($handle, $off, SEEK_SET);
        /**
         * @var int $vendorVersion
         * @var int $vendorId
         * @var int $keyStrength
         * @var int $method
         */
        $unpack = unpack('vvendorVersion/vvendorId/ckeyStrength/vmethod', fread($handle, 7));
        extract($unpack);
        $this->setVendorVersion($vendorVersion);
        if (self::VENDOR_ID != $vendorId) {
            throw new ZipException();
        }
        $this->setKeyStrength(self::keyStrength($keyStrength)); // checked
        $this->setMethod($method);
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
        return isset(self::$keyStrengths[$keyStrength]) ? self::$keyStrengths[$keyStrength] : self::$keyStrengths[self::KEY_STRENGTH_128BIT];
    }

    /**
     * Serializes a Data Block of ExtraField::getDataSize bytes to the
     * resource $handle at the zero based offset $off.
     *
     * @param resource $handle
     * @param int $off Offset bytes
     */
    public function writeTo($handle, $off)
    {
        fseek($handle, $off, SEEK_SET);
        fwrite($handle, pack('vvcv', $this->vendorVersion, self::VENDOR_ID, $this->encryptionStrength, $this->method));
    }
}