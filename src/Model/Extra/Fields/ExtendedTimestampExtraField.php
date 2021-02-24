<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Model\Extra\Fields;

use PhpZip\Model\Extra\ZipExtraField;
use PhpZip\Model\ZipEntry;

/**
 * Extended Timestamp Extra Field:
 * ==============================.
 *
 * The following is the layout of the extended-timestamp extra block.
 * (Last Revision 19970118)
 *
 * Local-header version:
 *
 * Value         Size        Description
 * -----         ----        -----------
 * (time) 0x5455 Short       tag for this extra block type ("UT")
 * TSize         Short       total data size for this block
 * Flags         Byte        info bits
 * (ModTime)     Long        time of last modification (UTC/GMT)
 * (AcTime)      Long        time of last access (UTC/GMT)
 * (CrTime)      Long        time of original creation (UTC/GMT)
 *
 * Central-header version:
 *
 * Value         Size        Description
 * -----         ----        -----------
 * (time) 0x5455 Short       tag for this extra block type ("UT")
 * TSize         Short       total data size for this block
 * Flags         Byte        info bits (refers to local header!)
 * (ModTime)     Long        time of last modification (UTC/GMT)
 *
 * The central-header extra field contains the modification time only,
 * or no timestamp at all.  TSize is used to flag its presence or
 * absence.  But note:
 *
 * If "Flags" indicates that Modtime is present in the local header
 * field, it MUST be present in the central header field, too!
 * This correspondence is required because the modification time
 * value may be used to support trans-timezone freshening and
 * updating operations with zip archives.
 *
 * The time values are in standard Unix signed-long format, indicating
 * the number of seconds since 1 January 1970 00:00:00.  The times
 * are relative to Coordinated Universal Time (UTC), also sometimes
 * referred to as Greenwich Mean Time (GMT).  To convert to local time,
 * the software must know the local timezone offset from UTC/GMT.
 *
 * The lower three bits of Flags in both headers indicate which time-
 * stamps are present in the LOCAL extra field:
 *
 * bit 0           if set, modification time is present
 * bit 1           if set, access time is present
 * bit 2           if set, creation time is present
 * bits 3-7        reserved for additional timestamps; not set
 *
 * Those times that are present will appear in the order indicated, but
 * any combination of times may be omitted.  (Creation time may be
 * present without access time, for example.)  TSize should equal
 * (1 + 4*(number of set bits in Flags)), as the block is currently
 * defined.  Other timestamps may be added in the future.
 *
 * @see ftp://ftp.info-zip.org/pub/infozip/doc/appnote-iz-latest.zip Info-ZIP version Specification
 */
final class ExtendedTimestampExtraField implements ZipExtraField
{
    /** @var int Header id */
    public const HEADER_ID = 0x5455;

    /**
     * @var int the bit set inside the flags by when the last modification time
     *          is present in this extra field
     */
    public const MODIFY_TIME_BIT = 1;

    /**
     * @var int the bit set inside the flags by when the last access time is
     *          present in this extra field
     */
    public const ACCESS_TIME_BIT = 2;

    /**
     * @var int the bit set inside the flags by when the original creation time
     *          is present in this extra field
     */
    public const CREATE_TIME_BIT = 4;

    /**
     * @var int The 3 boolean fields (below) come from this flags byte.  The remaining 5 bits
     *          are ignored according to the current version of the spec (December 2012).
     */
    private int $flags;

    /** @var int|null Modify time */
    private ?int $modifyTime;

    /** @var int|null Access time */
    private ?int $accessTime;

    /** @var int|null Create time */
    private ?int $createTime;

    public function __construct(int $flags, ?int $modifyTime, ?int $accessTime, ?int $createTime)
    {
        $this->flags = $flags;
        $this->modifyTime = $modifyTime;
        $this->accessTime = $accessTime;
        $this->createTime = $createTime;
    }

