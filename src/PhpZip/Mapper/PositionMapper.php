<?php

namespace PhpZip\Mapper;

/**
 * Maps a given position.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class PositionMapper
{
    /**
     * @param int $position
     * @return int
     */
    public function map($position)
    {
        return $position;
    }

    /**
     * @param int $position
     * @return int
     */
    public function unmap($position)
    {
        return $position;
    }
}
