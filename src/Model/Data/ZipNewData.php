<?php

namespace PhpZip\Model\Data;

use PhpZip\Model\ZipData;
use PhpZip\Model\ZipEntry;

/**
 * Class ZipNewData.
 */
class ZipNewData implements ZipData
{
    /** @var resource */
    private $stream;

    /** @var ZipEntry */
    private $zipEntry;

    /**
     * ZipStringData constructor.
     *
     * @param ZipEntry        $zipEntry
     * @param string|resource $data
     */
    public function __construct(ZipEntry $zipEntry, $data)
    {
        $this->zipEntry = $zipEntry;

        if (\is_string($data)) {
            $zipEntry->setUncompressedSize(\strlen($data));

            if (!($handle = fopen('php://temp', 'w+b'))) {
                throw new \RuntimeException('Temp resource can not open from write.');
            }
            fwrite($handle, $data);
            rewind($handle);
            $this->stream = $handle;
        } elseif (\is_resource($data)) {
            $this->stream = $data;
        }
    }

    /**
     * @return resource returns stream data
     */
    public function getDataAsStream()
    {
        if (!\is_resource($this->stream)) {
            throw new \LogicException(sprintf('Resource was closed (entry=%s).', $this->zipEntry->getName()));
        }

        return $this->stream;
    }

    /**
     * @return string returns data as string
     */
    public function getDataAsString()
    {
        $stream = $this->getDataAsStream();
        $pos = ftell($stream);

        try {
            rewind($stream);

            return stream_get_contents($stream);
        } finally {
            fseek($stream, $pos);
        }
    }

    /**
     * @param resource $outStream
     */
    public function copyDataToStream($outStream)
    {
        $stream = $this->getDataAsStream();
        rewind($stream);
        stream_copy_to_stream($stream, $outStream);
    }

    public function __destruct()
    {
        if (\is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}
