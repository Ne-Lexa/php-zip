<?php

namespace PhpZip;

use PhpZip\Exception\ZipException;

class ZipFileExtended extends ZipFile
{
    protected function onBeforeSave()
    {
        parent::onBeforeSave();
        $this->setZipAlign(4);
        $this->deleteFromRegex('~^META\-INF/~i');
    }
}

class ZipEventTest extends ZipTestCase
{
    /**
     * @throws ZipException
     */
    public function testBeforeSave()
    {
        $zipFile = new ZipFileExtended();
        $zipFile->openFile(__DIR__ . '/resources/test.apk');
        $this->assertTrue(isset($zipFile['META-INF/MANIFEST.MF']));
        $this->assertTrue(isset($zipFile['META-INF/CERT.SF']));
        $this->assertTrue(isset($zipFile['META-INF/CERT.RSA']));
        $zipFile->saveAsFile($this->outputFilename);
        $this->assertFalse(isset($zipFile['META-INF/MANIFEST.MF']));
        $this->assertFalse(isset($zipFile['META-INF/CERT.SF']));
        $this->assertFalse(isset($zipFile['META-INF/CERT.RSA']));
        $zipFile->close();

        $this->assertCorrectZipArchive($this->outputFilename);
        $result = $this->assertVerifyZipAlign($this->outputFilename);
        if (null !== $result) {
            $this->assertTrue($result);
        }

        $zipFile->openFile($this->outputFilename);
        $this->assertFalse(isset($zipFile['META-INF/MANIFEST.MF']));
        $this->assertFalse(isset($zipFile['META-INF/CERT.SF']));
        $this->assertFalse(isset($zipFile['META-INF/CERT.RSA']));
        $zipFile->close();
    }
}
