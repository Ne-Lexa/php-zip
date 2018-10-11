<?php

namespace PhpZip\Model;

/**
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
class ZipEntryMatcher implements \Countable
{
    /**
     * @var ZipModel
     */
    protected $zipModel;

    /**
     * @var array
     */
    protected $matches = [];

    /**
     * ZipEntryMatcher constructor.
     * @param ZipModel $zipModel
     */
    public function __construct(ZipModel $zipModel)
    {
        $this->zipModel = $zipModel;
    }

    /**
     * @param string|array $entries
     * @return ZipEntryMatcher
     */
    public function add($entries)
    {
        $entries = (array)$entries;
        $entries = array_map(function ($entry) {
            return $entry instanceof ZipEntry ? $entry->getName() : $entry;
        }, $entries);
        $this->matches = array_unique(
            array_merge(
                $this->matches,
                array_keys(
                    array_intersect_key(
                        $this->zipModel->getEntries(),
                        array_flip($entries)
                    )
                )
            )
        );
        return $this;
    }

    /**
     * @param string $regexp
     * @return ZipEntryMatcher
     */
    public function match($regexp)
    {
        array_walk($this->zipModel->getEntries(), function (
            /** @noinspection PhpUnusedParameterInspection */
            $entry,
            $entryName
        ) use ($regexp) {
            if (preg_match($regexp, $entryName)) {
                $this->matches[] = $entryName;
            }
        });
        $this->matches = array_unique($this->matches);
        return $this;
    }

    /**
     * @return ZipEntryMatcher
     */
    public function all()
    {
        $this->matches = array_keys($this->zipModel->getEntries());
        return $this;
    }

    /**
     * Callable function for all select entries.
     *
     * Callable function signature:
     * function(string $entryName){}
     *
     * @param callable $callable
     */
    public function invoke(callable $callable)
    {
        if (!empty($this->matches)) {
            array_walk($this->matches, function ($entryName) use ($callable) {
                call_user_func($callable, $entryName);
            });
        }
    }

    /**
     * @return array
     */
    public function getMatches()
    {
        return $this->matches;
    }

    public function delete()
    {
        array_walk($this->matches, function ($entry) {
            $this->zipModel->deleteEntry($entry);
        });
        $this->matches = [];
    }

    /**
     * @param string|null $password
     * @param int|null $encryptionMethod
     */
    public function setPassword($password, $encryptionMethod = null)
    {
        array_walk($this->matches, function ($entry) use ($password, $encryptionMethod) {
            $entry = $this->zipModel->getEntry($entry);
            if (!$entry->isDirectory()) {
                $this->zipModel->getEntryForChanges($entry)->setPassword($password, $encryptionMethod);
            }
        });
    }

    /**
     * @param int $encryptionMethod
     */
    public function setEncryptionMethod($encryptionMethod)
    {
        array_walk($this->matches, function ($entry) use ($encryptionMethod) {
            $entry = $this->zipModel->getEntry($entry);
            if (!$entry->isDirectory()) {
                $this->zipModel->getEntryForChanges($entry)->setEncryptionMethod($encryptionMethod);
            }
        });
    }

    public function disableEncryption()
    {
        array_walk($this->matches, function ($entry) {
            $entry = $this->zipModel->getEntry($entry);
            if (!$entry->isDirectory()) {
                $entry = $this->zipModel->getEntryForChanges($entry);
                $entry->disableEncryption();
            }
        });
    }

    /**
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count()
    {
        return count($this->matches);
    }
}
