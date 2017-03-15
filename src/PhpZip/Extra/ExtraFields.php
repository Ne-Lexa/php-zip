<?php
namespace PhpZip\Extra;

use PhpZip\Exception\ZipException;

/**
 * Represents a collection of Extra Fields as they may
 * be present at several locations in ZIP files.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ExtraFields
{
    /**
     * The map of Extra Fields.
     * Maps from Header ID to Extra Field.
     * Must not be null, but may be empty if no Extra Fields are used.
     * The map is sorted by Header IDs in ascending order.
     *
     * @var ExtraField[]
     */
    private $extra = [];

    /**
     * Returns the number of Extra Fields in this collection.
     *
     * @return int
     */
    public function size()
    {
        return sizeof($this->extra);
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
        if (isset($this->extra[$headerId])) {
            return $this->extra[$headerId];
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
        $this->extra[$headerId] = $extraField;
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
        return isset($this->extra[$headerId]);
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
        if (isset($this->extra[$headerId])) {
            $ef = $this->extra[$headerId];
            unset($this->extra[$headerId]);
            return $ef;
        }
        throw new ZipException('ExtraField not found');
    }

    /**
     * Returns a protective copy of the Extra Fields.
     * null is never returned.
     *
     * @return string
     * @throws ZipException If size out of range
     */
    public function getExtra()
    {
        $size = $this->getExtraLength();
        if (0x0000 > $size || $size > 0xffff) {
            throw new ZipException('size out of range');
        }
        if (0 === $size) return '';

        $fp = fopen('php://memory', 'r+b');
        $offset = 0;
        /**
         * @var ExtraField $ef
         */
        foreach ($this->extra as $ef) {
            fwrite($fp, pack('vv', $ef::getHeaderId(), $ef->getDataSize()));
            $offset += 4;
            fwrite($fp, $ef->writeTo($fp, $offset));
            $offset += $ef->getDataSize();
        }
        rewind($fp);
        $content = stream_get_contents($fp);
        fclose($fp);
        return $content;
    }

    /**
     * Returns the number of bytes required to hold the Extra Fields.
     *
     * @return int The length of the Extra Fields in bytes. May be 0.
     * @see #getExtra
     */
    public function getExtraLength()
    {
        if (empty($this->extra)) {
            return 0;
        }
        $length = 0;

        /**
         * @var ExtraField $extraField
         */
        foreach ($this->extra as $extraField) {
            $length += 4 + $extraField->getDataSize();
        }
        return $length;
    }

    /**
     * Initializes this Extra Field by deserializing a Data Block of
     * size bytes $size from the resource $handle at the zero based offset $off.
     *
     * @param resource $handle
     * @param int $off Offset
     * @param int $size Size
     * @throws ZipException If size out of range
     */
    public function readFrom($handle, $off, $size)
    {
        if (0x0000 > $size || $size > 0xffff) {
            throw new ZipException('size out of range');
        }
        $map = [];
        if (null !== $handle && 0 < $size) {
            $end = $off + $size;
            while ($off < $end) {
                fseek($handle, $off);
                $unpack = unpack('vheaderId/vdataSize', fread($handle, 4));
                $off += 4;
                $extraField = ExtraField::create($unpack['headerId']);
                $extraField->readFrom($handle, $off, $unpack['dataSize']);
                $off += $unpack['dataSize'];
                $map[$unpack['headerId']] = $extraField;
            }
            assert($off === $end);
        }
        $this->extra = $map;
    }

    /**
     * If clone extra fields.
     */
    function __clone()
    {
        foreach ($this->extra as $k => $v) {
            $this->extra[$k] = clone $v;
        }
    }

}