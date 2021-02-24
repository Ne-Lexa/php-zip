<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Model;

use PhpZip\Exception\ZipException;

interface ZipData
{
    /**
     * @return string returns data as string
     */
    public function getDataAsString(): string;

    /**
     * @return resource returns stream data
     */
    public function getDataAsStream();

    /**
     * @param resource $outStream
     *
     * @throws ZipException
     */
    public function copyDataToStream($outStream);
}
