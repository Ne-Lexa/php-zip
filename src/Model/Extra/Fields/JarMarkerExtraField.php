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
use PhpZip\Model\ZipContainer;
use PhpZip\Model\ZipEntry;

/**
 * Jar Marker Extra Field.
 * An executable Java program can be packaged in a JAR file with all the libraries it uses.
 * Executable JAR files can easily be distinguished from the files packed in the JAR file
 * by the extra field in the first file, which is hexadecimal in the 0xCAFE bytes series.
 * If this extra field is added as the very first extra field of
 * the archive, Solaris will consider it an executable jar file.
 */
final class JarMarkerExtraField implements ZipExtraField
{
    /** @var int Header id. */
    public const HEADER_ID = 0xCAFE;

    public static function setJarMarker(ZipContainer $container): void
    {
        $zipEntries = $container->getEntries();

        if (!empty($zipEntries)) {
            foreach ($zipEntries as $zipEntry) {
                $zipEntry->removeExtraField(self::HEADER_ID);
            }
            // set jar execute bit
            reset($zipEntries);
            $zipEntry = current($zipEntries);
            $zipEntry->getCdExtraFields()[] = new self();
        }
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
     * The actual data to put into local file data - without Header-ID
     * or length specifier.
     *
     * @return string the data
     */
    public function packLocalFileData(): string
    {
        return '';
    }

    /**
     * The actual data to put into central directory - without Header-ID or
     * length specifier.
     *
     * @return string the data
     */
    public function packCentralDirData(): string
    {
        return '';
    }

    /**
     * Populate data from this array as if it was in local file data.
     *
     * @param string        $buffer the buffer to read data from
     * @param ZipEntry|null $entry  optional zip entry
     *
     * @throws ZipException on error
     *
     * @return JarMarkerExtraField
     */
    public static function unpackLocalFileData(string $buffer, ?ZipEntry $entry = null): self
    {
        if (!empty($buffer)) {
            throw new ZipException("JarMarker doesn't expect any data");
        }

        return new self();
    }

    /**
     * Populate data from this array as if it was in central directory data.
     *
     * @param string        $buffer the buffer to read data from
     * @param ZipEntry|null $entry  optional zip entry
     *
     * @throws ZipException on error
     *
     * @return JarMarkerExtraField
     */
    public static function unpackCentralDirData(string $buffer, ?ZipEntry $entry = null): self
    {
        return self::unpackLocalFileData($buffer, $entry);
    }

    public function __toString(): string
    {
        return sprintf('0x%04x Jar Marker', self::HEADER_ID);
    }
}
