<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Tests\Internal\CustomZip;

use PhpZip\IO\ZipWriter;
use PhpZip\ZipFile;

class ZipFileCustomWriter extends ZipFile
{
    protected function createZipWriter(): ZipWriter
    {
        return new CustomZipWriter($this->zipContainer);
    }
}
