<?php

namespace PhpZip\Tests\Extra\Fields;

use PHPUnit\Framework\TestCase;
use PhpZip\Model\Extra\Fields\NtfsExtraField;

/**
 * Class NtfsExtraFieldTest.
 *
 * @internal
 *
 * @small
 */
final class NtfsExtraFieldTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (\PHP_INT_SIZE === 4) {
            self::markTestSkipped('only 64 bit test');
        }
    }

    /**
     * @dataProvider provideExtraField
     *
     * @param int    $modifyNtfsTime
     * @param int    $accessNtfsTime
     * @param int    $createNtfsTime
     * @param float  $modifyTimestamp
     * @param float  $accessTimestamp
     * @param float  $createTimestamp
     * @param string $binaryData
     *
     * @throws \Exception
     *
     * @noinspection PhpTooManyParametersInspection
     */
    public function testExtraField(
        $modifyNtfsTime,
        $accessNtfsTime,
        $createNtfsTime,
        $modifyTimestamp,
        $accessTimestamp,
        $createTimestamp,
        $binaryData
    ) {
        $extraField = new NtfsExtraField($modifyNtfsTime, $accessNtfsTime, $createNtfsTime);
        self::assertSame($extraField->getHeaderId(), NtfsExtraField::HEADER_ID);

        self::assertEquals($extraField->getModifyDateTime()->getTimestamp(), (int) $modifyTimestamp);
        self::assertEquals($extraField->getAccessDateTime()->getTimestamp(), (int) $accessTimestamp);
        self::assertEquals($extraField->getCreateDateTime()->getTimestamp(), (int) $createTimestamp);

        self::assertEquals(NtfsExtraField::unpackLocalFileData($binaryData), $extraField);
        self::assertEquals(NtfsExtraField::unpackCentralDirData($binaryData), $extraField);

        self::assertSame($extraField->packLocalFileData(), $binaryData);
        self::assertSame($extraField->packCentralDirData(), $binaryData);

        $extraFieldFromDateTime = NtfsExtraField::create(
            $extraField->getModifyDateTime(),
            $extraField->getAccessDateTime(),
            $extraField->getCreateDateTime()
        );

        self::assertEqualsIntegerWithDelta($extraFieldFromDateTime->getModifyNtfsTime(), $extraField->getModifyNtfsTime(), 100);
        self::assertEqualsIntegerWithDelta($extraFieldFromDateTime->getAccessNtfsTime(), $extraField->getAccessNtfsTime(), 100);
        self::assertEqualsIntegerWithDelta($extraFieldFromDateTime->getCreateNtfsTime(), $extraField->getCreateNtfsTime(), 100);
    }

    /**
     * @return array
     */
    public function provideExtraField()
    {
        return [
            [
                129853553114795379,
                129853553114795379,
                129853552641022547,
                1340881711.4795379,
                1340881711.4795379,
                1340881664.1022547,
                "\x00\x00\x00\x00\x01\x00\x18\x00s\xCD:Z\x1EU\xCD\x01s\xCD:Z\x1EU\xCD\x01S\x9A\xFD=\x1EU\xCD\x01",
            ],
            [
                131301570250000000,
                131865940850000000,
                131840940680000000,
                1485683425.000000,
                1542120485.000000,
                1539620468.000000,
                "\x00\x00\x00\x00\x01\x00\x18\x00\x80\xC63\x1D\x15z\xD2\x01\x80@V\xE2_{\xD4\x01\x00\xB2\x15\x14\xA3d\xD4\x01",
            ],
            [
                132181086710000000,
                132181086710000000,
                132181086710000000,
                1573635071.000000,
                1573635071.000000,
                1573635071.000000,
                "\x00\x00\x00\x00\x01\x00\x18\x00\x80\xE9_\x7F\xFF\x99\xD5\x01\x80\xE9_\x7F\xFF\x99\xD5\x01\x80\xE9_\x7F\xFF\x99\xD5\x01",
            ],
        ];
    }

    /**
     * @param int    $expected
     * @param int    $actual
     * @param int    $delta
     * @param string $message
     */
    private static function assertEqualsIntegerWithDelta(
        $expected,
        $actual,
        $delta,
        $message = ''
    ) {
        self::assertSame(
            self::roundInt($expected, $delta),
            self::roundInt($actual, $delta),
            $message
        );
    }

    /**
     * @param int $number
     * @param int $delta
     *
     * @return int
     */
    private static function roundInt($number, $delta)
    {
        return (int) (floor($number / $delta) * $delta);
    }

    /**
     * @dataProvider provideExtraField
     *
     * @param int   $mtimeNtfs
     * @param int   $atimeNtfs
     * @param int   $ctimeNtfs
     * @param float $mtimeTimestamp
     * @param float $atimeTimestamp
     * @param float $ctimeTimestamp
     *
     * @noinspection PhpTooManyParametersInspection
     * @noinspection PhpUnitDeprecationsInspection
     */
    public function testConverter(
        $mtimeNtfs,
        $atimeNtfs,
        $ctimeNtfs,
        $mtimeTimestamp,
        $atimeTimestamp,
        $ctimeTimestamp
    ) {
        self::assertEqualsWithDelta(NtfsExtraField::ntfsTimeToTimestamp($mtimeNtfs), $mtimeTimestamp, 0.00001);
        self::assertEqualsWithDelta(NtfsExtraField::ntfsTimeToTimestamp($atimeNtfs), $atimeTimestamp, 0.00001);
        self::assertEqualsWithDelta(NtfsExtraField::ntfsTimeToTimestamp($ctimeNtfs), $ctimeTimestamp, 0.00001);

        self::assertEqualsIntegerWithDelta(NtfsExtraField::timestampToNtfsTime($mtimeTimestamp), $mtimeNtfs, 10);
        self::assertEqualsIntegerWithDelta(NtfsExtraField::timestampToNtfsTime($atimeTimestamp), $atimeNtfs, 10);
        self::assertEqualsIntegerWithDelta(NtfsExtraField::timestampToNtfsTime($ctimeTimestamp), $ctimeNtfs, 10);
    }

    /**
     * @throws \Exception
     */
    public function testSetter()
    {
        $timeZone = new \DateTimeZone('UTC');
        $initDateTime = new \DateTimeImmutable('-1 min', $timeZone);
        $mtimeDateTime = new \DateTimeImmutable('-1 hour', $timeZone);
        $atimeDateTime = new \DateTimeImmutable('-1 day', $timeZone);
        $ctimeDateTime = new \DateTimeImmutable('-1 year', $timeZone);

        $extraField = NtfsExtraField::create($initDateTime, $initDateTime, $initDateTime);
        self::assertEquals(
            $extraField->getModifyDateTime()->getTimestamp(),
            $initDateTime->getTimestamp()
        );
        self::assertEquals(
            $extraField->getAccessDateTime()->getTimestamp(),
            $initDateTime->getTimestamp()
        );
        self::assertEquals(
            $extraField->getCreateDateTime()->getTimestamp(),
            $initDateTime->getTimestamp()
        );

        $extraField->setModifyDateTime($mtimeDateTime);
        self::assertEquals(
            $extraField->getModifyDateTime()->getTimestamp(),
            $mtimeDateTime->getTimestamp()
        );
        self::assertEquals(
            $extraField->getAccessDateTime()->getTimestamp(),
            $initDateTime->getTimestamp()
        );
        self::assertEquals(
            $extraField->getCreateDateTime()->getTimestamp(),
            $initDateTime->getTimestamp()
        );

        $extraField->setAccessDateTime($atimeDateTime);
        self::assertEquals(
            $extraField->getModifyDateTime()->getTimestamp(),
            $mtimeDateTime->getTimestamp()
        );
        self::assertEquals(
            $extraField->getAccessDateTime()->getTimestamp(),
            $atimeDateTime->getTimestamp()
        );
        self::assertEquals(
            $extraField->getCreateDateTime()->getTimestamp(),
            $initDateTime->getTimestamp()
        );

        $extraField->setCreateDateTime($ctimeDateTime);
        self::assertEquals(
            $extraField->getModifyDateTime()->getTimestamp(),
            $mtimeDateTime->getTimestamp()
        );
        self::assertEquals(
            $extraField->getAccessDateTime()->getTimestamp(),
            $atimeDateTime->getTimestamp()
        );
        self::assertEquals(
            $extraField->getCreateDateTime()->getTimestamp(),
            $ctimeDateTime->getTimestamp()
        );

        $newModifyNtfsTime = $extraField->getCreateNtfsTime();
        $newAccessNtfsTime = $extraField->getModifyNtfsTime();
        $newCreateNtfsTime = $extraField->getAccessNtfsTime();
        $extraField->setModifyNtfsTime($newModifyNtfsTime);
        $extraField->setAccessNtfsTime($newAccessNtfsTime);
        $extraField->setCreateNtfsTime($newCreateNtfsTime);
        self::assertSame($extraField->getModifyNtfsTime(), $newModifyNtfsTime);
        self::assertSame($extraField->getAccessNtfsTime(), $newAccessNtfsTime);
        self::assertSame($extraField->getCreateNtfsTime(), $newCreateNtfsTime);
    }
}
