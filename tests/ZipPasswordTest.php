<?php

namespace PhpZip\Tests;

use PhpZip\Constants\ZipCompressionMethod;
use PhpZip\Constants\ZipEncryptionMethod;
use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\RuntimeException;
use PhpZip\Exception\ZipAuthenticationException;
use PhpZip\Exception\ZipEntryNotFoundException;
use PhpZip\Exception\ZipException;
use PhpZip\Model\ZipInfo;
use PhpZip\ZipFile;

/**
 * Tests with zip password.
 *
 * @internal
 *
 * @small
 */
class ZipPasswordTest extends ZipFileSetTestCase
{
    /**
     * Test archive password.
     *
     * @throws ZipException
     * @throws \Exception
     * @noinspection PhpRedundantCatchClauseInspection
     */
    public function testSetPassword()
    {
        if (\PHP_INT_SIZE === 4) { // php 32 bit
            $this->setExpectedException(
                RuntimeException::class,
                'Traditional PKWARE Encryption is not supported in 32-bit PHP.'
            );
        }

        $password = base64_encode(random_bytes(100));
        $badPassword = 'bad password';

        // create encryption password with Traditional PKWARE encryption
        $zipFile = new ZipFile();
        $zipFile->addDir(__DIR__);
        $zipFile->setPassword($password, ZipEncryptionMethod::PKWARE);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename, $password);

        $zipFile->openFile($this->outputFilename);
        // check bad password for Traditional PKWARE encryption
        $zipFile->setReadPassword($badPassword);

        foreach ($zipFile->getListFiles() as $entryName) {
            try {
                $zipFile[$entryName];
                static::fail('Expected Exception has not been raised.');
            } catch (ZipAuthenticationException $ae) {
                static::assertContains('Invalid password', $ae->getMessage());
            }
        }

        // check correct password for Traditional PKWARE encryption
        $zipFile->setReadPassword($password);

        foreach ($zipFile->getAllInfo() as $info) {
            static::assertTrue($info->isEncrypted());
            static::assertContains('Traditional PKWARE encryption', $info->getEncryptionMethodName());
            $decryptContent = $zipFile[$info->getName()];
            static::assertNotEmpty($decryptContent);
            static::assertContains('<?php', $decryptContent);
        }

