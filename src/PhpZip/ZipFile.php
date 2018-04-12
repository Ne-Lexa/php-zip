<?php

namespace PhpZip;

use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipException;
use PhpZip\Exception\ZipNotFoundEntry;
use PhpZip\Exception\ZipUnsupportMethod;
use PhpZip\Model\Entry\ZipNewEntry;
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
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipFile implements ZipFileInterface
{
    /**
     * Allow compression methods.
     * @var int[]
     */
    private static $allowCompressionMethods = [
        self::METHOD_STORED,
        self::METHOD_DEFLATED,
        self::METHOD_BZIP2,
        ZipEntry::UNKNOWN
    ];

    /**
     * Allow encryption methods.
     * @var int[]
     */
    private static $allowEncryptionMethods = [
        self::ENCRYPTION_METHOD_TRADITIONAL,
        self::ENCRYPTION_METHOD_WINZIP_AES_128,
        self::ENCRYPTION_METHOD_WINZIP_AES_192,
        self::ENCRYPTION_METHOD_WINZIP_AES_256
    ];

    /**
     * Default mime types.
     *
     * @var array
     */
    private static $defaultMimeTypes = [
        'zip' => 'application/zip',
        'apk' => 'application/vnd.android.package-archive',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jar' => 'application/java-archive',
        'epub' => 'application/epub+zip'
    ];

    /**
     * Input seekable input stream.
     *
     * @var ZipInputStreamInterface
     */
    protected $inputStream;
    /**
     * @var ZipModel
     */
    protected $zipModel;

    /**
     * ZipFile constructor.
     */
    public function __construct()
    {
        $this->zipModel = new ZipModel();
    }

    /**
     * Open zip archive from file
     *
     * @param string $filename
     * @return ZipFileInterface
     * @throws InvalidArgumentException if file doesn't exists.
     * @throws ZipException             if can't open file.
     */
    public function openFile($filename)
    {
        if (!file_exists($filename)) {
            throw new InvalidArgumentException("File $filename can't exists.");
        }
        if (!($handle = @fopen($filename, 'rb'))) {
            throw new ZipException("File $filename can't open.");
        }
        $this->openFromStream($handle);
        return $this;
    }

    /**
     * Open zip archive from raw string data.
     *
     * @param string $data
     * @return ZipFileInterface
     * @throws InvalidArgumentException if data not available.
     * @throws ZipException             if can't open temp stream.
     */
    public function openFromString($data)
    {
        if (null === $data || strlen($data) === 0) {
            throw new InvalidArgumentException("Data not available");
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
     * Open zip archive from stream resource
     *
     * @param resource $handle
     * @return ZipFileInterface
     * @throws InvalidArgumentException Invalid stream resource
     *         or resource cannot seekable stream
     */
    public function openFromStream($handle)
    {
        if (!is_resource($handle)) {
            throw new InvalidArgumentException("Invalid stream resource.");
        }
        $type = get_resource_type($handle);
        if ('stream' !== $type) {
            throw new InvalidArgumentException("Invalid resource type - $type.");
        }
        $meta = stream_get_meta_data($handle);
        if ('dir' === $meta['stream_type']) {
            throw new InvalidArgumentException("Invalid stream type - {$meta['stream_type']}.");
        }
        if (!$meta['seekable']) {
            throw new InvalidArgumentException("Resource cannot seekable stream.");
        }
        $this->inputStream = new ZipInputStream($handle);
        $this->zipModel = $this->inputStream->readZip();
        return $this;
    }

    /**
     * @return string[] Returns the list files.
     */
    public function getListFiles()
    {
        return array_keys($this->zipModel->getEntries());
    }

    /**
     * @return int Returns the number of entries in this ZIP file.
     */
    public function count()
    {
        return $this->zipModel->count();
    }

    /**
     * Returns the file comment.
     *
     * @return string The file comment.
     */
    public function getArchiveComment()
    {
        return $this->zipModel->getArchiveComment();
    }

    /**
     * Set archive comment.
     *
     * @param null|string $comment
     * @return ZipFileInterface
     * @throws InvalidArgumentException Length comment out of range
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
     * @return bool
     * @throws ZipNotFoundEntry
     */
    public function isDirectory($entryName)
    {
        return $this->zipModel->getEntry($entryName)->isDirectory();
    }

    /**
     * Returns entry comment.
     *
     * @param string $entryName
     * @return string
     * @throws ZipNotFoundEntry
     */
    public function getEntryComment($entryName)
    {
        return $this->zipModel->getEntry($entryName)->getComment();
    }

    /**
     * Set entry comment.
     *
     * @param string $entryName
     * @param string|null $comment
     * @return ZipFileInterface
     * @throws ZipNotFoundEntry
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
     * @return ZipInfo
     * @throws ZipNotFoundEntry
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
     * Extract the archive contents
     *
     * Extract the complete archive or the given files to the specified destination.
     *
     * @param string $destination Location where to extract the files.
     * @param array|string|null $entries The entries to extract. It accepts either
     *                                   a single entry name or an array of names.
     * @return ZipFileInterface
     * @throws ZipException
     */
    public function extractTo($destination, $entries = null)
    {
        if (!file_exists($destination)) {
            throw new ZipException("Destination " . $destination . " not found");
        }
        if (!is_dir($destination)) {
            throw new ZipException("Destination is not directory");
        }
        if (!is_writable($destination)) {
            throw new ZipException("Destination is not writable directory");
        }

        $zipEntries = $this->zipModel->getEntries();

        if (!empty($entries)) {
            if (is_string($entries)) {
                $entries = (array)$entries;
            }
            if (is_array($entries)) {
                $entries = array_unique($entries);
                $flipEntries = array_flip($entries);
                $zipEntries = array_filter($zipEntries, function (ZipEntry $zipEntry) use ($flipEntries) {
                    return isset($flipEntries[$zipEntry->getName()]);
                });
            }
        }

        foreach ($zipEntries as $entry) {
            $file = $destination . DIRECTORY_SEPARATOR . $entry->getName();
            if ($entry->isDirectory()) {
                if (!is_dir($file)) {
                    if (!mkdir($file, 0755, true)) {
                        throw new ZipException("Can not create dir " . $file);
                    }
                    chmod($file, 0755);
                    touch($file, $entry->getTime());
                }
                continue;
            }
            $dir = dirname($file);
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new ZipException("Can not create dir " . $dir);
                }
                chmod($dir, 0755);
                touch($dir, $entry->getTime());
            }
            if (false === file_put_contents($file, $entry->getEntryContent())) {
                throw new ZipException('Can not extract file ' . $entry->getName());
            }
            touch($file, $entry->getTime());
        }
        return $this;
    }

    /**
     * Add entry from the string.
     *
     * @param string $localName Zip entry name.
     * @param string $contents String contents.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @throws InvalidArgumentException If incorrect data or entry name.
     * @throws ZipUnsupportMethod
     * @see ZipFileInterface::METHOD_STORED
     * @see ZipFileInterface::METHOD_DEFLATED
     * @see ZipFileInterface::METHOD_BZIP2
     */
    public function addFromString($localName, $contents, $compressionMethod = null)
    {
        if (null === $contents) {
            throw new InvalidArgumentException("Contents is null");
        }
        $localName = (string)$localName;
        if (null === $localName || 0 === strlen($localName)) {
            throw new InvalidArgumentException("Incorrect entry name " . $localName);
        }
        $contents = (string)$contents;
        $length = strlen($contents);
        if (null === $compressionMethod) {
            if ($length >= 512) {
                $compressionMethod = ZipEntry::UNKNOWN;
            } else {
                $compressionMethod = ZipFileInterface::METHOD_STORED;
            }
        } elseif (!in_array($compressionMethod, self::$allowCompressionMethods, true)) {
            throw new ZipUnsupportMethod('Unsupported compression method ' . $compressionMethod);
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
     * @param string $filename Destination file.
     * @param string|null $localName Zip Entry name.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @throws InvalidArgumentException
     * @throws ZipUnsupportMethod
     * @see ZipFileInterface::METHOD_STORED
     * @see ZipFileInterface::METHOD_DEFLATED
     * @see ZipFileInterface::METHOD_BZIP2
     */
    public function addFile($filename, $localName = null, $compressionMethod = null)
    {
        if (null === $filename) {
            throw new InvalidArgumentException("Filename is null");
        }
        if (!is_file($filename)) {
            throw new InvalidArgumentException("File $filename is not exists");
        }

        if (null === $compressionMethod) {
            if (function_exists('mime_content_type')) {
                $mimeType = @mime_content_type($filename);
                $type = strtok($mimeType, '/');
                if ('image' === $type) {
                    $compressionMethod = ZipFileInterface::METHOD_STORED;
                } elseif ('text' === $type && filesize($filename) < 150) {
                    $compressionMethod = ZipFileInterface::METHOD_STORED;
                } else {
                    $compressionMethod = ZipEntry::UNKNOWN;
                }
            } elseif (@filesize($filename) >= 512) {
                $compressionMethod = ZipEntry::UNKNOWN;
            } else {
                $compressionMethod = ZipFileInterface::METHOD_STORED;
            }
        } elseif (!in_array($compressionMethod, self::$allowCompressionMethods, true)) {
            throw new ZipUnsupportMethod('Unsupported method ' . $compressionMethod);
        }

        if (!($handle = @fopen($filename, 'rb'))) {
            throw new InvalidArgumentException('File ' . $filename . ' can not open.');
        }
        if (null === $localName) {
            $localName = basename($filename);
        }
        $this->addFromStream($handle, $localName, $compressionMethod);
        $this->zipModel->getEntry($localName)->setTime(filemtime($filename));
        return $this;
    }

    /**
     * Add entry from the stream.
     *
     * @param resource $stream Stream resource.
     * @param string $localName Zip Entry name.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @throws InvalidArgumentException
     * @throws ZipUnsupportMethod
     * @see ZipFileInterface::METHOD_STORED
     * @see ZipFileInterface::METHOD_DEFLATED
     * @see ZipFileInterface::METHOD_BZIP2
     */
    public function addFromStream($stream, $localName, $compressionMethod = null)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException("stream is not resource");
        }
        $localName = (string)$localName;
        if (empty($localName)) {
            throw new InvalidArgumentException("Incorrect entry name " . $localName);
        }
        $fstat = fstat($stream);
        $length = $fstat['size'];
        if (null === $compressionMethod) {
            if ($length >= 512) {
                $compressionMethod = ZipEntry::UNKNOWN;
            } else {
                $compressionMethod = ZipFileInterface::METHOD_STORED;
            }
        } elseif (!in_array($compressionMethod, self::$allowCompressionMethods, true)) {
            throw new ZipUnsupportMethod('Unsupported method ' . $compressionMethod);
        }

        $mode = sprintf('%o', $fstat['mode']);
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
     * @return ZipFileInterface
     * @throws InvalidArgumentException
     */
    public function addEmptyDir($dirName)
    {
        $dirName = (string)$dirName;
        if (strlen($dirName) === 0) {
            throw new InvalidArgumentException("DirName empty");
        }
        $dirName = rtrim($dirName, '/') . '/';
        $externalAttributes = 040755 << 16;

        $entry = new ZipNewEntry();
        $entry->setName($dirName);
        $entry->setTime(time());
        $entry->setMethod(ZipFileInterface::METHOD_STORED);
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
     * @param string $inputDir Input directory
     * @param string $localPath Add files to this directory, or the root.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @throws InvalidArgumentException
     */
    public function addDir($inputDir, $localPath = "/", $compressionMethod = null)
    {
        $inputDir = (string)$inputDir;
        if (null === $inputDir || strlen($inputDir) === 0) {
            throw new InvalidArgumentException('Input dir empty');
        }
        if (!is_dir($inputDir)) {
            throw new InvalidArgumentException('Directory ' . $inputDir . ' can\'t exists');
        }
        $inputDir = rtrim($inputDir, '/\\') . DIRECTORY_SEPARATOR;

        $directoryIterator = new \DirectoryIterator($inputDir);
        return $this->addFilesFromIterator($directoryIterator, $localPath, $compressionMethod);
    }

    /**
     * Add recursive directory to the zip archive.
     *
     * @param string $inputDir Input directory
     * @param string $localPath Add files to this directory, or the root.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @throws InvalidArgumentException
     * @throws ZipUnsupportMethod
     * @see ZipFileInterface::METHOD_STORED
     * @see ZipFileInterface::METHOD_DEFLATED
     * @see ZipFileInterface::METHOD_BZIP2
     */
    public function addDirRecursive($inputDir, $localPath = "/", $compressionMethod = null)
    {
        $inputDir = (string)$inputDir;
        if (null === $inputDir || strlen($inputDir) === 0) {
            throw new InvalidArgumentException('Input dir empty');
        }
        if (!is_dir($inputDir)) {
            throw new InvalidArgumentException('Directory ' . $inputDir . ' can\'t exists');
        }
        $inputDir = rtrim($inputDir, '/\\') . DIRECTORY_SEPARATOR;

        $directoryIterator = new \RecursiveDirectoryIterator($inputDir);
        return $this->addFilesFromIterator($directoryIterator, $localPath, $compressionMethod);
    }

    /**
     * Add directories from directory iterator.
     *
     * @param \Iterator $iterator Directory iterator.
     * @param string $localPath Add files to this directory, or the root.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @throws InvalidArgumentException
     * @throws ZipUnsupportMethod
     * @see ZipFileInterface::METHOD_STORED
     * @see ZipFileInterface::METHOD_DEFLATED
     * @see ZipFileInterface::METHOD_BZIP2
     */
    public function addFilesFromIterator(
        \Iterator $iterator,
        $localPath = '/',
        $compressionMethod = null
    ) {
        $localPath = (string)$localPath;
        if (null !== $localPath && 0 !== strlen($localPath)) {
            $localPath = rtrim($localPath, '/');
        } else {
            $localPath = "";
        }

        $iterator = $iterator instanceof \RecursiveIterator ?
            new \RecursiveIteratorIterator($iterator) :
            new \IteratorIterator($iterator);
        /**
         * @var string[] $files
         * @var string $path
         */
        $files = [];
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo) {
                if ('..' === $file->getBasename()) {
                    continue;
                }
                if ('.' === $file->getBasename()) {
                    $files[] = dirname($file->getPathname());
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
        foreach ($files as $file) {
            $relativePath = str_replace($path, $localPath, $file);
            $relativePath = ltrim($relativePath, '/');
            if (is_dir($file)) {
                FilesUtil::isEmptyDir($file) && $this->addEmptyDir($relativePath);
            } elseif (is_file($file)) {
                $this->addFile($file, $relativePath, $compressionMethod);
            }
        }
        return $this;
    }

    /**
     * Add files from glob pattern.
     *
     * @param string $inputDir Input directory
     * @param string $globPattern Glob pattern.
     * @param string|null $localPath Add files to this directory, or the root.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @throws InvalidArgumentException
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function addFilesFromGlob($inputDir, $globPattern, $localPath = '/', $compressionMethod = null)
    {
        return $this->addGlob($inputDir, $globPattern, $localPath, false, $compressionMethod);
    }

    /**
     * Add files from glob pattern.
     *
     * @param string $inputDir Input directory
     * @param string $globPattern Glob pattern.
     * @param string|null $localPath Add files to this directory, or the root.
     * @param bool $recursive Recursive search.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @throws InvalidArgumentException
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    private function addGlob(
        $inputDir,
        $globPattern,
        $localPath = '/',
        $recursive = true,
        $compressionMethod = null
    ) {
        $inputDir = (string)$inputDir;
        if (null === $inputDir || 0 === strlen($inputDir)) {
            throw new InvalidArgumentException('Input dir empty');
        }
        if (!is_dir($inputDir)) {
            throw new InvalidArgumentException('Directory ' . $inputDir . ' can\'t exists');
        }
        $globPattern = (string)$globPattern;
        if (empty($globPattern)) {
            throw new InvalidArgumentException("glob pattern empty");
        }

        $inputDir = rtrim($inputDir, '/\\') . DIRECTORY_SEPARATOR;
        $globPattern = $inputDir . $globPattern;

        $filesFound = FilesUtil::globFileSearch($globPattern, GLOB_BRACE, $recursive);
        if (false === $filesFound || empty($filesFound)) {
            return $this;
        }
        if (!empty($localPath) && is_string($localPath)) {
            $localPath = rtrim($localPath, '/') . '/';
        } else {
            $localPath = "/";
        }

        /**
         * @var string $file
         */
        foreach ($filesFound as $file) {
            $filename = str_replace($inputDir, $localPath, $file);
            $filename = ltrim($filename, '/');
            if (is_dir($file)) {
                FilesUtil::isEmptyDir($file) && $this->addEmptyDir($filename);
            } elseif (is_file($file)) {
                $this->addFile($file, $filename, $compressionMethod);
            }
        }
        return $this;
    }

    /**
     * Add files recursively from glob pattern.
     *
     * @param string $inputDir Input directory
     * @param string $globPattern Glob pattern.
     * @param string|null $localPath Add files to this directory, or the root.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @throws InvalidArgumentException
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function addFilesFromGlobRecursive($inputDir, $globPattern, $localPath = '/', $compressionMethod = null)
    {
        return $this->addGlob($inputDir, $globPattern, $localPath, true, $compressionMethod);
    }

    /**
     * Add files from regex pattern.
     *
     * @param string $inputDir Search files in this directory.
     * @param string $regexPattern Regex pattern.
     * @param string|null $localPath Add files to this directory, or the root.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @internal param bool $recursive Recursive search.
     */
    public function addFilesFromRegex($inputDir, $regexPattern, $localPath = "/", $compressionMethod = null)
    {
        return $this->addRegex($inputDir, $regexPattern, $localPath, false, $compressionMethod);
    }

    /**
     * Add files from regex pattern.
     *
     * @param string $inputDir Search files in this directory.
     * @param string $regexPattern Regex pattern.
     * @param string|null $localPath Add files to this directory, or the root.
     * @param bool $recursive Recursive search.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @throws InvalidArgumentException
     */
    private function addRegex(
        $inputDir,
        $regexPattern,
        $localPath = "/",
        $recursive = true,
        $compressionMethod = null
    ) {
        $regexPattern = (string)$regexPattern;
        if (empty($regexPattern)) {
            throw new InvalidArgumentException("regex pattern empty");
        }
        $inputDir = (string)$inputDir;
        if (null === $inputDir || 0 === strlen($inputDir)) {
            throw new InvalidArgumentException('Input dir empty');
        }
        if (!is_dir($inputDir)) {
            throw new InvalidArgumentException('Directory ' . $inputDir . ' can\'t exists');
        }
        $inputDir = rtrim($inputDir, '/\\') . DIRECTORY_SEPARATOR;

        $files = FilesUtil::regexFileSearch($inputDir, $regexPattern, $recursive);
        if (false === $files || empty($files)) {
            return $this;
        }
        if (!empty($localPath) && is_string($localPath)) {
            $localPath = rtrim($localPath, '/') . '/';
        } else {
            $localPath = "/";
        }
        $inputDir = rtrim($inputDir, '/\\') . DIRECTORY_SEPARATOR;

        /**
         * @var string $file
         */
        foreach ($files as $file) {
            $filename = str_replace($inputDir, $localPath, $file);
            $filename = ltrim($filename, '/');
            if (is_dir($file)) {
                FilesUtil::isEmptyDir($file) && $this->addEmptyDir($filename);
            } elseif (is_file($file)) {
                $this->addFile($file, $filename, $compressionMethod);
            }
        }
        return $this;
    }

    /**
     * Add files recursively from regex pattern.
     *
     * @param string $inputDir Search files in this directory.
     * @param string $regexPattern Regex pattern.
     * @param string|null $localPath Add files to this directory, or the root.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @internal param bool $recursive Recursive search.
     */
    public function addFilesFromRegexRecursive($inputDir, $regexPattern, $localPath = "/", $compressionMethod = null)
    {
        return $this->addRegex($inputDir, $regexPattern, $localPath, true, $compressionMethod);
    }

    /**
     * Add array data to archive.
     * Keys is local names.
     * Values is contents.
     *
     * @param array $mapData Associative array for added to zip.
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
     * @param string $oldName Old entry name.
     * @param string $newName New entry name.
     * @return ZipFileInterface
     * @throws InvalidArgumentException
     * @throws ZipNotFoundEntry
     */
    public function rename($oldName, $newName)
    {
        if (null === $oldName || null === $newName) {
            throw new InvalidArgumentException("name is null");
        }
        if ($oldName !== $newName) {
            $this->zipModel->renameEntry($oldName, $newName);
        }
        return $this;
    }

    /**
     * Delete entry by name.
     *
     * @param string $entryName Zip Entry name.
     * @return ZipFileInterface
     * @throws ZipNotFoundEntry If entry not found.
     */
    public function deleteFromName($entryName)
    {
        $entryName = (string)$entryName;
        if (!$this->zipModel->deleteEntry($entryName)) {
            throw new ZipNotFoundEntry("Entry " . $entryName . ' not found!');
        }
        return $this;
    }

    /**
     * Delete entries by glob pattern.
     *
     * @param string $globPattern Glob pattern
     * @return ZipFileInterface
     * @throws InvalidArgumentException
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function deleteFromGlob($globPattern)
    {
        if (null === $globPattern || !is_string($globPattern) || empty($globPattern)) {
            throw new InvalidArgumentException("Glob pattern is empty");
        }
        $globPattern = '~' . FilesUtil::convertGlobToRegEx($globPattern) . '~si';
        $this->deleteFromRegex($globPattern);
        return $this;
    }

    /**
     * Delete entries by regex pattern.
     *
     * @param string $regexPattern Regex pattern
     * @return ZipFileInterface
     * @throws InvalidArgumentException
     */
    public function deleteFromRegex($regexPattern)
    {
        if (null === $regexPattern || !is_string($regexPattern) || empty($regexPattern)) {
            throw new InvalidArgumentException("Regex pattern is empty.");
        }
        $this->matcher()->match($regexPattern)->delete();
        return $this;
    }

    /**
     * Delete all entries
     * @return ZipFileInterface
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
     * @return ZipFileInterface
     * @throws InvalidArgumentException
     * @see ZipFileInterface::LEVEL_DEFAULT_COMPRESSION
     * @see ZipFileInterface::LEVEL_SUPER_FAST
     * @see ZipFileInterface::LEVEL_FAST
     * @see ZipFileInterface::LEVEL_BEST_COMPRESSION
     */
    public function setCompressionLevel($compressionLevel = ZipFileInterface::LEVEL_DEFAULT_COMPRESSION)
    {
        if ($compressionLevel < ZipFileInterface::LEVEL_DEFAULT_COMPRESSION ||
            $compressionLevel > ZipFileInterface::LEVEL_BEST_COMPRESSION
        ) {
            throw new InvalidArgumentException('Invalid compression level. Minimum level ' .
                ZipFileInterface::LEVEL_DEFAULT_COMPRESSION . '. Maximum level ' . ZipFileInterface::LEVEL_BEST_COMPRESSION);
        }
        $this->matcher()->all()->invoke(function ($entry) use ($compressionLevel) {
            $this->setCompressionLevelEntry($entry, $compressionLevel);
        });
        return $this;
    }

    /**
     * @param string $entryName
     * @param int $compressionLevel
     * @return ZipFileInterface
     * @throws ZipException
     * @see ZipFileInterface::LEVEL_DEFAULT_COMPRESSION
     * @see ZipFileInterface::LEVEL_SUPER_FAST
     * @see ZipFileInterface::LEVEL_FAST
     * @see ZipFileInterface::LEVEL_BEST_COMPRESSION
     */
    public function setCompressionLevelEntry($entryName, $compressionLevel)
    {
        if (null !== $compressionLevel) {
            if ($compressionLevel < ZipFileInterface::LEVEL_DEFAULT_COMPRESSION ||
                $compressionLevel > ZipFileInterface::LEVEL_BEST_COMPRESSION
            ) {
                throw new InvalidArgumentException('Invalid compression level. Minimum level ' .
                    ZipFileInterface::LEVEL_DEFAULT_COMPRESSION . '. Maximum level ' . ZipFileInterface::LEVEL_BEST_COMPRESSION);
            }
            $entry = $this->zipModel->getEntry($entryName);
            if ($entry->getCompressionLevel() !== $compressionLevel) {
                $entry = $this->zipModel->getEntryForChanges($entry);
                $entry->setCompressionLevel($compressionLevel);
            }
        }
        return $this;
    }

    /**
     * @param string $entryName
     * @param int $compressionMethod
     * @return ZipFileInterface
     * @throws ZipException
     * @see ZipFileInterface::METHOD_STORED
     * @see ZipFileInterface::METHOD_DEFLATED
     * @see ZipFileInterface::METHOD_BZIP2
     */
    public function setCompressionMethodEntry($entryName, $compressionMethod)
    {
        if (!in_array($compressionMethod, self::$allowCompressionMethods, true)) {
            throw new ZipUnsupportMethod('Unsupported method ' . $compressionMethod);
        }
        $entry = $this->zipModel->getEntry($entryName);
        if ($entry->getMethod() !== $compressionMethod) {
            $this->zipModel
                ->getEntryForChanges($entry)
                ->setMethod($compressionMethod);
        }
        return $this;
    }

    /**
     * zipalign is optimization to Android application (APK) files.
     *
     * @param int|null $align
     * @return ZipFileInterface
     * @link https://developer.android.com/studio/command-line/zipalign.html
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
     * @return ZipFileInterface
     * @deprecated using ZipFileInterface::setReadPassword()
     */
    public function withReadPassword($password)
    {
        return $this->setReadPassword($password);
    }

    /**
     * Set password to all input encrypted entries.
     *
     * @param string $password Password
     * @return ZipFileInterface
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
     * @param string $password Password
     * @return ZipFileInterface
     */
    public function setReadPasswordEntry($entryName, $password)
    {
        $this->zipModel->setReadPasswordEntry($entryName, $password);
        return $this;
    }

    /**
     * Set password for all entries for update.
     *
     * @param string $password If password null then encryption clear
     * @param int|null $encryptionMethod Encryption method
     * @return ZipFileInterface
     * @deprecated using ZipFileInterface::setPassword()
     */
    public function withNewPassword($password, $encryptionMethod = self::ENCRYPTION_METHOD_WINZIP_AES_256)
    {
        return $this->setPassword($password, $encryptionMethod);
    }

    /**
     * Sets a new password for all files in the archive.
     *
     * @param string $password
     * @param int|null $encryptionMethod Encryption method
     * @return ZipFileInterface
     * @throws ZipException
     */
    public function setPassword($password, $encryptionMethod = self::ENCRYPTION_METHOD_WINZIP_AES_256)
    {
        $this->zipModel->setWritePassword($password);
        if (null !== $encryptionMethod) {
            if (!in_array($encryptionMethod, self::$allowEncryptionMethods)) {
                throw new ZipException('Invalid encryption method');
            }
            $this->zipModel->setEncryptionMethod($encryptionMethod);
        }
        return $this;
    }

    /**
     * Sets a new password of an entry defined by its name.
     *
     * @param string $entryName
     * @param string $password
     * @param int|null $encryptionMethod
     * @return ZipFileInterface
     * @throws ZipException
     */
    public function setPasswordEntry($entryName, $password, $encryptionMethod = null)
    {
        if (null !== $encryptionMethod) {
            if (!in_array($encryptionMethod, self::$allowEncryptionMethods)) {
                throw new ZipException('Invalid encryption method');
            }
        }
        $this->matcher()->add($entryName)->setPassword($password, $encryptionMethod);
        return $this;
    }

    /**
     * Remove password for all entries for update.
     * @return ZipFileInterface
     * @deprecated using ZipFileInterface::disableEncryption()
     */
    public function withoutPassword()
    {
        return $this->disableEncryption();
    }

    /**
     * Disable encryption for all entries that are already in the archive.
     * @return ZipFileInterface
     */
    public function disableEncryption()
    {
        $this->zipModel->removePassword();
        return $this;
    }

    /**
     * Disable encryption of an entry defined by its name.
     * @param string $entryName
     * @return ZipFileInterface
     */
    public function disableEncryptionEntry($entryName)
    {
        $this->zipModel->removePasswordEntry($entryName);
        return $this;
    }

    /**
     * Undo all changes done in the archive
     * @return ZipFileInterface
     */
    public function unchangeAll()
    {
        $this->zipModel->unchangeAll();
        return $this;
    }

    /**
     * Undo change archive comment
     * @return ZipFileInterface
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
     * @return ZipFileInterface
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
     * @return ZipFileInterface
     * @throws InvalidArgumentException
     * @throws ZipException
     */
    public function saveAsFile($filename)
    {
        $filename = (string)$filename;

        $tempFilename = $filename . '.temp' . uniqid();
        if (!($handle = @fopen($tempFilename, 'w+b'))) {
            throw new InvalidArgumentException("File " . $tempFilename . ' can not open from write.');
        }
        $this->saveAsStream($handle);

        if (!@rename($tempFilename, $filename)) {
            throw new ZipException('Can not move ' . $tempFilename . ' to ' . $filename);
        }
        return $this;
    }

    /**
     * Save as stream.
     *
     * @param resource $handle Output stream resource
     * @return ZipFileInterface
     * @throws ZipException
     */
    public function saveAsStream($handle)
    {
        if (!is_resource($handle)) {
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
     * @param string $outputFilename Output filename
     * @param string|null $mimeType Mime-Type
     * @param bool $attachment Http Header 'Content-Disposition' if true then attachment otherwise inline
     * @throws InvalidArgumentException
     */
    public function outputAsAttachment($outputFilename, $mimeType = null, $attachment = true)
    {
        $outputFilename = (string)$outputFilename;

        if (empty($mimeType) || !is_string($mimeType) && !empty($outputFilename)) {
            $ext = strtolower(pathinfo($outputFilename, PATHINFO_EXTENSION));

            if (!empty($ext) && isset(self::$defaultMimeTypes[$ext])) {
                $mimeType = self::$defaultMimeTypes[$ext];
            }
        }
        if (empty($mimeType)) {
            $mimeType = self::$defaultMimeTypes['zip'];
        }

        $content = $this->outputAsString();
        $this->close();

        $headerContentDisposition = 'Content-Disposition: ' . ($attachment ? 'attachment' : 'inline');
        if (!empty($outputFilename)) {
            $headerContentDisposition .= '; filename="' . basename($outputFilename) . '"';
        }

        header($headerContentDisposition);
        header("Content-Type: " . $mimeType);
        header("Content-Length: " . strlen($content));
        exit($content);
    }

    /**
     * Output .ZIP archive as PSR-7 Response.
     *
     * @param ResponseInterface $response Instance PSR-7 Response
     * @param string $outputFilename Output filename
     * @param string|null $mimeType Mime-Type
     * @param bool $attachment Http Header 'Content-Disposition' if true then attachment otherwise inline
     * @return ResponseInterface
     * @throws InvalidArgumentException
     */
    public function outputAsResponse(ResponseInterface $response, $outputFilename, $mimeType = null, $attachment = true)
    {
        $outputFilename = (string)$outputFilename;

        if (empty($mimeType) || !is_string($mimeType) && !empty($outputFilename)) {
            $ext = strtolower(pathinfo($outputFilename, PATHINFO_EXTENSION));

            if (!empty($ext) && isset(self::$defaultMimeTypes[$ext])) {
                $mimeType = self::$defaultMimeTypes[$ext];
            }
        }
        if (empty($mimeType)) {
            $mimeType = self::$defaultMimeTypes['zip'];
        }

        if (!($handle = fopen('php://memory', 'w+b'))) {
            throw new InvalidArgumentException("Memory can not open from write.");
        }
        $this->writeZipToStream($handle);
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
            ->withBody($stream);
    }

    /**
     * @param $handle
     */
    protected function writeZipToStream($handle)
    {
        $output = new ZipOutputStream($handle, $this->zipModel);
        $output->writeZip();
    }

    /**
     * Returns the zip archive as a string.
     * @return string
     * @throws InvalidArgumentException
     */
    public function outputAsString()
    {
        if (!($handle = fopen('php://memory', 'w+b'))) {
            throw new InvalidArgumentException("Memory can not open from write.");
        }
        $this->writeZipToStream($handle);
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        return $content;
    }

    /**
     * Close zip archive and release input stream.
     */
    public function close()
    {
        if (null !== $this->inputStream) {
            $this->inputStream->close();
            $this->inputStream = null;
            $this->zipModel = new ZipModel();
        }
    }

    /**
     * Save and reopen zip archive.
     * @return ZipFileInterface
     * @throws ZipException
     */
    public function rewrite()
    {
        if (null === $this->inputStream) {
            throw new ZipException('input stream is null');
        }
        $meta = stream_get_meta_data($this->inputStream->getStream());
        $content = $this->outputAsString();
        $this->close();
        if ('plainfile' === $meta['wrapper_type']) {
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
     * Release all resources
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param string $entryName The offset to assign the value to.
     * @param mixed $contents The value to set.
     * @throws InvalidArgumentException
     * @see ZipFile::addFromString
     * @see ZipFile::addEmptyDir
     * @see ZipFile::addFile
     * @see ZipFile::addFilesFromIterator
     */
    public function offsetSet($entryName, $contents)
    {
        if (null === $entryName) {
            throw new InvalidArgumentException('entryName is null');
        }
        $entryName = (string)$entryName;
        if (strlen($entryName) === 0) {
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
        } elseif (is_resource($contents)) {
            $this->addFromStream($contents, $entryName);
        } else {
            $contents = (string)$contents;
            $this->addFromString($entryName, $contents);
        }
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param string $entryName The offset to unset.
     * @throws ZipUnsupportMethod
     */
    public function offsetUnset($entryName)
    {
        $this->deleteFromName($entryName);
    }

    /**
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current()
    {
        return $this->offsetGet($this->key());
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param string $entryName The offset to retrieve.
     * @return string|null
     * @throws ZipNotFoundEntry
     */
    public function offsetGet($entryName)
    {
        return $this->getEntryContents($entryName);
    }

    /**
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        return key($this->zipModel->getEntries());
    }

    /**
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next()
    {
        next($this->zipModel->getEntries());
    }

    /**
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid()
    {
        return $this->offsetExists($this->key());
    }

    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param string $entryName An offset to check for.
     * @return boolean true on success or false on failure.
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($entryName)
    {
        return $this->hasEntry($entryName);
    }

    /**
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind()
    {
        reset($this->zipModel->getEntries());
    }
}
