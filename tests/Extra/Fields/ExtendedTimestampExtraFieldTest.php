<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Tests\Extra\Fields;

use PHPUnit\Framework\TestCase;
use PhpZip\Model\Extra\Fields\ExtendedTimestampExtraField;

/**
 * Class ExtendedTimestampExtraFieldTest.
 *
 * @internal
 *
 * @small
 */
final class ExtendedTimestampExtraFieldTest extends TestCase
{
    /**
     * @dataProvider provideExtraField
     *
     * @noinspection PhpTooManyParametersInspection
     *
     * @param ?int $modifyTime
     * @param ?int $accessTime
     * @param ?int $createTime
     */
    public function testExtraField(
        int $flags,
        ?int $modifyTime,
        ?int $accessTime,
        ?int $createTime,
        string $localData,
        string $cdData
    ): void {
        $localExtraField = new ExtendedTimestampExtraField($flags, $modifyTime, $accessTime, $createTime);
        self::assertSame($localExtraField->getHeaderId(), ExtendedTimestampExtraField::HEADER_ID);
        self::assertSame($localExtraField->getFlags(), $flags);
        self::assertSame($localExtraField->getModifyTime(), $modifyTime);
        self::assertSame($localExtraField->getAccessTime(), $accessTime);
        self::assertSame($localExtraField->getCreateTime(), $createTime);
        self::assertSame($localExtraField->packLocalFileData(), $localData);
        self::assertEquals(ExtendedTimestampExtraField::unpackLocalFileData($localData), $localExtraField);

        $extTimeField = ExtendedTimestampExtraField::create($modifyTime, $accessTime, $createTime);
        self::assertEquals($extTimeField, $localExtraField);

        $accessTime = null;
        $createTime = null;
        $cdExtraField = new ExtendedTimestampExtraField($flags, $modifyTime, $accessTime, $createTime);
        self::assertSame($cdExtraField->getHeaderId(), ExtendedTimestampExtraField::HEADER_ID);
        self::assertSame($cdExtraField->getFlags(), $flags);
        self::assertSame($cdExtraField->getModifyTime(), $modifyTime);
        self::assertSame($cdExtraField->getAccessTime(), $accessTime);
        self::assertSame($cdExtraField->getCreateTime(), $createTime);
        self::assertSame($cdExtraField->packCentralDirData(), $cdData);
        self::assertEquals(ExtendedTimestampExtraField::unpackCentralDirData($cdData), $cdExtraField);
        self::assertSame($localExtraField->packCentralDirData(), $cdData);
    }

    public function provideExtraField(): array
    {
        return [
            [
                ExtendedTimestampExtraField::MODIFY_TIME_BIT
                | ExtendedTimestampExtraField::ACCESS_TIME_BIT
                | ExtendedTimestampExtraField::CREATE_TIME_BIT,
                911512006,
                911430000,
                893709400,
                "\x07\xC6\x91T6pQS6X\xECD5",
                "\x07\xC6\x91T6",
            ],
            [
                ExtendedTimestampExtraField::MODIFY_TIME_BIT
                | ExtendedTimestampExtraField::ACCESS_TIME_BIT,
                1492955702,
                1492955638,
                null,
                "\x036\xB2\xFCX\xF6\xB1\xFCX",
                "\x036\xB2\xFCX",
            ],
            [
                ExtendedTimestampExtraField::MODIFY_TIME_BIT,
                1470494391,
                null,
                null,
                "\x01\xB7\xF6\xA5W",
                "\x01\xB7\xF6\xA5W",
            ],
        ];
    }

    /**
     * @throws \Exception
     */
    public function testSetter(): void
    {
        $mtime = time();
        $atime = null;
        $ctime = null;

        $field = ExtendedTimestampExtraField::create($mtime, $atime, $ctime);
        self::assertSame($field->getFlags(), ExtendedTimestampExtraField::MODIFY_TIME_BIT);
        self::assertSame($field->getModifyTime(), $mtime);
        self::assertEquals($field->getModifyDateTime(), new \DateTimeImmutable('@' . $mtime));
        self::assertSame($field->getAccessTime(), $atime);
        self::assertSame($field->getCreateTime(), $ctime);

        $atime = strtotime('-1 min');
        $field->setAccessTime($atime);
        self::assertSame(
            $field->getFlags(),
            ExtendedTimestampExtraField::MODIFY_TIME_BIT
            | ExtendedTimestampExtraField::ACCESS_TIME_BIT
        );
        self::assertSame($field->getModifyTime(), $mtime);
        self::assertSame($field->getAccessTime(), $atime);
        self::assertEquals($field->getAccessDateTime(), new \DateTimeImmutable('@' . $atime));
        self::assertSame($field->getCreateTime(), $ctime);

        $ctime = strtotime('-1 hour');
        $field->setCreateTime($ctime);
        self::assertSame(
            $field->getFlags(),
            ExtendedTimestampExtraField::MODIFY_TIME_BIT
            | ExtendedTimestampExtraField::ACCESS_TIME_BIT
            | ExtendedTimestampExtraField::CREATE_TIME_BIT
        );
        self::assertSame($field->getModifyTime(), $mtime);
        self::assertSame($field->getAccessTime(), $atime);
        self::assertSame($field->getCreateTime(), $ctime);
        self::assertEquals($field->getCreateDateTime(), new \DateTimeImmutable('@' . $ctime));

        $field->setCreateTime(null);
        self::assertNull($field->getCreateTime());
        self::assertNull($field->getCreateDateTime());
        self::assertSame(
            $field->getFlags(),
            ExtendedTimestampExtraField::MODIFY_TIME_BIT
            | ExtendedTimestampExtraField::ACCESS_TIME_BIT
        );

        $field->setAccessTime(null);
        self::assertNull($field->getAccessTime());
        self::assertNull($field->getAccessDateTime());
        self::assertSame($field->getFlags(), ExtendedTimestampExtraField::MODIFY_TIME_BIT);

        $field->setModifyTime(null);
        self::assertNull($field->getModifyTime());
        self::assertNull($field->getModifyDateTime());
        self::assertSame($field->getFlags(), 0);
    }
}