        // change encryption method to WinZip Aes and update file
        $zipFile->setPassword($password, ZipEncryptionMethod::WINZIP_AES_256);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename, $password);

        // check from WinZip AES encryption
        $zipFile->openFile($this->outputFilename);

        // set bad password WinZip AES
        $zipFile->setReadPassword($badPassword);

        foreach ($zipFile->getListFiles() as $entryName) {
            try {
                $zipFile[$entryName];
                static::fail('Expected Exception has not been raised.');
            } catch (ZipAuthenticationException $ae) {
                static::assertNotNull($ae);
            }
        }

        // set correct password WinZip AES
        $zipFile->setReadPassword($password);

        foreach ($zipFile->getAllInfo() as $info) {
            static::assertTrue($info->isEncrypted());
            static::assertContains('Deflated', $info->getMethodName());
            static::assertContains('WinZip AES-256', $info->getEncryptionMethodName());
            $decryptContent = $zipFile[$info->getName()];
            static::assertNotEmpty($decryptContent);
            static::assertContains('<?php', $decryptContent);
        }

        // clear password
        $zipFile->addFromString('file1', '');
        $zipFile->disableEncryption();
        $zipFile->addFromString('file2', '');
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename);

        // check remove password
        $zipFile->openFile($this->outputFilename);

        foreach ($zipFile->getAllInfo() as $info) {
            static::assertFalse($info->isEncrypted());
        }
        $zipFile->close();
    }

    /**
     * @throws ZipException
     * @throws \Exception
     */
    public function testTraditionalEncryption()
    {
        if (\PHP_INT_SIZE === 4) { // php 32 bit
            $this->setExpectedException(
                RuntimeException::class,
                'Traditional PKWARE Encryption is not supported in 32-bit PHP.'
            );
        }

        $password = base64_encode(random_bytes(50));

        $zip = new ZipFile();
        $zip->addDirRecursive($this->outputDirname);
        $zip->setPassword($password, ZipEncryptionMethod::PKWARE);
        $zip->saveAsFile($this->outputFilename);
        $zip->close();

        static::assertCorrectZipArchive($this->outputFilename, $password);

        $zip->openFile($this->outputFilename);
        $zip->setReadPassword($password);
        static::assertFilesResult($zip, array_keys(self::$files));

        foreach ($zip->getAllInfo() as $info) {
            if (!$info->isFolder()) {
                static::assertTrue($info->isEncrypted());
                static::assertContains('Traditional PKWARE encryption', $info->getEncryptionMethodName());
            }
        }
        $zip->close();
    }

    /**
     * @dataProvider winZipKeyStrengthProvider
     *
     * @param int $encryptionMethod
     * @param int $bitSize
     *
     * @throws ZipException
     * @throws \Exception
     */
    public function testWinZipAesEncryption($encryptionMethod, $bitSize)
    {
        $password = base64_encode(random_bytes(50));

        $zip = new ZipFile();
        $zip->addDirRecursive($this->outputDirname);
        $zip->setPassword($password, $encryptionMethod);
        $zip->saveAsFile($this->outputFilename);
        $zip->close();

        static::assertCorrectZipArchive($this->outputFilename, $password);

        $zip->openFile($this->outputFilename);
        $zip->setReadPassword($password);
        static::assertFilesResult($zip, array_keys(self::$files));

        foreach ($zip->getAllInfo() as $info) {
            if (!$info->isFolder()) {
                static::assertTrue($info->isEncrypted());
                static::assertSame($info->getEncryptionMethod(), $encryptionMethod);
                static::assertContains('WinZip AES-' . $bitSize, $info->getEncryptionMethodName());
            }
        }
        $zip->close();
    }

    /**
     * @return array
     */
    public function winZipKeyStrengthProvider()
    {
        return [
            [ZipEncryptionMethod::WINZIP_AES_128, 128],
            [ZipEncryptionMethod::WINZIP_AES_192, 192],
            [ZipEncryptionMethod::WINZIP_AES_256, 256],
        ];
    }

    /**
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testEncryptionEntries()
    {
        if (\PHP_INT_SIZE === 4) { // php 32 bit
            $this->setExpectedException(
                RuntimeException::class,
                'Traditional PKWARE Encryption is not supported in 32-bit PHP.'
            );
        }

        $password1 = '353442434235424234';
        $password2 = 'adgerhvrwjhqqehtqhkbqrgewg';

        $zip = new ZipFile();
        $zip->addDir($this->outputDirname);
        $zip->setPasswordEntry('.hidden', $password1, ZipEncryptionMethod::PKWARE);
        $zip->setPasswordEntry('text file.txt', $password2, ZipEncryptionMethod::WINZIP_AES_256);
        $zip->saveAsFile($this->outputFilename);
        $zip->close();

        $zip->openFile($this->outputFilename);
        $zip->setReadPasswordEntry('.hidden', $password1);
        $zip->setReadPasswordEntry('text file.txt', $password2);
        static::assertFilesResult(
            $zip,
            [
                '.hidden',
                'text file.txt',
                'Текстовый документ.txt',
                'empty dir/',
                'LoremIpsum.txt',
            ]
        );

        $info = $zip->getEntryInfo('.hidden');
        static::assertTrue($info->isEncrypted());
        static::assertContains('Traditional PKWARE encryption', $info->getEncryptionMethodName());

        $info = $zip->getEntryInfo('text file.txt');
        static::assertTrue($info->isEncrypted());
        static::assertContains('WinZip AES', $info->getEncryptionMethodName());

        static::assertFalse($zip->getEntryInfo('Текстовый документ.txt')->isEncrypted());
        static::assertFalse($zip->getEntryInfo('empty dir/')->isEncrypted());

        $zip->close();
    }

    /**
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testEncryptionEntriesWithDefaultPassword()
    {
        if (\PHP_INT_SIZE === 4) { // php 32 bit
            $this->setExpectedException(
                RuntimeException::class,
                'Traditional PKWARE Encryption is not supported in 32-bit PHP.'
            );
        }

        $password1 = '353442434235424234';
        $password2 = 'adgerhvrwjhqqehtqhkbqrgewg';
        $defaultPassword = '  f  f  f  f f  ffff   f5   ';

        $zip = new ZipFile();
        $zip->addDir($this->outputDirname);
        $zip->setPassword($defaultPassword);
        $zip->setPasswordEntry('.hidden', $password1, ZipEncryptionMethod::PKWARE);
        $zip->setPasswordEntry('text file.txt', $password2, ZipEncryptionMethod::WINZIP_AES_256);
        $zip->saveAsFile($this->outputFilename);
        $zip->close();

        $zip->openFile($this->outputFilename);
        $zip->setReadPassword($defaultPassword);
        $zip->setReadPasswordEntry('.hidden', $password1);
        $zip->setReadPasswordEntry('text file.txt', $password2);
        static::assertFilesResult(
            $zip,
            [
                '.hidden',
                'text file.txt',
                'Текстовый документ.txt',
                'empty dir/',
                'LoremIpsum.txt',
            ]
        );

        $info = $zip->getEntryInfo('.hidden');
        static::assertTrue($info->isEncrypted());
        static::assertContains('Traditional PKWARE encryption', $info->getEncryptionMethodName());

        $info = $zip->getEntryInfo('text file.txt');
        static::assertTrue($info->isEncrypted());
        static::assertContains('WinZip AES', $info->getEncryptionMethodName());

        $info = $zip->getEntryInfo('Текстовый документ.txt');
        static::assertTrue($info->isEncrypted());
        static::assertContains('WinZip AES', $info->getEncryptionMethodName());

        static::assertFalse($zip->getEntryInfo('empty dir/')->isEncrypted());

        $zip->close();
    }

    /**
     * @throws ZipException
     */
    public function testSetEncryptionMethodInvalid()
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Encryption method 9999 is not supported.');

        $zipFile = new ZipFile();
        $encryptionMethod = 9999;
        $zipFile['entry'] = 'content';
        $zipFile->setPassword('pass', $encryptionMethod);
        $zipFile->outputAsString();
    }

    /**
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testEntryPassword()
    {
        $zipFile = new ZipFile();
        $zipFile->setPassword('pass');
        $zipFile['file'] = 'content';
        static::assertFalse($zipFile->getEntryInfo('file')->isEncrypted());
        for ($i = 1; $i <= 10; $i++) {
            $zipFile['file' . $i] = 'content';

            if ($i < 6) {
                $zipFile->setPasswordEntry('file' . $i, 'pass');
                static::assertTrue($zipFile->getEntryInfo('file' . $i)->isEncrypted());
            } else {
                static::assertFalse($zipFile->getEntryInfo('file' . $i)->isEncrypted());
            }
        }
        $zipFile->disableEncryptionEntry('file3');
        static::assertFalse($zipFile->getEntryInfo('file3')->isEncrypted());
        static::assertTrue($zipFile->getEntryInfo('file2')->isEncrypted());
        $zipFile->disableEncryption();
        $infoList = $zipFile->getAllInfo();
        array_walk(
            $infoList,
            function (ZipInfo $zipInfo) {
                $this->assertFalse($zipInfo->isEncrypted());
            }
        );
        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testInvalidEncryptionMethodEntry()
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Encryption method 99 is not supported.');

        $zipFile = new ZipFile();
        $zipFile->addFromString('file', 'content', ZipCompressionMethod::STORED);
        $zipFile->setPasswordEntry('file', 'pass', ZipCompressionMethod::WINZIP_AES);
    }

    /**
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testArchivePasswordUpdateWithoutSetReadPassword()
    {
        $zipFile = new ZipFile();
        $zipFile['file1'] = 'content';
        $zipFile['file2'] = 'content';
        $zipFile['file3'] = 'content';
        $zipFile->setPassword('password');
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        static::assertCorrectZipArchive($this->outputFilename, 'password');

        $zipFile->openFile($this->outputFilename);
        static::assertCount(3, $zipFile);

        foreach ($zipFile->getAllInfo() as $info) {
            static::assertTrue($info->isEncrypted());
        }
        unset($zipFile['file3']);
        $zipFile['file4'] = 'content';
        $zipFile->rewrite();

        static::assertCorrectZipArchive($this->outputFilename, 'password');

        static::assertCount(3, $zipFile);
        static::assertFalse(isset($zipFile['file3']));
        static::assertTrue(isset($zipFile['file4']));
        static::assertTrue($zipFile->getEntryInfo('file1')->isEncrypted());
        static::assertTrue($zipFile->getEntryInfo('file2')->isEncrypted());
        static::assertFalse($zipFile->getEntryInfo('file4')->isEncrypted());
        static::assertSame($zipFile['file4'], 'content');

        $zipFile->extractTo($this->outputDirname, ['file4']);

        static::assertFileExists($this->outputDirname . \DIRECTORY_SEPARATOR . 'file4');
        static::assertStringEqualsFile($this->outputDirname . \DIRECTORY_SEPARATOR . 'file4', $zipFile['file4']);

        $zipFile->close();
    }

    /**
     * @see https://github.com/Ne-Lexa/php-zip/issues/9
     *
     * @throws ZipException
     * @throws \Exception
     */
    public function testIssues9()
    {
        $contents = str_pad('', 1000, 'test;test2;test3' . \PHP_EOL, \STR_PAD_RIGHT);
        $password = base64_encode(random_bytes(20));

        $zipFile = new ZipFile();
        $zipFile
            ->addFromString('codes.csv', $contents, ZipCompressionMethod::DEFLATED)
            ->setPassword($password, ZipEncryptionMethod::WINZIP_AES_256)
            ->saveAsFile($this->outputFilename)
            ->close()
        ;

        static::assertCorrectZipArchive($this->outputFilename, $password);

        $zipFile->openFile($this->outputFilename);
        $zipFile->setReadPassword($password);
        static::assertSame($zipFile['codes.csv'], $contents);
        $zipFile->close();
    }

    /**
     * @throws ZipEntryNotFoundException
     * @throws ZipException
     */
    public function testReadAesEncryptedAndRewriteArchive()
    {
        $file = __DIR__ . '/resources/aes_password_archive.zip';
        $password = '1234567890';

        $zipFile = new ZipFile();
        $zipFile->openFile($file);
        $zipFile->setReadPassword($password);
        $zipFile->setPassword($password);
        $zipFile->setEntryComment('contents.txt', 'comment'); // change entry, but not changed contents
        $zipFile->saveAsFile($this->outputFilename);

        $zipFile2 = new ZipFile();
        $zipFile2->openFile($this->outputFilename);
        $zipFile2->setReadPassword($password);
        static::assertSame($zipFile2->getListFiles(), $zipFile->getListFiles());

        foreach ($zipFile as $name => $contents) {
            static::assertNotEmpty($name);
            static::assertNotEmpty($contents);
            static::assertContains('test contents', $contents);
            static::assertSame($zipFile2[$name], $contents);
        }
        $zipFile2->close();

        $zipFile->close();
    }
}
