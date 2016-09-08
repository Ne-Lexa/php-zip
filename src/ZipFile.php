<?php
namespace Nelexa\Zip;

use Nelexa\Buffer\Buffer;
use Nelexa\Buffer\BufferException;
use Nelexa\Buffer\MathHelper;
use Nelexa\Buffer\MemoryResourceBuffer;
use Nelexa\Buffer\ResourceBuffer;
use Nelexa\Buffer\StringBuffer;

class ZipFile
{
    const SIGNATURE_LOCAL_HEADER = 0x04034b50;
    const SIGNATURE_CENTRAL_DIR = 0x02014b50;
    const SIGNATURE_END_CENTRAL_DIR = 0x06054b50;

    private static $initPwdKeys = array(305419896, 591751049, 878082192);


    /**
     * @var string
     */
    private $filename;
    /**
     * @var Buffer
     */
    private $buffer;
    /**
     * @var int
     */
    private $offsetCentralDirectory;
    /**
     * @var int
     */
    private $sizeCentralDirectory = 0;
    /**
     * @var ZipEntry[]
     */
    private $zipEntries;
    /**
     * @var string[]
     */
    private $zipEntriesIndex;
    /**
     * @var string
     */
    private $zipComment = "";
    /**
     * @var string
     */
    private $password = null;

    public function __construct()
    {

    }

    /**
     * Create zip archive
     */
    public function create()
    {
        $this->filename = null;
        $this->zipEntries = array();
        $this->zipEntriesIndex = array();
        $this->zipComment = "";
        $this->offsetCentralDirectory = 0;
        $this->sizeCentralDirectory = 0;

        $this->buffer = new MemoryResourceBuffer();
        $this->buffer->setOrder(Buffer::LITTLE_ENDIAN);
        $this->buffer->insertInt(self::SIGNATURE_END_CENTRAL_DIR);
        $this->buffer->insertString(str_repeat("\0", 18));
    }

    /**
     * Open exists zip archive
     *
     * @param string $filename
     * @throws ZipException
     */
    public function open($filename)
    {
        if (!file_exists($filename)) {
            throw new ZipException("Can not open file");
        }
        $this->filename = $filename;
        $this->openFromString(file_get_contents($this->filename));
    }

    public function openFromString($string)
    {
        $this->zipEntries = null;
        $this->zipEntriesIndex = null;
        $this->zipComment = "";
        $this->offsetCentralDirectory = null;
        $this->sizeCentralDirectory = 0;
        $this->password = null;

        $this->buffer = new StringBuffer($string);
        $this->buffer->setOrder(Buffer::LITTLE_ENDIAN);

        $this->findAndReadEndCentralDirectory();
    }

    /**
     * Set password
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * Find end central catalog
     *
     * @throws BufferException
     * @throws ZipException
     */
    private function findAndReadEndCentralDirectory()
    {
        if ($this->buffer->size() < 26) {
            return;
        }
        $this->buffer->setPosition($this->buffer->size() - 22);

        $endOfCentralDirSignature = $this->buffer->getUnsignedInt();
        if ($endOfCentralDirSignature === self::SIGNATURE_END_CENTRAL_DIR) {
            $this->readEndCentralDirectory();
        } else {
            $maximumSize = 65557;
            if ($this->buffer->size() < $maximumSize) {
                $maximumSize = $this->buffer->size();
            }
            $this->buffer->skip(-$maximumSize);
            $bytes = 0x00000000;
            while ($this->buffer->hasRemaining()) {
                $byte = $this->buffer->getUnsignedByte();
                $bytes = (($bytes & 0xFFFFFF) << 8) | $byte;

                if ($bytes === 0x504b0506) {
                    $this->readEndCentralDirectory();
                    return;
                }
            }
            throw new ZipException("Unable to find End of Central Dir Record signature");
        }
    }

    /**
     * Read end central catalog
     *
     * @throws BufferException
     * @throws ZipException
     */
    private function readEndCentralDirectory()
    {
        $this->buffer->skip(4); // number of this disk AND number of the disk with the start of the central directory
        $countFiles = $this->buffer->getUnsignedShort();
        $this->buffer->skip(2); // total number of entries in the central directory
        $this->sizeCentralDirectory = $this->buffer->getUnsignedInt();
        $this->offsetCentralDirectory = $this->buffer->getUnsignedInt();
        $zipCommentLength = $this->buffer->getUnsignedShort();
        $this->zipComment = $this->buffer->getString($zipCommentLength);

        $this->buffer->setPosition($this->offsetCentralDirectory);

        $this->zipEntries = array();
        $this->zipEntriesIndex = array();

        for ($i = 0; $i < $countFiles; $i++) {
            $offsetOfCentral = $this->buffer->position() - $this->offsetCentralDirectory;

            $zipEntry = new ZipEntry();
            $zipEntry->readCentralHeader($this->buffer);
            $zipEntry->setOffsetOfCentral($offsetOfCentral);

            $this->zipEntries[$i] = $zipEntry;
            $this->zipEntriesIndex[$zipEntry->getName()] = $i;
        }
    }

    /**
     * @return int
     */
    public function getCountFiles()
    {
        return $this->zipEntries === null ? 0 : sizeof($this->zipEntries);
    }

