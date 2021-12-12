<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Model\Extra\Fields;

use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipException;
use PhpZip\Model\Extra\ZipExtraField;
use PhpZip\Model\ZipEntry;

/**
 * NTFS Extra Field.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 */
final class NtfsExtraField implements ZipExtraField
{
    /** @var int Header id */
    public const HEADER_ID = 0x000A;

    /** @var int Tag ID */
    public const TIME_ATTR_TAG = 0x0001;

    /** @var int Attribute size */
    public const TIME_ATTR_SIZE = 24; // 3 * 8

    /**
     * @var int A file time is a 64-bit value that represents the number of
     *          100-nanosecond intervals that have elapsed since 12:00
     *          A.M. January 1, 1601 Coordinated Universal Time (UTC).
     *          this is the offset of Windows time 0 to Unix epoch in 100-nanosecond intervals.
     */
    public const EPOCH_OFFSET = -116444736000000000;

    /** @var int Modify ntfs time */
    private int $modifyNtfsTime;

    /** @var int Access ntfs time */
    private int $accessNtfsTime;

    /** @var int Create ntfs time */
    private int $createNtfsTime;

    public function __construct(int $modifyNtfsTime, int $accessNtfsTime, int $createNtfsTime)
    {
        $this->modifyNtfsTime = $modifyNtfsTime;
        $this->accessNtfsTime = $accessNtfsTime;
        $this->createNtfsTime = $createNtfsTime;
    }

    /**
     * @return NtfsExtraField
     */
    public static function create(
        \DateTimeInterface $modifyDateTime,
        \DateTimeInterface $accessDateTime,
        \DateTimeInterface $createNtfsTime
    ): self {
        return new self(
            self::dateTimeToNtfsTime($modifyDateTime),
            self::dateTimeToNtfsTime($accessDateTime),
            self::dateTimeToNtfsTime($createNtfsTime)
        );
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
     * @return NtfsExtraField
     */
    public static function unpackLocalFileData(string $buffer, ?ZipEntry $entry = null): self
    {
        if (\PHP_INT_SIZE === 4) {
            throw new ZipException('not supported for php-32bit');
        }

        $buffer = substr($buffer, 4);

        $modifyTime = 0;
        $accessTime = 0;
        $createTime = 0;

        while ($buffer || $buffer !== '') {
            [
                'tag' => $tag,
                'sizeAttr' => $sizeAttr,
            ] = unpack('vtag/vsizeAttr', $buffer);

            if ($tag === self::TIME_ATTR_TAG && $sizeAttr === self::TIME_ATTR_SIZE) {
                [
                    'modifyTime' => $modifyTime,
                    'accessTime' => $accessTime,
                    'createTime' => $createTime,
                ] = unpack('PmodifyTime/PaccessTime/PcreateTime', substr($buffer, 4, 24));

                break;
            }
            $buffer = substr($buffer, 4 + $sizeAttr);
        }

        return new self($modifyTime, $accessTime, $createTime);
    }

    /**
     * Populate data from this array as if it was in central directory data.
     *
     * @param string        $buffer the buffer to read data from
     * @param ZipEntry|null $entry  optional zip entry
     *
     * @throws ZipException
     *
     * @return NtfsExtraField
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
            'VvvPPP',
            0,
            self::TIME_ATTR_TAG,
            self::TIME_ATTR_SIZE,
            $this->modifyNtfsTime,
            $this->accessNtfsTime,
            $this->createNtfsTime
        );
    }

    public function getModifyNtfsTime(): int
    {
        return $this->modifyNtfsTime;
    }

    public function setModifyNtfsTime(int $modifyNtfsTime): void
    {
        $this->modifyNtfsTime = $modifyNtfsTime;
    }

    public function getAccessNtfsTime(): int
    {
        return $this->accessNtfsTime;
    }

    public function setAccessNtfsTime(int $accessNtfsTime): void
    {
        $this->accessNtfsTime = $accessNtfsTime;
    }

    public function getCreateNtfsTime(): int
    {
        return $this->createNtfsTime;
    }

    public function setCreateNtfsTime(int $createNtfsTime): void
    {
        $this->createNtfsTime = $createNtfsTime;
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

    public function getModifyDateTime(): \DateTimeInterface
    {
        return self::ntfsTimeToDateTime($this->modifyNtfsTime);
    }

    public function setModifyDateTime(\DateTimeInterface $modifyTime): void
    {
        $this->modifyNtfsTime = self::dateTimeToNtfsTime($modifyTime);
    }

    public function getAccessDateTime(): \DateTimeInterface
    {
        return self::ntfsTimeToDateTime($this->accessNtfsTime);
    }

    public function setAccessDateTime(\DateTimeInterface $accessTime): void
    {
        $this->accessNtfsTime = self::dateTimeToNtfsTime($accessTime);
    }

    public function getCreateDateTime(): \DateTimeInterface
    {
        return self::ntfsTimeToDateTime($this->createNtfsTime);
    }

    public function setCreateDateTime(\DateTimeInterface $createTime): void
    {
        $this->createNtfsTime = self::dateTimeToNtfsTime($createTime);
    }

    /**
     * @param float $timestamp Float timestamp
     */
    public static function timestampToNtfsTime(float $timestamp): int
    {
        return (int) (($timestamp * 10000000) - self::EPOCH_OFFSET);
    }

    public static function dateTimeToNtfsTime(\DateTimeInterface $dateTime): int
    {
        return self::timestampToNtfsTime((float) $dateTime->format('U.u'));
    }

    /**
     * @return float Float unix timestamp
     */
    public static function ntfsTimeToTimestamp(int $ntfsTime): float
    {
        return (float) (($ntfsTime + self::EPOCH_OFFSET) / 10000000);
    }

    public static function ntfsTimeToDateTime(int $ntfsTime): \DateTimeInterface
    {
        $timestamp = self::ntfsTimeToTimestamp($ntfsTime);
        $dateTime = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6f', $timestamp));

        if ($dateTime === false) {
            throw new InvalidArgumentException('Cannot create date/time object for timestamp ' . $timestamp);
        }

        return $dateTime;
    }

    public function __toString(): string
    {
        $args = [self::HEADER_ID];
        $format = '0x%04x NtfsExtra:';

        if ($this->modifyNtfsTime !== 0) {
            $format .= ' Modify:[%s]';
            $args[] = $this->getModifyDateTime()->format(\DATE_ATOM);
        }

        if ($this->accessNtfsTime !== 0) {
            $format .= ' Access:[%s]';
            $args[] = $this->getAccessDateTime()->format(\DATE_ATOM);
        }

        if ($this->createNtfsTime !== 0) {
            $format .= ' Create:[%s]';
            $args[] = $this->getCreateDateTime()->format(\DATE_ATOM);
        }

        return vsprintf($format, $args);
    }
}
