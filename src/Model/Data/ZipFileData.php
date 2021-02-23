<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Model\Data;

use PhpZip\Exception\ZipException;
use PhpZip\Model\ZipData;
use PhpZip\Model\ZipEntry;

class ZipFileData implements ZipData
{
    private \SplFileInfo $file;

    /**
     * @throws ZipException
     */
    public function __construct(ZipEntry $zipEntry, \SplFileInfo $fileInfo)
    {
        if (!$fileInfo->isFile()) {
            throw new ZipException('$fileInfo is not a file.');
        }

        if (!$fileInfo->isReadable()) {
            throw new ZipException('$fileInfo is not readable.');
        }

        $this->file = $fileInfo;
        $zipEntry->setUncompressedSize($fileInfo->getSize());
    }

    /**
     * @throws ZipException
     *
     * @return resource returns stream data
     */
    public function getDataAsStream()
    {
        if (!$this->file->isReadable()) {
            throw new ZipException(sprintf('The %s file is no longer readable.', $this->file->getPathname()));
        }

        return fopen($this->file->getPathname(), 'rb');
    }

    /**
     * @throws ZipException
     *
     * @return string returns data as string
     */
    public function getDataAsString(): string
    {
        if (!$this->file->isReadable()) {
            throw new ZipException(sprintf('The %s file is no longer readable.', $this->file->getPathname()));
        }

        return file_get_contents($this->file->getPathname());
    }

    /**
     * @param resource $outStream
     *
     * @throws ZipException
     */
    public function copyDataToStream($outStream): void
    {
        $stream = $this->getDataAsStream();
        stream_copy_to_stream($stream, $outStream);
        fclose($stream);
    }
}
