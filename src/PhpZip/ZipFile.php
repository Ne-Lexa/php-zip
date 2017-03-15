<?php
namespace PhpZip;

use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipException;
use PhpZip\Exception\ZipNotFoundEntry;
use PhpZip\Exception\ZipUnsupportMethod;
use PhpZip\Model\CentralDirectory;
use PhpZip\Model\Entry\ZipNewEmptyDirEntry;
use PhpZip\Model\Entry\ZipNewStreamEntry;
use PhpZip\Model\Entry\ZipNewStringEntry;
use PhpZip\Model\ZipEntry;
use PhpZip\Model\ZipInfo;
use PhpZip\Util\FilesUtil;

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
class ZipFile implements \Countable, \ArrayAccess, \Iterator
{
    /**
     * Default compression level.
     */
    const LEVEL_DEFAULT_COMPRESSION = -1;
    /**
     * Compression level for fastest compression.
     */
    const LEVEL_BEST_SPEED = 1;
    /**
     * Compression level for best compression.
     */
    const LEVEL_BEST_COMPRESSION = 9;
    /**
     * Method for Stored (uncompressed) entries.
     * @see ZipEntry::setMethod()
     */
    const METHOD_STORED = 0;
    /**
     * Method for Deflated compressed entries.
     * @see ZipEntry::setMethod()
     */
    const METHOD_DEFLATED = 8;
    /**
     * No specified method for set encryption method to WinZip AES encryption.
     */
    const ENCRYPTION_METHOD_WINZIP_AES = 1;
    /**
     * Method for BZIP2 compressed entries.
     * Require php extension bz2.
     * @see ZipEntry::setMethod()
     */
    const METHOD_BZIP2 = 12;
    /**
     * No specified method for set encryption method to Traditional PKWARE encryption.
     */
    const ENCRYPTION_METHOD_TRADITIONAL = 0;

    /**
     * Allow compression methods.
     * @var int[]
     */
    private static $allowCompressionMethods = [
        self::METHOD_STORED,
        self::METHOD_DEFLATED,
        self::METHOD_BZIP2
    ];
    /**
     * Input seekable input stream.
     *
     * @var resource
     */
    private $inputStream;

    /**
     * @var CentralDirectory
     */
    private $centralDirectory;
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
     * ZipFile constructor.
     */
    public function __construct()
    {
        $this->centralDirectory = new CentralDirectory();
    }

    /**
     * Open zip archive from file
     *
     * @param string $filename
     * @return ZipFile
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
     * @return ZipFile
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
     * @return ZipFile
     * @throws InvalidArgumentException Invalid stream resource
     *         or resource cannot seekable stream
     */
    public function openFromStream($handle)
    {
        if (!is_resource($handle)) {
            throw new InvalidArgumentException("Invalid stream resource.");
        }
        $meta = stream_get_meta_data($handle);
        if (!$meta['seekable']) {
            throw new InvalidArgumentException("Resource cannot seekable stream.");
        }
        $this->inputStream = $handle;
        $this->centralDirectory = new CentralDirectory();
        $this->centralDirectory->mountCentralDirectory($this->inputStream);
        return $this;
    }

    /**
     * @return int Returns the number of entries in this ZIP file.
     */
    public function count()
    {
        return sizeof($this->centralDirectory->getEntries());
    }

    /**
     * @return string[] Returns the list files.
     */
    public function getListFiles()
    {
        return array_keys($this->centralDirectory->getEntries());
    }

    /**
     * Check whether the directory entry.
     * Returns true if and only if this ZIP entry represents a directory entry
     * (i.e. end with '/').
     *
     * @param string $entryName
     * @return bool
     * @throws ZipNotFoundEntry
     */
    public function isDirectory($entryName)
    {
        return $this->centralDirectory->getEntry($entryName)->isDirectory();
    }

    /**
     * Returns the file comment.
     *
     * @return string The file comment.
     */
    public function getArchiveComment()
    {
        return $this->centralDirectory->getArchiveComment();
    }

    /**
     * Set password to all input encrypted entries.
     *
     * @param string $password Password
     * @return ZipFile
     */
    public function withReadPassword($password)
    {
        foreach ($this->centralDirectory->getEntries() as $entry) {
            if ($entry->isEncrypted()) {
                $entry->setPassword($password);
            }
        }
        return $this;
    }

    /**
     * Set archive comment.
     *
     * @param null|string $comment
     * @throws InvalidArgumentException Length comment out of range
     */
    public function setArchiveComment($comment = null)
    {
        $this->centralDirectory->getEndOfCentralDirectory()->setComment($comment);
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
        return $this->centralDirectory->getEntry($entryName)->getComment();
    }

