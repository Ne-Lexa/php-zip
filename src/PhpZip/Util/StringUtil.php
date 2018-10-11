<?php

namespace PhpZip\Util;

/**
 * String Util
 */
class StringUtil
{

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function startsWith($haystack, $needle)
    {
        return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function endsWith($haystack, $needle)
    {
        return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0
                && strpos($haystack, $needle, $temp) !== false);
    }

    /**
     * @param string $str
     * @return string
     */
    public static function cp866toUtf8($str)
    {
        if (function_exists('iconv')) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            return iconv('CP866', 'UTF-8//IGNORE', $str);
        } elseif (function_exists('mb_convert_encoding')) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            return mb_convert_encoding($str, 'UTF-8', 'CP866');
        } elseif (class_exists('UConverter')) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            $converter = new \UConverter('UTF-8', 'CP866');
            return $converter->convert($str, false);
        } else {
            static $cp866Utf8Pairs;
            if (empty($cp866Utf8Pairs)) {
                $cp866Utf8Pairs = require __DIR__ . '/encodings/cp866-utf8.php';
            }
            return strtr($str, $cp866Utf8Pairs);
        }
    }
}
