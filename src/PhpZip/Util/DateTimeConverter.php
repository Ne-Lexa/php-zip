<?php

namespace PhpZip\Util;

use PhpZip\Exception\ZipException;

/**
 * Convert unix timestamp values to DOS date/time values and vice versa.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class DateTimeConverter
{
    /**
     * Smallest supported DOS date/time value in a ZIP file,
     * which is January 1st, 1980 AD 00:00:00 local time.
     */
    const MIN_DOS_TIME = 0x210000; // (1 << 21) | (1 << 16)

    /**
     * Largest supported DOS date/time value in a ZIP file,
     * which is December 31st, 2107 AD 23:59:58 local time.
     */
    const MAX_DOS_TIME = 0xff9fbf7d; // ((2107 - 1980) << 25) | (12 << 21) | (31 << 16) | (23 << 11) | (59 << 5) | (58 >> 1);

    /**
     * Convert a 32 bit integer DOS date/time value to a UNIX timestamp value.
     *
     * @param int $dosTime Dos date/time
     * @return int Unix timestamp
     */
    public static function toUnixTimestamp($dosTime)
    {
        if (self::MIN_DOS_TIME > $dosTime) {
            $dosTime = self::MIN_DOS_TIME;
        } elseif (self::MAX_DOS_TIME < $dosTime) {
            $dosTime = self::MAX_DOS_TIME;
        }

        return mktime(
            ($dosTime >> 11) & 0x1f,         // hour
            ($dosTime >> 5) & 0x3f,        // minute
            2 * ($dosTime & 0x1f),         // second
            ($dosTime >> 21) & 0x0f,       // month
            ($dosTime >> 16) & 0x1f,         // day
            1980 + (($dosTime >> 25) & 0x7f) // year
        );
    }

    /**
     * Converts a UNIX timestamp value to a DOS date/time value.
     *
     * @param int $unixTimestamp The number of seconds since midnight, January 1st,
     *         1970 AD UTC.
     * @return int A DOS date/time value reflecting the local time zone and
     *         rounded down to even seconds
     *         and is in between DateTimeConverter::MIN_DOS_TIME and DateTimeConverter::MAX_DOS_TIME.
     * @throws ZipException If unix timestamp is negative.
     */
    public static function toDosTime($unixTimestamp)
    {
        if (0 > $unixTimestamp) {
            throw new ZipException("Negative unix timestamp: " . $unixTimestamp);
        }

        $date = getdate($unixTimestamp);

        if ($date['year'] < 1980) {
            return self::MIN_DOS_TIME;
        }

        $date['year'] -= 1980;
        return ($date['year'] << 25 | $date['mon'] << 21 |
            $date['mday'] << 16 | $date['hours'] << 11 |
            $date['minutes'] << 5 | $date['seconds'] >> 1);
    }
}
