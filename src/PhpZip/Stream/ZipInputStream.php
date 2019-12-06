<?php

namespace PhpZip\Stream;

use PhpZip\Crypto\TraditionalPkwareEncryptionEngine;
use PhpZip\Crypto\WinZipAesEngine;
use PhpZip\Exception\Crc32Exception;
use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\RuntimeException;
use PhpZip\Exception\ZipAuthenticationException;
use PhpZip\Exception\ZipException;
use PhpZip\Exception\ZipUnsupportMethodException;
use PhpZip\Extra\ExtraFieldsCollection;
use PhpZip\Extra\ExtraFieldsFactory;
use PhpZip\Extra\Fields\ApkAlignmentExtraField;
use PhpZip\Extra\Fields\WinZipAesEntryExtraField;
use PhpZip\Model\EndOfCentralDirectory;
use PhpZip\Model\Entry\ZipSourceEntry;
use PhpZip\Model\ZipEntry;
use PhpZip\Model\ZipModel;
use PhpZip\Util\PackUtil;
use PhpZip\Util\StringUtil;
use PhpZip\ZipFile;

/**
 * Read zip file.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipInputStream implements ZipInputStreamInterface
{
    /** @var resource */
    protected $in;

    /** @var ZipModel */
    protected $zipModel;

    /**
     * ZipInputStream constructor.
     *
     * @param resource $in
     */
    public function __construct($in)
    {
        if (!\is_resource($in)) {
            throw new RuntimeException('$in must be resource');
        }
        $this->in = $in;
    }

    /**
     * @throws ZipException
     *
     * @return ZipModel
     */
    public function readZip()
    {
        $this->checkZipFileSignature();
        $endOfCentralDirectory = $this->readEndOfCentralDirectory();
        $entries = $this->mountCentralDirectory($endOfCentralDirectory);
        $this->zipModel = ZipModel::newSourceModel($entries, $endOfCentralDirectory);

        return $this->zipModel;
    }

    /**
     * Check zip file signature.
     *
     * @throws ZipException if this not .ZIP file.
     */
    protected function checkZipFileSignature()
    {
        rewind($this->in);
        // Constraint: A ZIP file must start with a Local File Header
        // or a (ZIP64) End Of Central Directory Record if it's empty.
        $signatureBytes = fread($this->in, 4);

        if (\strlen($signatureBytes) < 4) {
            throw new ZipException('Invalid zip file.');
        }
        $signature = unpack('V', $signatureBytes)[1];

        if (
            $signature !== ZipEntry::LOCAL_FILE_HEADER_SIG
            && $signature !== EndOfCentralDirectory::ZIP64_END_OF_CD_RECORD_SIG
            && $signature !== EndOfCentralDirectory::END_OF_CD_SIG
        ) {
            throw new ZipException(
                'Expected Local File Header or (ZIP64) End Of Central Directory Record! Signature: ' . $signature
            );
        }
    }

    /**
     * @throws ZipException
     *
     * @return EndOfCentralDirectory
     */
    protected function readEndOfCentralDirectory()
    {
        if (!$this->findEndOfCentralDirectory()) {
            throw new ZipException('Invalid zip file. The end of the central directory could not be found.');
        }

        $positionECD = ftell($this->in) - 4;
        $buffer = fread($this->in, fstat($this->in)['size'] - $positionECD);

        $unpack = unpack(
            'vdiskNo/vcdDiskNo/vcdEntriesDisk/' .
            'vcdEntries/VcdSize/VcdPos/vcommentLength',
            substr($buffer, 0, 18)
        );

        if (
            $unpack['diskNo'] !== 0 ||
            $unpack['cdDiskNo'] !== 0 ||
            $unpack['cdEntriesDisk'] !== $unpack['cdEntries']
        ) {
            throw new ZipException(
                'ZIP file spanning/splitting is not supported!'
            );
        }
        // .ZIP file comment       (variable sizeECD)
        $comment = null;

        if ($unpack['commentLength'] > 0) {
            $comment = substr($buffer, 18, $unpack['commentLength']);
        }

        // Check for ZIP64 End Of Central Directory Locator exists.
        $zip64ECDLocatorPosition = $positionECD - EndOfCentralDirectory::ZIP64_END_OF_CD_LOCATOR_LEN;
        fseek($this->in, $zip64ECDLocatorPosition);
        // zip64 end of central dir locator
        // signature                       4 bytes  (0x07064b50)
        if ($zip64ECDLocatorPosition > 0 && unpack(
            'V',
            fread($this->in, 4)
        )[1] === EndOfCentralDirectory::ZIP64_END_OF_CD_LOCATOR_SIG) {
            $positionECD = $this->findZip64ECDPosition();
            $endCentralDirectory = $this->readZip64EndOfCentralDirectory($positionECD);
            $endCentralDirectory->setComment($comment);
        } else {
            $endCentralDirectory = new EndOfCentralDirectory(
                $unpack['cdEntries'],
                $unpack['cdPos'],
                $unpack['cdSize'],
                false,
                $comment
            );
        }

        return $endCentralDirectory;
    }

    /**
     * @throws ZipException
     *
     * @return bool
     */
    protected function findEndOfCentralDirectory()
    {
        $max = fstat($this->in)['size'] - EndOfCentralDirectory::END_OF_CENTRAL_DIRECTORY_RECORD_MIN_LEN;

        if ($max < 0) {
            throw new ZipException('Too short to be a zip file');
        }
        $min = $max >= 0xffff ? $max - 0xffff : 0;
        // Search for End of central directory record.
        for ($position = $max; $position >= $min; $position--) {
            fseek($this->in, $position);
            // end of central dir signature    4 bytes  (0x06054b50)
            if (unpack('V', fread($this->in, 4))[1] !== EndOfCentralDirectory::END_OF_CD_SIG) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Read Zip64 end of central directory locator and returns
     * Zip64 end of central directory position.
     *
     * number of the disk with the
     * start of the zip64 end of
     * central directory               4 bytes
     * relative offset of the zip64
     * end of central directory record 8 bytes
     * total number of disks           4 bytes
     *
     * @throws ZipException
     *
     * @return int Zip64 End Of Central Directory position
     */
    protected function findZip64ECDPosition()
    {
        $diskNo = unpack('V', fread($this->in, 4))[1];
        $zip64ECDPos = PackUtil::unpackLongLE(fread($this->in, 8));
        $totalDisks = unpack('V', fread($this->in, 4))[1];

        if ($diskNo !== 0 || $totalDisks > 1) {
            throw new ZipException('ZIP file spanning/splitting is not supported!');
        }

        return $zip64ECDPos;
    }

    /**
     * Read zip64 end of central directory locator and zip64 end
     * of central directory record.
     *
     * zip64 end of central dir
     * signature                       4 bytes  (0x06064b50)
     * size of zip64 end of central
     * directory record                8 bytes
     * version made by                 2 bytes
     * version needed to extract       2 bytes
     * number of this disk             4 bytes
     * number of the disk with the
     * start of the central directory  4 bytes
     * total number of entries in the
     * central directory on this disk  8 bytes
     * total number of entries in the
     * central directory               8 bytes
     * size of the central directory   8 bytes
     * offset of start of central
     * directory with respect to
     * the starting disk number        8 bytes
     * zip64 extensible data sector    (variable size)
     *
     * @param int $zip64ECDPosition
     *
     * @throws ZipException
     *
     * @return EndOfCentralDirectory
     */
    protected function readZip64EndOfCentralDirectory($zip64ECDPosition)
    {
        fseek($this->in, $zip64ECDPosition);

        $buffer = fread($this->in, 56 /* zip64 end of cd rec length */);

        if (unpack('V', $buffer)[1] !== EndOfCentralDirectory::ZIP64_END_OF_CD_RECORD_SIG) {
            throw new ZipException('Expected ZIP64 End Of Central Directory Record!');
        }

        $data = unpack(
            'VdiskNo/VcdDiskNo',
            substr($buffer, 16)
        );
        $cdEntriesDisk = PackUtil::unpackLongLE(substr($buffer, 24, 8));
        $entryCount = PackUtil::unpackLongLE(substr($buffer, 32, 8));
        $cdSize = PackUtil::unpackLongLE(substr($buffer, 40, 8));
        $cdPos = PackUtil::unpackLongLE(substr($buffer, 48, 8));

        if ($data['diskNo'] !== 0 || $data['cdDiskNo'] !== 0 || $entryCount !== $cdEntriesDisk) {
            throw new ZipException('ZIP file spanning/splitting is not supported!');
        }

        if ($entryCount < 0 || $entryCount > 0x7fffffff) {
            throw new ZipException('Total Number Of Entries In The Central Directory out of range!');
        }

        // skip zip64 extensible data sector (variable sizeEndCD)

        return new EndOfCentralDirectory(
            $entryCount,
            $cdPos,
            $cdSize,
            true
        );
    }

    /**
     * Reads the central directory from the given seekable byte channel
     * and populates the internal tables with ZipEntry instances.
     *
     * The ZipEntry's will know all data that can be obtained from the
     * central directory alone, but not the data that requires the local
     * file header or additional data to be read.
     *
     * @param EndOfCentralDirectory $endOfCentralDirectory
     *
     * @throws ZipException
     *
     * @return ZipEntry[]
     */
    protected function mountCentralDirectory(EndOfCentralDirectory $endOfCentralDirectory)
    {
        $entries = [];

        fseek($this->in, $endOfCentralDirectory->getCdOffset());

        if (!($cdStream = fopen('php://temp', 'w+b'))) {
            throw new ZipException('Temp resource can not open from write');
        }
        stream_copy_to_stream($this->in, $cdStream, $endOfCentralDirectory->getCdSize());
        rewind($cdStream);
        for ($numEntries = $endOfCentralDirectory->getEntryCount(); $numEntries > 0; $numEntries--) {
            $entry = $this->readCentralDirectoryEntry($cdStream);
            $entries[$entry->getName()] = $entry;
        }
        fclose($cdStream);

        return $entries;
    }

    /**
     * Read central directory entry.
     *
     * central file header signature   4 bytes  (0x02014b50)
     * version made by                 2 bytes
     * version needed to extract       2 bytes
     * general purpose bit flag        2 bytes
     * compression method              2 bytes
     * last mod file time              2 bytes
     * last mod file date              2 bytes
     * crc-32                          4 bytes
     * compressed size                 4 bytes
     * uncompressed size               4 bytes
     * file name length                2 bytes
     * extra field length              2 bytes
     * file comment length             2 bytes
     * disk number start               2 bytes
     * internal file attributes        2 bytes
     * external file attributes        4 bytes
     * relative offset of local header 4 bytes
     *
     * file name (variable size)
     * extra field (variable size)
     * file comment (variable size)
     *
     * @param resource $stream
     *
     * @throws ZipException
     *
     * @return ZipEntry
     */
    public function readCentralDirectoryEntry($stream)
    {
        if (unpack('V', fread($stream, 4))[1] !== ZipOutputStreamInterface::CENTRAL_FILE_HEADER_SIG) {
            throw new ZipException('Corrupt zip file. Cannot read central dir entry.');
        }

        $data = unpack(
            'vversionMadeBy/vversionNeededToExtract/' .
            'vgeneralPurposeBitFlag/vcompressionMethod/' .
            'VlastModFile/Vcrc/VcompressedSize/' .
            'VuncompressedSize/vfileNameLength/vextraFieldLength/' .
            'vfileCommentLength/vdiskNumberStart/vinternalFileAttributes/' .
            'VexternalFileAttributes/VoffsetLocalHeader',
            fread($stream, 42)
        );

        $createdOS = ($data['versionMadeBy'] & 0xFF00) >> 8;
        $softwareVersion = $data['versionMadeBy'] & 0x00FF;

        $extractOS = ($data['versionNeededToExtract'] & 0xFF00) >> 8;
        $extractVersion = $data['versionNeededToExtract'] & 0x00FF;

        $name = fread($stream, $data['fileNameLength']);

        $extra = '';

        if ($data['extraFieldLength'] > 0) {
            $extra = fread($stream, $data['extraFieldLength']);
        }

        $comment = null;

        if ($data['fileCommentLength'] > 0) {
            $comment = fread($stream, $data['fileCommentLength']);
        }

        $entry = new ZipSourceEntry($this);
        $entry->setName($name);
        $entry->setCreatedOS($createdOS);
        $entry->setSoftwareVersion($softwareVersion);
        $entry->setVersionNeededToExtract($extractVersion);
        $entry->setExtractedOS($extractOS);
        $entry->setMethod($data['compressionMethod']);
        $entry->setGeneralPurposeBitFlags($data['generalPurposeBitFlag']);
        $entry->setDosTime($data['lastModFile']);
        $entry->setCrc($data['crc']);
        $entry->setCompressedSize($data['compressedSize']);
        $entry->setSize($data['uncompressedSize']);
        $entry->setInternalAttributes($data['internalFileAttributes']);
        $entry->setExternalAttributes($data['externalFileAttributes']);
        $entry->setOffset($data['offsetLocalHeader']);
        $entry->setComment($comment);
        $entry->setExtra($extra);

        return $entry;
    }

    /**
     * @param ZipEntry $entry
     *
     * @throws ZipException
     *
     * @return string
     */
    public function readEntryContent(ZipEntry $entry)
    {
        if ($entry->isDirectory()) {
            return null;
        }

        if (!($entry instanceof ZipSourceEntry)) {
            throw new InvalidArgumentException('entry must be ' . ZipSourceEntry::class);
        }
        $isEncrypted = $entry->isEncrypted();

        if ($isEncrypted && $entry->getPassword() === null) {
            throw new ZipException('Can not password from entry ' . $entry->getName());
        }

        $startPos = $pos = $entry->getOffset();

        fseek($this->in, $startPos);

        // local file header signature     4 bytes  (0x04034b50)
        if (unpack('V', fread($this->in, 4))[1] !== ZipEntry::LOCAL_FILE_HEADER_SIG) {
            throw new ZipException($entry->getName() . ' (expected Local File Header)');
        }
        fseek($this->in, $pos + ZipEntry::LOCAL_FILE_HEADER_FILE_NAME_LENGTH_POS);
        // file name length                2 bytes
        // extra field length              2 bytes
        $data = unpack('vfileLength/vextraLength', fread($this->in, 4));
        $pos += ZipEntry::LOCAL_FILE_HEADER_MIN_LEN + $data['fileLength'] + $data['extraLength'];

        if ($entry->getCrc() === ZipEntry::UNKNOWN) {
            throw new ZipException(sprintf('Missing crc for entry %s', $entry->getName()));
        }

        $method = $entry->getMethod();

        fseek($this->in, $pos);

        // Get raw entry content
        $compressedSize = $entry->getCompressedSize();
        $content = '';

        if ($compressedSize > 0) {
            $offset = 0;

            while ($offset < $compressedSize) {
                $read = min(8192 /* chunk size */, $compressedSize - $offset);
                $content .= fread($this->in, $read);
                $offset += $read;
            }
        }

        $skipCheckCrc = false;

        if ($isEncrypted) {
            if ($method === ZipEntry::METHOD_WINZIP_AES) {
                // Strong Encryption Specification - WinZip AES
                $winZipAesEngine = new WinZipAesEngine($entry);
                $content = $winZipAesEngine->decrypt($content);
                /**
                 * @var WinZipAesEntryExtraField $field
                 */
                $field = $entry->getExtraFieldsCollection()->get(WinZipAesEntryExtraField::getHeaderId());
                $method = $field->getMethod();
                $entry->setEncryptionMethod($field->getEncryptionMethod());
                $skipCheckCrc = true;
            } else {
                // Traditional PKWARE Decryption
                $zipCryptoEngine = new TraditionalPkwareEncryptionEngine($entry);
                $content = $zipCryptoEngine->decrypt($content);
                $entry->setEncryptionMethod(ZipFile::ENCRYPTION_METHOD_TRADITIONAL);
            }

            if (!$skipCheckCrc) {
                // Check CRC32 in the Local File Header or Data Descriptor.
                $localCrc = null;

                if ($entry->getGeneralPurposeBitFlag(ZipEntry::GPBF_DATA_DESCRIPTOR)) {
                    // The CRC32 is in the Data Descriptor after the compressed size.
                    // Note the Data Descriptor's Signature is optional:
                    // All newer apps should write it (and so does TrueVFS),
                    // but older apps might not.
                    fseek($this->in, $pos + $compressedSize);
                    $localCrc = unpack('V', fread($this->in, 4))[1];

                    if ($localCrc === ZipEntry::DATA_DESCRIPTOR_SIG) {
                        $localCrc = unpack('V', fread($this->in, 4))[1];
                    }
                } else {
                    fseek($this->in, $startPos + 14);
                    // The CRC32 in the Local File Header.
                    $localCrc = fread($this->in, 4)[1];
                }

                if (\PHP_INT_SIZE === 4) {
                    if (sprintf('%u', $entry->getCrc()) === sprintf('%u', $localCrc)) {
                        throw new Crc32Exception($entry->getName(), $entry->getCrc(), $localCrc);
                    }
                } elseif ($localCrc !== $entry->getCrc()) {
                    throw new Crc32Exception($entry->getName(), $entry->getCrc(), $localCrc);
                }
            }
        }

        switch ($method) {
            case ZipFile::METHOD_STORED:
                break;

            case ZipFile::METHOD_DEFLATED:
                /** @noinspection PhpUsageOfSilenceOperatorInspection */
                $content = @gzinflate($content);
                break;

            case ZipFile::METHOD_BZIP2:
                if (!\extension_loaded('bz2')) {
                    throw new ZipException('Extension bzip2 not install');
                }
                /** @noinspection PhpComposerExtensionStubsInspection */
                $content = bzdecompress($content);

                if (\is_int($content)) { // decompress error
                    $content = false;
                }
                break;
            default:
                throw new ZipUnsupportMethodException(
                    $entry->getName() .
                    ' (compression method ' . $method . ' is not supported)'
                );
        }

        if ($content === false) {
            if ($isEncrypted) {
                throw new ZipAuthenticationException(
                    sprintf(
                        'Invalid password for zip entry "%s"',
                        $entry->getName()
                    )
                );
            }

            throw new ZipException(
                sprintf(
                    'Failed to get the contents of the zip entry "%s"',
                    $entry->getName()
                )
            );
        }

        if (!$skipCheckCrc) {
            $localCrc = crc32($content);

            if (sprintf('%u', $entry->getCrc()) !== sprintf('%u', $localCrc)) {
                if ($isEncrypted) {
                    throw new ZipAuthenticationException(
                        sprintf(
                            'Invalid password for zip entry "%s"',
                            $entry->getName()
                        )
                    );
                }

                throw new Crc32Exception($entry->getName(), $entry->getCrc(), $localCrc);
            }
        }

        return $content;
    }

    /**
     * @return resource
     */
    public function getStream()
    {
        return $this->in;
    }

    /**
     * Copy the input stream of the LOC entry zip and the data into
     * the output stream and zip the alignment if necessary.
     *
     * @param ZipEntry                 $entry
     * @param ZipOutputStreamInterface $out
     *
     * @throws ZipException
     */
    public function copyEntry(ZipEntry $entry, ZipOutputStreamInterface $out)
    {
        $pos = $entry->getOffset();

        if ($pos === ZipEntry::UNKNOWN) {
            throw new ZipException(sprintf('Missing local header offset for entry %s', $entry->getName()));
        }

        $nameLength = \strlen($entry->getName());

        fseek($this->in, $pos + ZipEntry::LOCAL_FILE_HEADER_MIN_LEN - 2, \SEEK_SET);
        $sourceExtraLength = $destExtraLength = unpack('v', fread($this->in, 2))[1];

        if ($sourceExtraLength > 0) {
            // read Local File Header extra fields
            fseek($this->in, $pos + ZipEntry::LOCAL_FILE_HEADER_MIN_LEN + $nameLength, \SEEK_SET);
            $extra = '';
            $offset = 0;

            while ($offset < $sourceExtraLength) {
                $read = min(8192 /* chunk size */, $sourceExtraLength - $offset);
                $extra .= fread($this->in, $read);
                $offset += $read;
            }
            $extraFieldsCollection = ExtraFieldsFactory::createExtraFieldCollections($extra, $entry);

            if (isset($extraFieldsCollection[ApkAlignmentExtraField::getHeaderId()]) && $this->zipModel->isZipAlign()) {
                unset($extraFieldsCollection[ApkAlignmentExtraField::getHeaderId()]);
                $destExtraLength = \strlen(ExtraFieldsFactory::createSerializedData($extraFieldsCollection));
            }
        } else {
            $extraFieldsCollection = new ExtraFieldsCollection();
        }

        $dataAlignmentMultiple = $this->zipModel->getZipAlign();
        $copyInToOutLength = $entry->getCompressedSize();

        fseek($this->in, $pos, \SEEK_SET);

        if (
            $this->zipModel->isZipAlign() &&
            !$entry->isEncrypted() &&
            $entry->getMethod() === ZipFile::METHOD_STORED
        ) {
            if (StringUtil::endsWith($entry->getName(), '.so')) {
                $dataAlignmentMultiple = ApkAlignmentExtraField::ANDROID_COMMON_PAGE_ALIGNMENT_BYTES;
            }

            $dataMinStartOffset =
                ftell($out->getStream()) +
                ZipEntry::LOCAL_FILE_HEADER_MIN_LEN +
                $destExtraLength +
                $nameLength +
                ApkAlignmentExtraField::ALIGNMENT_ZIP_EXTRA_MIN_SIZE_BYTES;
            $padding =
                ($dataAlignmentMultiple - ($dataMinStartOffset % $dataAlignmentMultiple))
                % $dataAlignmentMultiple;

            $alignExtra = new ApkAlignmentExtraField();
            $alignExtra->setMultiple($dataAlignmentMultiple);
            $alignExtra->setPadding($padding);
            $extraFieldsCollection->add($alignExtra);

            $extra = ExtraFieldsFactory::createSerializedData($extraFieldsCollection);

            // copy Local File Header without extra field length
            // from input stream to output stream
            stream_copy_to_stream($this->in, $out->getStream(), ZipEntry::LOCAL_FILE_HEADER_MIN_LEN - 2);
            // write new extra field length (2 bytes) to output stream
            fwrite($out->getStream(), pack('v', \strlen($extra)));
            // skip 2 bytes to input stream
            fseek($this->in, 2, \SEEK_CUR);
            // copy name from input stream to output stream
            stream_copy_to_stream($this->in, $out->getStream(), $nameLength);
            // write extra field to output stream
            fwrite($out->getStream(), $extra);
            // skip source extraLength from input stream
            fseek($this->in, $sourceExtraLength, \SEEK_CUR);
        } else {
            $copyInToOutLength += ZipEntry::LOCAL_FILE_HEADER_MIN_LEN + $sourceExtraLength + $nameLength;
        }

        if ($entry->getGeneralPurposeBitFlag(ZipEntry::GPBF_DATA_DESCRIPTOR)) {
//            crc-32                          4 bytes
//            compressed size                 4 bytes
//            uncompressed size               4 bytes
            $copyInToOutLength += 12;

            if ($entry->isZip64ExtensionsRequired()) {
//              compressed size                 +4 bytes
//              uncompressed size               +4 bytes
                $copyInToOutLength += 8;
            }
        }
        // copy loc, data, data descriptor from input to output stream
        stream_copy_to_stream($this->in, $out->getStream(), $copyInToOutLength);
    }

    /**
     * @param ZipEntry                 $entry
     * @param ZipOutputStreamInterface $out
     */
    public function copyEntryData(ZipEntry $entry, ZipOutputStreamInterface $out)
    {
        $offset = $entry->getOffset();
        $nameLength = \strlen($entry->getName());

        fseek($this->in, $offset + ZipEntry::LOCAL_FILE_HEADER_MIN_LEN - 2, \SEEK_SET);
        $extraLength = unpack('v', fread($this->in, 2))[1];

        fseek($this->in, $offset + ZipEntry::LOCAL_FILE_HEADER_MIN_LEN + $nameLength + $extraLength, \SEEK_SET);
        // copy raw data from input stream to output stream
        stream_copy_to_stream($this->in, $out->getStream(), $entry->getCompressedSize());
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close()
    {
        if ($this->in !== null) {
            fclose($this->in);
            $this->in = null;
        }
    }
}
