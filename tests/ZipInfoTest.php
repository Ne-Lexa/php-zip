<?php

namespace PhpZip\Tests;

use PhpZip\Constants\ZipCompressionMethod;
use PhpZip\Constants\ZipEncryptionMethod;
use PhpZip\Constants\ZipPlatform;
use PhpZip\Exception\ZipEntryNotFoundException;
use PhpZip\Exception\ZipException;
use PhpZip\Model\ZipInfo;
use PhpZip\ZipFile;

/**
 * Testing the {@see ZipInfo} class.
 *
 * {@see ZipInfo} is {@deprecated}. Use the {@see ZipEntry} class.
 *
 * @internal
 *
 * @small
 */
final class ZipInfoTest extends ZipTestCase
{
    public function testZipAllInfo()
    {
        $zipFile = new ZipFile();
        $zipFile['entry'] = 'contents';
        $zipFile['entry 2'] = 'contents';
        $zipAllInfo = $zipFile->getAllInfo();
        $zipFile->close();

        self::assertCount(2, $zipAllInfo);
        self::assertContainsOnlyInstancesOf(ZipInfo::class, $zipAllInfo);
    }

    /**
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testZipEntryInfo()
    {
        $zipFile = new ZipFile();
        $zipFile['entry'] = 'contents';
        $zipFile['entry 2'] = 'contents';
        $zipInfo = $zipFile->getEntryInfo('entry');
        $zipFile->close();

        self::assertInstanceOf(ZipInfo::class, $zipInfo);
    }

    /**
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testZipInfoEntryNotFound()
    {
        $this->setExpectedException(
            ZipEntryNotFoundException::class,
            'Zip Entry "unknown.name" was not found in the archive.'
        );

        $zipFile = new ZipFile();
        $zipFile->getEntryInfo('unknown.name');
    }

    /**
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testZipInfo()
    {
        $zipFile = new ZipFile();
        $zipFile->openFile(__DIR__ . '/resources/Advanced-v1.0.0.epub');
        $entryName = 'META-INF/container.xml';
        $zipEntry = $zipFile->getEntry($entryName);
        $zipInfo = $zipFile->getEntryInfo($entryName);
        $zipFile->close();

        self::assertSame($zipInfo->getName(), $zipEntry->getName());
        self::assertSame($zipInfo->isFolder(), $zipEntry->isDirectory());
        self::assertSame($zipInfo->getSize(), $zipEntry->getUncompressedSize());
        self::assertSame($zipInfo->getCompressedSize(), $zipEntry->getCompressedSize());
        self::assertSame($zipInfo->getMtime(), $zipEntry->getMTime()->getTimestamp());
        self::assertSame(
            $zipInfo->getCtime(),
            $zipEntry->getCTime() !== null ? $zipEntry->getCTime()->getTimestamp() : null
        );
        self::assertSame(
            $zipInfo->getAtime(),
            $zipEntry->getATime() !== null ? $zipEntry->getATime()->getTimestamp() : null
        );
        self::assertNotEmpty($zipInfo->getAttributes());
        self::assertSame($zipInfo->isEncrypted(), $zipEntry->isEncrypted());
        self::assertSame($zipInfo->getComment(), $zipEntry->getComment());
        self::assertSame($zipInfo->getCrc(), $zipEntry->getCrc());
        self::assertSame(
            $zipInfo->getMethod(),
            ZipCompressionMethod::getCompressionMethodName($zipEntry->getCompressionMethod())
        );
        self::assertSame(
            $zipInfo->getMethodName(),
            ZipCompressionMethod::getCompressionMethodName($zipEntry->getCompressionMethod())
        );
        self::assertSame(
            $zipInfo->getEncryptionMethodName(),
            ZipEncryptionMethod::getEncryptionMethodName($zipEntry->getEncryptionMethod())
        );
        self::assertSame($zipInfo->getPlatform(), ZipPlatform::getPlatformName($zipEntry->getExtractedOS()));
        self::assertSame(ZipInfo::getPlatformName($zipEntry), ZipPlatform::getPlatformName($zipEntry->getExtractedOS()));
        self::assertSame($zipInfo->getVersion(), $zipEntry->getExtractVersion());
        self::assertNull($zipInfo->getEncryptionMethod());
        self::assertSame($zipInfo->getCompressionLevel(), $zipEntry->getCompressionLevel());
        self::assertSame($zipInfo->getCompressionMethod(), $zipEntry->getCompressionMethod());
        self::assertNotEmpty($zipInfo->toArray());

        self::assertSame((string) $zipInfo, 'PhpZip\Model\ZipInfo {Name="META-INF/container.xml", Size="249 bytes", Compressed size="169 bytes", Modified time="2019-04-08T14:59:08+00:00", Comment="", Method name="Deflated", Attributes="------", Platform="MS-DOS", Version=20}');
    }
}
