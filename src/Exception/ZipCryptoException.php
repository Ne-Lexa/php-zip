<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Exception;

/**
 * Thrown if there is an issue when reading or writing an encrypted ZIP file
 * or entry.
 */
class ZipCryptoException extends ZipException
{
}
