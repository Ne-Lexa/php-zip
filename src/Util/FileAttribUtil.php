<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Util;

use PhpZip\Constants\DosAttrs;
use PhpZip\Constants\UnixStat;

/**
 * @internal
 */
class FileAttribUtil implements DosAttrs, UnixStat
{
    /**
     * Get DOS mode.
     */
    public static function getDosMode(int $xattr): string
    {
        $mode = (($xattr & self::DOS_DIRECTORY) === self::DOS_DIRECTORY) ? 'd' : '-';
        $mode .= (($xattr & self::DOS_ARCHIVE) === self::DOS_ARCHIVE) ? 'a' : '-';
        $mode .= (($xattr & self::DOS_READ_ONLY) === self::DOS_READ_ONLY) ? 'r' : '-';
        $mode .= (($xattr & self::DOS_HIDDEN) === self::DOS_HIDDEN) ? 'h' : '-';
        $mode .= (($xattr & self::DOS_SYSTEM) === self::DOS_SYSTEM) ? 's' : '-';
        $mode .= (($xattr & self::DOS_LABEL) === self::DOS_LABEL) ? 'l' : '-';

        return $mode;
    }

    /**
     * @noinspection DuplicatedCode
     */
    public static function getUnixMode(int $permission): string
    {
        $mode = '';
        switch ($permission & self::UNX_IFMT) {
            case self::UNX_IFDIR:
                $mode .= 'd';
                break;

            case self::UNX_IFREG:
                $mode .= '-';
                break;

            case self::UNX_IFLNK:
                $mode .= 'l';
                break;

            case self::UNX_IFBLK:
                $mode .= 'b';
                break;

            case self::UNX_IFCHR:
                $mode .= 'c';
                break;

            case self::UNX_IFIFO:
                $mode .= 'p';
                break;

            case self::UNX_IFSOCK:
                $mode .= 's';
                break;

            default:
                $mode .= '?';
                break;
        }
        $mode .= ($permission & self::UNX_IRUSR) ? 'r' : '-';
        $mode .= ($permission & self::UNX_IWUSR) ? 'w' : '-';

        if ($permission & self::UNX_IXUSR) {
            $mode .= ($permission & self::UNX_ISUID) ? 's' : 'x';
        } else {
            $mode .= ($permission & self::UNX_ISUID) ? 'S' : '-';  // S==undefined
        }
        $mode .= ($permission & self::UNX_IRGRP) ? 'r' : '-';
        $mode .= ($permission & self::UNX_IWGRP) ? 'w' : '-';

        if ($permission & self::UNX_IXGRP) {
            $mode .= ($permission & self::UNX_ISGID) ? 's' : 'x';
        }  // == self::UNX_ENFMT
        else {
            $mode .= ($permission & self::UNX_ISGID) ? 'S' : '-';
        }  // SunOS 4.1.x

        $mode .= ($permission & self::UNX_IROTH) ? 'r' : '-';
        $mode .= ($permission & self::UNX_IWOTH) ? 'w' : '-';

        if ($permission & self::UNX_IXOTH) {
            $mode .= ($permission & self::UNX_ISVTX) ? 't' : 'x';
        }  // "sticky bit"
        else {
            $mode .= ($permission & self::UNX_ISVTX) ? 'T' : '-';
        }  // T==undefined

        return $mode;
    }
}
