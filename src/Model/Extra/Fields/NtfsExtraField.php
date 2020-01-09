<?php

namespace PhpZip\Model\Extra\Fields;

use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Model\Extra\ZipExtraField;
use PhpZip\Model\ZipEntry;
use PhpZip\Util\PackUtil;

/**
 * NTFS Extra Field.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 *
 * @license MIT
 */
class NtfsExtraField implements ZipExtraField
{
    /** @var int Header id */
    const HEADER_ID = 0x000a;

    /** @var int Tag ID */
    const TIME_ATTR_TAG = 0x0001;

    /** @var int Attribute size */
    const TIME_ATTR_SIZE = 24; // 3 * 8

    /**
     * @var int A file time is a 64-bit value that represents the number of
     *          100-nanosecond intervals that have elapsed since 12:00
     *          A.M. January 1, 1601 Coordinated Universal Time (UTC).
     *          this is the offset of Windows time 0 to Unix epoch in 100-nanosecond intervals.
     */
    const EPOCH_OFFSET = -11644473600;

    /** @var int Modify ntfs time */
    private $modifyTime;

    /** @var int Access ntfs time */
    private $accessTime;

    /** @var int Create ntfs time */
    private $createTime;

    /**
     * @param int $modifyTime
     * @param int $accessTime
     * @param int $createTime
     */
    public function __construct($modifyTime, $accessTime, $createTime)
    {
        $this->modifyTime = (int) $modifyTime;
        $this->accessTime = (int) $accessTime;
        $this->createTime = (int) $createTime;
    }

    /**
     * @param \DateTimeInterface $mtime
     * @param \DateTimeInterface $atime
     * @param \DateTimeInterface $ctime
     *
     * @return NtfsExtraField
     */
    public static function create(\DateTimeInterface $mtime, \DateTimeInterface $atime, \DateTimeInterface $ctime)
    {
        return new self(
            self::dateTimeToNtfsTime($mtime),
            self::dateTimeToNtfsTime($atime),
            self::dateTimeToNtfsTime($ctime)
        );
    }

    /**
     * Returns the Header ID (type) of this Extra Field.
     * The Header ID is an unsigned short integer (two bytes)
     * which must be constant during the life cycle of this object.
     *
     * @return int
     */
    public function getHeaderId()
    {
        return self::HEADER_ID;
    }

    /**
     * Populate data from this array as if it was in local file data.
     *
     * @param string        $buffer the buffer to read data from
     * @param ZipEntry|null $entry
     *
     * @return NtfsExtraField
     */
    public static function unpackLocalFileData($buffer, ZipEntry $entry = null)
    {
        $buffer = substr($buffer, 4);

        $modifyTime = 0;
        $accessTime = 0;
        $createTime = 0;

        while ($buffer || $buffer !== '') {
            $unpack = unpack('vtag/vsizeAttr', $buffer);

            if ($unpack['tag'] === self::TIME_ATTR_TAG && $unpack['sizeAttr'] === self::TIME_ATTR_SIZE) {
                // refactoring will be needed when php 5.5 support ends
                $modifyTime = PackUtil::unpackLongLE(substr($buffer, 4, 8));
                $accessTime = PackUtil::unpackLongLE(substr($buffer, 12, 8));
                $createTime = PackUtil::unpackLongLE(substr($buffer, 20, 8));

                break;
            }
            $buffer = substr($buffer, 4 + $unpack['sizeAttr']);
        }

        return new self($modifyTime, $accessTime, $createTime);
    }

    /**
     * Populate data from this array as if it was in central directory data.
     *
     * @param string        $buffer the buffer to read data from
     * @param ZipEntry|null $entry
     *
     * @return NtfsExtraField
     */
    public static function unpackCentralDirData($buffer, ZipEntry $entry = null)
    {
        return self::unpackLocalFileData($buffer, $entry);
    }

    /**
     * The actual data to put into local file data - without Header-ID
     * or length specifier.
     *
     * @return string the data
     */
    public function packLocalFileData()
    {
        $data = pack('Vvv', 0, self::TIME_ATTR_TAG, self::TIME_ATTR_SIZE);
        // refactoring will be needed when php 5.5 support ends
        $data .= PackUtil::packLongLE($this->modifyTime);
        $data .= PackUtil::packLongLE($this->accessTime);
        $data .= PackUtil::packLongLE($this->createTime);

        return $data;
    }

    /**
     * The actual data to put into central directory - without Header-ID or
     * length specifier.
     *
     * @return string the data
     */
    public function packCentralDirData()
    {
        return $this->packLocalFileData();
    }

    /**
     * @return \DateTimeInterface
     */
    public function getModifyDateTime()
    {
        return self::ntfsTimeToDateTime($this->modifyTime);
    }

    /**
     * @param \DateTimeInterface $modifyTime
     */
    public function setModifyDateTime(\DateTimeInterface $modifyTime)
    {
        $this->modifyTime = self::dateTimeToNtfsTime($modifyTime);
    }

    /**
     * @return \DateTimeInterface
     */
    public function getAccessDateTime()
    {
        return self::ntfsTimeToDateTime($this->accessTime);
    }

    /**
     * @param \DateTimeInterface $accessTime
     */
    public function setAccessDateTime(\DateTimeInterface $accessTime)
    {
        $this->accessTime = self::dateTimeToNtfsTime($accessTime);
    }

    /**
     * @return \DateTimeInterface
     */
    public function getCreateDateTime()
    {
        return self::ntfsTimeToDateTime($this->createTime);
    }

    /**
     * @param \DateTimeInterface $createTime
     */
    public function setCreateDateTime(\DateTimeInterface $createTime)
    {
        $this->createTime = self::dateTimeToNtfsTime($createTime);
    }

    /**
     * @param \DateTimeInterface $dateTime
     *
     * @return int
     */
    public static function dateTimeToNtfsTime(\DateTimeInterface $dateTime)
    {
        return $dateTime->getTimestamp() * 10000000 + self::EPOCH_OFFSET;
    }

    /**
     * @param int $time
     *
     * @return \DateTimeInterface
     */
    public static function ntfsTimeToDateTime($time)
    {
        $timestamp = (int) ($time / 10000000 + self::EPOCH_OFFSET);

        try {
            return new \DateTimeImmutable('@' . $timestamp);
        } catch (\Exception $e) {
            throw new InvalidArgumentException('Cannot create date/time object for timestamp ' . $timestamp, 1, $e);
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $args = [self::HEADER_ID];
        $format = '0x%04x NtfsExtra:';

        if ($this->modifyTime !== 0) {
            $format .= ' Modify:[%s]';
            $args[] = $this->getModifyDateTime()->format(\DATE_ATOM);
        }

        if ($this->accessTime !== 0) {
            $format .= ' Access:[%s]';
            $args[] = $this->getAccessDateTime()->format(\DATE_ATOM);
        }

        if ($this->createTime !== 0) {
            $format .= ' Create:[%s]';
            $args[] = $this->getCreateDateTime()->format(\DATE_ATOM);
        }

        return vsprintf($format, $args);
    }
}
