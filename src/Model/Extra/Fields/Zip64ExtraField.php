<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Model\Extra\Fields;

use PhpZip\Constants\ZipConstants;
use PhpZip\Exception\RuntimeException;
use PhpZip\Exception\ZipException;
use PhpZip\Model\Extra\ZipExtraField;
use PhpZip\Model\ZipEntry;

/**
 * ZIP64 Extra Field.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 */
final class Zip64ExtraField implements ZipExtraField
{
    /** @var int The Header ID for a ZIP64 Extended Information Extra Field. */
    public const HEADER_ID = 0x0001;

    private ?int $uncompressedSize;

    private ?int $compressedSize;

    private ?int $localHeaderOffset;

    private ?int $diskStart;

    public function __construct(
        ?int $uncompressedSize = null,
        ?int $compressedSize = null,
        ?int $localHeaderOffset = null,
        ?int $diskStart = null
    ) {
        $this->uncompressedSize = $uncompressedSize;
        $this->compressedSize = $compressedSize;
        $this->localHeaderOffset = $localHeaderOffset;
        $this->diskStart = $diskStart;
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
     * @return Zip64ExtraField
     */
    public static function unpackLocalFileData(string $buffer, ?ZipEntry $entry = null): self
    {
        $length = \strlen($buffer);

        if ($length === 0) {
            // no local file data at all, may happen if an archive
            // only holds a ZIP64 extended information extra field
            // inside the central directory but not inside the local
            // file header
            return new self();
        }

        if ($length < 16) {
            throw new ZipException(
                'Zip64 extended information must contain both size values in the local file header.'
            );
        }

        [
            'uncompressedSize' => $uncompressedSize,
            'compressedSize' => $compressedSize,
        ] = unpack('PuncompressedSize/PcompressedSize', substr($buffer, 0, 16));

        return new self($uncompressedSize, $compressedSize);
    }

    /**
     * Populate data from this array as if it was in central directory data.
     *
     * @param string    $buffer the buffer to read data from
     * @param ?ZipEntry $entry
     *
     * @throws ZipException
     *
     * @return Zip64ExtraField
     */
    public static function unpackCentralDirData(string $buffer, ?ZipEntry $entry = null): self
    {
        if ($entry === null) {
            throw new RuntimeException('zipEntry is null');
        }

        $length = \strlen($buffer);
        $remaining = $length;

        $uncompressedSize = null;
        $compressedSize = null;
        $localHeaderOffset = null;
        $diskStart = null;

        if ($entry->getUncompressedSize() === ZipConstants::ZIP64_MAGIC) {
            if ($remaining < 8) {
                throw new ZipException('ZIP64 extension corrupt (no uncompressed size).');
            }
            $uncompressedSize = unpack('P', substr($buffer, $length - $remaining, 8))[1];
            $remaining -= 8;
        }

        if ($entry->getCompressedSize() === ZipConstants::ZIP64_MAGIC) {
            if ($remaining < 8) {
                throw new ZipException('ZIP64 extension corrupt (no compressed size).');
            }
            $compressedSize = unpack('P', substr($buffer, $length - $remaining, 8))[1];
            $remaining -= 8;
        }

        if ($entry->getLocalHeaderOffset() === ZipConstants::ZIP64_MAGIC) {
            if ($remaining < 8) {
                throw new ZipException('ZIP64 extension corrupt (no relative local header offset).');
            }
            $localHeaderOffset = unpack('P', substr($buffer, $length - $remaining, 8))[1];
            $remaining -= 8;
        }

        if ($remaining === 4) {
            $diskStart = unpack('V', substr($buffer, $length - $remaining, 4))[1];
        }

        return new self($uncompressedSize, $compressedSize, $localHeaderOffset, $diskStart);
    }

    /**
     * The actual data to put into local file data - without Header-ID
     * or length specifier.
     *
     * @return string the data
     */
    public function packLocalFileData(): string
    {
        if ($this->uncompressedSize !== null || $this->compressedSize !== null) {
            if ($this->uncompressedSize === null || $this->compressedSize === null) {
                throw new \InvalidArgumentException(
                    'Zip64 extended information must contain both size values in the local file header.'
                );
            }

            return $this->packSizes();
        }

        return '';
    }

    private function packSizes(): string
    {
        $data = '';

        if ($this->uncompressedSize !== null) {
            $data .= pack('P', $this->uncompressedSize);
        }

        if ($this->compressedSize !== null) {
            $data .= pack('P', $this->compressedSize);
        }

        return $data;
    }

    /**
     * The actual data to put into central directory - without Header-ID or
     * length specifier.
     *
     * @return string the data
     */
    public function packCentralDirData(): string
    {
        $data = $this->packSizes();

        if ($this->localHeaderOffset !== null) {
            $data .= pack('P', $this->localHeaderOffset);
        }

        if ($this->diskStart !== null) {
            $data .= pack('V', $this->diskStart);
        }

        return $data;
    }

    public function getUncompressedSize(): ?int
    {
        return $this->uncompressedSize;
    }

    public function setUncompressedSize(?int $uncompressedSize): void
    {
        $this->uncompressedSize = $uncompressedSize;
    }

    public function getCompressedSize(): ?int
    {
        return $this->compressedSize;
    }

    public function setCompressedSize(?int $compressedSize): void
    {
        $this->compressedSize = $compressedSize;
    }

    public function getLocalHeaderOffset(): ?int
    {
        return $this->localHeaderOffset;
    }

    public function setLocalHeaderOffset(?int $localHeaderOffset): void
    {
        $this->localHeaderOffset = $localHeaderOffset;
    }

    public function getDiskStart(): ?int
    {
        return $this->diskStart;
    }

    public function setDiskStart(?int $diskStart): void
    {
        $this->diskStart = $diskStart;
    }

    public function __toString(): string
    {
        $args = [self::HEADER_ID];
        $format = '0x%04x ZIP64: ';
        $formats = [];

        if ($this->uncompressedSize !== null) {
            $formats[] = 'SIZE=%d';
            $args[] = $this->uncompressedSize;
        }

        if ($this->compressedSize !== null) {
            $formats[] = 'COMP_SIZE=%d';
            $args[] = $this->compressedSize;
        }

        if ($this->localHeaderOffset !== null) {
            $formats[] = 'OFFSET=%d';
            $args[] = $this->localHeaderOffset;
        }

        if ($this->diskStart !== null) {
            $formats[] = 'DISK_START=%d';
            $args[] = $this->diskStart;
        }
        $format .= implode(' ', $formats);

        return vsprintf($format, $args);
    }
}
