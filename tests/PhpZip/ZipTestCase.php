<?php
namespace PhpZip;

/**
 * PHPUnit test case and helper methods.
 */
class ZipTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * Assert correct zip archive.
     *
     * @param $filename
     */
    public static function assertCorrectZipArchive($filename)
    {
        if (DIRECTORY_SEPARATOR !== '\\' && `which zip`) {
            exec("zip -T " . escapeshellarg($filename), $output, $returnCode);

            $output = implode(PHP_EOL, $output);

            self::assertEquals($returnCode, 0);
            self::assertNotContains('zip error', $output);
            self::assertContains(' OK', $output);
        }
    }

    /**
     * Assert correct empty zip archive.
     *
     * @param $filename
     */
    public static function assertCorrectEmptyZip($filename)
    {
        if (DIRECTORY_SEPARATOR !== '\\' && `which zipinfo`) {
            exec("zipinfo " . escapeshellarg($filename), $output, $returnCode);

            $output = implode(PHP_EOL, $output);

            self::assertContains('Empty zipfile', $output);
        }
        $actualEmptyZipData = pack('VVVVVv', ZipConstants::END_OF_CENTRAL_DIRECTORY_RECORD_SIG, 0, 0, 0, 0, 0);
        self::assertEquals(file_get_contents($filename), $actualEmptyZipData);
    }

    /**
     * @param string $filename
     * @return bool|null If null - can not install zipalign
     */
    public static function doZipAlignVerify($filename)
    {
        if (DIRECTORY_SEPARATOR !== '\\' && `which zipalign`) {
            exec("zipalign -c -v 4 " . escapeshellarg($filename), $output, $returnCode);
            return $returnCode === 0;
        } else {
            echo 'Can not find program "zipalign" for test' . PHP_EOL;
            fwrite(STDERR, 'Can not find program "zipalign" for test');
            return null;
        }
    }

}