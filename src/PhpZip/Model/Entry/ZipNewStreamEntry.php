<?php
namespace PhpZip\Model\Entry;

use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipException;

/**
 * New zip entry from stream.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipNewStreamEntry extends ZipNewEntry
{
    /**
     * @var resource
     */
    private $stream;

    /**
     * ZipNewStreamEntry constructor.
     * @param resource $stream
     * @throws InvalidArgumentException
     */
    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('stream is not resource');
        }
        $this->stream = $stream;
    }

    /**
     * Returns an string content of the given entry.
     *
     * @return null|string
     * @throws ZipException
     */
    public function getEntryContent()
    {
        return stream_get_contents($this->stream, -1, 0);
    }

    /**
     * Release stream resource.
     */
    function __destruct()
    {
        if (null !== $this->stream) {
            fclose($this->stream);
            $this->stream = null;
        }
    }
}