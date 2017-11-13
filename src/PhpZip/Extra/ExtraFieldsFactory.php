<?php

namespace PhpZip\Extra;

use PhpZip\Exception\ZipException;
use PhpZip\Extra\Fields\DefaultExtraField;
use PhpZip\Extra\Fields\NtfsExtraField;
use PhpZip\Extra\Fields\WinZipAesEntryExtraField;
use PhpZip\Extra\Fields\Zip64ExtraField;
use PhpZip\Model\ZipEntry;

/**
 * Extra Fields Factory
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ExtraFieldsFactory
{
    /**
     * @var array|null
     */
    protected static $registry;

    private function __construct()
    {
    }

    /**
     * A static factory method which creates a new Extra Field based on the
     * given Header ID.
     * The returned Extra Field still requires proper initialization, for
     * example by calling ExtraField::readFrom.
     *
     * @param int $headerId An unsigned short integer (two bytes) which indicates
     *         the type of the returned Extra Field.
     * @return ExtraField A new Extra Field or null if not support header id.
     * @throws ZipException If headerId is out of range.
     */
    public static function create($headerId)
    {
        if (0x0000 > $headerId || $headerId > 0xffff) {
            throw new ZipException('headerId out of range');
        }

        /**
         * @var ExtraField $extraField
         */
        if (isset(self::getRegistry()[$headerId])) {
            $extraClassName = self::getRegistry()[$headerId];
            $extraField = new $extraClassName;
            if ($extraField::getHeaderId() !== $headerId) {
                throw new ZipException('Runtime error support headerId ' . $headerId);
            }
        } else {
            $extraField = new DefaultExtraField($headerId);
        }
        return $extraField;
    }

    /**
     * Registered extra field classes.
     *
     * @return array
     */
    protected static function getRegistry()
    {
        if (null === self::$registry) {
            self::$registry[WinZipAesEntryExtraField::getHeaderId()] = WinZipAesEntryExtraField::class;
            self::$registry[NtfsExtraField::getHeaderId()] = NtfsExtraField::class;
            self::$registry[Zip64ExtraField::getHeaderId()] = Zip64ExtraField::class;
        }
        return self::$registry;
    }

    /**
     * @return WinZipAesEntryExtraField
     */
    public static function createWinZipAesEntryExtra()
    {
        return new WinZipAesEntryExtraField();
    }

    /**
     * @return NtfsExtraField
     */
    public static function createNtfsExtra()
    {
        return new NtfsExtraField();
    }

    /**
     * @param ZipEntry $entry
     * @return Zip64ExtraField
     */
    public static function createZip64Extra(ZipEntry $entry)
    {
        return new Zip64ExtraField($entry);
    }
}
