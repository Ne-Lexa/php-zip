<?php

declare(strict_types=1);

/*
 * This file is part of the nelexa/zip package.
 * (c) Ne-Lexa <https://github.com/Ne-Lexa/php-zip>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpZip\Tests;

use PhpZip\Constants\ZipCompressionMethod;
use PhpZip\Constants\ZipEncryptionMethod;
use PhpZip\Exception\InvalidArgumentException;
use PhpZip\Exception\RuntimeException;
use PhpZip\Exception\ZipAuthenticationException;
use PhpZip\Exception\ZipEntryNotFoundException;
use PhpZip\Exception\ZipException;
use PhpZip\Model\ZipEntry;
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
    public function testSetPassword(): void
    {
        if (\PHP_INT_SIZE === 4) { // php 32 bit
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Traditional PKWARE Encryption is not supported in 32-bit PHP.');
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
            } catch (ZipException $e) {
            }
        }

        // check correct password for Traditional PKWARE encryption
        $zipFile->setReadPassword($password);

        foreach ($zipFile->getEntries() as $zipEntry) {
            static::assertTrue($zipEntry->isEncrypted());
            static::assertSame(ZipEncryptionMethod::getEncryptionMethodName($zipEntry->getEncryptionMethod()), 'Traditional PKWARE encryption');
            $decryptContent = $zipFile[$zipEntry->getName()];
            static::assertNotEmpty($decryptContent);
            static::assertStringContainsString('<?php', $decryptContent);
        }

        // change encryption method to WinZip Aes and update file
        $zipFile->setPassword($password/*, ZipEncryptionMethod::WINZIP_AES_256*/);
        $zipFile->saveAsFile($this->outputFilename);
        $zipFile->close();

        /** @see https://sourceforge.net/p/p7zip/discussion/383044/thread/c859a2f0/ WinZip 99-character limit */
        static::assertCorrectZipArchive($this->outputFilename, substr($password, 0, 99));

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

        foreach ($zipFile->getEntries() as $zipEntry) {
            static::assertTrue($zipEntry->isEncrypted());
            static::assertSame($zipEntry->getCompressionMethod(), ZipCompressionMethod::DEFLATED);
            static::assertSame(ZipEncryptionMethod::getEncryptionMethodName($zipEntry->getEncryptionMethod()), 'WinZip AES-256');
            $decryptContent = $zipFile[$zipEntry->getName()];
            static::assertNotEmpty($decryptContent);
            static::assertStringContainsString('<?php', $decryptContent);
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

        foreach ($zipFile->getEntries() as $zipEntry) {
            static::assertFalse($zipEntry->isEncrypted());
        }
        $zipFile->close();
    }

    /**
     * @throws ZipException
     * @throws \Exception
     */
    public function testTraditionalEncryption(): void
    {
        if (\PHP_INT_SIZE === 4) { // php 32 bit
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Traditional PKWARE Encryption is not supported in 32-bit PHP.');
        }

        $password = md5(random_bytes(50));

        $zip = new ZipFile();
        $zip->addDirRecursive($this->outputDirname);
        $zip->setPassword($password, ZipEncryptionMethod::PKWARE);
        $zip->saveAsFile($this->outputFilename);
        $zip->close();

        static::assertCorrectZipArchive($this->outputFilename, $password);

        $zip->openFile($this->outputFilename);
        $zip->setReadPassword($password);
        static::assertFilesResult($zip, array_keys(self::$files));

        foreach ($zip->getEntries() as $zipEntry) {
            if (!$zipEntry->isDirectory()) {
                static::assertTrue($zipEntry->isEncrypted());
                static::assertSame(ZipEncryptionMethod::getEncryptionMethodName($zipEntry->getEncryptionMethod()), 'Traditional PKWARE encryption');
            }
        }
        $zip->close();
    }

    /**
     * @dataProvider winZipKeyStrengthProvider
     *
     * @throws ZipException
     * @throws \Exception
     */
    public function testWinZipAesEncryption(int $encryptionMethod, int $bitSize): void
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

        foreach ($zip->getEntries() as $info) {
            if (!$info->isDirectory()) {
                static::assertTrue($info->isEncrypted());
                static::assertSame($info->getEncryptionMethod(), $encryptionMethod);
                static::assertSame(ZipEncryptionMethod::getEncryptionMethodName($info->getEncryptionMethod()), 'WinZip AES-' . $bitSize);
            }
        }
        $zip->close();
    }

    public function winZipKeyStrengthProvider(): array
    {
        return [
            [ZipEncryptionMethod::WINZIP_AES_128, 128],
            [ZipEncryptionMethod::WINZIP_AES_192, 192],
            [ZipEncryptionMethod::WINZIP_AES_256, 256],
        ];
    }

    /**
     * @throws ZipException
     */
    public function testEncryptionEntries(): void
    {
        if (\PHP_INT_SIZE === 4) { // php 32 bit
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Traditional PKWARE Encryption is not supported in 32-bit PHP.');
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

        $info = $zip->getEntry('.hidden');
        static::assertTrue($info->isEncrypted());
        static::assertSame(ZipEncryptionMethod::getEncryptionMethodName($info->getEncryptionMethod()), 'Traditional PKWARE encryption');

        $info = $zip->getEntry('text file.txt');
        static::assertTrue($info->isEncrypted());
        static::assertStringContainsString(
            'WinZip AES',
            ZipEncryptionMethod::getEncryptionMethodName($info->getEncryptionMethod())
        );

        static::assertFalse($zip->getEntry('Текстовый документ.txt')->isEncrypted());
        static::assertFalse($zip->getEntry('empty dir/')->isEncrypted());

        $zip->close();
    }

    /**
     * @throws ZipException
     */
    public function testEncryptionEntriesWithDefaultPassword(): void
    {
        if (\PHP_INT_SIZE === 4) { // php 32 bit
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Traditional PKWARE Encryption is not supported in 32-bit PHP.');
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

        $zipEntry = $zip->getEntry('.hidden');
        static::assertTrue($zipEntry->isEncrypted());
        static::assertSame(ZipEncryptionMethod::getEncryptionMethodName($zipEntry->getEncryptionMethod()), 'Traditional PKWARE encryption');

        $zipEntry = $zip->getEntry('text file.txt');
        static::assertTrue($zipEntry->isEncrypted());
        static::assertStringContainsString(
            'WinZip AES',
            ZipEncryptionMethod::getEncryptionMethodName($zipEntry->getEncryptionMethod())
        );

        $zipEntry = $zip->getEntry('Текстовый документ.txt');
        static::assertTrue($zipEntry->isEncrypted());
        static::assertStringContainsString(
            'WinZip AES',
            ZipEncryptionMethod::getEncryptionMethodName($zipEntry->getEncryptionMethod())
        );

        static::assertFalse($zip->getEntry('empty dir/')->isEncrypted());

        $zip->close();
    }

    /**
     * @throws ZipException
     */
    public function testSetEncryptionMethodInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Encryption method 9999 is not supported.');

        $zipFile = new ZipFile();
        $encryptionMethod = 9999;
        $zipFile['entry'] = 'content';
        $zipFile->setPassword('pass', $encryptionMethod);
        $zipFile->outputAsString();
    }

    /**
     * @throws ZipException
     */
    public function testEntryPassword(): void
    {
        $zipFile = new ZipFile();
        $zipFile->setPassword('pass');
        $zipFile['file'] = 'content';
        static::assertFalse($zipFile->getEntry('file')->isEncrypted());
        for ($i = 1; $i <= 10; $i++) {
            $entryName = 'file' . $i;
            $zipFile[$entryName] = 'content';

            if ($i < 6) {
                $zipFile->setPasswordEntry($entryName, 'pass');
                static::assertTrue($zipFile->getEntry($entryName)->isEncrypted());
            } else {
                static::assertFalse($zipFile->getEntry($entryName)->isEncrypted());
            }
        }
        $zipFile->disableEncryptionEntry('file3');
        static::assertFalse($zipFile->getEntry('file3')->isEncrypted());
        static::assertTrue($zipFile->getEntry('file2')->isEncrypted());
        $zipFile->disableEncryption();
        $zipEntries = $zipFile->getEntries();
        array_walk(
            $zipEntries,
            function (ZipEntry $zipEntry): void {
                $this->assertFalse($zipEntry->isEncrypted());
            }
        );
        $zipFile->close();
    }

    /**
     * @throws ZipException
     */
    public function testInvalidEncryptionMethodEntry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Encryption method 99 is not supported.');

        $zipFile = new ZipFile();
        $zipFile->addFromString('file', 'content', ZipCompressionMethod::STORED);
        $zipFile->setPasswordEntry('file', 'pass', ZipCompressionMethod::WINZIP_AES);
    }

    /**
     * @throws ZipException
     */
    public function testArchivePasswordUpdateWithoutSetReadPassword(): void
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

        foreach ($zipFile->getEntries() as $zipEntry) {
            static::assertTrue($zipEntry->isEncrypted());
        }
        unset($zipFile['file3']);
        $zipFile['file4'] = 'content';
        $zipFile->rewrite();

        static::assertCorrectZipArchive($this->outputFilename, 'password');

        static::assertCount(3, $zipFile);
        static::assertFalse(isset($zipFile['file3']));
        static::assertTrue(isset($zipFile['file4']));
        static::assertTrue($zipFile->getEntry('file1')->isEncrypted());
        static::assertTrue($zipFile->getEntry('file2')->isEncrypted());
        static::assertFalse($zipFile->getEntry('file4')->isEncrypted());
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
    public function testIssues9(): void
    {
        $contents = str_pad('', 1000, 'test;test2;test3' . \PHP_EOL);
        $password = base64_encode(random_bytes(20));

        $zipFile = new ZipFile();
        $zipFile
            ->addFromString('codes.csv', $contents, ZipCompressionMethod::DEFLATED)
            ->setPassword($password)
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
    public function testReadAesEncryptedAndRewriteArchive(): void
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
            static::assertStringContainsString('test contents', $contents);
            static::assertSame($zipFile2[$name], $contents);
        }
        $zipFile2->close();

        $zipFile->close();
    }
}
