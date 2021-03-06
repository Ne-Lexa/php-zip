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
use PhpZip\Constants\ZipCompressionMethod;
use PhpZip\Constants\ZipEncryptionMethod;
use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipException;
use PhpZip\Exception\ZipUnsupportMethodException;
use PhpZip\Model\Extra\Fields\WinZipAesExtraField;

/**
 * @internal
 *
 * @small
 */
final class WinZipAesExtraFieldTest extends TestCase
{
    /**
     * @dataProvider provideExtraField
     *
     * @throws ZipException
     * @throws ZipUnsupportMethodException
     */
    public function testExtraField(
        int $vendorVersion,
        int $keyStrength,
        int $compressionMethod,
        int $saltSize,
        string $binaryData
    ): void {
        $extraField = new WinZipAesExtraField($vendorVersion, $keyStrength, $compressionMethod);
        self::assertSame($extraField->getHeaderId(), WinZipAesExtraField::HEADER_ID);
        self::assertSame($extraField->getVendorVersion(), $vendorVersion);
        self::assertSame($extraField->getKeyStrength(), $keyStrength);
        self::assertSame($extraField->getCompressionMethod(), $compressionMethod);
        self::assertSame($extraField->getVendorId(), WinZipAesExtraField::VENDOR_ID);
        self::assertSame($extraField->getSaltSize(), $saltSize);

        self::assertSame($binaryData, $extraField->packLocalFileData());
        self::assertSame($binaryData, $extraField->packCentralDirData());
        self::assertEquals(WinZipAesExtraField::unpackLocalFileData($binaryData), $extraField);
        self::assertEquals(WinZipAesExtraField::unpackCentralDirData($binaryData), $extraField);
    }

    public function provideExtraField(): array
    {
        return [
            [
                WinZipAesExtraField::VERSION_AE1,
                WinZipAesExtraField::KEY_STRENGTH_128BIT,
                ZipCompressionMethod::STORED,
                8,
                "\x01\x00AE\x01\x00\x00",
            ],
            [
                WinZipAesExtraField::VERSION_AE1,
                WinZipAesExtraField::KEY_STRENGTH_192BIT,
                ZipCompressionMethod::DEFLATED,
                12,
                "\x01\x00AE\x02\x08\x00",
            ],
            [
                WinZipAesExtraField::VERSION_AE2,
                WinZipAesExtraField::KEY_STRENGTH_128BIT,
                ZipCompressionMethod::DEFLATED,
                8,
                "\x02\x00AE\x01\x08\x00",
            ],
            [
                WinZipAesExtraField::VERSION_AE2,
                WinZipAesExtraField::KEY_STRENGTH_256BIT,
                ZipCompressionMethod::STORED,
                16,
                "\x02\x00AE\x03\x00\x00",
            ],
            [
                WinZipAesExtraField::VERSION_AE2,
                WinZipAesExtraField::KEY_STRENGTH_192BIT,
                ZipCompressionMethod::DEFLATED,
                12,
                "\x02\x00AE\x02\x08\x00",
            ],
            [
                WinZipAesExtraField::VERSION_AE2,
                WinZipAesExtraField::KEY_STRENGTH_256BIT,
                ZipCompressionMethod::STORED,
                16,
                "\x02\x00AE\x03\x00\x00",
            ],
        ];
    }

