<?php

namespace PhpZip;

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
    public function testBeforeSave()
    {
        $zipFile = new ZipFileExtended();
        $zipFile->openFile(__DIR__ . '/resources/test.apk');
        self::assertTrue(isset($zipFile['META-INF/MANIFEST.MF']));
        self::assertTrue(isset($zipFile['META-INF/CERT.SF']));
        self::assertTrue(isset($zipFile['META-INF/CERT.RSA']));
        $zipFile->saveAsFile($this->outputFilename);
        self::assertFalse(isset($zipFile['META-INF/MANIFEST.MF']));
        self::assertFalse(isset($zipFile['META-INF/CERT.SF']));
        self::assertFalse(isset($zipFile['META-INF/CERT.RSA']));
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);
        $result = self::doZipAlignVerify($this->outputFilename);
        if (null !== $result) {
            self::assertTrue($result);
        }

        $zipFile->openFile($this->outputFilename);
        self::assertFalse(isset($zipFile['META-INF/MANIFEST.MF']));
        self::assertFalse(isset($zipFile['META-INF/CERT.SF']));
        self::assertFalse(isset($zipFile['META-INF/CERT.RSA']));
        $zipFile->close();
    }
}
