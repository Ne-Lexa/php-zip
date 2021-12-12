<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\IO;

use PhpZip\Constants\DosCodePage;
use PhpZip\Constants\GeneralPurposeBitFlag;
use PhpZip\Constants\ZipCompressionMethod;
use PhpZip\Constants\ZipConstants;
use PhpZip\Constants\ZipEncryptionMethod;
use PhpZip\Constants\ZipOptions;
use PhpZip\Exception\Crc32Exception;
use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipException;
use PhpZip\IO\Filter\Cipher\Pkware\PKDecryptionStreamFilter;
use PhpZip\IO\Filter\Cipher\WinZipAes\WinZipAesDecryptionStreamFilter;
use PhpZip\Model\Data\ZipSourceFileData;
use PhpZip\Model\EndOfCentralDirectory;
use PhpZip\Model\Extra\ExtraFieldsCollection;
use PhpZip\Model\Extra\Fields\UnicodePathExtraField;
use PhpZip\Model\Extra\Fields\UnrecognizedExtraField;
use PhpZip\Model\Extra\Fields\WinZipAesExtraField;
use PhpZip\Model\Extra\Fields\Zip64ExtraField;
use PhpZip\Model\Extra\ZipExtraDriver;
use PhpZip\Model\Extra\ZipExtraField;
use PhpZip\Model\ImmutableZipContainer;
use PhpZip\Model\ZipEntry;

/**
 * Zip reader.
 */
class ZipReader
{
    /** @var int file size */
    protected int $size;

    /** @var resource */
    protected $inStream;

    protected array $options;

    /**
     * @param resource $inStream
     */
    public function __construct($inStream, array $options = [])
    {
        if (!\is_resource($inStream)) {
            throw new InvalidArgumentException('Stream must be a resource');
        }
        $type = get_resource_type($inStream);

        if ($type !== 'stream') {
            throw new InvalidArgumentException("Invalid resource type {$type}.");
        }
        $meta = stream_get_meta_data($inStream);

        $wrapperType = $meta['wrapper_type'] ?? 'Unknown';
        $supportStreamWrapperTypes = ['plainfile', 'PHP', 'user-space'];

        if (!\in_array($wrapperType, $supportStreamWrapperTypes, true)) {
            throw new InvalidArgumentException(
                'The stream wrapper type "' . $wrapperType . '" is not supported. Support: ' . implode(
                    ', ',
                    $supportStreamWrapperTypes
                )
            );
        }

        if (
            $wrapperType === 'plainfile'
            && (
                $meta['stream_type'] === 'dir'
                || (isset($meta['uri']) && is_dir($meta['uri']))
            )
        ) {
            throw new InvalidArgumentException('Directory stream not supported');
        }

        $seekable = $meta['seekable'];

        if (!$seekable) {
            throw new InvalidArgumentException('Resource does not support seekable.');
        }
        $this->size = fstat($inStream)['size'];
        $this->inStream = $inStream;

        /** @noinspection AdditionOperationOnArraysInspection */
        $options += $this->getDefaultOptions();
        $this->options = $options;
    }

    protected function getDefaultOptions(): array
    {
        return [
            ZipOptions::CHARSET => null,
        ];
    }

    /**
     * @throws ZipException
     */
    public function read(): ImmutableZipContainer
    {
        if ($this->size < ZipConstants::END_CD_MIN_LEN) {
            throw new ZipException('Corrupt zip file');
        }

        $endOfCentralDirectory = $this->readEndOfCentralDirectory();
        $entries = $this->readCentralDirectory($endOfCentralDirectory);

        return new ImmutableZipContainer($entries, $endOfCentralDirectory->getComment());
    }

    public function getStreamMetaData(): array
    {
        return stream_get_meta_data($this->inStream);
    }

