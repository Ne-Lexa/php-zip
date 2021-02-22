<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Tests;

use PhpZip\Exception\Crc32Exception;
use PhpZip\Exception\RuntimeException;
use PhpZip\Exception\ZipAuthenticationException;
use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;

/**
 * Some tests from the official extension of php-zip.
 *
 * @internal
 *
 * @small
 */
final class PhpZipExtResourceTest extends ZipTestCase
{
    /**
     * Bug #7214 (zip_entry_read() binary safe).
     *
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/bug7214.phpt
     *
     * @throws ZipException
     */
    public function testBinaryNull(): void
    {
        $filename = __DIR__ . '/resources/pecl/binarynull.zip';

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);

        foreach ($zipFile as $name => $contents) {
            $entry = $zipFile->getEntry($name);
            self::assertSame(\strlen($contents), $entry->getUncompressedSize());
        }
        $zipFile->close();

        self::assertCorrectZipArchive($filename);
    }

    /**
     * Bug #8009 (cannot add again same entry to an archive).
     *
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/bug8009.phpt
     *
     * @throws ZipException
     */
    public function testBug8009(): void
    {
        $filename = __DIR__ . '/resources/pecl/bug8009.zip';

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);
        $zipFile->addFromString('2.txt', '=)');
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        self::assertCount(2, $zipFile);
        self::assertTrue(isset($zipFile['1.txt']));
        self::assertTrue(isset($zipFile['2.txt']));
        self::assertSame($zipFile['2.txt'], $zipFile['1.txt']);
        $zipFile->close();
    }

    /**
     * Bug #40228 (extractTo does not create recursive empty path).
     *
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/bug40228.phpt
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/bug40228-mb.phpt
     * @dataProvider provideBug40228
     *
     * @throws ZipException
     */
    public function testBug40228(string $filename): void
    {
        self::assertTrue(mkdir($this->outputDirname, 0755, true));

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);
        $zipFile->extractTo($this->outputDirname);
        $zipFile->close();

        self::assertDirectoryExists($this->outputDirname . '/test/empty');
    }

    public function provideBug40228(): array
    {
        return [
            [__DIR__ . '/resources/pecl/bug40228.zip'],
        ];
    }

    /**
     * Bug #49072 (feof never returns true for damaged file in zip).
     *
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/bug49072.phpt
     *
     * @throws ZipException
     */
    public function testBug49072(): void
    {
        $this->expectException(Crc32Exception::class);
        $this->expectExceptionMessage('file1');

        $filename = __DIR__ . '/resources/pecl/bug49072.zip';

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);
        $zipFile->getEntryContents('file1');
    }

    /**
     * Bug #70752 (Depacking with wrong password leaves 0 length files).
     *
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/bug70752.phpt
     *
     * @throws ZipException
     */
    public function testBug70752(): void
    {
        if (\PHP_INT_SIZE === 4) { // php 32 bit
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Traditional PKWARE Encryption is not supported in 32-bit PHP.');
        } else { // php 64 bit
            $this->expectException(ZipAuthenticationException::class);
            $this->expectExceptionMessage('Invalid password');
        }

        $filename = __DIR__ . '/resources/pecl/bug70752.zip';

        self::assertTrue(mkdir($this->outputDirname, 0755, true));

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);
        $zipFile->setReadPassword('bar');

        try {
            $zipFile->extractTo($this->outputDirname);
            self::markTestIncomplete('failed test');
        } catch (ZipException $exception) {
            self::assertFileDoesNotExist($this->outputDirname . '/bug70752.txt');

            throw $exception;
        }
    }

    /**
     * Bug #12414 (extracting files from damaged archives).
     *
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/pecl12414.phpt
     *
     * @throws ZipException
     */
    public function testPecl12414(): void
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage('Corrupt zip file. Cannot read zip entry.');

        $filename = __DIR__ . '/resources/pecl/pecl12414.zip';

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);
    }
}
