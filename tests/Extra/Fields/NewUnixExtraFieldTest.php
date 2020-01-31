<?php

namespace PhpZip\Tests\Extra\Fields;

use PHPUnit\Framework\TestCase;
use PhpZip\Exception\ZipException;
use PhpZip\Model\Extra\Fields\NewUnixExtraField;

/**
 * Class NewUnixExtraFieldTest.
 *
 * @internal
 *
 * @small
 */
final class NewUnixExtraFieldTest extends TestCase
{
    /**
     * @dataProvider provideExtraField
     *
     * @param int    $version
     * @param int    $uid
     * @param int    $gid
     * @param string $binaryData
     *
     * @throws ZipException
     */
    public function testExtraField($version, $uid, $gid, $binaryData)
    {
        $extraField = new NewUnixExtraField($version, $uid, $gid);
        self::assertSame($extraField->getHeaderId(), NewUnixExtraField::HEADER_ID);
        self::assertSame($extraField->getVersion(), $version);
        self::assertSame($extraField->getGid(), $gid);
        self::assertSame($extraField->getUid(), $uid);

        self::assertEquals(NewUnixExtraField::unpackLocalFileData($binaryData), $extraField);
        self::assertEquals(NewUnixExtraField::unpackCentralDirData($binaryData), $extraField);

        self::assertSame($extraField->packLocalFileData(), $binaryData);
        self::assertSame($extraField->packCentralDirData(), $binaryData);
    }

    /**
     * @return array
     */
    public function provideExtraField()
    {
        return [
            [
                1,
                NewUnixExtraField::USER_GID_PID,
                NewUnixExtraField::USER_GID_PID,
                "\x01\x04\xE8\x03\x00\x00\x04\xE8\x03\x00\x00",
            ],
            [
                1,
                501,
                20,
                "\x01\x04\xF5\x01\x00\x00\x04\x14\x00\x00\x00",
            ],
            [
                1,
                500,
                495,
                "\x01\x04\xF4\x01\x00\x00\x04\xEF\x01\x00\x00",
            ],
            [
                1,
                11252,
                10545,
                "\x01\x04\xF4+\x00\x00\x041)\x00\x00",
            ],
            [
                1,
                1721,
                1721,
                "\x01\x04\xB9\x06\x00\x00\x04\xB9\x06\x00\x00",
            ],
        ];
    }

    public function testSetter()
    {
        $extraField = new NewUnixExtraField(1, 1000, 1000);
        self::assertSame(1, $extraField->getVersion());
        self::assertSame(1000, $extraField->getUid());
        self::assertSame(1000, $extraField->getGid());

        $extraField->setUid(0);
        self::assertSame(0, $extraField->getUid());
        self::assertSame(1000, $extraField->getGid());

        $extraField->setGid(0);
        self::assertSame(0, $extraField->getUid());
        self::assertSame(0, $extraField->getGid());
    }
}
