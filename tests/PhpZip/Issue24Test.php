<?php

namespace PhpZip;

use PhpZip\Exception\ZipException;
use PhpZip\Util\CryptoUtil;

class Issue24Test extends ZipTestCase
{
    /**
     * This method is called before the first test of this test class is run.
     */
    public static function setUpBeforeClass()
    {
        stream_wrapper_register("dummyfs", DummyFileSystemStream::class);
    }

    /**
     * @throws ZipException
     */
    public function testDummyFS()
    {
        $fileContents = str_repeat(base64_encode(CryptoUtil::randomBytes(12000)), 100);

        // create zip file
        $zip = new ZipFile();
        $zip->addFromString(
            'file.txt',
            $fileContents,
            ZipFile::METHOD_DEFLATED
        );
        $zip->saveAsFile($this->outputFilename);
        $zip->close();

        $this->assertCorrectZipArchive($this->outputFilename);

        $stream = fopen('dummyfs://localhost/' . $this->outputFilename, 'rb');
        $this->assertNotFalse($stream);
        $zip->openFromStream($stream);
        $this->assertEquals($zip->getListFiles(), ['file.txt']);
        $this->assertEquals($zip['file.txt'], $fileContents);
        $zip->close();
    }
}

/**
 * Try to load using dummy stream
 */
class DummyFileSystemStream
{
    /**
     * @var resource
     */
    private $fp;

    public function stream_open($path, $mode, $options, &$opened_path)
    {
//        echo "DummyFileSystemStream->stream_open($path, $mode, $options)" . PHP_EOL;

        $parsedUrl = parse_url($path);
        $path = $parsedUrl['path'];
        $this->fp = fopen($path, $mode);

        return true;
    }

    public function stream_read($count)
    {
//        echo "DummyFileSystemStream->stream_read($count)" . PHP_EOL;
        $position = ftell($this->fp);

//        echo "Loading chunk " . $position . " to " . ($position + $count - 1) . PHP_EOL;
        $ret = fread($this->fp, $count);

//        echo "String length: " . strlen($ret) . PHP_EOL;

        return $ret;
    }

    public function stream_tell()
    {
//        echo "DummyFileSystemStream->stream_tell()" . PHP_EOL;
        return ftell($this->fp);
    }

    public function stream_eof()
    {
//        echo "DummyFileSystemStream->stream_eof()" . PHP_EOL;
        $isfeof = feof($this->fp);
        return $isfeof;
    }

    public function stream_seek($offset, $whence)
    {
//        echo "DummyFileSystemStream->stream_seek($offset, $whence)" . PHP_EOL;
        fseek($this->fp, $offset, $whence);
    }

    public function stream_stat()
    {
//        echo "DummyFileSystemStream->stream_stat()" . PHP_EOL;
        return fstat($this->fp);
    }
}
