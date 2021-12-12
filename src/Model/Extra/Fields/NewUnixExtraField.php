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
 * Info-ZIP New Unix Extra Field:
 * ====================================.
 *
 * Currently stores Unix UIDs/GIDs up to 32 bits.
 * (Last Revision 20080509)
 *
 * Value         Size        Description
 * -----         ----        -----------
 * (UnixN) 0x7875        Short       tag for this extra block type ("ux")
 * TSize         Short       total data size for this block
 * Version       1 byte      version of this extra field, currently 1
 * UIDSize       1 byte      Size of UID field
 * UID           Variable    UID for this entry
 * GIDSize       1 byte      Size of GID field
 * GID           Variable    GID for this entry
 *
 * Currently Version is set to the number 1.  If there is a need
 * to change this field, the version will be incremented.  Changes
 * may not be backward compatible so this extra field should not be
 * used if the version is not recognized.
 *
 * UIDSize is the size of the UID field in bytes.  This size should
 * match the size of the UID field on the target OS.
 *
 * UID is the UID for this entry in standard little endian format.
 *
 * GIDSize is the size of the GID field in bytes.  This size should
 * match the size of the GID field on the target OS.
 *
 * GID is the GID for this entry in standard little endian format.
 *
 * If both the old 16-bit Unix extra field (tag 0x7855, Info-ZIP Unix)
 * and this extra field are present, the values in this extra field
 * supercede the values in that extra field.
 */
final class NewUnixExtraField implements ZipExtraField
{
    /** @var int header id */
    public const HEADER_ID = 0x7875;

    /** ID of the first non-root user created on a unix system. */
    public const USER_GID_PID = 1000;

    /** @var int version of this extra field, currently 1 */
    private int $version;

    /** @var int User id */
    private int $uid;

    /** @var int Group id */
    private int $gid;

    public function __construct(int $version = 1, int $uid = self::USER_GID_PID, int $gid = self::USER_GID_PID)
    {
        $this->version = $version;
        $this->uid = $uid;
        $this->gid = $gid;
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
     * @param string        $buffer the buffer to read data from
     * @param ZipEntry|null $entry  optional zip entry
     *
     * @throws ZipException
     *
     * @return NewUnixExtraField
     */
    public static function unpackLocalFileData(string $buffer, ?ZipEntry $entry = null): self
    {
        $length = \strlen($buffer);

        if ($length < 3) {
            throw new ZipException(sprintf('X7875_NewUnix length is too short, only %s bytes', $length));
        }
        $offset = 0;
        [
            'version' => $version,
            'uidSize' => $uidSize,
        ] = unpack('Cversion/CuidSize', $buffer);
        $offset += 2;
        $gid = self::readSizeIntegerLE(substr($buffer, $offset, $uidSize), $uidSize);
        $offset += $uidSize;
        $gidSize = unpack('C', $buffer[$offset])[1];
        $offset++;
        $uid = self::readSizeIntegerLE(substr($buffer, $offset, $gidSize), $gidSize);

        return new self($version, $gid, $uid);
    }

    /**
     * Populate data from this array as if it was in central directory data.
     *
     * @param string        $buffer the buffer to read data from
     * @param ZipEntry|null $entry  optional zip entry
     *
     * @throws ZipException
     *
     * @return NewUnixExtraField
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
            'CCVCV',
            $this->version,
            4, // UIDSize
            $this->uid,
            4, // GIDSize
            $this->gid
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
     * @throws ZipException
     */
    private static function readSizeIntegerLE(string $data, int $size): int
    {
        $format = [
            1 => 'C', // unsigned byte
            2 => 'v', // unsigned short LE
            4 => 'V', // unsigned int LE
        ];

        if (!isset($format[$size])) {
            throw new ZipException(sprintf('Invalid size bytes: %d', $size));
        }

        return unpack($format[$size], $data)[1];
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setUid(int $uid): void
    {
        $this->uid = $uid & 0xFFFFFFFF;
    }

    public function getGid(): int
    {
        return $this->gid;
    }

    public function setGid(int $gid): void
    {
        $this->gid = $gid & 0xFFFFFFFF;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function __toString(): string
    {
        return sprintf(
            '0x%04x NewUnix: UID=%d GID=%d',
            self::HEADER_ID,
            $this->uid,
            $this->gid
        );
    }
}