    /**
     * @throws ZipUnsupportMethodException
     */
    public function testSetter(): void
    {
        $extraField = new WinZipAesExtraField(
            WinZipAesExtraField::VERSION_AE1,
            WinZipAesExtraField::KEY_STRENGTH_256BIT,
            ZipCompressionMethod::DEFLATED
        );

        self::assertSame($extraField->getVendorVersion(), WinZipAesExtraField::VERSION_AE1);
        self::assertSame($extraField->getKeyStrength(), WinZipAesExtraField::KEY_STRENGTH_256BIT);
        self::assertSame($extraField->getCompressionMethod(), ZipCompressionMethod::DEFLATED);
        self::assertSame($extraField->getSaltSize(), 16);
        self::assertSame($extraField->getEncryptionStrength(), 256);
        self::assertSame($extraField->getEncryptionMethod(), ZipEncryptionMethod::WINZIP_AES_256);

        $extraField->setVendorVersion(WinZipAesExtraField::VERSION_AE2);
        self::assertSame($extraField->getVendorVersion(), WinZipAesExtraField::VERSION_AE2);
        self::assertSame($extraField->getKeyStrength(), WinZipAesExtraField::KEY_STRENGTH_256BIT);
        self::assertSame($extraField->getCompressionMethod(), ZipCompressionMethod::DEFLATED);
        self::assertSame($extraField->getSaltSize(), 16);
        self::assertSame($extraField->getEncryptionStrength(), 256);
        self::assertSame($extraField->getEncryptionMethod(), ZipEncryptionMethod::WINZIP_AES_256);

        $extraField->setKeyStrength(WinZipAesExtraField::KEY_STRENGTH_128BIT);
        self::assertSame($extraField->getVendorVersion(), WinZipAesExtraField::VERSION_AE2);
        self::assertSame($extraField->getKeyStrength(), WinZipAesExtraField::KEY_STRENGTH_128BIT);
        self::assertSame($extraField->getCompressionMethod(), ZipCompressionMethod::DEFLATED);
        self::assertSame($extraField->getSaltSize(), 8);
        self::assertSame($extraField->getEncryptionStrength(), 128);
        self::assertSame($extraField->getEncryptionMethod(), ZipEncryptionMethod::WINZIP_AES_128);

        $extraField->setKeyStrength(WinZipAesExtraField::KEY_STRENGTH_192BIT);
        self::assertSame($extraField->getVendorVersion(), WinZipAesExtraField::VERSION_AE2);
        self::assertSame($extraField->getKeyStrength(), WinZipAesExtraField::KEY_STRENGTH_192BIT);
        self::assertSame($extraField->getCompressionMethod(), ZipCompressionMethod::DEFLATED);
        self::assertSame($extraField->getSaltSize(), 12);
        self::assertSame($extraField->getEncryptionStrength(), 192);
        self::assertSame($extraField->getEncryptionMethod(), ZipEncryptionMethod::WINZIP_AES_192);

        $extraField->setCompressionMethod(ZipCompressionMethod::STORED);
        self::assertSame($extraField->getVendorVersion(), WinZipAesExtraField::VERSION_AE2);
        self::assertSame($extraField->getKeyStrength(), WinZipAesExtraField::KEY_STRENGTH_192BIT);
        self::assertSame($extraField->getCompressionMethod(), ZipCompressionMethod::STORED);
        self::assertSame($extraField->getSaltSize(), 12);
        self::assertSame($extraField->getEncryptionStrength(), 192);
        self::assertSame($extraField->getEncryptionMethod(), ZipEncryptionMethod::WINZIP_AES_192);
    }

    /**
     * @throws ZipUnsupportMethodException
     */
    public function testConstructUnsupportVendorVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupport WinZip AES vendor version: 3');

        new WinZipAesExtraField(
            3,
            WinZipAesExtraField::KEY_STRENGTH_192BIT,
            ZipCompressionMethod::STORED
        );
    }

    public function testSetterUnsupportVendorVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupport WinZip AES vendor version: 3');

        $extraField = new WinZipAesExtraField(
            WinZipAesExtraField::VERSION_AE1,
            WinZipAesExtraField::KEY_STRENGTH_192BIT,
            ZipCompressionMethod::STORED
        );
        $extraField->setVendorVersion(3);
    }

    public function testConstructUnsupportCompressionMethod(): void
    {
        $this->expectException(ZipUnsupportMethodException::class);
        $this->expectExceptionMessage('Compression method 3 (Reduced compression factor 2) is not supported.');

        new WinZipAesExtraField(
            WinZipAesExtraField::VERSION_AE1,
            WinZipAesExtraField::KEY_STRENGTH_192BIT,
            3
        );
    }

    public function testSetterUnsupportCompressionMethod(): void
    {
        $this->expectException(ZipUnsupportMethodException::class);
        $this->expectExceptionMessage('Compression method 3 (Reduced compression factor 2) is not supported.');

        $extraField = new WinZipAesExtraField(
            WinZipAesExtraField::VERSION_AE1,
            WinZipAesExtraField::KEY_STRENGTH_192BIT,
            ZipCompressionMethod::STORED
        );
        $extraField->setCompressionMethod(3);
    }

    /**
     * @throws ZipUnsupportMethodException
     */
    public function testConstructUnsupportKeyStrength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key strength 16 not support value. Allow values: 1, 2, 3');

        new WinZipAesExtraField(
            WinZipAesExtraField::VERSION_AE1,
            0x10,
            ZipCompressionMethod::STORED
        );
    }

    /**
     * @throws ZipException
     */
    public function testSetterUnsupportKeyStrength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key strength 16 not support value. Allow values: 1, 2, 3');

        new WinZipAesExtraField(
            WinZipAesExtraField::VERSION_AE1,
            0x10,
            ZipCompressionMethod::STORED
        );
    }
}
