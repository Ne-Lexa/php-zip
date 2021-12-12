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
 * Apk Alignment Extra Field.
 *
 * @see https://android.googlesource.com/platform/tools/apksig/+/master/src/main/java/com/android/apksig/ApkSigner.java
 * @see https://developer.android.com/studio/command-line/zipalign
 */
final class ApkAlignmentExtraField implements ZipExtraField
{
    /**
     * @var int Extensible data block/field header ID used for storing
     *          information about alignment of uncompressed entries as
     *          well as for aligning the entries's data. See ZIP
     *          appnote.txt section 4.5 Extensible data fields.
     */
    public const HEADER_ID = 0xD935;

    /** @var int */
    public const ALIGNMENT_BYTES = 4;

    /** @var int */
    public const COMMON_PAGE_ALIGNMENT_BYTES = 4096;

    private int $multiple;

    private int $padding;

    public function __construct(int $multiple, int $padding)
    {
        $this->multiple = $multiple;
        $this->padding = $padding;
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

    public function getMultiple(): int
    {
        return $this->multiple;
    }

    public function getPadding(): int
    {
        return $this->padding;
    }

    public function setMultiple(int $multiple): void
    {
        $this->multiple = $multiple;
    }

    public function setPadding(int $padding): void
    {
        $this->padding = $padding;
    }

    /**
     * Populate data from this array as if it was in local file data.
     *
     * @param string        $buffer the buffer to read data from
     * @param ZipEntry|null $entry  optional zip entry
     *
     * @throws ZipException
     *
     * @return ApkAlignmentExtraField
     */
    public static function unpackLocalFileData(string $buffer, ?ZipEntry $entry = null): self
    {
        $length = \strlen($buffer);

        if ($length < 2) {
            // This is APK alignment field.
            // FORMAT:
            //  * uint16 alignment multiple (in bytes)
            //  * remaining bytes -- padding to achieve alignment of data which starts after
            //    the extra field
            throw new ZipException(
                'Minimum 6 bytes of the extensible data block/field used for alignment of uncompressed entries.'
            );
        }
        $multiple = unpack('v', $buffer)[1];
        $padding = $length - 2;

        return new self($multiple, $padding);
    }

    /**
     * Populate data from this array as if it was in central directory data.
     *
     * @param string        $buffer the buffer to read data from
     * @param ZipEntry|null $entry  optional zip entry
     *
     * @throws ZipException on error
     *
     * @return ApkAlignmentExtraField
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
        return pack('vx' . $this->padding, $this->multiple);
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

    public function __toString(): string
    {
        return sprintf(
            '0x%04x APK Alignment: Multiple=%d Padding=%d',
            self::HEADER_ID,
            $this->multiple,
            $this->padding
        );
    }
}