    /**
     * @param ?int $modifyTime
     * @param ?int $accessTime
     * @param ?int $createTime
     *
     * @return ExtendedTimestampExtraField
     */
    public static function create(?int $modifyTime, ?int $accessTime, ?int $createTime): self
    {
        $flags = 0;

        if ($modifyTime !== null) {
            $flags |= self::MODIFY_TIME_BIT;
        }

        if ($accessTime !== null) {
            $flags |= self::ACCESS_TIME_BIT;
        }

        if ($createTime !== null) {
            $flags |= self::CREATE_TIME_BIT;
        }

        return new self($flags, $modifyTime, $accessTime, $createTime);
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
     * @return ExtendedTimestampExtraField
     */
    public static function unpackLocalFileData(string $buffer, ?ZipEntry $entry = null): self
    {
        $length = \strlen($buffer);
        $flags = unpack('C', $buffer)[1];
        $offset = 1;

        $modifyTime = null;
        $accessTime = null;
        $createTime = null;

        if (($flags & self::MODIFY_TIME_BIT) === self::MODIFY_TIME_BIT) {
            $modifyTime = unpack('V', substr($buffer, $offset, 4))[1];
            $offset += 4;
        }

        // Notice the extra length check in case we are parsing the shorter
        // central data field (for both access and create timestamps).
        if ((($flags & self::ACCESS_TIME_BIT) === self::ACCESS_TIME_BIT) && $offset + 4 <= $length) {
            $accessTime = unpack('V', substr($buffer, $offset, 4))[1];
            $offset += 4;
        }

        if ((($flags & self::CREATE_TIME_BIT) === self::CREATE_TIME_BIT) && $offset + 4 <= $length) {
            $createTime = unpack('V', substr($buffer, $offset, 4))[1];
        }

        return new self($flags, $modifyTime, $accessTime, $createTime);
    }

    /**
     * Populate data from this array as if it was in central directory data.
     *
     * @param string        $buffer the buffer to read data from
     * @param ZipEntry|null $entry  optional zip entry
     *
     * @return ExtendedTimestampExtraField
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
        $data = '';

        if (($this->flags & self::MODIFY_TIME_BIT) === self::MODIFY_TIME_BIT && $this->modifyTime !== null) {
            $data .= pack('V', $this->modifyTime);
        }

        if (($this->flags & self::ACCESS_TIME_BIT) === self::ACCESS_TIME_BIT && $this->accessTime !== null) {
            $data .= pack('V', $this->accessTime);
        }

        if (($this->flags & self::CREATE_TIME_BIT) === self::CREATE_TIME_BIT && $this->createTime !== null) {
            $data .= pack('V', $this->createTime);
        }

        return pack('C', $this->flags) . $data;
    }

    /**
     * The actual data to put into central directory - without Header-ID or
     * length specifier.
     *
     * Note: even if bit1 and bit2 are set, the Central data will still
     * not contain access/create fields: only local data ever holds those!
     *
     * @return string the data
     */
    public function packCentralDirData(): string
    {
        $cdLength = 1 + ($this->modifyTime !== null ? 4 : 0);

        return substr($this->packLocalFileData(), 0, $cdLength);
    }

    /**
     * Gets flags byte.
     *
     * The flags byte tells us which of the three datestamp fields are
     * present in the data:
     * bit0 - modify time
     * bit1 - access time
     * bit2 - create time
     *
     * Only first 3 bits of flags are used according to the
     * latest version of the spec (December 2012).
     *
     * @return int flags byte indicating which of the
     *             three datestamp fields are present
     */
    public function getFlags(): int
    {
        return $this->flags;
    }

    /**
     * Returns the modify time (seconds since epoch) of this zip entry,
     * or null if no such timestamp exists in the zip entry.
     *
     * @return int|null modify time (seconds since epoch) or null
     */
    public function getModifyTime(): ?int
    {
        return $this->modifyTime;
    }

    /**
     * Returns the access time (seconds since epoch) of this zip entry,
     * or null if no such timestamp exists in the zip entry.
     *
     * @return int|null access time (seconds since epoch) or null
     */
    public function getAccessTime(): ?int
    {
        return $this->accessTime;
    }

    /**
     * Returns the create time (seconds since epoch) of this zip entry,
     * or null if no such timestamp exists in the zip entry.
     *
     * Note: modern linux file systems (e.g., ext2)
     * do not appear to store a "create time" value, and so
     * it's usually omitted altogether in the zip extra
     * field. Perhaps other unix systems track this.
     *
     * @return int|null create time (seconds since epoch) or null
     */
    public function getCreateTime(): ?int
    {
        return $this->createTime;
    }

    /**
     * Returns the modify time as a \DateTimeInterface
     * of this zip entry, or null if no such timestamp exists in the zip entry.
     * The milliseconds are always zeroed out, since the underlying data
     * offers only per-second precision.
     *
     * @return \DateTimeInterface|null modify time as \DateTimeInterface or null
     */
    public function getModifyDateTime(): ?\DateTimeInterface
    {
        return self::timestampToDateTime($this->modifyTime);
    }

    /**
     * Returns the access time as a \DateTimeInterface
     * of this zip entry, or null if no such timestamp exists in the zip entry.
     * The milliseconds are always zeroed out, since the underlying data
     * offers only per-second precision.
     *
     * @return \DateTimeInterface|null access time as \DateTimeInterface or null
     */
    public function getAccessDateTime(): ?\DateTimeInterface
    {
        return self::timestampToDateTime($this->accessTime);
    }

    /**
     * Returns the create time as a a \DateTimeInterface
     * of this zip entry, or null if no such timestamp exists in the zip entry.
     * The milliseconds are always zeroed out, since the underlying data
     * offers only per-second precision.
     *
     * Note: modern linux file systems (e.g., ext2)
     * do not appear to store a "create time" value, and so
     * it's usually omitted altogether in the zip extra
     * field.  Perhaps other unix systems track $this->.
     *
     * @return \DateTimeInterface|null create time as \DateTimeInterface or null
     */
    public function getCreateDateTime(): ?\DateTimeInterface
    {
        return self::timestampToDateTime($this->createTime);
    }

    /**
     * Sets the modify time (seconds since epoch) of this zip entry
     * using a integer.
     *
     * @param int|null $unixTime unix time of the modify time (seconds per epoch) or null
     */
    public function setModifyTime(?int $unixTime): void
    {
        $this->modifyTime = $unixTime;
        $this->updateFlags();
    }

    private function updateFlags(): void
    {
        $flags = 0;

        if ($this->modifyTime !== null) {
            $flags |= self::MODIFY_TIME_BIT;
        }

        if ($this->accessTime !== null) {
            $flags |= self::ACCESS_TIME_BIT;
        }

        if ($this->createTime !== null) {
            $flags |= self::CREATE_TIME_BIT;
        }
        $this->flags = $flags;
    }

    /**
     * Sets the access time (seconds since epoch) of this zip entry
     * using a integer.
     *
     * @param int|null $unixTime Unix time of the access time (seconds per epoch) or null
     */
    public function setAccessTime(?int $unixTime): void
    {
        $this->accessTime = $unixTime;
        $this->updateFlags();
    }

    /**
     * Sets the create time (seconds since epoch) of this zip entry
     * using a integer.
     *
     * @param int|null $unixTime Unix time of the create time (seconds per epoch) or null
     */
    public function setCreateTime(?int $unixTime): void
    {
        $this->createTime = $unixTime;
        $this->updateFlags();
    }

    private static function timestampToDateTime(?int $timestamp): ?\DateTimeInterface
    {
        try {
            return $timestamp !== null ? new \DateTimeImmutable('@' . $timestamp) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function __toString(): string
    {
        $args = [self::HEADER_ID];
        $format = '0x%04x ExtendedTimestamp:';

        if ($this->modifyTime !== null) {
            $format .= ' Modify:[%s]';
            $args[] = date(\DATE_W3C, $this->modifyTime);
        }

        if ($this->accessTime !== null) {
            $format .= ' Access:[%s]';
            $args[] = date(\DATE_W3C, $this->accessTime);
        }

        if ($this->createTime !== null) {
            $format .= ' Create:[%s]';
            $args[] = date(\DATE_W3C, $this->createTime);
        }

        return vsprintf($format, $args);
    }
}
