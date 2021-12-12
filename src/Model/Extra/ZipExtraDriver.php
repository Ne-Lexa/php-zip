<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Model\Extra;

use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Model\Extra\Fields\ApkAlignmentExtraField;
use PhpZip\Model\Extra\Fields\AsiExtraField;
use PhpZip\Model\Extra\Fields\ExtendedTimestampExtraField;
use PhpZip\Model\Extra\Fields\JarMarkerExtraField;
use PhpZip\Model\Extra\Fields\NewUnixExtraField;
use PhpZip\Model\Extra\Fields\NtfsExtraField;
use PhpZip\Model\Extra\Fields\OldUnixExtraField;
use PhpZip\Model\Extra\Fields\UnicodeCommentExtraField;
use PhpZip\Model\Extra\Fields\UnicodePathExtraField;
use PhpZip\Model\Extra\Fields\WinZipAesExtraField;
use PhpZip\Model\Extra\Fields\Zip64ExtraField;

/**
 * Class ZipExtraManager.
 */
final class ZipExtraDriver
{
    /**
     * @var array<int, string>
     * @psalm-var array<int, class-string<ZipExtraField>>
     */
    private static array $implementations = [
        ApkAlignmentExtraField::HEADER_ID => ApkAlignmentExtraField::class,
        AsiExtraField::HEADER_ID => AsiExtraField::class,
        ExtendedTimestampExtraField::HEADER_ID => ExtendedTimestampExtraField::class,
        JarMarkerExtraField::HEADER_ID => JarMarkerExtraField::class,
        NewUnixExtraField::HEADER_ID => NewUnixExtraField::class,
        NtfsExtraField::HEADER_ID => NtfsExtraField::class,
        OldUnixExtraField::HEADER_ID => OldUnixExtraField::class,
        UnicodeCommentExtraField::HEADER_ID => UnicodeCommentExtraField::class,
        UnicodePathExtraField::HEADER_ID => UnicodePathExtraField::class,
        WinZipAesExtraField::HEADER_ID => WinZipAesExtraField::class,
        Zip64ExtraField::HEADER_ID => Zip64ExtraField::class,
    ];

    private function __construct()
    {
    }

    /**
     * @param string|ZipExtraField $extraField ZipExtraField object or class name
     */
    public static function register($extraField): void
    {
        if (!is_a($extraField, ZipExtraField::class, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    '$extraField "%s" is not implements interface %s',
                    (string) $extraField,
                    ZipExtraField::class
                )
            );
        }
        self::$implementations[\call_user_func([$extraField, 'getHeaderId'])] = $extraField;
    }

    /**
     * @param int|string|ZipExtraField $extraType ZipExtraField object or class name or extra header id
     */
    public static function unregister($extraType): bool
    {
        $headerId = null;

        if (\is_int($extraType)) {
            $headerId = $extraType;
        } elseif (is_a($extraType, ZipExtraField::class, true)) {
            $headerId = \call_user_func([$extraType, 'getHeaderId']);
        } else {
            return false;
        }

        if (isset(self::$implementations[$headerId])) {
            unset(self::$implementations[$headerId]);

            return true;
        }

        return false;
    }

    public static function getClassNameOrNull(int $headerId): ?string
    {
        if ($headerId < 0 || $headerId > 0xFFFF) {
            throw new \InvalidArgumentException('$headerId out of range: ' . $headerId);
        }

        return self::$implementations[$headerId] ?? null;
    }
}
