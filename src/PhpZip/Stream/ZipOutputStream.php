<?php

namespace PhpZip\Stream;

use PhpZip\Crypto\TraditionalPkwareEncryptionEngine;
use PhpZip\Crypto\WinZipAesEngine;
use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\RuntimeException;
use PhpZip\Exception\ZipException;
use PhpZip\Extra\ExtraFieldsFactory;
use PhpZip\Extra\Fields\ApkAlignmentExtraField;
use PhpZip\Extra\Fields\WinZipAesEntryExtraField;
use PhpZip\Extra\Fields\Zip64ExtraField;
use PhpZip\Model\EndOfCentralDirectory;
use PhpZip\Model\Entry\OutputOffsetEntry;
use PhpZip\Model\Entry\ZipChangesEntry;
use PhpZip\Model\Entry\ZipSourceEntry;
use PhpZip\Model\ZipEntry;
use PhpZip\Model\ZipModel;
use PhpZip\Util\PackUtil;
use PhpZip\Util\StringUtil;
use PhpZip\ZipFileInterface;

/**
 * Write zip file
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipOutputStream implements ZipOutputStreamInterface
{
    /**
     * @var resource
     */
    protected $out;
    /**
     * @var ZipModel
     */
    protected $zipModel;

    /**
     * ZipOutputStream constructor.
     * @param resource $out
     * @param ZipModel $zipModel
     */
    public function __construct($out, ZipModel $zipModel)
    {
        if (!is_resource($out)) {
            throw new InvalidArgumentException('$out must be resource');
        }
        $this->out = $out;
        $this->zipModel = $zipModel;
    }

    /**
     * @throws ZipException
     */
    public function writeZip()
    {
        $entries = $this->zipModel->getEntries();
        $outPosEntries = [];
        foreach ($entries as $entry) {
            $outPosEntries[] = new OutputOffsetEntry(ftell($this->out), $entry);
            $this->writeEntry($entry);
        }
        $centralDirectoryOffset = ftell($this->out);
        foreach ($outPosEntries as $outputEntry) {
            $this->writeCentralDirectoryHeader($outputEntry);
        }
        $this->writeEndOfCentralDirectoryRecord($centralDirectoryOffset);
    }

    /**
     * @param ZipEntry $entry
     * @throws ZipException
     */
    public function writeEntry(ZipEntry $entry)
    {
        if ($entry instanceof ZipSourceEntry) {
            $entry->getInputStream()->copyEntry($entry, $this);
            return;
        }

        $entryContent = $this->entryCommitChangesAndReturnContent($entry);

        $offset = ftell($this->out);
        $compressedSize = $entry->getCompressedSize();

        $extra = $entry->getExtra();

        $nameLength = strlen($entry->getName());
        $extraLength = strlen($extra);

        // zip align
        if (
            $this->zipModel->isZipAlign() &&
            !$entry->isEncrypted() &&
            $entry->getMethod() === ZipFileInterface::METHOD_STORED
        ) {
            $dataAlignmentMultiple = $this->zipModel->getZipAlign();
            if (StringUtil::endsWith($entry->getName(), '.so')) {
                $dataAlignmentMultiple = ApkAlignmentExtraField::ANDROID_COMMON_PAGE_ALIGNMENT_BYTES;
            }
            $dataMinStartOffset =
                $offset +
                ZipEntry::LOCAL_FILE_HEADER_MIN_LEN +
                $extraLength +
                $nameLength +
                ApkAlignmentExtraField::ALIGNMENT_ZIP_EXTRA_MIN_SIZE_BYTES;

            $padding =
                ($dataAlignmentMultiple - ($dataMinStartOffset % $dataAlignmentMultiple))
                % $dataAlignmentMultiple;

            $alignExtra = new ApkAlignmentExtraField();
            $alignExtra->setMultiple($dataAlignmentMultiple);
            $alignExtra->setPadding($padding);

            $extraFieldsCollection = clone $entry->getExtraFieldsCollection();
            $extraFieldsCollection->add($alignExtra);

            $extra = ExtraFieldsFactory::createSerializedData($extraFieldsCollection);
            $extraLength = strlen($extra);
        }

        $size = $nameLength + $extraLength;
        if ($size > 0xffff) {
            throw new ZipException(
                $entry->getName() . " (the total size of " . $size .
                " bytes for the name, extra fields and comment " .
                "exceeds the maximum size of " . 0xffff . " bytes)"
            );
        }

        $dd = $entry->isDataDescriptorRequired();
        fwrite(
            $this->out,
            pack(
                'VvvvVVVVvv',
                // local file header signature     4 bytes  (0x04034b50)
                ZipEntry::LOCAL_FILE_HEADER_SIG,
                // version needed to extract       2 bytes
                $entry->getVersionNeededToExtract(),
                // general purpose bit flag        2 bytes
                $entry->getGeneralPurposeBitFlags(),
                // compression method              2 bytes
                $entry->getMethod(),
                // last mod file time              2 bytes
                // last mod file date              2 bytes
                $entry->getDosTime(),
                // crc-32                          4 bytes
                $dd ? 0 : $entry->getCrc(),
                // compressed size                 4 bytes
                $dd ? 0 : $entry->getCompressedSize(),
                // uncompressed size               4 bytes
                $dd ? 0 : $entry->getSize(),
                // file name length                2 bytes
                $nameLength,
                // extra field length              2 bytes
                $extraLength
            )
        );
        if ($nameLength > 0) {
            fwrite($this->out, $entry->getName());
        }
        if ($extraLength > 0) {
            fwrite($this->out, $extra);
        }

        if ($entry instanceof ZipChangesEntry && !$entry->isChangedContent()) {
            $entry->getSourceEntry()->getInputStream()->copyEntryData($entry->getSourceEntry(), $this);
        } elseif ($entryContent !== null) {
            fwrite($this->out, $entryContent);
        }

        assert(ZipEntry::UNKNOWN !== $entry->getCrc());
        assert(ZipEntry::UNKNOWN !== $entry->getSize());
        if ($entry->getGeneralPurposeBitFlag(ZipEntry::GPBF_DATA_DESCRIPTOR)) {
            // data descriptor signature       4 bytes  (0x08074b50)
            // crc-32                          4 bytes
            fwrite($this->out, pack('VV', ZipEntry::DATA_DESCRIPTOR_SIG, $entry->getCrc()));
            // compressed size                 4 or 8 bytes
            // uncompressed size               4 or 8 bytes
            if ($entry->isZip64ExtensionsRequired()) {
                fwrite($this->out, PackUtil::packLongLE($compressedSize));
                fwrite($this->out, PackUtil::packLongLE($entry->getSize()));
            } else {
                fwrite($this->out, pack('VV', $entry->getCompressedSize(), $entry->getSize()));
            }
        } elseif ($compressedSize != $entry->getCompressedSize()) {
            throw new ZipException(
                $entry->getName() . " (expected compressed entry size of "
                . $entry->getCompressedSize() . " bytes, " .
                "but is actually " . $compressedSize . " bytes)"
            );
        }
    }

    /**
     * @param ZipEntry $entry
     * @return null|string
     * @throws ZipException
     */
    protected function entryCommitChangesAndReturnContent(ZipEntry $entry)
    {
        if ($entry->getPlatform() === ZipEntry::UNKNOWN) {
            $entry->setPlatform(ZipEntry::PLATFORM_UNIX);
        }
        if ($entry->getTime() === ZipEntry::UNKNOWN) {
            $entry->setTime(time());
        }
        $method = $entry->getMethod();

        $encrypted = $entry->isEncrypted();
        // See appendix D of PKWARE's ZIP File Format Specification.
        $utf8 = true;

        if ($encrypted && $entry->getPassword() === null) {
            throw new ZipException("Can not password from entry " . $entry->getName());
        }

        // Compose General Purpose Bit Flag.
        $general = ($encrypted ? ZipEntry::GPBF_ENCRYPTED : 0)
            | ($entry->isDataDescriptorRequired() ? ZipEntry::GPBF_DATA_DESCRIPTOR : 0)
            | ($utf8 ? ZipEntry::GPBF_UTF8 : 0);

        $entryContent = null;
        $extraFieldsCollection = $entry->getExtraFieldsCollection();
        if (!($entry instanceof ZipChangesEntry && !$entry->isChangedContent())) {
            $entryContent = $entry->getEntryContent();

            if ($entryContent !== null) {
                $entry->setSize(strlen($entryContent));
                $entry->setCrc(crc32($entryContent));

                if ($encrypted && $method === ZipEntry::METHOD_WINZIP_AES) {
                    /**
                     * @var WinZipAesEntryExtraField $field
                     */
                    $field = $extraFieldsCollection->get(WinZipAesEntryExtraField::getHeaderId());
                    if ($field !== null) {
                        $method = $field->getMethod();
                    }
                }

                switch ($method) {
                    case ZipFileInterface::METHOD_STORED:
                        break;

                    case ZipFileInterface::METHOD_DEFLATED:
                        $entryContent = gzdeflate($entryContent, $entry->getCompressionLevel());
                        break;

                    case ZipFileInterface::METHOD_BZIP2:
                        $compressionLevel = $entry->getCompressionLevel() === ZipFileInterface::LEVEL_DEFAULT_COMPRESSION ?
                            ZipEntry::LEVEL_DEFAULT_BZIP2_COMPRESSION :
                            $entry->getCompressionLevel();
                        /** @noinspection PhpComposerExtensionStubsInspection */
                        $entryContent = bzcompress($entryContent, $compressionLevel);
                        if (is_int($entryContent)) {
                            throw new ZipException('Error bzip2 compress. Error code: ' . $entryContent);
                        }
                        break;

                    case ZipEntry::UNKNOWN:
                        $entryContent = $this->determineBestCompressionMethod($entry, $entryContent);
                        $method = $entry->getMethod();
                        break;

                    default:
                        throw new ZipException($entry->getName() . " (unsupported compression method " . $method . ")");
                }

                if ($method === ZipFileInterface::METHOD_DEFLATED) {
                    $bit1 = false;
                    $bit2 = false;
                    switch ($entry->getCompressionLevel()) {
                        case ZipFileInterface::LEVEL_BEST_COMPRESSION:
                            $bit1 = true;
                            break;

                        case ZipFileInterface::LEVEL_FAST:
                            $bit2 = true;
                            break;

                        case ZipFileInterface::LEVEL_SUPER_FAST:
                            $bit1 = true;
                            $bit2 = true;
                            break;
                    }

                    $general |= ($bit1 ? ZipEntry::GPBF_COMPRESSION_FLAG1 : 0);
                    $general |= ($bit2 ? ZipEntry::GPBF_COMPRESSION_FLAG2 : 0);
                }

                if ($encrypted) {
                    if (in_array($entry->getEncryptionMethod(), [
                        ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_128,
                        ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_192,
                        ZipFileInterface::ENCRYPTION_METHOD_WINZIP_AES_256,
                    ], true)) {
                        $keyStrength = WinZipAesEntryExtraField::getKeyStrangeFromEncryptionMethod($entry->getEncryptionMethod()); // size bits
                        $field = ExtraFieldsFactory::createWinZipAesEntryExtra();
                        $field->setKeyStrength($keyStrength);
                        $field->setMethod($method);
                        $size = $entry->getSize();
                        if ($size >= 20 && $method !== ZipFileInterface::METHOD_BZIP2) {
                            $field->setVendorVersion(WinZipAesEntryExtraField::VV_AE_1);
                        } else {
                            $field->setVendorVersion(WinZipAesEntryExtraField::VV_AE_2);
                            $entry->setCrc(0);
                        }
                        $extraFieldsCollection->add($field);
                        $entry->setMethod(ZipEntry::METHOD_WINZIP_AES);

                        $winZipAesEngine = new WinZipAesEngine($entry);
                        $entryContent = $winZipAesEngine->encrypt($entryContent);
                    } elseif ($entry->getEncryptionMethod() === ZipFileInterface::ENCRYPTION_METHOD_TRADITIONAL) {
                        $zipCryptoEngine = new TraditionalPkwareEncryptionEngine($entry);
                        $entryContent = $zipCryptoEngine->encrypt($entryContent);
                    }
                }

                $compressedSize = strlen($entryContent);
                $entry->setCompressedSize($compressedSize);
            }
        }

        // Commit changes.
        $entry->setGeneralPurposeBitFlags($general);

        if ($entry->isZip64ExtensionsRequired()) {
            $extraFieldsCollection->add(ExtraFieldsFactory::createZip64Extra($entry));
        } elseif ($extraFieldsCollection->has(Zip64ExtraField::getHeaderId())) {
            $extraFieldsCollection->remove(Zip64ExtraField::getHeaderId());
        }
        return $entryContent;
    }

    /**
     * @param ZipEntry $entry
     * @param string $content
     * @return string
     * @throws ZipException
     */
    protected function determineBestCompressionMethod(ZipEntry $entry, $content)
    {
        if ($content !== null) {
            $entryContent = gzdeflate($content, $entry->getCompressionLevel());
            if (strlen($entryContent) < strlen($content)) {
                $entry->setMethod(ZipFileInterface::METHOD_DEFLATED);
                return $entryContent;
            }
            $entry->setMethod(ZipFileInterface::METHOD_STORED);
        }
        return $content;
    }

    /**
     * Writes a Central File Header record.
     *
     * @param OutputOffsetEntry $outEntry
     */
    protected function writeCentralDirectoryHeader(OutputOffsetEntry $outEntry)
    {
        $entry = $outEntry->getEntry();
        $compressedSize = $entry->getCompressedSize();
        $size = $entry->getSize();
        // This test MUST NOT include the CRC-32 because VV_AE_2 sets it to
        // UNKNOWN!
        if (($compressedSize | $size) === ZipEntry::UNKNOWN) {
            throw new RuntimeException("invalid entry");
        }
        $extra = $entry->getExtra();
        $extraSize = strlen($extra);

        $commentLength = strlen($entry->getComment());
        fwrite(
            $this->out,
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
                $outEntry->getOffset()
            )
        );
        // file name (variable size)
        fwrite($this->out, $entry->getName());
        if ($extraSize > 0) {
            // extra field (variable size)
            fwrite($this->out, $extra);
        }
        if ($commentLength > 0) {
            // file comment (variable size)
            fwrite($this->out, $entry->getComment());
        }
    }

    protected function writeEndOfCentralDirectoryRecord($centralDirectoryOffset)
    {
        $centralDirectoryEntriesCount = count($this->zipModel);
        $position = ftell($this->out);
        $centralDirectorySize = $position - $centralDirectoryOffset;
        $centralDirectoryEntriesZip64 = $centralDirectoryEntriesCount > 0xffff;
        $centralDirectorySizeZip64 = $centralDirectorySize > 0xffffffff;
        $centralDirectoryOffsetZip64 = $centralDirectoryOffset > 0xffffffff;
        $centralDirectoryEntries16 = $centralDirectoryEntriesZip64 ? 0xffff : (int)$centralDirectoryEntriesCount;
        $centralDirectorySize32 = $centralDirectorySizeZip64 ? 0xffffffff : $centralDirectorySize;
        $centralDirectoryOffset32 = $centralDirectoryOffsetZip64 ? 0xffffffff : $centralDirectoryOffset;
        $zip64 // ZIP64 extensions?
            = $centralDirectoryEntriesZip64
            || $centralDirectorySizeZip64
            || $centralDirectoryOffsetZip64;
        if ($zip64) {
            // [zip64 end of central directory record]
            // relative offset of the zip64 end of central directory record
            $zip64EndOfCentralDirectoryOffset = $position;
            // zip64 end of central dir
            // signature                       4 bytes  (0x06064b50)
            fwrite($this->out, pack('V', EndOfCentralDirectory::ZIP64_END_OF_CENTRAL_DIRECTORY_RECORD_SIG));
            // size of zip64 end of central
            // directory record                8 bytes
            fwrite($this->out, PackUtil::packLongLE(EndOfCentralDirectory::ZIP64_END_OF_CENTRAL_DIRECTORY_RECORD_MIN_LEN - 12));
            // version made by                 2 bytes
            // version needed to extract       2 bytes
            //                                 due to potential use of BZIP2 compression
            // number of this disk             4 bytes
            // number of the disk with the
            // start of the central directory  4 bytes
            fwrite($this->out, pack('vvVV', 63, 46, 0, 0));
            // total number of entries in the
            // central directory on this disk  8 bytes
            fwrite($this->out, PackUtil::packLongLE($centralDirectoryEntriesCount));
            // total number of entries in the
            // central directory               8 bytes
            fwrite($this->out, PackUtil::packLongLE($centralDirectoryEntriesCount));
            // size of the central directory   8 bytes
            fwrite($this->out, PackUtil::packLongLE($centralDirectorySize));
            // offset of start of central
            // directory with respect to
            // the starting disk number        8 bytes
            fwrite($this->out, PackUtil::packLongLE($centralDirectoryOffset));
            // zip64 extensible data sector    (variable size)

            // [zip64 end of central directory locator]
            // signature                       4 bytes  (0x07064b50)
            // number of the disk with the
            // start of the zip64 end of
            // central directory               4 bytes
            fwrite($this->out, pack('VV', EndOfCentralDirectory::ZIP64_END_OF_CENTRAL_DIRECTORY_LOCATOR_SIG, 0));
            // relative offset of the zip64
            // end of central directory record 8 bytes
            fwrite($this->out, PackUtil::packLongLE($zip64EndOfCentralDirectoryOffset));
            // total number of disks           4 bytes
            fwrite($this->out, pack('V', 1));
        }
        $comment = $this->zipModel->getArchiveComment();
        $commentLength = strlen($comment);
        fwrite(
            $this->out,
            pack(
                'VvvvvVVv',
                // end of central dir signature    4 bytes  (0x06054b50)
                EndOfCentralDirectory::END_OF_CENTRAL_DIRECTORY_RECORD_SIG,
                // number of this disk             2 bytes
                0,
                // number of the disk with the
                // start of the central directory  2 bytes
                0,
                // total number of entries in the
                // central directory on this disk  2 bytes
                $centralDirectoryEntries16,
                // total number of entries in
                // the central directory           2 bytes
                $centralDirectoryEntries16,
                // size of the central directory   4 bytes
                $centralDirectorySize32,
                // offset of start of central
                // directory with respect to
                // the starting disk number        4 bytes
                $centralDirectoryOffset32,
                // .ZIP file comment length        2 bytes
                $commentLength
            )
        );
        if ($commentLength > 0) {
            // .ZIP file comment       (variable size)
            fwrite($this->out, $comment);
        }
    }

    /**
     * @return resource
     */
    public function getStream()
    {
        return $this->out;
    }
}
