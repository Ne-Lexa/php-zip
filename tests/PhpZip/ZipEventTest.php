<?php

namespace PhpZip;

use PhpZip\Exception\ZipException;

/**
 * @internal
 *
 * @small
 */
class ZipEventTest extends ZipTestCase
{
    /**
     * @throws ZipException
     */
    public function testBeforeSave()
    {
        $zipFile = new Internal\ZipFileExtended();
        $zipFile->openFile(__DIR__ . '/resources/apk.zip');
        static::assertTrue(isset($zipFile['META-INF/MANIFEST.MF']));
        static::assertTrue(isset($zipFile['META-INF/CERT.SF']));
        static::assertTrue(isset($zipFile['META-INF/CERT.RSA']));
        $zipFile->saveAsFile($this->outputFilename);
        static::assertFalse(isset($zipFile['META-INF/MANIFEST.MF']));
        static::assertFalse(isset($zipFile['META-INF/CERT.SF']));
        static::assertFalse(isset($zipFile['META-INF/CERT.RSA']));
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);
        $result = static::assertVerifyZipAlign($this->outputFilename);

        if ($result !== null) {
            static::assertTrue($result);
        }

        $zipFile->openFile($this->outputFilename);
        static::assertFalse(isset($zipFile['META-INF/MANIFEST.MF']));
        static::assertFalse(isset($zipFile['META-INF/CERT.SF']));
        static::assertFalse(isset($zipFile['META-INF/CERT.RSA']));
        $zipFile->close();
    }
}
