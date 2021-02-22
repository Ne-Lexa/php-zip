<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Model\Data;

use PhpZip\Exception\Crc32Exception;
use PhpZip\Exception\ZipException;
use PhpZip\IO\ZipReader;
use PhpZip\Model\ZipData;
use PhpZip\Model\ZipEntry;

class ZipSourceFileData implements ZipData
{
    private ZipReader $zipReader;

    /** @var resource|null */
    private $stream;

    private ZipEntry $sourceEntry;

    private int $offset;

    private int $uncompressedSize;

    private int $compressedSize;

    public function __construct(ZipReader $zipReader, ZipEntry $zipEntry, int $offsetData)
    {
        $this->zipReader = $zipReader;
        $this->offset = $offsetData;
        $this->sourceEntry = $zipEntry;
        $this->compressedSize = $zipEntry->getCompressedSize();
        $this->uncompressedSize = $zipEntry->getUncompressedSize();
    }

    public function hasRecompressData(ZipEntry $entry): bool
    {
        return $this->sourceEntry->getCompressionLevel() !== $entry->getCompressionLevel()
            || $this->sourceEntry->getCompressionMethod() !== $entry->getCompressionMethod()
            || $this->sourceEntry->isEncrypted() !== $entry->isEncrypted()
            || $this->sourceEntry->getEncryptionMethod() !== $entry->getEncryptionMethod()
            || $this->sourceEntry->getPassword() !== $entry->getPassword()
            || $this->sourceEntry->getCompressedSize() !== $entry->getCompressedSize()
            || $this->sourceEntry->getUncompressedSize() !== $entry->getUncompressedSize()
            || $this->sourceEntry->getCrc() !== $entry->getCrc();
    }

    /**
     * @throws ZipException
     *
     * @return resource returns stream data
     */
    public function getDataAsStream()
    {
        if (!\is_resource($this->stream)) {
            $this->stream = $this->zipReader->getEntryStream($this);
        }

        return $this->stream;
    }

    /**
     * @throws ZipException
     *
     * @return string returns data as string
     */
    public function getDataAsString(): string
    {
        $autoClosable = $this->stream === null;

        $stream = $this->getDataAsStream();
        $pos = ftell($stream);

        try {
            rewind($stream);

            return stream_get_contents($stream);
        } finally {
            if ($autoClosable) {
                fclose($stream);
                $this->stream = null;
            } else {
                fseek($stream, $pos);
            }
        }
    }

    /**
     * @param resource $outStream Output stream
     *
     * @throws ZipException
     * @throws Crc32Exception
     */
    public function copyDataToStream($outStream): void
    {
        if (\is_resource($this->stream)) {
            rewind($this->stream);
            stream_copy_to_stream($this->stream, $outStream);
        } else {
            $this->zipReader->copyUncompressedDataToStream($this, $outStream);
        }
    }

    /**
     * @param resource $outputStream Output stream
     */
    public function copyCompressedDataToStream($outputStream): void
    {
        $this->zipReader->copyCompressedDataToStream($this, $outputStream);
    }

    public function getSourceEntry(): ZipEntry
    {
        return $this->sourceEntry;
    }

    public function getCompressedSize(): int
    {
        return $this->compressedSize;
    }

    public function getUncompressedSize(): int
    {
        return $this->uncompressedSize;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function __destruct()
    {
        if (\is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}
