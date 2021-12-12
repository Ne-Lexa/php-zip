<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Model\Extra\Fields;

use PhpZip\Exception\RuntimeException;
use PhpZip\Model\Extra\ZipExtraField;
use PhpZip\Model\ZipEntry;

/**
 * Simple placeholder for all those extra fields we don't want to deal with.
 */
final class UnrecognizedExtraField implements ZipExtraField
{
    private int $headerId;

    /** @var string extra field data without Header-ID or length specifier */
    private string $data;

    public function __construct(int $headerId, string $data)
    {
        $this->headerId = $headerId;
        $this->data = $data;
    }

    public function setHeaderId(int $headerId): void
    {
        $this->headerId = $headerId;
    }

    /**
     * Returns the Header ID (type) of this Extra Field.
     * The Header ID is an unsigned short integer (two bytes)
     * which must be constant during the life cycle of this object.
     */
    public function getHeaderId(): int
    {
        return $this->headerId;
    }

    /**
     * Populate data from this array as if it was in local file data.
     *
     * @param string        $buffer the buffer to read data from
     * @param ZipEntry|null $entry  optional zip entry
     *
     * @return UnrecognizedExtraField
     */
    public static function unpackLocalFileData(string $buffer, ?ZipEntry $entry = null): self
    {
        throw new RuntimeException('Unsupport parse');
    }

    /**
     * Populate data from this array as if it was in central directory data.
     *
     * @param string        $buffer the buffer to read data from
     * @param ZipEntry|null $entry  optional zip entry
     *
     * @return UnrecognizedExtraField
     */
    public static function unpackCentralDirData(string $buffer, ?ZipEntry $entry = null): self
    {
        throw new RuntimeException('Unsupport parse');
    }

    /**
     * {@inheritDoc}
     */
    public function packLocalFileData(): string
    {
        return $this->data;
    }

    /**
     * {@inheritDoc}
     */
    public function packCentralDirData(): string
    {
        return $this->data;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function setData(string $data): void
    {
        $this->data = $data;
    }

    public function __toString(): string
    {
        $args = [$this->headerId, $this->data];
        $format = '0x%04x Unrecognized Extra Field: "%s"';

        return vsprintf($format, $args);
    }
}
