<?php

namespace PhpZip;

use PhpZip\Exception\ZipException;
use PhpZip\Util\CryptoUtil;

/**
 * Test ZipAlign
 */
class ZipAlignTest extends ZipTestCase
{
    /**
     * @throws ZipException
     */
    public function testApkAlignedAndSetZipAlignAndReSave()
    {
        $filename = __DIR__ . '/resources/test.apk';

        $this->assertCorrectZipArchive($filename);
        $result = $this->assertVerifyZipAlign($filename);
        if (null !== $result) {
            $this->assertTrue($result);
        }

        $zipFile = new ZipFile();
        $zipFile->openFile($filename);
        $zipFile->setZipAlign(4);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $this->assertCorrectZipArchive($this->outputFilename);
        $result = $this->assertVerifyZipAlign($this->outputFilename, true);
        if (null !== $result) {
            $this->assertTrue($result);
        }
    }

    /**
     * Test zip alignment.
     * @throws ZipException
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

        $this->assertCorrectZipArchive($this->outputFilename);

        $result = $this->assertVerifyZipAlign($this->outputFilename);
        if ($result === null) {
            return;
        } // zip align not installed

        // check not zip align
        $this->assertFalse($result);

        $zipFile->openFile($this->outputFilename);
        $zipFile->setZipAlign(4);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $this->assertCorrectZipArchive($this->outputFilename);

        $result = $this->assertVerifyZipAlign($this->outputFilename, true);
        $this->assertNotNull($result);

        // check zip align
        $this->assertTrue($result);
    }

    /**
     * @throws ZipException
     */
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

        $this->assertCorrectZipArchive($this->outputFilename);

        $result = $this->assertVerifyZipAlign($this->outputFilename);
        if ($result === null) {
            return;
        } // zip align not installed
        // check not zip align
        $this->assertTrue($result);
    }

    /**
     * @throws ZipException
     */
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

        $this->assertCorrectZipArchive($this->outputFilename);

        $result = $this->assertVerifyZipAlign($this->outputFilename);
        if ($result === null) {
            return;
        } // zip align not installed

        // check not zip align
        $this->assertFalse($result);

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

        $this->assertCorrectZipArchive($this->outputFilename);

        $result = $this->assertVerifyZipAlign($this->outputFilename, true);
        $this->assertNotNull($result);

        // check zip align
        $this->assertTrue($result);
    }
}
