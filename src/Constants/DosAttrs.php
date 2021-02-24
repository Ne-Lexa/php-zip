<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Constants;

interface DosAttrs
{
    /** @var int DOS File Attribute Read Only */
    public const DOS_READ_ONLY = 0x01;

    /** @var int DOS File Attribute Hidden */
    public const DOS_HIDDEN = 0x02;

    /** @var int DOS File Attribute System */
    public const DOS_SYSTEM = 0x04;

    /** @var int DOS File Attribute Label */
    public const DOS_LABEL = 0x08;

    /** @var int DOS File Attribute Directory */
    public const DOS_DIRECTORY = 0x10;

    /** @var int DOS File Attribute Archive */
    public const DOS_ARCHIVE = 0x20;

    /** @var int DOS File Attribute Link */
    public const DOS_LINK = 0x40;

    /** @var int DOS File Attribute Execute */
    public const DOS_EXE = 0x80;
}
