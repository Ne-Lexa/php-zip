<?php
namespace PhpZip\Output;

use PhpZip\Model\ZipEntry;
use RuntimeException;

/**
 * Zip output entry for stream resource.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipOutputStreamEntry extends ZipOutputEntry
{
    /**
     * @var resource
     */
    private $stream;

    /**
     * @param resource $stream
     * @param ZipEntry $entry
     */
    public function __construct($stream, ZipEntry $entry)
    {
        parent::__construct($entry);
        if (!is_resource($stream)) {
            throw new RuntimeException('stream is not resource');
        }
        $this->stream = $stream;
    }

    /**
     * Returns entry data.
     *
     * @return string
     */
    public function getEntryContent()
    {
        rewind($this->stream);
        return stream_get_contents($this->stream);
    }

    /**
     * Release stream resource.
     */
    function __destruct()
    {
        if ($this->stream !== null) {
            fclose($this->stream);
            $this->stream = null;
        }
    }
}