    /**
     * Set entry comment.
     *
     * @param string $entryName
     * @param string|null $comment
     * @return ZipFile
     * @throws ZipNotFoundEntry
     */
    public function setEntryComment($entryName, $comment = null)
    {
        $this->centralDirectory->setEntryComment($entryName, $comment);
        return $this;
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
        if (!($entryName instanceof ZipEntry)) {
            $entryName = $this->centralDirectory->getEntry($entryName);
        }
        return new ZipInfo($entryName);
    }

    /**
     * Get info by all entries.
     *
     * @return ZipInfo[]
     */
    public function getAllInfo()
    {
        return array_map([$this, 'getEntryInfo'], $this->centralDirectory->getEntries());
    }

    /**
     * Extract the archive contents
     *
     * Extract the complete archive or the given files to the specified destination.
     *
     * @param string $destination Location where to extract the files.
     * @param array|string|null $entries The entries to extract. It accepts either
     *                                   a single entry name or an array of names.
     * @return ZipFile
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

        /**
         * @var ZipEntry[] $zipEntries
         */
        if (!empty($entries)) {
            if (is_string($entries)) {
                $entries = (array)$entries;
            }
            if (is_array($entries)) {
                $entries = array_unique($entries);
                $flipEntries = array_flip($entries);
                $zipEntries = array_filter(
                    $this->centralDirectory->getEntries(),
                    function ($zipEntry) use ($flipEntries) {
                        /**
                         * @var ZipEntry $zipEntry
                         */
                        return isset($flipEntries[$zipEntry->getName()]);
                    }
                );
            }
        } else {
            $zipEntries = $this->centralDirectory->getEntries();
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
     * @param string $localName Zip entry name.
     * @param string $contents String contents.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFile
     * @throws InvalidArgumentException If incorrect data or entry name.
     * @throws ZipUnsupportMethod
     * @see ZipFile::METHOD_STORED
     * @see ZipFile::METHOD_DEFLATED
     * @see ZipFile::METHOD_BZIP2
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
            if ($length >= 1024) {
                $compressionMethod = self::METHOD_DEFLATED;
            } else {
                $compressionMethod = self::METHOD_STORED;
            }
        } elseif (!in_array($compressionMethod, self::$allowCompressionMethods, true)) {
            throw new ZipUnsupportMethod('Unsupported method ' . $compressionMethod);
        }
        $externalAttributes = 0100644 << 16;

        $entry = new ZipNewStringEntry($contents);
        $entry->setName($localName);
        $entry->setMethod($compressionMethod);
        $entry->setTime(time());
        $entry->setExternalAttributes($externalAttributes);

