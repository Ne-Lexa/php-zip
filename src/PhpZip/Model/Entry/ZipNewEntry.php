<?php

namespace PhpZip\Model\Entry;

use PhpZip\Exception\InvalidArgumentException;

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
