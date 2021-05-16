<?php

namespace PhpZip\Tests\Extra\Fields;

use PHPUnit\Framework\TestCase;
use PhpZip\Exception\RuntimeException;
use PhpZip\Model\Extra\Fields\UnrecognizedExtraField;

/**
 * Class UnrecognizedExtraFieldTest.
 *
 * @internal
 *
 * @small
 */
final class UnrecognizedExtraFieldTest extends TestCase
{
    public function testExtraField()
    {
        $headerId = 0xF00D;
        $binaryData = "\x01\x02\x03\x04\x05";

        $unrecognizedExtraField = new UnrecognizedExtraField($headerId, $binaryData);
        self::assertSame($unrecognizedExtraField->getHeaderId(), $headerId);
        self::assertSame($unrecognizedExtraField->getData(), $binaryData);

        $newHeaderId = 0xDADA;
        $newBinaryData = "\x05\x00";
        $unrecognizedExtraField->setHeaderId($newHeaderId);
        self::assertSame($unrecognizedExtraField->getHeaderId(), $newHeaderId);
        $unrecognizedExtraField->setData($newBinaryData);
        self::assertSame($unrecognizedExtraField->getData(), $newBinaryData);

        self::assertSame($unrecognizedExtraField->packLocalFileData(), $newBinaryData);
        self::assertSame($unrecognizedExtraField->packCentralDirData(), $newBinaryData);
    }

    public function testUnpackLocalData()
    {
        $this->expectException(
            RuntimeException::class
        );
        $this->expectExceptionMessage(
            'Unsupport parse'
        );

        UnrecognizedExtraField::unpackLocalFileData("\x01\x02");
    }

    public function testUnpackCentralDirData()
    {
        $this->expectException(
            RuntimeException::class
        );
        $this->expectExceptionMessage(
            'Unsupport parse'
        );

        UnrecognizedExtraField::unpackCentralDirData("\x01\x02");
    }
}
