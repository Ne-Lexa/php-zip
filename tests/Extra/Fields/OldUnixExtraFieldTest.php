<?php

namespace PhpZip\Tests\Extra\Fields;

use PHPUnit\Framework\TestCase;
use PhpZip\Model\Extra\Fields\OldUnixExtraField;

/**
 * Class OldUnixExtraFieldTest.
 *
 * @internal
 *
 * @small
 */
final class OldUnixExtraFieldTest extends TestCase
{
    /**
     * @dataProvider provideExtraField
     *
     * @param int|null $accessTime
     * @param int|null $modifyTime
     * @param int|null $uid
     * @param int|null $gid
     * @param string   $localBinaryData
     * @param string   $cdBinaryData
     *
     * @noinspection PhpTooManyParametersInspection
     *
     * @throws \Exception
     */
    public function testExtraField(
        $accessTime,
        $modifyTime,
        $uid,
        $gid,
        $localBinaryData,
        $cdBinaryData
    ) {
        $extraField = new OldUnixExtraField($accessTime, $modifyTime, $uid, $gid);
        self::assertSame($extraField->getHeaderId(), OldUnixExtraField::HEADER_ID);

        self::assertSame($extraField->getAccessTime(), $accessTime);
        self::assertSame($extraField->getModifyTime(), $modifyTime);
        self::assertSame($extraField->getUid(), $uid);
        self::assertSame($extraField->getGid(), $gid);

        if ($extraField->getModifyTime() !== null) {
            self::assertEquals(
                new \DateTimeImmutable('@' . $extraField->getModifyTime()),
                $extraField->getModifyDateTime()
            );
        }

        if ($extraField->getAccessTime() !== null) {
            self::assertEquals(
                new \DateTimeImmutable('@' . $extraField->getAccessTime()),
                $extraField->getAccessDateTime()
            );
        }

        self::assertEquals(OldUnixExtraField::unpackLocalFileData($localBinaryData), $extraField);
        self::assertSame($extraField->packLocalFileData(), $localBinaryData);

        $uid = null;
        $gid = null;
        $extraField = new OldUnixExtraField($accessTime, $modifyTime, $uid, $gid);
        self::assertSame($extraField->getHeaderId(), OldUnixExtraField::HEADER_ID);

        self::assertSame($extraField->getAccessTime(), $accessTime);
        self::assertSame($extraField->getModifyTime(), $modifyTime);
        self::assertNull($extraField->getUid());
        self::assertNull($extraField->getGid());

        if ($extraField->getModifyTime() !== null) {
            self::assertEquals(
                new \DateTimeImmutable('@' . $extraField->getModifyTime()),
                $extraField->getModifyDateTime()
            );
        }

        if ($extraField->getAccessTime() !== null) {
            self::assertEquals(
                new \DateTimeImmutable('@' . $extraField->getAccessTime()),
                $extraField->getAccessDateTime()
            );
        }

        self::assertEquals(OldUnixExtraField::unpackCentralDirData($cdBinaryData), $extraField);
        self::assertSame($extraField->packCentralDirData(), $cdBinaryData);
    }

    /**
     * @return array
     */
    public function provideExtraField()
    {
        return [
            [
                1213373265,
                1213365834,
                502,
                502,
                "Q\x9BRHJ~RH\xF6\x01\xF6\x01",
                "Q\x9BRHJ~RH",
            ],
            [
                935520420,
                935520401,
                501,
                100,
                "\xA4\xE8\xC27\x91\xE8\xC27\xF5\x01d\x00",
                "\xA4\xE8\xC27\x91\xE8\xC27",
            ],
            [
                1402666135,
                1402666135,
                501,
                20,
                "\x97\xFC\x9AS\x97\xFC\x9AS\xF5\x01\x14\x00",
                "\x97\xFC\x9AS\x97\xFC\x9AS",
            ],
            [
                null,
                null,
                null,
                null,
                '',
                '',
            ],
        ];
    }

    public function testSetter()
    {
        $extraField = new OldUnixExtraField(null, null, null, null);

        self::assertNull($extraField->getAccessTime());
        self::assertNull($extraField->getAccessDateTime());
        self::assertNull($extraField->getModifyTime());
        self::assertNull($extraField->getModifyDateTime());
        self::assertNull($extraField->getUid());
        self::assertNull($extraField->getGid());

        $extraField->setModifyTime(1402666135);
        self::assertSame($extraField->getModifyTime(), 1402666135);

        $extraField->setAccessTime(1213365834);
        self::assertSame($extraField->getAccessTime(), 1213365834);

        $extraField->setUid(500);
        self::assertSame($extraField->getUid(), 500);

        $extraField->setGid(100);
        self::assertSame($extraField->getGid(), 100);
    }
}
