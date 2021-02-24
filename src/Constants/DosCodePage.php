<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Constants;

final class DosCodePage
{
    public const CP_LATIN_US = 'cp437';

    public const CP_GREEK = 'cp737';

    public const CP_BALT_RIM = 'cp775';

    public const CP_LATIN1 = 'cp850';

    public const CP_LATIN2 = 'cp852';

    public const CP_CYRILLIC = 'cp855';

    public const CP_TURKISH = 'cp857';

    public const CP_PORTUGUESE = 'cp860';

    public const CP_ICELANDIC = 'cp861';

    public const CP_HEBREW = 'cp862';

    public const CP_CANADA = 'cp863';

    public const CP_ARABIC = 'cp864';

    public const CP_NORDIC = 'cp865';

    public const CP_CYRILLIC_RUSSIAN = 'cp866';

    public const CP_GREEK2 = 'cp869';

    public const CP_THAI = 'cp874';

    /** @var string[] */
    private const CP_CHARSETS = [
        self::CP_LATIN_US,
        self::CP_GREEK,
        self::CP_BALT_RIM,
        self::CP_LATIN1,
        self::CP_LATIN2,
        self::CP_CYRILLIC,
        self::CP_TURKISH,
        self::CP_PORTUGUESE,
        self::CP_ICELANDIC,
        self::CP_HEBREW,
        self::CP_CANADA,
        self::CP_ARABIC,
        self::CP_NORDIC,
        self::CP_CYRILLIC_RUSSIAN,
        self::CP_GREEK2,
        self::CP_THAI,
    ];

    /**
     * @noinspection PhpComposerExtensionStubsInspection
     */
    public static function toUTF8(string $str, string $sourceEncoding): string
    {
        $s = iconv($sourceEncoding, 'UTF-8', $str);

        if ($s === false) {
            return $str;
        }

        return $s;
    }

    /**
     * @noinspection PhpComposerExtensionStubsInspection
     */
    public static function fromUTF8(string $str, string $destEncoding): string
    {
        $s = iconv('UTF-8', $destEncoding, $str);

        if ($s === false) {
            return $str;
        }

        return $s;
    }

    /**
     * @return string[]
     */
    public static function getCodePages(): array
    {
        return self::CP_CHARSETS;
    }
}
