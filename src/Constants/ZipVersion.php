<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Constants;

/**
 * Version needed to extract or software version.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT Section 4.4.3
 */
interface ZipVersion
{
    /** @var int 1.0 - Default value */
    public const v10_DEFAULT_MIN = 10;

    /** @var int 1.1 - File is a volume label */
    public const v11_FILE_VOLUME_LABEL = 11;

    /**
     * 2.0 - File is a folder (directory)
     * 2.0 - File is compressed using Deflate compression
     * 2.0 - File is encrypted using traditional PKWARE encryption.
     *
     * @var int
     */
    public const v20_DEFLATED_FOLDER_ZIPCRYPTO = 20;

    /** @var int 2.1 - File is compressed using Deflate64(tm) */
    public const v21_DEFLATED64 = 21;

    /** @var int 2.5 - File is compressed using PKWARE DCL Implode */
    public const v25_IMPLODED = 25;

    /** @var int 2.7 - File is a patch data set */
    public const v27_PATCH_DATA = 27;

    /** @var int 4.5 - File uses ZIP64 format extensions */
    public const v45_ZIP64_EXT = 45;

    /** @var int 4.6 - File is compressed using BZIP2 compression */
    public const v46_BZIP2 = 46;

    /**
     * 5.0 - File is encrypted using DES
     * 5.0 - File is encrypted using 3DES
     * 5.0 - File is encrypted using original RC2 encryption
     * 5.0 - File is encrypted using RC4 encryption.
     *
     * @var int
     */
    public const v50_ENCR_DES_3DES_RC2_ORIG_RC4 = 50;

    /**
     * 5.1 - File is encrypted using AES encryption
     * 5.1 - File is encrypted using corrected RC2 encryption**.
     *
     * @var int
     */
    public const v51_ENCR_AES_RC2_CORRECT = 51;

    /** @var int 5.2 - File is encrypted using corrected RC2-64 encryption** */
    public const v52_ENCR_RC2_64_CORRECT = 52;

    /** @var int 6.1 - File is encrypted using non-OAEP key wrapping*** */
    public const v61_ENCR_NON_OAE_KEY_WRAP = 61;

    /** @var int 6.2 - Central directory encryption */
    public const v62_ENCR_CENTRAL_DIR = 62;

    /**
     * 6.3 - File is compressed using LZMA
     * 6.3 - File is compressed using PPMd+
     * 6.3 - File is encrypted using Blowfish
     * 6.3 - File is encrypted using Twofish.
     *
     * @var int
     */
    public const v63_LZMA_PPMD_BLOWFISH_TWOFISH = 63;
}
