<?php

namespace PhpZip;

use PhpZip\Exception\ZipException;
use PhpZip\Util\Iterator\IgnoreFilesFilterIterator;
use PhpZip\Util\Iterator\IgnoreFilesRecursiveFilterIterator;

/**
 * Test add remote files to zip archive
 */
class ZipRemoteFileTest extends ZipTestCase
{

    protected function setUp()
    {
        parent::setUp();
    }

    /**
     * @throws ZipException
     */
    public function testAddRemoteFileFromStream()
    {
        $zipFile = new ZipFile();
        $outputZip = $this->outputFilename;
        $fileUrl = 'https://raw.githubusercontent.com/Ne-Lexa/php-zip/master/README.md';
        $fp = @fopen($fileUrl, 'rb', false, stream_context_create([
            'http' => [
                'timeout' => 3,
            ]
        ]));
        if ($fp === false) {
            self::markTestSkipped(sprintf(
                "Could not fetch remote file: %s",
                $fileUrl
            ));
            return;
        }

        $fileName = 'remote-file-from-http-stream.md';
        $zipFile->addFromStream($fp, $fileName);

        $zipFile->saveAsFile($outputZip);
        $zipFile->close();

        $zipFile = new ZipFile();
        $zipFile->openFile($outputZip);
        $files = $zipFile->getListFiles();
        self::assertCount(1, $files);
        self::assertSame($fileName, $files[0]);
        $zipFile->close();
    }

}
