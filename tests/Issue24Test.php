<?php

namespace PhpZip\Tests;

use PhpZip\Exception\ZipException;
use PhpZip\Tests\Internal\DummyFileSystemStream;
use PhpZip\ZipFile;

/**
 * @internal
 *
 * @small
 */
class Issue24Test extends ZipTestCase
{
    const PROTO_DUMMYFS = 'dummyfs';

    /**
     * This method is called before the first test of this test class is run.
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public static function setUpBeforeClass()
    {
        stream_wrapper_register(self::PROTO_DUMMYFS, DummyFileSystemStream::class);
    }

    /**
     * @throws ZipException
     * @throws \Exception
     */
    public function testDummyFS()
    {
        $fileContents = str_repeat(base64_encode(random_bytes(12000)), 100);

        // create zip file
        $zip = new ZipFile();
        $zip->addFromString(
            'file.txt',
            $fileContents,
            ZipFile::METHOD_DEFLATED
        );
        $zip->saveAsFile($this->outputFilename);
        $zip->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $uri = self::PROTO_DUMMYFS . '://localhost/' . $this->outputFilename;
        $stream = fopen($uri, 'rb');
        static::assertNotFalse($stream);
        $zip->openFromStream($stream);
        static::assertSame($zip->getListFiles(), ['file.txt']);
        static::assertSame($zip['file.txt'], $fileContents);
        $zip->close();
    }
}
