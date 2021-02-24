<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Constants;

use PhpZip\Exception\InvalidArgumentException;

final class ZipEncryptionMethod
{
    public const NONE = -1;

    /** @var int Traditional PKWARE encryption. */
    public const PKWARE = 0;

    /** @var int WinZip AES-256 */
    public const WINZIP_AES_256 = 1;

    /** @var int WinZip AES-128 */
    public const WINZIP_AES_128 = 2;

    /** @var int WinZip AES-192 */
    public const WINZIP_AES_192 = 3;

    /** @var array<int, string> */
    private const ENCRYPTION_METHODS = [
        self::NONE => 'no encryption',
        self::PKWARE => 'Traditional PKWARE encryption',
        self::WINZIP_AES_128 => 'WinZip AES-128',
        self::WINZIP_AES_192 => 'WinZip AES-192',
        self::WINZIP_AES_256 => 'WinZip AES-256',
    ];

    public static function getEncryptionMethodName(int $value): string
    {
        return self::ENCRYPTION_METHODS[$value] ?? 'Unknown Encryption Method';
    }

    public static function hasEncryptionMethod(int $encryptionMethod): bool
    {
        return isset(self::ENCRYPTION_METHODS[$encryptionMethod]);
    }

    public static function isWinZipAesMethod(int $encryptionMethod): bool
    {
        return \in_array(
            $encryptionMethod,
            [
                self::WINZIP_AES_256,
                self::WINZIP_AES_192,
                self::WINZIP_AES_128,
            ],
            true
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function checkSupport(int $encryptionMethod): void
    {
        if (!self::hasEncryptionMethod($encryptionMethod)) {
            throw new InvalidArgumentException(sprintf(
                'Encryption method %d is not supported.',
                $encryptionMethod
            ));
        }
    }
}
