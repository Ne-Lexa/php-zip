<?php

namespace PhpZip\Model\Entry;

use PhpZip\Exception\ZipException;
use PhpZip\Stream\ZipInputStreamInterface;

/**
 * This class is used to represent a ZIP file entry.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipSourceEntry extends ZipAbstractEntry
{
    /**
     * Max size cached content in memory.
     */
    const DEFAULT_MAX_SIZE_CACHED_CONTENT_IN_MEMORY = 524288; // 512 kb
    /**
     * @var ZipInputStreamInterface
     */
    protected $inputStream;
    /**
     * @var string|resource Cached entry content.
     */
    protected $entryContent;
    /**
     * @var string
     */
    protected $readPassword;
    /**
     * @var bool
     */
    private $clone = false;

    /**
     * @var int
     */
    private $maxCacheContentSize;

    /**
     * @var bool
     */
    private $shouldCache;

    /**
     * ZipSourceEntry constructor.
     * @param ZipInputStreamInterface $inputStream
     */
    public function __construct(ZipInputStreamInterface $inputStream)
    {
        parent::__construct();
        $this->inputStream = $inputStream;
        $this->maxCacheContentSize = self::DEFAULT_MAX_SIZE_CACHED_CONTENT_IN_MEMORY;
        $this->shouldCache = true;
    }

    /**
     * @return ZipInputStreamInterface
     */
    public function getInputStream()
    {
        return $this->inputStream;
    }

    /**
     * Returns an string content of the given entry.
     *
     * @return string
     */
    public function getEntryContent()
    {
        if ($this->entryContent === null) {
            // In order not to unpack again, we cache the content in memory or on disk
            $content = $this->inputStream->readEntryContent($this);

            if ($this->shouldCache) {
                $this->cacheEntryContent($content);
            }

            return $content;
        }
        if (is_resource($this->entryContent)) {
            rewind($this->entryContent);
            return stream_get_contents($this->entryContent);
        }
        return $this->entryContent;
    }

    /**
     * Sets the maximum byte size content can be to be kept in memory
     * @param int $size
     * @return void
     */
    public function setMaximumCacheContentSize($size)
    {
        $this->maxCacheContentSize = $size;
    }

    /**
     * When set to TRUE to content of the entry will be cached, when FALSE it will not
     *
     * @param bool $shouldCache
     * @return void
     */
    public function shouldCacheContent($shouldCache)
    {
        $this->shouldCache = $shouldCache;
    }

    /**
     * Clone extra fields
     */
    public function __clone()
    {
        $this->clone = true;
        parent::__clone();
    }

    public function __destruct()
    {
        if (!$this->clone && $this->entryContent !== null && is_resource($this->entryContent)) {
            fclose($this->entryContent);
        }
    }

    /**
     * Will cache the content of the entry
     *
     * @param string $content
     * @return void
     */
    private function cacheEntryContent($content)
    {
        if ($this->getSize() < $this->maxCacheContentSize) {
            $this->entryContent = $content;
        } else {
            $this->entryContent = fopen('php://temp/', 'r+b');
            fwrite($this->entryContent, $content);
        }
    }

}
