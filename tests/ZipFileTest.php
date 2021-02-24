<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Tests;

use GuzzleHttp\Psr7\Response;
use PhpZip\Constants\ZipCompressionLevel;
use PhpZip\Constants\ZipCompressionMethod;
use PhpZip\Constants\ZipPlatform;
use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\ZipEntryNotFoundException;
use PhpZip\Exception\ZipException;
use PhpZip\Exception\ZipUnsupportMethodException;
use PhpZip\Model\Data\ZipFileData;
use PhpZip\Model\ZipEntry;
use PhpZip\Util\FilesUtil;
use PhpZip\ZipFile;

/**
 * ZipFile test.
 *
 * @internal
 *
 * @small
 */
class ZipFileTest extends ZipTestCase
{
    /**
     * @throws ZipException
     */
    public function testOpenFileCantExists(): void
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage('does not exist');

        $zipFile = new ZipFile();
        $zipFile->openFile(uniqid('', false));
    }

    /**
     * @throws ZipException
     */
    public function testOpenFileCantOpen(): void
    {
        static::skipTestForWindows();
        static::skipTestForRootUser();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Permission denied');

        static::assertNotFalse(file_put_contents($this->outputFilename, 'content'));
        static::assertTrue(chmod($this->outputFilename, 0222));

        $zipFile = new ZipFile();
        $zipFile->openFile($this->outputFilename);
    }

    /**
     * @throws ZipException
     */
    public function testOpenFileEmptyFile(): void
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage('Corrupt zip file');

        static::assertNotFalse(touch($this->outputFilename));
        $zipFile = new ZipFile();
        $zipFile->openFile($this->outputFilename);
    }

    /**
     * @throws ZipException
     * @throws \Exception
     */
    public function testOpenFileInvalidZip(): void
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage('Invalid zip file. The end of the central directory could not be found.');

        static::assertNotFalse(file_put_contents($this->outputFilename, random_bytes(255)));
        $zipFile = new ZipFile();
        $zipFile->openFile($this->outputFilename);
    }

    /**
     * @throws ZipException
     */
    public function testOpenFromStringEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty string passed');

        $zipFile = new ZipFile();
        $zipFile->openFromString('');
    }

    /**
     * @throws ZipException
     * @throws \Exception
     */
    public function testOpenFromStringInvalidZip(): void
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage('Invalid zip file. The end of the central directory could not be found.');

        $zipFile = new ZipFile();
        $zipFile->openFromString(random_bytes(255));
    }

    /**
     * @throws ZipException
     */
    public function testOpenFromString(): void
    {
        $zipFile = new ZipFile();
        $zipFile->addFromString('file', 'content');
        $zipFile['file2'] = 'content 2';
        $zipContents = $zipFile->outputAsString();
        $zipFile->close();

        $zipFile->openFromString($zipContents);
        static::assertSame($zipFile->count(), 2);
        static::assertTrue(isset($zipFile['file']));
        static::assertTrue(isset($zipFile['file2']));
        static::assertSame($zipFile['file'], 'content');
        static::assertSame($zipFile['file2'], 'content 2');
        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testOpenFromStreamNullStream(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream must be a resource');

        $zipFile = new ZipFile();
        $zipFile->openFromStream(null);
    }

    /**
     * @throws ZipException
     */
    public function testOpenFromStreamInvalidResourceType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream must be a resource');

        $zipFile = new ZipFile();
        /** @noinspection PhpParamsInspection */
        $zipFile->openFromStream('stream resource');
    }

    /**
     * @throws ZipException
     */
    public function testOpenFromStreamInvalidResourceType3(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Directory stream not supported');

        $zipFile = new ZipFile();
        $zipFile->openFromStream(opendir(__DIR__));
    }

    /**
     * @throws ZipException
     * @noinspection PhpUsageOfSilenceOperatorInspection
     * @noinspection NestedPositiveIfStatementsInspection
     */
    public function testOpenFromStreamNoSeekable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The stream wrapper type "http" is not supported');

        if (!$fp = @fopen('http://localhost', 'rb')) {
            if (!$fp = @fopen('http://example.org', 'rb')) {
                static::markTestSkipped('not connected to localhost or remote host');
            }
        }

        $zipFile = new ZipFile();
        $zipFile->openFromStream($fp);
    }

    /**
     * @throws ZipException
     */
    public function testOpenFromStreamEmptyContents(): void
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage('Corrupt zip file');

        $fp = fopen($this->outputFilename, 'w+b');
        $zipFile = new ZipFile();
        $zipFile->openFromStream($fp);
    }

    /**
     * @throws ZipException
     * @throws \Exception
     */
    public function testOpenFromStreamInvalidZip(): void
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage('Invalid zip file. The end of the central directory could not be found.');

        $fp = fopen($this->outputFilename, 'w+b');
        fwrite($fp, random_bytes(255));
        $zipFile = new ZipFile();
        $zipFile->openFromStream($fp);
    }

    /**
     * @throws ZipException
     */
    public function testOpenFromStream(): void
    {
        $zipFile = new ZipFile();
        $zipFile
            ->addFromString('file', 'content')
            ->saveAsFile($this->outputFilename)
            ->close()
        ;

        $handle = fopen($this->outputFilename, 'rb');
        $zipFile->openFromStream($handle);
        static::assertSame($zipFile->count(), 1);
        static::assertTrue(isset($zipFile['file']));
        static::assertSame($zipFile['file'], 'content');
        $zipFile->close();
    }

    /**
     * Test create, open and extract empty archive.
     *
     * @throws ZipException
     */
    public function testEmptyArchive(): void
    {
        $zipFile = new ZipFile();
        $zipFile
            ->saveAsFile($this->outputFilename)
            ->close()
        ;

        static::assertCorrectEmptyZip($this->outputFilename);
        static::assertTrue(mkdir($this->outputDirname, 0755, true));

        $zipFile->openFile($this->outputFilename);
        static::assertSame($zipFile->count(), 0);
        $zipFile
            ->extractTo($this->outputDirname)
            ->close()
        ;

        static::assertTrue(FilesUtil::isEmptyDir($this->outputDirname));
    }

    /**
     * No modified archive.
     *
     * @throws ZipException
     *
     * @see ZipOutputFile::create()
     */
    public function testNoModifiedArchive(): void
    {
        static::assertTrue(mkdir($this->outputDirname, 0755, true));

        $fileActual = $this->outputDirname . \DIRECTORY_SEPARATOR . 'file_actual.zip';
        $fileExpected = $this->outputDirname . \DIRECTORY_SEPARATOR . 'file_expected.zip';

        $zipFile = new ZipFile();
        $zipFile->addDirRecursive(__DIR__ . '/../src');
        $sourceCount = $zipFile->count();
        static::assertTrue($sourceCount > 0);
        $zipFile
            ->saveAsFile($fileActual)
            ->close()
        ;
        static::assertCorrectZipArchive($fileActual);

        $zipFile
            ->openFile($fileActual)
            ->saveAsFile($fileExpected)
        ;
        static::assertCorrectZipArchive($fileExpected);

        $zipFileExpected = new ZipFile();
        $zipFileExpected->openFile($fileExpected);

        static::assertSame($zipFile->count(), $sourceCount);
        static::assertSame($zipFileExpected->count(), $zipFile->count());
        static::assertSame($zipFileExpected->getListFiles(), $zipFile->getListFiles());

        foreach ($zipFile as $entryName => $content) {
            static::assertSame($zipFileExpected[$entryName], $content);
        }

        $zipFileExpected->close();
        $zipFile->close();
    }

    /**
     * Create archive and add files.
     *
     * @throws ZipException
     *
     * @see ZipOutputFile::addFromFile()
     * @see ZipOutputFile::addFromStream()
     * @see ZipFile::getEntryContents()
     * @see ZipOutputFile::addFromString()
     */
    public function testCreateArchiveAndAddFiles(): void
    {
        $outputFromString = file_get_contents(__FILE__);
        $outputFromString2 = file_get_contents(\dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'README.md');
        $outputFromFile = file_get_contents(\dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'phpunit.xml');
        $outputFromStream = file_get_contents(\dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'composer.json');

        $filenameFromString = basename(__FILE__);
        $filenameFromString2 = 'test_file.txt';
        $filenameFromFile = 'data/test file.txt';
        $filenameFromStream = 'data/ডিরেক্টরি/αρχείο.json';
        $emptyDirName = 'empty dir/пустой каталог/空目錄/ไดเรกทอรีที่ว่างเปล่า/';
        $emptyDirName2 = 'empty dir/пустой каталог/';
        $emptyDirName3 = 'empty dir/пустой каталог/ещё один пустой каталог/';

        $tempFile = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($tempFile, $outputFromFile);

        $tempStream = tmpfile();
        fwrite($tempStream, $outputFromStream);

        $zipFile = new ZipFile();
        $zipFile
            ->addFromString($filenameFromString, $outputFromString)
            ->addFile($tempFile, $filenameFromFile)
            ->addFromStream($tempStream, $filenameFromStream)
            ->addEmptyDir($emptyDirName)
        ;
        $zipFile[$filenameFromString2] = $outputFromString2;
        $zipFile[$emptyDirName2] = null;
        $zipFile[$emptyDirName3] = 'this content ignoring';
        static::assertSame(\count($zipFile), 7);
        $zipFile
            ->saveAsFile($this->outputFilename)
            ->close()
        ;
        unlink($tempFile);

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertSame(\count($zipFile), 7);
        static::assertSame($zipFile[$filenameFromString], $outputFromString);
        static::assertSame($zipFile[$filenameFromFile], $outputFromFile);
        static::assertSame($zipFile[$filenameFromStream], $outputFromStream);
        static::assertSame($zipFile[$filenameFromString2], $outputFromString2);
        static::assertTrue(isset($zipFile[$emptyDirName]));
        static::assertTrue(isset($zipFile[$emptyDirName2]));
        static::assertTrue(isset($zipFile[$emptyDirName3]));
        static::assertTrue($zipFile->isDirectory($emptyDirName));
        static::assertTrue($zipFile->isDirectory($emptyDirName2));
        static::assertTrue($zipFile->isDirectory($emptyDirName3));

        $listFiles = $zipFile->getListFiles();
        static::assertSame($listFiles[0], $filenameFromString);
        static::assertSame($listFiles[1], $filenameFromFile);
        static::assertSame($listFiles[2], $filenameFromStream);
        static::assertSame($listFiles[3], $emptyDirName);
        static::assertSame($listFiles[4], $filenameFromString2);
        static::assertSame($listFiles[5], $emptyDirName2);
        static::assertSame($listFiles[6], $emptyDirName3);

        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testEmptyContent(): void
    {
        $zipFile = new ZipFile();
        $zipFile['file'] = '';
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $zipFile->openFile($this->outputFilename);
        static::assertSame($zipFile['file'], '');
        $zipFile->close();
    }

    /**
     * Test compression method from image file.
     *
     * @throws ZipException
     */
    public function testCompressionMethodFromImageMimeType(): void
    {
        if (!\function_exists('mime_content_type')) {
            static::markTestSkipped('Function mime_content_type not exists');
        }
        $outputFilename = $this->outputFilename;
        $this->outputFilename .= '.gif';
        static::assertNotFalse(
            file_put_contents(
                $this->outputFilename,
                base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==', true)
            )
        );
        $basename = basename($this->outputFilename);

        $zipFile = new ZipFile();
        $zipFile->addFile($this->outputFilename, $basename);
        $zipFile->saveAsFile($outputFilename);
        unlink($this->outputFilename);
        $this->outputFilename = $outputFilename;

        $zipFile->openFile($this->outputFilename);
        $zipEntry = $zipFile->getEntry($basename);
        static::assertSame($zipEntry->getCompressionMethod(), ZipCompressionMethod::STORED);
        $zipFile->close();
    }

    /**
     * Rename zip entry name.
     *
     * @throws ZipException
     */
    public function testRename(): void
    {
        $oldName = basename(__FILE__);
        $newName = 'tests/' . $oldName;

        $zipFile = new ZipFile();
        $zipFile->addDir(__DIR__);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        $zipFile->rename($oldName, $newName);
        $zipFile->addFromString('file1.txt', 'content');
        $zipFile->addFromString('file2.txt', 'content');
        $zipFile->addFromString('file3.txt', 'content');
        $zipFile->rename('file1.txt', 'file_long_name.txt');
        $zipFile->rename('file2.txt', 'file4.txt');
        $zipFile->rename('file3.txt', 'fi.txt');
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertFalse(isset($zipFile[$oldName]));
        static::assertTrue(isset($zipFile[$newName]));
        static::assertFalse(isset($zipFile['file1.txt']));
        static::assertFalse(isset($zipFile['file2.txt']));
        static::assertFalse(isset($zipFile['file3.txt']));
        static::assertTrue(isset($zipFile['file_long_name.txt']));
        static::assertTrue(isset($zipFile['file4.txt']));
        static::assertTrue(isset($zipFile['fi.txt']));
        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testRenameEntryToExistsNewEntry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is exists');

        $zipFile = new ZipFile();
        $zipFile['file'] = 'content';
        $zipFile['file2'] = 'content 2';
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $zipFile = new ZipFile();
        $zipFile->openFile($this->outputFilename);
        $zipFile->rename('file2', 'file');
    }

    /**
     * @throws ZipException
     */
    public function testRenameEntryNotFound(): void
    {
        $this->expectException(ZipEntryNotFoundException::class);

        $zipFile = new ZipFile();
        $zipFile['file'] = 'content';
        $zipFile['file2'] = 'content 2';
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $zipFile = new ZipFile();
        $zipFile->openFile($this->outputFilename);
        $zipFile->rename('file2.bak', 'file3');
    }

    /**
     * Delete entry from name.
     *
     * @throws ZipException
     */
    public function testDeleteFromName(): void
    {
        $inputDir = \dirname(__DIR__) . \DIRECTORY_SEPARATOR;
        $deleteEntryName = 'composer.json';

        $zipFile = new ZipFile();
        $zipFile->addDir($inputDir);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        $zipFile->deleteFromName($deleteEntryName);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertFalse(isset($zipFile[$deleteEntryName]));
        $zipFile->close();
    }

    /**
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testDeleteNewEntry(): void
    {
        $zipFile = new ZipFile();
        $zipFile['entry1'] = '';
        $zipFile['entry2'] = '';
        $zipFile->deleteFromName('entry2');
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $zipFile->openFile($this->outputFilename);
        static::assertSame(\count($zipFile), 1);
        static::assertTrue(isset($zipFile['entry1']));
        static::assertFalse(isset($zipFile['entry2']));
        $zipFile->close();
    }

    /**
     * @throws ZipEntryNotFoundException
     */
    public function testDeleteFromNameNotFoundEntry(): void
    {
        $this->expectException(ZipEntryNotFoundException::class);

        $zipFile = new ZipFile();
        $zipFile->deleteFromName('entry');
    }

    public function testCatchNotFoundEntry(): void
    {
        $entryName = 'entry';
        $zipFile = new ZipFile();

        try {
            $zipFile->getEntry($entryName);
        } catch (ZipEntryNotFoundException $e) {
            static::assertSame($e->getEntryName(), $entryName);
        }
    }

    /**
     * Delete zip entries from glob pattern.
     *
     * @throws ZipException
     */
    public function testDeleteFromGlob(): void
    {
        $inputDir = \dirname(__DIR__);

        $zipFile = new ZipFile();
        $zipFile->addFilesFromGlobRecursive($inputDir, '**.{xml,json,md}');
        static::assertTrue(isset($zipFile['composer.json']));
        static::assertTrue(isset($zipFile['phpunit.xml']));
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertTrue(isset($zipFile['composer.json']));
        static::assertTrue(isset($zipFile['phpunit.xml']));
        $zipFile->deleteFromGlob('**.{xml,json}');
        static::assertFalse(isset($zipFile['composer.json']));
        static::assertFalse(isset($zipFile['phpunit.xml']));
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertTrue($zipFile->count() > 0);

        foreach ($zipFile->getListFiles() as $name) {
            static::assertStringEndsWith('.md', $name);
        }

        $zipFile->close();
    }

    public function testDeleteFromGlobFailEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The glob pattern is not specified');

        $zipFile = new ZipFile();
        $zipFile->deleteFromGlob('');
    }

    /**
     * Delete entries from regex pattern.
     *
     * @throws ZipException
     */
    public function testDeleteFromRegex(): void
    {
        $inputDir = \dirname(__DIR__);

        $zipFile = new ZipFile();
        $zipFile->addFilesFromRegexRecursive($inputDir, '~\.(xml|json)$~i', 'Path');
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        $zipFile->deleteFromRegex('~\.(json)$~i');
        $zipFile->addFromString('test.txt', 'content');
        $zipFile->deleteFromRegex('~\.txt$~');
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertFalse(isset($zipFile['Path/composer.json']));
        static::assertFalse(isset($zipFile['Path/test.txt']));
        static::assertTrue(isset($zipFile['Path/phpunit.xml']));
        $zipFile->close();
    }

    public function testDeleteFromRegexFailEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The regex pattern is not specified');

        $zipFile = new ZipFile();
        $zipFile->deleteFromRegex('');
    }

    /**
     * Delete all entries.
     *
     * @throws ZipException
     */
    public function testDeleteAll(): void
    {
        $zipFile = new ZipFile();
        $zipFile->addDirRecursive(\dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'src');
        static::assertTrue($zipFile->count() > 0);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertTrue($zipFile->count() > 0);
        $zipFile->deleteAll();
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectEmptyZip($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertSame($zipFile->count(), 0);
        $zipFile->close();
    }

    /**
     * Test zip archive comment.
     *
     * @throws ZipException
     */
    public function testArchiveComment(): void
    {
        $comment = 'This zip file comment' . \PHP_EOL
            . 'Αυτό το σχόλιο αρχείο zip' . \PHP_EOL
            . 'Это комментарий zip архива' . \PHP_EOL
            . '這個ZIP文件註釋' . \PHP_EOL
            . 'ეს zip ფაილის კომენტარი' . \PHP_EOL
            . 'このzipファイルにコメント' . \PHP_EOL
            . 'ความคิดเห็นนี้ไฟล์ซิป';

        $zipFile = new ZipFile();
        $zipFile->setArchiveComment($comment);
        $zipFile->addFile(__FILE__);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertSame($zipFile->getArchiveComment(), $comment);
        $zipFile->setArchiveComment(/* null */); // remove archive comment
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        // check empty comment
        $zipFile->openFile($this->outputFilename);
        static::assertNull($zipFile->getArchiveComment());
        $zipFile->close();
    }

    /**
     * Test very long archive comment.
     */
    public function testVeryLongArchiveComment(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $comment = 'Very long comment' . \PHP_EOL
            . 'Очень длинный комментарий' . \PHP_EOL;
        $comment = str_repeat($comment, (int) ceil(0xffff / \strlen($comment)) + \strlen($comment) + 1);

        $zipFile = new ZipFile();
        $zipFile->setArchiveComment($comment);
    }

    /**
     * Test zip entry comment.
     *
     * @throws ZipException
     * @throws \Exception
     */
    public function testEntryComment(): void
    {
        $entries = [
            '文件1.txt' => [
                'data' => random_bytes(255),
                'comment' => '這是註釋的條目。',
            ],
            'file2.txt' => [
                'data' => random_bytes(255),
                'comment' => null,
            ],
            'file3.txt' => [
                'data' => random_bytes(255),
                'comment' => random_bytes(255),
            ],
            'file4.txt' => [
                'data' => random_bytes(255),
                'comment' => 'Комментарий файла',
            ],
            'file5.txt' => [
                'data' => random_bytes(255),
                'comment' => 'ไฟล์แสดงความคิดเห็น',
            ],
            'file6 emoji 🙍🏼.txt' => [
                'data' => random_bytes(255),
                'comment' => 'Emoji comment file - 😀 ⛈ ❤️ 🤴🏽',
            ],
        ];

        // create archive with entry comments
        $zipFile = new ZipFile();

        foreach ($entries as $entryName => $item) {
            $zipFile->addFromString($entryName, $item['data']);
            $zipFile->setEntryComment($entryName, $item['comment']);
        }
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        // check and modify comments
        $zipFile->openFile($this->outputFilename);

        foreach ($zipFile->getListFiles() as $entryName) {
            $entriesItem = $entries[$entryName];
            static::assertNotEmpty($entriesItem);
            static::assertSame($zipFile[$entryName], $entriesItem['data']);
            static::assertSame($zipFile->getEntryComment($entryName), (string) $entriesItem['comment']);
        }
        // modify comment
        $entries['file5.txt']['comment'] = random_bytes(256);
        $zipFile->setEntryComment('file5.txt', $entries['file5.txt']['comment']);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        // check modify comments
        $zipFile->openFile($this->outputFilename);

        foreach ($entries as $entryName => $entriesItem) {
            static::assertTrue(isset($zipFile[$entryName]));
            static::assertSame($zipFile->getEntryComment($entryName), (string) $entriesItem['comment']);
            static::assertSame($zipFile[$entryName], $entriesItem['data']);
        }
        $zipFile->close();
    }

    /**
     * Test zip entry very long comment.
     *
     * @throws ZipException
     */
    public function testVeryLongEntryComment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Comment too long');

        $comment = 'Very long comment' . \PHP_EOL
            . 'Очень длинный комментарий' . \PHP_EOL;
        $comment = str_repeat($comment, (int) ceil(0xffff / \strlen($comment)) + \strlen($comment) + 1);

        $zipFile = new ZipFile();
        $zipFile->addFile(__FILE__, 'test');
        $zipFile->setEntryComment('test', $comment);
    }

    /**
     * @throws ZipException
     */
    public function testSetEntryCommentNotFoundEntry(): void
    {
        $this->expectException(ZipEntryNotFoundException::class);

        $zipFile = new ZipFile();
        $zipFile->setEntryComment('test', 'comment');
    }

    /**
     * Test all available support compression methods.
     *
     * @throws ZipException
     * @throws \Exception
     */
    public function testCompressionMethod(): void
    {
        $entries = [
            1 => [
                'data' => random_bytes(255),
                'method' => ZipCompressionMethod::STORED,
            ],
            2 => [
                'data' => random_bytes(255),
                'method' => ZipCompressionMethod::DEFLATED,
            ],
        ];

        if (\extension_loaded('bz2')) {
            $entries[3] = [
                'data' => random_bytes(255),
                'method' => ZipCompressionMethod::BZIP2,
            ];
        }

        $zipFile = new ZipFile();

        foreach ($entries as $entryName => $item) {
            $entryName = (string) $entryName;
            $zipFile->addFromString($entryName, $item['data'], $item['method']);
        }
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        $zipFile->setCompressionLevel(ZipCompressionLevel::MAXIMUM);

        foreach ($zipFile->getEntries() as $entryName => $zipEntry) {
            $entryName = (string) $entryName;
            static::assertSame($zipFile[$entryName], $entries[$entryName]['data']);
            static::assertSame($zipEntry->getCompressionMethod(), $entries[$entryName]['method']);
        }
        $zipFile->close();
    }

    /**
     * @dataProvider provideInvalidCompressionLevels
     */
    public function testSetInvalidCompressionLevel(int $compressionLevel): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid compression level. Minimum level 1. Maximum level 9');

        $zipFile = new ZipFile();
        $zipFile['file 1'] = 'contents';
        $zipFile->setCompressionLevel($compressionLevel);
    }

    public function provideInvalidCompressionLevels(): array
    {
        return [
            [-10],
            [-2],
            [10],
            [0xffff],
        ];
    }

    /**
     * Test extract all files.
     *
     * @throws ZipException
     * @throws \Exception
     */
    public function testExtract(): void
    {
        $entries = [
            'test1.txt' => random_bytes(255),
            'test2.txt' => random_bytes(255),
            'test/test 2/test3.txt' => random_bytes(255),
            'test empty/dir/' => null,
        ];

        $zipFile = new ZipFile();

        foreach ($entries as $entryName => $contents) {
            if ($contents === null) {
                $zipFile->addEmptyDir($entryName);
            } else {
                $zipFile->addFromString($entryName, $contents);
            }
        }
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertTrue(mkdir($this->outputDirname, 0755, true));

        $zipFile->openFile($this->outputFilename);
        $zipFile->extractTo($this->outputDirname, null, [], $extractedEntries);

        foreach ($entries as $entryName => $contents) {
            $name = $entryName;

            if (\DIRECTORY_SEPARATOR === '\\') {
                $name = str_replace('/', '\\', $name);
            }
            $fullExtractedFilename = $this->outputDirname . \DIRECTORY_SEPARATOR . $name;

            static::assertTrue(
                isset($extractedEntries[$fullExtractedFilename]),
                'No extract info for ' . $fullExtractedFilename
            );

            if ($contents === null) {
                static::assertDirectoryExists($fullExtractedFilename);
                static::assertTrue(FilesUtil::isEmptyDir($fullExtractedFilename));
            } else {
                static::assertTrue(is_file($fullExtractedFilename));
                $contents = file_get_contents($fullExtractedFilename);
                static::assertSame($contents, $contents);
            }

            /** @var ZipEntry $entry */
            $entry = $extractedEntries[$fullExtractedFilename];
            static::assertSame($entry->getName(), $entryName);
        }
        $zipFile->close();
    }

    /**
     * Test extract some files.
     *
     * @throws ZipException
     * @throws \Exception
     */
    public function testExtractSomeFiles(): void
    {
        $entries = [
            'test1.txt' => random_bytes(255),
            'test2.txt' => random_bytes(255),
            'test3.txt' => random_bytes(255),
            'test4.txt' => random_bytes(255),
            'test5.txt' => random_bytes(255),
            'test/test/test.txt' => random_bytes(255),
            'test/test/test 2.txt' => random_bytes(255),
            'test empty/dir/' => null,
            'test empty/dir2/' => null,
        ];

        $extractEntries = [
            'test1.txt',
            'test3.txt',
            'test5.txt',
            'test/test/test 2.txt',
            'test empty/dir2/',
        ];

        static::assertTrue(mkdir($this->outputDirname, 0755, true));

        $zipFile = new ZipFile();
        $zipFile->addAll($entries);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $zipFile->openFile($this->outputFilename);
        $zipFile->extractTo($this->outputDirname, $extractEntries);

        foreach ($entries as $entryName => $value) {
            $fullExtractFilename = $this->outputDirname . \DIRECTORY_SEPARATOR . $entryName;

            if (\in_array($entryName, $extractEntries, true)) {
                if ($value === null) {
                    static::assertDirectoryExists($fullExtractFilename);
                    static::assertTrue(FilesUtil::isEmptyDir($fullExtractFilename));
                } else {
                    static::assertTrue(is_file($fullExtractFilename));
                    $contents = file_get_contents($fullExtractFilename);
                    static::assertEquals($contents, $value);
                }
            } elseif ($value === null) {
                static::assertDirectoryDoesNotExist($fullExtractFilename);
            } else {
                static::assertFalse(is_file($fullExtractFilename));
            }
        }
        static::assertFalse(is_file($this->outputDirname . \DIRECTORY_SEPARATOR . 'test/test/test.txt'));
        $zipFile->extractTo($this->outputDirname, 'test/test/test.txt');
        static::assertTrue(is_file($this->outputDirname . \DIRECTORY_SEPARATOR . 'test/test/test.txt'));

        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testExtractFail(): void
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage('not found');

        $zipFile = new ZipFile();
        $zipFile['file'] = 'content';
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $zipFile->openFile($this->outputFilename);
        $zipFile->extractTo('path/to/path');
    }

    /**
     * @throws ZipException
     */
    public function testExtractFail2(): void
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage('Destination is not directory');

        $zipFile = new ZipFile();
        $zipFile['file'] = 'content';
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $zipFile->openFile($this->outputFilename);
        $zipFile->extractTo($this->outputFilename);
    }

    /**
     * @throws ZipException
     */
    public function testExtractFail3(): void
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage('Destination is not writable directory');

        static::skipTestForRootUser();

        $zipFile = new ZipFile();
        $zipFile['file'] = 'content';
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertTrue(mkdir($this->outputDirname, 0444, true));
        static::assertTrue(chmod($this->outputDirname, 0444));

        $zipFile->openFile($this->outputFilename);
        $zipFile->extractTo($this->outputDirname);
    }

    /**
     * @noinspection OnlyWritesOnParameterInspection
     */
    public function testAddFromArrayAccessNullName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key must not be null, but must contain the name of the zip entry.');

        $zipFile = new ZipFile();
        $zipFile[null] = 'content';
    }

    /**
     * @noinspection OnlyWritesOnParameterInspection
     */
    public function testAddFromArrayAccessEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Key is empty, but must contain the name of the zip entry.');

        $zipFile = new ZipFile();
        $zipFile[''] = 'content';
    }

    /**
     * @throws ZipException
     */
    public function testAddFromStringUnsupportedMethod(): void
    {
        $this->expectException(ZipUnsupportMethodException::class);
        $this->expectExceptionMessage('Compression method 99 (AES Encryption) is not supported.');

        $zipFile = new ZipFile();
        $zipFile->addFromString('file', 'contents', ZipCompressionMethod::WINZIP_AES);
        $zipFile->outputAsString();
    }

    /**
     * @throws ZipException
     */
    public function testAddFromStringEmptyEntryName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty entry name');

        $zipFile = new ZipFile();
        $zipFile->addFromString('', 'contents');
    }

    /**
     * Test compression method from add string.
     *
     * @throws ZipException
     */
    public function testAddFromStringCompressionMethod(): void
    {
        $fileStored = sys_get_temp_dir() . '/zip-stored.txt';
        $fileDeflated = sys_get_temp_dir() . '/zip-deflated.txt';

        static::assertNotFalse(file_put_contents($fileStored, 'content'));
        static::assertNotFalse(file_put_contents($fileDeflated, str_repeat('content', 200)));

        $zipFile = new ZipFile();
        $zipFile->addFromString(basename($fileStored), file_get_contents($fileStored));
        $zipFile->addFromString(basename($fileDeflated), file_get_contents($fileDeflated));
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        unlink($fileStored);
        unlink($fileDeflated);

        $zipFile->openFile($this->outputFilename);
        $methodStored = $zipFile->getEntry(basename($fileStored))->getCompressionMethod();
        $methodDeflated = $zipFile->getEntry(basename($fileDeflated))->getCompressionMethod();
        static::assertSame($methodStored, ZipCompressionMethod::STORED);
        static::assertSame($methodDeflated, ZipCompressionMethod::DEFLATED);
        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testAddFromStreamInvalidResource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream is not resource');

        $zipFile = new ZipFile();
        /** @noinspection PhpParamsInspection */
        $zipFile->addFromStream('invalid resource', 'name');
    }

    /**
     * @throws ZipException
     */
    public function testAddFromStreamEmptyEntryName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty entry name');

        $handle = fopen(__FILE__, 'rb');

        $zipFile = new ZipFile();
        $zipFile->addFromStream($handle, '');
    }

    /**
     * @throws ZipException
     */
    public function testAddFromStreamUnsupportedMethod(): void
    {
        $this->expectException(ZipUnsupportMethodException::class);
        $this->expectExceptionMessage('Compression method 99 (AES Encryption) is not supported.');

        $handle = fopen(__FILE__, 'rb');

        $zipFile = new ZipFile();
        $zipFile->addFromStream($handle, basename(__FILE__), ZipCompressionMethod::WINZIP_AES);
        $zipFile->outputAsString();
    }

    /**
     * Test compression method from add stream.
     *
     * @throws ZipException
     */
    public function testAddFromStreamCompressionMethod(): void
    {
        $fileStored = sys_get_temp_dir() . '/zip-stored.txt';
        $fileDeflated = sys_get_temp_dir() . '/zip-deflated.txt';

        static::assertNotFalse(file_put_contents($fileStored, 'content'));
        static::assertNotFalse(file_put_contents($fileDeflated, str_repeat('content', 200)));

        $fpStored = fopen($fileStored, 'rb');
        $fpDeflated = fopen($fileDeflated, 'rb');

        $zipFile = new ZipFile();
        $zipFile->addFromStream($fpStored, basename($fileStored));
        $zipFile->addFromStream($fpDeflated, basename($fileDeflated));
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        unlink($fileStored);
        unlink($fileDeflated);

        $zipFile->openFile($this->outputFilename);
        $methodStored = $zipFile->getEntry(basename($fileStored))->getCompressionMethod();
        $methodDeflated = $zipFile->getEntry(basename($fileDeflated))->getCompressionMethod();
        static::assertSame($methodStored, ZipCompressionMethod::STORED);
        static::assertSame($methodDeflated, ZipCompressionMethod::DEFLATED);
        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testAddFileCantExists(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File path/to/file is not readable');

        $zipFile = new ZipFile();
        $zipFile->addFile('path/to/file');
    }

    /**
     * @throws ZipException
     */
    public function testAddFileUnsupportedMethod(): void
    {
        $this->expectException(ZipUnsupportMethodException::class);
        $this->expectExceptionMessage('Compression method 99 (AES Encryption) is not supported.');

        $zipFile = new ZipFile();
        $zipFile->addFile(__FILE__, null, ZipCompressionMethod::WINZIP_AES);
        $zipFile->outputAsString();
    }

    /**
     * @throws ZipException
     */
    public function testAddFileCannotOpen(): void
    {
        static::skipTestForWindows();
        static::skipTestForRootUser();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not readable');

        static::assertNotFalse(file_put_contents($this->outputFilename, ''));
        static::assertTrue(chmod($this->outputFilename, 0244));

        $zipFile = new ZipFile();
        $zipFile->addFile($this->outputFilename);
    }

    /**
     * @throws ZipException
     */
    public function testAddDirEmptyDirname(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The input directory is not specified');

        $zipFile = new ZipFile();
        $zipFile->addDir('');
    }

    /**
     * @throws ZipException
     */
    public function testAddDirCantExists(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $zipFile = new ZipFile();
        $zipFile->addDir(uniqid('', false));
    }

    /**
     * @throws ZipException
     */
    public function testAddDirRecursiveEmptyDirname(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The input directory is not specified');

        $zipFile = new ZipFile();
        $zipFile->addDirRecursive('');
    }

    /**
     * @throws ZipException
     */
    public function testAddDirRecursiveCantExists(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $zipFile = new ZipFile();
        $zipFile->addDirRecursive(uniqid('', false));
    }

    /**
     * @throws ZipException
     */
    public function testAddFilesFromGlobEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The input directory is not specified');

        $zipFile = new ZipFile();
        $zipFile->addFilesFromGlob('', '*.png');
    }

    /**
     * @throws ZipException
     */
    public function testAddFilesFromGlobCantExists(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $zipFile = new ZipFile();
        $zipFile->addFilesFromGlob('path/to/path', '*.png');
    }

    /**
     * @throws ZipException
     */
    public function testAddFilesFromGlobEmptyPattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The glob pattern is not specified');

        $zipFile = new ZipFile();
        $zipFile->addFilesFromGlob(__DIR__, '');
    }

    /**
     * @throws ZipException
     */
    public function testAddFilesFromGlobRecursiveEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The input directory is not specified');

        $zipFile = new ZipFile();
        $zipFile->addFilesFromGlobRecursive('', '*.png');
    }

    /**
     * @throws ZipException
     */
    public function testAddFilesFromGlobRecursiveCantExists(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $zipFile = new ZipFile();
        $zipFile->addFilesFromGlobRecursive('path/to/path', '*.png');
    }

    /**
     * @throws ZipException
     */
    public function testAddFilesFromGlobRecursiveEmptyPattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The glob pattern is not specified');

        $zipFile = new ZipFile();
        $zipFile->addFilesFromGlobRecursive(__DIR__, '');
    }

    /**
     * @throws ZipException
     */
    public function testAddFilesFromRegexDirectoryEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The input directory is not specified');

        $zipFile = new ZipFile();
        $zipFile->addFilesFromRegex('', '~\.png$~i');
    }

    /**
     * @throws ZipException
     */
    public function testAddFilesFromRegexCantExists(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $zipFile = new ZipFile();
        $zipFile->addFilesFromRegex('path/to/path', '~\.png$~i');
    }

    /**
     * @throws ZipException
     */
    public function testAddFilesFromRegexEmptyPattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The regex pattern is not specified');

        $zipFile = new ZipFile();
        $zipFile->addFilesFromRegex(__DIR__, '');
    }

    /**
     * @throws ZipException
     */
    public function testAddFilesFromRegexRecursiveEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The input directory is not specified');

        $zipFile = new ZipFile();
        $zipFile->addFilesFromRegexRecursive('', '~\.png$~i');
    }

    /**
     * @throws ZipException
     */
    public function testAddFilesFromRegexRecursiveCantExists(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $zipFile = new ZipFile();
        $zipFile->addFilesFromGlobRecursive('path/to/path', '~\.png$~i');
    }

    /**
     * @throws ZipException
     */
    public function testAddFilesFromRegexRecursiveEmptyPattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The regex pattern is not specified');

        $zipFile = new ZipFile();
        $zipFile->addFilesFromRegexRecursive(__DIR__, '');
    }

    /**
     * @throws ZipException
     */
    public function testSaveAsStreamBadStream(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('handle is not resource');

        $zipFile = new ZipFile();
        /** @noinspection PhpParamsInspection */
        $zipFile->saveAsStream('bad stream');
    }

    /**
     * @throws ZipException
     */
    public function testSaveAsFileNotWritable(): void
    {
        static::skipTestForWindows();
        static::skipTestForRootUser();

        static::assertTrue(mkdir($this->outputDirname, 0444, true));
        static::assertTrue(chmod($this->outputDirname, 0444));

        $this->outputFilename = $this->outputDirname . \DIRECTORY_SEPARATOR . basename($this->outputFilename);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Permission denied');

        $zipFile = new ZipFile();
        $zipFile->saveAsFile($this->outputFilename);
    }

    /**
     * Test `ZipFile` implemented \ArrayAccess, \Countable and |iterator.
     *
     * @throws ZipException
     * @throws \Exception
     */
    public function testZipFileArrayAccessAndCountableAndIterator(): void
    {
        $files = [];
        $numFiles = random_int(20, 100);
        for ($i = 0; $i < $numFiles; $i++) {
            $files['file' . $i . '.txt'] = random_bytes(255);
        }

        $compressionMethods = [ZipCompressionMethod::STORED, ZipCompressionMethod::DEFLATED];

        if (\extension_loaded('bz2')) {
            $compressionMethods[] = ZipCompressionMethod::BZIP2;
        }

        $zipFile = new ZipFile();
        $zipFile->setCompressionLevel(ZipCompressionLevel::SUPER_FAST);

        $i = 0;
        $countMethods = \count($compressionMethods);

        foreach ($files as $entryName => $content) {
            $compressionMethod = $compressionMethods[$i++ % $countMethods];
            $zipFile->addFromString($entryName, $content, $compressionMethod);
        }
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);

        // Test \Countable
        static::assertSame($zipFile->count(), $numFiles);
        static::assertSame(\count($zipFile), $numFiles);

        // Test \ArrayAccess
        reset($files);

        foreach ($zipFile as $entryName => $content) {
            static::assertSame($entryName, key($files));
            static::assertSame($content, current($files));
            next($files);
        }

        // Test \Iterator
        reset($files);
        $iterator = new \ArrayIterator($zipFile);
        $iterator->rewind();

        while ($iterator->valid()) {
            $key = $iterator->key();
            $value = $iterator->current();

            static::assertSame($key, key($files));
            static::assertSame($value, current($files));

            next($files);
            $iterator->next();
        }
        $zipFile->close();

        $zipFile = new ZipFile();
        $zipFile['file1.txt'] = 'content 1';
        $zipFile['dir/file2.txt'] = 'content 1';
        $zipFile['dir/empty dir/'] = null;
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertTrue(isset($zipFile['file1.txt']));
        static::assertTrue(isset($zipFile['dir/file2.txt']));
        static::assertTrue(isset($zipFile['dir/empty dir/']));
        static::assertFalse(isset($zipFile['dir/empty dir/2/']));
        $zipFile['dir/empty dir/2/'] = null;
        unset($zipFile['dir/file2.txt'], $zipFile['dir/empty dir/']);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertTrue(isset($zipFile['file1.txt']));
        static::assertFalse(isset($zipFile['dir/file2.txt']));
        static::assertFalse(isset($zipFile['dir/empty dir/']));
        static::assertTrue(isset($zipFile['dir/empty dir/2/']));
        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testArrayAccessAddFile(): void
    {
        $entryName = 'path/to/file.dat';
        $entryNameStream = 'path/to/' . basename(__FILE__);

        $zipFile = new ZipFile();
        $zipFile[$entryName] = new \SplFileInfo(__FILE__);
        $zipFile[$entryNameStream] = fopen(__FILE__, 'rb');
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertSame(\count($zipFile), 2);
        static::assertTrue(isset($zipFile[$entryName]));
        static::assertTrue(isset($zipFile[$entryNameStream]));
        static::assertSame($zipFile[$entryName], file_get_contents(__FILE__));
        static::assertSame($zipFile[$entryNameStream], file_get_contents(__FILE__));
        $zipFile->close();
    }

    /**
     * @throws ZipException
     * @throws \Exception
     */
    public function testUnknownCompressionMethod(): void
    {
        $zipFile = new ZipFile();

        $zipFile->addFromString('file', 'content', ZipEntry::UNKNOWN);
        $zipFile->addFromString('file2', base64_encode(random_bytes(512)), ZipEntry::UNKNOWN);

        static::assertSame($zipFile->getEntry('file')->getCompressionMethod(), ZipCompressionMethod::STORED);
        static::assertSame($zipFile->getEntry('file2')->getCompressionMethod(), ZipCompressionMethod::DEFLATED);

        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $zipFile->openFile($this->outputFilename);
        static::assertSame($zipFile->getEntry('file')->getCompressionMethod(), ZipCompressionMethod::STORED);
        static::assertSame($zipFile->getEntry('file2')->getCompressionMethod(), ZipCompressionMethod::DEFLATED);
        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testAddEmptyDirEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty entry name');

        $zipFile = new ZipFile();
        $zipFile->addEmptyDir('');
    }

    public function testNotFoundEntry(): void
    {
        $this->expectException(ZipEntryNotFoundException::class);
        $this->expectExceptionMessage('"bad entry name"');

        $zipFile = new ZipFile();
        $zipFile['bad entry name'];
    }

    /**
     * Test rewrite input file.
     *
     * @throws ZipException
     */
    public function testRewriteFile(): void
    {
        $zipFile = new ZipFile();
        $zipFile['file'] = 'content';
        $zipFile['file2'] = 'content2';
        static::assertSame(\count($zipFile), 2);
        $zipFile
            ->saveAsFile($this->outputFilename)
            ->close()
        ;

        $md5file = md5_file($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertSame(\count($zipFile), 2);
        static::assertTrue(isset($zipFile['file']));
        static::assertTrue(isset($zipFile['file2']));
        $zipFile['file3'] = 'content3';
        static::assertSame(\count($zipFile), 3);
        $zipFile = $zipFile->rewrite();
        static::assertSame(\count($zipFile), 3);
        static::assertTrue(isset($zipFile['file']));
        static::assertTrue(isset($zipFile['file2']));
        static::assertTrue(isset($zipFile['file3']));
        $zipFile->close();

        static::assertNotSame(md5_file($this->outputFilename), $md5file);
    }

    /**
     * Test rewrite for string.
     *
     * @throws ZipException
     */
    public function testRewriteString(): void
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage('Overwrite is only supported for open local files');

        $zipFile = new ZipFile();
        $zipFile['file'] = 'content';
        $zipFile['file2'] = 'content2';
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $zipFile->openFromString(file_get_contents($this->outputFilename));
        static::assertSame(\count($zipFile), 2);
        static::assertTrue(isset($zipFile['file']));
        static::assertTrue(isset($zipFile['file2']));
        $zipFile['file3'] = 'content3';
        $zipFile = $zipFile->rewrite();
        static::assertSame(\count($zipFile), 3);
        static::assertTrue(isset($zipFile['file']));
        static::assertTrue(isset($zipFile['file2']));
        static::assertTrue(isset($zipFile['file3']));
        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testRewriteNullStream(): void
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage('input stream is null');

        $zipFile = new ZipFile();
        $zipFile->rewrite();
    }

    /**
     * Checks the ability to overwrite an open zip file with a relative path.
     *
     * @throws ZipException
     */
    public function testRewriteRelativeFile(): void
    {
        $zipFile = new ZipFile();
        $zipFile['entry.txt'] = 'test';
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $outputDirname = \dirname($this->outputFilename);
        static::assertTrue(chdir($outputDirname));

        $relativeFilename = basename($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        $zipFile['entry2.txt'] = 'test';
        $zipFile->saveAsFile($relativeFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);
    }

    /**
     * Checks the ability to overwrite an open zip file with a relative path.
     *
     * @throws ZipException
     */
    public function testRewriteDifferentWinDirectorySeparator(): void
    {
        if (\DIRECTORY_SEPARATOR !== '\\') {
            static::markTestSkipped('Windows test only');
        }

        $zipFile = new ZipFile();
        $zipFile['entry.txt'] = 'test';
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $alternativeOutputFilename = str_replace('\\', '/', $this->outputFilename);
        self::assertCorrectZipArchive($alternativeOutputFilename);

        $zipFile->openFile($this->outputFilename);
        $zipFile['entry2.txt'] = 'test';
        $zipFile->saveAsFile($alternativeOutputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($alternativeOutputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertCount(2, $zipFile);
        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testRewriteRelativeFile2(): void
    {
        $this->outputFilename = basename($this->outputFilename);

        $zipFile = new ZipFile();
        $zipFile['entry.txt'] = 'test';
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $absoluteOutputFilename = getcwd() . \DIRECTORY_SEPARATOR . $this->outputFilename;
        self::assertCorrectZipArchive($absoluteOutputFilename);

        $zipFile->openFile($this->outputFilename);
        $zipFile['entry2.txt'] = 'test';
        $zipFile->saveAsFile($absoluteOutputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($absoluteOutputFilename);
    }

    /**
     * @throws ZipException
     */
    public function testFilename0(): void
    {
        $zipFile = new ZipFile();
        $zipFile[0] = 0;
        static::assertTrue(isset($zipFile['0']));
        static::assertCount(1, $zipFile);
        $zipFile
            ->saveAsFile($this->outputFilename)
            ->close()
        ;

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertTrue(isset($zipFile[0]));
        static::assertTrue(isset($zipFile['0']));
        static::assertSame($zipFile['0'], '0');
        static::assertCount(1, $zipFile);
        $zipFile->close();

        static::assertTrue(unlink($this->outputFilename));

        $zipFile = new ZipFile();
        $zipFile->addFromString('0', '0');
        static::assertTrue(isset($zipFile[0]));
        static::assertTrue(isset($zipFile['0']));
        static::assertCount(1, $zipFile);
        $zipFile
            ->saveAsFile($this->outputFilename)
            ->close()
        ;

        static::assertCorrectZipArchive($this->outputFilename);
    }

    /**
     * @throws ZipException
     */
    public function testPsrResponse(): void
    {
        $zipFile = new ZipFile();
        for ($i = 0; $i < 10; $i++) {
            $zipFile[$i] = $i;
        }
        $filename = 'file.jar';
        $response = $zipFile->outputAsResponse(new Response(), $filename);
        static::assertSame('application/java-archive', $response->getHeaderLine('content-type'));
        static::assertSame('attachment; filename="file.jar"', $response->getHeaderLine('content-disposition'));
    }

    /**
     * @dataProvider provideCompressionLevels
     *
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     * @throws \Exception
     */
    public function testCompressionLevel(int $compressionLevel): void
    {
        $fileContent = random_bytes(512);
        $entryName = 'file.txt';

        $zipFile = new ZipFile();
        $zipFile
            ->addFromString($entryName, $fileContent, ZipCompressionMethod::DEFLATED)
            ->setCompressionLevelEntry($entryName, $compressionLevel)
            ->saveAsFile($this->outputFilename)
            ->close()
        ;

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertSame($zipFile->getEntryContents($entryName), $fileContent);
        static::assertSame($zipFile->getEntry($entryName)->getCompressionLevel(), $compressionLevel);
        $zipFile->close();
    }

    public function provideCompressionLevels(): array
    {
        return [
            [ZipCompressionLevel::MAXIMUM],
            [ZipCompressionLevel::NORMAL],
            [ZipCompressionLevel::FAST],
            [ZipCompressionLevel::SUPER_FAST],
        ];
    }

    /**
     * @throws ZipException
     */
    public function testInvalidCompressionLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid compression level');

        $zipFile = new ZipFile();
        $zipFile->addFromString('file', 'content');
        $zipFile->setCompressionLevel(15);
    }

    /**
     * @throws ZipException
     */
    public function testInvalidCompressionLevelEntry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid compression level');

        $zipFile = new ZipFile();
        $zipFile->addFromString('file', 'content');
        $zipFile->setCompressionLevelEntry('file', 15);
    }

    /**
     * @throws ZipException
     */
    public function testCompressionGlobal(): void
    {
        $zipFile = new ZipFile();
        for ($i = 0; $i < 10; $i++) {
            $zipFile->addFromString('file' . $i, 'content', ZipCompressionMethod::DEFLATED);
        }
        $zipFile
            ->setCompressionLevel(ZipCompressionLevel::SUPER_FAST)
            ->saveAsFile($this->outputFilename)
            ->close()
        ;

        static::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        $zipEntries = $zipFile->getEntries();
        array_walk(
            $zipEntries,
            function (ZipEntry $zipEntry): void {
                $this->assertSame($zipEntry->getCompressionLevel(), ZipCompressionLevel::SUPER_FAST);
            }
        );
        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testCompressionMethodEntry(): void
    {
        $zipFile = new ZipFile();
        $zipFile->addFromString('file', 'content', ZipCompressionMethod::STORED);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $zipFile->openFile($this->outputFilename);
        static::assertSame($zipFile->getEntry('file')->getCompressionMethod(), ZipCompressionMethod::STORED);
        $zipFile->setCompressionMethodEntry('file', ZipCompressionMethod::DEFLATED);
        static::assertSame($zipFile->getEntry('file')->getCompressionMethod(), ZipCompressionMethod::DEFLATED);

        $zipFile->rewrite();
        static::assertSame($zipFile->getEntry('file')->getCompressionMethod(), ZipCompressionMethod::DEFLATED);
    }

    /**
     * @throws ZipException
     */
    public function testInvalidCompressionMethodEntry(): void
    {
        $this->expectException(ZipUnsupportMethodException::class);
        $this->expectExceptionMessage('Compression method 99 (AES Encryption) is not supported.');

        $zipFile = new ZipFile();
        $zipFile->addFromString('file', 'content', ZipCompressionMethod::STORED);
        $zipFile->setCompressionMethodEntry('file', 99);
        $zipFile->outputAsString();
    }

    /**
     * @throws ZipException
     */
    public function testUnchangeAll(): void
    {
        $zipFile = new ZipFile();
        for ($i = 0; $i < 10; $i++) {
            $zipFile[$i] = $i;
        }
        $zipFile->setArchiveComment('comment');
        static::assertCount(10, $zipFile);
        static::assertSame($zipFile->getArchiveComment(), 'comment');
        $zipFile->saveAsFile($this->outputFilename);

        $zipFile->unchangeAll();
        static::assertCount(0, $zipFile);
        static::assertNull($zipFile->getArchiveComment());
        $zipFile->close();

        $zipFile->openFile($this->outputFilename);
        static::assertCount(10, $zipFile);
        static::assertSame($zipFile->getArchiveComment(), 'comment');

        for ($i = 10; $i < 100; $i++) {
            $zipFile[$i] = $i;
        }
        $zipFile->setArchiveComment('comment 2');
        static::assertCount(100, $zipFile);
        static::assertSame($zipFile->getArchiveComment(), 'comment 2');

        $zipFile->unchangeAll();
        static::assertCount(10, $zipFile);
        static::assertSame($zipFile->getArchiveComment(), 'comment');
        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testUnchangeArchiveComment(): void
    {
        $zipFile = new ZipFile();
        for ($i = 0; $i < 10; $i++) {
            $zipFile[$i] = $i;
        }
        $zipFile->setArchiveComment('comment');
        static::assertSame($zipFile->getArchiveComment(), 'comment');
        $zipFile->saveAsFile($this->outputFilename);

        $zipFile->unchangeArchiveComment();
        static::assertNull($zipFile->getArchiveComment());
        $zipFile->close();

        $zipFile->openFile($this->outputFilename);
        static::assertSame($zipFile->getArchiveComment(), 'comment');
        $zipFile->setArchiveComment('comment 2');
        static::assertSame($zipFile->getArchiveComment(), 'comment 2');

        $zipFile->unchangeArchiveComment();
        static::assertSame($zipFile->getArchiveComment(), 'comment');
        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testUnchangeEntry(): void
    {
        $zipFile = new ZipFile();
        $zipFile['file 1'] = 'content 1';
        $zipFile['file 2'] = 'content 2';
        $zipFile
            ->saveAsFile($this->outputFilename)
            ->close()
        ;

        $zipFile->openFile($this->outputFilename);

        $zipFile['file 1'] = 'modify content 1';
        $zipFile->setPasswordEntry('file 1', 'password');

        static::assertSame($zipFile['file 1'], 'modify content 1');
        static::assertTrue($zipFile->getEntry('file 1')->isEncrypted());

        static::assertSame($zipFile['file 2'], 'content 2');
        static::assertFalse($zipFile->getEntry('file 2')->isEncrypted());

        $zipFile->unchangeEntry('file 1');

        static::assertSame($zipFile['file 1'], 'content 1');
        static::assertFalse($zipFile->getEntry('file 1')->isEncrypted());

        static::assertSame($zipFile['file 2'], 'content 2');
        static::assertFalse($zipFile->getEntry('file 2')->isEncrypted());
        $zipFile->close();
    }

    /**
     * @runInSeparateProcess
     *
     * @dataProvider provideOutputAsAttachment
     *
     * @param ?string $mimeType
     *
     * @throws ZipException
     */
    public function testOutputAsAttachment(string $zipFilename, ?string $mimeType, string $expectedMimeType, bool $attachment, string $expectedAttachment): void
    {
        $zipFile = new ZipFile();
        $file1Contents = 'content 1';
        $zipFile['file 1'] = $file1Contents;

        ob_start();
        $zipFile->outputAsAttachment($zipFilename, $mimeType, $attachment);
        $zipContents = ob_get_clean();

        $zipFile->close();

        $length = \strlen($zipContents);
        static::assertTrue($length > 0);

        $zipFile->openFromString($zipContents);
        static::assertSame($zipFile['file 1'], $file1Contents);
        $zipFile->close();

        if (\function_exists('xdebug_get_headers')) {
            $expectedHeaders = [
                'Content-Disposition: ' . $expectedAttachment . '; filename="' . $zipFilename . '"',
                'Content-Type: ' . $expectedMimeType,
                'Content-Length: ' . $length,
            ];
            /** @noinspection ForgottenDebugOutputInspection */
            /** @noinspection PhpComposerExtensionStubsInspection */
            static::assertSame($expectedHeaders, xdebug_get_headers());
        }
    }

    public function provideOutputAsAttachment(): array
    {
        return [
            ['file.zip', null, 'application/zip', true, 'attachment'],
            ['file.zip', 'application/x-zip', 'application/x-zip', false, 'inline'],
            ['file.apk', null, 'application/vnd.android.package-archive', true, 'attachment'],
        ];
    }

    /**
     * @dataProvider provideGetEntryStream
     *
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testReopenEntryStream(ZipFile $zipFile, string $entryName, string $contents): void
    {
        for ($i = 0; $i < 2; $i++) {
            $fp = $zipFile->getEntryStream($entryName);
            static::assertIsResource($fp);
            static::assertSame(stream_get_contents($fp), $contents);
            fclose($fp);
        }

        $zipFile->close();
    }

    /**
     * @throws \Exception
     */
    public function provideGetEntryStream(): array
    {
        $entryName = 'entry';
        $contents = random_bytes(1024);

        $zipFileSpl = new ZipFile();
        $zipFileSpl->addSplFile(new \SplFileInfo(__FILE__), $entryName);

        return [
            [(new ZipFile())->addFromString($entryName, $contents), $entryName, $contents],
            [(new ZipFile())->addFile(__FILE__, $entryName), $entryName, file_get_contents(__FILE__)],
            [
                (new ZipFile())->addFromStream(fopen(__FILE__, 'rb'), $entryName),
                $entryName,
                file_get_contents(__FILE__),
            ],
            [$zipFileSpl, $entryName, file_get_contents(__FILE__)],
        ];
    }

    /**
     * @throws ZipException
     */
    public function testGetEntries(): void
    {
        $zipFile = new ZipFile();
        for ($i = 0; $i < 100; $i++) {
            $zipFile->addFromString($i . '.txt', 'contents ' . $i);
        }
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $zipFile->openFile($this->outputFilename);
        $zipEntries = $zipFile->getEntries();
        static::assertCount(100, $zipEntries);

        foreach ($zipEntries as $zipEntry) {
            static::assertInstanceOf(ZipEntry::class, $zipEntry);
            static::assertNotSame($zipEntry->getDosTime(), 0);
            $zipEntry->setDosTime(0);
            $zipEntry->setCreatedOS(ZipPlatform::OS_DOS);
            $zipEntry->setExtractedOS(ZipPlatform::OS_DOS);
            $zipEntry->setInternalAttributes(1);
            $zipEntry->setExternalAttributes(0);
        }
        $zipFile->rewrite();

        self::assertCorrectZipArchive($this->outputFilename);

        foreach ($zipFile->getEntries() as $zipEntry) {
            static::assertSame($zipEntry->getDosTime(), 0);
            static::assertSame($zipEntry->getExtractedOS(), ZipPlatform::OS_DOS);
            static::assertSame($zipEntry->getCreatedOS(), ZipPlatform::OS_DOS);
            static::assertSame($zipEntry->getInternalAttributes(), 1);
            static::assertSame($zipEntry->getExternalAttributes(), 0);
        }
        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testRenameWithRecompressData(): void
    {
        $entryName = 'file.txt';
        $newEntryName = 'rename_file.txt';
        $contents = str_repeat('Test' . \PHP_EOL, 1024);

        $zipFile = new ZipFile();
        $zipFile->addFromString($entryName, $contents, ZipCompressionMethod::DEFLATED);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        $zipFile->rename($entryName, $newEntryName);
        $zipFile->setCompressionMethodEntry($newEntryName, ZipCompressionMethod::STORED);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertSame($zipFile->getEntry($newEntryName)->getCompressionMethod(), ZipCompressionMethod::STORED);
        $zipFile->close();
    }

    /**
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testCloneZipContainerInZipWriter(): void
    {
        $zipFile = new ZipFile();
        $zipFile['file 1'] = 'contents';
        $zipEntryBeforeWrite = $zipFile->getEntry('file 1');
        $zipFile->saveAsFile($this->outputFilename);
        $zipAfterBeforeWrite = $zipFile->getEntry('file 1');

        static::assertSame($zipAfterBeforeWrite, $zipEntryBeforeWrite);

        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testMultiSave(): void
    {
        $zipFile = new ZipFile();
        $zipFile['file 1'] = 'contents';
        for ($i = 0; $i < 10; $i++) {
            $zipFile->saveAsFile($this->outputFilename);
            self::assertCorrectZipArchive($this->outputFilename);
        }
        $zipFile->close();
    }

    /**
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testNoData(): void
    {
        $this->expectException(ZipException::class);
        $this->expectExceptionMessage('No data for zip entry file');

        $entryName = 'file';

        $zipFile = new ZipFile();

        try {
            $zipFile[$entryName] = '';
            $zipEntry = $zipFile->getEntry($entryName);
            $zipEntry->setData(null);
            $zipFile->getEntryContents($entryName);
        } finally {
            $zipFile->close();
        }
    }

    /**
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testReplaceEntryContentsByFile(): void
    {
        $entryName = basename(__FILE__);

        $zipFile = new ZipFile();
        $zipFile[$entryName] = 'contents';
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        $zipFile->openFile($this->outputFilename);
        $entry = $zipFile->getEntry($entryName);
        $data = new ZipFileData($entry, new \SplFileInfo(__FILE__));
        $entry->setData($data);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        self::assertCorrectZipArchive($this->outputFilename);

        $zipFile->openFile($this->outputFilename);
        static::assertSame(
            $zipFile->getEntryContents($entryName),
            file_get_contents(__FILE__)
        );
        $zipFile->close();
    }
}
