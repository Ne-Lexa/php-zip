<?php

namespace PhpZip\Internal;

/**
 * Try to load using dummy stream.
 */
class DummyFileSystemStream
{
    /** @var resource */
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
        return fread($this->fp, $count);
//        echo "String length: " . strlen($ret) . PHP_EOL;
    }

    public function stream_tell()
    {
//        echo "DummyFileSystemStream->stream_tell()" . PHP_EOL;
        return ftell($this->fp);
    }

    public function stream_eof()
    {
//        echo "DummyFileSystemStream->stream_eof()" . PHP_EOL;
        return feof($this->fp);
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
