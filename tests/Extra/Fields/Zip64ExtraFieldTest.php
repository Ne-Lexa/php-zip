<?php

namespace PhpZip\Tests\Extra\Fields;

use PHPUnit\Framework\TestCase;
use PhpZip\Constants\ZipConstants;
use PhpZip\Exception\ZipException;
use PhpZip\Model\Extra\Fields\Zip64ExtraField;
use PhpZip\Model\ZipEntry;

/**
 * @internal
 *
 * @small
 */
final class Zip64ExtraFieldTest extends TestCase
{
    protected function setUp()
    {
        if (\PHP_INT_SIZE === 4) {
            self::markTestSkipped('only 64 bit test');
        }
    }

    /**
     * @dataProvider provideExtraField
     *
     * @param int|null    $uncompressedSize
     * @param int|null    $compressedSize
     * @param int|null    $localHeaderOffset
     * @param int|null    $diskStart
     * @param string|null $localBinaryData
     * @param string|null $cdBinaryData
     *
     * @throws ZipException
     *
     * @noinspection PhpTooManyParametersInspection
     */
    public function testExtraField(
        $uncompressedSize,
        $compressedSize,
        $localHeaderOffset,
        $diskStart,
        $localBinaryData,
        $cdBinaryData
    ) {
        $extraField = new Zip64ExtraField(
            $uncompressedSize,
            $compressedSize,
            $localHeaderOffset,
            $diskStart
        );
        self::assertSame($extraField->getHeaderId(), Zip64ExtraField::HEADER_ID);
        self::assertSame($extraField->getUncompressedSize(), $uncompressedSize);
        self::assertSame($extraField->getCompressedSize(), $compressedSize);
        self::assertSame($extraField->getLocalHeaderOffset(), $localHeaderOffset);
        self::assertSame($extraField->getDiskStart(), $diskStart);

        $zipEntry = new ZipEntry('entry');
        $zipEntry->setUncompressedSize($uncompressedSize !== null ? ZipConstants::ZIP64_MAGIC : 0xfffff);
        $zipEntry->setCompressedSize($compressedSize !== null ? ZipConstants::ZIP64_MAGIC : 0xffff);
        $zipEntry->setLocalHeaderOffset($localHeaderOffset !== null ? ZipConstants::ZIP64_MAGIC : 0xfff);

        if ($localBinaryData !== null) {
            self::assertSame($localBinaryData, $extraField->packLocalFileData());
            self::assertEquals(Zip64ExtraField::unpackLocalFileData($localBinaryData, $zipEntry), $extraField);
        }

        if ($cdBinaryData !== null) {
            self::assertSame($cdBinaryData, $extraField->packCentralDirData());
            self::assertEquals(Zip64ExtraField::unpackCentralDirData($cdBinaryData, $zipEntry), $extraField);
        }
    }

    /**
     * @return array
     */
    public function provideExtraField()
    {
        return [
            [
                0,
                2,
                null,
                null,
                "\x00\x00\x00\x00\x00\x00\x00\x00\x02\x00\x00\x00\x00\x00\x00\x00",
                null,
            ],
            [
                5368709120,
                5369580144,
                null,
                null,
                null,
                "\x00\x00\x00@\x01\x00\x00\x00pJ\x0D@\x01\x00\x00\x00",
            ],
            [
                null,
                null,
                4945378839,
                null,
                null,
                "\x17~\xC4&\x01\x00\x00\x00",
            ],
        ];
    }

    public function testSetter()
    {
        $extraField = new Zip64ExtraField();
        self::assertNull($extraField->getUncompressedSize());
        self::assertNull($extraField->getCompressedSize());
        self::assertNull($extraField->getLocalHeaderOffset());
        self::assertNull($extraField->getDiskStart());

        $uncompressedSize = 12222;
        $extraField->setUncompressedSize($uncompressedSize);
        self::assertSame($extraField->getUncompressedSize(), $uncompressedSize);

        $compressedSize = 12222;
        $extraField->setCompressedSize($uncompressedSize);
        self::assertSame($extraField->getCompressedSize(), $compressedSize);

        $localHeaderOffset = 12222;
        $extraField->setLocalHeaderOffset($localHeaderOffset);
        self::assertSame($extraField->getLocalHeaderOffset(), $localHeaderOffset);

        $diskStart = 2;
        $extraField->setDiskStart($diskStart);
        self::assertSame($extraField->getDiskStart(), $diskStart);
    }
}
