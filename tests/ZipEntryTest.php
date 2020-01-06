<?php

namespace PhpZip\Tests;

use PHPUnit\Framework\TestCase;
use PhpZip\Constants\DosAttrs;
use PhpZip\Constants\DosCodePage;
use PhpZip\Constants\GeneralPurposeBitFlag;
use PhpZip\Constants\ZipCompressionLevel;
use PhpZip\Constants\ZipCompressionMethod;
use PhpZip\Constants\ZipConstants;
use PhpZip\Constants\ZipEncryptionMethod;
use PhpZip\Constants\ZipPlatform;
use PhpZip\Constants\ZipVersion;
use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipException;
use PhpZip\Exception\ZipUnsupportMethodException;
use PhpZip\Model\Data\ZipFileData;
use PhpZip\Model\Data\ZipNewData;
use PhpZip\Model\Extra\ExtraFieldsCollection;
use PhpZip\Model\Extra\Fields\AsiExtraField;
use PhpZip\Model\Extra\Fields\ExtendedTimestampExtraField;
use PhpZip\Model\Extra\Fields\JarMarkerExtraField;
use PhpZip\Model\Extra\Fields\NewUnixExtraField;
use PhpZip\Model\Extra\Fields\NtfsExtraField;
use PhpZip\Model\Extra\Fields\OldUnixExtraField;
use PhpZip\Model\Extra\Fields\UnicodePathExtraField;
use PhpZip\Model\ZipEntry;

/**
 * Class ZipEntryTest.
 *
 * @internal
 *
 * @small
 */
