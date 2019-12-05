<?php

namespace PhpZip\Model\Entry;

use PhpZip\Exception\InvalidArgumentException;
use PhpZip\ZipFileInterface;

/**
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipNewEntry extends ZipAbstractEntry
{
    /** @var resource|string|null */
    protected $content;

    /** @var bool */
    private $clone = false;

    /**
     * ZipNewEntry constructor.
     *
     * @param string|resource|null $content
     */
    public function __construct($content = null)
    {
        parent::__construct();

        if ($content !== null && !\is_string($content) && !\is_resource($content)) {
            throw new InvalidArgumentException('invalid content');
        }
        $this->content = $content;
    }

    /**
     * Returns an string content of the given entry.
     *
     * @return string|null
     */
    public function getEntryContent()
    {
        if (\is_resource($this->content)) {
            if (stream_get_meta_data($this->content)['seekable']) {
                rewind($this->content);
            }

            return stream_get_contents($this->content);
        }

        return $this->content;
    }

    /**
     * Version needed to extract.
     *
     * @return int
     */
    public function getVersionNeededToExtract()
    {
        $method = $this->getMethod();

        return $method === self::METHOD_WINZIP_AES ? 51 :
            (
                $method === ZipFileInterface::METHOD_BZIP2 ? 46 :
                (
                    $this->isZip64ExtensionsRequired() ? 45 :
                    ($method === ZipFileInterface::METHOD_DEFLATED || $this->isDirectory() ? 20 : 10)
                )
            );
    }

    /**
     * Clone extra fields.
     */
    public function __clone()
    {
        $this->clone = true;
        parent::__clone();
    }

    public function __destruct()
    {
        if (!$this->clone && $this->content !== null && \is_resource($this->content)) {
            fclose($this->content);
            $this->content = null;
        }
    }
}
