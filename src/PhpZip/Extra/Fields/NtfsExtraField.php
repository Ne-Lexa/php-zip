<?php

namespace PhpZip\Extra\Fields;

use PhpZip\Extra\ExtraField;
use PhpZip\Util\PackUtil;

/**
 * NTFS Extra Field
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class NtfsExtraField implements ExtraField
{

    /**
     * Modify time
     *
     * @var int Unix Timestamp
     */
    private $mtime;

    /**
     * Access Time
     *
     * @var int Unix Timestamp
     */
    private $atime;

    /**
     * Create Time
     *
     * @var int Unix Time
     */
    private $ctime;

    /**
     * Returns the Header ID (type) of this Extra Field.
     * The Header ID is an unsigned short integer (two bytes)
     * which must be constant during the life cycle of this object.
     *
     * @return int
     */
    public static function getHeaderId()
    {
        return 0x000a;
    }

    /**
     * Initializes this Extra Field by deserializing a Data Block.
     * @param string $data
     */
    public function deserialize($data)
    {
        $unpack = unpack('vtag/vsizeAttr', substr($data, 0, 4));
        if ($unpack['sizeAttr'] === 24) {
            $tagData = substr($data, 4, $unpack['sizeAttr']);
            $this->mtime = PackUtil::unpackLongLE(substr($tagData, 0, 8)) / 10000000 - 11644473600;
            $this->atime = PackUtil::unpackLongLE(substr($tagData, 8, 8)) / 10000000 - 11644473600;
            $this->ctime = PackUtil::unpackLongLE(substr($tagData, 16, 8)) / 10000000 - 11644473600;
        }
    }

    /**
     * Serializes a Data Block.
     * @return string
     */
    public function serialize()
    {
        $serialize = '';
        if ($this->mtime !== null && $this->atime !== null && $this->ctime !== null) {
            $mtimeLong = ($this->mtime + 11644473600) * 10000000;
            $atimeLong = ($this->atime + 11644473600) * 10000000;
            $ctimeLong = ($this->ctime + 11644473600) * 10000000;

            $serialize .= pack('Vvv', 0, 1, 8 * 3)
                . PackUtil::packLongLE($mtimeLong)
                . PackUtil::packLongLE($atimeLong)
                . PackUtil::packLongLE($ctimeLong);
        }
        return $serialize;
    }

    /**
     * @return int
     */
    public function getMtime()
    {
        return $this->mtime;
    }

    /**
     * @param int $mtime
     */
    public function setMtime($mtime)
    {
        $this->mtime = (int)$mtime;
    }

    /**
     * @return int
     */
    public function getAtime()
    {
        return $this->atime;
    }

    /**
     * @param int $atime
     */
    public function setAtime($atime)
    {
        $this->atime = (int)$atime;
    }

    /**
     * @return int
     */
    public function getCtime()
    {
        return $this->ctime;
    }

    /**
     * @param int $ctime
     */
    public function setCtime($ctime)
    {
        $this->ctime = (int)$ctime;
    }
}
