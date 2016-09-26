<?php
namespace PhpZip;

/**
 * Constants for ZIP files.
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
interface ZipConstants
{
    /** Local File Header signature. */
    const LOCAL_FILE_HEADER_SIG = 0x04034B50;

    /** Data Descriptor signature. */
    const DATA_DESCRIPTOR_SIG = 0x08074B50;

    /** Central File Header signature. */
    const CENTRAL_FILE_HEADER_SIG = 0x02014B50;

    /** Zip64 End Of Central Directory Record. */
    const ZIP64_END_OF_CENTRAL_DIRECTORY_RECORD_SIG = 0x06064B50;

    /** Zip64 End Of Central Directory Locator. */
    const ZIP64_END_OF_CENTRAL_DIRECTORY_LOCATOR_SIG = 0x07064B50;

    /** End Of Central Directory Record signature. */
    const END_OF_CENTRAL_DIRECTORY_RECORD_SIG = 0x06054B50;

    /**
     * The minimum length of the Local File Header record.
     *
     * local file header signature      4
     * version needed to extract        2
     * general purpose bit flag         2
     * compression method               2
     * last mod file time               2
     * last mod file date               2
     * crc-32                           4
     * compressed size                  4
     * uncompressed size                4
     * file name length                 2
     * extra field length               2
     */
    const LOCAL_FILE_HEADER_MIN_LEN = 30;

    /**
     * The minimum length of the End Of Central Directory Record.
     *
     * end of central dir signature    4
     * number of this disk             2
     * number of the disk with the
     * start of the central directory  2
     * total number of entries in the
     * central directory on this disk  2
     * total number of entries in
     * the central directory           2
     * size of the central directory   4
     * offset of start of central      *
     * directory with respect to       *
     * the starting disk number        4
     * zipfile comment length          2
     */
    const END_OF_CENTRAL_DIRECTORY_RECORD_MIN_LEN = 22;

    /**
     * The length of the Zip64 End Of Central Directory Locator.
     * zip64 end of central dir locator
     * signature                       4
     * number of the disk with the
     * start of the zip64 end of
     * central directory               4
     * relative offset of the zip64
     * end of central directory record 8
     * total number of disks           4
     */
    const ZIP64_END_OF_CENTRAL_DIRECTORY_LOCATOR_LEN = 20;

    /**
     * The minimum length of the Zip64 End Of Central Directory Record.
     *
     * zip64 end of central dir
     * signature                        4
     * size of zip64 end of central
     * directory record                 8
     * version made by                  2
     * version needed to extract        2
     * number of this disk              4
     * number of the disk with the
     * start of the central directory   4
     * total number of entries in the
     * central directory on this disk   8
     * total number of entries in
     * the central directory            8
     * size of the central directory    8
     * offset of start of central
     * directory with respect to
     * the starting disk number         8
     */
    const ZIP64_END_OF_CENTRAL_DIRECTORY_RECORD_MIN_LEN = 56;

    /**
     * Local File Header signature      4
     * Version Needed To Extract        2
     * General Purpose Bit Flags        2
     * Compression Method               2
     * Last Mod File Time               2
     * Last Mod File Date               2
     * CRC-32                           4
     * Compressed Size                  4
     * Uncompressed Size                4
     */
    const LOCAL_FILE_HEADER_FILE_NAME_LENGTH_POS = 26;

}