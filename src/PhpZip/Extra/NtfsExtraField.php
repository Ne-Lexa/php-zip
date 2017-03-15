<?php
namespace PhpZip\Extra;

use PhpZip\Exception\ZipException;
use PhpZip\Util\PackUtil;

/**
 * NTFS Extra Field
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class NtfsExtraField extends ExtraField
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
     * @var string
     */
    private $rawData = "";

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
     * Returns the Data Size of this Extra Field.
     * The Data Size is an unsigned short integer (two bytes)
     * which indicates the length of the Data Block in bytes and does not
     * include its own size in this Extra Field.
     * This property may be initialized by calling ExtraField::readFrom.
     *
     * @return int The size of the Data Block in bytes
     *         or 0 if unknown.
     */
    public function getDataSize()
    {
        return 8 * 4 + strlen($this->rawData);
    }

    /**
     * Initializes this Extra Field by deserializing a Data Block of
     * size bytes $size from the resource $handle at the zero based offset $off.
     *
     * @param resource $handle
     * @param int $off Offset bytes
     * @param int $size Size
     * @throws ZipException If size out of range
     */
    public function readFrom($handle, $off, $size)
    {
        if (0x0000 > $size || $size > 0xffff) {
            throw new ZipException('size out of range');
        }
        if ($size > 0) {
            $off += 4;
            fseek($handle, $off, SEEK_SET);

            $unpack = unpack('vtag/vsizeAttr', fread($handle, 4));
            if (24 === $unpack['sizeAttr']) {
                $tagData = fread($handle, $unpack['sizeAttr']);

                $this->mtime = PackUtil::unpackLongLE(substr($tagData, 0, 8)) / 10000000 - 11644473600;
                $this->atime = PackUtil::unpackLongLE(substr($tagData, 8, 8)) / 10000000 - 11644473600;
                $this->ctime = PackUtil::unpackLongLE(substr($tagData, 16, 8)) / 10000000 - 11644473600;
            }
            $off += $unpack['sizeAttr'];

            if ($size > $off) {
                $this->rawData .= fread($handle, $size - $off);
            }
        }
    }

    /**
     * Serializes a Data Block of ExtraField::getDataSize bytes to the
     * resource $handle at the zero based offset $off.
     *
     * @param resource $handle
     * @param int $off Offset bytes
     */
    public function writeTo($handle, $off)
    {
        if (null !== $this->mtime && null !== $this->atime && null !== $this->ctime) {
            fseek($handle, $off, SEEK_SET);
            fwrite($handle, pack('Vvv', 0, 1, 8 * 3 + strlen($this->rawData)));
            $mtimeLong = ($this->mtime + 11644473600) * 10000000;
            fwrite($handle, PackUtil::packLongLE($mtimeLong));
            $atimeLong = ($this->atime + 11644473600) * 10000000;
            fwrite($handle, PackUtil::packLongLE($atimeLong));
            $ctimeLong = ($this->ctime + 11644473600) * 10000000;
            fwrite($handle, PackUtil::packLongLE($ctimeLong));
            if (!empty($this->rawData)) {
                fwrite($handle, $this->rawData);
            }
        }
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