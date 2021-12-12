<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Model\Extra;

/**
 * Represents a collection of Extra Fields as they may
 * be present at several locations in ZIP files.
 */
class ExtraFieldsCollection implements \ArrayAccess, \Countable, \Iterator
{
    /**
     * The map of Extra Fields.
     * Maps from Header ID to Extra Field.
     * Must not be null, but may be empty if no Extra Fields are used.
     * The map is sorted by Header IDs in ascending order.
     *
     * @var ZipExtraField[]
     */
    protected array $collection = [];

    /**
     * Returns the number of Extra Fields in this collection.
     */
    public function count(): int
    {
        return \count($this->collection);
    }

    /**
     * Returns the Extra Field with the given Header ID or null
     * if no such Extra Field exists.
     *
     * @param int $headerId the requested Header ID
     *
     * @return ZipExtraField|null the Extra Field with the given Header ID or
     *                            if no such Extra Field exists
     */
    public function get(int $headerId): ?ZipExtraField
    {
        $this->validateHeaderId($headerId);

        return $this->collection[$headerId] ?? null;
    }

    private function validateHeaderId(int $headerId): void
    {
        if ($headerId < 0 || $headerId > 0xFFFF) {
            throw new \InvalidArgumentException('$headerId out of range');
        }
    }

    /**
     * Stores the given Extra Field in this collection.
     *
     * @param ZipExtraField $extraField the Extra Field to store in this collection
     *
     * @return ZipExtraField the Extra Field previously associated with the Header ID of
     *                       of the given Extra Field or null if no such Extra Field existed
     */
    public function add(ZipExtraField $extraField): ZipExtraField
    {
        $headerId = $extraField->getHeaderId();

        $this->validateHeaderId($headerId);
        $this->collection[$headerId] = $extraField;

        return $extraField;
    }

    /**
     * @param ZipExtraField[] $extraFields
     */
    public function addAll(array $extraFields): void
    {
        foreach ($extraFields as $extraField) {
            $this->add($extraField);
        }
    }

    /**
     * @param ExtraFieldsCollection $collection
     */
    public function addCollection(self $collection): void
    {
        $this->addAll($collection->collection);
    }

    /**
     * @return ZipExtraField[]
     */
    public function getAll(): array
    {
        return $this->collection;
    }

    /**
     * Returns Extra Field exists.
     *
     * @param int $headerId the requested Header ID
     */
    public function has(int $headerId): bool
    {
        return isset($this->collection[$headerId]);
    }

    /**
     * Removes the Extra Field with the given Header ID.
     *
     * @param int $headerId the requested Header ID
     *
     * @return ZipExtraField|null the Extra Field with the given Header ID or null
     *                            if no such Extra Field exists
     */
    public function remove(int $headerId): ?ZipExtraField
    {
        $this->validateHeaderId($headerId);

        if (isset($this->collection[$headerId])) {
            $ef = $this->collection[$headerId];
            unset($this->collection[$headerId]);

            return $ef;
        }

        return null;
    }

    /**
     * Whether a offset exists.
     *
     * @see http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param mixed $offset an offset to check for
     *
     * @return bool true on success or false on failure
     */
    public function offsetExists($offset): bool
    {
        return isset($this->collection[(int) $offset]);
    }

    /**
     * Offset to retrieve.
     *
     * @see http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param mixed $offset the offset to retrieve
     */
    public function offsetGet($offset): ?ZipExtraField
    {
        return $this->collection[(int) $offset] ?? null;
    }

    /**
     * Offset to set.
     *
     * @see http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $offset the offset to assign the value to
     * @param mixed $value  the value to set
     */
    public function offsetSet($offset, $value): void
    {
        if (!$value instanceof ZipExtraField) {
            throw new \InvalidArgumentException('value is not instanceof ' . ZipExtraField::class);
        }
        $this->add($value);
    }

    /**
     * Offset to unset.
     *
     * @see http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param mixed $offset the offset to unset
     */
    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }

    /**
     * Return the current element.
     *
     * @see http://php.net/manual/en/iterator.current.php
     */
    public function current(): ZipExtraField
    {
        return current($this->collection);
    }

    /**
     * Move forward to next element.
     *
     * @see http://php.net/manual/en/iterator.next.php
     */
    public function next(): void
    {
        next($this->collection);
    }

    /**
     * Return the key of the current element.
     *
     * @see http://php.net/manual/en/iterator.key.php
     *
     * @return int scalar on success, or null on failure
     */
    public function key(): int
    {
        return key($this->collection);
    }

    /**
     * Checks if current position is valid.
     *
     * @see http://php.net/manual/en/iterator.valid.php
     *
     * @return bool The return value will be casted to boolean and then evaluated.
     *              Returns true on success or false on failure.
     */
    public function valid(): bool
    {
        return key($this->collection) !== null;
    }

    /**
     * Rewind the Iterator to the first element.
     *
     * @see http://php.net/manual/en/iterator.rewind.php
     */
    public function rewind(): void
    {
        reset($this->collection);
    }

    public function clear(): void
    {
        $this->collection = [];
    }

    public function __toString(): string
    {
        $formats = [];

        foreach ($this->collection as $key => $value) {
            $formats[] = (string) $value;
        }

        return implode("\n", $formats);
    }

    /**
     * If clone extra fields.
     */
    public function __clone()
    {
        foreach ($this->collection as $k => $v) {
            $this->collection[$k] = clone $v;
        }
    }
}
