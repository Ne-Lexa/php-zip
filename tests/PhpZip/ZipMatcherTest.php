<?php

namespace PhpZip;

use PHPUnit\Framework\TestCase;
use PhpZip\Model\ZipEntryMatcher;
use PhpZip\Model\ZipInfo;
use PhpZip\Util\CryptoUtil;

/**
 * @internal
 *
 * @small
 */
class ZipMatcherTest extends TestCase
{
    public function testMatcher()
    {
        $zipFile = new ZipFile();
        for ($i = 0; $i < 100; $i++) {
            $zipFile[$i] = $i;
        }

        $matcher = $zipFile->matcher();
        static::assertInstanceOf(ZipEntryMatcher::class, $matcher);

        static::assertInternalType('array', $matcher->getMatches());
        static::assertCount(0, $matcher);

        $matcher->add(1)->add(10)->add(20);
        static::assertCount(3, $matcher);
        static::assertEquals($matcher->getMatches(), ['1', '10', '20']);

        $matcher->delete();
        static::assertCount(97, $zipFile);
        static::assertCount(0, $matcher);

        $matcher->match('~^[2][1-5]|[3][6-9]|40$~s');
        static::assertCount(10, $matcher);
        $actualMatches = [
            '21', '22', '23', '24', '25',
            '36', '37', '38', '39', '40',
        ];
        static::assertSame($matcher->getMatches(), $actualMatches);
        $matcher->setPassword('qwerty');
        $info = $zipFile->getAllInfo();
        array_walk(
            $info,
            function (ZipInfo $zipInfo) use ($actualMatches) {
                $this->assertSame($zipInfo->isEncrypted(), \in_array($zipInfo->getName(), $actualMatches, true));
            }
        );

        $matcher->all();
        static::assertCount(\count($zipFile), $matcher);

        $expectedNames = [];
        $matcher->invoke(
            static function ($entryName) use (&$expectedNames) {
                $expectedNames[] = $entryName;
            }
        );
        static::assertSame($expectedNames, $matcher->getMatches());

        $zipFile->close();
    }

    public function testDocsExample()
    {
        $zipFile = new ZipFile();
        for ($i = 0; $i < 100; $i++) {
            $zipFile['file_' . $i . '.jpg'] = CryptoUtil::randomBytes(100);
        }

        $renameEntriesArray = [
            'file_10.jpg',
            'file_11.jpg',
            'file_12.jpg',
            'file_13.jpg',
            'file_14.jpg',
            'file_15.jpg',
            'file_16.jpg',
            'file_17.jpg',
            'file_18.jpg',
            'file_19.jpg',
            'file_50.jpg',
            'file_51.jpg',
            'file_52.jpg',
            'file_53.jpg',
            'file_54.jpg',
            'file_55.jpg',
            'file_56.jpg',
            'file_57.jpg',
            'file_58.jpg',
            'file_59.jpg',
        ];

        foreach ($renameEntriesArray as $name) {
            static::assertTrue(isset($zipFile[$name]));
        }

        $matcher = $zipFile->matcher();
        $matcher->match('~^file_(1|5)\d+~');
        static::assertSame($matcher->getMatches(), $renameEntriesArray);

        $matcher->invoke(
            static function ($entryName) use ($zipFile) {
                $newName = preg_replace('~\.(jpe?g)$~i', '.no_optimize.$1', $entryName);
                $zipFile->rename($entryName, $newName);
            }
        );

        foreach ($renameEntriesArray as $name) {
            static::assertFalse(isset($zipFile[$name]));

            $pathInfo = pathinfo($name);
            $newName = $pathInfo['filename'] . '.no_optimize.' . $pathInfo['extension'];
            static::assertTrue(isset($zipFile[$newName]));
        }

        $zipFile->close();
    }
}
