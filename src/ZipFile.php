<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip;

use PhpZip\Constants\UnixStat;
use PhpZip\Constants\ZipCompressionLevel;
use PhpZip\Constants\ZipCompressionMethod;
use PhpZip\Constants\ZipEncryptionMethod;
use PhpZip\Constants\ZipOptions;
use PhpZip\Constants\ZipPlatform;
use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipEntryNotFoundException;
use PhpZip\Exception\ZipException;
use PhpZip\IO\Stream\ResponseStream;
use PhpZip\IO\Stream\ZipEntryStreamWrapper;
use PhpZip\IO\ZipReader;
use PhpZip\IO\ZipWriter;
use PhpZip\Model\Data\ZipFileData;
use PhpZip\Model\Data\ZipNewData;
use PhpZip\Model\ImmutableZipContainer;
use PhpZip\Model\ZipContainer;
use PhpZip\Model\ZipEntry;
use PhpZip\Model\ZipEntryMatcher;
use PhpZip\Util\FilesUtil;
use PhpZip\Util\StringUtil;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo as SymfonySplFileInfo;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Create, open .ZIP files, modify, get info and extract files.
 *
 * Implemented support traditional PKWARE encryption and WinZip AES encryption.
 * Implemented support ZIP64.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 */
class ZipFile implements \Countable, \ArrayAccess, \Iterator
{
    /** @var array default mime types */
    private const DEFAULT_MIME_TYPES = [
        'zip' => 'application/zip',
        'apk' => 'application/vnd.android.package-archive',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'epub' => 'application/epub+zip',
        'jar' => 'application/java-archive',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'pptx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xpi' => 'application/x-xpinstall',
    ];

    protected ZipContainer $zipContainer;

    private ?ZipReader $reader = null;

    public function __construct()
    {
        $this->zipContainer = $this->createZipContainer();
    }

    /**
     * @param resource $inputStream
     */
    protected function createZipReader($inputStream, array $options = []): ZipReader
    {
        return new ZipReader($inputStream, $options);
    }

    protected function createZipWriter(): ZipWriter
    {
        return new ZipWriter($this->zipContainer);
    }

    protected function createZipContainer(?ImmutableZipContainer $sourceContainer = null): ZipContainer
    {
        return new ZipContainer($sourceContainer);
    }

    /**
     * Open zip archive from file.
     *
     * @throws ZipException if can't open file
     *
     * @return ZipFile
     */
    public function openFile(string $filename, array $options = []): self
    {
        if (!file_exists($filename)) {
            throw new ZipException("File {$filename} does not exist.");
        }

        /** @psalm-suppress InvalidArgument */
        set_error_handler(
            static function (int $errorNumber, string $errorString): ?bool {
                throw new InvalidArgumentException($errorString, $errorNumber);
            }
        );
        $handle = fopen($filename, 'rb');
        restore_error_handler();

        return $this->openFromStream($handle, $options);
    }

    /**
     * Open zip archive from raw string data.
     *
     * @throws ZipException if can't open temp stream
     *
     * @return ZipFile
     */
    public function openFromString(string $data, array $options = []): self
    {
        if ($data === '') {
            throw new InvalidArgumentException('Empty string passed');
        }

        if (!($handle = fopen('php://temp', 'r+b'))) {
            // @codeCoverageIgnoreStart
            throw new ZipException('A temporary resource cannot be opened for writing.');
            // @codeCoverageIgnoreEnd
        }
        fwrite($handle, $data);
        rewind($handle);

        return $this->openFromStream($handle, $options);
    }

    /**
     * Open zip archive from stream resource.
     *
     * @param resource $handle
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function openFromStream($handle, array $options = []): self
    {
        $this->reader = $this->createZipReader($handle, $options);
        $this->zipContainer = $this->createZipContainer($this->reader->read());

        return $this;
    }

    /**
     * @return string[] returns the list files
     */
    public function getListFiles(): array
    {
        // strval is needed to cast entry names to string type
        return array_map('strval', array_keys($this->zipContainer->getEntries()));
    }

    /**
     * @return int returns the number of entries in this ZIP file
     */
    public function count(): int
    {
        return $this->zipContainer->count();
    }

    /**
     * Returns the file comment.
     *
     * @return string|null the file comment
     */
    public function getArchiveComment(): ?string
    {
        return $this->zipContainer->getArchiveComment();
    }

    /**
     * Set archive comment.
     *
     * @param ?string $comment
     *
     * @return ZipFile
     */
    public function setArchiveComment(?string $comment = null): self
    {
        $this->zipContainer->setArchiveComment($comment);

        return $this;
    }

    /**
     * Checks if there is an entry in the archive.
     */
    public function hasEntry(string $entryName): bool
    {
        return $this->zipContainer->hasEntry($entryName);
    }

    /**
     * Returns ZipEntry object.
     *
     * @throws ZipEntryNotFoundException
     */
    public function getEntry(string $entryName): ZipEntry
    {
        return $this->zipContainer->getEntry($entryName);
    }

    /**
     * Checks that the entry in the archive is a directory.
     * Returns true if and only if this ZIP entry represents a directory entry
     * (i.e. end with '/').
     *
     * @throws ZipEntryNotFoundException
     */
    public function isDirectory(string $entryName): bool
    {
        return $this->getEntry($entryName)->isDirectory();
    }

    /**
     * Returns entry comment.
     *
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function getEntryComment(string $entryName): string
    {
        return $this->getEntry($entryName)->getComment();
    }

    /**
     * Set entry comment.
     *
     * @param ?string $comment
     *
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function setEntryComment(string $entryName, ?string $comment = null): self
    {
        $this->getEntry($entryName)->setComment($comment);

        return $this;
    }

    /**
     * Returns the entry contents.
     *
     * @throws ZipException
     * @throws ZipEntryNotFoundException
     */
    public function getEntryContents(string $entryName): string
    {
        $zipData = $this->zipContainer->getEntry($entryName)->getData();

        if ($zipData === null) {
            throw new ZipException(sprintf('No data for zip entry %s', $entryName));
        }

        return $zipData->getDataAsString();
    }

    /**
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     *
     * @return resource
     */
    public function getEntryStream(string $entryName)
    {
        $resource = ZipEntryStreamWrapper::wrap($this->zipContainer->getEntry($entryName));
        rewind($resource);

        return $resource;
    }

