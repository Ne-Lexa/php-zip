<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\IO\Stream;

use Psr\Http\Message\StreamInterface;

/**
 * Implement PSR Message Stream.
 */
class ResponseStream implements StreamInterface
{
    /** @var array */
    private const READ_WRITE_MAP = [
        'read' => [
            'r' => true,
            'w+' => true,
            'r+' => true,
            'x+' => true,
            'c+' => true,
            'rb' => true,
            'w+b' => true,
            'r+b' => true,
            'x+b' => true,
            'c+b' => true,
            'rt' => true,
            'w+t' => true,
            'r+t' => true,
            'x+t' => true,
            'c+t' => true,
            'a+' => true,
        ],
        'write' => [
            'w' => true,
            'w+' => true,
            'rw' => true,
            'r+' => true,
            'x+' => true,
            'c+' => true,
            'wb' => true,
            'w+b' => true,
            'r+b' => true,
            'x+b' => true,
            'c+b' => true,
            'w+t' => true,
            'r+t' => true,
            'x+t' => true,
            'c+t' => true,
            'a' => true,
            'a+' => true,
        ],
    ];

    /** @var resource|null */
    private $stream;

    private ?int $size = null;

    private bool $seekable;

    private bool $readable;

    private bool $writable;

    private ?string $uri;

    /**
     * @param resource $stream stream resource to wrap
     *
     * @throws \InvalidArgumentException if the stream is not a stream resource
     */
    public function __construct($stream)
    {
        if (!\is_resource($stream)) {
            throw new \InvalidArgumentException('Stream must be a resource');
        }
        $this->stream = $stream;
        $meta = stream_get_meta_data($this->stream);
        $this->seekable = $meta['seekable'];
        $this->readable = isset(self::READ_WRITE_MAP['read'][$meta['mode']]);
        $this->writable = isset(self::READ_WRITE_MAP['write'][$meta['mode']]);
        $this->uri = $this->getMetadata('uri');
    }

    /**
     * {@inheritDoc}
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getMetadata($key = null)
    {
        if ($this->stream === null) {
            return $key ? null : [];
        }
        $meta = stream_get_meta_data($this->stream);

        return $meta[$key] ?? null;
    }

    /**
     * Reads all data from the stream into a string, from the beginning to end.
     *
     * This method MUST attempt to seek to the beginning of the stream before
     * reading data and read the stream until the end is reached.
     *
     * Warning: This could attempt to load a large amount of data into memory.
     *
     * This method MUST NOT raise an exception in order to conform with PHP's
     * string casting operations.
     *
     * @see http://php.net/manual/en/language.oop5.magic.php#object.tostring
     */
    public function __toString(): string
    {
        if (!$this->stream) {
            return '';
        }
        $this->rewind();

        return (string) stream_get_contents($this->stream);
    }

    /**
     * Seek to the beginning of the stream.
     *
     * If the stream is not seekable, this method will raise an exception;
     * otherwise, it will perform a seek(0).
     *
     * @throws \RuntimeException on failure
     *
     * @see http://www.php.net/manual/en/function.fseek.php
     * @see seek()
     */
    public function rewind(): void
    {
        $this->stream !== null && $this->seekable && rewind($this->stream);
    }

    /**
     * Get the size of the stream if known.
     *
     * @return int|null returns the size in bytes if known, or null if unknown
     */
    public function getSize(): ?int
    {
        if ($this->size !== null) {
            return $this->size;
        }

        if (!$this->stream) {
            return null;
        }
        // Clear the stat cache if the stream has a URI
        if ($this->uri !== null) {
            clearstatcache(true, $this->uri);
        }
        $stats = fstat($this->stream);

        if (isset($stats['size'])) {
            $this->size = $stats['size'];

            return $this->size;
        }

        return null;
    }

    public function tell()
    {
        return $this->stream ? ftell($this->stream) : false;
    }

    /**
     * Returns true if the stream is at the end of the stream.
     */
    public function eof(): bool
    {
        return !$this->stream || feof($this->stream);
    }

    /**
     * Returns whether or not the stream is seekable.
     */
    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    /**
     * {@inheritDoc}
     */
    public function seek($offset, $whence = \SEEK_SET): void
    {
        $this->stream !== null && $this->seekable && fseek($this->stream, $offset, $whence);
    }

    /**
     * Returns whether or not the stream is writable.
     */
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * {@inheritDoc}
     */
    public function write($string)
    {
        $this->size = null;

        return $this->stream !== null && $this->writable ? fwrite($this->stream, $string) : false;
    }

    /**
     * Returns whether or not the stream is readable.
     */
    public function isReadable(): bool
    {
        return $this->readable;
    }

    /**
     * {@inheritDoc}
     */
    public function read($length): string
    {
        return $this->stream !== null && $this->readable ? fread($this->stream, $length) : '';
    }

    /**
     * Returns the remaining contents in a string.
     *
     * @throws \RuntimeException if unable to read or an error occurs while
     *                           reading
     */
    public function getContents(): string
    {
        return $this->stream ? stream_get_contents($this->stream) : '';
    }

    /**
     * Closes the stream when the destructed.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Closes the stream and any underlying resources.
     *
     * @psalm-suppress InvalidPropertyAssignmentValue
     */
    public function close(): void
    {
        if (\is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->detach();
    }

    /**
     * Separates any underlying resources from the stream.
     *
     * After the stream has been detached, the stream is in an unusable state.
     *
     * @return resource|null Underlying PHP stream, if any
     */
    public function detach()
    {
        $result = $this->stream;
        $this->stream = null;
        $this->size = null;
        $this->uri = null;
        $this->readable = false;
        $this->writable = false;
        $this->seekable = false;

        return $result;
    }
}
