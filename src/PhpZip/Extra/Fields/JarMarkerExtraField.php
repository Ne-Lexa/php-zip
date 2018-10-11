<?php

namespace PhpZip\Extra\Fields;

use PhpZip\Exception\ZipException;
use PhpZip\Extra\ExtraField;

/**
 * Jar Marker Extra Field
 * An executable Java program can be packaged in a JAR file with all the libraries it uses.
 * Executable JAR files can easily be distinguished from the files packed in the JAR file
 * by the extra field in the first file, which is hexadecimal in the 0xCAFE bytes series.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class JarMarkerExtraField implements ExtraField
{
    /**
     * Returns the Header ID (type) of this Extra Field.
     * The Header ID is an unsigned short integer (two bytes)
     * which must be constant during the life cycle of this object.
     *
     * @return int
     */
    public static function getHeaderId()
    {
        return 0xCAFE;
    }

    /**
     * Serializes a Data Block.
     * @return string
     */
    public function serialize()
    {
        return '';
    }

    /**
     * Initializes this Extra Field by deserializing a Data Block.
     * @param string $data
     * @throws ZipException
     */
    public function deserialize($data)
    {
        if (strlen($data) !== 0) {
            throw new ZipException("JarMarker doesn't expect any data");
        }
    }
}