class ZipEntryTest extends TestCase
{
    public function testEntry()
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getName(), 'entry');
        static::assertFalse($zipEntry->isDirectory());
        static::assertNull($zipEntry->getData());
        static::assertSame($zipEntry->getCompressionMethod(), ZipEntry::UNKNOWN);
        static::assertSame($zipEntry->getCreatedOS(), ZipEntry::UNKNOWN);
        static::assertSame($zipEntry->getExtractedOS(), ZipEntry::UNKNOWN);
        static::assertSame($zipEntry->getSoftwareVersion(), ZipVersion::v10_DEFAULT_MIN);
        static::assertSame($zipEntry->getExtractVersion(), ZipVersion::v10_DEFAULT_MIN);
        static::assertSame($zipEntry->getGeneralPurposeBitFlags(), 0);
        static::assertSame($zipEntry->getDosTime(), ZipEntry::UNKNOWN);
        static::assertSame($zipEntry->getTime(), ZipEntry::UNKNOWN);
        static::assertSame($zipEntry->getCrc(), ZipEntry::UNKNOWN);
        static::assertSame($zipEntry->getCompressedSize(), ZipEntry::UNKNOWN);
        static::assertSame($zipEntry->getUncompressedSize(), ZipEntry::UNKNOWN);
        static::assertSame($zipEntry->getInternalAttributes(), 0);
        static::assertSame($zipEntry->getExternalAttributes(), DosAttrs::DOS_ARCHIVE);
        static::assertSame($zipEntry->getLocalHeaderOffset(), 0);
        static::assertInstanceOf(ExtraFieldsCollection::class, $zipEntry->getCdExtraFields());
        static::assertInstanceOf(ExtraFieldsCollection::class, $zipEntry->getLocalExtraFields());
        static::assertCount(0, $zipEntry->getCdExtraFields());
        static::assertCount(0, $zipEntry->getLocalExtraFields());
        static::assertSame($zipEntry->getComment(), '');
        static::assertNull($zipEntry->getPassword());
        static::assertSame($zipEntry->getEncryptionMethod(), ZipEncryptionMethod::NONE);
        static::assertSame($zipEntry->getCompressionLevel(), ZipCompressionLevel::NORMAL);
        static::assertNull($zipEntry->getCharset());
        static::assertNull($zipEntry->getATime());
        static::assertNull($zipEntry->getCTime());
        static::assertSame($zipEntry->getUnixMode(), 0100644);

        $zipDirEntry = $zipEntry->rename('directory/');
        static::assertNotSame($zipEntry, $zipDirEntry);
        static::assertSame($zipDirEntry->getName(), 'directory/');
        static::assertTrue($zipDirEntry->isDirectory());
        static::assertSame($zipDirEntry->getExternalAttributes(), DosAttrs::DOS_DIRECTORY);
        static::assertSame($zipDirEntry->getUnixMode(), 040755);
        static::assertNotSame($zipDirEntry->getName(), $zipEntry->getName());
        static::assertNotSame($zipDirEntry->isDirectory(), $zipEntry->isDirectory());
        static::assertNotSame($zipDirEntry->getExternalAttributes(), $zipEntry->getExternalAttributes());
        static::assertNotSame($zipDirEntry->getUnixMode(), $zipEntry->getUnixMode());
    }

    /**
     * @dataProvider provideEmptyName
     *
     * @param string|null $entryName
     * @param string      $exceptionMessage
     */
    public function testEmptyName($entryName, $exceptionMessage)
    {
        $this->setExpectedException(InvalidArgumentException::class, $exceptionMessage);

        new ZipEntry($entryName);
    }

    /**
     * @return array
     */
    public function provideEmptyName()
    {
        return [
            ['', 'Empty zip entry name'],
            ['/', 'Empty zip entry name'],
            [null, 'zip entry name is null'],
        ];
    }

    /**
     * @dataProvider provideEntryName
     *
     * @param string $entryName
     * @param string $actualEntryName
     * @param bool   $directory
     */
    public function testEntryName($entryName, $actualEntryName, $directory)
    {
        $entry = new ZipEntry($entryName);
        static::assertSame($entry->getName(), $actualEntryName);
        static::assertSame($entry->isDirectory(), $directory);
    }

    /**
     * @return array
     */
    public function provideEntryName()
    {
        return [
            ['0', '0', false],
            [0, '0', false],
            ['directory/', 'directory/', true],
        ];
    }

    /**
     * @dataProvider provideCompressionMethod
     *
     * @param int $compressionMethod
     *
     * @throws ZipUnsupportMethodException
     */
    public function testCompressionMethod($compressionMethod)
    {
        $entry = new ZipEntry('entry');
        static::assertSame($entry->getCompressionMethod(), ZipEntry::UNKNOWN);

        $entry->setCompressionMethod($compressionMethod);
        static::assertSame($entry->getCompressionMethod(), $compressionMethod);
    }

    /**
     * @return array
     */
    public function provideCompressionMethod()
    {
        $provides = [
            [ZipCompressionMethod::STORED],
            [ZipCompressionMethod::DEFLATED],
        ];

        if (\extension_loaded('bz2')) {
            $provides[] = [ZipCompressionMethod::BZIP2];
        }

        return $provides;
    }

    /**
     * @dataProvider provideOutOfRangeCompressionMethod
     *
     * @param int $compressionMethod
     *
     * @throws ZipUnsupportMethodException
     */
    public function testOutOfRangeCompressionMethod($compressionMethod)
    {
        $this->setExpectedException(InvalidArgumentException::class, 'method out of range: ' . $compressionMethod);

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCompressionMethod($compressionMethod);
    }

    /**
     * @return array
     */
    public function provideOutOfRangeCompressionMethod()
    {
        return [
            [-1],
            [0x44444],
        ];
    }

    /**
     * @dataProvider provideUnsupportCompressionMethod
     *
     * @param int    $compressionMethod
     * @param string $exceptionMessage
     *
     * @throws ZipUnsupportMethodException
     */
    public function testUnsupportCompressionMethod($compressionMethod, $exceptionMessage)
    {
        $this->setExpectedException(ZipUnsupportMethodException::class, $exceptionMessage);

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCompressionMethod($compressionMethod);
    }

    /**
     * @return array
     */
    public function provideUnsupportCompressionMethod()
    {
        return [
            [1, 'Compression method 1 (Shrunk) is not supported.'],
            [2, 'Compression method 2 (Reduced compression factor 1) is not supported.'],
            [3, 'Compression method 3 (Reduced compression factor 2) is not supported.'],
            [4, 'Compression method 4 (Reduced compression factor 3) is not supported.'],
            [5, 'Compression method 5 (Reduced compression factor 4) is not supported.'],
            [6, 'Compression method 6 (Imploded) is not supported.'],
            [7, 'Compression method 7 (Reserved for Tokenizing compression algorithm) is not supported.'],
            [9, 'Compression method 9 (Enhanced Deflating using Deflate64(tm)) is not supported.'],
            [10, 'Compression method 10 (PKWARE Data Compression Library Imploding) is not supported.'],
            [11, 'Compression method 11 (Reserved by PKWARE) is not supported.'],
            [13, 'Compression method 13 (Reserved by PKWARE) is not supported.'],
            [14, 'Compression method 14 (LZMA) is not supported.'],
            [15, 'Compression method 15 (Reserved by PKWARE) is not supported.'],
            [16, 'Compression method 16 (Reserved by PKWARE) is not supported.'],
            [17, 'Compression method 17 (Reserved by PKWARE) is not supported.'],
            [18, 'Compression method 18 (File is compressed using IBM TERSE (new)) is not supported.'],
            [19, 'Compression method 19 (IBM LZ77 z Architecture (PFS)) is not supported.'],
            [96, 'Compression method 96 (WinZip JPEG Compression) is not supported.'],
            [97, 'Compression method 97 (WavPack compressed data) is not supported.'],
            [98, 'Compression method 98 (PPMd version I, Rev 1) is not supported.'],
            [
                ZipCompressionMethod::WINZIP_AES,
                'Compression method ' . ZipCompressionMethod::WINZIP_AES . ' (AES Encryption) is not supported.',
            ],
            [100, 'Compression method 100 (Unknown Method) is not supported.'],
        ];
    }

    public function testCharset()
    {
        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCharset(DosCodePage::CP_CYRILLIC_RUSSIAN);
        static::assertSame($zipEntry->getCharset(), DosCodePage::CP_CYRILLIC_RUSSIAN);

        $zipEntry->setCharset(null);
        static::assertNull($zipEntry->getCharset());
    }

    public function testEmptyCharset()
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Empty charset');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCharset('');
    }

    public function testRenameAndDeleteUnicodePath()
    {
        $entryName = 'файл.txt';
        $charset = DosCodePage::CP_CYRILLIC_RUSSIAN;
        $dosEntryName = DosCodePage::fromUTF8($entryName, $charset);
        static::assertSame(DosCodePage::toUTF8($dosEntryName, $charset), $entryName);

        $unicodePathExtraField = UnicodePathExtraField::create($entryName);

        $zipEntry = new ZipEntry($dosEntryName, $charset);
        static::assertSame($zipEntry->getName(), $dosEntryName);
        static::assertSame($zipEntry->getCharset(), $charset);
        static::assertFalse($zipEntry->isUtf8Flag());
        $zipEntry->addExtraField($unicodePathExtraField);
        static::assertSame(
            $zipEntry->getLocalExtraField(UnicodePathExtraField::HEADER_ID),
            $unicodePathExtraField
        );
        static::assertSame(
            $zipEntry->getCdExtraField(UnicodePathExtraField::HEADER_ID),
            $unicodePathExtraField
        );

        $utf8EntryName = $zipEntry->rename($entryName);
        static::assertSame($utf8EntryName->getName(), $entryName);
        static::assertTrue($utf8EntryName->isUtf8Flag());
        static::assertNull($utf8EntryName->getCharset());
        static::assertNull($utf8EntryName->getLocalExtraField(UnicodePathExtraField::HEADER_ID));
        static::assertNull($utf8EntryName->getCdExtraField(UnicodePathExtraField::HEADER_ID));
    }

    public function testData()
    {
        $zipEntry = new ZipEntry('entry');
        static::assertNull($zipEntry->getData());

        $zipData = new ZipNewData($zipEntry, 'Text contents');

        $zipEntry->setData($zipData);
        static::assertSame($zipEntry->getData(), $zipData);

        $zipEntry->setData(null);
        static::assertNull($zipEntry->getData());
    }

    /**
     * @dataProvider providePlatform
     *
     * @param int $zipOS
     */
    public function testCreatedOS($zipOS)
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getCreatedOS(), ZipEntry::UNKNOWN);
        $zipEntry->setCreatedOS($zipOS);
        static::assertSame($zipEntry->getCreatedOS(), $zipOS);
    }

    /**
     * @return array
     */
    public function providePlatform()
    {
        return [
            [ZipPlatform::OS_DOS],
            [ZipPlatform::OS_UNIX],
            [ZipPlatform::OS_MAC_OSX],
        ];
    }

    /**
     * @dataProvider providePlatform
     *
     * @param int $zipOS
     */
    public function testExtractedOS($zipOS)
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getExtractedOS(), ZipEntry::UNKNOWN);
        $zipEntry->setExtractedOS($zipOS);
        static::assertSame($zipEntry->getExtractedOS(), $zipOS);
    }

    /**
     * @dataProvider provideInvalidPlatform
     *
     * @param int $zipOS
     */
    public function testInvalidCreatedOs($zipOS)
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Platform out of range');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCreatedOS($zipOS);
    }

    /**
     * @return array
     */
    public function provideInvalidPlatform()
    {
        return [
            [-1],
            [0xff + 1],
        ];
    }

    /**
     * @dataProvider provideInvalidPlatform
     *
     * @param int $zipOS
     */
    public function testInvalidExtractedOs($zipOS)
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Platform out of range');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setExtractedOS($zipOS);
    }

    /**
     * @throws ZipException
     */
    public function testAutoExtractVersion()
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getExtractVersion(), ZipVersion::v10_DEFAULT_MIN);

        $zipEntry->setCompressionMethod(ZipCompressionMethod::DEFLATED);
        static::assertSame($zipEntry->getExtractVersion(), ZipVersion::v20_DEFLATED_FOLDER_ZIPCRYPTO);

        static::assertSame(
            (new ZipEntry('directory/'))->getExtractVersion(),
            ZipVersion::v20_DEFLATED_FOLDER_ZIPCRYPTO
        );

        if (\extension_loaded('bz2')) {
            $zipEntry->setCompressionMethod(ZipCompressionMethod::BZIP2);
            static::assertSame($zipEntry->getExtractVersion(), ZipVersion::v46_BZIP2);
        }

        $zipEntry->setCompressionMethod(ZipCompressionMethod::STORED);
        static::assertSame($zipEntry->getExtractVersion(), ZipVersion::v10_DEFAULT_MIN);

        $zipEntry->setPassword('12345', ZipEncryptionMethod::PKWARE);
        static::assertSame($zipEntry->getExtractVersion(), ZipVersion::v20_DEFLATED_FOLDER_ZIPCRYPTO);

        $zipEntry->setEncryptionMethod(ZipEncryptionMethod::WINZIP_AES_256);
        static::assertSame($zipEntry->getExtractVersion(), ZipVersion::v51_ENCR_AES_RC2_CORRECT);
    }

    /**
     * @throws ZipException
     */
    public function testExtractVersion()
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getExtractVersion(), ZipVersion::v10_DEFAULT_MIN);

        $zipEntry->setExtractVersion(ZipVersion::v63_LZMA_PPMD_BLOWFISH_TWOFISH);
        static::assertSame($zipEntry->getExtractVersion(), ZipVersion::v63_LZMA_PPMD_BLOWFISH_TWOFISH);

        $renameEntry = $zipEntry->rename('new_entry');
        static::assertSame($renameEntry->getExtractVersion(), ZipVersion::v63_LZMA_PPMD_BLOWFISH_TWOFISH);

        $renameDirEntry = $zipEntry->rename('new_directory/');
        static::assertSame($renameDirEntry->getExtractVersion(), ZipVersion::v63_LZMA_PPMD_BLOWFISH_TWOFISH);

        $zipEntry->setExtractVersion(ZipVersion::v10_DEFAULT_MIN);
        static::assertSame($zipEntry->getExtractVersion(), ZipVersion::v10_DEFAULT_MIN);

        $renameDirEntry = $zipEntry->rename('new_directory/');
        static::assertSame($renameDirEntry->getExtractVersion(), ZipVersion::v20_DEFLATED_FOLDER_ZIPCRYPTO);

        $zipEntry->setCompressionMethod(ZipCompressionMethod::DEFLATED);
        static::assertSame($zipEntry->getExtractVersion(), ZipVersion::v20_DEFLATED_FOLDER_ZIPCRYPTO);

        if (\extension_loaded('bz2')) {
            $zipEntry->setExtractVersion(ZipVersion::v10_DEFAULT_MIN);
            $zipEntry->setCompressionMethod(ZipCompressionMethod::BZIP2);
            static::assertSame($zipEntry->getExtractVersion(), ZipVersion::v46_BZIP2);
        }

        $zipEntry->setExtractVersion(ZipVersion::v63_LZMA_PPMD_BLOWFISH_TWOFISH);
        $zipEntry->setCompressionMethod(ZipCompressionMethod::STORED);
        static::assertSame($zipEntry->getExtractVersion(), ZipVersion::v10_DEFAULT_MIN);

        $zipEntry->setExtractVersion(ZipVersion::v10_DEFAULT_MIN);
        $zipEntry->setPassword('12345', ZipEncryptionMethod::PKWARE);
        static::assertSame($zipEntry->getExtractVersion(), ZipVersion::v20_DEFLATED_FOLDER_ZIPCRYPTO);

        $zipEntry->setExtractVersion(ZipVersion::v10_DEFAULT_MIN);
        $zipEntry->setEncryptionMethod(ZipEncryptionMethod::WINZIP_AES_256);
        static::assertSame($zipEntry->getExtractVersion(), ZipVersion::v51_ENCR_AES_RC2_CORRECT);
    }

    public function testSoftwareVersion()
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getSoftwareVersion(), $zipEntry->getExtractVersion());

        $zipEntry->setExtractVersion(ZipVersion::v45_ZIP64_EXT);
        static::assertSame($zipEntry->getSoftwareVersion(), $zipEntry->getExtractVersion());

        $softwareVersion = 35;
        $zipEntry->setSoftwareVersion($softwareVersion);
        static::assertSame($softwareVersion, $zipEntry->getSoftwareVersion());
        static::assertSame($zipEntry->getExtractVersion(), ZipVersion::v45_ZIP64_EXT);

        $zipEntry->setExtractVersion(ZipVersion::v63_LZMA_PPMD_BLOWFISH_TWOFISH);
        static::assertNotSame($zipEntry->getSoftwareVersion(), $zipEntry->getExtractVersion());
        static::assertSame($softwareVersion, $zipEntry->getSoftwareVersion());
        static::assertSame($zipEntry->getExtractVersion(), ZipVersion::v63_LZMA_PPMD_BLOWFISH_TWOFISH);
    }

    public function testSize()
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getCompressedSize(), ZipEntry::UNKNOWN);
        static::assertSame($zipEntry->getUncompressedSize(), ZipEntry::UNKNOWN);

        $compressedSize = 100000;
        $uncompressedSize = 400000;

        $zipEntry->setCompressedSize($compressedSize);
        $zipEntry->setUncompressedSize($uncompressedSize);
        static::assertSame($zipEntry->getCompressedSize(), $compressedSize);
        static::assertSame($zipEntry->getUncompressedSize(), $uncompressedSize);

        $zipEntry->setCompressedSize(ZipEntry::UNKNOWN);
        $zipEntry->setUncompressedSize(ZipEntry::UNKNOWN);
        static::assertSame($zipEntry->getCompressedSize(), ZipEntry::UNKNOWN);
        static::assertSame($zipEntry->getUncompressedSize(), ZipEntry::UNKNOWN);
    }

    public function testInvalidCompressedSize()
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Compressed size < -1');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCompressedSize(-2);
    }

    public function testInvalidUncompressedSize()
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Uncompressed size < -1');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setUncompressedSize(-2);
    }

    public function testLocalHeaderOffset()
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getLocalHeaderOffset(), 0);

        $localHeaderOffset = 10000;
        $zipEntry->setLocalHeaderOffset($localHeaderOffset);
        static::assertSame($zipEntry->getLocalHeaderOffset(), $localHeaderOffset);

        $this->setExpectedException(InvalidArgumentException::class, 'Negative $localHeaderOffset');
        $zipEntry->setLocalHeaderOffset(-1);
    }

    public function testGeneralPurposeBitFlags()
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getGeneralPurposeBitFlags(), 0);
        static::assertFalse($zipEntry->isUtf8Flag());
        static::assertFalse($zipEntry->isEncrypted());
        static::assertFalse($zipEntry->isStrongEncryption());
        static::assertFalse($zipEntry->isDataDescriptorEnabled());

        $gpbf = GeneralPurposeBitFlag::DATA_DESCRIPTOR | GeneralPurposeBitFlag::UTF8;
        $zipEntry->setGeneralPurposeBitFlags($gpbf);
        static::assertSame($zipEntry->getGeneralPurposeBitFlags(), $gpbf);
        static::assertTrue($zipEntry->isDataDescriptorEnabled());
        static::assertTrue($zipEntry->isUtf8Flag());

        $zipEntry->setGeneralPurposeBitFlags(0);
        static::assertSame($zipEntry->getGeneralPurposeBitFlags(), 0);
        static::assertFalse($zipEntry->isUtf8Flag());
        static::assertFalse($zipEntry->isDataDescriptorEnabled());

        $zipEntry->enableUtf8Name(true);
        static::assertTrue($zipEntry->isUtf8Flag());
        static::assertSame(
            ($zipEntry->getGeneralPurposeBitFlags() & GeneralPurposeBitFlag::UTF8),
            GeneralPurposeBitFlag::UTF8
        );
        $zipEntry->enableUtf8Name(false);
        static::assertFalse($zipEntry->isUtf8Flag());
        static::assertSame(
            ($zipEntry->getGeneralPurposeBitFlags() & GeneralPurposeBitFlag::UTF8),
            0
        );

        $zipEntry->enableDataDescriptor(true);
        static::assertTrue($zipEntry->isDataDescriptorEnabled());
        static::assertSame(
            ($zipEntry->getGeneralPurposeBitFlags() & GeneralPurposeBitFlag::DATA_DESCRIPTOR),
            GeneralPurposeBitFlag::DATA_DESCRIPTOR
        );
        $zipEntry->enableDataDescriptor(false);
        static::assertFalse($zipEntry->isDataDescriptorEnabled());
        static::assertSame(
            ($zipEntry->getGeneralPurposeBitFlags() & GeneralPurposeBitFlag::DATA_DESCRIPTOR),
            0
        );
    }

    public function testEncryptionGPBF()
    {
        $zipEntry = new ZipEntry('entry');
        static::assertFalse($zipEntry->isEncrypted());

        $zipEntry->setGeneralPurposeBitFlags(GeneralPurposeBitFlag::ENCRYPTION);

        static::assertSame(
            ($zipEntry->getGeneralPurposeBitFlags() & GeneralPurposeBitFlag::ENCRYPTION),
            GeneralPurposeBitFlag::ENCRYPTION
        );
        static::assertTrue($zipEntry->isEncrypted());

        $zipEntry->disableEncryption();
        static::assertSame(
            ($zipEntry->getGeneralPurposeBitFlags() & GeneralPurposeBitFlag::ENCRYPTION),
            0
        );
        static::assertFalse($zipEntry->isEncrypted());

        // SIC! Strong encryption is not supported in ZipReader and ZipWriter
        static::assertFalse($zipEntry->isStrongEncryption());
        $zipEntry->setGeneralPurposeBitFlags(GeneralPurposeBitFlag::STRONG_ENCRYPTION);
        static::assertTrue($zipEntry->isStrongEncryption());
    }

    /**
     * @dataProvider provideInvalidGPBF
     *
     * @param int $gpbf
     */
    public function testInvalidGPBF($gpbf)
    {
        $this->setExpectedException(InvalidArgumentException::class, 'general purpose bit flags out of range');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setGeneralPurposeBitFlags($gpbf);
    }

    /**
     * @return array
     */
    public function provideInvalidGPBF()
    {
        return [
            [-1],
            [0x10000],
        ];
    }

    /**
     * @dataProvider provideCompressionLevelGPBF
     *
     * @param int  $compressionLevel
     * @param bool $bit1
     * @param bool $bit2
     *
     * @throws ZipUnsupportMethodException
     */
    public function testSetCompressionFlags($compressionLevel, $bit1, $bit2)
    {
        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCompressionMethod(ZipCompressionMethod::DEFLATED);

        $gpbf = ($bit1 ? GeneralPurposeBitFlag::COMPRESSION_FLAG1 : 0) |
            ($bit2 ? GeneralPurposeBitFlag::COMPRESSION_FLAG2 : 0);
        $zipEntry->setGeneralPurposeBitFlags($gpbf);
        static::assertSame($zipEntry->getCompressionLevel(), $compressionLevel);

        static::assertSame(
            (
                $zipEntry->getGeneralPurposeBitFlags() & GeneralPurposeBitFlag::COMPRESSION_FLAG1
            ) === GeneralPurposeBitFlag::COMPRESSION_FLAG1,
            $bit1,
            'Compression flag1 is not same'
        );
        static::assertSame(
            (
                $zipEntry->getGeneralPurposeBitFlags() & GeneralPurposeBitFlag::COMPRESSION_FLAG2
            ) === GeneralPurposeBitFlag::COMPRESSION_FLAG2,
            $bit2,
            'Compression flag2 is not same'
        );
    }

    /**
     * @return array
     */
    public function provideCompressionLevelGPBF()
    {
        return [
            [ZipCompressionLevel::SUPER_FAST, true, true],
            [ZipCompressionLevel::FAST, false, true],
            [ZipCompressionLevel::NORMAL, false, false],
            [ZipCompressionLevel::MAXIMUM, true, false],
        ];
    }

    /**
     * @dataProvider provideCompressionLevels
     *
     * @param int  $compressionLevel
     * @param bool $bit1
     * @param bool $bit2
     *
     * @throws ZipUnsupportMethodException
     */
    public function testSetCompressionLevel($compressionLevel, $bit1, $bit2)
    {
        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCompressionMethod(ZipCompressionMethod::DEFLATED);

        $zipEntry->setCompressionLevel($compressionLevel);
        static::assertSame($zipEntry->getCompressionLevel(), $compressionLevel);

        static::assertSame(
            (
                $zipEntry->getGeneralPurposeBitFlags() & GeneralPurposeBitFlag::COMPRESSION_FLAG1
            ) === GeneralPurposeBitFlag::COMPRESSION_FLAG1,
            $bit1,
            'Compression flag1 is not same'
        );
        static::assertSame(
            (
                $zipEntry->getGeneralPurposeBitFlags() & GeneralPurposeBitFlag::COMPRESSION_FLAG2
            ) === GeneralPurposeBitFlag::COMPRESSION_FLAG2,
            $bit2,
            'Compression flag2 is not same'
        );
    }

    /**
     * @return array
     */
    public function provideCompressionLevels()
    {
        return [
            [ZipCompressionLevel::SUPER_FAST, true, true],
            [ZipCompressionLevel::FAST, false, true],
            [3, false, false],
            [4, false, false],
            [ZipCompressionLevel::NORMAL, false, false],
            [6, false, false],
            [7, false, false],
            [8, false, false],
            [ZipCompressionLevel::MAXIMUM, true, false],
        ];
    }

    /**
     * @throws ZipException
     */
    public function testLegacyDefaultCompressionLevel()
    {
        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCompressionMethod(ZipCompressionMethod::DEFLATED);
        $zipEntry->setCompressionLevel(ZipCompressionLevel::MAXIMUM);
        static::assertSame($zipEntry->getCompressionLevel(), ZipCompressionLevel::MAXIMUM);

        $zipEntry->setCompressionLevel(ZipEntry::UNKNOWN);
        static::assertSame($zipEntry->getCompressionLevel(), ZipCompressionLevel::NORMAL);
    }

    /**
     * @dataProvider provideInvalidCompressionLevel
     *
     * @param int $compressionLevel
     *
     * @throws ZipException
     */
    public function testInvalidCompressionLevel($compressionLevel)
    {
        $this->setExpectedException(
            InvalidArgumentException::class,
            'Invalid compression level. Minimum level ' . ZipCompressionLevel::LEVEL_MIN .
            '. Maximum level ' . ZipCompressionLevel::LEVEL_MAX
        );

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCompressionMethod(ZipCompressionMethod::DEFLATED);
        $zipEntry->setCompressionLevel($compressionLevel);
    }

    /**
     * @return array
     */
    public function provideInvalidCompressionLevel()
    {
        return [
            [0],
            [-2],
            [10],
            [100],
        ];
    }

    /**
     * @dataProvider provideDosTime
     *
     * @param int $dosTime
     * @param int $timestamp
     */
    public function testDosTime($dosTime, $timestamp)
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getDosTime(), ZipEntry::UNKNOWN);

        $zipEntry->setDosTime($dosTime);
        static::assertSame($zipEntry->getDosTime(), $dosTime);
        static::assertSame($zipEntry->getTime(), $timestamp);

        $zipEntry->setTime($timestamp);
        static::assertSame($zipEntry->getTime(), $timestamp);
        static::assertSame($zipEntry->getDosTime(), $dosTime);
    }

    /**
     * @return array
     */
    public function provideDosTime()
    {
        return [
            [0, 312757200],
            [1043487716, 1295339468],
            [1177556759, 1421366206],
            [1282576076, 1521384864],
        ];
    }

    /**
     * @dataProvider provideInvalidDosTime
     *
     * @param int $dosTime
     */
    public function testInvalidDosTime($dosTime)
    {
        $this->setExpectedException(InvalidArgumentException::class, 'DosTime out of range');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setDosTime($dosTime);
    }

    /**
     * @return array
     */
    public function provideInvalidDosTime()
    {
        return [
            [-1],
            [0xffffffff + 1],
        ];
    }

    public function testSetTime()
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getDosTime(), ZipEntry::UNKNOWN);
        $zipEntry->setTime(ZipEntry::UNKNOWN);
        static::assertSame($zipEntry->getDosTime(), 0);

        $zipEntry->setTime(0);
        static::assertSame($zipEntry->getDosTime(), 0);
    }

    /**
     * @dataProvider provideExternalAttributes
     *
     * @param string   $entryName
     * @param int      $expectedExternalAttr
     * @param int      $createdOS
     * @param int      $extractedOS
     * @param int|null $externalAttr
     * @param int      $unixMode
     *
     * @noinspection PhpTooManyParametersInspection
     */
    public function testExternalAttributes(
        $entryName,
        $expectedExternalAttr,
        $createdOS,
        $extractedOS,
        $externalAttr,
        $unixMode
    ) {
        $zipEntry = new ZipEntry($entryName);
        static::assertSame($zipEntry->getExternalAttributes(), $expectedExternalAttr);
        $zipEntry
            ->setCreatedOS($createdOS)
            ->setExtractedOS($extractedOS)
        ;

        if ($externalAttr !== null) {
            $zipEntry->setExternalAttributes($externalAttr);
            static::assertSame($zipEntry->getExternalAttributes(), $externalAttr);
        }

        static::assertSame($zipEntry->getUnixMode(), $unixMode);
    }

    /**
     * @return array
     */
    public function provideExternalAttributes()
    {
        return [
            [
                'entry.txt',
                DosAttrs::DOS_ARCHIVE,
                ZipPlatform::OS_UNIX,
                ZipPlatform::OS_UNIX,
                (010644 << 16) | DosAttrs::DOS_ARCHIVE,
                010644,
            ],
            [
                'dir/',
                DosAttrs::DOS_DIRECTORY,
                ZipPlatform::OS_UNIX,
                ZipPlatform::OS_UNIX,
                (040755 << 16) | DosAttrs::DOS_DIRECTORY,
                040755,
            ],
            [
                'entry.txt',
                DosAttrs::DOS_ARCHIVE,
                ZipPlatform::OS_DOS,
                ZipPlatform::OS_DOS,
                null,
                0100644,
            ],
            [
                'entry.txt',
                DosAttrs::DOS_ARCHIVE,
                ZipPlatform::OS_DOS,
                ZipPlatform::OS_UNIX,
                null,
                0100644,
            ],
            [
                'entry.txt',
                DosAttrs::DOS_ARCHIVE,
                ZipPlatform::OS_UNIX,
                ZipPlatform::OS_DOS,
                null,
                0100644,
            ],
            [
                'dir/',
                DosAttrs::DOS_DIRECTORY,
                ZipPlatform::OS_DOS,
                ZipPlatform::OS_DOS,
                null,
                040755,
            ],
            [
                'dir/',
                DosAttrs::DOS_DIRECTORY,
                ZipPlatform::OS_DOS,
                ZipPlatform::OS_UNIX,
                null,
                040755,
            ],
            [
                'dir/',
                DosAttrs::DOS_DIRECTORY,
                ZipPlatform::OS_UNIX,
                ZipPlatform::OS_DOS,
                null,
                040755,
            ],
            [
                'entry.txt',
                DosAttrs::DOS_ARCHIVE,
                ZipPlatform::OS_UNIX,
                ZipPlatform::OS_UNIX,
                0777 << 16,
                0777,
            ],
        ];
    }

    /**
     * @dataProvider provideInvalidExternalAttributes
     *
     * @param int $externalAttributes
     */
    public function testInvalidExternalAttributes($externalAttributes)
    {
        $this->setExpectedException(InvalidArgumentException::class, 'external attributes out of range');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setExternalAttributes($externalAttributes);
    }

    /**
     * @return array
     */
    public function provideInvalidExternalAttributes()
    {
        return [
            [-1],
            [0xffffffff + 1],
        ];
    }

    public function testInternalAttributes()
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getInternalAttributes(), 0);

        $zipEntry->setInternalAttributes(1);
        static::assertSame($zipEntry->getInternalAttributes(), 1);
    }

    /**
     * @dataProvider provideInvalidInternalAttributes
     *
     * @param int $internalAttributes
     */
    public function testInvalidInternalAttributes($internalAttributes)
    {
        $this->setExpectedException(InvalidArgumentException::class, 'internal attributes out of range');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setInternalAttributes($internalAttributes);
    }

    /**
     * @return array
     */
    public function provideInvalidInternalAttributes()
    {
        return [
            [-1],
            [0xffff + 1],
        ];
    }

    public function testExtraFields()
    {
        $zipEntry = new ZipEntry('entry');

        static::assertInstanceOf(ExtraFieldsCollection::class, $zipEntry->getCdExtraFields());
        static::assertInstanceOf(ExtraFieldsCollection::class, $zipEntry->getLocalExtraFields());
        static::assertCount(0, $zipEntry->getCdExtraFields());
        static::assertCount(0, $zipEntry->getLocalExtraFields());

        $extraNtfs = new NtfsExtraField(time(), time() - 10000, time() - 100000);
        $extraAsi = new AsiExtraField(010644);
        $extraJar = new JarMarkerExtraField();

        $zipEntry->getLocalExtraFields()->add($extraNtfs);
        $zipEntry->getCdExtraFields()->add($extraNtfs);
        static::assertCount(1, $zipEntry->getCdExtraFields());
        static::assertCount(1, $zipEntry->getLocalExtraFields());

        $zipEntry->addExtraField($extraAsi);
        static::assertCount(2, $zipEntry->getCdExtraFields());
        static::assertCount(2, $zipEntry->getLocalExtraFields());

        $zipEntry->addCdExtraField($extraJar);
        static::assertCount(3, $zipEntry->getCdExtraFields());
        static::assertCount(2, $zipEntry->getLocalExtraFields());

        static::assertSame($zipEntry->getCdExtraField(JarMarkerExtraField::HEADER_ID), $extraJar);
        static::assertNull($zipEntry->getLocalExtraField(JarMarkerExtraField::HEADER_ID));
        static::assertSame($zipEntry->getLocalExtraField(AsiExtraField::HEADER_ID), $extraAsi);

        static::assertSame(
            [$extraNtfs, $extraAsi, $extraJar],
            array_values($zipEntry->getCdExtraFields()->getAll())
        );
        static::assertSame(
            [$extraNtfs, $extraAsi],
            array_values($zipEntry->getLocalExtraFields()->getAll())
        );

        $zipEntry->removeExtraField(AsiExtraField::HEADER_ID);
        static::assertNull($zipEntry->getCdExtraField(AsiExtraField::HEADER_ID));
        static::assertNull($zipEntry->getLocalExtraField(AsiExtraField::HEADER_ID));

        static::assertCount(2, $zipEntry->getCdExtraFields());
        static::assertCount(1, $zipEntry->getLocalExtraFields());
        static::assertSame(
            [$extraNtfs, $extraJar],
            array_values($zipEntry->getCdExtraFields()->getAll())
        );
        static::assertSame(
            [$extraNtfs],
            array_values($zipEntry->getLocalExtraFields()->getAll())
        );

        static::assertTrue($zipEntry->hasExtraField(NtfsExtraField::HEADER_ID));
        static::assertTrue($zipEntry->hasExtraField(JarMarkerExtraField::HEADER_ID));
        static::assertFalse($zipEntry->hasExtraField(AsiExtraField::HEADER_ID));
    }

    public function testComment()
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getComment(), '');
        $zipEntry->setComment('comment');
        static::assertSame($zipEntry->getComment(), 'comment');
        $zipEntry->setComment(null);
        static::assertSame($zipEntry->getComment(), '');
        static::assertFalse($zipEntry->isUtf8Flag());
        $zipEntry->setComment('комментарий');
        static::assertTrue($zipEntry->isUtf8Flag());
        static::assertSame($zipEntry->getComment(), 'комментарий');
    }

    /**
     * @throws \Exception
     */
    public function testLongComment()
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Comment too long');

        $longComment = random_bytes(0xffff + 1);
        $zipEntry = new ZipEntry('entry');
        $zipEntry->setComment($longComment);
    }

    /**
     * @dataProvider provideDataDescriptorRequired
     *
     * @param int  $crc
     * @param int  $compressedSize
     * @param int  $uncompressedSize
     * @param bool $required
     */
    public function testDataDescriptorRequired($crc, $compressedSize, $uncompressedSize, $required)
    {
        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCrc($crc);
        $zipEntry->setCompressedSize($compressedSize);
        $zipEntry->setUncompressedSize($uncompressedSize);

        static::assertSame($zipEntry->isDataDescriptorRequired(), $required);
        static::assertSame($zipEntry->getCrc(), $crc);
        static::assertSame($zipEntry->getCompressedSize(), $compressedSize);
        static::assertSame($zipEntry->getUncompressedSize(), $uncompressedSize);
    }

    /**
     * @return array
     */
    public function provideDataDescriptorRequired()
    {
        return [
            [ZipEntry::UNKNOWN, ZipEntry::UNKNOWN, ZipEntry::UNKNOWN, true],
            [0xF33F33, ZipEntry::UNKNOWN, ZipEntry::UNKNOWN, true],
            [0xF33F33, 11111111, ZipEntry::UNKNOWN, true],
            [0xF33F33, ZipEntry::UNKNOWN, 22333333, true],
            [ZipEntry::UNKNOWN, 11111111, ZipEntry::UNKNOWN, true],
            [ZipEntry::UNKNOWN, 11111111, 22333333, true],
            [ZipEntry::UNKNOWN, ZipEntry::UNKNOWN, 22333333, true],
            [0xF33F33, 11111111, 22333333, false],
        ];
    }

    /**
     * @dataProvider provideEncryption
     *
     * @param string|null $password
     * @param int|null    $encryptionMethod
     * @param bool        $encrypted
     * @param int         $expectedEncryptionMethod
     */
    public function testEncryption($password, $encryptionMethod, $encrypted, $expectedEncryptionMethod)
    {
        $zipEntry = new ZipEntry('entry');
        $zipEntry->setPassword($password, $encryptionMethod);

        static::assertSame($zipEntry->isEncrypted(), $encrypted);
        static::assertSame($zipEntry->getPassword(), $password);
        static::assertSame($zipEntry->getEncryptionMethod(), $expectedEncryptionMethod);

        $zipEntry->setPassword($password, null);
        static::assertSame($zipEntry->getEncryptionMethod(), $expectedEncryptionMethod);
    }

    /**
     * @return array
     */
    public function provideEncryption()
    {
        return [
            [null, null, false, ZipEncryptionMethod::NONE],
            [null, ZipEncryptionMethod::WINZIP_AES_256, false, ZipEncryptionMethod::NONE],
            ['12345', null, true, ZipEncryptionMethod::WINZIP_AES_256],
            ['12345', ZipEncryptionMethod::PKWARE, true, ZipEncryptionMethod::PKWARE],
            ['12345', ZipEncryptionMethod::WINZIP_AES_256, true, ZipEncryptionMethod::WINZIP_AES_256],
            ['12345', ZipEncryptionMethod::WINZIP_AES_128, true, ZipEncryptionMethod::WINZIP_AES_128],
            ['12345', ZipEncryptionMethod::WINZIP_AES_192, true, ZipEncryptionMethod::WINZIP_AES_192],
        ];
    }

    public function testDirectoryEncryption()
    {
        $zipEntry = new ZipEntry('directory/');
        $zipEntry->setPassword('12345', ZipEncryptionMethod::WINZIP_AES_256);
        static::assertTrue($zipEntry->isDirectory());
        static::assertNull($zipEntry->getPassword());
        static::assertFalse($zipEntry->isEncrypted());
        static::assertSame($zipEntry->getEncryptionMethod(), ZipEncryptionMethod::NONE);
    }

    /**
     * @dataProvider provideEncryptionMethod
     *
     * @param int|null $encryptionMethod
     * @param int      $expectedEncryptionMethod
     * @param bool     $encrypted
     * @param int      $extractVersion
     */
    public function testEncryptionMethod(
        $encryptionMethod,
        $expectedEncryptionMethod,
        $encrypted,
        $extractVersion
    ) {
        $zipEntry = new ZipEntry('entry');
        $zipEntry->setEncryptionMethod($encryptionMethod);
        static::assertSame($zipEntry->isEncrypted(), $encrypted);
        static::assertSame($zipEntry->getEncryptionMethod(), $expectedEncryptionMethod);
        static::assertSame($zipEntry->getExtractVersion(), $extractVersion);
    }

    /**
     * @return array
     */
    public function provideEncryptionMethod()
    {
        return [
            [
                null,
                ZipEncryptionMethod::NONE,
                false,
                ZipVersion::v10_DEFAULT_MIN,
            ],
            [
                ZipEncryptionMethod::NONE,
                ZipEncryptionMethod::NONE,
                false,
                ZipVersion::v10_DEFAULT_MIN,
            ],
            [
                ZipEncryptionMethod::PKWARE,
                ZipEncryptionMethod::PKWARE,
                true,
                ZipVersion::v20_DEFLATED_FOLDER_ZIPCRYPTO,
            ],
            [
                ZipEncryptionMethod::WINZIP_AES_256,
                ZipEncryptionMethod::WINZIP_AES_256,
                true,
                ZipVersion::v51_ENCR_AES_RC2_CORRECT,
            ],
            [
                ZipEncryptionMethod::WINZIP_AES_192,
                ZipEncryptionMethod::WINZIP_AES_192,
                true,
                ZipVersion::v51_ENCR_AES_RC2_CORRECT,
            ],
            [
                ZipEncryptionMethod::WINZIP_AES_128,
                ZipEncryptionMethod::WINZIP_AES_128,
                true,
                ZipVersion::v51_ENCR_AES_RC2_CORRECT,
            ],
        ];
    }

    /**
     * @dataProvider provideInvalidEncryptionMethod
     *
     * @param int $encryptionMethod
     */
    public function testInvalidEncryptionMethod($encryptionMethod)
    {
        $this->setExpectedException(
            InvalidArgumentException::class,
            'Encryption method ' . $encryptionMethod . ' is not supported.'
        );

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setEncryptionMethod($encryptionMethod);
    }

    /**
     * @return array
     */
    public function provideInvalidEncryptionMethod()
    {
        return [
            [-2],
            [4],
            [5],
        ];
    }

    /**
     * @dataProvider provideUnixMode
     *
     * @param string $entryName
     * @param int    $unixMode
     */
    public function testUnixMode($entryName, $unixMode)
    {
        $zipEntry = new ZipEntry($entryName);
        $zipEntry->setUnixMode($unixMode);

        static::assertSame($zipEntry->getUnixMode(), $unixMode);
        static::assertSame($zipEntry->getCreatedOS(), ZipPlatform::OS_UNIX);
    }

    /**
     * @return array
     */
    public function provideUnixMode()
    {
        return [
            ['entry.txt', 0700], // read, write, & execute only for owner
            ['entry.txt', 0770], // read, write, & execute for owner and group
            ['entry.txt', 0777], // read, write, & execute for owner, group and others
            ['entry.txt', 0111], // execute
            ['entry.txt', 0222], // write
            ['entry.txt', 0333], // write & execute
            ['entry.txt', 0444], // read
            ['entry.txt', 0555], // read & execute
            ['entry.txt', 0666], // read & write
            ['entry.txt', 0740], // owner can read, write, & execute; group can only read; others have no permissions
            ['entry.txt', 0777], // owner can read, write, & execute
            ['directory/', 040700], // directory, read, write, & execute only for owner
            ['directory/', 040770], // directory, read, write, & execute for owner and group
            ['directory/', 040777], // directory, read, write, & execute
        ];
    }

    /**
     * @dataProvider provideUnixMode
     * @dataProvider provideSymlink
     *
     * @param      $entryName
     * @param      $unixMode
     * @param bool $symlink
     */
    public function testSymlink($entryName, $unixMode, $symlink = false)
    {
        $zipEntry = new ZipEntry($entryName);
        $zipEntry->setUnixMode($unixMode);
        static::assertSame($zipEntry->isUnixSymlink(), $symlink);
    }

    public function testAsiUnixMode()
    {
        $unixMode = 0100666;
        $asiUnixMode = 0100600;

        $asiExtraField = new AsiExtraField($asiUnixMode);

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCreatedOS(ZipPlatform::OS_DOS);
        $zipEntry->setExtractedOS(ZipPlatform::OS_DOS);
        $zipEntry->setExternalAttributes(DosAttrs::DOS_ARCHIVE);
        $zipEntry->addExtraField($asiExtraField);

        static::assertSame($zipEntry->getUnixMode(), $asiUnixMode);

        $zipEntry->setUnixMode($unixMode);
        static::assertSame($zipEntry->getCreatedOS(), ZipPlatform::OS_UNIX);
        static::assertSame($zipEntry->getUnixMode(), $unixMode);
    }

    /**
     * @return array
     */
    public function provideSymlink()
    {
        return [
            ['entry', 0120644, true],
            ['dir/', 0120755, true],
        ];
    }

    /**
     * @dataProvider provideIsZip64ExtensionsRequired
     *
     * @param int  $compressionSize
     * @param int  $uncompressionSize
     * @param bool $required
     */
    public function testIsZip64ExtensionsRequired($compressionSize, $uncompressionSize, $required)
    {
        if (\PHP_INT_SIZE === 4) {
            static::markTestSkipped('only php 64-bit');

            return;
        }

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCompressedSize($compressionSize);
        $zipEntry->setUncompressedSize($uncompressionSize);
        static::assertSame($zipEntry->isZip64ExtensionsRequired(), $required);
    }

    /**
     * @return array
     */
    public function provideIsZip64ExtensionsRequired()
    {
        return [
            [11111111, 22222222, false],
            [ZipEntry::UNKNOWN, ZipEntry::UNKNOWN, false],
            [ZipEntry::UNKNOWN, ZipConstants::ZIP64_MAGIC + 1, true],
            [ZipConstants::ZIP64_MAGIC + 1, ZipEntry::UNKNOWN, true],
            [ZipConstants::ZIP64_MAGIC + 1, ZipConstants::ZIP64_MAGIC + 1, true],
            [ZipConstants::ZIP64_MAGIC, ZipConstants::ZIP64_MAGIC, false],
            [ZipConstants::ZIP64_MAGIC, ZipEntry::UNKNOWN, false],
            [ZipEntry::UNKNOWN, ZipConstants::ZIP64_MAGIC, false],
        ];
    }

    /**
     * @dataProvider provideExtraTime
     *
     * @param ExtraFieldsCollection   $extraFieldsCollection
     * @param \DateTimeInterface      $mtime
     * @param \DateTimeInterface|null $atime
     * @param \DateTimeInterface|null $ctime
     */
    public function testMTimeATimeCTime(ExtraFieldsCollection $extraFieldsCollection, $mtime, $atime, $ctime)
    {
        $unixTimestamp = time();

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setTime($unixTimestamp);

        // converting from unixtime to dos may occur with a small margin of error
        static::assertLessThanOrEqual($zipEntry->getMTime()->getTimestamp() + 1, $unixTimestamp);
        static::assertGreaterThanOrEqual($zipEntry->getMTime()->getTimestamp() - 1, $unixTimestamp);

        static::assertNull($zipEntry->getATime());
        static::assertNull($zipEntry->getCTime());

        $zipEntry->getCdExtraFields()->addCollection($extraFieldsCollection);
        $zipEntry->getLocalExtraFields()->addCollection($extraFieldsCollection);

        static::assertNotNull($zipEntry->getMTime());

        static::assertSame($zipEntry->getMTime()->getTimestamp(), $mtime->getTimestamp());

        if ($atime !== null) {
            static::assertSame($zipEntry->getATime()->getTimestamp(), $atime->getTimestamp());
        } else {
            static::assertNull($zipEntry->getATime());
        }

        if ($ctime !== null) {
            static::assertSame($zipEntry->getCTime()->getTimestamp(), $ctime->getTimestamp());
        } else {
            static::assertNull($zipEntry->getCTime());
        }
    }

    /**
     * @throws \Exception
     *
     * @return array
     */
    public function provideExtraTime()
    {
        $ntfsExtra = NtfsExtraField::create(
            new \DateTimeImmutable('-1 week'),
            new \DateTimeImmutable('-1 month'),
            new \DateTimeImmutable('-1 year')
        );

        $extendedTimestampExtraField = ExtendedTimestampExtraField::create(
            strtotime('-2 weeks'),
            strtotime('-2 months'),
            strtotime('-2 years')
        );

        $oldUnixExtraField = new OldUnixExtraField(
            strtotime('-3 weeks'),
            strtotime('-3 months'),
            1000,
            1000
        );

        $ntfsTimeCollection = new ExtraFieldsCollection();
        $ntfsTimeCollection->add($ntfsExtra);

        $extendedTimestampCollection = new ExtraFieldsCollection();
        $extendedTimestampCollection->add($extendedTimestampExtraField);

        $oldUnixExtraFieldCollection = new ExtraFieldsCollection();
        $oldUnixExtraFieldCollection->add($oldUnixExtraField);

        $oldExtendedCollection = clone $oldUnixExtraFieldCollection;
        $oldExtendedCollection->add($extendedTimestampExtraField);

        $fullCollection = clone $oldExtendedCollection;
        $fullCollection->add($ntfsExtra);

        return [
            [
                $ntfsTimeCollection,
                $ntfsExtra->getModifyDateTime(),
                $ntfsExtra->getAccessDateTime(),
                $ntfsExtra->getCreateDateTime(),
            ],
            [
                $extendedTimestampCollection,
                $extendedTimestampExtraField->getModifyDateTime(),
                $extendedTimestampExtraField->getAccessDateTime(),
                $extendedTimestampExtraField->getCreateDateTime(),
            ],
            [
                $oldUnixExtraFieldCollection,
                $oldUnixExtraField->getModifyDateTime(),
                $oldUnixExtraField->getAccessDateTime(),
                null,
            ],
            [
                $oldExtendedCollection,
                $extendedTimestampExtraField->getModifyDateTime(),
                $extendedTimestampExtraField->getAccessDateTime(),
                $extendedTimestampExtraField->getCreateDateTime(),
            ],
            [
                $fullCollection,
                $ntfsExtra->getModifyDateTime(),
                $ntfsExtra->getAccessDateTime(),
                $ntfsExtra->getCreateDateTime(),
            ],
        ];
    }

    /**
     * @throws ZipException
     */
    public function testClone()
    {
        $newUnixExtra = new NewUnixExtraField();
        $zipData = new ZipFileData(new \SplFileInfo(__FILE__));

        $zipEntry = new ZipEntry('entry');
        $zipEntry->addExtraField($newUnixExtra);
        $zipEntry->setData($zipData);

        $cloneEntry = clone $zipEntry;

        static::assertNotSame($cloneEntry, $zipEntry);
        static::assertNotSame($cloneEntry->getCdExtraFields(), $zipEntry->getCdExtraFields());
        static::assertNotSame($cloneEntry->getLocalExtraFields(), $zipEntry->getLocalExtraFields());
        static::assertNotSame($cloneEntry->getCdExtraField(NewUnixExtraField::HEADER_ID), $newUnixExtra);
        static::assertNotSame($cloneEntry->getLocalExtraField(NewUnixExtraField::HEADER_ID), $newUnixExtra);
        static::assertNotSame($cloneEntry->getData(), $zipData);
    }

    public function testExtraCollection()
    {
        $zipEntry = new ZipEntry('entry');
        $cdCollection = $zipEntry->getCdExtraFields();
        $localCollection = $zipEntry->getLocalExtraFields();

        static::assertNotSame($cdCollection, $localCollection);

        $anotherCollection = new ExtraFieldsCollection();
        $anotherCollection->add(new JarMarkerExtraField());
        $anotherCollection->add(new AsiExtraField(0100777, 1000, 1000));

        $zipEntry->setCdExtraFields($anotherCollection);
        static::assertSame($anotherCollection, $zipEntry->getCdExtraFields());
        static::assertSame($localCollection, $zipEntry->getLocalExtraFields());

        $zipEntry->setLocalExtraFields($anotherCollection);
        static::assertSame($anotherCollection, $zipEntry->getLocalExtraFields());
        static::assertSame($zipEntry->getCdExtraFields(), $zipEntry->getLocalExtraFields());

        $newUnixExtraField = new NewUnixExtraField(1, 1000, 1000);
        $zipEntry->getCdExtraFields()->add($newUnixExtraField);

        static::assertSame($zipEntry->getCdExtraField(NewUnixExtraField::HEADER_ID), $newUnixExtraField);
        static::assertSame($zipEntry->getLocalExtraField(NewUnixExtraField::HEADER_ID), $newUnixExtraField);
    }
}
