<?php

namespace PhpZip\Internal;

/**
 * Try to load using dummy stream.
 */
class DummyFileSystemStream
{
    /** @var resource */
    private $fp;

    /**
     * @param $path
     * @param $mode
     * @param $options
     * @param $opened_path
     *
     * @return bool
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $parsedUrl = parse_url($path);
        $path = $parsedUrl['path'];
        $this->fp = fopen($path, $mode);

        return true;
    }

    /**
     * @param $count
     *
     * @return false|string
     */
    public function stream_read($count)
    {
        return fread($this->fp, $count);
    }

    /**
     * @return false|int
     */
    public function stream_tell()
    {
        return ftell($this->fp);
    }

    /**
     * @return bool
     */
    public function stream_eof()
    {
        return feof($this->fp);
    }

    /**
     * @param $offset
     * @param $whence
     */
    public function stream_seek($offset, $whence)
    {
        fseek($this->fp, $offset, $whence);
    }

    /**
     * @return array
     */
    public function stream_stat()
    {
        return fstat($this->fp);
    }
}
