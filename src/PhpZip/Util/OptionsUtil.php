<?php
namespace PhpZip\Util;

class OptionsUtil
{

    /**
     * @param $key
     * @param $options
     * @return null
     */
    public static function byKey($key, $options)
    {
        if (!array_key_exists($key, $options)) {
            return null;
        }

        return $options[$key];
    }

}