    public function matcher(): ZipEntryMatcher
    {
        return $this->zipContainer->matcher();
    }

    /**
     * Returns an array of zip records (ex. for modify time).
     *
     * @return ZipEntry[] array of raw zip entries
     */
    public function getEntries(): array
    {
        return $this->zipContainer->getEntries();
    }

    /**
     * Extract the archive contents (unzip).
     *
     * Extract the complete archive or the given files to the specified destination.
     *
     * @param string     $destDir          location where to extract the files
     * @param mixed      $entries          entries to extract (array, string or null)
     * @param array      $options          extract options
     * @param array|null $extractedEntries if the extractedEntries argument is present,
     *                                     then the specified array will be filled with
     *                                     information about the extracted entries
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function extractTo(
        string $destDir,
        $entries = null,
        array $options = [],
        ?array &$extractedEntries = []
    ): self {
        if (!file_exists($destDir)) {
            throw new ZipException(sprintf('Destination %s not found', $destDir));
        }

        if (!is_dir($destDir)) {
            throw new ZipException('Destination is not directory');
        }

        if (!is_writable($destDir)) {
            throw new ZipException('Destination is not writable directory');
        }

        if ($extractedEntries === null) {
            $extractedEntries = [];
        }

        $defaultOptions = [
            ZipOptions::EXTRACT_SYMLINKS => false,
        ];
        /** @noinspection AdditionOperationOnArraysInspection */
        $options += $defaultOptions;

        $zipEntries = $this->zipContainer->getEntries();

        if (!empty($entries)) {
            if (\is_string($entries)) {
                $entries = (array) $entries;
            }

            if (\is_array($entries)) {
                $entries = array_unique($entries);
                $zipEntries = array_intersect_key($zipEntries, array_flip($entries));
            }
        }

        if (empty($zipEntries)) {
            return $this;
        }

        /** @var int[] $lastModDirs */
        $lastModDirs = [];

        krsort($zipEntries, \SORT_NATURAL);

        $symlinks = [];
        $destDir = rtrim($destDir, '/\\');

        foreach ($zipEntries as $entryName => $entry) {
            $unixMode = $entry->getUnixMode();
            $entryName = FilesUtil::normalizeZipPath($entryName);
            $file = $destDir . \DIRECTORY_SEPARATOR . $entryName;

            $extractedEntries[$file] = $entry;
            $modifyTimestamp = $entry->getMTime()->getTimestamp();
            $atime = $entry->getATime();
            $accessTimestamp = $atime === null ? null : $atime->getTimestamp();

            $dir = $entry->isDirectory() ? $file : \dirname($file);

            if (!is_dir($dir)) {
                $dirMode = $entry->isDirectory() ? $unixMode : 0755;

                if ($dirMode === 0) {
                    $dirMode = 0755;
                }

                if (!mkdir($dir, $dirMode, true) && !is_dir($dir)) {
                    // @codeCoverageIgnoreStart
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
                    // @codeCoverageIgnoreEnd
                }
                chmod($dir, $dirMode);
            }

            $parts = explode('/', rtrim($entryName, '/'));
            $path = $destDir . \DIRECTORY_SEPARATOR;

            foreach ($parts as $part) {
                if (!isset($lastModDirs[$path]) || $lastModDirs[$path] > $modifyTimestamp) {
                    $lastModDirs[$path] = $modifyTimestamp;
                }

                $path .= $part . \DIRECTORY_SEPARATOR;
            }

            if ($entry->isDirectory()) {
                $lastModDirs[$dir] = $modifyTimestamp;

                continue;
            }

            $zipData = $entry->getData();

            if ($zipData === null) {
                continue;
            }

            if ($entry->isUnixSymlink()) {
                $symlinks[$file] = $zipData->getDataAsString();

                continue;
            }

            /** @psalm-suppress InvalidArgument */
            set_error_handler(
                static function (int $errorNumber, string $errorString) use ($entry, $file): ?bool {
                    throw new ZipException(
                        sprintf(
                            'Cannot extract zip entry %s. File %s cannot open for write. %s',
                            $entry->getName(),
                            $file,
                            $errorString
                        ),
                        $errorNumber
                    );
                }
            );
            $handle = fopen($file, 'w+b');
            restore_error_handler();

            try {
                $zipData->copyDataToStream($handle);
            } catch (ZipException $e) {
                unlink($file);

                throw $e;
            }
            fclose($handle);

            if ($unixMode === 0) {
                $unixMode = 0644;
            }
            chmod($file, $unixMode);

            if ($accessTimestamp !== null) {
                /** @noinspection PotentialMalwareInspection */
                touch($file, $modifyTimestamp, $accessTimestamp);
            } else {
                touch($file, $modifyTimestamp);
            }
        }

        $allowSymlink = (bool) $options[ZipOptions::EXTRACT_SYMLINKS];

        foreach ($symlinks as $linkPath => $target) {
            if (!FilesUtil::symlink($target, $linkPath, $allowSymlink)) {
                unset($extractedEntries[$linkPath]);
            }
        }

        krsort($lastModDirs, \SORT_NATURAL);

        foreach ($lastModDirs as $dir => $lastMod) {
            touch($dir, $lastMod);
        }

        ksort($extractedEntries);

