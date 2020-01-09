<?php

namespace PhpZip\Constants;

/**
 * Interface ZipOptions.
 */
interface ZipOptions
{
    /**
     * Boolean option for store just file names (skip directory names).
     *
     * @var string
     */
    const STORE_ONLY_FILES = 'only_files';

    /** @var string */
    const COMPRESSION_METHOD = 'compression_method';

    /** @var string */
    const MODIFIED_TIME = 'mtime';

    /**
     * @var string
     *
     * @see DosCodePage::getCodePages()
     */
    const CHARSET = 'charset';
}
