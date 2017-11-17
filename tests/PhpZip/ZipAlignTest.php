<?php

namespace PhpZip;

use PhpZip\Util\CryptoUtil;

/**
 * Test ZipAlign
 */
class ZipAlignTest extends ZipTestCase
{
    public function testApkAlignedAndSetZipAlignAndReSave()
    {
        $filename = __DIR__ . '/resources/test.apk';

        self::assertCorrectZipArchive($filename);
        $result = self::doZipAlignVerify($filename);
        if (null !== $result) {
            self::assertTrue($result);
        }

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);
        $zipFile->setZipAlign(4);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);
        $result = self::doZipAlignVerify($this->outputFilename, true);
        if (null !== $result) {
            self::assertTrue($result);
        }
    }

    /**
     * Test zip alignment.
     */
    public function testZipAlignSourceZip()
    {
        $zipFile = new ZipFile();
        for ($i = 0; $i < 100; $i++) {
            $zipFile->addFromString(
                'entry' . $i . '.txt',
                CryptoUtil::randomBytes(mt_rand(100, 4096)),
                ZipFileInterface::METHOD_STORED
            );
        }
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $result = self::doZipAlignVerify($this->outputFilename);
        if ($result === null) {
            return;
        } // zip align not installed

        // check not zip align
        self::assertFalse($result);

        $zipFile->openFile($this->outputFilename);
        $zipFile->setZipAlign(4);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $result = self::doZipAlignVerify($this->outputFilename, true);
        self::assertNotNull($result);

        // check zip align
        self::assertTrue($result);
    }

    public function testZipAlignNewFiles()
    {
        $zipFile = new ZipFile();
        for ($i = 0; $i < 100; $i++) {
            $zipFile->addFromString(
                'entry' . $i . '.txt',
                CryptoUtil::randomBytes(mt_rand(100, 4096)),
                ZipFileInterface::METHOD_STORED
            );
        }
        $zipFile->setZipAlign(4);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $result = self::doZipAlignVerify($this->outputFilename);
        if ($result === null) {
            return;
        } // zip align not installed
        // check not zip align
        self::assertTrue($result);
    }

    public function testZipAlignFromModifiedZipArchive()
    {
        $zipFile = new ZipFile();
        for ($i = 0; $i < 100; $i++) {
            $zipFile->addFromString(
                'entry' . $i . '.txt',
                CryptoUtil::randomBytes(mt_rand(100, 4096)),
                ZipFileInterface::METHOD_STORED
            );
        }
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $result = self::doZipAlignVerify($this->outputFilename);
        if ($result === null) {
            return;
        } // zip align not installed

        // check not zip align
        self::assertFalse($result);

        $zipFile->openFile($this->outputFilename);
        $zipFile->deleteFromRegex("~entry2[\d]+\.txt$~s");
        for ($i = 0; $i < 100; $i++) {
            $isStored = (bool)mt_rand(0, 1);

            $zipFile->addFromString(
                'entry_new_' . ($isStored ? 'stored' : 'deflated') . '_' . $i . '.txt',
                CryptoUtil::randomBytes(mt_rand(100, 4096)),
                $isStored ?
                    ZipFileInterface::METHOD_STORED :
                    ZipFileInterface::METHOD_DEFLATED
            );
        }
        $zipFile->setZipAlign(4);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $result = self::doZipAlignVerify($this->outputFilename, true);
        self::assertNotNull($result);

        // check zip align
        self::assertTrue($result);
    }
}
