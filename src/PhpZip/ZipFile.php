<?php

/** @noinspection PhpUsageOfSilenceOperatorInspection */

namespace PhpZip;

use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipEntryNotFoundException;
use PhpZip\Exception\ZipException;
use PhpZip\Exception\ZipUnsupportMethodException;
use PhpZip\Model\Entry\ZipNewEntry;
use PhpZip\Model\Entry\ZipNewFileEntry;
use PhpZip\Model\ZipEntry;
use PhpZip\Model\ZipEntryMatcher;
use PhpZip\Model\ZipInfo;
use PhpZip\Model\ZipModel;
use PhpZip\Stream\ResponseStream;
use PhpZip\Stream\ZipInputStream;
use PhpZip\Stream\ZipInputStreamInterface;
use PhpZip\Stream\ZipOutputStream;
use PhpZip\Util\FilesUtil;
use PhpZip\Util\StringUtil;
use Psr\Http\Message\ResponseInterface;

/**
 * Create, open .ZIP files, modify, get info and extract files.
 *
 * Implemented support traditional PKWARE encryption and WinZip AES encryption.
 * Implemented support ZIP64.
 * Implemented support skip a preamble like the one found in self extracting archives.
 * Support ZipAlign functional.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipFile implements ZipFileInterface
{
    /** @var int[] allow compression methods */
    private static $allowCompressionMethods = [
        self::METHOD_STORED,
        self::METHOD_DEFLATED,
        self::METHOD_BZIP2,
        ZipEntry::UNKNOWN,
    ];

    /** @var int[] allow encryption methods */
    private static $allowEncryptionMethods = [
        self::ENCRYPTION_METHOD_TRADITIONAL,
        self::ENCRYPTION_METHOD_WINZIP_AES_128,
        self::ENCRYPTION_METHOD_WINZIP_AES_192,
        self::ENCRYPTION_METHOD_WINZIP_AES_256,
    ];

    /** @var array default mime types */
    private static $defaultMimeTypes = [
        'zip' => 'application/zip',
        'apk' => 'application/vnd.android.package-archive',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jar' => 'application/java-archive',
        'epub' => 'application/epub+zip',
    ];

    /** @var ZipInputStreamInterface input seekable input stream */
    protected $inputStream;

    /** @var ZipModel */
    protected $zipModel;

    /**
     * ZipFile constructor.
     */
    public function __construct()
    {
        $this->zipModel = new ZipModel();
    }

    /**
     * Open zip archive from file.
     *
     * @param string $filename
     *
     * @throws ZipException if can't open file
     *
     * @return ZipFile
     */
    public function openFile($filename)
    {
        if (!file_exists($filename)) {
            throw new ZipException("File {$filename} does not exist.");
        }

        if (!($handle = @fopen($filename, 'rb'))) {
            throw new ZipException("File {$filename} can't open.");
        }
        $this->openFromStream($handle);

        return $this;
    }

    /**
     * Open zip archive from raw string data.
     *
     * @param string $data
     *
     * @throws ZipException if can't open temp stream
     *
     * @return ZipFile
     */
    public function openFromString($data)
    {
        if ($data === null || $data === '') {
            throw new InvalidArgumentException('Empty string passed');
        }

        if (!($handle = fopen('php://temp', 'r+b'))) {
            throw new ZipException("Can't open temp stream.");
        }
        fwrite($handle, $data);
        rewind($handle);
        $this->openFromStream($handle);

        return $this;
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
    public function openFromStream($handle)
    {
        if (!\is_resource($handle)) {
            throw new InvalidArgumentException('Invalid stream resource.');
        }
        $type = get_resource_type($handle);

        if ($type !== 'stream') {
            throw new InvalidArgumentException("Invalid resource type - {$type}.");
        }
        $meta = stream_get_meta_data($handle);

        if ($meta['stream_type'] === 'dir') {
            throw new InvalidArgumentException("Invalid stream type - {$meta['stream_type']}.");
        }

        if (!$meta['seekable']) {
            throw new InvalidArgumentException('Resource cannot seekable stream.');
        }
        $this->inputStream = new ZipInputStream($handle);
        $this->zipModel = $this->inputStream->readZip();

        return $this;
    }

    /**
     * @return string[] returns the list files
     */
    public function getListFiles()
    {
        return array_map('strval', array_keys($this->zipModel->getEntries()));
    }

    /**
     * @return int returns the number of entries in this ZIP file
     */
    public function count()
    {
        return $this->zipModel->count();
    }

    /**
     * Returns the file comment.
     *
     * @return string|null the file comment
     */
    public function getArchiveComment()
    {
        return $this->zipModel->getArchiveComment();
    }

    /**
     * Set archive comment.
     *
     * @param string|null $comment
     *
     * @return ZipFile
     */
    public function setArchiveComment($comment = null)
    {
        $this->zipModel->setArchiveComment($comment);

        return $this;
    }

    /**
     * Checks that the entry in the archive is a directory.
     * Returns true if and only if this ZIP entry represents a directory entry
     * (i.e. end with '/').
     *
     * @param string $entryName
     *
     * @throws ZipEntryNotFoundException
     *
     * @return bool
     */
    public function isDirectory($entryName)
    {
        return $this->zipModel->getEntry($entryName)->isDirectory();
    }

    /**
     * Returns entry comment.
     *
     * @param string $entryName
     *
     * @throws ZipEntryNotFoundException
     *
     * @return string
     */
    public function getEntryComment($entryName)
    {
        return $this->zipModel->getEntry($entryName)->getComment();
    }

    /**
     * Set entry comment.
     *
     * @param string      $entryName
     * @param string|null $comment
     *
     * @throws ZipException
     * @throws ZipEntryNotFoundException
     *
     * @return ZipFile
     */
    public function setEntryComment($entryName, $comment = null)
    {
        $this->zipModel->getEntryForChanges($entryName)->setComment($comment);

        return $this;
    }

    /**
     * Returns the entry contents.
     *
     * @param string $entryName
     *
     * @throws ZipException
     *
     * @return string
     */
    public function getEntryContents($entryName)
    {
        return $this->zipModel->getEntry($entryName)->getEntryContent();
    }

    /**
     * Checks if there is an entry in the archive.
     *
     * @param string $entryName
     *
     * @return bool
     */
    public function hasEntry($entryName)
    {
        return $this->zipModel->hasEntry($entryName);
    }

    /**
     * Get info by entry.
     *
     * @param string|ZipEntry $entryName
     *
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     *
     * @return ZipInfo
     */
    public function getEntryInfo($entryName)
    {
        return new ZipInfo($this->zipModel->getEntry($entryName));
    }

    /**
     * Get info by all entries.
     *
     * @return ZipInfo[]
     */
    public function getAllInfo()
    {
        return array_map([$this, 'getEntryInfo'], $this->zipModel->getEntries());
    }

    /**
     * @return ZipEntryMatcher
     */
    public function matcher()
    {
        return $this->zipModel->matcher();
    }

    /**
     * Extract the archive contents.
     *
     * Extract the complete archive or the given files to the specified destination.
     *
     * @param string            $destination location where to extract the files
     * @param array|string|null $entries     The entries to extract. It accepts either
     *                                       a single entry name or an array of names.
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function extractTo($destination, $entries = null)
    {
        if (!file_exists($destination)) {
            throw new ZipException(sprintf('Destination %s not found', $destination));
        }

        if (!is_dir($destination)) {
            throw new ZipException('Destination is not directory');
        }

        if (!is_writable($destination)) {
            throw new ZipException('Destination is not writable directory');
        }

        $zipEntries = $this->zipModel->getEntries();

        if (!empty($entries)) {
            if (\is_string($entries)) {
                $entries = (array) $entries;
            }

            if (\is_array($entries)) {
                $entries = array_unique($entries);
                $flipEntries = array_flip($entries);
                $zipEntries = array_filter(
                    $zipEntries,
                    static function (ZipEntry $zipEntry) use ($flipEntries) {
                        return isset($flipEntries[$zipEntry->getName()]);
                    }
                );
            }
        }

        foreach ($zipEntries as $entry) {
            $entryName = FilesUtil::normalizeZipPath($entry->getName());
            $file = $destination . \DIRECTORY_SEPARATOR . $entryName;

            if ($entry->isDirectory()) {
                if (!is_dir($file)) {
                    if (!mkdir($file, 0755, true) && !is_dir($file)) {
                        throw new ZipException('Can not create dir ' . $file);
                    }
                    chmod($file, 0755);
                    touch($file, $entry->getTime());
                }

                continue;
            }
            $dir = \dirname($file);

            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                    throw new ZipException('Can not create dir ' . $dir);
                }
                chmod($dir, 0755);
                touch($dir, $entry->getTime());
            }

            if (file_put_contents($file, $entry->getEntryContent()) === false) {
                throw new ZipException('Can not extract file ' . $entry->getName());
            }
            touch($file, $entry->getTime());
        }

        return $this;
    }

    /**
     * Add entry from the string.
     *
     * @param string   $localName         zip entry name
     * @param string   $contents          string contents
     * @param int|null $compressionMethod Compression method.
     *                                    Use {@see ZipFile::METHOD_STORED}, {@see ZipFile::METHOD_DEFLATED} or
     *                                    {@see ZipFile::METHOD_BZIP2}. If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @see ZipFile::METHOD_STORED
     * @see ZipFile::METHOD_DEFLATED
     * @see ZipFile::METHOD_BZIP2
     */
    public function addFromString($localName, $contents, $compressionMethod = null)
    {
        if ($contents === null) {
            throw new InvalidArgumentException('Contents is null');
        }

        if ($localName === null) {
            throw new InvalidArgumentException('Entry name is null');
        }
        $localName = ltrim((string) $localName, '\\/');

        if ($localName === '') {
            throw new InvalidArgumentException('Empty entry name');
        }
        $contents = (string) $contents;
        $length = \strlen($contents);

        if ($compressionMethod === null) {
            if ($length >= 512) {
                $compressionMethod = ZipEntry::UNKNOWN;
            } else {
                $compressionMethod = self::METHOD_STORED;
            }
        } elseif (!\in_array($compressionMethod, self::$allowCompressionMethods, true)) {
            throw new ZipUnsupportMethodException('Unsupported compression method ' . $compressionMethod);
        }
        $externalAttributes = 0100644 << 16;

        $entry = new ZipNewEntry($contents);
        $entry->setName($localName);
        $entry->setMethod($compressionMethod);
        $entry->setTime(time());
        $entry->setExternalAttributes($externalAttributes);

        $this->zipModel->addEntry($entry);

        return $this;
    }

    /**
     * Add entry from the file.
     *
     * @param string      $filename          destination file
     * @param string|null $localName         zip Entry name
     * @param int|null    $compressionMethod Compression method.
     *                                       Use {@see ZipFile::METHOD_STORED}, {@see ZipFile::METHOD_DEFLATED} or
     *                                       {@see ZipFile::METHOD_BZIP2}. If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @see ZipFile::METHOD_STORED
     * @see ZipFile::METHOD_DEFLATED
     * @see ZipFile::METHOD_BZIP2
     */
    public function addFile($filename, $localName = null, $compressionMethod = null)
    {
        $entry = new ZipNewFileEntry($filename);

        if ($compressionMethod === null) {
            if (\function_exists('mime_content_type')) {
                /** @noinspection PhpComposerExtensionStubsInspection */
                $mimeType = @mime_content_type($filename);
                $type = strtok($mimeType, '/');

                if ($type === 'image') {
                    $compressionMethod = self::METHOD_STORED;
                } elseif ($type === 'text' && filesize($filename) < 150) {
                    $compressionMethod = self::METHOD_STORED;
                } else {
                    $compressionMethod = ZipEntry::UNKNOWN;
                }
            } elseif (filesize($filename) >= 512) {
                $compressionMethod = ZipEntry::UNKNOWN;
            } else {
                $compressionMethod = self::METHOD_STORED;
            }
        } elseif (!\in_array($compressionMethod, self::$allowCompressionMethods, true)) {
            throw new ZipUnsupportMethodException('Unsupported compression method ' . $compressionMethod);
        }

        if ($localName === null) {
            $localName = basename($filename);
        }
        $localName = ltrim((string) $localName, '\\/');

        if ($localName === '') {
            throw new InvalidArgumentException('Empty entry name');
        }

        $stat = stat($filename);
        $mode = sprintf('%o', $stat['mode']);
        $externalAttributes = (octdec($mode) & 0xffff) << 16;

        $entry->setName($localName);
        $entry->setMethod($compressionMethod);
        $entry->setTime($stat['mtime']);
        $entry->setExternalAttributes($externalAttributes);

        $this->zipModel->addEntry($entry);

        return $this;
    }

    /**
     * Add entry from the stream.
     *
     * @param resource $stream            stream resource
     * @param string   $localName         zip Entry name
     * @param int|null $compressionMethod Compression method.
     *                                    Use {@see ZipFile::METHOD_STORED}, {@see ZipFile::METHOD_DEFLATED} or
     *                                    {@see ZipFile::METHOD_BZIP2}. If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @see ZipFile::METHOD_STORED
     * @see ZipFile::METHOD_DEFLATED
     * @see ZipFile::METHOD_BZIP2
     */
    public function addFromStream($stream, $localName, $compressionMethod = null)
    {
        if (!\is_resource($stream)) {
            throw new InvalidArgumentException('Stream is not resource');
        }

        if ($localName === null) {
            throw new InvalidArgumentException('Entry name is null');
        }
        $localName = ltrim((string) $localName, '\\/');

        if ($localName === '') {
            throw new InvalidArgumentException('Empty entry name');
        }
        $fstat = fstat($stream);

        if ($fstat !== false) {
            $mode = sprintf('%o', $fstat['mode']);
            $length = $fstat['size'];

            if ($compressionMethod === null) {
                if ($length >= 512) {
                    $compressionMethod = ZipEntry::UNKNOWN;
                } else {
                    $compressionMethod = self::METHOD_STORED;
                }
            }
        } else {
            $mode = 010644;
        }

        if ($compressionMethod !== null && !\in_array($compressionMethod, self::$allowCompressionMethods, true)) {
            throw new ZipUnsupportMethodException('Unsupported method ' . $compressionMethod);
        }

        $externalAttributes = (octdec($mode) & 0xffff) << 16;

        $entry = new ZipNewEntry($stream);
        $entry->setName($localName);
        $entry->setMethod($compressionMethod);
        $entry->setTime(time());
        $entry->setExternalAttributes($externalAttributes);

        $this->zipModel->addEntry($entry);

        return $this;
    }

    /**
     * Add an empty directory in the zip archive.
     *
     * @param string $dirName
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function addEmptyDir($dirName)
    {
        if ($dirName === null) {
            throw new InvalidArgumentException('Dir name is null');
        }
        $dirName = ltrim((string) $dirName, '\\/');

        if ($dirName === '') {
            throw new InvalidArgumentException('Empty dir name');
        }
        $dirName = rtrim($dirName, '\\/') . '/';
        $externalAttributes = 040755 << 16;

        $entry = new ZipNewEntry();
        $entry->setName($dirName);
        $entry->setTime(time());
        $entry->setMethod(self::METHOD_STORED);
        $entry->setSize(0);
        $entry->setCompressedSize(0);
        $entry->setCrc(0);
        $entry->setExternalAttributes($externalAttributes);

        $this->zipModel->addEntry($entry);

        return $this;
    }

    /**
     * Add directory not recursively to the zip archive.
     *
     * @param string   $inputDir          Input directory
     * @param string   $localPath         add files to this directory, or the root
     * @param int|null $compressionMethod Compression method.
     *                                    Use {@see ZipFile::METHOD_STORED}, {@see ZipFile::METHOD_DEFLATED} or
     *                                    {@see ZipFile::METHOD_BZIP2}. If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function addDir($inputDir, $localPath = '/', $compressionMethod = null)
    {
        if ($inputDir === null) {
            throw new InvalidArgumentException('Input dir is null');
        }
        $inputDir = (string) $inputDir;

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
     *                                    Use {@see ZipFile::METHOD_STORED}, {@see ZipFile::METHOD_DEFLATED} or
     *                                    {@see ZipFile::METHOD_BZIP2}. If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @see ZipFile::METHOD_STORED
     * @see ZipFile::METHOD_DEFLATED
     * @see ZipFile::METHOD_BZIP2
     */
    public function addDirRecursive($inputDir, $localPath = '/', $compressionMethod = null)
    {
        if ($inputDir === null) {
            throw new InvalidArgumentException('Input dir is null');
        }
        $inputDir = (string) $inputDir;

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
     *                                     Use {@see ZipFile::METHOD_STORED}, {@see ZipFile::METHOD_DEFLATED} or
     *                                     {@see ZipFile::METHOD_BZIP2}. If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @see ZipFile::METHOD_STORED
     * @see ZipFile::METHOD_DEFLATED
     * @see ZipFile::METHOD_BZIP2
     */
    public function addFilesFromIterator(
        \Iterator $iterator,
        $localPath = '/',
        $compressionMethod = null
    ) {
        $localPath = (string) $localPath;

        if ($localPath !== '') {
            $localPath = trim($localPath, '\\/');
        } else {
            $localPath = '';
        }

        $iterator = $iterator instanceof \RecursiveIterator ?
            new \RecursiveIteratorIterator($iterator) :
            new \IteratorIterator($iterator);
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
     * @param string      $inputDir          Input directory
     * @param string      $globPattern       glob pattern
     * @param string|null $localPath         add files to this directory, or the root
     * @param int|null    $compressionMethod Compression method.
     *                                       Use {@see ZipFile::METHOD_STORED}, {@see ZipFile::METHOD_DEFLATED} or
     *                                       {@see ZipFile::METHOD_BZIP2}. If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function addFilesFromGlob($inputDir, $globPattern, $localPath = '/', $compressionMethod = null)
    {
        return $this->addGlob($inputDir, $globPattern, $localPath, false, $compressionMethod);
    }

    /**
     * Add files from glob pattern.
     *
     * @param string      $inputDir          Input directory
     * @param string      $globPattern       glob pattern
     * @param string|null $localPath         add files to this directory, or the root
     * @param bool        $recursive         recursive search
     * @param int|null    $compressionMethod Compression method.
     *                                       Use {@see ZipFile::METHOD_STORED}, {@see ZipFile::METHOD_DEFLATED} or
     *                                       {@see ZipFile::METHOD_BZIP2}. If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    private function addGlob(
        $inputDir,
        $globPattern,
        $localPath = '/',
        $recursive = true,
        $compressionMethod = null
    ) {
        if ($inputDir === null) {
            throw new InvalidArgumentException('Input dir is null');
        }
        $inputDir = (string) $inputDir;

        if ($inputDir === '') {
            throw new InvalidArgumentException('The input directory is not specified');
        }

        if (!is_dir($inputDir)) {
            throw new InvalidArgumentException(sprintf('The "%s" directory does not exist.', $inputDir));
        }
        $globPattern = (string) $globPattern;

        if (empty($globPattern)) {
            throw new InvalidArgumentException('The glob pattern is not specified');
        }

        $inputDir = rtrim($inputDir, '/\\') . \DIRECTORY_SEPARATOR;
        $globPattern = $inputDir . $globPattern;

        $filesFound = FilesUtil::globFileSearch($globPattern, \GLOB_BRACE, $recursive);

        if ($filesFound === false || empty($filesFound)) {
            return $this;
        }

        $this->doAddFiles($inputDir, $filesFound, $localPath, $compressionMethod);

        return $this;
    }

    /**
     * Add files recursively from glob pattern.
     *
     * @param string      $inputDir          Input directory
     * @param string      $globPattern       glob pattern
     * @param string|null $localPath         add files to this directory, or the root
     * @param int|null    $compressionMethod Compression method.
     *                                       Use {@see ZipFile::METHOD_STORED}, {@see ZipFile::METHOD_DEFLATED} or
     *                                       {@see ZipFile::METHOD_BZIP2}. If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function addFilesFromGlobRecursive($inputDir, $globPattern, $localPath = '/', $compressionMethod = null)
    {
        return $this->addGlob($inputDir, $globPattern, $localPath, true, $compressionMethod);
    }

    /**
     * Add files from regex pattern.
     *
     * @param string      $inputDir          search files in this directory
     * @param string      $regexPattern      regex pattern
     * @param string|null $localPath         add files to this directory, or the root
     * @param int|null    $compressionMethod Compression method.
     *                                       Use {@see ZipFile::METHOD_STORED}, {@see ZipFile::METHOD_DEFLATED} or
     *                                       {@see ZipFile::METHOD_BZIP2}. If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @internal param bool $recursive Recursive search
     */
    public function addFilesFromRegex($inputDir, $regexPattern, $localPath = '/', $compressionMethod = null)
    {
        return $this->addRegex($inputDir, $regexPattern, $localPath, false, $compressionMethod);
    }

    /**
     * Add files from regex pattern.
     *
     * @param string      $inputDir          search files in this directory
     * @param string      $regexPattern      regex pattern
     * @param string|null $localPath         add files to this directory, or the root
     * @param bool        $recursive         recursive search
     * @param int|null    $compressionMethod Compression method.
     *                                       Use {@see ZipFile::METHOD_STORED}, {@see ZipFile::METHOD_DEFLATED} or
     *                                       {@see ZipFile::METHOD_BZIP2}. If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    private function addRegex(
        $inputDir,
        $regexPattern,
        $localPath = '/',
        $recursive = true,
        $compressionMethod = null
    ) {
        $regexPattern = (string) $regexPattern;

        if (empty($regexPattern)) {
            throw new InvalidArgumentException('The regex pattern is not specified');
        }
        $inputDir = (string) $inputDir;

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
     * @param string   $fileSystemDir
     * @param array    $files
     * @param string   $zipPath
     * @param int|null $compressionMethod
     *
     * @throws ZipException
     */
    private function doAddFiles($fileSystemDir, array $files, $zipPath, $compressionMethod = null)
    {
        $fileSystemDir = rtrim($fileSystemDir, '/\\') . \DIRECTORY_SEPARATOR;

        if (!empty($zipPath) && \is_string($zipPath)) {
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
     * @param string      $inputDir          search files in this directory
     * @param string      $regexPattern      regex pattern
     * @param string|null $localPath         add files to this directory, or the root
     * @param int|null    $compressionMethod Compression method.
     *                                       Use {@see ZipFile::METHOD_STORED}, {@see ZipFile::METHOD_DEFLATED} or
     *                                       {@see ZipFile::METHOD_BZIP2}. If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @internal param bool $recursive Recursive search
     */
    public function addFilesFromRegexRecursive($inputDir, $regexPattern, $localPath = '/', $compressionMethod = null)
    {
        return $this->addRegex($inputDir, $regexPattern, $localPath, true, $compressionMethod);
    }

    /**
     * Add array data to archive.
     * Keys is local names.
     * Values is contents.
     *
     * @param array $mapData associative array for added to zip
     */
    public function addAll(array $mapData)
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
    public function rename($oldName, $newName)
    {
        if ($oldName === null || $newName === null) {
            throw new InvalidArgumentException('name is null');
        }
        $oldName = ltrim((string) $oldName, '\\/');
        $newName = ltrim((string) $newName, '\\/');

        if ($oldName !== $newName) {
            $this->zipModel->renameEntry($oldName, $newName);
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
    public function deleteFromName($entryName)
    {
        $entryName = ltrim((string) $entryName, '\\/');

        if (!$this->zipModel->deleteEntry($entryName)) {
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
    public function deleteFromGlob($globPattern)
    {
        if ($globPattern === null || !\is_string($globPattern) || empty($globPattern)) {
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
    public function deleteFromRegex($regexPattern)
    {
        if ($regexPattern === null || !\is_string($regexPattern) || empty($regexPattern)) {
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
    public function deleteAll()
    {
        $this->zipModel->deleteAll();

        return $this;
    }

    /**
     * Set compression level for new entries.
     *
     * @param int $compressionLevel
     *
     * @return ZipFile
     *
     * @see ZipFile::LEVEL_DEFAULT_COMPRESSION
     * @see ZipFile::LEVEL_SUPER_FAST
     * @see ZipFile::LEVEL_FAST
     * @see ZipFile::LEVEL_BEST_COMPRESSION
     */
    public function setCompressionLevel($compressionLevel = self::LEVEL_DEFAULT_COMPRESSION)
    {
        if ($compressionLevel < self::LEVEL_DEFAULT_COMPRESSION ||
            $compressionLevel > self::LEVEL_BEST_COMPRESSION
        ) {
            throw new InvalidArgumentException(
                'Invalid compression level. Minimum level ' .
                self::LEVEL_DEFAULT_COMPRESSION . '. Maximum level ' . self::LEVEL_BEST_COMPRESSION
            );
        }
        $this->matcher()->all()->invoke(
            function ($entry) use ($compressionLevel) {
                $this->setCompressionLevelEntry($entry, $compressionLevel);
            }
        );

        return $this;
    }

    /**
     * @param string $entryName
     * @param int    $compressionLevel
     *
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @see ZipFile::LEVEL_DEFAULT_COMPRESSION
     * @see ZipFile::LEVEL_SUPER_FAST
     * @see ZipFile::LEVEL_FAST
     * @see ZipFile::LEVEL_BEST_COMPRESSION
     */
    public function setCompressionLevelEntry($entryName, $compressionLevel)
    {
        if ($compressionLevel !== null) {
            if ($compressionLevel < self::LEVEL_DEFAULT_COMPRESSION ||
                $compressionLevel > self::LEVEL_BEST_COMPRESSION
            ) {
                throw new InvalidArgumentException(
                    'Invalid compression level. Minimum level ' .
                    self::LEVEL_DEFAULT_COMPRESSION . '. Maximum level ' . self::LEVEL_BEST_COMPRESSION
                );
            }
            $entry = $this->zipModel->getEntry($entryName);

            if ($compressionLevel !== $entry->getCompressionLevel()) {
                $entry = $this->zipModel->getEntryForChanges($entry);
                $entry->setCompressionLevel($compressionLevel);
            }
        }

        return $this;
    }

    /**
     * @param string $entryName
     * @param int    $compressionMethod Compression method.
     *                                  Use {@see ZipFile::METHOD_STORED}, {@see ZipFile::METHOD_DEFLATED} or
     *                                  {@see ZipFile::METHOD_BZIP2}. If null, then auto choosing method.
     *
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @see ZipFile::METHOD_STORED
     * @see ZipFile::METHOD_DEFLATED
     * @see ZipFile::METHOD_BZIP2
     */
    public function setCompressionMethodEntry($entryName, $compressionMethod)
    {
        if (!\in_array($compressionMethod, self::$allowCompressionMethods, true)) {
            throw new ZipUnsupportMethodException('Unsupported method ' . $compressionMethod);
        }
        $entry = $this->zipModel->getEntry($entryName);

        if ($compressionMethod !== $entry->getMethod()) {
            $this->zipModel
                ->getEntryForChanges($entry)
                ->setMethod($compressionMethod)
            ;
        }

        return $this;
    }

    /**
     * zipalign is optimization to Android application (APK) files.
     *
     * @param int|null $align
     *
     * @return ZipFile
     *
     * @see https://developer.android.com/studio/command-line/zipalign.html
     */
    public function setZipAlign($align = null)
    {
        $this->zipModel->setZipAlign($align);

        return $this;
    }

    /**
     * Set password to all input encrypted entries.
     *
     * @param string $password Password
     *
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @deprecated using ZipFile::setReadPassword()
     */
    public function withReadPassword($password)
    {
        return $this->setReadPassword($password);
    }

    /**
     * Set password to all input encrypted entries.
     *
     * @param string $password Password
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function setReadPassword($password)
    {
        $this->zipModel->setReadPassword($password);

        return $this;
    }

    /**
     * Set password to concrete input entry.
     *
     * @param string $entryName
     * @param string $password  Password
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function setReadPasswordEntry($entryName, $password)
    {
        $this->zipModel->setReadPasswordEntry($entryName, $password);

        return $this;
    }

    /**
     * Set password for all entries for update.
     *
     * @param string   $password         If password null then encryption clear
     * @param int|null $encryptionMethod Encryption method
     *
     * @throws ZipException
     *
     * @return ZipFile
     *
     * @deprecated using ZipFile::setPassword()
     */
    public function withNewPassword($password, $encryptionMethod = self::ENCRYPTION_METHOD_WINZIP_AES_256)
    {
        return $this->setPassword($password, $encryptionMethod);
    }

    /**
     * Sets a new password for all files in the archive.
     *
     * @param string   $password
     * @param int|null $encryptionMethod Encryption method
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function setPassword($password, $encryptionMethod = self::ENCRYPTION_METHOD_WINZIP_AES_256)
    {
        $this->zipModel->setWritePassword($password);

        if ($encryptionMethod !== null) {
            if (!\in_array($encryptionMethod, self::$allowEncryptionMethods, true)) {
                throw new ZipException('Invalid encryption method "' . $encryptionMethod . '"');
            }
            $this->zipModel->setEncryptionMethod($encryptionMethod);
        }

        return $this;
    }

    /**
     * Sets a new password of an entry defined by its name.
     *
     * @param string   $entryName
     * @param string   $password
     * @param int|null $encryptionMethod
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function setPasswordEntry($entryName, $password, $encryptionMethod = null)
    {
        if ($encryptionMethod !== null && !\in_array($encryptionMethod, self::$allowEncryptionMethods, true)) {
            throw new ZipException('Invalid encryption method "' . $encryptionMethod . '"');
        }
        $this->matcher()->add($entryName)->setPassword($password, $encryptionMethod);

        return $this;
    }

    /**
     * Remove password for all entries for update.
     *
     * @return ZipFile
     *
     * @deprecated using ZipFile::disableEncryption()
     */
    public function withoutPassword()
    {
        return $this->disableEncryption();
    }

    /**
     * Disable encryption for all entries that are already in the archive.
     *
     * @return ZipFile
     */
    public function disableEncryption()
    {
        $this->zipModel->removePassword();

        return $this;
    }

    /**
     * Disable encryption of an entry defined by its name.
     *
     * @param string $entryName
     *
     * @return ZipFile
     */
    public function disableEncryptionEntry($entryName)
    {
        $this->zipModel->removePasswordEntry($entryName);

        return $this;
    }

    /**
     * Undo all changes done in the archive.
     *
     * @return ZipFile
     */
    public function unchangeAll()
    {
        $this->zipModel->unchangeAll();

        return $this;
    }

    /**
     * Undo change archive comment.
     *
     * @return ZipFile
     */
    public function unchangeArchiveComment()
    {
        $this->zipModel->unchangeArchiveComment();

        return $this;
    }

    /**
     * Revert all changes done to an entry with the given name.
     *
     * @param string|ZipEntry $entry Entry name or ZipEntry
     *
     * @return ZipFile
     */
    public function unchangeEntry($entry)
    {
        $this->zipModel->unchangeEntry($entry);

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
    public function saveAsFile($filename)
    {
        $filename = (string) $filename;

        $tempFilename = $filename . '.temp' . uniqid('', true);

        if (!($handle = @fopen($tempFilename, 'w+b'))) {
            throw new InvalidArgumentException('File ' . $tempFilename . ' can not open from write.');
        }
        $this->saveAsStream($handle);

        if (!@rename($tempFilename, $filename)) {
            if (is_file($tempFilename)) {
                unlink($tempFilename);
            }

            throw new ZipException('Can not move ' . $tempFilename . ' to ' . $filename);
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
    public function saveAsStream($handle)
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
    public function outputAsAttachment($outputFilename, $mimeType = null, $attachment = true)
    {
        $outputFilename = (string) $outputFilename;

        if ($mimeType === null) {
            $mimeType = $this->getMimeTypeByFilename($outputFilename);
        }

        if (!($handle = fopen('php://temp', 'w+b'))) {
            throw new InvalidArgumentException('php://temp cannot open for write.');
        }
        $this->writeZipToStream($handle);
        $this->close();

        $size = fstat($handle)['size'];

        $headerContentDisposition = 'Content-Disposition: ' . ($attachment ? 'attachment' : 'inline');

        if (!empty($outputFilename)) {
            $headerContentDisposition .= '; filename="' . basename($outputFilename) . '"';
        }

        header($headerContentDisposition);
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $size);

        rewind($handle);

        try {
            echo stream_get_contents($handle, -1, 0);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param string $outputFilename
     *
     * @return string
     */
    protected function getMimeTypeByFilename($outputFilename)
    {
        $outputFilename = (string) $outputFilename;
        $ext = strtolower(pathinfo($outputFilename, \PATHINFO_EXTENSION));

        if (!empty($ext) && isset(self::$defaultMimeTypes[$ext])) {
            return self::$defaultMimeTypes[$ext];
        }

        return self::$defaultMimeTypes['zip'];
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
     * @return ResponseInterface
     */
    public function outputAsResponse(ResponseInterface $response, $outputFilename, $mimeType = null, $attachment = true)
    {
        $outputFilename = (string) $outputFilename;

        if ($mimeType === null) {
            $mimeType = $this->getMimeTypeByFilename($outputFilename);
        }

        if (!($handle = fopen('php://temp', 'w+b'))) {
            throw new InvalidArgumentException('php://temp cannot open for write.');
        }
        $this->writeZipToStream($handle);
        $this->close();
        rewind($handle);

        $contentDispositionValue = ($attachment ? 'attachment' : 'inline');

        if (!empty($outputFilename)) {
            $contentDispositionValue .= '; filename="' . basename($outputFilename) . '"';
        }

        $stream = new ResponseStream($handle);

        return $response
            ->withHeader('Content-Type', $mimeType)
            ->withHeader('Content-Disposition', $contentDispositionValue)
            ->withHeader('Content-Length', $stream->getSize())
            ->withBody($stream)
        ;
    }

    /**
     * @param resource $handle
     *
     * @throws ZipException
     */
    protected function writeZipToStream($handle)
    {
        $this->onBeforeSave();

        $output = new ZipOutputStream($handle, $this->zipModel);
        $output->writeZip();
    }

    /**
     * Returns the zip archive as a string.
     *
     * @throws ZipException
     *
     * @return string
     */
    public function outputAsString()
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
    protected function onBeforeSave()
    {
    }

    /**
     * Close zip archive and release input stream.
     */
    public function close()
    {
        if ($this->inputStream !== null) {
            $this->inputStream->close();
            $this->inputStream = null;
            $this->zipModel = new ZipModel();
        }
    }

    /**
     * Save and reopen zip archive.
     *
     * @throws ZipException
     *
     * @return ZipFile
     */
    public function rewrite()
    {
        if ($this->inputStream === null) {
            throw new ZipException('input stream is null');
        }
        $meta = stream_get_meta_data($this->inputStream->getStream());
        $content = $this->outputAsString();
        $this->close();

        if ($meta['wrapper_type'] === 'plainfile') {
            /**
             * @var resource $uri
             */
            $uri = $meta['uri'];

            if (file_put_contents($uri, $content) === false) {
                throw new ZipException("Can not overwrite the zip file in the {$uri} file.");
            }

            if (!($handle = @fopen($uri, 'rb'))) {
                throw new ZipException("File {$uri} can't open.");
            }

            return $this->openFromStream($handle);
        }

        return $this->openFromString($content);
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
     * @param string $entryName the offset to assign the value to
     * @param mixed  $contents  the value to set
     *
     * @throws ZipException
     *
     * @see ZipFile::addFromString
     * @see ZipFile::addEmptyDir
     * @see ZipFile::addFile
     * @see ZipFile::addFilesFromIterator
     */
    public function offsetSet($entryName, $contents)
    {
        if ($entryName === null) {
            throw new InvalidArgumentException('entryName is null');
        }
        $entryName = ltrim((string) $entryName, '\\/');

        if ($entryName === '') {
            throw new InvalidArgumentException('entryName is empty');
        }

        if ($contents instanceof \SplFileInfo) {
            if ($contents instanceof \DirectoryIterator) {
                $this->addFilesFromIterator($contents, $entryName);

                return;
            }
            $this->addFile($contents->getPathname(), $entryName);

            return;
        }

        if (StringUtil::endsWith($entryName, '/')) {
            $this->addEmptyDir($entryName);
        } elseif (\is_resource($contents)) {
            $this->addFromStream($contents, $entryName);
        } else {
            $this->addFromString($entryName, (string) $contents);
        }
    }

    /**
     * Offset to unset.
     *
     * @see http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param string $entryName the offset to unset
     *
     * @throws ZipEntryNotFoundException
     */
    public function offsetUnset($entryName)
    {
        $this->deleteFromName($entryName);
    }

    /**
     * Return the current element.
     *
     * @see http://php.net/manual/en/iterator.current.php
     *
     * @throws ZipException
     *
     * @return mixed can return any type
     *
     * @since 5.0.0
     */
    public function current()
    {
        return $this->offsetGet($this->key());
    }

    /**
     * Offset to retrieve.
     *
     * @see http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param string $entryName the offset to retrieve
     *
     * @throws ZipException
     *
     * @return string|null
     */
    public function offsetGet($entryName)
    {
        return $this->getEntryContents($entryName);
    }

    /**
     * Return the key of the current element.
     *
     * @see http://php.net/manual/en/iterator.key.php
     *
     * @return mixed scalar on success, or null on failure
     *
     * @since 5.0.0
     */
    public function key()
    {
        return key($this->zipModel->getEntries());
    }

    /**
     * Move forward to next element.
     *
     * @see http://php.net/manual/en/iterator.next.php
     * @since 5.0.0
     */
    public function next()
    {
        next($this->zipModel->getEntries());
    }

    /**
     * Checks if current position is valid.
     *
     * @see http://php.net/manual/en/iterator.valid.php
     *
     * @return bool The return value will be casted to boolean and then evaluated.
     *              Returns true on success or false on failure.
     *
     * @since 5.0.0
     */
    public function valid()
    {
        return $this->offsetExists($this->key());
    }

    /**
     * Whether a offset exists.
     *
     * @see http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param string $entryName an offset to check for
     *
     * @return bool true on success or false on failure.
     *              The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($entryName)
    {
        return $this->hasEntry($entryName);
    }

    /**
     * Rewind the Iterator to the first element.
     *
     * @see http://php.net/manual/en/iterator.rewind.php
     * @since 5.0.0
     */
    public function rewind()
    {
        reset($this->zipModel->getEntries());
    }
}
