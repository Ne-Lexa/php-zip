<?php

namespace PhpZip\Extra;

/**
 * Extra Field in a Local or Central Header of a ZIP archive.
 * It defines the common properties of all Extra Fields and how to
 * serialize/deserialize them to/from byte arrays.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
interface ExtraField
{
    /**
     * Returns the Header ID (type) of this Extra Field.
     * The Header ID is an unsigned short integer (two bytes)
     * which must be constant during the life cycle of this object.
     *
     * @return int
     */
    public static function getHeaderId();

    /**
     * Serializes a Data Block.
     * @return string
     */
    public function serialize();

    /**
     * Initializes this Extra Field by deserializing a Data Block.
     * @param string $data
     */
    public function deserialize($data);
}
