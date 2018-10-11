<?php

namespace PhpZip\Extra\Fields;

use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Extra\ExtraField;

/**
 * Apk Alignment Extra Field
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ApkAlignmentExtraField implements ExtraField
{
    /**
     * Minimum size (in bytes) of the extensible data block/field used
     * for alignment of uncompressed entries.
     */
    const ALIGNMENT_ZIP_EXTRA_MIN_SIZE_BYTES = 6;

    const ANDROID_COMMON_PAGE_ALIGNMENT_BYTES = 4096;

    /**
     * @var int
     */
    private $multiple;
    /**
     * @var int
     */
    private $padding;

    /**
     * Returns the Header ID (type) of this Extra Field.
     * The Header ID is an unsigned short integer (two bytes)
     * which must be constant during the life cycle of this object.
     *
     * @return int
     */
    public static function getHeaderId()
    {
        return 0xD935;
    }

    /**
     * Serializes a Data Block.
     * @return string
     */
    public function serialize()
    {
        if ($this->padding > 0) {
            $args = array_merge(
                ['vc*', $this->multiple],
                array_fill(2, $this->padding, 0)
            );
            return call_user_func_array('pack', $args);
        }
        return pack('v', $this->multiple);
    }

    /**
     * Initializes this Extra Field by deserializing a Data Block.
     * @param string $data
     */
    public function deserialize($data)
    {
        $length = strlen($data);
        if ($length < 2) {
            // This is APK alignment field.
            // FORMAT:
            //  * uint16 alignment multiple (in bytes)
            //  * remaining bytes -- padding to achieve alignment of data which starts after
            //    the extra field
            throw new InvalidArgumentException("Minimum 6 bytes of the extensible data block/field used for alignment of uncompressed entries.");
        }
        $this->multiple = unpack('v', $data)[1];
        $this->padding = $length - 2;
    }

    /**
     * @return mixed
     */
    public function getMultiple()
    {
        return $this->multiple;
    }

    /**
     * @return int
     */
    public function getPadding()
    {
        return $this->padding;
    }

    /**
     * @param int $multiple
     */
    public function setMultiple($multiple)
    {
        $this->multiple = $multiple;
    }

    /**
     * @param int $padding
     */
    public function setPadding($padding)
    {
        $this->padding = $padding;
    }
}
