<?php

namespace PhpZip\Tests;

use PhpZip\Exception\ZipException;
use PhpZip\Tests\Internal\ZipFileExtended;

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
        $zipFile = new ZipFileExtended();
        $zipFile->openFile(__DIR__ . '/resources/apk.zip');
        static::assertTrue(isset($zipFile['META-INF/MANIFEST.MF']));
        static::assertTrue(isset($zipFile['META-INF/CERT.SF']));
        static::assertTrue(isset($zipFile['META-INF/CERT.RSA']));
        // the "META-INF/" folder will be deleted when saved
        // in the ZipFileExtended::onBeforeSave() method
        $zipFile->saveAsFile($this->outputFilename);
        static::assertFalse(isset($zipFile['META-INF/MANIFEST.MF']));
        static::assertFalse(isset($zipFile['META-INF/CERT.SF']));
        static::assertFalse(isset($zipFile['META-INF/CERT.RSA']));
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertFalse(isset($zipFile['META-INF/MANIFEST.MF']));
        static::assertFalse(isset($zipFile['META-INF/CERT.SF']));
        static::assertFalse(isset($zipFile['META-INF/CERT.RSA']));
        $zipFile->close();
    }
}
