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
 * Thrown to indicate that an authenticated ZIP entry has been tampered with.
 */
class ZipAuthenticationException extends ZipCryptoException
{
}
