<?php
namespace PhpZip\Util;

use PhpZip\Exception\ZipException;

/**
 * Pack util
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class PackUtil
{

    /**
     * @param int|string $longValue
     * @return string
     */
    public static function packLongLE($longValue)
    {
        if (version_compare(PHP_VERSION, '5.6.3') >= 0) {
            return pack("P", $longValue);
        }

        $left = 0xffffffff00000000;
        $right = 0x00000000ffffffff;

        $r = ($longValue & $left) >> 32;
        $l = $longValue & $right;

        return pack('VV', $l, $r);
    }

    /**
     * @param string|int $value
     * @return int
     * @throws ZipException
     */
    public static function unpackLongLE($value)
    {
        if (version_compare(PHP_VERSION, '5.6.3') >= 0) {
            return current(unpack('P', $value));
        }
        $unpack = unpack('Va/Vb', $value);
        return $unpack['a'] + ($unpack['b'] << 32);
    }

}