    /**
     * Read End of central directory record.
     *
     * end of central dir signature    4 bytes  (0x06054b50)
     * number of this disk             2 bytes
     * number of the disk with the
     * start of the central directory  2 bytes
     * total number of entries in the
     * central directory on this disk  2 bytes
     * total number of entries in
     * the central directory           2 bytes
     * size of the central directory   4 bytes
     * offset of start of central
     * directory with respect to
     * the starting disk number        4 bytes
     * .ZIP file comment length        2 bytes
     * .ZIP file comment       (variable size)
     *
     * @throws ZipException
     */
    protected function readEndOfCentralDirectory(): EndOfCentralDirectory
    {
        if (!$this->findEndOfCentralDirectory()) {
            throw new ZipException('Invalid zip file. The end of the central directory could not be found.');
        }

        $positionECD = ftell($this->inStream) - 4;
        $sizeECD = $this->size - ftell($this->inStream);
        $buffer = fread($this->inStream, $sizeECD);

        [
            'diskNo' => $diskNo,
            'cdDiskNo' => $cdDiskNo,
            'cdEntriesDisk' => $cdEntriesDisk,
            'cdEntries' => $cdEntries,
            'cdSize' => $cdSize,
            'cdPos' => $cdPos,
            'commentLength' => $commentLength,
        ] = unpack(
            'vdiskNo/vcdDiskNo/vcdEntriesDisk/'
            . 'vcdEntries/VcdSize/VcdPos/vcommentLength',
            substr($buffer, 0, 18)
        );

        if (
            $diskNo !== 0
            || $cdDiskNo !== 0
            || $cdEntriesDisk !== $cdEntries
        ) {
            throw new ZipException(
                'ZIP file spanning/splitting is not supported!'
            );
        }
        $comment = null;

        if ($commentLength > 0) {
            // .ZIP file comment       (variable sizeECD)
            $comment = substr($buffer, 18, $commentLength);
        }

        // Check for ZIP64 End Of Central Directory Locator exists.
        $zip64ECDLocatorPosition = $positionECD - ZipConstants::ZIP64_END_CD_LOC_LEN;
        fseek($this->inStream, $zip64ECDLocatorPosition);
        // zip64 end of central dir locator
        // signature                       4 bytes  (0x07064b50)
        if (
            $zip64ECDLocatorPosition > 0
            && unpack('V', fread($this->inStream, 4))[1] === ZipConstants::ZIP64_END_CD_LOC
        ) {
            if (!$this->isZip64Support()) {
                throw new ZipException('ZIP64 not supported this archive.');
            }

            $positionECD = $this->findZip64ECDPosition();
            $endCentralDirectory = $this->readZip64EndOfCentralDirectory($positionECD);
            $endCentralDirectory->setComment($comment);
        } else {
            $endCentralDirectory = new EndOfCentralDirectory(
                $cdEntries,
                $cdPos,
                $cdSize,
                false,
                $comment
            );
        }

        return $endCentralDirectory;
    }

