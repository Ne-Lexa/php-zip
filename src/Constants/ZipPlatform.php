<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Constants;

final class ZipPlatform
{
    /** @var int MS-DOS OS */
    public const OS_DOS = 0;

    /** @var int Unix OS */
    public const OS_UNIX = 3;

    /** @var int MacOS platform */
    public const OS_MAC_OSX = 19;

    /** @var array Zip Platforms */
    private const PLATFORMS = [
        self::OS_DOS => 'MS-DOS',
        1 => 'Amiga',
        2 => 'OpenVMS',
        self::OS_UNIX => 'Unix',
        4 => 'VM/CMS',
        5 => 'Atari ST',
        6 => 'HPFS (OS/2, NT 3.x)',
        7 => 'Macintosh',
        8 => 'Z-System',
        9 => 'CP/M',
        10 => 'Windows NTFS or TOPS-20',
        11 => 'MVS or NTFS',
        12 => 'VSE or SMS/QDOS',
        13 => 'Acorn RISC OS',
        14 => 'VFAT',
        15 => 'alternate MVS',
        16 => 'BeOS',
        17 => 'Tandem',
        18 => 'OS/400',
        self::OS_MAC_OSX => 'OS/X (Darwin)',
        30 => 'AtheOS/Syllable',
    ];

    public static function getPlatformName(int $platform): string
    {
        return self::PLATFORMS[$platform] ?? 'Unknown';
    }
}
