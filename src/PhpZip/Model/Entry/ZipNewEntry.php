<?php

namespace PhpZip\Model\Entry;

use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipException;
use PhpZip\ZipFileInterface;

/**
 * Abstract class for new zip entry.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipNewEntry extends ZipAbstractEntry
{
    /**
     * @var resource|string|null
     */
    protected $content;
    /**
     * @var bool
     */
    private $clone = false;

    /**
     * ZipNewEntry constructor.
     * @param string|resource|null $content
     * @throws InvalidArgumentException
     */
    public function __construct($content = null)
    {
        parent::__construct();
        if ($content !== null && !is_string($content) && !is_resource($content)) {
            throw new InvalidArgumentException('invalid content');
        }
        $this->content = $content;
    }

    /**
     * Returns an string content of the given entry.
     *
     * @return null|string
     * @throws ZipException
     */
    public function getEntryContent()
    {
        if (is_resource($this->content)) {
            return stream_get_contents($this->content, -1, 0);
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
        return self::METHOD_WINZIP_AES === $method ? 51 :
            (
            ZipFileInterface::METHOD_BZIP2 === $method ? 46 :
                (
                $this->isZip64ExtensionsRequired() ? 45 :
                    (ZipFileInterface::METHOD_DEFLATED === $method || $this->isDirectory() ? 20 : 10)
                )
            );
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
        if (!$this->clone && null !== $this->content && is_resource($this->content)) {
            fclose($this->content);
            $this->content = null;
        }
    }
}
