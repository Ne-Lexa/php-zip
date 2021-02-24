<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Constants;

use PhpZip\Exception\ZipUnsupportMethodException;

final class ZipCompressionMethod
{
    /** @var int Compression method Store */
    public const STORED = 0;

    /** @var int Compression method Deflate */
    public const DEFLATED = 8;

    /** @var int Compression method Bzip2 */
    public const BZIP2 = 12;

    /** @var int Compression method AES-Encryption */
    public const WINZIP_AES = 99;

    /** @var array Compression Methods */
    private const ZIP_COMPRESSION_METHODS = [
        self::STORED => 'Stored',
        1 => 'Shrunk',
        2 => 'Reduced compression factor 1',
        3 => 'Reduced compression factor 2',
        4 => 'Reduced compression factor 3',
        5 => 'Reduced compression factor 4',
        6 => 'Imploded',
        7 => 'Reserved for Tokenizing compression algorithm',
        self::DEFLATED => 'Deflated',
        9 => 'Enhanced Deflating using Deflate64(tm)',
        10 => 'PKWARE Data Compression Library Imploding',
        11 => 'Reserved by PKWARE',
        self::BZIP2 => 'BZIP2',
        13 => 'Reserved by PKWARE',
        14 => 'LZMA',
        15 => 'Reserved by PKWARE',
        16 => 'Reserved by PKWARE',
        17 => 'Reserved by PKWARE',
        18 => 'File is compressed using IBM TERSE (new)',
        19 => 'IBM LZ77 z Architecture (PFS)',
        96 => 'WinZip JPEG Compression',
        97 => 'WavPack compressed data',
        98 => 'PPMd version I, Rev 1',
        self::WINZIP_AES => 'AES Encryption',
    ];

    public static function getCompressionMethodName(int $value): string
    {
        return self::ZIP_COMPRESSION_METHODS[$value] ?? 'Unknown Method';
    }

    /**
     * @return int[]
     */
    public static function getSupportMethods(): array
    {
        static $methods;

        if ($methods === null) {
            $methods = [
                self::STORED,
                self::DEFLATED,
            ];

            if (\extension_loaded('bz2')) {
                $methods[] = self::BZIP2;
            }
        }

        return $methods;
    }

    /**
     * @throws ZipUnsupportMethodException
     */
    public static function checkSupport(int $compressionMethod): void
    {
        if (!\in_array($compressionMethod, self::getSupportMethods(), true)) {
            throw new ZipUnsupportMethodException(sprintf(
                'Compression method %d (%s) is not supported.',
                $compressionMethod,
                self::getCompressionMethodName($compressionMethod)
            ));
        }
    }
}
