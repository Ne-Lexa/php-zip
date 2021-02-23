<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Model\Extra\Fields;

use PhpZip\Constants\ZipCompressionMethod;
use PhpZip\Constants\ZipEncryptionMethod;
use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipException;
use PhpZip\Exception\ZipUnsupportMethodException;
use PhpZip\Model\Extra\ZipExtraField;
use PhpZip\Model\ZipEntry;

/**
 * WinZip AES Extra Field.
 *
 * @see http://www.winzip.com/win/en/aes_tips.htm AES Coding Tips for Developers
 */
final class WinZipAesExtraField implements ZipExtraField
{
    /** @var int Header id */
    public const HEADER_ID = 0x9901;

    /**
     * @var int Data size (currently 7, but subject to possible increase
     *          in the future)
     */
    public const DATA_SIZE = 7;

    /**
     * @var int The vendor ID field should always be set to the two ASCII
     *          characters "AE"
     */
    public const VENDOR_ID = 0x4541; // 'A' | ('E' << 8)

    /**
     * @var int Entries of this type do include the standard ZIP CRC-32 value.
     *          For use with {@see WinZipAesExtraField::setVendorVersion()}.
     */
    public const VERSION_AE1 = 1;

    /**
     * @var int Entries of this type do not include the standard ZIP CRC-32 value.
     *          For use with {@see WinZipAesExtraField::setVendorVersion().
     */
    public const VERSION_AE2 = 2;

    /** @var int integer mode value indicating AES encryption 128-bit strength */
    public const KEY_STRENGTH_128BIT = 0x01;

    /** @var int integer mode value indicating AES encryption 192-bit strength */
    public const KEY_STRENGTH_192BIT = 0x02;

    /** @var int integer mode value indicating AES encryption 256-bit strength */
    public const KEY_STRENGTH_256BIT = 0x03;

    /** @var int[] */
    private const ALLOW_VENDOR_VERSIONS = [
        self::VERSION_AE1,
        self::VERSION_AE2,
    ];

    /** @var array<int, int> */
    private const ENCRYPTION_STRENGTHS = [
        self::KEY_STRENGTH_128BIT => 128,
        self::KEY_STRENGTH_192BIT => 192,
        self::KEY_STRENGTH_256BIT => 256,
    ];

    /** @var array<int, int> */
    private const MAP_KEY_STRENGTH_METHODS = [
        self::KEY_STRENGTH_128BIT => ZipEncryptionMethod::WINZIP_AES_128,
        self::KEY_STRENGTH_192BIT => ZipEncryptionMethod::WINZIP_AES_192,
        self::KEY_STRENGTH_256BIT => ZipEncryptionMethod::WINZIP_AES_256,
    ];

    /** @var int Integer version number specific to the zip vendor */
    private int $vendorVersion = self::VERSION_AE1;

    /** @var int Integer mode value indicating AES encryption strength */
    private int $keyStrength = self::KEY_STRENGTH_256BIT;

    /** @var int The actual compression method used to compress the file */
    private int $compressionMethod;

    /**
     * @param int $vendorVersion     Integer version number specific to the zip vendor
     * @param int $keyStrength       Integer mode value indicating AES encryption strength
     * @param int $compressionMethod The actual compression method used to compress the file
     *
     * @throws ZipUnsupportMethodException
     */
    public function __construct(int $vendorVersion, int $keyStrength, int $compressionMethod)
    {
        $this->setVendorVersion($vendorVersion);
        $this->setKeyStrength($keyStrength);
        $this->setCompressionMethod($compressionMethod);
    }

    /**
     * @throws ZipUnsupportMethodException
     *
     * @return WinZipAesExtraField
     */
    public static function create(ZipEntry $entry): self
    {
        $keyStrength = array_search($entry->getEncryptionMethod(), self::MAP_KEY_STRENGTH_METHODS, true);

        if ($keyStrength === false) {
            throw new InvalidArgumentException('Not support encryption method ' . $entry->getEncryptionMethod());
        }

        // WinZip 11 will continue to use AE-2, with no CRC, for very small files
        // of less than 20 bytes. It will also use AE-2 for files compressed in
        // BZIP2 format, because this format has internal integrity checks
        // equivalent to a CRC check built in.
        //
        // https://www.winzip.com/win/en/aes_info.html
        $vendorVersion = (
            $entry->getUncompressedSize() < 20
            || $entry->getCompressionMethod() === ZipCompressionMethod::BZIP2
        )
            ? self::VERSION_AE2
            : self::VERSION_AE1;

        $field = new self($vendorVersion, $keyStrength, $entry->getCompressionMethod());

        $entry->getLocalExtraFields()->add($field);
        $entry->getCdExtraFields()->add($field);

        return $field;
    }

    /**
     * Returns the Header ID (type) of this Extra Field.
     * The Header ID is an unsigned short integer (two bytes)
     * which must be constant during the life cycle of this object.
     */
    public function getHeaderId(): int
    {
        return self::HEADER_ID;
    }

