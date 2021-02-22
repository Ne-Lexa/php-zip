<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Constants;

/**
 * Unix stat constants.
 */
interface UnixStat
{
    /** @var int unix file type mask */
    public const UNX_IFMT = 0170000;

    /** @var int unix regular file */
    public const UNX_IFREG = 0100000;

    /** @var int unix socket (BSD, not SysV or Amiga) */
    public const UNX_IFSOCK = 0140000;

    /** @var int unix symbolic link (not SysV, Amiga) */
    public const UNX_IFLNK = 0120000;

    /** @var int unix block special       (not Amiga) */
    public const UNX_IFBLK = 0060000;

    /** @var int unix directory */
    public const UNX_IFDIR = 0040000;

    /** @var int unix character special   (not Amiga) */
    public const UNX_IFCHR = 0020000;

    /** @var int unix fifo    (BCC, not MSC or Amiga) */
    public const UNX_IFIFO = 0010000;

    /** @var int unix set user id on execution */
    public const UNX_ISUID = 04000;

    /** @var int unix set group id on execution */
    public const UNX_ISGID = 02000;

    /** @var int unix directory permissions control */
    public const UNX_ISVTX = 01000;

    /** @var int unix record locking enforcement flag */
    public const UNX_ENFMT = 02000;

    /** @var int unix read, write, execute: owner */
    public const UNX_IRWXU = 00700;

    /** @var int unix read permission: owner */
    public const UNX_IRUSR = 00400;

    /** @var int unix write permission: owner */
    public const UNX_IWUSR = 00200;

    /** @var int unix execute permission: owner */
    public const UNX_IXUSR = 00100;

    /** @var int unix read, write, execute: group */
    public const UNX_IRWXG = 00070;

    /** @var int unix read permission: group */
    public const UNX_IRGRP = 00040;

    /** @var int unix write permission: group */
    public const UNX_IWGRP = 00020;

    /** @var int unix execute permission: group */
    public const UNX_IXGRP = 00010;

    /** @var int unix read, write, execute: other */
    public const UNX_IRWXO = 00007;

    /** @var int unix read permission: other */
    public const UNX_IROTH = 00004;

    /** @var int unix write permission: other */
    public const UNX_IWOTH = 00002;

    /** @var int unix execute permission: other */
    public const UNX_IXOTH = 00001;
}
