<?php
namespace PhpZip;

use PhpZip\Crypto\TraditionalPkwareEncryptionEngine;
use PhpZip\Crypto\WinZipAesEngine;
use PhpZip\Exception\IllegalArgumentException;
use PhpZip\Exception\ZipException;
use PhpZip\Exception\ZipNotFoundEntry;
use PhpZip\Extra\WinZipAesEntryExtraField;
use PhpZip\Model\ZipEntry;
use PhpZip\Output\ZipOutputEmptyDirEntry;
use PhpZip\Output\ZipOutputEntry;
use PhpZip\Output\ZipOutputStreamEntry;
use PhpZip\Output\ZipOutputStringEntry;
use PhpZip\Output\ZipOutputZipFileEntry;
use PhpZip\Util\FilesUtil;
use PhpZip\Util\PackUtil;

/**
 * This class is able to create or update the .ZIP file in write mode.
 *
 * Implemented support traditional PKWARE encryption and WinZip AES encryption.
 * Implemented support ZIP64.
 * Implemented support skip a preamble like the one found in self extracting archives.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipOutputFile implements \Countable, \ArrayAccess, \Iterator, ZipConstants
{
    /**
     * Compression level for fastest compression.
     */
    const LEVEL_BEST_SPEED = 1;

    /**
     * Compression level for best compression.
     */
    const LEVEL_BEST_COMPRESSION = 9;

    /**
     * Default compression level.
     */
    const LEVEL_DEFAULT_COMPRESSION = -1;

    /**
     * Allow compression methods.
     *
     * @var array
     */
    private static $allowCompressionMethods = [
        ZipEntry::METHOD_STORED,
        ZipEntry::METHOD_DEFLATED,
        ZipEntry::METHOD_BZIP2
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
     * The charset to use for entry names and comments.
     *
     * @var string
     */
    private $charset = 'UTF-8';

    /**
     * The file comment.
     *
     * @var null|string
     */
    private $comment;

    /**
     * Output zip entries.
     *
     * @var ZipOutputEntry[]
     */
    private $entries = [];

    /**
     * Start of central directory.
     *
     * @var int
     */
    private $cdOffset;

    /**
     * Default compression level for the methods DEFLATED and BZIP2.
     *
     * @var int
     */
    private $level = self::LEVEL_DEFAULT_COMPRESSION;

    /**
     * ZipAlign setting
     *
     * @var int
     */
    private $align;

    /**
     * ZipOutputFile constructor.
     * @param ZipFile|null $zipFile
     */
    public function __construct(ZipFile $zipFile = null)
    {
        if ($zipFile !== null) {
            $this->charset = $zipFile->getCharset();
            $this->comment = $zipFile->getComment();
            foreach ($zipFile->getRawEntries() as $entry) {
                $this->entries[$entry->getName()] = new ZipOutputZipFileEntry($zipFile, $entry);
            }
        }
    }

    /**
     * Create empty archive
     *
     * @return ZipOutputFile
     * @see ZipOutputFile::__construct()
     */
    public static function create()
    {
        return new self();
    }

    /**
     * Open zip archive from update.
     *
     * @param ZipFile $zipFile
     * @return ZipOutputFile
     * @throws IllegalArgumentException
     * @see ZipOutputFile::__construct()
     */
    public static function openFromZipFile(ZipFile $zipFile)
    {
        if ($zipFile === null) {
            throw new IllegalArgumentException("Zip file is null");
        }
        return new self($zipFile);
    }

    /**
     * Returns the list files.
     *
     * @return string[]
     */
    public function getListFiles()
    {
        return array_keys($this->entries);
    }

    /**
     * Extract the archive contents
     *
     * Extract the complete archive or the given files to the specified destination.
     *
     * @param string $destination Location where to extract the files.
     * @param array $entries The entries to extract. It accepts
     *                       either a single entry name or an array of names.
     * @return bool
     * @throws ZipException
     */
    public function extractTo($destination, $entries = null)
    {
        if ($this->entries === null) {
            throw new ZipException("Zip entries not initial");
        }
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
         * @var ZipOutputEntry[] $zipOutputEntries
         */
        if (!empty($entries)) {
            if (is_string($entries)) {
                $entries = (array)$entries;
            }
            if (is_array($entries)) {
                $flipEntries = array_flip($entries);
                $zipOutputEntries = array_filter($this->entries, function ($zipOutputEntry) use ($flipEntries) {
                    /**
                     * @var ZipOutputEntry $zipOutputEntry
                     */
                    return isset($flipEntries[$zipOutputEntry->getEntry()->getName()]);
                });
            }
        } else {
            $zipOutputEntries = $this->entries;
        }

        $extract = 0;
        foreach ($zipOutputEntries AS $outputEntry) {
            $entry = $outputEntry->getEntry();
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
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new ZipException("Can not create dir " . $dir);
                }
                chmod($dir, 0755);
                touch($file, $entry->getTime());
            }
            if (file_put_contents($file, $this->getEntryContent($entry->getName())) === null) {
                return false;
            }
            touch($file, $entry->getTime());
            $extract++;
        }
        return $extract > 0;
    }

    /**
     * Returns entry content.
     *
     * @param string $entryName
     * @return string
     * @throws ZipNotFoundEntry
     */
    public function getEntryContent($entryName)
    {
        $entryName = (string)$entryName;
        if (!isset($this->entries[$entryName])) {
            throw new ZipNotFoundEntry('Can not entry ' . $entryName);
        }
        return $this->entries[$entryName]->getEntryContent();
    }

    /**
     * @return null|string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @param null|string $comment
     * @throws IllegalArgumentException Length comment out of range
     */
    public function setComment($comment)
    {
        if (null !== $comment && strlen($comment) !== 0) {
            $comment = (string)$comment;
            $length = strlen($comment);
            if (0x0000 > $length || $length > 0xffff) {
                throw new IllegalArgumentException('Length comment out of range');
            }
            $this->comment = $comment;
        } else {
            $this->comment = null;
        }
    }

    /**
     * Add entry from the string.
     *
     * @param string $entryName
     * @param string $data String contents
     * @param int $compressionMethod
     * @throws IllegalArgumentException
     */
    public function addFromString($entryName, $data, $compressionMethod = ZipEntry::METHOD_DEFLATED)
    {
        $entryName = (string)$entryName;
        if ($data === null || strlen($data) === 0) {
            throw new IllegalArgumentException("Data is empty");
        }
        if ($entryName === null || strlen($entryName) === 0) {
            throw new IllegalArgumentException("Incorrect entry name " . $entryName);
        }
        $this->validateCompressionMethod($compressionMethod);

        $entry = new ZipEntry($entryName);
        $entry->setMethod($compressionMethod);
        $entry->setTime(time());

        $this->entries[$entryName] = new ZipOutputStringEntry($data, $entry);
    }

    /**
     * Validate compression method.
     *
     * @param int $compressionMethod
     * @throws IllegalArgumentException
     * @see ZipEntry::METHOD_STORED
     * @see ZipEntry::METHOD_DEFLATED
     * @see ZipEntry::METHOD_BZIP2
     */
    private function validateCompressionMethod($compressionMethod)
    {
        if (!in_array($compressionMethod, self::$allowCompressionMethods, true)) {
            throw new IllegalArgumentException("Compression method " . $compressionMethod . ' is not support');
        }
    }

    /**
     * Add directory to the zip archive.
     *
     * @param string $inputDir Input directory
     * @param bool $recursive Recursive search files
     * @param string|null $moveToPath If not null then put $inputDir to path $outEntryDir
     * @param array $ignoreFiles List of files to exclude from the folder $inputDir.
     * @param int $compressionMethod Compression method
     * @return bool
     * @throws IllegalArgumentException
     */
    public function addDir(
        $inputDir,
        $recursive = true,
        $moveToPath = "/",
        array $ignoreFiles = [],
        $compressionMethod = ZipEntry::METHOD_DEFLATED
    )
    {
        $inputDir = (string)$inputDir;
        if ($inputDir === null || strlen($inputDir) === 0) {
            throw new IllegalArgumentException('Input dir empty');
        }
        if (!is_dir($inputDir)) {
            throw new IllegalArgumentException('Directory ' . $inputDir . ' can\'t exists');
        }
        $this->validateCompressionMethod($compressionMethod);

        if (null !== $moveToPath && is_string($moveToPath) && !empty($moveToPath)) {
            $moveToPath = rtrim($moveToPath, '/') . '/';
        } else {
            $moveToPath = "/";
        }
        $inputDir = rtrim($inputDir, '/\\') . DIRECTORY_SEPARATOR;

        $count = $this->count();

        $files = FilesUtil::fileSearchWithIgnore($inputDir, $recursive, $ignoreFiles);
        /**
         * @var \SplFileInfo $file
         */
        foreach ($files as $file) {
            $filename = str_replace($inputDir, $moveToPath, $file);
            $filename = ltrim($filename, '/');
            if(is_dir($file)){
                FilesUtil::isEmptyDir($file) && $this->addEmptyDir($filename);
            }
            elseif(is_file($file)){
                $this->addFromFile($file, $filename, $compressionMethod);
            }
        }
        return $this->count() > $count;
    }

    /**
     * Count zip entries.
     *
     * @return int
     */
    public function count()
    {
        return sizeof($this->entries);
    }

    /**
     * Add an empty directory in the zip archive.
     *
     * @param string $dirName
     * @throws IllegalArgumentException
     */
    public function addEmptyDir($dirName)
    {
        $dirName = (string)$dirName;
        if (strlen($dirName) === 0) {
            throw new IllegalArgumentException("dirName null or not string");
        }
        $dirName = rtrim($dirName, '/') . '/';
        if (!isset($this->entries[$dirName])) {
            $entry = new ZipEntry($dirName);
            $entry->setTime(time());
            $entry->setMethod(ZipEntry::METHOD_STORED);
            $entry->setSize(0);
            $entry->setCompressedSize(0);
            $entry->setCrc(0);

            $this->entries[$dirName] = new ZipOutputEmptyDirEntry($entry);
        }
    }

    /**
     * Add entry from the file.
     *
     * @param string $filename
     * @param string|null $entryName
     * @param int $compressionMethod
     * @throws IllegalArgumentException
     */
    public function addFromFile($filename, $entryName = null, $compressionMethod = ZipEntry::METHOD_DEFLATED)
    {
        if ($filename === null) {
            throw new IllegalArgumentException("Filename is null");
        }
        if (!is_file($filename)) {
            throw new IllegalArgumentException("File is not exists");
        }
        if (!($handle = fopen($filename, 'rb'))) {
            throw new IllegalArgumentException('File ' . $filename . ' can not open.');
        }
        if ($entryName === null) {
            $entryName = basename($filename);
        }
        $this->addFromStream($handle, $entryName, $compressionMethod);
    }

    /**
     * Add entry from the stream.
     *
     * @param resource $stream Stream resource
     * @param string $entryName
     * @param int $compressionMethod
     * @throws IllegalArgumentException
     */
    public function addFromStream($stream, $entryName, $compressionMethod = ZipEntry::METHOD_DEFLATED)
    {
        if (!is_resource($stream)) {
            throw new IllegalArgumentException("stream is not resource");
        }
        $entryName = (string)$entryName;
        if (strlen($entryName) === 0) {
            throw new IllegalArgumentException("Incorrect entry name " . $entryName);
        }
        $this->validateCompressionMethod($compressionMethod);

        $entry = new ZipEntry($entryName);
        $entry->setMethod($compressionMethod);
        $entry->setTime(time());

        $this->entries[$entryName] = new ZipOutputStreamEntry($stream, $entry);
    }

    /**
     * Add files from glob pattern.
     *
     * @param string $inputDir Input directory
     * @param string $globPattern Glob pattern.
     * @param bool $recursive Recursive search.
     * @param string|null $moveToPath Add files to this directory, or the root.
     * @param int $compressionMethod Compression method.
     * @return bool
     * @throws IllegalArgumentException
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function addFilesFromGlob(
        $inputDir,
        $globPattern,
        $recursive = true,
        $moveToPath = '/',
        $compressionMethod = ZipEntry::METHOD_DEFLATED
    )
    {
        $inputDir = (string)$inputDir;
        if (empty($inputDir)) {
            throw new IllegalArgumentException('Input dir empty');
        }
        if (!is_dir($inputDir)) {
            throw new IllegalArgumentException('Directory ' . $inputDir . ' can\'t exists');
        }
        if (null === $globPattern || strlen($globPattern) === 0) {
            throw new IllegalArgumentException("globPattern null");
        }
        if (empty($globPattern)) {
            throw new IllegalArgumentException("globPattern empty");
        }
        $this->validateCompressionMethod($compressionMethod);

        $inputDir = rtrim($inputDir, '/\\') . DIRECTORY_SEPARATOR;
        $globPattern = $inputDir . $globPattern;

        $filesFound = FilesUtil::globFileSearch($globPattern, GLOB_BRACE, $recursive);
        if ($filesFound === false || empty($filesFound)) {
            return false;
        }
        if (!empty($moveToPath) && is_string($moveToPath)) {
            $moveToPath = rtrim($moveToPath, '/') . '/';
        } else {
            $moveToPath = "/";
        }

        $count = $this->count();
        /**
         * @var string $file
         */
        foreach ($filesFound as $file) {
            $filename = str_replace($inputDir, $moveToPath, $file);
            $filename = ltrim($filename, '/');
            if(is_dir($file)){
                FilesUtil::isEmptyDir($file) && $this->addEmptyDir($filename);
            }
            elseif(is_file($file)){
                $this->addFromFile($file, $filename, $compressionMethod);
            }
        }
        return $this->count() > $count;
    }

    /**
     * Add files from regex pattern.
     *
     * @param string $inputDir Search files in this directory.
     * @param string $regexPattern Regex pattern.
     * @param bool $recursive Recursive search.
     * @param string|null $moveToPath Add files to this directory, or the root.
     * @param int $compressionMethod Compression method.
     * @return bool
     * @throws IllegalArgumentException
     */
    public function addFilesFromRegex(
        $inputDir,
        $regexPattern,
        $recursive = true,
        $moveToPath = "/",
        $compressionMethod = ZipEntry::METHOD_DEFLATED
    )
    {
        if ($regexPattern === null || !is_string($regexPattern) || empty($regexPattern)) {
            throw new IllegalArgumentException("regex pattern empty");
        }
        $inputDir = (string)$inputDir;
        if (empty($inputDir)) {
            throw new IllegalArgumentException('Invalid $inputDir value');
        }
        if (!is_dir($inputDir)) {
            throw new IllegalArgumentException('Path ' . $inputDir . ' can\'t directory.');
        }
        $this->validateCompressionMethod($compressionMethod);

        $inputDir = rtrim($inputDir, '/\\') . DIRECTORY_SEPARATOR;

        $files = FilesUtil::regexFileSearch($inputDir, $regexPattern, $recursive);
        if ($files === false || empty($files)) {
            return false;
        }
        if (!empty($moveToPath) && is_string($moveToPath)) {
            $moveToPath = rtrim($moveToPath, '/') . '/';
        } else {
            $moveToPath = "/";
        }
        $inputDir = rtrim($inputDir, '/\\') . DIRECTORY_SEPARATOR;

        $count = $this->count();
        /**
         * @var string $file
         */
        foreach ($files as $file) {
            $filename = str_replace($inputDir, $moveToPath, $file);
            $filename = ltrim($filename, '/');
            if(is_dir($file)){
                FilesUtil::isEmptyDir($file) && $this->addEmptyDir($filename);
            }
            elseif(is_file($file)){
                $this->addFromFile($file, $filename, $compressionMethod);
            }
        }
        return $this->count() > $count;
    }

    /**
     * Rename the entry.
     *
     * @param string $oldName Old entry name.
     * @param string $newName New entry name.
     * @throws IllegalArgumentException
     * @throws ZipNotFoundEntry
     */
    public function rename($oldName, $newName)
    {
        if ($oldName === null || $newName === null) {
            throw new IllegalArgumentException("name is null");
        }
        $oldName = (string)$oldName;
        $newName = (string)$newName;
        if (!isset($this->entries[$oldName])) {
            throw new ZipNotFoundEntry("Not found entry " . $oldName);
        }
        if (isset($this->entries[$newName])) {
            throw new IllegalArgumentException("New entry name " . $newName . ' is exists.');
        }
        $this->entries[$newName] = $this->entries[$oldName];
        unset($this->entries[$oldName]);
        $this->entries[$newName]->getEntry()->setName($newName);
    }

    /**
     * Delete entry by name.
     *
     * @param string $entryName
     * @throws ZipNotFoundEntry
     */
    public function deleteFromName($entryName)
    {
        $entryName = (string)$entryName;
        if (!isset($this->entries[$entryName])) {
            throw new ZipNotFoundEntry("Not found entry " . $entryName);
        }
        unset($this->entries[$entryName]);
    }

    /**
     * Delete entries by glob pattern.
     *
     * @param string $globPattern Glob pattern
     * @return bool
     * @throws IllegalArgumentException
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function deleteFromGlob($globPattern)
    {
        if ($globPattern === null || !is_string($globPattern) || empty($globPattern)) {
            throw new IllegalArgumentException("Glob pattern is empty");
        }
        $globPattern = '~' . FilesUtil::convertGlobToRegEx($globPattern) . '~si';
        return $this->deleteFromRegex($globPattern);
    }

    /**
     * Delete entries by regex pattern.
     *
     * @param string $regexPattern Regex pattern
     * @return bool
     * @throws IllegalArgumentException
     */
    public function deleteFromRegex($regexPattern)
    {
        if ($regexPattern === null || !is_string($regexPattern) || empty($regexPattern)) {
            throw new IllegalArgumentException("Regex pattern is empty.");
        }
        $count = $this->count();
        foreach ($this->entries as $entryName => $entry) {
            if (preg_match($regexPattern, $entryName)) {
                unset($this->entries[$entryName]);
            }
        }
        return $this->count() > $count;
    }

    /**
     * Delete all entries
     */
    public function deleteAll()
    {
        unset($this->entries); // for stream close
        $this->entries = [];
    }

    /**
     * Set the compression method for a concrete entry.
     *
     * @param string $entryName
     * @param int $compressionMethod
     * @throws ZipNotFoundEntry
     * @see ZipEntry::METHOD_STORED
     * @see ZipEntry::METHOD_DEFLATED
     * @see ZipEntry::METHOD_BZIP2
     */
    public function setCompressionMethod($entryName, $compressionMethod = ZipEntry::METHOD_DEFLATED)
    {
        $entryName = (string)$entryName;
        if (!isset($this->entries[$entryName])) {
            throw new ZipNotFoundEntry("Not found entry " . $entryName);
        }
        $this->validateCompressionMethod($compressionMethod);
        $this->entries[$entryName]->getEntry()->setMethod($compressionMethod);
    }

    /**
     * Returns the comment from the entry.
     *
     * @param string $entryName
     * @return string|null
     * @throws ZipNotFoundEntry
     */
    public function getEntryComment($entryName)
    {
        $entryName = (string)$entryName;
        if (!isset($this->entries[$entryName])) {
            throw new ZipNotFoundEntry("Not found entry " . $entryName);
        }
        return $this->entries[$entryName]->getEntry()->getComment();
    }

    /**
     * Set entry comment.
     *
     * @param string $entryName
     * @param string|null $comment
     * @throws ZipNotFoundEntry
     */
    public function setEntryComment($entryName, $comment = null)
    {
        $entryName = (string)$entryName;
        if (!isset($this->entries[$entryName])) {
            throw new ZipNotFoundEntry("Not found entry " . $entryName);
        }
        $this->entries[$entryName]->getEntry()->setComment($comment);
    }

    /**
     * Set password for all previously added entries.
     * For the following entries, set the password separately,
     * or set a password before saving archive so that it applies to all entries.
     *
     * @param string $password If password null then encryption clear
     * @param int $encryptionMethod Encryption method
     */
    public function setPassword($password, $encryptionMethod = ZipEntry::ENCRYPTION_METHOD_WINZIP_AES)
    {
        foreach ($this->entries as $outputEntry) {
            $outputEntry->getEntry()->setPassword($password, $encryptionMethod);
        }
    }

    /**
     * Set a password and encryption method for a concrete entry.
     *
     * @param string $entryName Zip entry name
     * @param string $password If password null then encryption clear
     * @param int $encryptionMethod Encryption method
     * @throws ZipNotFoundEntry
     * @see ZipEntry::ENCRYPTION_METHOD_TRADITIONAL
     * @see ZipEntry::ENCRYPTION_METHOD_WINZIP_AES
     */
    public function setEntryPassword($entryName, $password, $encryptionMethod = ZipEntry::ENCRYPTION_METHOD_WINZIP_AES)
    {
        $entryName = (string)$entryName;
        if (!isset($this->entries[$entryName])) {
            throw new ZipNotFoundEntry("Not found entry " . $entryName);
        }
        $entry = $this->entries[$entryName]->getEntry();
        $entry->setPassword($password, $encryptionMethod);
    }

    /**
     * Remove password from all entries
     */
    public function removePasswordAllEntries()
    {
        foreach ($this->entries as $outputEntry) {
            $zipEntry = $outputEntry->getEntry();
            $zipEntry->clearEncryption();
        }
    }

    /**
     * Remove password for concrete zip entry.
     *
     * @param string $entryName
     * @throws ZipNotFoundEntry
     */
    public function removePasswordFromEntry($entryName)
    {
        $entryName = (string)$entryName;
        if (!isset($this->entries[$entryName])) {
            throw new ZipNotFoundEntry("Not found entry " . $entryName);
        }
        $zipEntry = $this->entries[$entryName]->getEntry();
        $zipEntry->clearEncryption();
    }

    /**
     * Returns the compression level for entries.
     * This property is only used if the effective compression method is DEFLATED or BZIP2
     *
     * @return int The compression level for entries.
     * @see    ZipOutputFile::setLevel()
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * Sets the compression level for entries.
     * This property is only used if the effective compression method is DEFLATED or BZIP2.
     * Legal values are ZipOutputFile::LEVEL_DEFAULT_COMPRESSION or range from
     * ZipOutputFile::LEVEL_BEST_SPEED to ZipOutputFile::LEVEL_BEST_COMPRESSION.
     *
     * @param  int $level the compression level for entries.
     * @throws IllegalArgumentException if the compression level is invalid.
     * @see    ZipOutputFile::getLevel()
     */
    public function setLevel($level)
    {
        if (
            ($level < self::LEVEL_BEST_SPEED || self::LEVEL_BEST_COMPRESSION < $level)
            && self::LEVEL_DEFAULT_COMPRESSION !== $level
        ) {
            throw new IllegalArgumentException("Invalid compression level!");
        }
        $this->level = $level;
    }

    /**
     * @param int|null $align
     */
    public function setZipAlign($align = 4)
    {
        if ($align === null) {
            $this->align = null;
            return;
        }
        $this->align = (int)$align;
    }

    /**
     * Save as file
     *
     * @param string $filename Output filename
     * @throws IllegalArgumentException
     * @throws ZipException
     */
    public function saveAsFile($filename)
    {
        $filename = (string)$filename;

        $tempFilename = $filename . '.temp' . uniqid();
        if (!($handle = fopen($tempFilename, 'w+b'))) {
            throw new IllegalArgumentException("File " . $tempFilename . ' can not open from write.');
        }
        $this->saveAsStream($handle);

        if (!rename($tempFilename, $filename)) {
            throw new ZipException('Can not move ' . $tempFilename . ' to ' . $filename);
        }
    }

    /**
     * Save as stream
     *
     * @param resource $handle Output stream resource
     * @param bool $autoClose Close the stream resource, if found true.
     * @throws IllegalArgumentException
     */
    public function saveAsStream($handle, $autoClose = true)
    {
        if (!is_resource($handle)) {
            throw new IllegalArgumentException('handle is not resource');
        }
        ftruncate($handle, 0);
        foreach ($this->entries as $key => $outputEntry) {
            $this->writeEntry($handle, $outputEntry);
        }
        $this->cdOffset = ftell($handle);
        foreach ($this->entries as $key => $outputEntry) {
            if (!$this->writeCentralFileHeader($handle, $outputEntry->getEntry())) {
                unset($this->entries[$key]);
            }
        }
        $this->writeEndOfCentralDirectory($handle);
        if ($autoClose) {
            fclose($handle);
        }
    }

    /**
     * Write entry.
     *
     * @param resource $outputHandle Output stream resource.
     * @param ZipOutputEntry $outputEntry
     * @throws ZipException
     */
    private function writeEntry($outputHandle, ZipOutputEntry $outputEntry)
    {
        $entry = $outputEntry->getEntry();
        $size = strlen($entry->getName()) + strlen($entry->getExtra()) + strlen($entry->getComment());
        if (0xffff < $size) {
            throw new ZipException($entry->getName()
                . " (the total size of "
                . $size
                . " bytes for the name, extra fields and comment exceeds the maximum size of "
                . 0xffff . " bytes)");
        }

        if (ZipEntry::UNKNOWN === $entry->getPlatform()) {
            $entry->setRawPlatform(ZipEntry::getCurrentPlatform());
        }
        if (ZipEntry::UNKNOWN === $entry->getTime()) {
            $entry->setTime(time());
        }
        $method = $entry->getMethod();
        if (ZipEntry::UNKNOWN === $method) {
            $entry->setRawMethod($method = ZipEntry::METHOD_DEFLATED);
        }
        $skipCrc = false;

        $encrypted = $entry->isEncrypted();
        $dd = $entry->isDataDescriptorRequired();
        // Compose General Purpose Bit Flag.
        // See appendix D of PKWARE's ZIP File Format Specification.
        $utf8 = true;
        $general = ($encrypted ? ZipEntry::GPBF_ENCRYPTED : 0)
            | ($dd ? ZipEntry::GPBF_DATA_DESCRIPTOR : 0)
            | ($utf8 ? ZipEntry::GPBF_UTF8 : 0);

        $entryContent = $outputEntry->getEntryContent();

        $entry->setRawSize(strlen($entryContent));
        $entry->setCrc(crc32($entryContent));

        if ($encrypted && null === $entry->getPassword()) {
            throw new ZipException("Can not password from entry " . $entry->getName());
        }

        if (
            $encrypted &&
            (
                ZipEntry::WINZIP_AES === $method ||
                $entry->getEncryptionMethod() === ZipEntry::ENCRYPTION_METHOD_WINZIP_AES
            )
        ) {
            $field = null;
            $method = $entry->getMethod();
            $keyStrength = 256; // bits

            $compressedSize = $entry->getCompressedSize();

            if (ZipEntry::WINZIP_AES === $method) {
                /**
                 * @var WinZipAesEntryExtraField $field
                 */
                $field = $entry->getExtraField(WinZipAesEntryExtraField::getHeaderId());
                if (null !== $field) {
                    $method = $field->getMethod();
                    if (ZipEntry::UNKNOWN !== $compressedSize) {
                        $compressedSize -= $field->getKeyStrength() / 2 // salt value
                            + 2   // password verification value
                            + 10; // authentication code
                    }
                    $entry->setRawMethod($method);
                }
            }
            if (null === $field) {
                $field = new WinZipAesEntryExtraField();
            }
            $field->setKeyStrength($keyStrength);
            $field->setMethod($method);
            $size = $entry->getSize();
            if (20 <= $size && ZipEntry::METHOD_BZIP2 !== $method) {
                $field->setVendorVersion(WinZipAesEntryExtraField::VV_AE_1);
            } else {
                $field->setVendorVersion(WinZipAesEntryExtraField::VV_AE_2);
                $skipCrc = true;
            }
            $entry->addExtraField($field);
            if (ZipEntry::UNKNOWN !== $compressedSize) {
                $compressedSize += $field->getKeyStrength() / 2 // salt value
                    + 2   // password verification value
                    + 10; // authentication code
                $entry->setRawCompressedSize($compressedSize);
            }
            if ($skipCrc) {
                $entry->setRawCrc(0);
            }
        }

        switch ($method) {
            case ZipEntry::METHOD_STORED:
                break;
            case ZipEntry::METHOD_DEFLATED:
                $entryContent = gzdeflate($entryContent, $this->level);
                break;
            case ZipEntry::METHOD_BZIP2:
                $entryContent = bzcompress(
                    $entryContent,
                    $this->level === self::LEVEL_DEFAULT_COMPRESSION ? 4 : $this->level
                );
                break;
            default:
                throw new ZipException($entry->getName() . " (unsupported compression method " . $method . ")");
        }

        if ($encrypted) {
            if ($entry->getEncryptionMethod() === ZipEntry::ENCRYPTION_METHOD_WINZIP_AES) {
                if ($skipCrc) {
                    $entry->setRawCrc(0);
                }
                $entry->setRawMethod(ZipEntry::WINZIP_AES);

                /**
                 * @var WinZipAesEntryExtraField $field
                 */
                $field = $entry->getExtraField(WinZipAesEntryExtraField::getHeaderId());
                $winZipAesEngine = new WinZipAesEngine($entry, $field);
                $entryContent = $winZipAesEngine->encrypt($entryContent);
            } elseif ($entry->getEncryptionMethod() === ZipEntry::ENCRYPTION_METHOD_TRADITIONAL) {
                $zipCryptoEngine = new TraditionalPkwareEncryptionEngine($entry);
                $entryContent = $zipCryptoEngine->encrypt(
                    $entryContent,
                    ($dd ? ($entry->getRawTime() & 0x0000ffff) << 16 : $entry->getCrc())
                );
            }
        }

        $compressedSize = strlen($entryContent);
        $entry->setCompressedSize($compressedSize);

        $offset = ftell($outputHandle);

        // Commit changes.
        $entry->setGeneralPurposeBitFlags($general);
        $entry->setRawOffset($offset);

        // Start changes.
        // local file header signature     4 bytes  (0x04034b50)
        // version needed to extract       2 bytes
        // general purpose bit flag        2 bytes
        // compression method              2 bytes
        // last mod file time              2 bytes
        // last mod file date              2 bytes
        // crc-32                          4 bytes
        // compressed size                 4 bytes
        // uncompressed size               4 bytes
        // file name length                2 bytes
        // extra field length              2 bytes
        $extra = $entry->getRawExtraFields();

        // zip align
        $padding = 0;
        if ($this->align !== null && !$entry->isEncrypted() && $entry->getMethod() === ZipEntry::METHOD_STORED) {
            $padding =
                (
                    $this->align -
                    (
                        $offset +
                        self::LOCAL_FILE_HEADER_MIN_LEN +
                        strlen($entry->getName()) +
                        strlen($extra)
                    ) % $this->align
                ) % $this->align;
        }

        fwrite($outputHandle, pack('VvvvVVVVvv',
            ZipConstants::LOCAL_FILE_HEADER_SIG,
            $entry->getVersionNeededToExtract(),
            $general,
            $entry->getRawMethod(),
            (int)$entry->getRawTime(),
            $dd ? 0 : (int)$entry->getRawCrc(),
            $dd ? 0 : (int)$entry->getRawCompressedSize(),
            $dd ? 0 : (int)$entry->getRawSize(),
            strlen($entry->getName()),
            strlen($extra) + $padding
        ));
        // file name (variable size)
        fwrite($outputHandle, $entry->getName());
        // extra field (variable size)
        fwrite($outputHandle, $extra);

        if ($padding > 0) {
            fwrite($outputHandle, str_repeat(chr(0), $padding));
        }

        fwrite($outputHandle, $entryContent);

        assert(ZipEntry::UNKNOWN !== $entry->getCrc());
        assert(ZipEntry::UNKNOWN !== $entry->getSize());
        if ($entry->getGeneralPurposeBitFlag(ZipEntry::GPBF_DATA_DESCRIPTOR)) {
            // data descriptor signature       4 bytes  (0x08074b50)
            // crc-32                          4 bytes
            fwrite($outputHandle, pack('VV',
                ZipConstants::DATA_DESCRIPTOR_SIG,
                (int)$entry->getRawCrc()
            ));
            // compressed size                 4 or 8 bytes
            // uncompressed size               4 or 8 bytes
            if ($entry->isZip64ExtensionsRequired()) {
                fwrite($outputHandle, PackUtil::packLongLE($compressedSize));
                fwrite($outputHandle, PackUtil::packLongLE($entry->getSize()));
            } else {
                fwrite($outputHandle, pack('VV',
                    (int)$entry->getRawCompressedSize(),
                    (int)$entry->getRawSize()
                ));
            }
        } elseif ($entry->getCompressedSize() !== $compressedSize) {
            throw new ZipException($entry->getName()
                . " (expected compressed entry size of "
                . $entry->getCompressedSize() . " bytes, but is actually " . $compressedSize . " bytes)");
        }
    }

    /**
     * Writes a Central File Header record.
     *
     * @param resource $handle Output stream.
     * @param ZipEntry $entry
     * @return bool false if and only if the record has been skipped,
     *         i.e. not written for some other reason than an I/O error.
     */
    private function writeCentralFileHeader($handle, ZipEntry $entry)
    {
        $compressedSize = $entry->getCompressedSize();
        $size = $entry->getSize();
        // This test MUST NOT include the CRC-32 because VV_AE_2 sets it to
        // UNKNOWN!
        if (ZipEntry::UNKNOWN === ($compressedSize | $size)) {
            return false;
        }

        // central file header signature   4 bytes  (0x02014b50)
        // version made by                 2 bytes
        // version needed to extract       2 bytes
        // general purpose bit flag        2 bytes
        // compression method              2 bytes
        // last mod file datetime          4 bytes
        // crc-32                          4 bytes
        // compressed size                 4 bytes
        // uncompressed size               4 bytes
        // file name length                2 bytes
        // extra field length              2 bytes
        // file comment length             2 bytes
        // disk number start               2 bytes
        // internal file attributes        2 bytes
        // external file attributes        4 bytes
        // relative offset of local header 4 bytes
        $extra = $entry->getRawExtraFields();
        $extraSize = strlen($extra);
        fwrite($handle, pack('VvvvvVVVVvvvvvVV',
            self::CENTRAL_FILE_HEADER_SIG,
            ($entry->getRawPlatform() << 8) | 63,
            $entry->getVersionNeededToExtract(),
            $entry->getGeneralPurposeBitFlags(),
            $entry->getRawMethod(),
            (int)$entry->getRawTime(),
            (int)$entry->getRawCrc(),
            (int)$entry->getRawCompressedSize(),
            (int)$entry->getRawSize(),
            strlen($entry->getName()),
            $extraSize,
            strlen($entry->getComment()),
            0,
            0,
            (int)$entry->getRawExternalAttributes(),
            (int)$entry->getRawOffset()
        ));
        // file name (variable size)
        fwrite($handle, $entry->getName());
        // extra field (variable size)
        fwrite($handle, $extra);
        // file comment (variable size)
        fwrite($handle, $entry->getComment());
        return true;
    }

    /**
     * Write end of central directory.
     *
     * @param resource $handle Output stream resource
     */
    private function writeEndOfCentralDirectory($handle)
    {
        $cdEntries = sizeof($this->entries);
        $cdOffset = $this->cdOffset;
        $cdSize = ftell($handle) - $cdOffset;
        $cdEntriesZip64 = $cdEntries > 0xffff;
        $cdSizeZip64 = $cdSize > 0xffffffff;
        $cdOffsetZip64 = $cdOffset > 0xffffffff;
        $cdEntries16 = $cdEntriesZip64 ? 0xffff : (int)$cdEntries;
        $cdSize32 = $cdSizeZip64 ? 0xffffffff : $cdSize;
        $cdOffset32 = $cdOffsetZip64 ? 0xffffffff : $cdOffset;
        $zip64 // ZIP64 extensions?
            = $cdEntriesZip64
            || $cdSizeZip64
            || $cdOffsetZip64;
        if ($zip64) {
            $zip64EndOfCentralDirectoryOffset // relative offset of the zip64 end of central directory record
                = ftell($handle);
            // zip64 end of central dir
            // signature                       4 bytes  (0x06064b50)
            fwrite($handle, pack('V', ZipConstants::ZIP64_END_OF_CENTRAL_DIRECTORY_RECORD_SIG));
            // size of zip64 end of central
            // directory record                8 bytes
            fwrite($handle, PackUtil::packLongLE(ZipConstants::ZIP64_END_OF_CENTRAL_DIRECTORY_RECORD_MIN_LEN - 12));
            // version made by                 2 bytes
            // version needed to extract       2 bytes
            //                                 due to potential use of BZIP2 compression
            // number of this disk             4 bytes
            // number of the disk with the
            // start of the central directory  4 bytes
            fwrite($handle, pack('vvVV', 63, 46, 0, 0));
            // total number of entries in the
            // central directory on this disk  8 bytes
            fwrite($handle, PackUtil::packLongLE($cdEntries));
            // total number of entries in the
            // central directory               8 bytes
            fwrite($handle, PackUtil::packLongLE($cdEntries));
            // size of the central directory   8 bytes
            fwrite($handle, PackUtil::packLongLE($cdSize));
            // offset of start of central
            // directory with respect to
            // the starting disk number        8 bytes
            fwrite($handle, PackUtil::packLongLE($cdOffset));
            // zip64 extensible data sector    (variable size)
            //
            // zip64 end of central dir locator
            // signature                       4 bytes  (0x07064b50)
            // number of the disk with the
            // start of the zip64 end of
            // central directory               4 bytes
            fwrite($handle, pack('VV', self::ZIP64_END_OF_CENTRAL_DIRECTORY_LOCATOR_SIG, 0));
            // relative offset of the zip64
            // end of central directory record 8 bytes
            fwrite($handle, PackUtil::packLongLE($zip64EndOfCentralDirectoryOffset));
            // total number of disks           4 bytes
            fwrite($handle, pack('V', 1));
        }
        // end of central dir signature    4 bytes  (0x06054b50)
        // number of this disk             2 bytes
        // number of the disk with the
        // start of the central directory  2 bytes
        // total number of entries in the
        // central directory on this disk  2 bytes
        // total number of entries in
        // the central directory           2 bytes
        // size of the central directory   4 bytes
        // offset of start of central
        // directory with respect to
        // the starting disk number        4 bytes
        // .ZIP file comment length        2 bytes
        $comment = $this->comment === null ? "" : $this->comment;
        $commentLength = strlen($comment);
        fwrite($handle, pack('VvvvvVVv',
            self::END_OF_CENTRAL_DIRECTORY_RECORD_SIG,
            0,
            0,
            $cdEntries16,
            $cdEntries16,
            (int)$cdSize32,
            (int)$cdOffset32,
            $commentLength
        ));
        if ($commentLength > 0) {
            // .ZIP file comment       (variable size)
            fwrite($handle, $comment);
        }
    }

    /**
     * Output .ZIP archive as attachment.
     * Die after output.
     *
     * @param string $outputFilename
     * @param string|null $mimeType
     * @throws IllegalArgumentException
     */
    public function outputAsAttachment($outputFilename, $mimeType = null)
    {
        $outputFilename = (string)$outputFilename;
        if (strlen($outputFilename) === 0) {
            throw new IllegalArgumentException("Output filename is empty.");
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

        header("Content-Type: " . $mimeType);
        header("Content-Disposition: attachment; filename=" . rawurlencode($outputFilename));
        header("Content-Length: " . strlen($content));
        header("Accept-Ranges: bytes");

        echo $content;
        exit;
    }

    /**
     * Returns the zip archive as a string.
     *
     * @return string
     * @throws IllegalArgumentException
     */
    public function outputAsString()
    {
        if (!($handle = fopen('php://temp', 'w+b'))) {
            throw new IllegalArgumentException("Temp file can not open from write.");
        }
        $this->saveAsStream($handle, false);
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        return $content;
    }

    /**
     * Close zip archive.
     * Release all resources.
     */
    public function close()
    {
        unset($this->entries);
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
        return isset($this->entries[$entryName]);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param string $entryName The offset to retrieve.
     * @return string|null
     */
    public function offsetGet($entryName)
    {
        return $this->offsetExists($entryName) ? $this->getEntryContent($entryName) : null;
    }

    /**
     * Offset to set. Create or modify zip entry.
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param string $entryName The offset to assign the value to.
     * @param string $uncompressedDataContent The value to set.
     * @throws IllegalArgumentException
     */
    public function offsetSet($entryName, $uncompressedDataContent)
    {
        $entryName = (string)$entryName;
        if (strlen($entryName) === 0) {
            throw new IllegalArgumentException('Entry name empty');
        }
        if ($entryName[strlen($entryName) - 1] === '/') {
            $this->addEmptyDir($entryName);
        } else {
            $this->addFromString($entryName, $uncompressedDataContent);
        }
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param string $entryName The offset to unset.
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
        next($this->entries);
    }

    /**
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        return key($this->entries);
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
        reset($this->entries);
    }
}