    protected function findEndOfCentralDirectory(): bool
    {
        $max = $this->size - ZipConstants::END_CD_MIN_LEN;
        $min = $max >= 0xFFFF ? $max - 0xFFFF : 0;
        // Search for End of central directory record.
        for ($position = $max; $position >= $min; $position--) {
            fseek($this->inStream, $position);
            // end of central dir signature    4 bytes  (0x06054b50)
            if (unpack('V', fread($this->inStream, 4))[1] !== ZipConstants::END_CD) {
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
    protected function findZip64ECDPosition(): int
    {
        [
            'diskNo' => $diskNo,
            'zip64ECDPos' => $zip64ECDPos,
            'totalDisks' => $totalDisks,
        ] = unpack('VdiskNo/Pzip64ECDPos/VtotalDisks', fread($this->inStream, 16));

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
     * @throws ZipException
     */
    protected function readZip64EndOfCentralDirectory(int $zip64ECDPosition): EndOfCentralDirectory
    {
        fseek($this->inStream, $zip64ECDPosition);

        $buffer = fread($this->inStream, ZipConstants::ZIP64_END_OF_CD_LEN);

        if (unpack('V', $buffer)[1] !== ZipConstants::ZIP64_END_CD) {
            throw new ZipException('Expected ZIP64 End Of Central Directory Record!');
        }

        [
//            'size' => $size,
//            'versionMadeBy' => $versionMadeBy,
//            'extractVersion' => $extractVersion,
            'diskNo' => $diskNo,
            'cdDiskNo' => $cdDiskNo,
            'cdEntriesDisk' => $cdEntriesDisk,
            'entryCount' => $entryCount,
            'cdSize' => $cdSize,
            'cdPos' => $cdPos,
        ] = unpack(
//            'Psize/vversionMadeBy/vextractVersion/'.
            'VdiskNo/VcdDiskNo/PcdEntriesDisk/PentryCount/PcdSize/PcdPos',
            substr($buffer, 16, 40)
        );

//        $platform = ZipPlatform::fromValue(($versionMadeBy & 0xFF00) >> 8);
//        $softwareVersion = $versionMadeBy & 0x00FF;

        if ($diskNo !== 0 || $cdDiskNo !== 0 || $entryCount !== $cdEntriesDisk) {
            throw new ZipException('ZIP file spanning/splitting is not supported!');
        }

        if ($entryCount < 0 || $entryCount > 0x7FFFFFFF) {
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
     * @throws ZipException
     *
     * @return ZipEntry[]
     */
    protected function readCentralDirectory(EndOfCentralDirectory $endCD): array
    {
        $entries = [];

        $cdOffset = $endCD->getCdOffset();
        fseek($this->inStream, $cdOffset);

        if (!($cdStream = fopen('php://temp', 'w+b'))) {
            // @codeCoverageIgnoreStart
            throw new ZipException('A temporary resource cannot be opened for writing.');
            // @codeCoverageIgnoreEnd
        }
        stream_copy_to_stream($this->inStream, $cdStream, $endCD->getCdSize());
        rewind($cdStream);
        for ($numEntries = $endCD->getEntryCount(); $numEntries > 0; $numEntries--) {
            $zipEntry = $this->readZipEntry($cdStream);

            $entryName = $zipEntry->getName();

            /** @var UnicodePathExtraField|null $unicodePathExtraField */
            $unicodePathExtraField = $zipEntry->getExtraField(UnicodePathExtraField::HEADER_ID);

            if ($unicodePathExtraField !== null && $unicodePathExtraField->getCrc32() === crc32($entryName)) {
                $unicodePath = $unicodePathExtraField->getUnicodeValue();

                if ($unicodePath !== '') {
                    $unicodePath = str_replace('\\', '/', $unicodePath);

                    if (substr_count($entryName, '/') === substr_count($unicodePath, '/')) {
                        $entryName = $unicodePath;
                    }
                }
            }

            $entries[$entryName] = $zipEntry;
        }

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
     */
    protected function readZipEntry($stream): ZipEntry
    {
        if (unpack('V', fread($stream, 4))[1] !== ZipConstants::CENTRAL_FILE_HEADER) {
            throw new ZipException('Corrupt zip file. Cannot read zip entry.');
        }

        [
            'versionMadeBy' => $versionMadeBy,
            'versionNeededToExtract' => $versionNeededToExtract,
            'generalPurposeBitFlags' => $generalPurposeBitFlags,
            'compressionMethod' => $compressionMethod,
            'lastModFile' => $dosTime,
            'crc' => $crc,
            'compressedSize' => $compressedSize,
            'uncompressedSize' => $uncompressedSize,
            'fileNameLength' => $fileNameLength,
            'extraFieldLength' => $extraFieldLength,
            'fileCommentLength' => $fileCommentLength,
            'diskNumberStart' => $diskNumberStart,
            'internalFileAttributes' => $internalFileAttributes,
            'externalFileAttributes' => $externalFileAttributes,
            'offsetLocalHeader' => $offsetLocalHeader,
        ] = unpack(
            'vversionMadeBy/vversionNeededToExtract/'
            . 'vgeneralPurposeBitFlags/vcompressionMethod/'
            . 'VlastModFile/Vcrc/VcompressedSize/'
            . 'VuncompressedSize/vfileNameLength/vextraFieldLength/'
            . 'vfileCommentLength/vdiskNumberStart/vinternalFileAttributes/'
            . 'VexternalFileAttributes/VoffsetLocalHeader',
            fread($stream, 42)
        );

        if ($diskNumberStart !== 0) {
            throw new ZipException('ZIP file spanning/splitting is not supported!');
        }

        $isUtf8 = ($generalPurposeBitFlags & GeneralPurposeBitFlag::UTF8) !== 0;

        $name = fread($stream, $fileNameLength);

        $createdOS = ($versionMadeBy & 0xFF00) >> 8;
        $softwareVersion = $versionMadeBy & 0x00FF;

        $extractedOS = ($versionNeededToExtract & 0xFF00) >> 8;
        $extractVersion = $versionNeededToExtract & 0x00FF;
        $comment = null;

        if ($fileCommentLength > 0) {
            $comment = fread($stream, $fileCommentLength);
        }

        // decode code page names
        $fallbackCharset = null;

        if (!$isUtf8 && isset($this->options[ZipOptions::CHARSET])) {
            $charset = $this->options[ZipOptions::CHARSET];

            $fallbackCharset = $charset;
            $name = DosCodePage::toUTF8($name, $charset);

            if ($comment !== null) {
                $comment = DosCodePage::toUTF8($comment, $charset);
            }
        }

        $zipEntry = ZipEntry::create(
            $name,
            $createdOS,
            $extractedOS,
            $softwareVersion,
            $extractVersion,
            $compressionMethod,
            $generalPurposeBitFlags,
            $dosTime,
            $crc,
            $compressedSize,
            $uncompressedSize,
            $internalFileAttributes,
            $externalFileAttributes,
            $offsetLocalHeader,
            $comment,
            $fallbackCharset
        );

        if ($extraFieldLength > 0) {
            $this->parseExtraFields(
                fread($stream, $extraFieldLength),
                $zipEntry
            );

            /** @var Zip64ExtraField|null $extraZip64 */
            $extraZip64 = $zipEntry->getCdExtraField(Zip64ExtraField::HEADER_ID);

            if ($extraZip64 !== null) {
                $this->handleZip64Extra($extraZip64, $zipEntry);
            }
        }

        $this->loadLocalExtraFields($zipEntry);
        $this->handleExtraEncryptionFields($zipEntry);
        $this->handleExtraFields($zipEntry);

        return $zipEntry;
    }

    protected function parseExtraFields(string $buffer, ZipEntry $zipEntry, bool $local = false): ExtraFieldsCollection
    {
        $collection = $local
            ? $zipEntry->getLocalExtraFields()
            : $zipEntry->getCdExtraFields();

        if (!empty($buffer)) {
            $pos = 0;
            $endPos = \strlen($buffer);

            while ($endPos - $pos >= 4) {
                [
                    'headerId' => $headerId,
                    'dataSize' => $dataSize,
                ] = unpack('vheaderId/vdataSize', substr($buffer, $pos, 4));
                $pos += 4;

                if ($endPos - $pos - $dataSize < 0) {
                    break;
                }
                $bufferData = substr($buffer, $pos, $dataSize);

                /** @var string|ZipExtraField|null $className */
                $className = ZipExtraDriver::getClassNameOrNull($headerId);

                try {
                    if ($className !== null) {
                        try {
                            $extraField = $local
                                ? $className::unpackLocalFileData($bufferData, $zipEntry)
                                : $className::unpackCentralDirData($bufferData, $zipEntry);
                        } catch (\Throwable $e) {
                            // skip errors while parsing invalid data
                            continue;
                        }
                    } else {
                        $extraField = new UnrecognizedExtraField($headerId, $bufferData);
                    }
                    $collection->add($extraField);
                } finally {
                    $pos += $dataSize;
                }
            }
        }

        return $collection;
    }

    protected function handleZip64Extra(Zip64ExtraField $extraZip64, ZipEntry $zipEntry): void
    {
        $uncompressedSize = $extraZip64->getUncompressedSize();
        $compressedSize = $extraZip64->getCompressedSize();
        $localHeaderOffset = $extraZip64->getLocalHeaderOffset();

        if ($uncompressedSize !== null) {
            $zipEntry->setUncompressedSize($uncompressedSize);
        }

        if ($compressedSize !== null) {
            $zipEntry->setCompressedSize($compressedSize);
        }

        if ($localHeaderOffset !== null) {
            $zipEntry->setLocalHeaderOffset($localHeaderOffset);
        }
    }

    /**
     * Read Local File Header.
     *
     * local file header signature     4 bytes  (0x04034b50)
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
     * file name (variable size)
     * extra field (variable size)
     *
     * @throws ZipException
     */
    protected function loadLocalExtraFields(ZipEntry $entry): void
    {
        $offsetLocalHeader = $entry->getLocalHeaderOffset();

        fseek($this->inStream, $offsetLocalHeader);

        if (unpack('V', fread($this->inStream, 4))[1] !== ZipConstants::LOCAL_FILE_HEADER) {
            throw new ZipException(sprintf('%s (expected Local File Header)', $entry->getName()));
        }

        fseek($this->inStream, $offsetLocalHeader + ZipConstants::LFH_FILENAME_LENGTH_POS);
        [
            'fileNameLength' => $fileNameLength,
            'extraFieldLength' => $extraFieldLength,
        ] = unpack('vfileNameLength/vextraFieldLength', fread($this->inStream, 4));
        $offsetData = ftell($this->inStream) + $fileNameLength + $extraFieldLength;
        fseek($this->inStream, $fileNameLength, \SEEK_CUR);

        if ($extraFieldLength > 0) {
            $this->parseExtraFields(
                fread($this->inStream, $extraFieldLength),
                $entry,
                true
            );
        }

        $zipData = new ZipSourceFileData($this, $entry, $offsetData);
        $entry->setData($zipData);
    }

    /**
     * @throws ZipException
     */
    private function handleExtraEncryptionFields(ZipEntry $zipEntry): void
    {
        if ($zipEntry->isEncrypted()) {
            if ($zipEntry->getCompressionMethod() === ZipCompressionMethod::WINZIP_AES) {
                /** @var WinZipAesExtraField|null $extraField */
                $extraField = $zipEntry->getExtraField(WinZipAesExtraField::HEADER_ID);

                if ($extraField === null) {
                    throw new ZipException(
                        sprintf(
                            'Extra field 0x%04x (WinZip-AES Encryption) expected for compression method %d',
                            WinZipAesExtraField::HEADER_ID,
                            $zipEntry->getCompressionMethod()
                        )
                    );
                }
                $zipEntry->setCompressionMethod($extraField->getCompressionMethod());
                $zipEntry->setEncryptionMethod($extraField->getEncryptionMethod());
            } else {
                $zipEntry->setEncryptionMethod(ZipEncryptionMethod::PKWARE);
            }
        }
    }

    /**
     * Handle extra data in zip records.
     *
     * This is a special method in which you can process ExtraField
     * and make changes to ZipEntry.
     */
    protected function handleExtraFields(ZipEntry $zipEntry): void
    {
    }

    /**
     * @throws ZipException
     * @throws Crc32Exception
     *
     * @return resource
     */
    public function getEntryStream(ZipSourceFileData $zipFileData)
    {
        $outStream = fopen('php://temp', 'w+b');
        $this->copyUncompressedDataToStream($zipFileData, $outStream);
        rewind($outStream);

        return $outStream;
    }

    /**
     * @param resource $outStream
     *
     * @throws Crc32Exception
     * @throws ZipException
     */
    public function copyUncompressedDataToStream(ZipSourceFileData $zipFileData, $outStream): void
    {
        if (!\is_resource($outStream)) {
            throw new InvalidArgumentException('outStream is not resource');
        }

        $entry = $zipFileData->getSourceEntry();

//        if ($entry->isDirectory()) {
//            throw new InvalidArgumentException('Streams not supported for directories');
//        }

        if ($entry->isStrongEncryption()) {
            throw new ZipException('Not support encryption zip.');
        }

        $compressionMethod = $entry->getCompressionMethod();

        fseek($this->inStream, $zipFileData->getOffset());

        $filters = [];

        $skipCheckCrc = false;
        $isEncrypted = $entry->isEncrypted();

        if ($isEncrypted) {
            if ($entry->getPassword() === null) {
                throw new ZipException('Can not password from entry ' . $entry->getName());
            }

            if (ZipEncryptionMethod::isWinZipAesMethod($entry->getEncryptionMethod())) {
                /** @var WinZipAesExtraField|null $winZipAesExtra */
                $winZipAesExtra = $entry->getExtraField(WinZipAesExtraField::HEADER_ID);

                if ($winZipAesExtra === null) {
                    throw new ZipException(
                        sprintf('WinZip AES must contain the extra field %s', WinZipAesExtraField::HEADER_ID)
                    );
                }
                $compressionMethod = $winZipAesExtra->getCompressionMethod();

                WinZipAesDecryptionStreamFilter::register();
                $cipherFilterName = WinZipAesDecryptionStreamFilter::FILTER_NAME;

                if ($winZipAesExtra->isV2()) {
                    $skipCheckCrc = true;
                }
            } else {
                PKDecryptionStreamFilter::register();
                $cipherFilterName = PKDecryptionStreamFilter::FILTER_NAME;
            }
            $encContextFilter = stream_filter_append(
                $this->inStream,
                $cipherFilterName,
                \STREAM_FILTER_READ,
                [
                    'entry' => $entry,
                ]
            );

            if (!$encContextFilter) {
                throw new \RuntimeException('Not apply filter ' . $cipherFilterName);
            }
            $filters[] = $encContextFilter;
        }

        // hack, see https://groups.google.com/forum/#!topic/alt.comp.lang.php/37_JZeW63uc
        $pos = ftell($this->inStream);
        rewind($this->inStream);
        fseek($this->inStream, $pos);

        $contextDecompress = null;
        switch ($compressionMethod) {
            case ZipCompressionMethod::STORED:
                // file without compression, do nothing
                break;

            case ZipCompressionMethod::DEFLATED:
                if (!($contextDecompress = stream_filter_append(
                    $this->inStream,
                    'zlib.inflate',
                    \STREAM_FILTER_READ
                ))) {
                    throw new \RuntimeException('Could not append filter "zlib.inflate" to stream');
                }
                $filters[] = $contextDecompress;

                break;

            case ZipCompressionMethod::BZIP2:
                if (!($contextDecompress = stream_filter_append(
                    $this->inStream,
                    'bzip2.decompress',
                    \STREAM_FILTER_READ
                ))) {
                    throw new \RuntimeException('Could not append filter "bzip2.decompress" to stream');
                }
                $filters[] = $contextDecompress;

                break;

            default:
                throw new ZipException(
                    sprintf(
                        '%s (compression method %d (%s) is not supported)',
                        $entry->getName(),
                        $compressionMethod,
                        ZipCompressionMethod::getCompressionMethodName($compressionMethod)
                    )
                );
        }

        $limit = $zipFileData->getUncompressedSize();

        $offset = 0;
        $chunkSize = 8192;

        try {
            if ($skipCheckCrc) {
                while ($offset < $limit) {
                    $length = min($chunkSize, $limit - $offset);
                    $buffer = fread($this->inStream, $length);

                    if ($buffer === false) {
                        throw new ZipException(sprintf('Error reading the contents of entry "%s".', $entry->getName()));
                    }
                    fwrite($outStream, $buffer);
                    $offset += $length;
                }
            } else {
                $contextHash = hash_init('crc32b');

                while ($offset < $limit) {
                    $length = min($chunkSize, $limit - $offset);
                    $buffer = fread($this->inStream, $length);

                    if ($buffer === false) {
                        throw new ZipException(sprintf('Error reading the contents of entry "%s".', $entry->getName()));
                    }
                    fwrite($outStream, $buffer);
                    hash_update($contextHash, $buffer);
                    $offset += $length;
                }

                $expectedCrc = (int) hexdec(hash_final($contextHash));

                if ($expectedCrc !== $entry->getCrc()) {
                    throw new Crc32Exception($entry->getName(), $expectedCrc, $entry->getCrc());
                }
            }
        } finally {
            for ($i = \count($filters); $i > 0; $i--) {
                stream_filter_remove($filters[$i - 1]);
            }
        }
    }

    /**
     * @param resource $outStream
     */
    public function copyCompressedDataToStream(ZipSourceFileData $zipData, $outStream): void
    {
        if ($zipData->getCompressedSize() > 0) {
            fseek($this->inStream, $zipData->getOffset());
            stream_copy_to_stream($this->inStream, $outStream, $zipData->getCompressedSize());
        }
    }

    protected function isZip64Support(): bool
    {
        return \PHP_INT_SIZE === 8; // true for 64bit system
    }

    /**
     * @psalm-suppress InvalidPropertyAssignmentValue
     */
    public function close(): void
    {
        if (\is_resource($this->inStream)) {
            fclose($this->inStream);
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
