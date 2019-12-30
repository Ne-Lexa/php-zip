<?php

namespace PhpZip\Util;

/**
 * String Util.
 *
 * @internal
 */
final class StringUtil
{
    /**
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     */
    public static function startsWith($haystack, $needle)
    {
        return $needle === '' || strrpos($haystack, $needle, -\strlen($haystack)) !== false;
    }

    /**
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     */
    public static function endsWith($haystack, $needle)
    {
        return $needle === '' || (($temp = \strlen($haystack) - \strlen($needle)) >= 0
                && strpos($haystack, $needle, $temp) !== false);
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    public static function isBinary($string)
    {
        return strpos($string, "\0") !== false;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public static function isASCII($name)
    {
        return preg_match('~[^\x20-\x7e]~', (string) $name) === 0;
    }
}
