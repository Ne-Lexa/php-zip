<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    public function testEntry(): void
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
     */
    public function testEmptyName(string $entryName, string $exceptionMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($exceptionMessage);

        new ZipEntry($entryName);
    }

    public function provideEmptyName(): array
    {
        return [
            ['', 'Empty zip entry name'],
            ['/', 'Empty zip entry name'],
        ];
    }

    /**
     * @dataProvider provideEntryName
     */
    public function testEntryName(string $entryName, string $actualEntryName, bool $directory): void
    {
        $entry = new ZipEntry($entryName);
        static::assertSame($entry->getName(), $actualEntryName);
        static::assertSame($entry->isDirectory(), $directory);
    }

    public function provideEntryName(): array
    {
        return [
            ['0', '0', false],
            ['directory/', 'directory/', true],
        ];
    }

    /**
     * @dataProvider provideCompressionMethod
     *
     * @throws ZipUnsupportMethodException
     */
    public function testCompressionMethod(int $compressionMethod): void
    {
        $entry = new ZipEntry('entry');
        static::assertSame($entry->getCompressionMethod(), ZipEntry::UNKNOWN);

        $entry->setCompressionMethod($compressionMethod);
        static::assertSame($entry->getCompressionMethod(), $compressionMethod);
    }

    public function provideCompressionMethod(): array
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
     * @throws ZipUnsupportMethodException
     */
    public function testOutOfRangeCompressionMethod(int $compressionMethod): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('method out of range: ' . $compressionMethod);

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCompressionMethod($compressionMethod);
    }

    public function provideOutOfRangeCompressionMethod(): array
    {
        return [
            [-1],
            [0x44444],
        ];
    }

    /**
     * @dataProvider provideUnsupportCompressionMethod
     *
     * @throws ZipUnsupportMethodException
     */
    public function testUnsupportCompressionMethod(int $compressionMethod, string $exceptionMessage): void
    {
        $this->expectException(ZipUnsupportMethodException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCompressionMethod($compressionMethod);
    }

    public function provideUnsupportCompressionMethod(): array
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

    public function testCharset(): void
    {
        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCharset(DosCodePage::CP_CYRILLIC_RUSSIAN);
        static::assertSame($zipEntry->getCharset(), DosCodePage::CP_CYRILLIC_RUSSIAN);

        $zipEntry->setCharset(/* null */);
        static::assertNull($zipEntry->getCharset());
    }

    public function testEmptyCharset(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty charset');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCharset('');
    }

    public function testRenameAndDeleteUnicodePath(): void
    {
        $entryName = 'файл.txt';
        $charset = DosCodePage::CP_CYRILLIC_RUSSIAN;
        $dosEntryName = DosCodePage::fromUTF8($entryName, $charset);
        static::assertSame(DosCodePage::toUTF8($dosEntryName, $charset), $entryName);

        $unicodePathExtraField = new UnicodePathExtraField(crc32($dosEntryName), $entryName);

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

    public function testData(): void
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
     * @throws \Exception
     */
    public function testZipNewDataGuardClone(): void
    {
        $resource = fopen('php://temp', 'r+b');
        static::assertNotFalse($resource);
        fwrite($resource, random_bytes(1024));
        rewind($resource);

        $zipEntry = new ZipEntry('entry');
        $zipEntry2 = new ZipEntry('entry2');

        $zipData = new ZipNewData($zipEntry, $resource);
        $zipData2 = new ZipNewData($zipEntry2, $resource);
        $cloneData = clone $zipData;
        $cloneData2 = clone $cloneData;

        static::assertSame($zipData->getDataAsStream(), $resource);
        static::assertSame($zipData2->getDataAsStream(), $resource);
        static::assertSame($cloneData->getDataAsStream(), $resource);
        static::assertSame($cloneData2->getDataAsStream(), $resource);

        $validResource = \is_resource($resource);
        static::assertTrue($validResource);

        unset($cloneData);
        $validResource = \is_resource($resource);
        static::assertTrue($validResource);

        unset($zipData);
        $validResource = \is_resource($resource);
        static::assertTrue($validResource);

        unset($zipData2);
        $validResource = \is_resource($resource);
        static::assertTrue($validResource);

        $reflectionClass = new \ReflectionClass($cloneData2);
        static::assertSame(
            $reflectionClass->getStaticProperties()['guardClonedStream'][(int) $resource],
            0
        );

        unset($cloneData2);
        $validResource = \is_resource($resource);
        static::assertFalse($validResource);
    }

    /**
     * @dataProvider providePlatform
     */
    public function testCreatedOS(int $zipOS): void
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getCreatedOS(), ZipEntry::UNKNOWN);
        $zipEntry->setCreatedOS($zipOS);
        static::assertSame($zipEntry->getCreatedOS(), $zipOS);
    }

    public function providePlatform(): array
    {
        return [
            [ZipPlatform::OS_DOS],
            [ZipPlatform::OS_UNIX],
            [ZipPlatform::OS_MAC_OSX],
        ];
    }

    /**
     * @dataProvider providePlatform
     */
    public function testExtractedOS(int $zipOS): void
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getExtractedOS(), ZipEntry::UNKNOWN);
        $zipEntry->setExtractedOS($zipOS);
        static::assertSame($zipEntry->getExtractedOS(), $zipOS);
    }

    /**
     * @dataProvider provideInvalidPlatform
     */
    public function testInvalidCreatedOs(int $zipOS): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Platform out of range');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCreatedOS($zipOS);
    }

    public function provideInvalidPlatform(): array
    {
        return [
            [-1],
            [0xff + 1],
        ];
    }

    /**
     * @dataProvider provideInvalidPlatform
     */
    public function testInvalidExtractedOs(int $zipOS): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Platform out of range');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setExtractedOS($zipOS);
    }

    /**
     * @throws ZipException
     */
    public function testAutoExtractVersion(): void
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
    public function testExtractVersion(): void
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

    public function testSoftwareVersion(): void
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

    public function testSize(): void
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

    public function testInvalidCompressedSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Compressed size < -1');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCompressedSize(-2);
    }

    public function testInvalidUncompressedSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Uncompressed size < -1');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setUncompressedSize(-2);
    }

    public function testLocalHeaderOffset(): void
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getLocalHeaderOffset(), 0);

        $localHeaderOffset = 10000;
        $zipEntry->setLocalHeaderOffset($localHeaderOffset);
        static::assertSame($zipEntry->getLocalHeaderOffset(), $localHeaderOffset);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Negative $localHeaderOffset');
        $zipEntry->setLocalHeaderOffset(-1);
    }

    public function testGeneralPurposeBitFlags(): void
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

    public function testEncryptionGPBF(): void
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
     */
    public function testInvalidGPBF(int $gpbf): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('general purpose bit flags out of range');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setGeneralPurposeBitFlags($gpbf);
    }

    public function provideInvalidGPBF(): array
    {
        return [
            [-1],
            [0x10000],
        ];
    }

    /**
     * @dataProvider provideCompressionLevelGPBF
     *
     * @throws ZipUnsupportMethodException
     */
    public function testSetCompressionFlags(int $compressionLevel, bool $bit1, bool $bit2): void
    {
        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCompressionMethod(ZipCompressionMethod::DEFLATED);

        $gpbf = ($bit1 ? GeneralPurposeBitFlag::COMPRESSION_FLAG1 : 0)
            | ($bit2 ? GeneralPurposeBitFlag::COMPRESSION_FLAG2 : 0);
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

    public function provideCompressionLevelGPBF(): array
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
     * @throws ZipUnsupportMethodException
     */
    public function testSetCompressionLevel(int $compressionLevel, bool $bit1, bool $bit2): void
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

    public function provideCompressionLevels(): array
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
    public function testLegacyDefaultCompressionLevel(): void
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
     * @throws ZipException
     */
    public function testInvalidCompressionLevel(int $compressionLevel): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid compression level. Minimum level %s. Maximum level %s',
                ZipCompressionLevel::LEVEL_MIN,
                ZipCompressionLevel::LEVEL_MAX
            )
        );

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCompressionMethod(ZipCompressionMethod::DEFLATED);
        $zipEntry->setCompressionLevel($compressionLevel);
    }

    public function provideInvalidCompressionLevel(): array
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
     */
    public function testDosTime(int $dosTime): void
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getDosTime(), ZipEntry::UNKNOWN);

        $zipEntry->setDosTime($dosTime);
        static::assertSame($zipEntry->getDosTime(), $dosTime);
    }

    public function provideDosTime(): array
    {
        return [
            [0],
            [1043487716],
            [1177556759],
            [1282576076],
        ];
    }

    /**
     * @dataProvider provideInvalidDosTime
     */
    public function testInvalidDosTime(int $dosTime): void
    {
        if (\PHP_INT_SIZE === 4) {
            static::markTestSkipped('only 64 bit test');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DosTime out of range');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setDosTime($dosTime);
    }

    public function provideInvalidDosTime(): array
    {
        return [
            [-1],
            [0xffffffff + 1],
        ];
    }

    public function testSetTime(): void
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
     * @noinspection PhpTooManyParametersInspection
     *
     * @param ?int $externalAttr
     */
    public function testExternalAttributes(
        string $entryName,
        int $expectedExternalAttr,
        int $createdOS,
        int $extractedOS,
        ?int $externalAttr,
        int $unixMode
    ): void {
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

    public function provideExternalAttributes(): array
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
     */
    public function testInvalidExternalAttributes(int $externalAttributes): void
    {
        if (\PHP_INT_SIZE === 4) {
            static::markTestSkipped('only 64 bit test');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('external attributes out of range');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setExternalAttributes($externalAttributes);
    }

    public function provideInvalidExternalAttributes(): array
    {
        return [
            [-1],
            [0xffffffff + 1],
        ];
    }

    public function testInternalAttributes(): void
    {
        $zipEntry = new ZipEntry('entry');
        static::assertSame($zipEntry->getInternalAttributes(), 0);

        $zipEntry->setInternalAttributes(1);
        static::assertSame($zipEntry->getInternalAttributes(), 1);
    }

    /**
     * @dataProvider provideInvalidInternalAttributes
     */
    public function testInvalidInternalAttributes(int $internalAttributes): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('internal attributes out of range');

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setInternalAttributes($internalAttributes);
    }

    public function provideInvalidInternalAttributes(): array
    {
        return [
            [-1],
            [0xffff + 1],
        ];
    }

    public function testExtraFields(): void
    {
        $zipEntry = new ZipEntry('entry');

        $extraCdFields = $zipEntry->getCdExtraFields();
        $extraLocalFields = $zipEntry->getLocalExtraFields();

        static::assertCount(0, $extraCdFields);
        static::assertCount(0, $extraLocalFields);

        $extraNtfs = new NtfsExtraField(time(), time() - 10000, time() - 100000);
        $extraAsi = new AsiExtraField(010644);
        $extraJar = new JarMarkerExtraField();

        $extraLocalFields->add($extraNtfs);
        $extraCdFields->add($extraNtfs);
        static::assertCount(1, $extraCdFields);
        static::assertCount(1, $extraLocalFields);

        $zipEntry->addExtraField($extraAsi);
        static::assertCount(2, $extraCdFields);
        static::assertCount(2, $extraLocalFields);

        $zipEntry->addCdExtraField($extraJar);
        static::assertCount(3, $extraCdFields);
        static::assertCount(2, $extraLocalFields);

        static::assertSame($zipEntry->getCdExtraField(JarMarkerExtraField::HEADER_ID), $extraJar);
        static::assertNull($zipEntry->getLocalExtraField(JarMarkerExtraField::HEADER_ID));
        static::assertSame($zipEntry->getLocalExtraField(AsiExtraField::HEADER_ID), $extraAsi);

        static::assertSame(
            [$extraNtfs, $extraAsi, $extraJar],
            array_values($extraCdFields->getAll())
        );
        static::assertSame(
            [$extraNtfs, $extraAsi],
            array_values($extraLocalFields->getAll())
        );

        $zipEntry->removeExtraField(AsiExtraField::HEADER_ID);
        static::assertNull($zipEntry->getCdExtraField(AsiExtraField::HEADER_ID));
        static::assertNull($zipEntry->getLocalExtraField(AsiExtraField::HEADER_ID));

        static::assertCount(2, $extraCdFields);
        static::assertCount(1, $extraLocalFields);
        static::assertSame(
            [$extraNtfs, $extraJar],
            array_values($extraCdFields->getAll())
        );
        static::assertSame(
            [$extraNtfs],
            array_values($extraLocalFields->getAll())
        );

        static::assertTrue($zipEntry->hasExtraField(NtfsExtraField::HEADER_ID));
        static::assertTrue($zipEntry->hasExtraField(JarMarkerExtraField::HEADER_ID));
        static::assertFalse($zipEntry->hasExtraField(AsiExtraField::HEADER_ID));
    }

    public function testComment(): void
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
    public function testLongComment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Comment too long');

        $longComment = random_bytes(0xffff + 1);
        $zipEntry = new ZipEntry('entry');
        $zipEntry->setComment($longComment);
    }

    /**
     * @dataProvider provideDataDescriptorRequired
     */
    public function testDataDescriptorRequired(int $crc, int $compressedSize, int $uncompressedSize, bool $required): void
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

    public function provideDataDescriptorRequired(): array
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
     * @param ?string $password
     * @param ?int    $encryptionMethod
     */
    public function testEncryption(?string $password, ?int $encryptionMethod, bool $encrypted, int $expectedEncryptionMethod): void
    {
        $zipEntry = new ZipEntry('entry');
        $zipEntry->setPassword($password, $encryptionMethod);

        static::assertSame($zipEntry->isEncrypted(), $encrypted);
        static::assertSame($zipEntry->getPassword(), $password);
        static::assertSame($zipEntry->getEncryptionMethod(), $expectedEncryptionMethod);

        $zipEntry->setPassword($password);
        static::assertSame($zipEntry->getEncryptionMethod(), $expectedEncryptionMethod);
    }

    public function provideEncryption(): array
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

    public function testDirectoryEncryption(): void
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
     * @param ?int $encryptionMethod
     */
    public function testEncryptionMethod(
        ?int $encryptionMethod,
        int $expectedEncryptionMethod,
        bool $encrypted,
        int $extractVersion
    ): void {
        $zipEntry = new ZipEntry('entry');
        $zipEntry->setEncryptionMethod($encryptionMethod);
        static::assertSame($zipEntry->isEncrypted(), $encrypted);
        static::assertSame($zipEntry->getEncryptionMethod(), $expectedEncryptionMethod);
        static::assertSame($zipEntry->getExtractVersion(), $extractVersion);
    }

    public function provideEncryptionMethod(): array
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
     */
    public function testInvalidEncryptionMethod(int $encryptionMethod): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Encryption method %d is not supported.', $encryptionMethod));

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setEncryptionMethod($encryptionMethod);
    }

    public function provideInvalidEncryptionMethod(): array
    {
        return [
            [-2],
            [4],
            [5],
        ];
    }

    /**
     * @dataProvider provideUnixMode
     */
    public function testUnixMode(string $entryName, int $unixMode): void
    {
        $zipEntry = new ZipEntry($entryName);
        $zipEntry->setUnixMode($unixMode);

        static::assertSame($zipEntry->getUnixMode(), $unixMode);
        static::assertSame($zipEntry->getCreatedOS(), ZipPlatform::OS_UNIX);
    }

    public function provideUnixMode(): array
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
     */
    public function testSymlink(string $entryName, int $unixMode, bool $symlink = false): void
    {
        $zipEntry = new ZipEntry($entryName);
        $zipEntry->setUnixMode($unixMode);
        static::assertSame($zipEntry->isUnixSymlink(), $symlink);
    }

    public function testAsiUnixMode(): void
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

    public function provideSymlink(): array
    {
        return [
            ['entry', 0120644, true],
            ['dir/', 0120755, true],
        ];
    }

    /**
     * @dataProvider provideIsZip64ExtensionsRequired
     */
    public function testIsZip64ExtensionsRequired(int $compressionSize, int $uncompressionSize, bool $required): void
    {
        if (\PHP_INT_SIZE === 4) {
            static::markTestSkipped('only php 64-bit');
        }

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setCompressedSize($compressionSize);
        $zipEntry->setUncompressedSize($uncompressionSize);
        static::assertSame($zipEntry->isZip64ExtensionsRequired(), $required);
    }

    public function provideIsZip64ExtensionsRequired(): array
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
     * @param ?\DateTimeInterface $atime
     * @param ?\DateTimeInterface $ctime
     */
    public function testMTimeATimeCTime(ExtraFieldsCollection $extraFieldsCollection, \DateTimeInterface $mtime, ?\DateTimeInterface $atime, ?\DateTimeInterface $ctime): void
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
     */
    public function provideExtraTime(): array
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
    public function testClone(): void
    {
        $newUnixExtra = new NewUnixExtraField();

        $zipEntry = new ZipEntry('entry');
        $zipData = new ZipFileData($zipEntry, new \SplFileInfo(__FILE__));

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

    public function testExtraCollection(): void
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
