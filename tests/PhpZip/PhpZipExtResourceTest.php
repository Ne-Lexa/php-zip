<?php

namespace PhpZip;

use PhpZip\Exception\ZipAuthenticationException;

/**
 * Some tests from the official extension of php-zip.
 */
class PhpZipExtResourceTest extends ZipTestCase
{
    /**
     * Bug #7214 (zip_entry_read() binary safe)
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/bug7214.phpt
     */
    public function testBinaryNull()
    {
        $filename = __DIR__ . '/php-zip-ext-test-resources/binarynull.zip';

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);
        foreach ($zipFile as $name => $contents) {
            $info = $zipFile->getEntryInfo($name);
            self::assertEquals(strlen($contents), $info->getSize());
        }
        $zipFile->close();

        self::assertCorrectZipArchive($filename);
    }

    /**
     * Bug #8009 (cannot add again same entry to an archive)
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/bug8009.phpt
     */
    public function testBug8009()
    {
        $filename = __DIR__ . '/php-zip-ext-test-resources/bug8009.zip';

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
        self::assertEquals($zipFile['2.txt'], $zipFile['1.txt']);
        $zipFile->close();
    }

    /**
     * Bug #40228 (extractTo does not create recursive empty path)
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/bug40228.phpt
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/bug40228-mb.phpt
     * @dataProvider provideBug40228
     * @param string $filename
     */
    public function testBug40228($filename)
    {
        self::assertTrue(mkdir($this->outputDirname, 0755, true));

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);
        $zipFile->extractTo($this->outputDirname);
        $zipFile->close();

        self::assertTrue(is_dir($this->outputDirname . '/test/empty'));
    }

    public function provideBug40228()
    {
        return [
            [__DIR__ . '/php-zip-ext-test-resources/bug40228.zip'],
        ];
    }

    /**
     * Bug #49072 (feof never returns true for damaged file in zip)
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/bug49072.phpt
     * @expectedException \PhpZip\Exception\Crc32Exception
     * @expectedExceptionMessage file1
     */
    public function testBug49072()
    {
        $filename = __DIR__ . '/php-zip-ext-test-resources/bug49072.zip';

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);
        $zipFile->getEntryContents('file1');
    }

    /**
     * Bug #70752 (Depacking with wrong password leaves 0 length files)
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/bug70752.phpt
     * @expectedException \PhpZip\Exception\ZipAuthenticationException
     * @expectedExceptionMessage Bad password for entry bug70752.txt
     */
    public function testBug70752()
    {
        $filename = __DIR__ . '/php-zip-ext-test-resources/bug70752.zip';

        self::assertTrue(mkdir($this->outputDirname, 0755, true));

        $zipFile = new ZipFile();
        try {
            $zipFile->openFile($filename);
            $zipFile->setReadPassword('bar');
            $zipFile->extractTo($this->outputDirname);
            self::markTestIncomplete('failed test');
        } catch (ZipAuthenticationException $exception) {
            self::assertFalse(file_exists($this->outputDirname . '/bug70752.txt'));
            $zipFile->close();
            throw $exception;
        }
    }

    /**
     * Bug #12414 ( extracting files from damaged archives)
     * @see https://github.com/php/php-src/blob/master/ext/zip/tests/pecl12414.phpt
     */
    public function testPecl12414()
    {
        $filename = __DIR__ . '/php-zip-ext-test-resources/pecl12414.zip';

        $entryName = 'MYLOGOV2.GFX';

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);

        $info = $zipFile->getEntryInfo($entryName);
        self::assertTrue($info->getSize() > 0);

        $contents = $zipFile[$entryName];
        self::assertEquals(strlen($contents), $info->getSize());

        $zipFile->close();
    }
}
