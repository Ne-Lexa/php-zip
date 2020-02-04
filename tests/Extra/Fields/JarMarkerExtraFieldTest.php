<?php

namespace PhpZip\Tests\Extra\Fields;

use PHPUnit\Framework\TestCase;
use PhpZip\Exception\ZipException;
use PhpZip\Model\Extra\Fields\JarMarkerExtraField;

/**
 * Class JarMarkerExtraFieldTest.
 *
 * @internal
 *
 * @small
 */
final class JarMarkerExtraFieldTest extends TestCase
{
    /**
     * @throws ZipException
     */
    public function testExtraField()
    {
        $jarField = new JarMarkerExtraField();
        self::assertSame('', $jarField->packLocalFileData());
        self::assertSame('', $jarField->packCentralDirData());
        self::assertEquals(JarMarkerExtraField::unpackLocalFileData(''), $jarField);
        self::assertEquals(JarMarkerExtraField::unpackCentralDirData(''), $jarField);
    }

    /**
     * @throws ZipException
     */
    public function testInvalidUnpackLocalData()
    {
        $this->setExpectedException(
            ZipException::class,
            "JarMarker doesn't expect any data"
        );

        JarMarkerExtraField::unpackLocalFileData("\x02\x00\00");
    }

    /**
     * @throws ZipException
     */
    public function testInvalidUnpackCdData()
    {
        $this->setExpectedException(
            ZipException::class,
            "JarMarker doesn't expect any data"
        );

        JarMarkerExtraField::unpackCentralDirData("\x02\x00\00");
    }
}
