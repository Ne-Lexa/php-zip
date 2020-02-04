<?php

namespace PhpZip\Tests\Extra\Fields;

use PHPUnit\Framework\TestCase;
use PhpZip\Exception\Crc32Exception;
use PhpZip\Exception\ZipException;
use PhpZip\Model\Extra\Fields\AsiExtraField;

/**
 * @internal
 *
 * @small
 */
final class AsiExtraFieldTest extends TestCase
{
    /**
     * @dataProvider provideExtraField
     *
     * @param int    $mode
     * @param int    $uid
     * @param int    $gid
     * @param string $link
     * @param string $binaryData
     *
     * @throws ZipException
     */
    public function testExtraField($mode, $uid, $gid, $link, $binaryData)
    {
        $asiExtraField = new AsiExtraField($mode, $uid, $gid, $link);
        self::assertSame($asiExtraField->getHeaderId(), AsiExtraField::HEADER_ID);

        self::assertSame($asiExtraField->getMode(), $mode);
        self::assertSame($asiExtraField->getUserId(), $uid);
        self::assertSame($asiExtraField->getGroupId(), $uid);
        self::assertSame($asiExtraField->getLink(), $link);

        self::assertSame($asiExtraField->packLocalFileData(), $binaryData);
        self::assertSame($asiExtraField->packCentralDirData(), $binaryData);

        self::assertEquals(AsiExtraField::unpackLocalFileData($binaryData), $asiExtraField);
        self::assertEquals(AsiExtraField::unpackCentralDirData($binaryData), $asiExtraField);
    }

    /**
     * @return array
     */
    public function provideExtraField()
    {
        return [
            [
                040755,
                AsiExtraField::USER_GID_PID,
                AsiExtraField::USER_GID_PID,
                '',
                "#\x06\\\xF6\xEDA\x00\x00\x00\x00\xE8\x03\xE8\x03",
            ],
            [
                0100644,
                0,
                0,
                'sites-enabled/example.conf',
                "_\xB8\xC7b\xA4\x81\x1A\x00\x00\x00\x00\x00\x00\x00sites-enabled/example.conf",
            ],
        ];
    }

    public function testSetter()
    {
        $extraField = new AsiExtraField(0777);
        $extraField->setMode(0100666);
        self::assertSame(0100666, $extraField->getMode());
        $extraField->setUserId(700);
        self::assertSame(700, $extraField->getUserId());
        $extraField->setGroupId(500);
        self::assertSame(500, $extraField->getGroupId());
        $extraField->setLink('link.txt');
        self::assertSame($extraField->getLink(), 'link.txt');
        self::assertSame(0120666, $extraField->getMode());

        // dir mode
        $extraField->setMode(0755);
        self::assertSame(0120755, $extraField->getMode());
        $extraField->setLink('');
        self::assertSame($extraField->getLink(), '');
        self::assertSame(0100755, $extraField->getMode());
    }

    /**
     * @throws Crc32Exception
     */
    public function testInvalidParse()
    {
        $this->setExpectedException(
            Crc32Exception::class,
            'Asi Unix Extra Filed Data (expected CRC32 value'
        );

        AsiExtraField::unpackLocalFileData("\x01\x06\\\xF6\xEDA\x00\x00\x00\x00\xE8\x03\xE8\x03");
    }
}
