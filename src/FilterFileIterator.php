<?php
namespace Nelexa\Zip;

class FilterFileIterator extends \FilterIterator
{
    private $ignoreFiles;
    private static $ignoreAlways = array('..');

    /**
     * @param \Iterator $iterator
     * @param array $ignoreFiles
     */
    public function __construct(\Iterator $iterator, array $ignoreFiles)
    {
        parent::__construct($iterator);
        $this->ignoreFiles = array_merge(self::$ignoreAlways, $ignoreFiles);
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Check whether the current element of the iterator is acceptable
     * @link http://php.net/manual/en/filteriterator.accept.php
     * @return bool true if the current element is acceptable, otherwise false.
     */
    public function accept()
    {
        /**
         * @var \SplFileInfo $value
         */
        $value = $this->current();
        $pathName = $value->getRealPath();
        foreach ($this->ignoreFiles AS $ignoreFile) {
            if ($this->endsWith($pathName, $ignoreFile)) {
                return false;
            }
        }
        return true;
    }

    function endsWith($haystack, $needle)
    {
        // search forward starting from end minus needle length characters
        return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
    }
}