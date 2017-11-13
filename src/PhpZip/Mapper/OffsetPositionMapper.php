<?php

namespace PhpZip\Mapper;

/**
 * Adds a offset value to the given position.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class OffsetPositionMapper extends PositionMapper
{
    /**
     * @var int
     */
    private $offset;

    /**
     * @param int $offset
     */
    public function __construct($offset)
    {
        $this->offset = (int)$offset;
    }

    /**
     * @param int $position
     * @return int
     */
    public function map($position)
    {
        return parent::map($position) + $this->offset;
    }

    /**
     * @param int $position
     * @return int
     */
    public function unmap($position)
    {
        return parent::unmap($position) - $this->offset;
    }
}
