<?php

namespace PhpZip;

use PhpZip\Exception\ZipException;

/**
 * @internal
 *
 * @large
 */
class Zip64Test extends ZipTestCase
{
    /**
     * Test support ZIP64 ext (slow test - normal).
     * Create > 65535 files in archive and open and extract to /dev/null.
     *
     * @throws ZipException
     */
    public function testCreateAndOpenZip64Ext()
    {
        $countFiles = 0xffff + 1;

        $zipFile = new ZipFile();
        for ($i = 0; $i < $countFiles; $i++) {
            $zipFile[$i . '.txt'] = (string) $i;
        }
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertSame($zipFile->count(), $countFiles);
        $i = 0;

        foreach ($zipFile as $entry => $content) {
            static::assertSame($entry, $i . '.txt');
            static::assertSame($content, (string) $i);
            $i++;
        }
        $zipFile->close();
    }
}