    /**
     * Add empty directory in zip archive
     *
     * @param string $dirName
     * @return bool
     * @throws ZipException
     */
    public function addEmptyDir($dirName)
    {
        if ($dirName === null) {
            throw new ZipException("dirName null");
        }
        $dirName = rtrim($dirName, '/') . '/';
        if (isset($this->zipEntriesIndex[$dirName])) {
            return true;
        }
        $zipEntry = new ZipEntry();
        $zipEntry->setName($dirName);
        $zipEntry->setCompressionMethod(0);
        $zipEntry->setLastModDateTime(time());
        $zipEntry->setCrc32(0);
        $zipEntry->setCompressedSize(0);
        $zipEntry->setUnCompressedSize(0);
        $zipEntry->setOffsetOfLocal($this->offsetCentralDirectory);

        $this->buffer->setPosition($zipEntry->getOffsetOfLocal());
        $bufferLocal = $zipEntry->writeLocalHeader();
        $this->buffer->insert($bufferLocal);
        $this->offsetCentralDirectory += $bufferLocal->size();

        $zipEntry->setOffsetOfCentral($this->sizeCentralDirectory);
        $this->buffer->setPosition($this->offsetCentralDirectory + $zipEntry->getOffsetOfCentral());
        $bufferCentral = $zipEntry->writeCentralHeader();
        $this->buffer->insert($bufferCentral);
        $this->sizeCentralDirectory += $bufferCentral->size();

        $this->zipEntries[] = $zipEntry;
        end($this->zipEntries);
        $this->zipEntriesIndex[$zipEntry->getName()] = key($this->zipEntries);

        $size = $this->getCountFiles();
        $this->buffer->setPosition($this->offsetCentralDirectory + $this->sizeCentralDirectory + 8);
//        $signature = $this->buffer->getUnsignedInt();
//        if ($signature !== self::SIGNATURE_END_CENTRAL_DIR) {
//            throw new ZipException("error position end central dir");
//        }
//        $this->buffer->skip(4);
        $this->buffer->putShort($size);
        $this->buffer->putShort($size);
        $this->buffer->putInt($this->sizeCentralDirectory);
        $this->buffer->putInt($this->offsetCentralDirectory);
        return true;
    }

