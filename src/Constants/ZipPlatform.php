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

    /** @var int Amica OS */
    public const OS_AMIGA = 1;

    /** @var int OpenVMS */
    public const OS_OPENVMS = 2;

    /** @var int Unix OS */
    public const OS_UNIX = 3;

    /** @var int VM/CMS */
    public const OS_VM_CMS = 4;

    /** @var int AtariST */
    public const OS_ATARI_ST = 5;

    /** @var int OS/2 (HPFS) */
    public const OS_OS_2 = 6;

    /** @var int Macintosh */
    public const OS_MACINTOSH = 7;

    /** @var int Z-System */
    public const OS_Z_SYSTEM = 8;

    /** @var int CPM */
    public const OS_CPM = 9;

    /** @var int Windows NTFS / TOPS-20 */
    public const OS_WINDOWS_NTFS = 10;

    /** @var int MVS */
    public const OS_MVS = 11;

    /** @var int VSE */
    public const OS_VSE = 12;
    public const OS_QDOS = 12;

    /** @var int Acorn RISC OS */
    public const OS_ACORN_RISC = 13;

    /** @var int VFAT */
    public const OS_VFAT = 14;

    /** @var int alternate MVS */
    public const OS_ALTERNATE_MVS = 15;

    /** @var int BeOS */
    public const OS_BEOS = 16;

    /** @var int Tandem */
    public const OS_TANDEM = 17;

    /** @var int OS/400 or THEOS */
    public const OS_OS_400 = 18;

    /** @var int macOS */
    public const OS_MAC_OSX = 19;

    /** @var int AtheOS */
    public const OS_ATHEOS = 30;

    /** @var array Zip Platforms */
    private const PLATFORMS = [
        self::OS_DOS => 'MS-DOS',
        self::OS_AMIGA => 'Amiga',
        self::OS_OPENVMS => 'OpenVMS',
        self::OS_UNIX => 'Unix',
        self::OS_VM_CMS => 'VM/CMS',
        self::OS_ATARI_ST => 'Atari ST',
        self::OS_OS_2 => 'HPFS (OS/2, NT 3.x)',
        self::OS_MACINTOSH => 'Macintosh',
        self::OS_Z_SYSTEM => 'Z-System',
        self::OS_CPM => 'CP/M',
        self::OS_WINDOWS_NTFS => 'Windows NTFS or TOPS-20',
        self::OS_MVS => 'MVS or NTFS',
        self::OS_VSE => 'VSE or SMS/QDOS',
        self::OS_ACORN_RISC => 'Acorn RISC OS',
        self::OS_VFAT => 'VFAT',
        self::OS_ALTERNATE_MVS => 'alternate MVS',
        self::OS_BEOS => 'BeOS',
        self::OS_TANDEM => 'Tandem',
        self::OS_OS_400 => 'OS/400',
        self::OS_MAC_OSX => 'OS/X (Darwin)',
        self::OS_ATHEOS => 'AtheOS/Syllable',
    ];

    public static function getPlatformName(int $platform): string
    {
        return self::PLATFORMS[$platform] ?? 'Unknown';
    }
}
