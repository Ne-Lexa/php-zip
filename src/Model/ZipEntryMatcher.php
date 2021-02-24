<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Model;

use PhpZip\Exception\ZipEntryNotFoundException;

class ZipEntryMatcher implements \Countable
{
    protected ZipContainer $zipContainer;

    protected array $matches = [];

    public function __construct(ZipContainer $zipContainer)
    {
        $this->zipContainer = $zipContainer;
    }

    /**
     * @param string|ZipEntry|string[]|ZipEntry[] $entries
     *
     * @return ZipEntryMatcher
     */
    public function add($entries): self
    {
        $entries = (array) $entries;
        $entries = array_map(
            static fn ($entry) => $entry instanceof ZipEntry ? $entry->getName() : (string) $entry,
            $entries
        );
        $this->matches = array_values(
            array_map(
                'strval',
                array_unique(
                    array_merge(
                        $this->matches,
                        array_keys(
                            array_intersect_key(
                                $this->zipContainer->getEntries(),
                                array_flip($entries)
                            )
                        )
                    )
                )
            )
        );

        return $this;
    }

    /**
     * @return ZipEntryMatcher
     * @noinspection PhpUnusedParameterInspection
     */
    public function match(string $regexp): self
    {
        array_walk(
            $this->zipContainer->getEntries(),
            function (ZipEntry $entry, string $entryName) use ($regexp): void {
                if (preg_match($regexp, $entryName)) {
                    $this->matches[] = $entryName;
                }
            }
        );
        $this->matches = array_unique($this->matches);

        return $this;
    }

    /**
     * @return ZipEntryMatcher
     */
    public function all(): self
    {
        $this->matches = array_map(
            'strval',
            array_keys($this->zipContainer->getEntries())
        );

        return $this;
    }

    /**
     * Callable function for all select entries.
     *
     * Callable function signature:
     * function(string $entryName){}
     */
    public function invoke(callable $callable): void
    {
        if (!empty($this->matches)) {
            array_walk(
                $this->matches,
                /** @param string $entryName */
                static function (string $entryName) use ($callable): void {
                    $callable($entryName);
                }
            );
        }
    }

    public function getMatches(): array
    {
        return $this->matches;
    }

    public function delete(): void
    {
        array_walk(
            $this->matches,
            /** @param string $entryName */
            function (string $entryName): void {
                $this->zipContainer->deleteEntry($entryName);
            }
        );
        $this->matches = [];
    }

    /**
     * @param ?string $password
     * @param ?int    $encryptionMethod
     *
     * @throws ZipEntryNotFoundException
     */
    public function setPassword(?string $password, ?int $encryptionMethod = null): void
    {
        array_walk(
            $this->matches,
            /** @param string $entryName */
            function (string $entryName) use ($password, $encryptionMethod): void {
                $entry = $this->zipContainer->getEntry($entryName);

                if (!$entry->isDirectory()) {
                    $entry->setPassword($password, $encryptionMethod);
                }
            }
        );
    }

    /**
     * @throws ZipEntryNotFoundException
     */
    public function setEncryptionMethod(int $encryptionMethod): void
    {
        array_walk(
            $this->matches,
            /** @param string $entryName */
            function (string $entryName) use ($encryptionMethod): void {
                $entry = $this->zipContainer->getEntry($entryName);

                if (!$entry->isDirectory()) {
                    $entry->setEncryptionMethod($encryptionMethod);
                }
            }
        );
    }

    /**
     * @throws ZipEntryNotFoundException
     */
    public function disableEncryption(): void
    {
        array_walk(
            $this->matches,
            function (string $entryName): void {
                $entry = $this->zipContainer->getEntry($entryName);

                if (!$entry->isDirectory()) {
                    $entry->disableEncryption();
                }
            }
        );
    }

    /**
     * Count elements of an object.
     *
     * @see http://php.net/manual/en/countable.count.php
     *
     * @return int the custom count as an integer
     */
    public function count(): int
    {
        return \count($this->matches);
    }
}
