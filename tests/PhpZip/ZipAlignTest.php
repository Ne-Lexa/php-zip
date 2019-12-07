<?php

namespace PhpZip;

use PhpZip\Exception\ZipException;

/**
 * Test ZipAlign.
 *
 * @internal
 *
 * @small
 */
class ZipAlignTest extends ZipTestCase
{
    /**
     * @throws ZipException
     */
    public function testApkAlignedAndSetZipAlignAndReSave()
    {
        $filename = __DIR__ . '/resources/apk.zip';

        static::assertCorrectZipArchive($filename);
        $result = static::assertVerifyZipAlign($filename);

        if ($result !== null) {
            static::assertTrue($result);
        }

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);
        $zipFile->setZipAlign(4);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);
        $result = static::assertVerifyZipAlign($this->outputFilename, true);

        if ($result !== null) {
            static::assertTrue($result);
        }
    }

    /**
     * Test zip alignment.
     *
     * @throws ZipException
     * @throws \Exception
     */
    public function testZipAlignSourceZip()
    {
        $zipFile = new ZipFile();
        for ($i = 0; $i < 100; $i++) {
            $zipFile->addFromString(
                'entry' . $i . '.txt',
                random_bytes(mt_rand(100, 4096)),
                ZipFile::METHOD_STORED
            );
        }
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $result = static::assertVerifyZipAlign($this->outputFilename);

        if ($result === null) {
            return;
        } // zip align not installed

        // check not zip align
        static::assertFalse($result);

        $zipFile->openFile($this->outputFilename);
        $zipFile->setZipAlign(4);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $result = static::assertVerifyZipAlign($this->outputFilename, true);
        static::assertNotNull($result);

        // check zip align
        static::assertTrue($result);
    }

    /**
     * @throws ZipException
     * @throws \Exception
     */
    public function testZipAlignNewFiles()
    {
        $zipFile = new ZipFile();
        for ($i = 0; $i < 100; $i++) {
            $zipFile->addFromString(
                'entry' . $i . '.txt',
                random_bytes(mt_rand(100, 4096)),
                ZipFile::METHOD_STORED
            );
        }
        $zipFile->setZipAlign(4);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $result = static::assertVerifyZipAlign($this->outputFilename);

        if ($result === null) {
            return;
        } // zip align not installed
        // check not zip align
        static::assertTrue($result);
    }

    /**
     * @throws ZipException
     * @throws \Exception
     */
    public function testZipAlignFromModifiedZipArchive()
    {
        $zipFile = new ZipFile();
        for ($i = 0; $i < 100; $i++) {
            $zipFile->addFromString(
                'entry' . $i . '.txt',
                random_bytes(mt_rand(100, 4096)),
                ZipFile::METHOD_STORED
            );
        }
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $result = static::assertVerifyZipAlign($this->outputFilename);

        if ($result === null) {
            return;
        } // zip align not installed

        // check not zip align
        static::assertFalse($result);

        $zipFile->openFile($this->outputFilename);
        $zipFile->deleteFromRegex('~entry2[\\d]+\\.txt$~s');
        for ($i = 0; $i < 100; $i++) {
            $isStored = (bool) mt_rand(0, 1);

            $zipFile->addFromString(
                'entry_new_' . ($isStored ? 'stored' : 'deflated') . '_' . $i . '.txt',
                random_bytes(mt_rand(100, 4096)),
                $isStored ?
                    ZipFile::METHOD_STORED :
                    ZipFile::METHOD_DEFLATED
            );
        }
        $zipFile->setZipAlign(4);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $result = static::assertVerifyZipAlign($this->outputFilename, true);
        static::assertNotNull($result);

        // check zip align
        static::assertTrue($result);
    }
}
