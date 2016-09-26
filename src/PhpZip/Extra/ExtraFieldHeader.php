<?php
namespace PhpZip\Extra;

/**
 * Interface ExtraFieldHeader
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
interface ExtraFieldHeader
{
    /**
     * Returns the Header ID (type) of this Extra Field.
     * The Header ID is an unsigned short integer (two bytes)
     * which must be constant during the life cycle of this object.
     *
     * @return int
     */
    public static function getHeaderId();

}