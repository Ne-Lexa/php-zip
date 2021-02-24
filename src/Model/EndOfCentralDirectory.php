<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Model;

/**
 * End of Central Directory.
 */
class EndOfCentralDirectory
{
    /** @var int Count files. */
    private int $entryCount;

    /** @var int Central Directory Offset. */
    private int $cdOffset;

    private int $cdSize;

    /** @var string|null The archive comment. */
    private ?string $comment;

    /** @var bool Zip64 extension */
    private bool $zip64;

    public function __construct(int $entryCount, int $cdOffset, int $cdSize, bool $zip64, ?string $comment = null)
    {
        $this->entryCount = $entryCount;
        $this->cdOffset = $cdOffset;
        $this->cdSize = $cdSize;
        $this->zip64 = $zip64;
        $this->comment = $comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getEntryCount(): int
    {
        return $this->entryCount;
    }

    public function getCdOffset(): int
    {
        return $this->cdOffset;
    }

    public function getCdSize(): int
    {
        return $this->cdSize;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function isZip64(): bool
    {
        return $this->zip64;
    }
}
