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
 * Math util.
 *
 * @internal
 */
final class MathUtil
{
    /**
     * Cast to signed int 32-bit.
     */
    public static function toSignedInt32(int $int): int
    {
        if (\PHP_INT_SIZE === 8) {
            $int &= 0xFFFFFFFF;

            if ($int & 0x80000000) {
                return $int - 0x100000000;
            }
        }

        return $int;
    }
}