    /**
     * @param string $inDirectory
     * @param string|null $addPath
     * @param array $ignoreFiles
     * @return bool
     * @throws ZipException
     */
    public function addDir($inDirectory, $addPath = null, array $ignoreFiles = array())
    {
        if ($inDirectory === null) {
            throw new ZipException("dirName null");
        }
        if (!file_exists($inDirectory)) {
            throw new ZipException("directory not found");
        }
        if (!is_dir($inDirectory)) {
            throw new ZipException("input directory is not directory");
        }
        if ($addPath !== null && is_string($addPath) && !empty($addPath)) {
            $addPath = rtrim($addPath, '/');
        } else {
            $addPath = "";
        }
        $inDirectory = rtrim($inDirectory, '/');

        $iterator = new FilterFileIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($inDirectory)), $ignoreFiles);
        $files = iterator_to_array($iterator, false);

        $count = $this->getCountFiles();
        /**
         * @var \SplFileInfo $file
         */
        foreach ($files as $file) {
            if ($file->getFilename() === '.') {
                $filename = dirname(str_replace($inDirectory, $addPath, $file));
                $this->isEmptyDir($file) && $this->addEmptyDir($filename);
            } else if ($file->isFile()) {
                $filename = str_replace($inDirectory, $addPath, $file);
                $this->addFile($file, $filename);
            }
        }
        return $this->getCountFiles() > $count;
    }

    public function addGlob($pattern, $removePath = null, $addPath = null, $recursive = true)
    {
        if ($pattern === null) {
            throw new ZipException("pattern null");
        }
        $glob = $this->globFileSearch($pattern, GLOB_BRACE, $recursive);
        if ($glob === FALSE || empty($glob)) {
            return false;
        }
        if (!empty($addPath) && is_string($addPath)) {
            $addPath = rtrim($addPath, '/');
        } else {
            $addPath = "";
        }
        if (!empty($removePath) && is_string($removePath)) {
            $removePath = rtrim($removePath, '/');
        } else {
            $removePath = "";
        }

        $count = $this->getCountFiles();
        /**
         * @var string $file
         */
        foreach ($glob as $file) {
            if (is_dir($file)) {
                $filename = str_replace($addPath, $removePath, $file);
                $this->isEmptyDir($file) && $this->addEmptyDir($filename);
            } else if (is_file($file)) {
                $filename = str_replace($removePath, $addPath, $file);
                $this->addFile($file, $filename);
            }
        }
        return $this->getCountFiles() > $count;
    }

    public function addPattern($pattern, $inDirectory, $addPath = null, $recursive = true)
    {
        if ($pattern === null) {
            throw new ZipException("pattern null");
        }
        $files = $this->regexFileSearch($inDirectory, $pattern, $recursive);
        if ($files === FALSE || empty($files)) {
            return false;
        }
        if (!empty($addPath) && is_string($addPath)) {
            $addPath = rtrim($addPath, '/');
        } else {
            $addPath = "";
        }
        $inDirectory = rtrim($inDirectory, '/');

        $count = $this->getCountFiles();
        /**
         * @var string $file
         */
        foreach ($files as $file) {
            if (is_dir($file)) {
                $filename = str_replace($addPath, $inDirectory, $file);
                $this->isEmptyDir($file) && $this->addEmptyDir($filename);
            } else if (is_file($file)) {
                $filename = str_replace($inDirectory, $addPath, $file);
                $this->addFile($file, $filename);
            }
        }
        return $this->getCountFiles() > $count;
    }

    private function globFileSearch($pattern, $flags = 0, $recursive = true)
    {
        $files = glob($pattern, $flags);
        if (!$recursive) return $files;
        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = array_merge($files, $this->globFileSearch($dir . '/' . basename($pattern), $flags, $recursive));
        }
        return $files;
    }

    private function regexFileSearch($folder, $pattern, $recursive = true)
    {
        $dir = $recursive ? new \RecursiveDirectoryIterator($folder) : new \DirectoryIterator($folder);
        $ite = $recursive ? new \RecursiveIteratorIterator($dir) : new \IteratorIterator($dir);
        $files = new \RegexIterator($ite, $pattern, \RegexIterator::GET_MATCH);
        $fileList = array();
        foreach ($files as $file) {
            $fileList = array_merge($fileList, $file);
        }
        return $fileList;
    }

    private function isEmptyDir($dir)
    {
        if (!is_readable($dir)) return false;
        return (count(scandir($dir)) == 2);
    }

    /**
     * Add file in zip archive
     *
     * @param string $filename
     * @param string|null $localName
     * @param int|null $compressionMethod
     * @throws ZipException
     */
    public function addFile($filename, $localName = NULL, $compressionMethod = null)
    {
        if ($filename === null) {
            throw new ZipException("filename null");
        }
        if (!file_exists($filename)) {
            throw new ZipException("file not found");
        }
        if (!is_file($filename)) {
            throw new ZipException("input filename is not file");
        }
        if ($localName === null) {
            $localName = basename($filename);
        }
        $this->addFromString($localName, file_get_contents($filename), $compressionMethod);
    }

    /**
     * @param string $localName
     * @param string $contents
     * @param int|null $compressionMethod
     * @throws ZipException
     */
    public function addFromString($localName, $contents, $compressionMethod = null)
    {
        if ($localName === null || !is_string($localName) || strlen($localName) === 0) {
            throw new ZipException("local name empty");
        }
        if ($contents === null) {
            throw new ZipException("contents null");
        }
        $unCompressedSize = strlen($contents);
        $compress = null;
        if ($compressionMethod === null) {
            if ($unCompressedSize === 0) {
                $compressionMethod = ZipEntry::COMPRESS_METHOD_STORED;
            } else {
                $compressionMethod = ZipEntry::COMPRESS_METHOD_DEFLATED;
            }
        }
        switch ($compressionMethod) {
            case ZipEntry::COMPRESS_METHOD_STORED:
                $compress = $contents;
                break;
            case ZipEntry::COMPRESS_METHOD_DEFLATED:
                $compress = gzdeflate($contents);
                break;
            default:
                throw new ZipException("Compression method not support");
        }
        $crc32 = sprintf('%u', crc32($contents));
        $compressedSize = strlen($compress);

        if (isset($this->zipEntriesIndex[$localName])) {
            /**
             * @var int $index
             */
            $index = $this->zipEntriesIndex[$localName];
            $zipEntry = &$this->zipEntries[$index];

            $oldCompressedSize = $zipEntry->getCompressedSize();

            $zipEntry->setCompressionMethod($compressionMethod);
            $zipEntry->setLastModDateTime(time());
            $zipEntry->setCompressedSize($compressedSize);
            $zipEntry->setUnCompressedSize($unCompressedSize);
            $zipEntry->setCrc32($crc32);

            $this->buffer->setPosition($zipEntry->getOffsetOfLocal() + 8);
            $this->buffer->putShort($zipEntry->getCompressionMethod());
            $this->buffer->putShort($zipEntry->getLastModifyDosTime());
            $this->buffer->putShort($zipEntry->getLastModifyDosDate());
            if ($zipEntry->hasDataDescriptor()) {
                $this->buffer->skip(12);
            } else {
                $this->buffer->putInt($zipEntry->getCrc32());
                $this->buffer->putInt($zipEntry->getCompressedSize());
                $this->buffer->putInt($zipEntry->getUnCompressedSize());
            }
            $this->buffer->skip(4 + strlen($zipEntry->getName()) + strlen($zipEntry->getExtraLocal()));
            $this->buffer->replaceString($compress, $oldCompressedSize);

            if ($zipEntry->hasDataDescriptor()) {
                $this->buffer->put($zipEntry->writeDataDescriptor());
            }

            $diff = $oldCompressedSize - $zipEntry->getCompressedSize();
            if ($diff !== 0) {
                $this->offsetCentralDirectory -= $diff;
            }
            $this->buffer->setPosition($this->offsetCentralDirectory + $zipEntry->getOffsetOfCentral() + 10);
            $this->buffer->putShort($zipEntry->getCompressionMethod());
            $this->buffer->putShort($zipEntry->getLastModifyDosTime());
            $this->buffer->putShort($zipEntry->getLastModifyDosDate());
            $this->buffer->putInt($zipEntry->getCrc32());
            $this->buffer->putInt($zipEntry->getCompressedSize());
            $this->buffer->putInt($zipEntry->getUnCompressedSize());

            if ($diff !== 0) {
                $this->buffer->skip(18 + strlen($zipEntry->getName()) + strlen($zipEntry->getExtraCentral()) + strlen($zipEntry->getComment()));

                $size = $this->getCountFiles();
                /**
                 * @var ZipEntry $entry
                 */
                for ($i = $index + 1; $i < $size; $i++) {
                    $zipEntry = &$this->zipEntries[$i];

                    $zipEntry->setOffsetOfLocal($zipEntry->getOffsetOfLocal() - $diff);
                    $this->buffer->setPosition($this->offsetCentralDirectory + $zipEntry->getOffsetOfCentral() + 42);
//                $this->buffer->setPosition($this->offsetCentralDirectory + $zipEntry->getOffsetOfCentral());
//                $sig = $this->buffer->getUnsignedInt();
//                if ($sig !== self::SIGNATURE_CENTRAL_DIR) {
//                    $this->buffer->skip(-4);
//                    throw new ZipException("Signature central dir corrupt. Bad signature = 0x" . dechex($sig) . "; Current entry: " . $entry->getName());
//                }
//                $this->buffer->skip(38);
                    $this->buffer->putInt($zipEntry->getOffsetOfLocal());
                }

                $this->buffer->setPosition($this->offsetCentralDirectory + $this->sizeCentralDirectory + 12);
//                $signature = $this->buffer->getUnsignedInt();
//                if ($signature !== self::SIGNATURE_END_CENTRAL_DIR) {
//                    throw new ZipException("error position end central dir");
//                }
//                $this->buffer->skip(8);
                $this->buffer->putInt($this->sizeCentralDirectory);
                $this->buffer->putInt($this->offsetCentralDirectory);
            }
        } else {
            $zipEntry = new ZipEntry();
//            if ($flagBit > 0) $zipEntry->setFlagBit($flagBit);
            $zipEntry->setName($localName);
            $zipEntry->setCompressionMethod($compressionMethod);
            $zipEntry->setLastModDateTime(time());
            $zipEntry->setCrc32($crc32);
            $zipEntry->setCompressedSize($compressedSize);
            $zipEntry->setUnCompressedSize($unCompressedSize);
            $zipEntry->setOffsetOfLocal($this->offsetCentralDirectory);

            $bufferLocal = $zipEntry->writeLocalHeader();
            $bufferLocal->insertString($compress);
            if ($zipEntry->hasDataDescriptor()) {
                $bufferLocal->insert($zipEntry->writeDataDescriptor());
            }

            $this->buffer->setPosition($zipEntry->getOffsetOfLocal());
            $this->buffer->insert($bufferLocal);
            $this->offsetCentralDirectory += $bufferLocal->size();

            $zipEntry->setOffsetOfCentral($this->sizeCentralDirectory);
            $this->buffer->setPosition($this->offsetCentralDirectory + $zipEntry->getOffsetOfCentral());
            $bufferCentral = $zipEntry->writeCentralHeader();
            $this->buffer->insert($bufferCentral);
            $this->sizeCentralDirectory += $bufferCentral->size();

            $this->zipEntries[] = $zipEntry;
            end($this->zipEntries);
            $this->zipEntriesIndex[$zipEntry->getName()] = key($this->zipEntries);

            $size = $this->getCountFiles();

            $this->buffer->setPosition($this->offsetCentralDirectory + $this->sizeCentralDirectory + 8);
//            $signature = $this->buffer->getUnsignedInt();
//            if ($signature !== self::SIGNATURE_END_CENTRAL_DIR) {
//                throw new ZipException("error position end central dir");
//            }
//            $this->buffer->skip(4);
            $this->buffer->putShort($size);
            $this->buffer->putShort($size);
            $this->buffer->putInt($this->sizeCentralDirectory);
            $this->buffer->putInt($this->offsetCentralDirectory);
        }
    }

    /**
     * Update timestamp archive for all files
     *
     * @param int|null $timestamp
     * @throws BufferException
     */
    public function updateTimestamp($timestamp = null)
    {
        if ($timestamp === null || !is_int($timestamp)) {
            $timestamp = time();
        }
        foreach ($this->zipEntries AS $entry) {
            $entry->setLastModDateTime($timestamp);
            $this->buffer->setPosition($entry->getOffsetOfLocal() + 10);
            $this->buffer->putShort($entry->getLastModifyDosTime());
            $this->buffer->putShort($entry->getLastModifyDosDate());

            $this->buffer->setPosition($this->offsetCentralDirectory + $entry->getOffsetOfCentral() + 12);
            $this->buffer->putShort($entry->getLastModifyDosTime());
            $this->buffer->putShort($entry->getLastModifyDosDate());
        }
    }

    public function deleteGlob($pattern)
    {
        if ($pattern === null) {
            throw new ZipException("pattern null");
        }
        $pattern = '~' . $this->convertGlobToRegEx($pattern) . '~si';
        return $this->deletePattern($pattern);
    }

    public function deletePattern($pattern)
    {
        if ($pattern === null) {
            throw new ZipException("pattern null");
        }
        $offsetLocal = 0;
        $offsetCentral = 0;
        $modify = false;
        foreach ($this->zipEntries AS $index => &$entry) {
            if (preg_match($pattern, $entry->getName())) {
                $this->buffer->setPosition($entry->getOffsetOfLocal() - $offsetLocal);
                $lengthLocal = $entry->getLengthOfLocal();
                $this->buffer->remove($lengthLocal);
                $offsetLocal += $lengthLocal;

                $this->offsetCentralDirectory -= $lengthLocal;

                $this->buffer->setPosition($this->offsetCentralDirectory + $entry->getOffsetOfCentral() - $offsetCentral);
                $lengthCentral = $entry->getLengthOfCentral();
                $this->buffer->remove($lengthCentral);
                $offsetCentral += $lengthCentral;

                $this->sizeCentralDirectory -= $lengthCentral;

                unset($this->zipEntries[$index], $this->zipEntriesIndex[$entry->getName()]);
                $modify = true;
                continue;
            }
            if ($modify) {
                $entry->setOffsetOfLocal($entry->getOffsetOfLocal() - $offsetLocal);
                $entry->setOffsetOfCentral($entry->getOffsetOfCentral() - $offsetCentral);
                $this->buffer->setPosition($this->offsetCentralDirectory + $entry->getOffsetOfCentral() + 42);
                $this->buffer->putInt($entry->getOffsetOfLocal());
            }
        }
        if ($modify) {
            $size = $this->getCountFiles();
            $this->buffer->setPosition($this->offsetCentralDirectory + $this->sizeCentralDirectory + 8);
//        $signature = $this->buffer->getUnsignedInt();
//        if ($signature !== self::SIGNATURE_END_CENTRAL_DIR) {
//            throw new ZipException("error position end central dir");
//        }
//        $this->buffer->skip(4);
            $this->buffer->putShort($size);
            $this->buffer->putShort($size);
            $this->buffer->putInt($this->sizeCentralDirectory);
            $this->buffer->putInt($this->offsetCentralDirectory);
            return true;
        }
        return false;
    }

    /**
     * @param int $index
     * @return bool
     * @throws ZipException
     */
    public function deleteIndex($index)
    {
        if ($index === null || !is_numeric($index)) {
            throw new ZipException("index no numeric");
        }
        if (!isset($this->zipEntries[$index])) {
            return false;
        }

        $entry = $this->zipEntries[$index];

        $offsetCentral = $entry->getOffsetOfCentral();
        $lengthCentral = $entry->getLengthOfCentral();

        $offsetLocal = $entry->getOffsetOfLocal();
        $lengthLocal = $entry->getLengthOfLocal();

        unset(
            $this->zipEntries[$index],
            $this->zipEntriesIndex[$entry->getName()]
        );
        $this->zipEntries = array_values($this->zipEntries);
        $this->zipEntriesIndex = array_flip(array_keys($this->zipEntriesIndex));

        $size = $this->getCountFiles();

        $this->buffer->setPosition($this->offsetCentralDirectory + $offsetCentral);
        $this->buffer->remove($lengthCentral);

        $this->buffer->setPosition($offsetLocal);
        $this->buffer->remove($lengthLocal);

        $this->offsetCentralDirectory -= $lengthLocal;
        $this->sizeCentralDirectory -= $lengthCentral;

        /**
         * @var ZipEntry $entry
         */
        for ($i = $index; $i < $size; $i++) {
            $entry = &$this->zipEntries[$i];

            $entry->setOffsetOfLocal($entry->getOffsetOfLocal() - $lengthLocal);
//            $this->buffer->setPosition($entry->getOffsetOfLocal());
//            $sig = $this->buffer->getUnsignedInt();
//            if ($sig !== self::SIGNATURE_LOCAL_HEADER) {
//                throw new ZipException("Signature local header corrupt");
//            }
            $entry->setOffsetOfCentral($entry->getOffsetOfCentral() - $lengthCentral);

            $this->buffer->setPosition($this->offsetCentralDirectory + $entry->getOffsetOfCentral() + 42);
//            $this->buffer->setPosition($this->offsetCentralDirectory + $entry->getOffsetOfCentral());
//            $sig = $this->buffer->getUnsignedInt();
//            if ($sig !== self::SIGNATURE_CENTRAL_DIR) {
//                $this->buffer->skip(-4);
//                throw new ZipException("Signature central dir corrupt. Bad signature = 0x" . dechex($sig) . "; Current entry: " . $entry->getName());
//            }
//            $this->buffer->skip(38);
            $this->buffer->putInt($entry->getOffsetOfLocal());
        }

        $this->buffer->setPosition($this->offsetCentralDirectory + $this->sizeCentralDirectory + 8);
//        $signature = $this->buffer->getUnsignedInt();
//        if ($signature !== self::SIGNATURE_END_CENTRAL_DIR) {
//            throw new ZipException("error position end central dir");
//        }
//        $this->buffer->skip(4);
        $this->buffer->putShort($size);
        $this->buffer->putShort($size);
        $this->buffer->putInt($this->sizeCentralDirectory);
        $this->buffer->putInt($this->offsetCentralDirectory);
        return true;
    }

    public function deleteAll()
    {
        $this->zipEntries = array();
        $this->zipEntriesIndex = array();
        $this->offsetCentralDirectory = 0;
        $this->sizeCentralDirectory = 0;

        $this->buffer->truncate();
        $this->buffer->insertInt(self::SIGNATURE_END_CENTRAL_DIR);
        $this->buffer->insertString(str_repeat("\0", 18));
    }

    /**
     * @param $name
     * @return bool
     * @throws ZipException
     */
    public function deleteName($name)
    {
        if (empty($name)) {
            throw new ZipException("name is empty");
        }
        if (!isset($this->zipEntriesIndex[$name])) {
            return false;
        }
        $index = $this->zipEntriesIndex[$name];
        return $this->deleteIndex($index);
    }

    /**
     * @param string $destination
     * @param array $entries
     * @return bool
     * @throws ZipException
     */
    public function extractTo($destination, array $entries = null)
    {
        if ($this->zipEntries === NULL) {
            throw new ZipException("zip entries not initial");
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
         * @var ZipEntry[] $zipEntries
         */
        if ($entries !== null && is_array($entries) && !empty($entries)) {
            $flipEntries = array_flip($entries);
            $zipEntries = array_filter($this->zipEntries, function ($zipEntry) use ($flipEntries) {
                /**
                 * @var ZipEntry $zipEntry
                 */
                return isset($flipEntries[$zipEntry->getName()]);
            });
        } else {
            $zipEntries = $this->zipEntries;
        }

        $extract = 0;
        foreach ($zipEntries AS $entry) {
            $file = $destination . '/' . $entry->getName();
            $dir = dirname($file);
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new ZipException("Can not create dir " . $dir);
                }
                chmod($dir, 0755);
            }
            if ($entry->isDirectory()) {
                continue;
            }
            if (file_put_contents($file, $this->getEntryBytes($entry)) === FALSE) {
                return false;
            }
            touch($file, $entry->getLastModDateTime());
            $extract++;
        }
        return $extract > 0;
    }

    /**
     * @param ZipEntry $entry
     * @return string
     * @throws BufferException
     * @throws ZipException
     */
    private function getEntryBytes(ZipEntry $entry)
    {
        $this->buffer->setPosition($entry->getOffsetOfLocal() + $entry->getLengthLocalHeader());
//        $this->buffer->setPosition($entry->getOffsetOfLocal());
//        $signature = $this->buffer->getUnsignedInt();
//        if ($signature !== self::SIGNATURE_LOCAL_HEADER) {
//            throw new ZipException("Can not read entry " . $entry->getName());
//        }
//        $this->buffer->skip($entry->getLengthLocalHeader() - 4);

        $string = $this->buffer->getString($entry->getCompressedSize());

        if ($entry->isEncrypted()) {
            if (empty($this->password)) {
                throw new ZipException("need password archive");
            }

            $pwdKeys = self::$initPwdKeys;

            $bufPass = new StringBuffer($this->password);
            while ($bufPass->hasRemaining()) {
                $byte = $bufPass->getUnsignedByte();
                $pwdKeys = ZipUtils::updateKeys($byte, $pwdKeys);
            }
            unset($bufPass);

            $keys = $pwdKeys;

            $strBuffer = new StringBuffer($string);
            for ($i = 0; $i < ZipUtils::DECRYPT_HEADER_SIZE; $i++) {
                $result = $strBuffer->getUnsignedByte();
                $lastValue = $result ^ ZipUtils::decryptByte($keys[2]);
                $keys = ZipUtils::updateKeys($lastValue, $keys);
            }

            $string = "";
            while ($strBuffer->hasRemaining()) {
                $result = $strBuffer->getUnsignedByte();
                $result = ($result ^ ZipUtils::decryptByte($keys[2])) & 0xff;
                $keys = ZipUtils::updateKeys(MathHelper::castToByte($result), $keys);
                $string .= chr($result);
            }
            unset($strBuffer);
        }

        switch ($entry->getCompressionMethod()) {
            case ZipEntry::COMPRESS_METHOD_DEFLATED:
                $string = @gzinflate($string);
                break;
            case ZipEntry::COMPRESS_METHOD_STORED:
                break;
            default:
                throw new ZipException("Compression method " . $entry->compressionMethodToString() . " not support!");
        }
        $expectedCrc = sprintf('%u', crc32($string));
        if ($expectedCrc != $entry->getCrc32()) {
            if ($entry->isEncrypted()) {
                throw new ZipException("Wrong password");
            }
            throw new ZipException("File " . $entry->getName() . ' corrupt. Bad CRC ' . dechex($expectedCrc) . '  (should be ' . dechex($entry->getCrc32()) . ')');
        }
        return $string;
    }

    /**
     * @return string
     */
    public function getArchiveComment()
    {
        return $this->zipComment;
    }

    /**
     * @param $index
     * @return string
     * @throws ZipException
     */
    public function getCommentIndex($index)
    {
        if (!isset($this->zipEntries[$index])) {
            throw new ZipException("File for index " . $index . " not found");
        }
        return $this->zipEntries[$index]->getComment();
    }

    /**
     * @param string $name
     * @return string
     * @throws ZipException
     */
    public function getCommentName($name)
    {
        if (!isset($this->zipEntriesIndex[$name])) {
            throw new ZipException("File for name " . $name . " not found");
        }
        $index = $this->zipEntriesIndex[$name];
        return $this->getCommentIndex($index);
    }

    /**
     * @param int $index
     * @return string
     * @throws ZipException
     */
    public function getFromIndex($index)
    {
        if (!isset($this->zipEntries[$index])) {
            throw new ZipException("File for index " . $index . " not found");
        }
        return $this->getEntryBytes($this->zipEntries[$index]);
    }

    /**
     * @param string $name
     * @return string
     * @throws ZipException
     */
    public function getFromName($name)
    {
        if (!isset($this->zipEntriesIndex[$name])) {
            throw new ZipException("File for name " . $name . " not found");
        }
        $index = $this->zipEntriesIndex[$name];
        return $this->getEntryBytes($this->zipEntries[$index]);
    }

    /**
     * @param int $index
     * @return string
     * @throws ZipException
     */
    public function getNameIndex($index)
    {
        if (!isset($this->zipEntries[$index])) {
            throw new ZipException("File for index " . $index . " not found");
        }
        return $this->zipEntries[$index]->getName();
    }

    /**
     * @param string $name
     * @return bool|string
     */
    public function locateName($name)
    {
        return isset($this->zipEntriesIndex[$name]) ? $this->zipEntriesIndex[$name] : false;
    }

    /**
     * @param int $index
     * @param string $newName
     * @return bool
     * @throws ZipException
     */
    public function renameIndex($index, $newName)
    {
        if (!isset($this->zipEntries[$index])) {
            throw new ZipException("File for index " . $index . " not found");
        }
        $lengthNewName = strlen($newName);
        if (strlen($lengthNewName) > 0xFF) {
            throw new ZipException("Length new name is very long. Maximum size 255");
        }
        $entry = &$this->zipEntries[$index];
        if ($entry->getName() === $newName) {
            return true;
        }
        if (isset($this->zipEntriesIndex[$newName])) {
            return false;
        }

        $lengthOldName = strlen($entry->getName());

        $this->buffer->setPosition($entry->getOffsetOfLocal() + 26);
        $this->buffer->putShort($lengthNewName);
        $this->buffer->skip(2);
        if ($lengthOldName === $lengthNewName) {
            $this->buffer->putString($newName);
            $this->buffer->setPosition($this->offsetCentralDirectory + $entry->getOffsetOfCentral() + 46);
            $this->buffer->putString($newName);
        } else {
            $this->buffer->replaceString($newName, $lengthOldName);
            $diff = $lengthOldName - $lengthNewName;

            $this->offsetCentralDirectory -= $diff;

            $this->buffer->setPosition($this->offsetCentralDirectory + $entry->getOffsetOfCentral() + 28);
            $this->buffer->putShort($lengthNewName);
            $this->buffer->skip(16);
            $this->buffer->replaceString($newName, $lengthOldName);
            $this->sizeCentralDirectory -= $diff;

            $size = $this->getCountFiles();
            for ($i = $index + 1; $i < $size; $i++) {
                $zipEntry = &$this->zipEntries[$i];
                $zipEntry->setOffsetOfLocal($zipEntry->getOffsetOfLocal() - $diff);
                $zipEntry->setOffsetOfCentral($zipEntry->getOffsetOfCentral() - $diff);
                $this->buffer->setPosition($this->offsetCentralDirectory + $zipEntry->getOffsetOfCentral() + 42);
//                $this->buffer->setPosition($this->offsetCentralDirectory + $zipEntry->getOffsetOfCentral());
//                $sig = $this->buffer->getUnsignedInt();
//                if ($sig !== self::SIGNATURE_CENTRAL_DIR) {
//                    $this->buffer->skip(-4);
//                    throw new ZipException("Signature central dir corrupt. Bad signature = 0x" . dechex($sig) . "; Current entry: " . $entry->getName());
//                }
//                $this->buffer->skip(38);
                $this->buffer->putInt($zipEntry->getOffsetOfLocal());
            }

            $this->buffer->setPosition($this->offsetCentralDirectory + $this->sizeCentralDirectory + 12);
//            $this->buffer->setPosition($this->offsetCentralDirectory + $this->sizeCentralDirectory);
//            $signature = $this->buffer->getUnsignedInt();
//            if ($signature !== self::SIGNATURE_END_CENTRAL_DIR) {
//                throw new ZipException("error position end central dir");
//            }
//            $this->buffer->skip(8);
            $this->buffer->putInt($this->sizeCentralDirectory);
            $this->buffer->putInt($this->offsetCentralDirectory);
        }
        $entry->setName($newName);
        return true;
    }

    /**
     * @param string $name
     * @param string $newName
     * @return bool
     * @throws ZipException
     */
    public function renameName($name, $newName)
    {
        if (!isset($this->zipEntriesIndex[$name])) {
            throw new ZipException("File for name " . $name . " not found");
        }
        $index = $this->zipEntriesIndex[$name];
        return $this->renameIndex($index, $newName);
    }

    /**
     * @param string $comment
     * @return bool
     * @throws ZipException
     */
    public function setArchiveComment($comment)
    {
        if ($comment === null) {
            return false;
        }
        if ($comment === $this->zipComment) {
            return true;
        }
        $currentCommentLength = strlen($this->zipComment);
        $commentLength = strlen($comment);
        if ($commentLength > 0xffff) {
            $commentLength = 0xffff;
            $comment = substr($comment, 0, $commentLength);
        }

        $this->buffer->setPosition($this->offsetCentralDirectory + $this->sizeCentralDirectory + 20);
//        $signature = $this->buffer->getUnsignedInt();
//        if ($signature !== self::SIGNATURE_END_CENTRAL_DIR) {
//            throw new ZipException("error position end central dir");
//        }
//        $this->buffer->skip(16);
        $this->buffer->putShort($commentLength);
        $this->buffer->replaceString($comment, $currentCommentLength);

        $this->zipComment = $comment;
        return true;
    }

    /**
     * Set the comment of an entry defined by its index
     *
     * @param int $index
     * @param string $comment
     * @return bool
     * @throws ZipException
     */
    public function setCommentIndex($index, $comment)
    {
        if (!isset($this->zipEntries[$index])) {
            throw new ZipException("File for index " . $index . " not found");
        }
        if ($comment === null) {
            return false;
        }
        $newCommentLength = strlen($comment);
        if ($newCommentLength > 0xffff) {
            $newCommentLength = 0xffff;
            $comment = substr($comment, 0, $newCommentLength);
        }
        $entry = &$this->zipEntries[$index];
        $oldComment = $entry->getComment();
        $oldCommentLength = strlen($oldComment);
        $this->buffer->setPosition($this->offsetCentralDirectory + $entry->getOffsetOfCentral() + 32);

        $this->buffer->putShort($newCommentLength);
        $this->buffer->skip(12 + strlen($entry->getName()) + strlen($entry->getExtraCentral()));

        if ($oldCommentLength === $newCommentLength) {
            $this->buffer->putString($comment);
        } else {
            $this->buffer->replaceString($comment, $oldCommentLength);
            $diff = $oldCommentLength - $newCommentLength;

            $this->sizeCentralDirectory -= $diff;
            $size = $this->getCountFiles();
            /**
             * @var ZipEntry $entry
             */
            for ($i = $index + 1; $i < $size; $i++) {
                $zipEntry = &$this->zipEntries[$i];
                $zipEntry->setOffsetOfCentral($zipEntry->getOffsetOfCentral() - $diff);
            }

            $this->buffer->setPosition($this->offsetCentralDirectory + $this->sizeCentralDirectory + 12);
//            $signature = $this->buffer->getUnsignedInt();
//            if ($signature !== self::SIGNATURE_END_CENTRAL_DIR) {
//                throw new ZipException("error position end central dir");
//            }
//            $this->buffer->skip(8);
            $this->buffer->putInt($this->sizeCentralDirectory);
        }
        $entry->setComment($comment);
        return true;
    }

    /**
     * @return ZipEntry[]
     */
    public function getZipEntries()
    {
        return $this->zipEntries;
    }

    /**
     * @param int $index
     * @return ZipEntry|bool
     */
    public function getZipEntryIndex($index)
    {
        return isset($this->zipEntries[$index]) ? $this->zipEntries[$index] : false;
    }

    /**
     * @param string $name
     * @return ZipEntry|bool
     */
    public function getZipEntryName($name)
    {
        return isset($this->zipEntriesIndex[$name]) ? $this->zipEntries[$this->zipEntriesIndex[$name]] : false;
    }

    /**
     * Set the comment of an entry defined by its name
     *
     * @param string $name
     * @param string $comment
     * @return bool
     * @throws ZipException
     */
    public function setCommentName($name, $comment)
    {
        if (!isset($this->zipEntriesIndex[$name])) {
            throw new ZipException("File for name " . $name . " not found");
        }
        $index = $this->zipEntriesIndex[$name];
        return $this->setCommentIndex($index, $comment);
    }

    /**
     * @param $index
     * @return array
     * @throws ZipException
     */
    public function statIndex($index)
    {
        if (!isset($this->zipEntries[$index])) {
            throw new ZipException("File for index " . $index . " not found");
        }
        $entry = $this->zipEntries[$index];
        return array(
            'name' => $entry->getName(),
            'index' => $index,
            'crc' => $entry->getCrc32(),
            'size' => $entry->getUnCompressedSize(),
            'mtime' => $entry->getLastModDateTime(),
            'comp_size' => $entry->getCompressedSize(),
            'comp_method' => $entry->getCompressionMethod()
        );
    }

    /**
     * @param string $name
     * @return array
     * @throws ZipException
     */
    public function statName($name)
    {
        if (!isset($this->zipEntriesIndex[$name])) {
            throw new ZipException("File for name " . $name . " not found");
        }
        $index = $this->zipEntriesIndex[$name];
        return $this->statIndex($index);
    }

    public function getListFiles()
    {
        return array_flip($this->zipEntriesIndex);
    }

    /**
     * @return array
     */
    public function getExtendedListFiles()
    {

        return array_map(function ($index, $entry) {
            /**
             * @var ZipEntry $entry
             * @var int $index
             */
            return array(
                'name' => $entry->getName(),
                'index' => $index,
                'crc' => $entry->getCrc32(),
                'size' => $entry->getUnCompressedSize(),
                'mtime' => $entry->getLastModDateTime(),
                'comp_size' => $entry->getUnCompressedSize(),
                'comp_method' => $entry->getCompressionMethod()
            );
        }, array_keys($this->zipEntries), $this->zipEntries);
    }

    public function output()
    {
        return $this->buffer->toString();
    }

    /**
     * @param string $file
     * @return bool
     */
    public function saveAs($file)
    {
        return file_put_contents($file, $this->output()) !== false;
    }

    /**
     * @return bool
     */
    public function save()
    {
        if ($this->filename !== NULL) {
            return file_put_contents($this->filename, $this->output()) !== false;
        }
        return false;
    }

    public function close()
    {
        if ($this->buffer !== null) {
            ($this->buffer instanceof ResourceBuffer) && $this->buffer->close();
        }
        $this->zipEntries = null;
        $this->zipEntriesIndex = null;
        $this->zipComment = null;
        $this->buffer = null;
        $this->filename = null;
        $this->offsetCentralDirectory = null;
    }

    function __destruct()
    {
        $this->close();
    }

    private static function convertGlobToRegEx($pattern)
    {
        $pattern = trim($pattern, '*'); // Remove beginning and ending * globs because they're useless
        $escaping = false;
        $inCurlies = 0;
        $chars = str_split($pattern);
        $sb = '';
        foreach ($chars AS $currentChar) {
            switch ($currentChar) {
                case '*':
                    $sb .= ($escaping ? "\\*" : '.*');
                    $escaping = false;
                    break;
                case '?':
                    $sb .= ($escaping ? "\\?" : '.');
                    $escaping = false;
                    break;
                case '.':
                case '(':
                case ')':
                case '+':
                case '|':
                case '^':
                case '$':
                case '@':
                case '%':
                    $sb .= '\\' . $currentChar;
                    $escaping = false;
                    break;
                case '\\':
                    if ($escaping) {
                        $sb .= "\\\\";
                        $escaping = false;
                    } else {
                        $escaping = true;
                    }
                    break;
                case '{':
                    if ($escaping) {
                        $sb .= "\\{";
                    } else {
                        $sb = '(';
                        $inCurlies++;
                    }
                    $escaping = false;
                    break;
                case '}':
                    if ($inCurlies > 0 && !$escaping) {
                        $sb .= ')';
                        $inCurlies--;
                    } else if ($escaping)
                        $sb = "\\}";
                    else
                        $sb = "}";
                    $escaping = false;
                    break;
                case ',':
                    if ($inCurlies > 0 && !$escaping) {
                        $sb .= '|';
                    } else if ($escaping)
                        $sb .= "\\,";
                    else
                        $sb = ",";
                    break;
                default:
                    $escaping = false;
                    $sb .= $currentChar;
            }
        }
        return $sb;
    }

}
