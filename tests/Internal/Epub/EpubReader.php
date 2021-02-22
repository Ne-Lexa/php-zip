<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Tests\Internal\Epub;

use PhpZip\IO\ZipReader;

/**
 * Class EpubReader.
 */
class EpubReader extends ZipReader
{
    /**
     * @see https://github.com/w3c/epubcheck/issues/334
     */
    protected function isZip64Support(): bool
    {
        return false;
    }
}