        return $this;
    }

    /**
     * Add entry from the string.
     *
     * @param string   $entryName         zip entry name
     * @param string   $contents          string contents
     * @param int|null $compressionMethod Compression method.
     *                                    Use {@see ZipCompressionMethod::STORED},
     *                                    {@see ZipCompressionMethod::DEFLATED} or
     *                                    {@see ZipCompressionMethod::BZIP2}.
     *                                    If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function addFromString(string $entryName, string $contents, ?int $compressionMethod = null): self
    {
        $entryName = $this->normalizeEntryName($entryName);

        $length = \strlen($contents);

        if ($compressionMethod === null || $compressionMethod === ZipEntry::UNKNOWN) {
            if ($length < 512) {
                $compressionMethod = ZipCompressionMethod::STORED;
            } else {
                $mimeType = FilesUtil::getMimeTypeFromString($contents);
                $compressionMethod = FilesUtil::isBadCompressionMimeType($mimeType)
                    ? ZipCompressionMethod::STORED
                    : ZipCompressionMethod::DEFLATED;
            }
        }

        $zipEntry = new ZipEntry($entryName);
        $zipEntry->setData(new ZipNewData($zipEntry, $contents));
        $zipEntry->setUncompressedSize($length);
        $zipEntry->setCompressionMethod($compressionMethod);
        $zipEntry->setCreatedOS(ZipPlatform::OS_UNIX);
        $zipEntry->setExtractedOS(ZipPlatform::OS_UNIX);
        $zipEntry->setUnixMode(0100644);
        $zipEntry->setTime(time());

        $this->addZipEntry($zipEntry);

        return $this;
    }

    protected function normalizeEntryName(string $entryName): string
    {
        $entryName = ltrim($entryName, '\\/');

        if (\DIRECTORY_SEPARATOR === '\\') {
            $entryName = str_replace('\\', '/', $entryName);
        }

        if ($entryName === '') {
            throw new InvalidArgumentException('Empty entry name');
        }

        return $entryName;
    }

    /**
     * @throws ZipException
     *
     * @return ZipEntry[]
     */
    public function addFromFinder(Finder $finder, array $options = []): array
    {
        $defaultOptions = [
            ZipOptions::STORE_ONLY_FILES => false,
            ZipOptions::COMPRESSION_METHOD => null,
            ZipOptions::MODIFIED_TIME => null,
        ];
        /** @noinspection AdditionOperationOnArraysInspection */
        $options += $defaultOptions;

        if ($options[ZipOptions::STORE_ONLY_FILES]) {
            $finder->files();
        }

        $entries = [];

        foreach ($finder as $fileInfo) {
            if ($fileInfo->isReadable()) {
                $entry = $this->addSplFile($fileInfo, null, $options);
                $entries[$entry->getName()] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param ?string $entryName
     *
     * @throws ZipException
     */
    public function addSplFile(\SplFileInfo $file, ?string $entryName = null, array $options = []): ZipEntry
    {
        if ($file instanceof \DirectoryIterator) {
            throw new InvalidArgumentException('File should not be \DirectoryIterator.');
        }
        $defaultOptions = [
            ZipOptions::COMPRESSION_METHOD => null,
            ZipOptions::MODIFIED_TIME => null,
        ];
        /** @noinspection AdditionOperationOnArraysInspection */
        $options += $defaultOptions;

        if (!$file->isReadable()) {
            throw new InvalidArgumentException(sprintf('File %s is not readable', $file->getPathname()));
        }

        if ($entryName === null) {
            if ($file instanceof SymfonySplFileInfo) {
                $entryName = $file->getRelativePathname();
            } else {
                $entryName = $file->getBasename();
            }
        }

        $entryName = $this->normalizeEntryName($entryName);
        $entryName = $file->isDir() ? rtrim($entryName, '/\\') . '/' : $entryName;

        $zipEntry = new ZipEntry($entryName);
        $zipEntry->setCreatedOS(ZipPlatform::OS_UNIX);
        $zipEntry->setExtractedOS(ZipPlatform::OS_UNIX);

        $zipData = null;
        $filePerms = $file->getPerms();

        if ($file->isLink()) {
            $linkTarget = $file->getLinkTarget();
            $lengthLinkTarget = \strlen($linkTarget);

            $zipEntry->setCompressionMethod(ZipCompressionMethod::STORED);
            $zipEntry->setUncompressedSize($lengthLinkTarget);
            $zipEntry->setCompressedSize($lengthLinkTarget);
            $zipEntry->setCrc(crc32($linkTarget));
            $filePerms |= UnixStat::UNX_IFLNK;

            $zipData = new ZipNewData($zipEntry, $linkTarget);
        } elseif ($file->isFile()) {
            if (isset($options[ZipOptions::COMPRESSION_METHOD])) {
                $compressionMethod = $options[ZipOptions::COMPRESSION_METHOD];
            } elseif ($file->getSize() < 512) {
                $compressionMethod = ZipCompressionMethod::STORED;
            } else {
                $compressionMethod = FilesUtil::isBadCompressionFile($file->getPathname())
                    ? ZipCompressionMethod::STORED
                    : ZipCompressionMethod::DEFLATED;
            }

            $zipEntry->setCompressionMethod($compressionMethod);

            $zipData = new ZipFileData($zipEntry, $file);
        } elseif ($file->isDir()) {
            $zipEntry->setCompressionMethod(ZipCompressionMethod::STORED);
            $zipEntry->setUncompressedSize(0);
            $zipEntry->setCompressedSize(0);
            $zipEntry->setCrc(0);
        }

        $zipEntry->setUnixMode($filePerms);

        $timestamp = null;

        if (isset($options[ZipOptions::MODIFIED_TIME])) {
            $mtime = $options[ZipOptions::MODIFIED_TIME];

            if ($mtime instanceof \DateTimeInterface) {
                $timestamp = $mtime->getTimestamp();
            } elseif (is_numeric($mtime)) {
                $timestamp = (int) $mtime;
            } elseif (\is_string($mtime)) {
                $timestamp = strtotime($mtime);

                if ($timestamp === false) {
                    $timestamp = null;
                }
            }
        }

        if ($timestamp === null) {
            $timestamp = $file->getMTime();
        }

        $zipEntry->setTime($timestamp);
        $zipEntry->setData($zipData);

        $this->addZipEntry($zipEntry);

        return $zipEntry;
    }

    protected function addZipEntry(ZipEntry $zipEntry): void
    {
        $this->zipContainer->addEntry($zipEntry);
    }

    /**
     * Add entry from the file.
     *
     * @param string      $filename          destination file
     * @param string|null $entryName         zip Entry name
     * @param int|null    $compressionMethod Compression method.
     *                                       Use {@see ZipCompressionMethod::STORED},
     *                                       {@see ZipCompressionMethod::DEFLATED} or
     *                                       {@see ZipCompressionMethod::BZIP2}.
     *                                       If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function addFile(string $filename, ?string $entryName = null, ?int $compressionMethod = null): self
    {
        $this->addSplFile(
            new \SplFileInfo($filename),
            $entryName,
            [
                ZipOptions::COMPRESSION_METHOD => $compressionMethod,
            ]
        );

        return $this;
    }

    /**
     * Add entry from the stream.
     *
     * @param resource $stream            stream resource
     * @param string   $entryName         zip Entry name
     * @param int|null $compressionMethod Compression method.
     *                                    Use {@see ZipCompressionMethod::STORED},
     *                                    {@see ZipCompressionMethod::DEFLATED} or
     *                                    {@see ZipCompressionMethod::BZIP2}.
     *                                    If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function addFromStream($stream, string $entryName, ?int $compressionMethod = null): self
    {
        if (!\is_resource($stream)) {
            throw new InvalidArgumentException('Stream is not resource');
        }

        $entryName = $this->normalizeEntryName($entryName);
        $zipEntry = new ZipEntry($entryName);
        $fstat = fstat($stream);

        if ($fstat !== false) {
            $unixMode = $fstat['mode'];
            $length = $fstat['size'];

            if ($compressionMethod === null || $compressionMethod === ZipEntry::UNKNOWN) {
                if ($length < 512) {
                    $compressionMethod = ZipCompressionMethod::STORED;
                } else {
                    rewind($stream);
                    $bufferContents = stream_get_contents($stream, min(1024, $length));
                    rewind($stream);
                    $mimeType = FilesUtil::getMimeTypeFromString($bufferContents);
                    $compressionMethod = FilesUtil::isBadCompressionMimeType($mimeType)
                        ? ZipCompressionMethod::STORED
                        : ZipCompressionMethod::DEFLATED;
                }
                $zipEntry->setUncompressedSize($length);
            }
        } else {
            $unixMode = 0100644;

            if ($compressionMethod === null || $compressionMethod === ZipEntry::UNKNOWN) {
                $compressionMethod = ZipCompressionMethod::DEFLATED;
            }
        }

        $zipEntry->setCreatedOS(ZipPlatform::OS_UNIX);
        $zipEntry->setExtractedOS(ZipPlatform::OS_UNIX);
        $zipEntry->setUnixMode($unixMode);
        $zipEntry->setCompressionMethod($compressionMethod);
        $zipEntry->setTime(time());
        $zipEntry->setData(new ZipNewData($zipEntry, $stream));

        $this->addZipEntry($zipEntry);

        return $this;
    }

    /**
     * Add an empty directory in the zip archive.
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function addEmptyDir(string $dirName): self
    {
        $dirName = $this->normalizeEntryName($dirName);
        $dirName = rtrim($dirName, '\\/') . '/';

        $zipEntry = new ZipEntry($dirName);
        $zipEntry->setCompressionMethod(ZipCompressionMethod::STORED);
        $zipEntry->setUncompressedSize(0);
        $zipEntry->setCompressedSize(0);
        $zipEntry->setCrc(0);
        $zipEntry->setCreatedOS(ZipPlatform::OS_UNIX);
        $zipEntry->setExtractedOS(ZipPlatform::OS_UNIX);
        $zipEntry->setUnixMode(040755);
        $zipEntry->setTime(time());

        $this->addZipEntry($zipEntry);

        return $this;
    }

    /**
     * Add directory not recursively to the zip archive.
     *
     * @param string   $inputDir          Input directory
     * @param string   $localPath         add files to this directory, or the root
     * @param int|null $compressionMethod Compression method.
     *                                    Use {@see ZipCompressionMethod::STORED},
     *                                    {@see ZipCompressionMethod::DEFLATED} or
     *                                    {@see ZipCompressionMethod::BZIP2}.
     *                                    If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function addDir(string $inputDir, string $localPath = '/', ?int $compressionMethod = null): self
    {
        if ($inputDir === '') {
            throw new InvalidArgumentException('The input directory is not specified');
        }

        if (!is_dir($inputDir)) {
            throw new InvalidArgumentException(sprintf('The "%s" directory does not exist.', $inputDir));
        }
        $inputDir = rtrim($inputDir, '/\\') . \DIRECTORY_SEPARATOR;

        $directoryIterator = new \DirectoryIterator($inputDir);

        return $this->addFilesFromIterator($directoryIterator, $localPath, $compressionMethod);
    }

    /**
     * Add recursive directory to the zip archive.
     *
     * @param string   $inputDir          Input directory
     * @param string   $localPath         add files to this directory, or the root
     * @param int|null $compressionMethod Compression method.
     *                                    Use {@see ZipCompressionMethod::STORED}, {@see
     *                                    ZipCompressionMethod::DEFLATED} or
     *                                    {@see ZipCompressionMethod::BZIP2}.
     *                                    If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @see ZipCompressionMethod::STORED
     * @see ZipCompressionMethod::DEFLATED
     * @see ZipCompressionMethod::BZIP2
     */
    public function addDirRecursive(string $inputDir, string $localPath = '/', ?int $compressionMethod = null): self
    {
        if ($inputDir === '') {
            throw new InvalidArgumentException('The input directory is not specified');
        }

        if (!is_dir($inputDir)) {
            throw new InvalidArgumentException(sprintf('The "%s" directory does not exist.', $inputDir));
        }
        $inputDir = rtrim($inputDir, '/\\') . \DIRECTORY_SEPARATOR;

        $directoryIterator = new \RecursiveDirectoryIterator($inputDir);

        return $this->addFilesFromIterator($directoryIterator, $localPath, $compressionMethod);
    }

    /**
     * Add directories from directory iterator.
     *
     * @param \Iterator $iterator          directory iterator
     * @param string    $localPath         add files to this directory, or the root
     * @param int|null  $compressionMethod Compression method.
     *                                     Use {@see ZipCompressionMethod::STORED}, {@see
     *                                     ZipCompressionMethod::DEFLATED} or
     *                                     {@see ZipCompressionMethod::BZIP2}.
     *                                     If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @see ZipCompressionMethod::STORED
     * @see ZipCompressionMethod::DEFLATED
     * @see ZipCompressionMethod::BZIP2
     */
    public function addFilesFromIterator(
        \Iterator $iterator,
        string $localPath = '/',
        ?int $compressionMethod = null
    ): self {
        if ($localPath !== '') {
            $localPath = trim($localPath, '\\/');
        } else {
            $localPath = '';
        }

        $iterator = $iterator instanceof \RecursiveIterator
            ? new \RecursiveIteratorIterator($iterator)
            : new \IteratorIterator($iterator);
        /**
         * @var string[] $files
         * @var string   $path
         */
        $files = [];

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo) {
                if ($file->getBasename() === '..') {
                    continue;
                }

                if ($file->getBasename() === '.') {
                    $files[] = \dirname($file->getPathname());
                } else {
                    $files[] = $file->getPathname();
                }
            }
        }

        if (empty($files)) {
            return $this;
        }

        natcasesort($files);
        $path = array_shift($files);

        $this->doAddFiles($path, $files, $localPath, $compressionMethod);

        return $this;
    }

    /**
     * Add files from glob pattern.
     *
     * @param string   $inputDir          Input directory
     * @param string   $globPattern       glob pattern
     * @param string   $localPath         add files to this directory, or the root
     * @param int|null $compressionMethod Compression method.
     *                                    Use {@see ZipCompressionMethod::STORED},
     *                                    {@see ZipCompressionMethod::DEFLATED} or
     *                                    {@see ZipCompressionMethod::BZIP2}.
     *                                    If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function addFilesFromGlob(
        string $inputDir,
        string $globPattern,
        string $localPath = '/',
        ?int $compressionMethod = null
    ): self {
        return $this->addGlob($inputDir, $globPattern, $localPath, false, $compressionMethod);
    }

    /**
     * Add files from glob pattern.
     *
     * @param string   $inputDir          Input directory
     * @param string   $globPattern       glob pattern
     * @param string   $localPath         add files to this directory, or the root
     * @param bool     $recursive         recursive search
     * @param int|null $compressionMethod Compression method.
     *                                    Use {@see ZipCompressionMethod::STORED},
     *                                    {@see ZipCompressionMethod::DEFLATED} or
     *                                    {@see ZipCompressionMethod::BZIP2}.
     *                                    If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    private function addGlob(
        string $inputDir,
        string $globPattern,
        string $localPath = '/',
        bool $recursive = true,
        ?int $compressionMethod = null
    ): self {
        if ($inputDir === '') {
            throw new InvalidArgumentException('The input directory is not specified');
        }

        if (!is_dir($inputDir)) {
            throw new InvalidArgumentException(sprintf('The "%s" directory does not exist.', $inputDir));
        }

        if (empty($globPattern)) {
            throw new InvalidArgumentException('The glob pattern is not specified');
        }

        $inputDir = rtrim($inputDir, '/\\') . \DIRECTORY_SEPARATOR;
        $globPattern = $inputDir . $globPattern;

        $filesFound = FilesUtil::globFileSearch($globPattern, \GLOB_BRACE, $recursive);

        if (empty($filesFound)) {
            return $this;
        }

        $this->doAddFiles($inputDir, $filesFound, $localPath, $compressionMethod);

        return $this;
    }

    /**
     * Add files recursively from glob pattern.
     *
     * @param string   $inputDir          Input directory
     * @param string   $globPattern       glob pattern
     * @param string   $localPath         add files to this directory, or the root
     * @param int|null $compressionMethod Compression method.
     *                                    Use {@see ZipCompressionMethod::STORED},
     *                                    {@see ZipCompressionMethod::DEFLATED} or
     *                                    {@see ZipCompressionMethod::BZIP2}.
     *                                    If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function addFilesFromGlobRecursive(
        string $inputDir,
        string $globPattern,
        string $localPath = '/',
        ?int $compressionMethod = null
    ): self {
        return $this->addGlob($inputDir, $globPattern, $localPath, true, $compressionMethod);
    }

    /**
     * Add files from regex pattern.
     *
     * @param string   $inputDir          search files in this directory
     * @param string   $regexPattern      regex pattern
     * @param string   $localPath         add files to this directory, or the root
     * @param int|null $compressionMethod Compression method.
     *                                    Use {@see ZipCompressionMethod::STORED},
     *                                    {@see ZipCompressionMethod::DEFLATED} or
     *                                    {@see ZipCompressionMethod::BZIP2}.
     *                                    If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @internal param bool $recursive Recursive search
     */
    public function addFilesFromRegex(
        string $inputDir,
        string $regexPattern,
        string $localPath = '/',
        ?int $compressionMethod = null
    ): self {
        return $this->addRegex($inputDir, $regexPattern, $localPath, false, $compressionMethod);
    }

    /**
     * Add files from regex pattern.
     *
     * @param string   $inputDir          search files in this directory
     * @param string   $regexPattern      regex pattern
     * @param string   $localPath         add files to this directory, or the root
     * @param bool     $recursive         recursive search
     * @param int|null $compressionMethod Compression method.
     *                                    Use {@see ZipCompressionMethod::STORED},
     *                                    {@see ZipCompressionMethod::DEFLATED} or
     *                                    {@see ZipCompressionMethod::BZIP2}.
     *                                    If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    private function addRegex(
        string $inputDir,
        string $regexPattern,
        string $localPath = '/',
        bool $recursive = true,
        ?int $compressionMethod = null
    ): self {
        if ($regexPattern === '') {
            throw new InvalidArgumentException('The regex pattern is not specified');
        }

        if ($inputDir === '') {
            throw new InvalidArgumentException('The input directory is not specified');
        }

        if (!is_dir($inputDir)) {
            throw new InvalidArgumentException(sprintf('The "%s" directory does not exist.', $inputDir));
        }
        $inputDir = rtrim($inputDir, '/\\') . \DIRECTORY_SEPARATOR;

        $files = FilesUtil::regexFileSearch($inputDir, $regexPattern, $recursive);

        if (empty($files)) {
            return $this;
        }

        $this->doAddFiles($inputDir, $files, $localPath, $compressionMethod);

        return $this;
    }

    /**
     * @param ?int $compressionMethod
     *
     * @throws ZipException
     */
    private function doAddFiles(
        string $fileSystemDir,
        array $files,
        string $zipPath,
        ?int $compressionMethod = null
    ): void {
        $fileSystemDir = rtrim($fileSystemDir, '/\\') . \DIRECTORY_SEPARATOR;

        if (!empty($zipPath)) {
            $zipPath = trim($zipPath, '\\/') . '/';
        } else {
            $zipPath = '/';
        }

        /**
         * @var string $file
         */
        foreach ($files as $file) {
            $filename = str_replace($fileSystemDir, $zipPath, $file);
            $filename = ltrim($filename, '\\/');

            if (is_dir($file) && FilesUtil::isEmptyDir($file)) {
                $this->addEmptyDir($filename);
            } elseif (is_file($file)) {
                $this->addFile($file, $filename, $compressionMethod);
            }
        }
    }

    /**
     * Add files recursively from regex pattern.
     *
     * @param string   $inputDir          search files in this directory
     * @param string   $regexPattern      regex pattern
     * @param string   $localPath         add files to this directory, or the root
     * @param int|null $compressionMethod Compression method.
     *                                    Use {@see ZipCompressionMethod::STORED},
     *                                    {@see ZipCompressionMethod::DEFLATED} or
     *                                    {@see ZipCompressionMethod::BZIP2}.
     *                                    If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @internal param bool $recursive Recursive search
     */
    public function addFilesFromRegexRecursive(
        string $inputDir,
        string $regexPattern,
        string $localPath = '/',
        ?int $compressionMethod = null
    ): self {
        return $this->addRegex($inputDir, $regexPattern, $localPath, true, $compressionMethod);
    }

    /**
     * Add array data to archive.
     * Keys is local names.
     * Values is contents.
     *
     * @param array $mapData associative array for added to zip
     */
    public function addAll(array $mapData): void
    {
        foreach ($mapData as $localName => $content) {
            $this[$localName] = $content;
        }
    }

    /**
     * Rename the entry.
     *
     * @param string $oldName old entry name
     * @param string $newName new entry name
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function rename(string $oldName, string $newName): self
    {
        $oldName = ltrim($oldName, '\\/');
        $newName = ltrim($newName, '\\/');

        if ($oldName !== $newName) {
            $this->zipContainer->renameEntry($oldName, $newName);
        }

        return $this;
    }

    /**
     * Delete entry by name.
     *
     * @param string $entryName zip Entry name
     *
     * @throws ZipEntryNotFoundException if entry not found
     *
     * @return ZipFile
     */
    public function deleteFromName(string $entryName): self
    {
        $entryName = ltrim($entryName, '\\/');

        if (!$this->zipContainer->deleteEntry($entryName)) {
            throw new ZipEntryNotFoundException($entryName);
        }

        return $this;
    }

    /**
     * Delete entries by glob pattern.
     *
     * @param string $globPattern Glob pattern
     *
     * @return ZipFile
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function deleteFromGlob(string $globPattern): self
    {
        if (empty($globPattern)) {
            throw new InvalidArgumentException('The glob pattern is not specified');
        }
        $globPattern = '~' . FilesUtil::convertGlobToRegEx($globPattern) . '~si';
        $this->deleteFromRegex($globPattern);

        return $this;
    }

    /**
     * Delete entries by regex pattern.
     *
     * @param string $regexPattern Regex pattern
     *
     * @return ZipFile
     */
    public function deleteFromRegex(string $regexPattern): self
    {
        if (empty($regexPattern)) {
            throw new InvalidArgumentException('The regex pattern is not specified');
        }
        $this->matcher()->match($regexPattern)->delete();

        return $this;
    }

    /**
     * Delete all entries.
     *
     * @return ZipFile
     */
    public function deleteAll(): self
    {
        $this->zipContainer->deleteAll();

        return $this;
    }

    /**
     * Set compression level for new entries.
     *
     * @return ZipFile
     *
     * @see ZipCompressionLevel::NORMAL
     * @see ZipCompressionLevel::SUPER_FAST
     * @see ZipCompressionLevel::FAST
     * @see ZipCompressionLevel::MAXIMUM
     */
    public function setCompressionLevel(int $compressionLevel = ZipCompressionLevel::NORMAL): self
    {
        foreach ($this->zipContainer->getEntries() as $entry) {
            $entry->setCompressionLevel($compressionLevel);
        }

        return $this;
    }

    /**
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @see ZipCompressionLevel::NORMAL
     * @see ZipCompressionLevel::SUPER_FAST
     * @see ZipCompressionLevel::FAST
     * @see ZipCompressionLevel::MAXIMUM
     */
    public function setCompressionLevelEntry(string $entryName, int $compressionLevel): self
    {
        $this->getEntry($entryName)->setCompressionLevel($compressionLevel);

        return $this;
    }

    /**
     * @param int $compressionMethod Compression method.
     *                               Use {@see ZipCompressionMethod::STORED},
     *                               {@see ZipCompressionMethod::DEFLATED} or
     *                               {@see ZipCompressionMethod::BZIP2}.
     *                               If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @see ZipCompressionMethod::STORED
     * @see ZipCompressionMethod::DEFLATED
     * @see ZipCompressionMethod::BZIP2
     */
    public function setCompressionMethodEntry(string $entryName, int $compressionMethod): self
    {
        $this->zipContainer
            ->getEntry($entryName)
            ->setCompressionMethod($compressionMethod)
        ;

        return $this;
    }

    /**
     * Set password to all input encrypted entries.
     *
     * @param string $password Password
     *
     * @return ZipFile
     */
    public function setReadPassword(string $password): self
    {
        $this->zipContainer->setReadPassword($password);

        return $this;
    }

    /**
     * Set password to concrete input entry.
     *
     * @param string $password Password
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function setReadPasswordEntry(string $entryName, string $password): self
    {
        $this->zipContainer->setReadPasswordEntry($entryName, $password);

        return $this;
    }

    /**
     * Sets a new password for all files in the archive.
     *
     * @param string   $password         Password
     * @param int|null $encryptionMethod Encryption method
     *
     * @throws ZipEntryNotFoundException
     *
     * @return ZipFile
     */
    public function setPassword(string $password, ?int $encryptionMethod = ZipEncryptionMethod::WINZIP_AES_256): self
    {
        $this->zipContainer->setWritePassword($password);

        if ($encryptionMethod !== null) {
            $this->zipContainer->setEncryptionMethod($encryptionMethod);
        }

        return $this;
    }

    /**
     * Sets a new password of an entry defined by its name.
     *
     * @param ?int $encryptionMethod
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function setPasswordEntry(string $entryName, string $password, ?int $encryptionMethod = null): self
    {
        $this->getEntry($entryName)->setPassword($password, $encryptionMethod);

        return $this;
    }

    /**
     * Disable encryption for all entries that are already in the archive.
     *
     * @throws ZipEntryNotFoundException
     *
     * @return ZipFile
     */
    public function disableEncryption(): self
    {
        $this->zipContainer->removePassword();

        return $this;
    }

    /**
     * Disable encryption of an entry defined by its name.
     *
     * @throws ZipEntryNotFoundException
     *
     * @return ZipFile
     */
    public function disableEncryptionEntry(string $entryName): self
    {
        $this->zipContainer->removePasswordEntry($entryName);

        return $this;
    }

    /**
     * Undo all changes done in the archive.
     *
     * @return ZipFile
     */
    public function unchangeAll(): self
    {
        $this->zipContainer->unchangeAll();

        return $this;
    }

    /**
     * Undo change archive comment.
     *
     * @return ZipFile
     */
    public function unchangeArchiveComment(): self
    {
        $this->zipContainer->unchangeArchiveComment();

        return $this;
    }

    /**
     * Revert all changes done to an entry with the given name.
     *
     * @param string|ZipEntry $entry Entry name or ZipEntry
     *
     * @return ZipFile
     */
    public function unchangeEntry($entry): self
    {
        $this->zipContainer->unchangeEntry($entry);

        return $this;
    }

    /**
     * Save as file.
     *
     * @param string $filename Output filename
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function saveAsFile(string $filename): self
    {
        $tempFilename = $filename . '.temp' . uniqid('', false);

        /** @psalm-suppress InvalidArgument */
        set_error_handler(
            static function (int $errorNumber, string $errorString): ?bool {
                throw new InvalidArgumentException($errorString, $errorNumber);
            }
        );
        $handle = fopen($tempFilename, 'w+b');
        restore_error_handler();
        $this->saveAsStream($handle);

        $reopen = false;

        if ($this->reader !== null) {
            $meta = $this->reader->getStreamMetaData();

            if ($meta['wrapper_type'] === 'plainfile' && isset($meta['uri'])) {
                $readFilePath = realpath($meta['uri']);
                $writeFilePath = realpath($filename);

                if ($readFilePath !== false && $writeFilePath !== false && $readFilePath === $writeFilePath) {
                    $this->reader->close();
                    $reopen = true;
                }
            }
        }

        if (!rename($tempFilename, $filename)) {
            if (is_file($tempFilename)) {
                unlink($tempFilename);
            }

            throw new ZipException(sprintf('Cannot move %s to %s', $tempFilename, $filename));
        }

        if ($reopen) {
            return $this->openFile($filename);
        }

        return $this;
    }

    /**
     * Save as stream.
     *
     * @param resource $handle Output stream resource
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function saveAsStream($handle): self
    {
        if (!\is_resource($handle)) {
            throw new InvalidArgumentException('handle is not resource');
        }
        ftruncate($handle, 0);
        $this->writeZipToStream($handle);
        fclose($handle);

        return $this;
    }

    /**
     * Output .ZIP archive as attachment.
     * Die after output.
     *
     * @param string      $outputFilename Output filename
     * @param string|null $mimeType       Mime-Type
     * @param bool        $attachment     Http Header 'Content-Disposition' if true then attachment otherwise inline
     *
     * @throws ZipException
     */
    public function outputAsAttachment(string $outputFilename, ?string $mimeType = null, bool $attachment = true): void
    {
        [
            'resource' => $resource,
            'headers' => $headers,
        ] = $this->getOutputData($outputFilename, $mimeType, $attachment);

        if (!headers_sent()) {
            foreach ($headers as $key => $value) {
                header($key . ': ' . $value);
            }
        }

        rewind($resource);

        try {
            echo stream_get_contents($resource, -1, 0);
        } finally {
            fclose($resource);
        }
    }

    /**
     * @param ?string $mimeType
     *
     * @throws ZipException
     */
    private function getOutputData(string $outputFilename, ?string $mimeType = null, bool $attachment = true): array
    {
        $mimeType ??= $this->getMimeTypeByFilename($outputFilename);

        if (!($handle = fopen('php://temp', 'w+b'))) {
            throw new InvalidArgumentException('php://temp cannot open for write.');
        }
        $this->writeZipToStream($handle);
        $this->close();

        $size = fstat($handle)['size'];

        $contentDisposition = $attachment ? 'attachment' : 'inline';
        $name = basename($outputFilename);

        if (!empty($name)) {
            $contentDisposition .= '; filename="' . $name . '"';
        }

        return [
            'resource' => $handle,
            'headers' => [
                'Content-Disposition' => $contentDisposition,
                'Content-Type' => $mimeType,
                'Content-Length' => $size,
            ],
        ];
    }

    protected function getMimeTypeByFilename(string $outputFilename): string
    {
        $ext = strtolower(pathinfo($outputFilename, \PATHINFO_EXTENSION));

        if (!empty($ext) && isset(self::DEFAULT_MIME_TYPES[$ext])) {
            return self::DEFAULT_MIME_TYPES[$ext];
        }

        return self::DEFAULT_MIME_TYPES['zip'];
    }

    /**
     * Output .ZIP archive as PSR-7 Response.
     *
     * @param ResponseInterface $response       Instance PSR-7 Response
     * @param string            $outputFilename Output filename
     * @param string|null       $mimeType       Mime-Type
     * @param bool              $attachment     Http Header 'Content-Disposition' if true then attachment otherwise inline
     *
     * @throws ZipException
     *
     * @deprecated deprecated since version 2.0, replace to {@see ZipFile::outputAsPsr7Response}
     */
    public function outputAsResponse(
        ResponseInterface $response,
        string $outputFilename,
        ?string $mimeType = null,
        bool $attachment = true
    ): ResponseInterface {
        @trigger_error(
            sprintf(
                'Method %s is deprecated. Replace to %s::%s',
                __METHOD__,
                __CLASS__,
                'outputAsPsr7Response'
            ),
            \E_USER_DEPRECATED
        );

        return $this->outputAsPsr7Response($response, $outputFilename, $mimeType, $attachment);
    }

    /**
     * Output .ZIP archive as PSR-7 Response.
     *
     * @param ResponseInterface $response       Instance PSR-7 Response
     * @param string            $outputFilename Output filename
     * @param string|null       $mimeType       Mime-Type
     * @param bool              $attachment     Http Header 'Content-Disposition' if true then attachment otherwise inline
     *
     * @throws ZipException
     *
     * @since 4.0.0
     */
    public function outputAsPsr7Response(
        ResponseInterface $response,
        string $outputFilename,
        ?string $mimeType = null,
        bool $attachment = true
    ): ResponseInterface {
        [
            'resource' => $resource,
            'headers' => $headers,
        ] = $this->getOutputData($outputFilename, $mimeType, $attachment);

        foreach ($headers as $key => $value) {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $response = $response->withHeader($key, (string) $value);
        }

        return $response->withBody(new ResponseStream($resource));
    }

    /**
     * Output .ZIP archive as Symfony Response.
     *
     * @param string      $outputFilename Output filename
     * @param string|null $mimeType       Mime-Type
     * @param bool        $attachment     Http Header 'Content-Disposition' if true then attachment otherwise inline
     *
     * @throws ZipException
     *
     * @since 4.0.0
     */
    public function outputAsSymfonyResponse(
        string $outputFilename,
        ?string $mimeType = null,
        bool $attachment = true
    ): Response {
        [
            'resource' => $resource,
            'headers' => $headers,
        ] = $this->getOutputData($outputFilename, $mimeType, $attachment);

        return new StreamedResponse(
            static function () use ($resource): void {
                if (!($output = fopen('php://output', 'w+b'))) {
                    throw new InvalidArgumentException('php://output cannot open for write.');
                }
                rewind($resource);
                stream_copy_to_stream($resource, $output);
                fclose($output);
                fclose($resource);
            },
            200,
            $headers
        );
    }

    /**
     * @param resource $handle
     *
     * @throws ZipException
     */
    protected function writeZipToStream($handle): void
    {
        $this->onBeforeSave();

        $this->createZipWriter()->write($handle);
    }

    /**
     * Returns the zip archive as a string.
     *
     * @throws ZipException
     */
    public function outputAsString(): string
    {
        if (!($handle = fopen('php://temp', 'w+b'))) {
            throw new InvalidArgumentException('php://temp cannot open for write.');
        }
        $this->writeZipToStream($handle);
        rewind($handle);

        try {
            return stream_get_contents($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Event before save or output.
     */
    protected function onBeforeSave(): void
    {
    }

    /**
     * Close zip archive and release input stream.
     */
    public function close(): void
    {
        if ($this->reader !== null) {
            $this->reader->close();
            $this->reader = null;
        }
        $this->zipContainer = $this->createZipContainer();
        gc_collect_cycles();
    }

    /**
     * Save and reopen zip archive.
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function rewrite(): self
    {
        if ($this->reader === null) {
            throw new ZipException('input stream is null');
        }

        $meta = $this->reader->getStreamMetaData();

        if ($meta['wrapper_type'] !== 'plainfile' || !isset($meta['uri'])) {
            throw new ZipException('Overwrite is only supported for open local files.');
        }

        return $this->saveAsFile($meta['uri']);
    }

    /**
     * Release all resources.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Offset to set.
     *
     * @see http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed                                           $offset the offset to assign the value to
     * @param string|\DirectoryIterator|\SplFileInfo|resource $value  the value to set
     *
     * @throws ZipException
     *
     * @see ZipFile::addFromString
     * @see ZipFile::addEmptyDir
     * @see ZipFile::addFile
     * @see ZipFile::addFilesFromIterator
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            throw new InvalidArgumentException('Key must not be null, but must contain the name of the zip entry.');
        }
        $offset = ltrim((string) $offset, '\\/');

        if ($offset === '') {
            throw new InvalidArgumentException('Key is empty, but must contain the name of the zip entry.');
        }

        if ($value instanceof \DirectoryIterator) {
            $this->addFilesFromIterator($value, $offset);
        } elseif ($value instanceof \SplFileInfo) {
            $this->addSplFile($value, $offset);
        } elseif (StringUtil::endsWith($offset, '/')) {
            $this->addEmptyDir($offset);
        } elseif (\is_resource($value)) {
            $this->addFromStream($value, $offset);
        } else {
            $this->addFromString($offset, (string) $value);
        }
    }

    /**
     * Offset to unset.
     *
     * @see http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param mixed $offset zip entry name
     *
     * @throws ZipEntryNotFoundException
     */
    public function offsetUnset($offset): void
    {
        $this->deleteFromName($offset);
    }

    /**
     * Return the current element.
     *
     * @see http://php.net/manual/en/iterator.current.php
     *
     * @throws ZipException
     */
    public function current(): ?string
    {
        return $this->offsetGet($this->key());
    }

    /**
     * Offset to retrieve.
     *
     * @see http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param mixed $offset zip entry name
     *
     * @throws ZipException
     */
    public function offsetGet($offset): ?string
    {
        return $this->getEntryContents($offset);
    }

    /**
     * Return the key of the current element.
     *
     * @see http://php.net/manual/en/iterator.key.php
     *
     * @return string|null scalar on success, or null on failure
     */
    public function key(): ?string
    {
        return key($this->zipContainer->getEntries());
    }

    /**
     * Move forward to next element.
     *
     * @see http://php.net/manual/en/iterator.next.php
     */
    public function next(): void
    {
        next($this->zipContainer->getEntries());
    }

    /**
     * Checks if current position is valid.
     *
     * @see http://php.net/manual/en/iterator.valid.php
     *
     * @return bool The return value will be casted to boolean and then evaluated.
     *              Returns true on success or false on failure.
     */
    public function valid(): bool
    {
        $key = $this->key();

        return $key !== null && isset($this->zipContainer->getEntries()[$key]);
    }

    /**
     * Whether a offset exists.
     *
     * @see http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param mixed $offset an offset to check for
     *
     * @return bool true on success or false on failure.
     *              The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset): bool
    {
        return isset($this->zipContainer->getEntries()[$offset]);
    }

    /**
     * Rewind the Iterator to the first element.
     *
     * @see http://php.net/manual/en/iterator.rewind.php
     */
    public function rewind(): void
    {
        reset($this->zipContainer->getEntries());
    }
}
