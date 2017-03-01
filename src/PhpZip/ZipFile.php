<?php
namespace PhpZip;

use PhpZip\Crypto\TraditionalPkwareEncryptionEngine;
use PhpZip\Crypto\WinZipAesEngine;
use PhpZip\Exception\Crc32Exception;
use PhpZip\Exception\IllegalArgumentException;
use PhpZip\Exception\ZipCryptoException;
use PhpZip\Exception\ZipException;
use PhpZip\Exception\ZipNotFoundEntry;
use PhpZip\Exception\ZipUnsupportMethod;
use PhpZip\Extra\WinZipAesEntryExtraField;
use PhpZip\Mapper\OffsetPositionMapper;
use PhpZip\Mapper\PositionMapper;
use PhpZip\Model\ZipEntry;
use PhpZip\Model\ZipInfo;
use PhpZip\Util\PackUtil;

/**
 * This class is able to open the .ZIP file in read mode and extract files from it.
 *
 * Implemented support traditional PKWARE encryption and WinZip AES encryption.
 * Implemented support ZIP64.
 * Implemented support skip a preamble like the one found in self extracting archives.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipFile implements \Countable, \ArrayAccess, \Iterator, ZipConstants
{
    /**
     * Input seekable stream resource.
     *
     * @var resource
     */
    private $inputStream;

    /**
     * The total number of bytes in the ZIP archive.
     *
     * @var int
     */
    private $length;

    /**
     * The charset to use for entry names and comments.
     *
     * @var string
     */
    private $charset;

    /**
     * The number of bytes in the preamble of this ZIP file.
     *
     * @var int
     */
    private $preamble;

    /**
     * The number of bytes in the postamble of this ZIP file.
     *
     * @var int
     */
    private $postamble;

    /**
     * Maps entry names to zip entries.
     *
     * @var ZipEntry[]
     */
    private $entries;

    /**
     * The file comment.
     *
     * @var string
     */
    private $comment;

    /**
     * Maps offsets specified in the ZIP file to real offsets in the file.
     *
     * @var PositionMapper
     */
    private $mapper;

    /**
     * Private ZipFile constructor.
     *
     * @see ZipFile::openFromFile()
     * @see ZipFile::openFromString()
     * @see ZipFile::openFromStream()
     */
    private function __construct()
    {
        $this->mapper = new PositionMapper();
        $this->charset = "UTF-8";
    }

    /**
     * Open zip archive from file
     *
     * @param string $filename
     * @return ZipFile
     * @throws IllegalArgumentException if file doesn't exists.
     * @throws ZipException             if can't open file.
     */
    public static function openFromFile($filename)
    {
        if (!file_exists($filename)) {
            throw new IllegalArgumentException("File $filename can't exists.");
        }
        if (!($handle = fopen($filename, 'rb'))) {
            throw new ZipException("File $filename can't open.");
        }
        $zipFile = self::openFromStream($handle);
        $zipFile->length = filesize($filename);
        return $zipFile;
    }

    /**
     * Open zip archive from stream resource
     *
     * @param resource $handle
     * @return ZipFile
     * @throws IllegalArgumentException Invalid stream resource
     *         or resource cannot seekable stream
     */
    public static function openFromStream($handle)
    {
        if (!is_resource($handle)) {
            throw new IllegalArgumentException("Invalid stream resource.");
        }
        $meta = stream_get_meta_data($handle);
        if (!$meta['seekable']) {
            throw new IllegalArgumentException("Resource cannot seekable stream.");
        }
        $zipFile = new self();
        $stats = fstat($handle);
        if (isset($stats['size'])) {
            $zipFile->length = $stats['size'];
        }
        $zipFile->checkZipFileSignature($handle);
        $numEntries = $zipFile->findCentralDirectory($handle);
        $zipFile->mountCentralDirectory($handle, $numEntries);
        if ($zipFile->preamble + $zipFile->postamble >= $zipFile->length) {
            assert(0 === $numEntries);
            $zipFile->checkZipFileSignature($handle);
        }
        assert(null !== $handle);
        assert(null !== $zipFile->charset);
        assert(null !== $zipFile->entries);
        assert(null !== $zipFile->mapper);
        $zipFile->inputStream = $handle;
        // Do NOT close stream!
        return $zipFile;
    }

    /**
     * @return ZipOutputFile
     */
    public function edit(){
        return ZipOutputFile::openFromZipFile($this);
    }

    /**
     * Check zip file signature
     *
     * @param resource $handle
     * @throws ZipException if this not .ZIP file.
     */
    private function checkZipFileSignature($handle)
    {
        rewind($handle);
        $signature = current(unpack('V', fread($handle, 4)));
        // Constraint: A ZIP file must start with a Local File Header
        // or a (ZIP64) End Of Central Directory Record if it's empty.
        if (self::LOCAL_FILE_HEADER_SIG !== $signature && self::ZIP64_END_OF_CENTRAL_DIRECTORY_RECORD_SIG !== $signature && self::END_OF_CENTRAL_DIRECTORY_RECORD_SIG !== $signature
        ) {
            throw new ZipException("Expected Local File Header or (ZIP64) End Of Central Directory Record! Signature: " . $signature);
        }
    }

    /**
     * Positions the file pointer at the first Central File Header.
     * Performs some means to check that this is really a ZIP file.
     *
     * @param resource $handle
     * @return int
     * @throws ZipException If the file is not compatible to the ZIP File
     *         Format Specification.
     */
    private function findCentralDirectory($handle)
    {
        // Search for End of central directory record.
        $max = $this->length - self::END_OF_CENTRAL_DIRECTORY_RECORD_MIN_LEN;
        $min = $max >= 0xffff ? $max - 0xffff : 0;
        for ($endOfCentralDirRecordPos = $max; $endOfCentralDirRecordPos >= $min; $endOfCentralDirRecordPos--) {
            fseek($handle, $endOfCentralDirRecordPos, SEEK_SET);
            // end of central dir signature    4 bytes  (0x06054b50)
            if (self::END_OF_CENTRAL_DIRECTORY_RECORD_SIG !== current(unpack('V', fread($handle, 4))))
                continue;

            // Process End Of Central Directory Record.
            $data = fread($handle, self::END_OF_CENTRAL_DIRECTORY_RECORD_MIN_LEN - 4);

            /**
             * @var int $diskNo number of this disk                        - 2 bytes
             * @var int $cdDiskNo number of the disk with the start of the
             *                         central directory                   - 2 bytes
             * @var int $cdEntriesDisk total number of entries in the central
             *                         directory on this disk              - 2 bytes
             * @var int $cdEntries total number of entries in the central
             *                         directory                           - 2 bytes
             * @var int $cdSize size of the central directory              - 4 bytes
             * @var int $cdPos offset of start of central directory with
             *                         respect to the starting disk number - 4 bytes
             * @var int $commentLen ZIP file comment length                - 2 bytes
             */
            $unpack = unpack('vdiskNo/vcdDiskNo/vcdEntriesDisk/vcdEntries/VcdSize/VcdPos/vcommentLen', $data);
            extract($unpack);

            if (0 !== $diskNo || 0 !== $cdDiskNo || $cdEntriesDisk !== $cdEntries) {
                throw new ZipException(
                    "ZIP file spanning/splitting is not supported!"
                );
            }
            // .ZIP file comment       (variable size)
            if (0 < $commentLen) {
                $this->comment = fread($handle, $commentLen);
            }
            $this->preamble = $endOfCentralDirRecordPos;
            $this->postamble = $this->length - ftell($handle);

            // Check for ZIP64 End Of Central Directory Locator.
            $endOfCentralDirLocatorPos = $endOfCentralDirRecordPos - self::ZIP64_END_OF_CENTRAL_DIRECTORY_LOCATOR_LEN;

            fseek($handle, $endOfCentralDirLocatorPos, SEEK_SET);

            // zip64 end of central dir locator
            // signature                       4 bytes  (0x07064b50)
            if (
                0 > $endOfCentralDirLocatorPos ||
                ftell($handle) === $this->length ||
                self::ZIP64_END_OF_CENTRAL_DIRECTORY_LOCATOR_SIG !== current(unpack('V', fread($handle, 4)))
            ) {
                // Seek and check first CFH, probably requiring an offset mapper.
                $offset = $endOfCentralDirRecordPos - $cdSize;
                fseek($handle, $offset, SEEK_SET);
                $offset -= $cdPos;
                if (0 !== $offset) {
                    $this->mapper = new OffsetPositionMapper($offset);
                }
                return (int)$cdEntries;
            }

            // number of the disk with the
            // start of the zip64 end of
            // central directory               4 bytes
            $zip64EndOfCentralDirectoryRecordDisk = current(unpack('V', fread($handle, 4)));
            // relative offset of the zip64
            // end of central directory record 8 bytes
            $zip64EndOfCentralDirectoryRecordPos = PackUtil::unpackLongLE(fread($handle, 8));
            // total number of disks           4 bytes
            $totalDisks = current(unpack('V', fread($handle, 4)));
            if (0 !== $zip64EndOfCentralDirectoryRecordDisk || 1 !== $totalDisks) {
                throw new ZipException("ZIP file spanning/splitting is not supported!");
            }
            fseek($handle, $zip64EndOfCentralDirectoryRecordPos, SEEK_SET);
            // zip64 end of central dir
            // signature                       4 bytes  (0x06064b50)
            $zip64EndOfCentralDirSig = current(unpack('V', fread($handle, 4)));
            if (self::ZIP64_END_OF_CENTRAL_DIRECTORY_RECORD_SIG !== $zip64EndOfCentralDirSig) {
                throw new ZipException("Expected ZIP64 End Of Central Directory Record!");
            }
            // size of zip64 end of central
            // directory record                8 bytes
            // version made by                 2 bytes
            // version needed to extract       2 bytes
            fseek($handle, 12, SEEK_CUR);
            // number of this disk             4 bytes
            $diskNo = current(unpack('V', fread($handle, 4)));
            // number of the disk with the
            // start of the central directory  4 bytes
            $cdDiskNo = current(unpack('V', fread($handle, 4)));
            // total number of entries in the
            // central directory on this disk  8 bytes
            $cdEntriesDisk = PackUtil::unpackLongLE(fread($handle, 8));
            // total number of entries in the
            // central directory               8 bytes
            $cdEntries = PackUtil::unpackLongLE(fread($handle, 8));
            if (0 !== $diskNo || 0 !== $cdDiskNo || $cdEntriesDisk !== $cdEntries) {
                throw new ZipException(
                    "ZIP file spanning/splitting is not supported!");
            }
            if ($cdEntries < 0 || 0x7fffffff < $cdEntries) {
                throw new ZipException(
                    "Total Number Of Entries In The Central Directory out of range!");
            }
            // size of the central directory   8 bytes
            //$cdSize = self::getLongLE($channel);
            fseek($handle, 8, SEEK_CUR);
            // offset of start of central
            // directory with respect to
            // the starting disk number        8 bytes
            $cdPos = PackUtil::unpackLongLE(fread($handle, 8));
            // zip64 extensible data sector    (variable size)
            fseek($handle, $cdPos, SEEK_SET);
            $this->preamble = $zip64EndOfCentralDirectoryRecordPos;
            return (int)$cdEntries;
        }
        // Start recovering file entries from min.
        $this->preamble = $min;
        $this->postamble = $this->length - $min;
        return 0;
    }

    /**
     * Reads the central directory from the given seekable byte channel
     * and populates the internal tables with ZipEntry instances.
     *
     * The ZipEntry's will know all data that can be obtained from the
     * central directory alone, but not the data that requires the local
     * file header or additional data to be read.
     *
     * @param resource $handle Input channel.
     * @param int $numEntries Size zip entries.
     * @throws ZipException
     */
    private function mountCentralDirectory($handle, $numEntries)
    {
        $numEntries = (int)$numEntries;
        $entries = [];
        for (; ; $numEntries--) {
            // central file header signature   4 bytes  (0x02014b50)
            if (self::CENTRAL_FILE_HEADER_SIG !== current(unpack('V', fread($handle, 4)))) {
                break;
            }
            // version made by                 2 bytes
            $versionMadeBy = current(unpack('v', fread($handle, 2)));

            // version needed to extract       2 bytes
            fseek($handle, 2, SEEK_CUR);

            $unpack = unpack('vgpbf/vrawMethod/VrawTime/VrawCrc/VrawCompressedSize/VrawSize/vfileLen/vextraLen/vcommentLen', fread($handle, 26));

            // disk number start               2 bytes
            // internal file attributes        2 bytes
            fseek($handle, 4, SEEK_CUR);

            // external file attributes        4 bytes
            // relative offset of local header 4 bytes
            $unpack2 = unpack('VrawExternalAttributes/VlfhOff', fread($handle, 8));

            $utf8 = 0 !== ($unpack['gpbf'] & ZipEntry::GPBF_UTF8);
            if ($utf8) {
                $this->charset = "UTF-8";
            }

            // See appendix D of PKWARE's ZIP File Format Specification.
            $name = fread($handle, $unpack['fileLen']);
            $entry = new ZipEntry($name, $handle);
            $entry->setRawPlatform($versionMadeBy >> 8);
            $entry->setGeneralPurposeBitFlags($unpack['gpbf']);
            $entry->setRawMethod($unpack['rawMethod']);
            $entry->setRawTime($unpack['rawTime']);
            $entry->setRawCrc($unpack['rawCrc']);
            $entry->setRawCompressedSize($unpack['rawCompressedSize']);
            $entry->setRawSize($unpack['rawSize']);
            $entry->setRawExternalAttributes($unpack2['rawExternalAttributes']);
            $entry->setRawOffset($unpack2['lfhOff']); // must be unmapped!
            if (0 < $unpack['extraLen']) {
                $entry->setRawExtraFields(fread($handle, $unpack['extraLen']));
            }
            if (0 < $unpack['commentLen']) {
                $entry->setComment(fread($handle, $unpack['commentLen']));
            }

            unset($unpack, $unpack2);

            // Re-load virtual offset after ZIP64 Extended Information
            // Extra Field may have been parsed, map it to the real
            // offset and conditionally update the preamble size from it.
            $lfhOff = $this->mapper->map($entry->getOffset());
            if ($lfhOff < $this->preamble) {
                $this->preamble = $lfhOff;
            }
            $entries[$entry->getName()] = $entry;
        }

        if (0 !== $numEntries % 0x10000) {
            throw new ZipException("Expected " . abs($numEntries) .
                ($numEntries > 0 ? " more" : " less") .
                " entries in the Central Directory!");
        }

        $this->entries = $entries;
    }

    /**
     * Open zip archive from raw string data.
     *
     * @param string $data
     * @return ZipFile
     * @throws IllegalArgumentException if data not available.
     * @throws ZipException             if can't open temp stream.
     */
    public static function openFromString($data)
    {
        if (null === $data || strlen($data) === 0) {
            throw new IllegalArgumentException("Data not available");
        }
        if (!($handle = fopen('php://temp', 'r+b'))) {
            throw new ZipException("Can't open temp stream.");
        }
        fwrite($handle, $data);
        rewind($handle);
        $zipFile = self::openFromStream($handle);
        $zipFile->length = strlen($data);
        return $zipFile;
    }

    /**
     * Returns the number of entries in this ZIP file.
     *
     * @return int
     */
    public function count()
    {
        return sizeof($this->entries);
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
     * @api
     * @return ZipEntry[]
     */
    public function getRawEntries()
    {
        return $this->entries;
    }

    /**
     * Checks whether a entry exists
     *
     * @param string $entryName
     * @return bool
     */
    public function hasEntry($entryName)
    {
        return isset($this->entries[$entryName]);
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
        if (!isset($this->entries[$entryName])) {
            throw new ZipNotFoundEntry('Zip entry ' . $entryName . ' not found');
        }
        return $this->entries[$entryName]->isDirectory();
    }

    /**
     * Set password to all encrypted entries.
     *
     * @param string $password Password
     */
    public function setPassword($password)
    {
        foreach ($this->entries as $entry) {
            if ($entry->isEncrypted()) {
                $entry->setPassword($password);
            }
        }
    }

    /**
     * Set password to concrete zip entry.
     *
     * @param string $entryName Zip entry name
     * @param string $password Password
     * @throws ZipNotFoundEntry if don't exist zip entry.
     */
    public function setEntryPassword($entryName, $password)
    {
        if (!isset($this->entries[$entryName])) {
            throw new ZipNotFoundEntry('Zip entry ' . $entryName . ' not found');
        }
        $entry = $this->entries[$entryName];
        if ($entry->isEncrypted()) {
            $entry->setPassword($password);
        }
    }

    /**
     * Returns the file comment.
     *
     * @return string The file comment.
     */
    public function getComment()
    {
        return null === $this->comment ? '' : $this->decode($this->comment);
    }

    /**
     * Decode charset entry name.
     *
     * @param string $text
     * @return string
     */
    private function decode($text)
    {
        $inCharset = mb_detect_encoding($text, mb_detect_order(), true);
        if ($inCharset === $this->charset) return $text;
        return iconv($inCharset, $this->charset, $text);
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
        if (!isset($this->entries[$entryName])) {
            throw new ZipNotFoundEntry("Not found entry " . $entryName);
        }
        return $this->entries[$entryName]->getComment();
    }

    /**
     * Returns the name of the character set which is effectively used for
     * decoding entry names and the file comment.
     *
     * @return string
     */
    public function getCharset()
    {
        return $this->charset;
    }

    /**
     * Returns the file length of this ZIP file in bytes.
     *
     * @return int
     */
    public function length()
    {
        return $this->length;
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
        if ($entryName instanceof ZipEntry) {
            $entryName = $entryName->getName();
        }
        if (!isset($this->entries[$entryName])) {
            throw new ZipNotFoundEntry('Zip entry ' . $entryName . ' not found');
        }
        $entry = $this->entries[$entryName];

        return new ZipInfo($entry);
    }

    /**
     * Get info by all entries.
     *
     * @return ZipInfo[]
     */
    public function getAllInfo()
    {
        return array_map([$this, 'getEntryInfo'], $this->entries);
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
         * @var ZipEntry[] $zipEntries
         */
        if (!empty($entries)) {
            if (is_string($entries)) {
                $entries = (array)$entries;
            }
            if (is_array($entries)) {
                $flipEntries = array_flip($entries);
                $zipEntries = array_filter($this->entries, function ($zipEntry) use ($flipEntries) {
                    /**
                     * @var ZipEntry $zipEntry
                     */
                    return isset($flipEntries[$zipEntry->getName()]);
                });
            }
        } else {
            $zipEntries = $this->entries;
        }

        $extract = 0;
        foreach ($zipEntries AS $entry) {
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
     * Returns an string content of the given entry.
     *
     * @param string $entryName
     * @return string|null
     * @throws ZipException
     */
    public function getEntryContent($entryName)
    {
        if (!isset($this->entries[$entryName])) {
            throw new ZipNotFoundEntry('Zip entry ' . $entryName . ' not found');
        }
        $entry = $this->entries[$entryName];

        $pos = $entry->getOffset();
        assert(ZipEntry::UNKNOWN !== $pos);
        $startPos = $pos = $this->mapper->map($pos);
        fseek($this->inputStream, $pos, SEEK_SET);
        $localFileHeaderSig = current(unpack('V', fread($this->inputStream, 4)));
        if (self::LOCAL_FILE_HEADER_SIG !== $localFileHeaderSig) {
            throw new ZipException($entry->getName() . " (expected Local File Header)");
        }
        fseek($this->inputStream, $pos + self::LOCAL_FILE_HEADER_FILE_NAME_LENGTH_POS, SEEK_SET);
        $unpack = unpack('vfileLen/vextraLen', fread($this->inputStream, 4));
        $pos += self::LOCAL_FILE_HEADER_MIN_LEN + $unpack['fileLen'] + $unpack['extraLen'];

        assert(ZipEntry::UNKNOWN !== $entry->getCrc());

        $check = $entry->isEncrypted();
        $method = $entry->getMethod();

        $password = $entry->getPassword();
        if ($entry->isEncrypted() && empty($password)) {
            throw new ZipException("Not set password");
        }
        // Strong Encryption Specification - WinZip AES
        if ($entry->isEncrypted() && ZipEntry::WINZIP_AES === $method) {
            fseek($this->inputStream, $pos, SEEK_SET);
            $winZipAesEngine = new WinZipAesEngine($entry);
            $content = $winZipAesEngine->decrypt($this->inputStream);
            // Disable redundant CRC-32 check.
            $check = false;

            /**
             * @var WinZipAesEntryExtraField $field
             */
            $field = $entry->getExtraField(WinZipAesEntryExtraField::getHeaderId());
            $method = $field->getMethod();
            $entry->setEncryptionMethod(ZipEntry::ENCRYPTION_METHOD_WINZIP_AES);
        } else {
            // Get raw entry content
            $content = stream_get_contents($this->inputStream, $entry->getCompressedSize(), $pos);

            // Traditional PKWARE Decryption
            if ($entry->isEncrypted()) {
                $zipCryptoEngine = new TraditionalPkwareEncryptionEngine($entry);
                $content = $zipCryptoEngine->decrypt($content);

                $entry->setEncryptionMethod(ZipEntry::ENCRYPTION_METHOD_TRADITIONAL);
            }
        }
        if ($check) {
            // Check CRC32 in the Local File Header or Data Descriptor.
            $localCrc = null;
            if ($entry->getGeneralPurposeBitFlag(ZipEntry::GPBF_DATA_DESCRIPTOR)) {
                // The CRC32 is in the Data Descriptor after the compressed
                // size.
                // Note the Data Descriptor's Signature is optional:
                // All newer apps should write it (and so does TrueVFS),
                // but older apps might not.
                fseek($this->inputStream, $pos + $entry->getCompressedSize(), SEEK_SET);
                $localCrc = current(unpack('V', fread($this->inputStream, 4)));
                if (self::DATA_DESCRIPTOR_SIG === $localCrc) {
                    $localCrc = current(unpack('V', fread($this->inputStream, 4)));
                }
            } else {
                fseek($this->inputStream, $startPos + 14, SEEK_SET);
                // The CRC32 in the Local File Header.
                $localCrc = current(unpack('V', fread($this->inputStream, 4)));
            }
            if ($entry->getCrc() !== $localCrc) {
                throw new Crc32Exception($entry->getName(), $entry->getCrc(), $localCrc);
            }
        }

        switch ($method) {
            case ZipEntry::METHOD_STORED:
                break;
            case ZipEntry::METHOD_DEFLATED:
                $content = gzinflate($content);
                break;
            case ZipEntry::METHOD_BZIP2:
                if (!extension_loaded('bz2')) {
                    throw new ZipException('Extension bzip2 not install');
                }
                $content = bzdecompress($content);
                break;
            default:
                throw new ZipUnsupportMethod($entry->getName()
                    . " (compression method "
                    . $method
                    . " is not supported)");
        }
        if ($check) {
            $localCrc = crc32($content);
            if ($entry->getCrc() !== $localCrc) {
                if ($entry->isEncrypted()) {
                    throw new ZipCryptoException("Wrong password");
                }
                throw new Crc32Exception($entry->getName(), $entry->getCrc(), $localCrc);
            }
        }
        return $content;
    }

    /**
     * Release all resources
     */
    function __destruct()
    {
        $this->close();
    }

    /**
     * Close zip archive and release input stream.
     */
    public function close()
    {
        $this->length = null;

        if ($this->inputStream !== null) {
            fclose($this->inputStream);
            $this->inputStream = null;
        }
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
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param string $entryName The offset to assign the value to.
     * @param mixed $value The value to set.
     * @throws ZipUnsupportMethod
     */
    public function offsetSet($entryName, $value)
    {
        throw new ZipUnsupportMethod('Zip-file is read-only. This operation is prohibited.');
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param string $entryName The offset to unset.
     * @throws ZipUnsupportMethod
     */
    public function offsetUnset($entryName)
    {
        throw new ZipUnsupportMethod('Zip-file is read-only. This operation is prohibited.');
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