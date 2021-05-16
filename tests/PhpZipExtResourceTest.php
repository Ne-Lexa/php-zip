<?php

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
class PhpZipExtResourceTest extends ZipTestCase
{
    /**
     * Bug #7214 (zip_entry_read() binary safe).
     *
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/bug7214.phpt
     *
     * @throws ZipException
     */
    public function testBinaryNull()
    {
        $filename = __DIR__ . '/resources/pecl/binarynull.zip';

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);

        foreach ($zipFile as $name => $contents) {
            $info = $zipFile->getEntryInfo($name);
            static::assertSame(\strlen($contents), $info->getSize());
        }
        $zipFile->close();

        static::assertCorrectZipArchive($filename);
    }

    /**
     * Bug #8009 (cannot add again same entry to an archive).
     *
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/bug8009.phpt
     *
     * @throws ZipException
     */
    public function testBug8009()
    {
        $filename = __DIR__ . '/resources/pecl/bug8009.zip';

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);
        $zipFile->addFromString('2.txt', '=)');
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertCount(2, $zipFile);
        static::assertTrue(isset($zipFile['1.txt']));
        static::assertTrue(isset($zipFile['2.txt']));
        static::assertSame($zipFile['2.txt'], $zipFile['1.txt']);
        $zipFile->close();
    }

    /**
     * Bug #40228 (extractTo does not create recursive empty path).
     *
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/bug40228.phpt
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/bug40228-mb.phpt
     * @dataProvider provideBug40228
     *
     * @param string $filename
     *
     * @throws ZipException
     */
    public function testBug40228($filename)
    {
        static::assertTrue(mkdir($this->outputDirname, 0755, true));

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);
        $zipFile->extractTo($this->outputDirname);
        $zipFile->close();

        static::assertDirectoryExists($this->outputDirname . '/test/empty');
    }

    /**
     * @return array
     */
    public function provideBug40228()
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
    public function testBug49072()
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
    public function testBug70752()
    {
        if (\PHP_INT_SIZE === 4) { // php 32 bit
            $this->expectException(
                RuntimeException::class
            );
            $this->expectExceptionMessage(
                'Traditional PKWARE Encryption is not supported in 32-bit PHP.'
            );
        } else { // php 64 bit
            $this->expectException(
                ZipAuthenticationException::class
            );
            $this->expectExceptionMessage(
                'Invalid password'
            );
        }

        $filename = __DIR__ . '/resources/pecl/bug70752.zip';

        static::assertTrue(mkdir($this->outputDirname, 0755, true));

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);
        $zipFile->setReadPassword('bar');

        try {
            $zipFile->extractTo($this->outputDirname);
            static::markTestIncomplete('failed test');
        } catch (ZipException $exception) {
            static::assertFileDoesNotExist($this->outputDirname . '/bug70752.txt');

            throw $exception;
        }
    }

    /**
     * Bug #12414 ( extracting files from damaged archives).
     *
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/pecl12414.phpt
     *
     * @throws ZipException
     */
    public function testPecl12414()
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage('Corrupt zip file. Cannot read zip entry.');

        $filename = __DIR__ . '/resources/pecl/pecl12414.zip';

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);
    }
}
