<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Exception;

/**
 * Thrown to indicate a CRC32 mismatch between the declared value in the
 * Central File Header and the Data Descriptor or between the declared value
 * and the computed value from the decompressed data.
 *
 * The exception detail message is the name of the ZIP entry.
 */
class Crc32Exception extends ZipException
{
    /** Expected crc. */
    private int $expectedCrc;

    /** Actual crc. */
    private int $actualCrc;

    public function __construct(string $name, int $expected, int $actual)
    {
        parent::__construct(
            sprintf(
                '%s (expected CRC32 value 0x%x, but is actually 0x%x)',
                $name,
                $expected,
                $actual
            )
        );
        $this->expectedCrc = $expected;
        $this->actualCrc = $actual;
    }

    /**
     * Returns expected crc.
     */
    public function getExpectedCrc(): int
    {
        return $this->expectedCrc;
    }

    /**
     * Returns actual crc.
     */
    public function getActualCrc(): int
    {
        return $this->actualCrc;
    }
}
