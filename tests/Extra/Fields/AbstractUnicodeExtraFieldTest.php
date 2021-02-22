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
use PhpZip\Exception\ZipException;
use PhpZip\Model\Extra\Fields\AbstractUnicodeExtraField;

abstract class AbstractUnicodeExtraFieldTest extends TestCase
{
    /**
     * @return string|AbstractUnicodeExtraField
     *
     * @psalm-var class-string<AbstractUnicodeExtraField>
     */
    abstract protected function getUnicodeExtraFieldClassName();

    /**
     * @dataProvider provideExtraField
     *
     * @throws ZipException
     */
    public function testExtraField(int $crc32, string $unicodePath, string $originalPath, string $binaryData): void
    {
        $className = $this->getUnicodeExtraFieldClassName();

        /** @var AbstractUnicodeExtraField $extraField */
        $extraField = new $className($crc32, $unicodePath);
        static::assertSame($extraField->getCrc32(), $crc32);
        static::assertSame($extraField->getUnicodeValue(), $unicodePath);
        static::assertSame(crc32($originalPath), $crc32);

        static::assertSame($binaryData, $extraField->packLocalFileData());
        static::assertSame($binaryData, $extraField->packCentralDirData());
        static::assertEquals($className::unpackLocalFileData($binaryData), $extraField);
        static::assertEquals($className::unpackCentralDirData($binaryData), $extraField);
    }

    abstract public function provideExtraField(): array;

    public function testSetter(): void
    {
        $className = $this->getUnicodeExtraFieldClassName();
        $entryName = '11111';

        /** @var AbstractUnicodeExtraField $extraField */
        $extraField = new $className(crc32($entryName), '22222');
        static::assertSame($extraField->getHeaderId(), $className::HEADER_ID);
        static::assertSame($extraField->getCrc32(), crc32($entryName));
        static::assertSame($extraField->getUnicodeValue(), '22222');

        $crc32 = 1234567;
        $extraField->setCrc32($crc32);
        static::assertSame($extraField->getCrc32(), $crc32);
        $extraField->setUnicodeValue('44444');
        static::assertSame($extraField->getUnicodeValue(), '44444');
    }

    /**
     * @throws ZipException
     */
    public function testUnicodeErrorParse(): void
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage('Unicode path extra data must have at least 5 bytes.');

        $className = $this->getUnicodeExtraFieldClassName();
        $className::unpackLocalFileData('');
    }

    /**
     * @throws ZipException
     */
    public function testUnknownVersionParse(): void
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage('Unsupported version [2] for Unicode path extra data.');

        $className = $this->getUnicodeExtraFieldClassName();
        $className::unpackLocalFileData("\x02\x04a\xD28\xC3\xA4\\\xC3\xBC.txt");
    }
}
