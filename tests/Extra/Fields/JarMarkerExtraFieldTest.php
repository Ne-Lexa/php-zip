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
    public function testExtraField(): void
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
    public function testInvalidUnpackLocalData(): void
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage("JarMarker doesn't expect any data");

        JarMarkerExtraField::unpackLocalFileData("\x02\x00\00");
    }

    /**
     * @throws ZipException
     */
    public function testInvalidUnpackCdData(): void
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage("JarMarker doesn't expect any data");

        JarMarkerExtraField::unpackCentralDirData("\x02\x00\00");
    }
}