        $this->centralDirectory->putInModified($localName, $entry);
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
     * @return ZipFile
     * @throws InvalidArgumentException
     * @throws ZipUnsupportMethod
     * @see ZipFile::METHOD_STORED
     * @see ZipFile::METHOD_DEFLATED
     * @see ZipFile::METHOD_BZIP2
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
                    $compressionMethod = self::METHOD_STORED;
                } elseif ('text' === $type && filesize($filename) < 150) {
                    $compressionMethod = self::METHOD_STORED;
                } else {
                    $compressionMethod = self::METHOD_DEFLATED;
                }
            } elseif (@filesize($filename) >= 1024) {
                $compressionMethod = self::METHOD_DEFLATED;
            } else {
                $compressionMethod = self::METHOD_STORED;
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
        $this->centralDirectory
            ->getModifiedEntry($localName)
            ->setTime(filemtime($filename));
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
     * @return ZipFile
     * @throws InvalidArgumentException
     * @throws ZipUnsupportMethod
     * @see ZipFile::METHOD_STORED
     * @see ZipFile::METHOD_DEFLATED
     * @see ZipFile::METHOD_BZIP2
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
            if ($length >= 1024) {
                $compressionMethod = self::METHOD_DEFLATED;
            } else {
                $compressionMethod = self::METHOD_STORED;
            }
        } elseif (!in_array($compressionMethod, self::$allowCompressionMethods, true)) {
            throw new ZipUnsupportMethod('Unsupported method ' . $compressionMethod);
        }

        $mode = sprintf('%o', $fstat['mode']);
        $externalAttributes = (octdec($mode) & 0xffff) << 16;

        $entry = new ZipNewStreamEntry($stream);
        $entry->setName($localName);
        $entry->setMethod($compressionMethod);
        $entry->setTime(time());
        $entry->setExternalAttributes($externalAttributes);

        $this->centralDirectory->putInModified($localName, $entry);
        return $this;
    }

    /**
     * Add an empty directory in the zip archive.
     *
     * @param string $dirName
     * @return ZipFile
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

        $entry = new ZipNewEmptyDirEntry();
        $entry->setName($dirName);
        $entry->setTime(time());
        $entry->setMethod(self::METHOD_STORED);
        $entry->setSize(0);
        $entry->setCompressedSize(0);
        $entry->setCrc(0);
        $entry->setExternalAttributes($externalAttributes);

        $this->centralDirectory->putInModified($dirName, $entry);
        return $this;
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
     * Add directory not recursively to the zip archive.
     *
     * @param string $inputDir Input directory
     * @param string $localPath Add files to this directory, or the root.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFile
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
     * @return ZipFile
     * @throws InvalidArgumentException
     * @throws ZipUnsupportMethod
     * @see ZipFile::METHOD_STORED
     * @see ZipFile::METHOD_DEFLATED
     * @see ZipFile::METHOD_BZIP2
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
     * @return ZipFile
     * @throws InvalidArgumentException
     * @throws ZipUnsupportMethod
     * @see ZipFile::METHOD_STORED
     * @see ZipFile::METHOD_DEFLATED
     * @see ZipFile::METHOD_BZIP2
     */
    public function addFilesFromIterator(
        \Iterator $iterator,
        $localPath = '/',
        $compressionMethod = null
    )
    {
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
     * @return ZipFile
     * @throws InvalidArgumentException
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function addFilesFromGlob($inputDir, $globPattern, $localPath = '/', $compressionMethod = null)
    {
        return $this->addGlob($inputDir, $globPattern, $localPath, false, $compressionMethod);
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
     * @return ZipFile
     * @throws InvalidArgumentException
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function addFilesFromGlobRecursive($inputDir, $globPattern, $localPath = '/', $compressionMethod = null)
    {
        return $this->addGlob($inputDir, $globPattern, $localPath, true, $compressionMethod);
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
     * @return ZipFile
     * @throws InvalidArgumentException
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    private function addGlob(
        $inputDir,
        $globPattern,
        $localPath = '/',
        $recursive = true,
        $compressionMethod = null
    )
    {
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
     * Add files from regex pattern.
     *
     * @param string $inputDir Search files in this directory.
     * @param string $regexPattern Regex pattern.
     * @param string|null $localPath Add files to this directory, or the root.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFile
     * @internal param bool $recursive Recursive search.
     */
    public function addFilesFromRegex($inputDir, $regexPattern, $localPath = "/", $compressionMethod = null)
    {
        return $this->addRegex($inputDir, $regexPattern, $localPath, false, $compressionMethod);
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
     * @return ZipFile
     * @internal param bool $recursive Recursive search.
     */
    public function addFilesFromRegexRecursive($inputDir, $regexPattern, $localPath = "/", $compressionMethod = null)
    {
        return $this->addRegex($inputDir, $regexPattern, $localPath, true, $compressionMethod);
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
     * @return ZipFile
     * @throws InvalidArgumentException
     */
    private function addRegex(
        $inputDir,
        $regexPattern,
        $localPath = "/",
        $recursive = true,
        $compressionMethod = null
    )
    {
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
     * Rename the entry.
     *
     * @param string $oldName Old entry name.
     * @param string $newName New entry name.
     * @return ZipFile
     * @throws InvalidArgumentException
     * @throws ZipNotFoundEntry
     */
    public function rename($oldName, $newName)
    {
        if (null === $oldName || null === $newName) {
            throw new InvalidArgumentException("name is null");
        }
        $this->centralDirectory->rename($oldName, $newName);
        return $this;
    }

    /**
     * Delete entry by name.
     *
     * @param string $entryName Zip Entry name.
     * @return ZipFile
     * @throws ZipNotFoundEntry If entry not found.
     */
    public function deleteFromName($entryName)
    {
        $entryName = (string)$entryName;
        $this->centralDirectory->deleteEntry($entryName);
        return $this;
    }

    /**
     * Delete entries by glob pattern.
     *
     * @param string $globPattern Glob pattern
     * @return ZipFile
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
     * @return ZipFile
     * @throws InvalidArgumentException
     */
    public function deleteFromRegex($regexPattern)
    {
        if (null === $regexPattern || !is_string($regexPattern) || empty($regexPattern)) {
            throw new InvalidArgumentException("Regex pattern is empty.");
        }
        $this->centralDirectory->deleteEntriesFromRegex($regexPattern);
        return $this;
    }

    /**
     * Delete all entries
     * @return ZipFile
     */
    public function deleteAll()
    {
        $this->centralDirectory->deleteAll();
        return $this;
    }

    /**
     * Set compression level for new entries.
     *
     * @param int $compressionLevel
     * @see ZipFile::LEVEL_DEFAULT_COMPRESSION
     * @see ZipFile::LEVEL_BEST_SPEED
     * @see ZipFile::LEVEL_BEST_COMPRESSION
     */
    public function setCompressionLevel($compressionLevel = self::LEVEL_DEFAULT_COMPRESSION)
    {
        $this->centralDirectory->setCompressionLevel($compressionLevel);
    }

    /**
     * @param int|null $align
     */
    public function setZipAlign($align = null)
    {
        $this->centralDirectory->setZipAlign($align);
    }

    /**
     * Set password for all entries for update.
     *
     * @param string $password If password null then encryption clear
     * @param int $encryptionMethod Encryption method
     * @return ZipFile
     */
    public function withNewPassword($password, $encryptionMethod = self::ENCRYPTION_METHOD_WINZIP_AES)
    {
        $this->centralDirectory->setNewPassword($password, $encryptionMethod);
        return $this;
    }

    /**
     * Remove password for all entries for update.
     * @return ZipFile
     */
    public function withoutPassword()
    {
        $this->centralDirectory->setNewPassword(null);
        return $this;
    }

    /**
     * Save as file.
     *
     * @param string $filename Output filename
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
    }

    /**
     * Save as stream.
     *
     * @param resource $handle Output stream resource
     * @throws ZipException
     */
    public function saveAsStream($handle)
    {
        if (!is_resource($handle)) {
            throw new InvalidArgumentException('handle is not resource');
        }
        ftruncate($handle, 0);
        $this->centralDirectory->writeArchive($handle);
        fclose($handle);
    }

    /**
     * Output .ZIP archive as attachment.
     * Die after output.
     *
     * @param string $outputFilename
     * @param string|null $mimeType
     * @throws InvalidArgumentException
     */
    public function outputAsAttachment($outputFilename, $mimeType = null)
    {
        $outputFilename = (string)$outputFilename;
        if (strlen($outputFilename) === 0) {
            throw new InvalidArgumentException("Output filename is empty.");
        }
        if (empty($mimeType) || !is_string($mimeType)) {
            $ext = strtolower(pathinfo($outputFilename, PATHINFO_EXTENSION));

            if (!empty($ext) && isset(self::$defaultMimeTypes[$ext])) {
                $mimeType = self::$defaultMimeTypes[$ext];
            } else {
                $mimeType = self::$defaultMimeTypes['zip'];
            }
        }
        $outputFilename = basename($outputFilename);

        $content = $this->outputAsString();
        $this->close();

        header("Content-Type: " . $mimeType);
        header("Content-Disposition: attachment; filename=" . rawurlencode($outputFilename));
        header("Content-Length: " . strlen($content));
        exit($content);
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
        $this->centralDirectory->writeArchive($handle);
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        return $content;
    }

    /**
     * Rewrite and reopen zip archive.
     * @return ZipFile
     * @throws ZipException
     */
    public function rewrite()
    {
        if (null === $this->inputStream) {
            throw new ZipException('input stream is null');
        }
        $meta = stream_get_meta_data($this->inputStream);
        $content = $this->outputAsString();
        $this->close();
        if ('plainfile' === $meta['wrapper_type']) {
            if (file_put_contents($meta['uri'], $content) === false) {
                throw new ZipException("Can not overwrite the zip file in the {$meta['uri']} file.");
            }
            if (!($handle = @fopen($meta['uri'], 'rb'))) {
                throw new ZipException("File {$meta['uri']} can't open.");
            }
            return $this->openFromStream($handle);
        }
        return $this->openFromString($content);
    }

    /**
     * Close zip archive and release input stream.
     */
    public function close()
    {
        if (null !== $this->inputStream) {
            fclose($this->inputStream);
            $this->inputStream = null;
        }
        if (null !== $this->centralDirectory) {
            $this->centralDirectory->release();
            $this->centralDirectory = null;
        }
    }

    /**
     * Release all resources
     */
    function __destruct()
    {
        $this->close();
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
        return isset($this->centralDirectory->getEntries()[$entryName]);
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
        return $this->centralDirectory->getEntry($entryName)->getEntryContent();
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
        $contents = (string)$contents;
        if ('/' === $entryName[strlen($entryName) - 1]) {
            $this->addEmptyDir($entryName);
        } else {
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
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next()
    {
        next($this->centralDirectory->getEntries());
    }

    /**
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        return key($this->centralDirectory->getEntries());
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
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind()
    {
        reset($this->centralDirectory->getEntries());
    }
}