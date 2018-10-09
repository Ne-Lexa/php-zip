<?php

namespace PhpZip\Extra;

use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipException;

/**
 * Represents a collection of Extra Fields as they may
 * be present at several locations in ZIP files.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ExtraFieldsCollection implements \Countable, \ArrayAccess, \Iterator
{
    /**
     * The map of Extra Fields.
     * Maps from Header ID to Extra Field.
     * Must not be null, but may be empty if no Extra Fields are used.
     * The map is sorted by Header IDs in ascending order.
     *
     * @var ExtraField[]
     */
    protected $collection = [];

    /**
     * Returns the number of Extra Fields in this collection.
     *
     * @return int
     */
    public function count()
    {
        return sizeof($this->collection);
    }

    /**
     * Returns the Extra Field with the given Header ID or null
     * if no such Extra Field exists.
     *
     * @param int $headerId The requested Header ID.
     * @return ExtraField The Extra Field with the given Header ID or
     *         if no such Extra Field exists.
     * @throws ZipException If headerId is out of range.
     */
    public function get($headerId)
    {
        if (0x0000 > $headerId || $headerId > 0xffff) {
            throw new ZipException('headerId out of range');
        }
        if (isset($this->collection[$headerId])) {
            return $this->collection[$headerId];
        }
        return null;
    }

    /**
     * Stores the given Extra Field in this collection.
     *
     * @param ExtraField $extraField The Extra Field to store in this collection.
     * @return ExtraField The Extra Field previously associated with the Header ID of
     *                    of the given Extra Field or null if no such Extra Field existed.
     * @throws ZipException If headerId is out of range.
     */
    public function add(ExtraField $extraField)
    {
        $headerId = $extraField::getHeaderId();
        if (0x0000 > $headerId || $headerId > 0xffff) {
            throw new ZipException('headerId out of range');
        }
        $this->collection[$headerId] = $extraField;
        return $extraField;
    }

    /**
     * Returns Extra Field exists
     *
     * @param int $headerId The requested Header ID.
     * @return bool
     */
    public function has($headerId)
    {
        return isset($this->collection[$headerId]);
    }

    /**
     * Removes the Extra Field with the given Header ID.
     *
     * @param int $headerId The requested Header ID.
     * @return ExtraField   The Extra Field with the given Header ID or null
     *                      if no such Extra Field exists.
     * @throws ZipException If headerId is out of range or extra field not found.
     */
    public function remove($headerId)
    {
        if (0x0000 > $headerId || $headerId > 0xffff) {
            throw new ZipException('headerId out of range');
        }
        if (isset($this->collection[$headerId])) {
            $ef = $this->collection[$headerId];
            unset($this->collection[$headerId]);
            return $ef;
        }
        throw new ZipException('ExtraField not found');
    }

    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        return isset($this->collection[$offset]);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     * @throws ZipException
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @throws ZipException
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        if ($value instanceof ExtraField) {
            if ($offset !== $value::getHeaderId()) {
                throw new InvalidArgumentException("Value header id !== array access key");
            }
            $this->add($value);
        } else {
            throw new InvalidArgumentException('value is not instanceof ' . ExtraField::class);
        }
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     * @throws ZipException
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current()
    {
        return current($this->collection);
    }

    /**
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next()
    {
        next($this->collection);
    }

    /**
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        return key($this->collection);
    }

    /**
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid()
    {
        return $this->offsetExists($this->key());
    }

    /**
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind()
    {
        reset($this->collection);
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
