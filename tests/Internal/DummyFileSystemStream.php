<?php

namespace PhpZip\Tests\Internal;

/**
 * Try to load using dummy stream.
 *
 * @see https://www.php.net/streamwrapper
 */
class DummyFileSystemStream
{
    /** @var resource */
    private $fp;

    /**
     * Opens file or URL.
     *
     * This method is called immediately after the wrapper is
     * initialized (f.e. by {@see fopen()} and {@see file_get_contents()}).
     *
     * @param string $path        specifies the URL that was passed to
     *                            the original function
     * @param string $mode        the mode used to open the file, as detailed
     *                            for {@see fopen()}
     * @param int    $options     Holds additional flags set by the streams
     *                            API. It can hold one or more of the
     *                            following values OR'd together.
     * @param string $opened_path if the path is opened successfully, and
     *                            STREAM_USE_PATH is set in options,
     *                            opened_path should be set to the
     *                            full path of the file/resource that
     *                            was actually opened
     *
     * @return bool
     *
     * @see https://www.php.net/streamwrapper.stream-open
     *
     * @noinspection PhpUsageOfSilenceOperatorInspection
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $parsedUrl = parse_url($path);
        $uri = substr($parsedUrl['path'], 1);
        $this->fp = @fopen($uri, $mode);

        return $this->fp !== false;
    }

    /**
     * Read from stream.
     *
     * This method is called in response to {@see fread()} and {@see fgets()}.
     *
     * Note: Remember to update the read/write position of the stream
     * (by the number of bytes that were successfully read).
     *
     * @param int $count how many bytes of data from the current
     *                   position should be returned
     *
     * @return false|string If there are less than count bytes available,
     *                      return as many as are available. If no more data
     *                      is available, return either FALSE or
     *                      an empty string.
     *
     * @see https://www.php.net/streamwrapper.stream-read
     */
    public function stream_read($count)
    {
        return fread($this->fp, $count);
    }

    /**
     * Seeks to specific location in a stream.
     *
     * This method is called in response to {@see fseek()}.
     * The read/write position of the stream should be updated according
     * to the offset and whence.
     *
     * @param int $offset the stream offset to seek to
     * @param int $whence Possible values:
     *                    {@see \SEEK_SET} - Set position equal to offset bytes.
     *                    {@see \SEEK_CUR} - Set position to current location plus offset.
     *                    {@see \SEEK_END} - Set position to end-of-file plus offset.
     *
     * @return bool return TRUE if the position was updated, FALSE otherwise
     *
     * @see https://www.php.net/streamwrapper.stream-seek
     */
    public function stream_seek($offset, $whence = \SEEK_SET)
    {
        return fseek($this->fp, $offset, $whence) === 0;
    }

    /**
     * Retrieve the current position of a stream.
     *
     * This method is called in response to {@see fseek()} to determine
     * the current position.
     *
     * @return int should return the current position of the stream
     *
     * @see https://www.php.net/streamwrapper.stream-tell
     */
    public function stream_tell()
    {
        $pos = ftell($this->fp);

        if ($pos === false) {
            throw new \RuntimeException('Cannot get stream position.');
        }

        return $pos;
    }

    /**
     * Tests for end-of-file on a file pointer.
     *
     * This method is called in response to {@see feof()}.
     *
     * @return bool should return TRUE if the read/write position is at
     *              the end of the stream and if no more data is available
     *              to be read, or FALSE otherwise
     *
     * @see https://www.php.net/streamwrapper.stream-eof
     */
    public function stream_eof()
    {
        return feof($this->fp);
    }

    /**
     * Retrieve information about a file resource.
     *
     * This method is called in response to {@see fstat()}.
     *
     * @return array
     *
     * @see https://www.php.net/streamwrapper.stream-stat
     * @see https://www.php.net/stat
     * @see https://www.php.net/fstat
     */
    public function stream_stat()
    {
        return fstat($this->fp);
    }

    /**
     * Flushes the output.
     *
     * This method is called in response to {@see fflush()} and when the
     * stream is being closed while any unflushed data has been written to
     * it before.
     * If you have cached data in your stream but not yet stored it into
     * the underlying storage, you should do so now.
     *
     * @return bool should return TRUE if the cached data was successfully
     *              stored (or if there was no data to store), or FALSE
     *              if the data could not be stored
     *
     * @see https://www.php.net/streamwrapper.stream-flush
     */
    public function stream_flush()
    {
        return fflush($this->fp);
    }

    /**
     * Truncate stream.
     *
     * Will respond to truncation, e.g., through {@see ftruncate()}.
     *
     * @param int $new_size the new size
     *
     * @return bool returns TRUE on success or FALSE on failure
     *
     * @see https://www.php.net/streamwrapper.stream-truncate
     */
    public function stream_truncate($new_size)
    {
        return ftruncate($this->fp, (int) $new_size);
    }

    /**
     * Write to stream.
     *
     * This method is called in response to {@see fwrite().}
     *
     * Note: Remember to update the current position of the stream by
     * number of bytes that were successfully written.
     *
     * @param string $data should be stored into the underlying stream
     *
     * @return int should return the number of bytes that were successfully stored, or 0 if none could be stored
     *
     * @see https://www.php.net/streamwrapper.stream-write
     */
    public function stream_write($data)
    {
        $bytes = fwrite($this->fp, $data);

        return $bytes === false ? 0 : $bytes;
    }

    /**
     * Close a resource.
     *
     * This method is called in response to {@see fclose()}.
     * All resources that were locked, or allocated, by the wrapper should be released.
     *
     * @see https://www.php.net/streamwrapper.stream-close
     */
    public function stream_close()
    {
        fclose($this->fp);
    }
}
