<h1 align="center"><img src="logo.svg" alt="PhpZip" width="250" height="51"></h1>

`PhpZip` is a php-library for extended work with ZIP-archives.

[![Packagist Version](https://img.shields.io/packagist/v/nelexa/zip.svg)](https://packagist.org/packages/nelexa/zip)
[![Packagist Downloads](https://img.shields.io/packagist/dt/nelexa/zip.svg?color=%23ff007f)](https://packagist.org/packages/nelexa/zip)
[![Code Coverage](https://scrutinizer-ci.com/g/Ne-Lexa/php-zip/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Ne-Lexa/php-zip/?branch=master)
[![Build Status](https://github.com/Ne-Lexa/php-zip/workflows/build/badge.svg)](https://github.com/Ne-Lexa/php-zip/actions)
[![License](https://img.shields.io/packagist/l/nelexa/zip.svg)](https://github.com/Ne-Lexa/php-zip/blob/master/LICENSE)

[Russian Documentation](README.RU.md)

### Versions & Dependencies
| Version             | PHP        | Documentation                                                        |
| ------------------- | ---------- | -------------------------------------------------------------------- |
| ^4.0 (master)       | ^7.4\|^8.0 | current                                                              |
| ^3.0                | ^5.5\|^7.0 | [Docs v3.3](https://github.com/Ne-Lexa/php-zip/blob/3.3.3/README.md) |

Table of contents
-----------------
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Examples](#examples)
- [Glossary](#glossary)
- [Documentation](#documentation)
  + [Overview of methods of the class `\PhpZip\ZipFile`](#overview-of-methods-of-the-class-phpzipzipfile)
  + [Creation/Opening of ZIP-archive](#creationopening-of-zip-archive)
  + [Reading entries from the archive](#reading-entries-from-the-archive)
  + [Iterating entries](#iterating-entries)
  + [Getting information about entries](#getting-information-about-entries)
  + [Adding entries to the archive](#adding-entries-to-the-archive)
  + [Deleting entries from the archive](#deleting-entries-from-the-archive)
  + [Working with entries and archive](#working-with-entries-and-archive)
  + [Working with passwords](#working-with-passwords)
  + [Undo changes](#undo-changes)
  + [Saving a file or output to a browser](#saving-a-file-or-output-to-a-browser)
  + [Closing the archive](#closing-the-archive)
- [Running the tests](#running-the-tests)
- [Changelog](#changelog)
- [Upgrade](#upgrade)
  + [Upgrade version 3 to version 4](#upgrade-version-3-to-version-4)
  + [Upgrade version 2 to version 3](#upgrade-version-2-to-version-3)

### Features
- Opening and unzipping zip files.
- Creating ZIP-archives.
- Modifying ZIP archives.
- Pure php (not require extension `php-zip` and class `\ZipArchive`).
- It supports saving the archive to a file, outputting the archive to the browser, or outputting it as a string without saving it to a file.
- Archival comments and comments of individual entry are supported.
- Get information about each entry in the archive.
- Only the following compression methods are supported:
  + No compressed (Stored).
  + Deflate compression.
  + BZIP2 compression with the extension `php-bz2`.
- Support for `ZIP64` (file size is more than 4 GB or the number of entries in the archive is more than 65535).
- Working with passwords
  > **Attention!**
  >
  > For 32-bit systems, the `Traditional PKWARE Encryption (ZipCrypto)` encryption method is not currently supported. 
  > Use the encryption method `WinZIP AES Encryption`, whenever possible.
  + Set the password to read the archive for all entries or only for some.
  + Change the password for the archive, including for individual entries.
  + Delete the archive password for all or individual entries.
  + Set the password and/or the encryption method, both for all, and for individual entries in the archive.
  + Set different passwords and encryption methods for different entries.
  + Delete the password for all or some entries.
  + Support `Traditional PKWARE Encryption (ZipCrypto)` and `WinZIP AES Encryption` encryption methods.
  + Set the encryption method for all or individual entries in the archive.

### Requirements
- `PHP` >= 7.4 or `PHP` >= 8.0 (preferably 64-bit).
- Optional php-extension `bzip2` for BZIP2 compression.
- Optional php-extension `openssl` for `WinZip Aes Encryption` support.

### Installation
`composer require nelexa/zip`

Latest stable version: [![Latest Stable Version](https://poser.pugx.org/nelexa/zip/v/stable)](https://packagist.org/packages/nelexa/zip)

### Examples
```php
// create new archive
$zipFile = new \PhpZip\ZipFile();
try{
    $zipFile
        ->addFromString('zip/entry/filename', 'Is file content') // add an entry from the string
        ->addFile('/path/to/file', 'data/tofile') // add an entry from the file
        ->addDir(__DIR__, 'to/path/') // add files from the directory
        ->saveAsFile($outputFilename) // save the archive to a file
        ->close(); // close archive
            
    // open archive, extract, add files, set password and output to browser.
    $zipFile
        ->openFile($outputFilename) // open archive from file
        ->extractTo($outputDirExtract) // extract files to the specified directory
        ->deleteFromRegex('~^\.~') // delete all hidden (Unix) files
        ->addFromString('dir/file.txt', 'Test file') // add a new entry from the string
        ->setPassword('password') // set password for all entries
        ->outputAsAttachment('library.jar'); // output to the browser without saving to a file
}
catch(\PhpZip\Exception\ZipException $e){
    // handle exception
}
finally{
    $zipFile->close();
}
```
Other examples can be found in the `tests/` folder

### Glossary
**Zip Entry** - file or folder in a ZIP-archive. Each entry in the archive has certain properties, for example: file name, compression method, encryption method, file size before compression, file size after compression, CRC32 and others.

### Documentation:
#### Overview of methods of the class `\PhpZip\ZipFile`
- [ZipFile::__construct](#zipfile__construct) - initializes the ZIP archive.
- [ZipFile::addAll](#zipfileaddall) - adds all entries from an array.
- [ZipFile::addDir](#zipfileadddir) - adds files to the archive from the directory on the specified path without subdirectories.
- [ZipFile::addDirRecursive](#zipfileadddirrecursive) - adds files to the archive from the directory on the specified path with subdirectories.
- [ZipFile::addEmptyDir](#zipfileaddemptydir) - add a new directory.
- [ZipFile::addFile](#zipfileaddfile) - adds a file to a ZIP archive from the given path.
- [ZipFile::addSplFile](#zipfileaddsplfile) - adds a `\SplFileInfo` to a ZIP archive.
- [ZipFile::addFromFinder](#zipfileaddfromfinder) - adds files from the `Symfony\Component\Finder\Finder` to a ZIP archive.
- [ZipFile::addFilesFromIterator](#zipfileaddfilesfromiterator) - adds files from the iterator of directories.
- [ZipFile::addFilesFromGlob](#zipfileaddfilesfromglob) - adds files from a directory by glob pattern without subdirectories.
- [ZipFile::addFilesFromGlobRecursive](#zipfileaddfilesfromglobrecursive) - adds files from a directory by glob pattern with subdirectories.
- [ZipFile::addFilesFromRegex](#zipfileaddfilesfromregex) - adds files from a directory by PCRE pattern without subdirectories.
- [ZipFile::addFilesFromRegexRecursive](#zipfileaddfilesfromregexrecursive) - adds files from a directory by PCRE pattern with subdirectories.
- [ZipFile::addFromStream](#zipfileaddfromstream) - adds an entry from the stream to the ZIP archive.
- [ZipFile::addFromString](#zipfileaddfromstring) - adds a file to a ZIP archive using its contents.
- [ZipFile::close](#zipfileclose) - close the archive.
- [ZipFile::count](#zipfilecount) - returns the number of entries in the archive.
- [ZipFile::deleteFromName](#zipfiledeletefromname) - deletes an entry in the archive using its name.
- [ZipFile::deleteFromGlob](#zipfiledeletefromglob) - deletes an entries in the archive using glob pattern.
- [ZipFile::deleteFromRegex](#zipfiledeletefromregex) - deletes an entries in the archive using PCRE pattern.
- [ZipFile::deleteAll](#zipfiledeleteall) - deletes all entries in the ZIP archive.
- [ZipFile::disableEncryption](#zipfiledisableencryption) - disable encryption for all entries that are already in the archive.
- [ZipFile::disableEncryptionEntry](#zipfiledisableencryptionentry) - disable encryption of an entry defined by its name.
- [ZipFile::extractTo](#zipfileextractto) - extract the archive contents.
- [ZipFile::getArchiveComment](#zipfilegetarchivecomment) - returns the Zip archive comment.
- [ZipFile::getEntryComment](#zipfilegetentrycomment) - returns the comment of an entry using the entry name.
- [ZipFile::getEntryContent](#zipfilegetentrycontent) - returns the entry contents using its name.
- [ZipFile::getListFiles](#zipfilegetlistfiles) - returns list of archive files.
- [ZipFile::hasEntry](#zipfilehasentry) - checks if there is an entry in the archive.
- [ZipFile::isDirectory](#zipfileisdirectory) - checks that the entry in the archive is a directory.
- [ZipFile::matcher](#zipfilematcher) - selecting entries in the archive to perform operations on them.
- [ZipFile::openFile](#zipfileopenfile) - opens a zip-archive from a file.
- [ZipFile::openFromString](#zipfileopenfromstring) - opens a zip-archive from a string.
- [ZipFile::openFromStream](#zipfileopenfromstream) - opens a zip-archive from the stream.
- [ZipFile::outputAsAttachment](#zipfileoutputasattachment) - outputs a ZIP-archive to the browser.
- [ZipFile::outputAsPsr7Response](#zipfileoutputaspsr7response) - outputs a ZIP-archive as PSR-7 Response.
- [ZipFile::outputAsSymfonyResponse](#zipfileoutputaspsr7response) - outputs a ZIP-archive as Symfony Response.
- [ZipFile::outputAsString](#zipfileoutputasstring) - outputs a ZIP-archive as string.
- [ZipFile::rename](#zipfilerename) - renames an entry defined by its name.
- [ZipFile::rewrite](#zipfilerewrite) - save changes and re-open the changed archive.
- [ZipFile::saveAsFile](#zipfilesaveasfile) - saves the archive to a file.
- [ZipFile::saveAsStream](#zipfilesaveasstream) - writes the archive to the stream.
- [ZipFile::setArchiveComment](#zipfilesetarchivecomment) - set the comment of a ZIP archive.
- [ZipFile::setCompressionLevel](#zipfilesetcompressionlevel) - set the compression level for all files in the archive.
- [ZipFile::setCompressionLevelEntry](#zipfilesetcompressionlevelentry) - sets the compression level for the entry by its name.
- [ZipFile::setCompressionMethodEntry](#zipfilesetcompressionmethodentry) - sets the compression method for the entry by its name.
- [ZipFile::setEntryComment](#zipfilesetentrycomment) - set the comment of an entry defined by its name.
- [ZipFile::setReadPassword](#zipfilesetreadpassword) - set the password for the open archive.
- [ZipFile::setReadPasswordEntry](#zipfilesetreadpasswordentry) - sets a password for reading of an entry defined by its name.
- [ZipFile::setPassword](#zipfilesetpassword) - sets a new password for all files in the archive.
- [ZipFile::setPasswordEntry](#zipfilesetpasswordentry) - sets a new password of an entry defined by its name.
- [ZipFile::unchangeAll](#zipfileunchangeall) - undo all changes done in the archive.
- [ZipFile::unchangeArchiveComment](#zipfileunchangearchivecomment) - undo changes to the archive comment.
- [ZipFile::unchangeEntry](#zipfileunchangeentry) - undo changes of an entry defined by its name.

#### Creation/Opening of ZIP-archive
##### ZipFile::__construct**
Initializes the ZIP archive
```php
$zipFile = new \PhpZip\ZipFile();
```
##### ZipFile::openFile
Opens a zip-archive from a file.
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->openFile('file.zip');
```
##### ZipFile::openFromString
Opens a zip-archive from a string.
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->openFromString($stringContents);
```
##### ZipFile::openFromStream
Opens a zip-archive from the stream.
```php
$stream = fopen('file.zip', 'rb');

$zipFile = new \PhpZip\ZipFile();
$zipFile->openFromStream($stream);
```
#### Reading entries from the archive
##### ZipFile::count
Returns the number of entries in the archive.
```php
$zipFile = new \PhpZip\ZipFile();

$count = count($zipFile);
// or
$count = $zipFile->count();
```
##### ZipFile::getListFiles
Returns list of archive files.
```php
$zipFile = new \PhpZip\ZipFile();
$listFiles = $zipFile->getListFiles();

// example array contents:
// array (
//   0 => 'info.txt',
//   1 => 'path/to/file.jpg',
//   2 => 'another path/',
//   3 => '0',
// )
```
##### ZipFile::getEntryContent
Returns the entry contents using its name.
```php
// $entryName = 'path/to/example-entry-name.txt';
$zipFile = new \PhpZip\ZipFile();

$contents = $zipFile[$entryName];
// or
$contents = $zipFile->getEntryContents($entryName);
```
##### ZipFile::hasEntry
Checks if there is an entry in the archive.
```php
// $entryName = 'path/to/example-entry-name.txt';
$zipFile = new \PhpZip\ZipFile();

$hasEntry = isset($zipFile[$entryName]);
// or
$hasEntry = $zipFile->hasEntry($entryName);
```
##### ZipFile::isDirectory
Checks that the entry in the archive is a directory.
```php
// $entryName = 'path/to/';
$zipFile = new \PhpZip\ZipFile();

$isDirectory = $zipFile->isDirectory($entryName);
```
##### ZipFile::extractTo
Extract the archive contents.
The directory must exist.
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->extractTo($directory);
```
Extract some files to the directory.
The directory must exist.
```php
// $toDirectory = '/tmp';
$extractOnlyFiles = [
    'filename1', 
    'filename2', 
    'dir/dir/dir/'
];
$zipFile = new \PhpZip\ZipFile();
$zipFile->extractTo($toDirectory, $extractOnlyFiles);
```
#### Iterating entries
`ZipFile` is an iterator.
Can iterate all the entries in the `foreach` loop.
```php
foreach($zipFile as $entryName => $contents){
    echo "Filename: $entryName" . PHP_EOL;
    echo "Contents: $contents" . PHP_EOL;
    echo '-----------------------------' . PHP_EOL;
}
```
Can iterate through the `Iterator`.
```php
$iterator = new \ArrayIterator($zipFile);
while ($iterator->valid())
{
    $entryName = $iterator->key();
    $contents = $iterator->current();

    echo "Filename: $entryName" . PHP_EOL;
    echo "Contents: $contents" . PHP_EOL;
    echo '-----------------------------' . PHP_EOL;

    $iterator->next();
}
```
#### Getting information about entries
##### ZipFile::getArchiveComment
Returns the Zip archive comment.
```php
$zipFile = new \PhpZip\ZipFile();
$commentArchive = $zipFile->getArchiveComment();
```
##### ZipFile::getEntryComment
Returns the comment of an entry using the entry name.
```php
$zipFile = new \PhpZip\ZipFile();
$commentEntry = $zipFile->getEntryComment($entryName);
```

#### Adding entries to the archive
All methods of adding entries to a ZIP archive allow you to specify a method for compressing content.

The following methods of compression are available:
- `\PhpZip\Constants\ZipCompressionMethod::STORED` - no compression
- `\PhpZip\Constants\ZipCompressionMethod::DEFLATED` - Deflate compression
- `\PhpZip\Constants\ZipCompressionMethod::BZIP2` - Bzip2 compression with the extension `ext-bz2`

##### ZipFile::addFile
Adds a file to a ZIP archive from the given path.
```php
$zipFile = new \PhpZip\ZipFile();
// $file = '...../file.ext'; 
// $entryName = 'file2.ext'
$zipFile->addFile($file);

// you can specify the name of the entry in the archive (if null, then the last component from the file name is used)
$zipFile->addFile($file, $entryName);

// you can specify a compression method
$zipFile->addFile($file, $entryName, \PhpZip\Constants\ZipCompressionMethod::STORED); // No compression
$zipFile->addFile($file, $entryName, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate compression
$zipFile->addFile($file, $entryName, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 compression
```
##### ZipFile::addSplFile
Adds a `\SplFileInfo` to a ZIP archive.
```php
// $file = '...../file.ext'; 
// $entryName = 'file2.ext'
$zipFile = new \PhpZip\ZipFile();

$splFile = new \SplFileInfo('README.md');

$zipFile->addSplFile($splFile);
$zipFile->addSplFile($splFile, $entryName);
// or
$zipFile[$entryName] = new \SplFileInfo($file);

// set compression method
$zipFile->addSplFile($splFile, $entryName, $options = [
    \PhpZip\Constants\ZipOptions::COMPRESSION_METHOD => \PhpZip\Constants\ZipCompressionMethod::DEFLATED,
]);
```
##### ZipFile::addFromFinder
Adds files from the [`Symfony\Component\Finder\Finder`](https://symfony.com/doc/current/components/finder.html) to a ZIP archive.
```php
$finder = new \Symfony\Component\Finder\Finder();
$finder
    ->files()
    ->name('*.{jpg,jpeg,gif,png}')
    ->name('/^[0-9a-f]\./')
    ->contains('/lorem\s+ipsum$/i')
    ->in('path');

$zipFile = new \PhpZip\ZipFile();
$zipFile->addFromFinder($finder, $options = [
    \PhpZip\Constants\ZipOptions::COMPRESSION_METHOD => \PhpZip\Constants\ZipCompressionMethod::DEFLATED,
    \PhpZip\Constants\ZipOptions::MODIFIED_TIME => new \DateTimeImmutable('-1 day 5 min')
]);
```
##### ZipFile::addFromString
Adds a file to a ZIP archive using its contents.
```php
$zipFile = new \PhpZip\ZipFile();

$zipFile[$entryName] = $contents;
// or
$zipFile->addFromString($entryName, $contents);

// you can specify a compression method
$zipFile->addFromString($entryName, $contents, \PhpZip\Constants\ZipCompressionMethod::STORED); // No compression
$zipFile->addFromString($entryName, $contents, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate compression
$zipFile->addFromString($entryName, $contents, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 compression
```
##### ZipFile::addFromStream
Adds an entry from the stream to the ZIP archive.
```php
$zipFile = new \PhpZip\ZipFile();
// $stream = fopen(..., 'rb');

$zipFile->addFromStream($stream, $entryName);
// or
$zipFile[$entryName] = $stream;

// you can specify a compression method
$zipFile->addFromStream($stream, $entryName, \PhpZip\Constants\ZipCompressionMethod::STORED); // No compression
$zipFile->addFromStream($stream, $entryName, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate compression
$zipFile->addFromStream($stream, $entryName, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 compression
```
##### ZipFile::addEmptyDir
Add a new directory.
```php
$zipFile = new \PhpZip\ZipFile();
// $path = "path/to/";
$zipFile->addEmptyDir($path);
// or
$zipFile[$path] = null;
```
##### ZipFile::addAll
Adds all entries from an array.
```php
$entries = [
    'file.txt' => 'file contents', // add an entry from the string contents
    'empty dir/' => null, // add empty directory
    'path/to/file.jpg' => fopen('..../filename', 'rb'), // add an entry from the stream
    'path/to/file.dat' => new \SplFileInfo('..../filename'), // add an entry from the file
];

$zipFile = new \PhpZip\ZipFile();
$zipFile->addAll($entries);
```
##### ZipFile::addDir
Adds files to the archive from the directory on the specified path without subdirectories.
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->addDir($dirName);

// you can specify the path in the archive to which you want to put entries
$localPath = 'to/path/';
$zipFile->addDir($dirName, $localPath);

// you can specify a compression method
$zipFile->addDir($dirName, $localPath, \PhpZip\Constants\ZipCompressionMethod::STORED); // No compression
$zipFile->addDir($dirName, $localPath, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate compression
$zipFile->addDir($dirName, $localPath, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 compression
```
##### ZipFile::addDirRecursive
Adds files to the archive from the directory on the specified path with subdirectories.
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->addDirRecursive($dirName);

// you can specify the path in the archive to which you want to put entries
$localPath = 'to/path/';
$zipFile->addDirRecursive($dirName, $localPath);

// you can specify a compression method
$zipFile->addDirRecursive($dirName, $localPath, \PhpZip\Constants\ZipCompressionMethod::STORED); // No compression
$zipFile->addDirRecursive($dirName, $localPath, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate compression
$zipFile->addDirRecursive($dirName, $localPath, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 compression
```
##### ZipFile::addFilesFromIterator
Adds files from the iterator of directories.
```php
// $directoryIterator = new \DirectoryIterator($dir); // without subdirectories
// $directoryIterator = new \RecursiveDirectoryIterator($dir); // with subdirectories
$zipFile = new \PhpZip\ZipFile();
$zipFile->addFilesFromIterator($directoryIterator);

// you can specify the path in the archive to which you want to put entries
$localPath = 'to/path/';
$zipFile->addFilesFromIterator($directoryIterator, $localPath);
// or
$zipFile[$localPath] = $directoryIterator;

// you can specify a compression method
$zipFile->addFilesFromIterator($directoryIterator, $localPath, \PhpZip\Constants\ZipCompressionMethod::STORED); // No compression
$zipFile->addFilesFromIterator($directoryIterator, $localPath, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate compression
$zipFile->addFilesFromIterator($directoryIterator, $localPath, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 compression
```
Example with some files ignoring:
```php
$ignoreFiles = [
    'file_ignore.txt', 
    'dir_ignore/sub dir ignore/'
];

// $directoryIterator = new \DirectoryIterator($dir); // without subdirectories
// $directoryIterator = new \RecursiveDirectoryIterator($dir); // with subdirectories
// use \PhpZip\Util\Iterator\IgnoreFilesFilterIterator for non-recursive search
 
$zipFile = new \PhpZip\ZipFile();
$ignoreIterator = new \PhpZip\Util\Iterator\IgnoreFilesRecursiveFilterIterator(
    $directoryIterator, 
    $ignoreFiles
);

$zipFile->addFilesFromIterator($ignoreIterator);
```
##### ZipFile::addFilesFromGlob
Adds files from a directory by [glob pattern](https://en.wikipedia.org/wiki/Glob_(programming)) without subdirectories.
```php
$globPattern = '**.{jpg,jpeg,png,gif}'; // example glob pattern -> add all .jpg, .jpeg, .png and .gif files

$zipFile = new \PhpZip\ZipFile();
$zipFile->addFilesFromGlob($dir, $globPattern);

// you can specify the path in the archive to which you want to put entries
$localPath = 'to/path/';
$zipFile->addFilesFromGlob($dir, $globPattern, $localPath);

// you can specify a compression method
$zipFile->addFilesFromGlob($dir, $globPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::STORED); // No compression
$zipFile->addFilesFromGlob($dir, $globPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate compression
$zipFile->addFilesFromGlob($dir, $globPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 compression
```
##### ZipFile::addFilesFromGlobRecursive
Adds files from a directory by [glob pattern](https://en.wikipedia.org/wiki/Glob_(programming)) with subdirectories.
```php
$globPattern = '**.{jpg,jpeg,png,gif}'; // example glob pattern -> add all .jpg, .jpeg, .png and .gif files

$zipFile = new \PhpZip\ZipFile();
$zipFile->addFilesFromGlobRecursive($dir, $globPattern);

// you can specify the path in the archive to which you want to put entries
$localPath = 'to/path/';
$zipFile->addFilesFromGlobRecursive($dir, $globPattern, $localPath);

// you can specify a compression method
$zipFile->addFilesFromGlobRecursive($dir, $globPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::STORED); // No compression
$zipFile->addFilesFromGlobRecursive($dir, $globPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate compression
$zipFile->addFilesFromGlobRecursive($dir, $globPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 compression
```
##### ZipFile::addFilesFromRegex
Adds files from a directory by [PCRE pattern](https://en.wikipedia.org/wiki/Regular_expression) without subdirectories.
```php
$regexPattern = '/\.(jpe?g|png|gif)$/si'; // example regex pattern -> add all .jpg, .jpeg, .png and .gif files

$zipFile = new \PhpZip\ZipFile();
$zipFile->addFilesFromRegex($dir, $regexPattern);

// you can specify the path in the archive to which you want to put entries
$localPath = 'to/path/';
$zipFile->addFilesFromRegex($dir, $regexPattern, $localPath);

// you can specify a compression method
$zipFile->addFilesFromRegex($dir, $regexPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::STORED); // No compression
$zipFile->addFilesFromRegex($dir, $regexPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate compression
$zipFile->addFilesFromRegex($dir, $regexPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 compression
```
##### ZipFile::addFilesFromRegexRecursive
Adds files from a directory by [PCRE pattern](https://en.wikipedia.org/wiki/Regular_expression) with subdirectories.
```php
$regexPattern = '/\.(jpe?g|png|gif)$/si'; // example regex pattern -> add all .jpg, .jpeg, .png and .gif files

$zipFile->addFilesFromRegexRecursive($dir, $regexPattern);

// you can specify the path in the archive to which you want to put entries
$localPath = 'to/path/';
$zipFile->addFilesFromRegexRecursive($dir, $regexPattern, $localPath);

// you can specify a compression method
$zipFile->addFilesFromRegexRecursive($dir, $regexPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::STORED); // No compression
$zipFile->addFilesFromRegexRecursive($dir, $regexPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::DEFLATED); // Deflate compression
$zipFile->addFilesFromRegexRecursive($dir, $regexPattern, $localPath, \PhpZip\Constants\ZipCompressionMethod::BZIP2); // BZIP2 compression
```
#### Deleting entries from the archive
##### ZipFile::deleteFromName
Deletes an entry in the archive using its name.
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->deleteFromName($entryName);
```
##### ZipFile::deleteFromGlob
Deletes a entries in the archive using [glob pattern](https://en.wikipedia.org/wiki/Glob_(programming)).
```php
$globPattern = '**.{jpg,jpeg,png,gif}'; // example glob pattern -> delete all .jpg, .jpeg, .png and .gif files

$zipFile = new \PhpZip\ZipFile();
$zipFile->deleteFromGlob($globPattern);
```
##### ZipFile::deleteFromRegex
Deletes a entries in the archive using [PCRE pattern](https://en.wikipedia.org/wiki/Regular_expression).
```php
$regexPattern = '/\.(jpe?g|png|gif)$/si'; // example regex pattern -> delete all .jpg, .jpeg, .png and .gif files

$zipFile = new \PhpZip\ZipFile();
$zipFile->deleteFromRegex($regexPattern);
```
##### ZipFile::deleteAll
Deletes all entries in the ZIP archive.
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->deleteAll();
```
#### Working with entries and archive
##### ZipFile::rename
Renames an entry defined by its name.
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->rename($oldName, $newName);
```
##### ZipFile::setCompressionLevel
Set the compression level for all files in the archive.

> _Note that this method does not apply to entries that are added after this method is run._

By default, the compression level is 5 (`\PhpZip\Constants\ZipCompressionLevel::NORMAL`) or the compression level specified in the archive for Deflate compression.

The values range from 1 (`\PhpZip\Constants\ZipCompressionLevel::SUPER_FAST`) to 9 (`\PhpZip\Constants\ZipCompressionLevel::MAXIMUM`) are supported. The higher the number, the better and longer the compression.
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->setCompressionLevel(\PhpZip\Constants\ZipCompressionLevel::MAXIMUM);
```
##### ZipFile::setCompressionLevelEntry
Sets the compression level for the entry by its name.

The values range from 1 (`\PhpZip\Constants\ZipCompressionLevel::SUPER_FAST`) to 9 (`\PhpZip\Constants\ZipCompressionLevel::MAXIMUM`) are supported. The higher the number, the better and longer the compression.
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->setCompressionLevelEntry($entryName, \PhpZip\Constants\ZipCompressionLevel::FAST);
```
##### ZipFile::setCompressionMethodEntry
Sets the compression method for the entry by its name.

The following compression methods are available:
- `\PhpZip\Constants\ZipCompressionMethod::STORED` - No compression
- `\PhpZip\Constants\ZipCompressionMethod::DEFLATED` - Deflate compression
- `\PhpZip\Constants\ZipCompressionMethod::BZIP2` - Bzip2 compression with the extension `ext-bz2`
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->setCompressionMethodEntry($entryName, \PhpZip\Constants\ZipCompressionMethod::DEFLATED);
```
##### ZipFile::setArchiveComment
Set the comment of a ZIP archive.
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->setArchiveComment($commentArchive);
```
##### ZipFile::setEntryComment
Set the comment of an entry defined by its name.
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->setEntryComment($entryName, $comment);
```
##### ZipFile::matcher
Selecting entries in the archive to perform operations on them.
```php
$zipFile = new \PhpZip\ZipFile();
$matcher = $zipFile->matcher();
```
Selecting files from the archive one at a time:
```php
$matcher
    ->add('entry name')
    ->add('another entry');
```
Select multiple files in the archive:
```php
$matcher->add([
    'entry name',
    'another entry name',
    'path/'
]);
```
Selecting files by regular expression:
```php
$matcher->match('~\.jpe?g$~i');
```
Select all files in the archive:
```php
$matcher->all();
```
count() - gets the number of selected entries:
```php
$count = count($matcher);
// or
$count = $matcher->count();
```
getMatches() - returns a list of selected entries:
```php
$entries = $matcher->getMatches();
// example array contents: ['entry name', 'another entry name'];
```
invoke() - invoke a callable function on selected entries:
```php
// example
$matcher->invoke(static function($entryName) use($zipFile) {
    $newName = preg_replace('~\.(jpe?g)$~i', '.no_optimize.$1', $entryName);
    $zipFile->rename($entryName, $newName);
});
```
Functions for working on the selected entries:
```php
$matcher->delete(); // remove selected entries from a ZIP archive
$matcher->setPassword($password); // sets a new password for the selected entries
$matcher->setPassword($password, $encryptionMethod); // sets a new password and encryption method to selected entries
$matcher->setEncryptionMethod($encryptionMethod); // sets the encryption method to the selected entries
$matcher->disableEncryption(); // disables encryption for selected entries
```
#### Working with passwords

Implemented support for encryption methods:
- `\PhpZip\Constants\ZipEncryptionMethod::PKWARE` - Traditional PKWARE encryption (legacy)
- `\PhpZip\Constants\ZipEncryptionMethod::WINZIP_AES_256` - WinZip AES encryption 256 bit (recommended)
- `\PhpZip\Constants\ZipEncryptionMethod::WINZIP_AES_192` - WinZip AES encryption 192 bit
- `\PhpZip\Constants\ZipEncryptionMethod::WINZIP_AES_128` - WinZip AES encryption 128 bit

##### ZipFile::setReadPassword
Set the password for the open archive.

> _Setting a password is not required for adding new entries or deleting existing ones, but if you want to extract the content or change the method / compression level, the encryption method, or change the password, in this case the password must be specified._
```php
$zipFile->setReadPassword($password);
```
##### ZipFile::setReadPasswordEntry
Gets a password for reading of an entry defined by its name.
```php
$zipFile->setReadPasswordEntry($entryName, $password);
```
##### ZipFile::setPassword
Sets a new password for all files in the archive.

> _Note that this method does not apply to entries that are added after this method is run._
```php
$zipFile->setPassword($password);
```
You can set the encryption method:
```php
$encryptionMethod = \PhpZip\Constants\ZipEncryptionMethod::WINZIP_AES_256;
$zipFile->setPassword($password, $encryptionMethod);
```
##### ZipFile::setPasswordEntry
Sets a new password of an entry defined by its name.
```php
$zipFile->setPasswordEntry($entryName, $password);
```
You can set the encryption method:
```php
$encryptionMethod = \PhpZip\Constants\ZipEncryptionMethod::WINZIP_AES_256;
$zipFile->setPasswordEntry($entryName, $password, $encryptionMethod);
```
##### ZipFile::disableEncryption
Disable encryption for all entries that are already in the archive.

> _Note that this method does not apply to entries that are added after this method is run._
```php
$zipFile->disableEncryption();
```
##### ZipFile::disableEncryptionEntry
Disable encryption of an entry defined by its name.
```php
$zipFile->disableEncryptionEntry($entryName);
```
#### Undo changes
##### ZipFile::unchangeAll
Undo all changes done in the archive.
```php
$zipFile->unchangeAll();
```
##### ZipFile::unchangeArchiveComment
Undo changes to the archive comment.
```php
$zipFile->unchangeArchiveComment();
```
##### ZipFile::unchangeEntry
Undo changes of an entry defined by its name.
```php
$zipFile->unchangeEntry($entryName);
```
#### Saving a file or output to a browser
##### ZipFile::saveAsFile
Saves the archive to a file.
```php
$zipFile->saveAsFile($filename);
```
##### ZipFile::saveAsStream
Writes the archive to the stream.
```php
// $fp = fopen($filename, 'w+b');

$zipFile->saveAsStream($fp);
```
##### ZipFile::outputAsString
Outputs a ZIP-archive as string.
```php
$rawZipArchiveBytes = $zipFile->outputAsString();
```
##### ZipFile::outputAsAttachment
Outputs a ZIP-archive to the browser.
```php
$zipFile->outputAsAttachment($outputFilename);
```
You can set the Mime-Type:
```php
$mimeType = 'application/zip';
$zipFile->outputAsAttachment($outputFilename, $mimeType);
```
##### ZipFile::outputAsPsr7Response
Outputs a ZIP-archive as [PSR-7 Response](http://www.php-fig.org/psr/psr-7/).

The output method can be used in any PSR-7 compatible framework. 
```php
// $response = ....; // instance Psr\Http\Message\ResponseInterface
$zipFile->outputAsPsr7Response($response, $outputFilename);
```
You can set the Mime-Type:
```php
$mimeType = 'application/zip';
$zipFile->outputAsPsr7Response($response, $outputFilename, $mimeType);
```
##### ZipFile::outputAsSymfonyResponse
Outputs a ZIP-archive as [Symfony Response](https://symfony.com/doc/current/components/http_foundation.html#response).

The output method can be used in Symfony framework. 
```php
$response = $zipFile->outputAsSymfonyResponse($outputFilename);
```
You can set the Mime-Type:
```php
$mimeType = 'application/zip';
$response = $zipFile->outputAsSymfonyResponse($outputFilename, $mimeType);
```
Example use in Symfony Controller:
```php
<?php

namespace App\Controller;

use PhpZip\ZipFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DownloadZipController
{
    /**
     * @Route("/downloads/{id}")
     *
     * @throws \PhpZip\Exception\ZipException
     */
    public function __invoke(string $id): Response
    {
        $zipFile = new ZipFile();
        $zipFile['file'] = 'contents';

        $outputFilename = $id . '.zip';
        return $zipFile->outputAsSymfonyResponse($outputFilename);
    }
}
```
##### ZipFile::rewrite
Save changes and re-open the changed archive.
```php
$zipFile->rewrite();
```
#### Closing the archive
##### ZipFile::close
Close the archive.
```php
$zipFile->close();
```
### Running the tests
Install the dependencies for the development:
```bash
composer install --dev
```
Run the tests:
```bash
vendor/bin/phpunit
```
### Changelog
Changes are documented in the [releases page](https://github.com/Ne-Lexa/php-zip/releases).

### Upgrade
#### Upgrade version 3 to version 4
Update the major version in the file `composer.json` to `^4.0`.
```json
{
    "require": {
        "nelexa/zip": "^4.0"
    }
}
```
Then install updates using `Composer`:
```bash
composer update nelexa/zip
```
Update your code to work with the new version:
**BC**
- removed deprecated classes and methods.
- removed `zipalign` functional. This functionality will be placed in a separate package `nelexa/apkfile`.

#### Upgrade version 2 to version 3
Update the major version in the file `composer.json` to `^3.0`.
```json
{
    "require": {
        "nelexa/zip": "^3.0"
    }
}
```
Then install updates using `Composer`:
```bash
composer update nelexa/zip
```
Update your code to work with the new version:
- Class `ZipOutputFile` merged to `ZipFile` and removed.
  + `new \PhpZip\ZipOutputFile()` to `new \PhpZip\ZipFile()`
- Static initialization methods are now not static.
  + `\PhpZip\ZipFile::openFromFile($filename);` to `(new \PhpZip\ZipFile())->openFile($filename);`
  + `\PhpZip\ZipOutputFile::openFromFile($filename);` to `(new \PhpZip\ZipFile())->openFile($filename);`
  + `\PhpZip\ZipFile::openFromString($contents);` to `(new \PhpZip\ZipFile())->openFromString($contents);`
  + `\PhpZip\ZipFile::openFromStream($stream);` to `(new \PhpZip\ZipFile())->openFromStream($stream);`
  + `\PhpZip\ZipOutputFile::create()` to `new \PhpZip\ZipFile()`
  + `\PhpZip\ZipOutputFile::openFromZipFile(\PhpZip\ZipFile $zipFile)` &gt; `(new \PhpZip\ZipFile())->openFile($filename);`
- Rename methods:
  + `addFromFile` to `addFile`
  + `setLevel` to `setCompressionLevel`
  + `ZipFile::setPassword` to `ZipFile::withReadPassword`
  + `ZipOutputFile::setPassword` to `ZipFile::withNewPassword`
  + `ZipOutputFile::disableEncryptionAllEntries` to `ZipFile::withoutPassword`
  + `ZipOutputFile::setComment` to `ZipFile::setArchiveComment`
  + `ZipFile::getComment` to `ZipFile::getArchiveComment`
- Changed signature for methods `addDir`, `addFilesFromGlob`, `addFilesFromRegex`.
- Remove methods:
  + `getLevel`
  + `setCompressionMethod`
  + `setEntryPassword`