    /**
     * Populate data from this array as if it was in local file data.
     *
     * @param string    $buffer the buffer to read data from
     * @param ?ZipEntry $entry
     *
     * @throws ZipException on error
     *
     * @return WinZipAesExtraField
     */
    public static function unpackLocalFileData(string $buffer, ?ZipEntry $entry = null): self
    {
        $size = \strlen($buffer);

        if ($size !== self::DATA_SIZE) {
            throw new ZipException(
                sprintf(
                    'WinZip AES Extra data invalid size: %d. Must be %d',
                    $size,
                    self::DATA_SIZE
                )
            );
        }

        [
            'vendorVersion' => $vendorVersion,
            'vendorId' => $vendorId,
            'keyStrength' => $keyStrength,
            'compressionMethod' => $compressionMethod,
        ] = unpack('vvendorVersion/vvendorId/ckeyStrength/vcompressionMethod', $buffer);

        if ($vendorId !== self::VENDOR_ID) {
            throw new ZipException(
                sprintf(
                    'Vendor id invalid: %d. Must be %d',
                    $vendorId,
                    self::VENDOR_ID
                )
            );
        }

        return new self($vendorVersion, $keyStrength, $compressionMethod);
    }

    /**
     * Populate data from this array as if it was in central directory data.
     *
     * @param string    $buffer the buffer to read data from
     * @param ?ZipEntry $entry
     *
     * @throws ZipException
     *
     * @return WinZipAesExtraField
     */
    public static function unpackCentralDirData(string $buffer, ?ZipEntry $entry = null): self
    {
        return self::unpackLocalFileData($buffer, $entry);
    }

    /**
     * The actual data to put into local file data - without Header-ID
     * or length specifier.
     *
     * @return string the data
     */
    public function packLocalFileData(): string
    {
        return pack(
            'vvcv',
            $this->vendorVersion,
            self::VENDOR_ID,
            $this->keyStrength,
            $this->compressionMethod
        );
    }

    /**
     * The actual data to put into central directory - without Header-ID or
     * length specifier.
     *
     * @return string the data
     */
    public function packCentralDirData(): string
    {
        return $this->packLocalFileData();
    }

    /**
     * Returns the vendor version.
     *
     * @see WinZipAesExtraField::VERSION_AE2
     * @see WinZipAesExtraField::VERSION_AE1
     */
    public function getVendorVersion(): int
    {
        return $this->vendorVersion;
    }

    /**
     * Sets the vendor version.
     *
     * @param int $vendorVersion the vendor version
     *
     * @see    WinZipAesExtraField::VERSION_AE2
     * @see    WinZipAesExtraField::VERSION_AE1
     */
    public function setVendorVersion(int $vendorVersion): void
    {
        if (!\in_array($vendorVersion, self::ALLOW_VENDOR_VERSIONS, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unsupport WinZip AES vendor version: %d',
                    $vendorVersion
                )
            );
        }
        $this->vendorVersion = $vendorVersion;
    }

    /**
     * Returns vendor id.
     */
    public function getVendorId(): int
    {
        return self::VENDOR_ID;
    }

    public function getKeyStrength(): int
    {
        return $this->keyStrength;
    }

    /**
     * Set key strength.
     */
    public function setKeyStrength(int $keyStrength): void
    {
        if (!isset(self::ENCRYPTION_STRENGTHS[$keyStrength])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Key strength %d not support value. Allow values: %s',
                    $keyStrength,
                    implode(', ', array_keys(self::ENCRYPTION_STRENGTHS))
                )
            );
        }
        $this->keyStrength = $keyStrength;
    }

    public function getCompressionMethod(): int
    {
        return $this->compressionMethod;
    }

    /**
     * @throws ZipUnsupportMethodException
     */
    public function setCompressionMethod(int $compressionMethod): void
    {
        ZipCompressionMethod::checkSupport($compressionMethod);
        $this->compressionMethod = $compressionMethod;
    }

    public function getEncryptionStrength(): int
    {
        return self::ENCRYPTION_STRENGTHS[$this->keyStrength];
    }

    public function getEncryptionMethod(): int
    {
        $keyStrength = $this->getKeyStrength();

        if (!isset(self::MAP_KEY_STRENGTH_METHODS[$keyStrength])) {
            throw new InvalidArgumentException('Invalid encryption method');
        }

        return self::MAP_KEY_STRENGTH_METHODS[$keyStrength];
    }

    public function isV1(): bool
    {
        return $this->vendorVersion === self::VERSION_AE1;
    }

    public function isV2(): bool
    {
        return $this->vendorVersion === self::VERSION_AE2;
    }

    public function getSaltSize(): int
    {
        return (int) ($this->getEncryptionStrength() / 8 / 2);
    }

    public function __toString(): string
    {
        return sprintf(
            '0x%04x WINZIP AES: VendorVersion=%d KeyStrength=0x%02x CompressionMethod=%s',
            __CLASS__,
            $this->vendorVersion,
            $this->keyStrength,
            $this->compressionMethod
        );
    }
}
