<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Model\Extra\Fields;

use PhpZip\Exception\ZipException;
use PhpZip\Model\Extra\ZipExtraField;
use PhpZip\Model\ZipEntry;

/**
 * A common base class for Unicode extra information extra fields.
 */
abstract class AbstractUnicodeExtraField implements ZipExtraField
{
    public const DEFAULT_VERSION = 0x01;

    private int $crc32;

    private string $unicodeValue;

    public function __construct(int $crc32, string $unicodeValue)
    {
        $this->crc32 = $crc32;
        $this->unicodeValue = $unicodeValue;
    }

    /**
     * @return int the CRC32 checksum of the filename or comment as
     *             encoded in the central directory of the zip file
     */
    public function getCrc32(): int
    {
        return $this->crc32;
    }

    public function setCrc32(int $crc32): void
    {
        $this->crc32 = $crc32;
    }

    public function getUnicodeValue(): string
    {
        return $this->unicodeValue;
    }

    /**
     * @param string $unicodeValue the UTF-8 encoded name to set
     */
    public function setUnicodeValue(string $unicodeValue): void
    {
        $this->unicodeValue = $unicodeValue;
    }

    /**
     * Populate data from this array as if it was in local file data.
     *
     * @param string        $buffer the buffer to read data from
     * @param ZipEntry|null $entry  optional zip entry
     *
     * @throws ZipException on error
     *
     * @return static
     */
    public static function unpackLocalFileData(string $buffer, ?ZipEntry $entry = null): self
    {
        if (\strlen($buffer) < 5) {
            throw new ZipException('Unicode path extra data must have at least 5 bytes.');
        }

        [
            'version' => $version,
            'crc32' => $crc32,
        ] = unpack('Cversion/Vcrc32', $buffer);

        if ($version !== self::DEFAULT_VERSION) {
            throw new ZipException(sprintf('Unsupported version [%d] for Unicode path extra data.', $version));
        }

        $unicodeValue = substr($buffer, 5);

        return new static($crc32, $unicodeValue);
    }

    /**
     * Populate data from this array as if it was in central directory data.
     *
     * @param string        $buffer the buffer to read data from
     * @param ZipEntry|null $entry  optional zip entry
     *
     * @throws ZipException on error
     *
     * @return static
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
            'CV',
            self::DEFAULT_VERSION,
            $this->crc32
        )
            . $this->unicodeValue;
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
}
