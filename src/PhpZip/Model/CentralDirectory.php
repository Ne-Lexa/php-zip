<?php
namespace PhpZip\Model;

use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipException;
use PhpZip\Exception\ZipNotFoundEntry;
use PhpZip\Model\Entry\ZipNewStringEntry;
use PhpZip\Model\Entry\ZipReadEntry;
use PhpZip\ZipFile;

/**
 * Read Central Directory
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class CentralDirectory
{
    /** Central File Header signature. */
    const CENTRAL_FILE_HEADER_SIG = 0x02014B50;
    /**
     * @var EndOfCentralDirectory End of Central Directory
     */
    private $endOfCentralDirectory;
    /**
     * @var ZipEntry[] Maps entry names to zip entries.
     */
    private $entries = [];
    /**
     * @var ZipEntry[] New and modified entries
     */
    private $modifiedEntries = [];
    /**
     * @var int Default compression level for the methods DEFLATED and BZIP2.
     */
    private $compressionLevel = ZipFile::LEVEL_DEFAULT_COMPRESSION;
    /**
     * @var int|null ZipAlign setting
     */
    private $zipAlign;
    /**
     * @var string New password
     */
    private $password;
    /**
     * @var int
     */
    private $encryptionMethod;
    /**
     * @var bool
     */
    private $clearPassword;

    public function __construct()
    {
        $this->endOfCentralDirectory = new EndOfCentralDirectory();
    }

    /**
     * Reads the central directory from the given seekable byte channel
     * and populates the internal tables with ZipEntry instances.
     *
     * The ZipEntry's will know all data that can be obtained from the
     * central directory alone, but not the data that requires the local
     * file header or additional data to be read.
     *
     * @param resource $inputStream
     * @throws ZipException
     */
    public function mountCentralDirectory($inputStream)
    {
        $this->modifiedEntries = [];
        $this->checkZipFileSignature($inputStream);
        $this->endOfCentralDirectory->findCentralDirectory($inputStream);

        $numEntries = $this->endOfCentralDirectory->getCentralDirectoryEntriesSize();
        $entries = [];
        for (; $numEntries > 0; $numEntries--) {
            $entry = new ZipReadEntry($inputStream);
            $entry->setCentralDirectory($this);
            // Re-load virtual offset after ZIP64 Extended Information
            // Extra Field may have been parsed, map it to the real
            // offset and conditionally update the preamble size from it.
            $lfhOff = $this->endOfCentralDirectory->getMapper()->map($entry->getOffset());
            if ($lfhOff < $this->endOfCentralDirectory->getPreamble()) {
                $this->endOfCentralDirectory->setPreamble($lfhOff);
            }
            $entries[$entry->getName()] = $entry;
        }

        if (0 !== $numEntries % 0x10000) {
            throw new ZipException("Expected " . abs($numEntries) .
                ($numEntries > 0 ? " more" : " less") .
                " entries in the Central Directory!");
        }
        $this->entries = $entries;

        if ($this->endOfCentralDirectory->getPreamble() + $this->endOfCentralDirectory->getPostamble() >= fstat($inputStream)['size']) {
            assert(0 === $numEntries);
            $this->checkZipFileSignature($inputStream);
        }
    }

    /**
     * Check zip file signature
     *
     * @param resource $inputStream
     * @throws ZipException if this not .ZIP file.
     */
    private function checkZipFileSignature($inputStream)
    {
        rewind($inputStream);
        // Constraint: A ZIP file must start with a Local File Header
        // or a (ZIP64) End Of Central Directory Record if it's empty.
        $signatureBytes = fread($inputStream, 4);
        if (strlen($signatureBytes) < 4) {
            throw new ZipException("Invalid zip file.");
        }
        $signature = unpack('V', $signatureBytes)[1];
        if (
            ZipEntry::LOCAL_FILE_HEADER_SIG !== $signature
            && EndOfCentralDirectory::ZIP64_END_OF_CENTRAL_DIRECTORY_RECORD_SIG !== $signature
            && EndOfCentralDirectory::END_OF_CENTRAL_DIRECTORY_RECORD_SIG !== $signature
        ) {
            throw new ZipException("Expected Local File Header or (ZIP64) End Of Central Directory Record! Signature: " . $signature);
        }
    }

    /**
     * Set compression method for new or rewrites entries.
     * @param int $compressionLevel
     * @throws InvalidArgumentException
     * @see ZipFile::LEVEL_DEFAULT_COMPRESSION
     * @see ZipFile::LEVEL_BEST_SPEED
     * @see ZipFile::LEVEL_BEST_COMPRESSION
     */
    public function setCompressionLevel($compressionLevel = ZipFile::LEVEL_DEFAULT_COMPRESSION)
    {
        if ($compressionLevel < ZipFile::LEVEL_DEFAULT_COMPRESSION ||
            $compressionLevel > ZipFile::LEVEL_BEST_COMPRESSION
        ) {
            throw new InvalidArgumentException('Invalid compression level. Minimum level ' .
                ZipFile::LEVEL_DEFAULT_COMPRESSION . '. Maximum level ' . ZipFile::LEVEL_BEST_COMPRESSION);
        }
        $this->compressionLevel = $compressionLevel;
    }

    /**
     * @return ZipEntry[]
     */
    public function &getEntries()
    {
        return $this->entries;
    }

    /**
     * @param string $entryName
     * @return ZipEntry
     * @throws ZipNotFoundEntry
     */
    public function getEntry($entryName)
    {
        if (!isset($this->entries[$entryName])) {
            throw new ZipNotFoundEntry('Zip entry ' . $entryName . ' not found');
        }
        return $this->entries[$entryName];
    }

    /**
     * @param string $entryName
     * @return ZipEntry
     * @throws ZipNotFoundEntry
     */
    public function getModifiedEntry($entryName){
        if (!isset($this->modifiedEntries[$entryName])) {
            throw new ZipNotFoundEntry('Zip modified entry ' . $entryName . ' not found');
        }
        return $this->modifiedEntries[$entryName];
    }

    /**
     * @return EndOfCentralDirectory
     */
    public function getEndOfCentralDirectory()
    {
        return $this->endOfCentralDirectory;
    }

    public function getArchiveComment()
    {
        return null === $this->endOfCentralDirectory->getComment() ?
            '' :
            $this->endOfCentralDirectory->getComment();
    }

    /**
     * Set entry comment
     * @param string $entryName
     * @param string|null $comment
     * @throws ZipNotFoundEntry
     */
    public function setEntryComment($entryName, $comment)
    {
        if (isset($this->modifiedEntries[$entryName])) {
            $this->modifiedEntries[$entryName]->setComment($comment);
        } elseif (isset($this->entries[$entryName])) {
            $entry = clone $this->entries[$entryName];
            $entry->setComment($comment);
            $this->putInModified($entryName, $entry);
        } else {
            throw new ZipNotFoundEntry("Not found entry " . $entryName);
        }
    }

    /**
     * @param string|null $password
     * @param int|null $encryptionMethod
     */
    public function setNewPassword($password, $encryptionMethod = null)
    {
        $this->password = $password;
        $this->encryptionMethod = $encryptionMethod;
        $this->clearPassword = $password === null;
    }

    /**
     * @return int|null
     */
    public function getZipAlign()
    {
        return $this->zipAlign;
    }

    /**
     * @param int|null $zipAlign
     */
    public function setZipAlign($zipAlign = null)
    {
        if (null === $zipAlign) {
            $this->zipAlign = null;
            return;
        }
        $this->zipAlign = (int)$zipAlign;
    }

    /**
     * Put modification or new entries.
     *
     * @param $entryName
     * @param ZipEntry $entry
     */
    public function putInModified($entryName, ZipEntry $entry)
    {
        $this->modifiedEntries[$entryName] = $entry;
    }

    /**
     * @param string $entryName
     * @throws ZipNotFoundEntry
     */
    public function deleteEntry($entryName)
    {
        if (isset($this->entries[$entryName])) {
            $this->modifiedEntries[$entryName] = null;
        } elseif (isset($this->modifiedEntries[$entryName])) {
            unset($this->modifiedEntries[$entryName]);
        } else {
            throw new ZipNotFoundEntry("Not found entry " . $entryName);
        }
    }

    /**
     * @param string $regexPattern
     * @return bool
     */
    public function deleteEntriesFromRegex($regexPattern)
    {
        $count = 0;
        foreach ($this->modifiedEntries as $entryName => &$entry) {
            if (preg_match($regexPattern, $entryName)) {
                unset($entry);
                $count++;
            }
        }
        foreach ($this->entries as $entryName => $entry) {
            if (preg_match($regexPattern, $entryName)) {
                $this->modifiedEntries[$entryName] = null;
                $count++;
            }
        }
        return $count > 0;
    }

    /**
     * @param string $oldName
     * @param string $newName
     * @throws InvalidArgumentException
     * @throws ZipNotFoundEntry
     */
    public function rename($oldName, $newName)
    {
        $oldName = (string)$oldName;
        $newName = (string)$newName;

        if (isset($this->entries[$newName]) || isset($this->modifiedEntries[$newName])) {
            throw new InvalidArgumentException("New entry name " . $newName . ' is exists.');
        }

        if (isset($this->modifiedEntries[$oldName]) || isset($this->entries[$oldName])) {
            $newEntry = clone (isset($this->modifiedEntries[$oldName]) ?
                $this->modifiedEntries[$oldName] :
                $this->entries[$oldName]);
            $newEntry->setName($newName);

            $this->modifiedEntries[$oldName] = null;
            $this->modifiedEntries[$newName] = $newEntry;
            return;
        }
        throw new ZipNotFoundEntry("Not found entry " . $oldName);
    }

    /**
     * Delete all entries.
     */
    public function deleteAll()
    {
        $this->modifiedEntries = [];
        foreach ($this->entries as $entry) {
            $this->modifiedEntries[$entry->getName()] = null;
        }
    }

    /**
     * @param resource $outputStream
     */
    public function writeArchive($outputStream)
    {
        /**
         * @var ZipEntry[] $memoryEntriesResult
         */
        $memoryEntriesResult = [];
        foreach ($this->entries as $entryName => $entry) {
            if (isset($this->modifiedEntries[$entryName])) continue;

            if (
                (null !== $this->password || $this->clearPassword) &&
                $entry->isEncrypted() &&
                $entry->getPassword() !== null &&
                (
                    $entry->getPassword() !== $this->password ||
                    $entry->getEncryptionMethod() !== $this->encryptionMethod
                )
            ) {
                $prototypeEntry = new ZipNewStringEntry($entry->getEntryContent());
                $prototypeEntry->setName($entry->getName());
                $prototypeEntry->setMethod($entry->getMethod());
                $prototypeEntry->setTime($entry->getTime());
                $prototypeEntry->setExternalAttributes($entry->getExternalAttributes());
                $prototypeEntry->setExtra($entry->getExtra());
                $prototypeEntry->setPassword($this->password, $this->encryptionMethod);
                if ($this->clearPassword) {
                    $prototypeEntry->clearEncryption();
                }
            } else {
                $prototypeEntry = clone $entry;
            }
            $memoryEntriesResult[$entryName] = $prototypeEntry;
        }

        foreach ($this->modifiedEntries as $entryName => $outputEntry) {
            if (null === $outputEntry) { // remove marked entry
                unset($memoryEntriesResult[$entryName]);
            } else {
                if (null !== $this->password) {
                    $outputEntry->setPassword($this->password, $this->encryptionMethod);
                }
                $memoryEntriesResult[$entryName] = $outputEntry;
            }
        }

        foreach ($memoryEntriesResult as $key => $outputEntry) {
            $outputEntry->setCentralDirectory($this);
            $outputEntry->writeEntry($outputStream);
        }
        $centralDirectoryOffset = ftell($outputStream);
        foreach ($memoryEntriesResult as $key => $outputEntry) {
            if (!$this->writeCentralFileHeader($outputStream, $outputEntry)) {
                unset($memoryEntriesResult[$key]);
            }
        }
        $centralDirectoryEntries = sizeof($memoryEntriesResult);
        $this->getEndOfCentralDirectory()->writeEndOfCentralDirectory(
            $outputStream,
            $centralDirectoryEntries,
            $centralDirectoryOffset
        );
    }

    /**
     * Writes a Central File Header record.
     *
     * @param resource $outputStream
     * @param ZipEntry $entry
     * @return bool false if and only if the record has been skipped,
     *         i.e. not written for some other reason than an I/O error.
     */
    private function writeCentralFileHeader($outputStream, ZipEntry $entry)
    {
        $compressedSize = $entry->getCompressedSize();
        $size = $entry->getSize();
        // This test MUST NOT include the CRC-32 because VV_AE_2 sets it to
        // UNKNOWN!
        if (ZipEntry::UNKNOWN === ($compressedSize | $size)) {
            return false;
        }
        $extra = $entry->getExtra();
        $extraSize = strlen($extra);

        $commentLength = strlen($entry->getComment());
        fwrite(
            $outputStream,
            pack(
                'VvvvvVVVVvvvvvVV',
                // central file header signature   4 bytes  (0x02014b50)
                self::CENTRAL_FILE_HEADER_SIG,
                // version made by                 2 bytes
                ($entry->getPlatform() << 8) | 63,
                // version needed to extract       2 bytes
                $entry->getVersionNeededToExtract(),
                // general purpose bit flag        2 bytes
                $entry->getGeneralPurposeBitFlags(),
                // compression method              2 bytes
                $entry->getMethod(),
                // last mod file datetime          4 bytes
                $entry->getDosTime(),
                // crc-32                          4 bytes
                $entry->getCrc(),
                // compressed size                 4 bytes
                $entry->getCompressedSize(),
                // uncompressed size               4 bytes
                $entry->getSize(),
                // file name length                2 bytes
                strlen($entry->getName()),
                // extra field length              2 bytes
                $extraSize,
                // file comment length             2 bytes
                $commentLength,
                // disk number start               2 bytes
                0,
                // internal file attributes        2 bytes
                0,
                // external file attributes        4 bytes
                $entry->getExternalAttributes(),
                // relative offset of local header 4 bytes
                $entry->getOffset()
            )
        );
        // file name (variable size)
        fwrite($outputStream, $entry->getName());
        if (0 < $extraSize) {
            // extra field (variable size)
            fwrite($outputStream, $extra);
        }
        if (0 < $commentLength) {
            // file comment (variable size)
            fwrite($outputStream, $entry->getComment());
        }
        return true;
    }

    public function release()
    {
        unset($this->entries);
        unset($this->modifiedEntries);
    }

    function __destruct()
    {
        $this->release();
    }

}