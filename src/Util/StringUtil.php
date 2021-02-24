<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Util;

/**
 * String Util.
 *
 * @internal
 */
final class StringUtil
{
    public static function endsWith(string $haystack, string $needle): bool
    {
        return $needle === '' || ($haystack !== '' && substr_compare($haystack, $needle, -\strlen($needle)) === 0);
    }

    public static function isBinary(string $string): bool
    {
        return strpos($string, "\0") !== false;
    }

    public static function isASCII(string $name): bool
    {
        return preg_match('~[^\x20-\x7e]~', $name) === 0;
    }
}
