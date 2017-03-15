`PhpZip`
========
`PhpZip` - php library for manipulating zip archives.

[![Build Status](https://travis-ci.org/Ne-Lexa/php-zip.svg?branch=master)](https://travis-ci.org/Ne-Lexa/php-zip)
[![Latest Stable Version](https://poser.pugx.org/nelexa/zip/v/stable)](https://packagist.org/packages/nelexa/zip)
[![Total Downloads](https://poser.pugx.org/nelexa/zip/downloads)](https://packagist.org/packages/nelexa/zip)
[![Minimum PHP Version](http://img.shields.io/badge/php%2064bit-%3E%3D%205.5-8892BF.svg)](https://php.net/)
[![Test Coverage](https://codeclimate.com/github/Ne-Lexa/php-zip/badges/coverage.svg)](https://codeclimate.com/github/Ne-Lexa/php-zip/coverage)
[![License](https://poser.pugx.org/nelexa/zip/license)](https://packagist.org/packages/nelexa/zip)

Table of contents
-----------------
- [Features](#Features)
- [Requirements](#Requirements)
- [Installation](#Installation)
- [Examples](#Examples)
- [Documentation](#Documentation)
  + [Open Zip Archive](#Documentation-Open-Zip-Archive)
  + [Get Zip Entries](#Documentation-Open-Zip-Entries)
  + [Add Zip Entries](#Documentation-Add-Zip-Entries)
  + [ZipAlign Usage](#Documentation-ZipAlign-Usage)
  + [Save Zip File or Output](#Documentation-Save-Or-Output-Entries)
  + [Close Zip Archive](#Documentation-Close-Zip-Archive)
- [Running Tests](#Running-Tests)
- [Upgrade version 2 to version 3](#Upgrade)

### <a name="Features"></a> Features
- Opening and unzipping zip files.
- Create zip files.
- Update zip files.
- Pure php (not require extension `php-zip` and class `\ZipArchive`).
- Output the modified archive as a string or output to the browser without saving the result to disk.
- Support archive comment and entries comments.
- Get info of zip entries.
- Support zip password for PHP 5.5, include update and remove password.
- Support encryption method `Traditional PKWARE Encryption (ZipCrypto)` and `WinZIP AES Encryption`.
- Support `ZIP64` (size > 4 GiB or files > 65535 in a .ZIP archive).
- Support archive alignment functional [`zipalign`](https://developer.android.com/studio/command-line/zipalign.html).

### <a name="Requirements"></a> Requirements
- `PHP` >= 5.5 (64 bit)
- Optional php-extension `bzip2` for BZIP2 compression.
- Optional php-extension `openssl` or `mcrypt` for `WinZip Aes Encryption` support.

### <a name="Installation"></a> Installation
`composer require nelexa/zip:^3.0`

### <a name="Examples"></a> Examples
```php
// create new archive
$zipFile = new \PhpZip\ZipFile();
$zipFile
    ->addFromString("zip/entry/filename", "Is file content")
    ->addFile("/path/to/file", "data/tofile")
    ->addDir(__DIR__, "to/path/")
    ->saveAsFile($outputFilename)
    ->close();
        
// open archive, extract, add files, set password and output to browser.
$zipFile
    ->openFile($outputFilename)
    ->extractTo($outputDirExtract)
    ->deleteFromRegex('~^\.~') // delete all hidden (Unix) files
    ->addFromString('dir/file.txt', 'Test file')
    ->withNewPassword('password')
    ->outputAsAttachment('library.jar');
```
Other examples can be found in the `tests/` folder

### <a name="Documentation"></a> Documentation:
#### <a name="Documentation-Open-Zip-Archive"></a> Open Zip Archive
Open zip archive from file.
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->openFile($filename);
```
Open zip archive from data string.
```php
$zipFile = new \PhpZip\ZipFile();
$zipFile->openFromString($stringContents);
```
Open zip archive from stream resource.
```php
$stream = fopen($filename, 'rb');

$zipFile = new \PhpZip\ZipFile();
$zipFile->openFromStream($stream);
```
#### <a name="Documentation-Open-Zip-Entries"></a> Get Zip Entries
Get num entries.
```php
$count = count($zipFile);
// or
$count = $zipFile->count();
```
Get list files.
```php
$listFiles = $zipFile->getListFiles();

// Example result:
//
// $listFiles = [
//   'info.txt',
//   'path/to/file.jpg',
//   'another path/'
// ];
```
Get entry contents.
```php
// $entryName = 'path/to/example-entry-name.txt';

$contents = $zipFile[$entryName];
```
Checks whether a entry exists.
```php
// $entryName = 'path/to/example-entry-name.txt';

$hasEntry = isset($zipFile[$entryName]);
```
Check whether the directory entry.
```php
// $entryName = 'path/to/';

$isDirectory = $zipFile->isDirectory($entryName);
```
Extract all files to directory.
```php
$zipFile->extractTo($directory);
```
Extract some files to directory.
```php
$extractOnlyFiles = [
    "filename1", 
    "filename2", 
    "dir/dir/dir/"
];
$zipFile->extractTo($directory, $extractOnlyFiles);
```
Iterate zip entries.
```php
foreach($zipFile as $entryName => $dataContent){
    echo "Entry: $entryName" . PHP_EOL;
    echo "Data: $dataContent" . PHP_EOL;
    echo "-----------------------------" . PHP_EOL;
}
```
or
```php
$iterator = new \ArrayIterator($zipFile);
while ($iterator->valid())
{
    $entryName = $iterator->key();
    $dataContent = $iterator->current();

    echo "Entry: $entryName" . PHP_EOL;
    echo "Data: $dataContent" . PHP_EOL;
    echo "-----------------------------" . PHP_EOL;

    $iterator->next();
}
```
Get comment archive.
```php
$commentArchive = $zipFile->getArchiveComment();
```
Get comment zip entry.
```php
$commentEntry = $zipFile->getEntryComment($entryName);
```
Set password for read encrypted entries.
```php
$zipFile->withReadPassword($password);
```
Get entry info.
```php
$zipInfo = $zipFile->getEntryInfo('file.txt');

echo $zipInfo . PHP_EOL;

// Output:
// ZipInfo {Path="file.txt", Size=9.77KB, Compressed size=2.04KB, Modified time=2016-09-24T19:25:10+03:00, Crc=0x4b5ab5c7, Method="Deflate", Attributes="-rw-r--r--", Platform="UNIX", Version=20}

print_r($zipInfo);

// Output:
// PhpZip\Model\ZipInfo Object
// (
//     [path:PhpZip\Model\ZipInfo:private] => file.txt
//     [folder:PhpZip\Model\ZipInfo:private] => 
//     [size:PhpZip\Model\ZipInfo:private] => 10000
//     [compressedSize:PhpZip\Model\ZipInfo:private] => 2086
//     [mtime:PhpZip\Model\ZipInfo:private] => 1474734310
//     [ctime:PhpZip\Model\ZipInfo:private] => 
//     [atime:PhpZip\Model\ZipInfo:private] => 
//     [encrypted:PhpZip\Model\ZipInfo:private] => 
//     [comment:PhpZip\Model\ZipInfo:private] => 
//     [crc:PhpZip\Model\ZipInfo:private] => 1264235975
//     [method:PhpZip\Model\ZipInfo:private] => Deflate
//     [platform:PhpZip\Model\ZipInfo:private] => UNIX
//     [version:PhpZip\Model\ZipInfo:private] => 20
//     [attributes:PhpZip\Model\ZipInfo:private] => -rw-r--r--
// )
```
Get info for all entries.
```php
$zipAllInfo = $zipFile->getAllInfo();

print_r($zipAllInfo);

//Array
//(
//    [file.txt] => PhpZip\Model\ZipInfo Object
//    (
//            ...
//    )
//
//    [file2.txt] => PhpZip\Model\ZipInfo Object
//    (
//            ...
//    )
//    
//    ...
//)

```
#### <a name="Documentation-Add-Zip-Entries"></a> Add Zip Entries
Adding a file to the zip-archive.
```php
// entry name is file basename.
$zipFile->addFile($filename);
// or
$zipFile->addFile($filename, null);

// with entry name
$zipFile->addFile($filename, $entryName);
// or
$zipFile[$entryName] = new \SplFileInfo($filename);

// with compression method
$zipFile->addFile($filename, $entryName, ZipFile::METHOD_DEFLATED); // Deflate compression
$zipFile->addFile($filename, $entryName, ZipFile::METHOD_STORED); // No compression
$zipFile->addFile($filename, null, ZipFile::METHOD_BZIP2); // BZIP2 compression
```
Add entry from string data.
```php
$zipFile[$entryName] = $data;
// or
$zipFile->addFromString($entryName, $data);

// with compression method
$zipFile->addFromString($entryName, $data, ZipFile::METHOD_DEFLATED); // Deflate compression
$zipFile->addFromString($entryName, $data, ZipFile::METHOD_STORED); // No compression
$zipFile->addFromString($entryName, $data, ZipFile::METHOD_BZIP2); // BZIP2 compression
```
Add entry from stream.
```php
// $stream = fopen(...);

$zipFile->addFromStream($stream, $entryName);

// with compression method
$zipFile->addFromStream($stream, $entryName, ZipFile::METHOD_DEFLATED); // Deflate compression
$zipFile->addFromStream($stream, $entryName, ZipFile::METHOD_STORED); // No compression
$zipFile->addFromStream($stream, $entryName, ZipFile::METHOD_BZIP2); // BZIP2 compression
```
Add empty dir
```php
// $dirName = "path/to/";

$zipFile->addEmptyDir($dirName);
// or
$zipFile[$dirName] = null;
```
Add all entries form string contents.
```php
$mapData = [
    'file.txt' => 'file contents',
    'path/to/file.txt' => 'another file contents',
    'empty dir/' => null,
];

$zipFile->addAll($mapData);
```
Add a directory **not recursively** to the archive.
```php
$zipFile->addDir($dirName);

// with entry path
$localPath = "to/path/";
$zipFile->addDir($dirName, $localPath);

// with compression method for all files
$zipFile->addDir($dirName, $localPath, ZipFile::METHOD_DEFLATED); // Deflate compression
$zipFile->addDir($dirName, $localPath, ZipFile::METHOD_STORED); // No compression
$zipFile->addDir($dirName, $localPath, ZipFile::METHOD_BZIP2); // BZIP2 compression
```
Add a directory **recursively** to the archive.
```php
$zipFile->addDirRecursive($dirName);

// with entry path
$localPath = "to/path/";
$zipFile->addDirRecursive($dirName, $localPath);

// with compression method for all files
$zipFile->addDirRecursive($dirName, $localPath, ZipFile::METHOD_DEFLATED); // Deflate compression
$zipFile->addDirRecursive($dirName, $localPath, ZipFile::METHOD_STORED); // No compression
$zipFile->addDirRecursive($dirName, $localPath, ZipFile::METHOD_BZIP2); // BZIP2 compression
```
Add a files from directory iterator.
```php
// $directoryIterator = new \DirectoryIterator($dir); // not recursive
// $directoryIterator = new \RecursiveDirectoryIterator($dir); // recursive

$zipFile->addFilesFromIterator($directoryIterator);

// with entry path
$localPath = "to/path/";
$zipFile->addFilesFromIterator($directoryIterator, $localPath);
// or
$zipFile[$localPath] = $directoryIterator;

// with compression method for all files
$zipFile->addFilesFromIterator($directoryIterator, $localPath, ZipFile::METHOD_DEFLATED); // Deflate compression
$zipFile->addFilesFromIterator($directoryIterator, $localPath, ZipFile::METHOD_STORED); // No compression
$zipFile->addFilesFromIterator($directoryIterator, $localPath, ZipFile::METHOD_BZIP2); // BZIP2 compression
```
Example add a directory to the archive with ignoring files from directory iterator.
```php
$ignoreFiles = [
    "file_ignore.txt", 
    "dir_ignore/sub dir ignore/"
];

// use \DirectoryIterator for not recursive
$directoryIterator = new \RecursiveDirectoryIterator($dir);
 
// use IgnoreFilesFilterIterator for not recursive
$ignoreIterator = new IgnoreFilesRecursiveFilterIterator(
    $directoryIterator, 
    $ignoreFiles
);

$zipFile->addFilesFromIterator($ignoreIterator);
```
Add a files **recursively** from [glob pattern](https://en.wikipedia.org/wiki/Glob_(programming)) to the archive.
```php
$globPattern = '**.{jpg,jpeg,png,gif}'; // example glob pattern -> add all .jpg, .jpeg, .png and .gif files

$zipFile->addFilesFromGlobRecursive($dir, $globPattern);

// with entry path
$localPath = "to/path/";
$zipFile->addFilesFromGlobRecursive($dir, $globPattern, $localPath);

// with compression method for all files
$zipFile->addFilesFromGlobRecursive($dir, $globPattern, $localPath), ZipFile::METHOD_DEFLATED); // Deflate compression
$zipFile->addFilesFromGlobRecursive($dir, $globPattern, $localPath), ZipFile::METHOD_STORED); // No compression
$zipFile->addFilesFromGlobRecursive($dir, $globPattern, $localPath), ZipFile::METHOD_BZIP2); // BZIP2 compression
```
Add a files **not recursively** from [glob pattern](https://en.wikipedia.org/wiki/Glob_(programming)) to the archive.
```php
$globPattern = '**.{jpg,jpeg,png,gif}'; // example glob pattern -> add all .jpg, .jpeg, .png and .gif files

$zipFile->addFilesFromGlob($dir, $globPattern);

// with entry path
$localPath = "to/path/";
$zipFile->addFilesFromGlob($dir, $globPattern, $localPath);

// with compression method for all files
$zipFile->addFilesFromGlob($dir, $globPattern, $localPath), ZipFile::METHOD_DEFLATED); // Deflate compression
$zipFile->addFilesFromGlob($dir, $globPattern, $localPath), ZipFile::METHOD_STORED); // No compression
$zipFile->addFilesFromGlob($dir, $globPattern, $localPath), ZipFile::METHOD_BZIP2); // BZIP2 compression
```
Add a files **recursively** from [RegEx (Regular Expression) pattern](https://en.wikipedia.org/wiki/Regular_expression) to the archive.
```php
$regexPattern = '/\.(jpe?g|png|gif)$/si'; // example regex pattern -> add all .jpg, .jpeg, .png and .gif files

$zipFile->addFilesFromRegexRecursive($dir, $regexPattern);

// with entry path
$localPath = "to/path/";
$zipFile->addFilesFromRegexRecursive($dir, $regexPattern, $localPath);

// with compression method for all files
$zipFile->addFilesFromRegexRecursive($dir, $regexPattern, $localPath, ZipFile::METHOD_DEFLATED); // Deflate compression
$zipFile->addFilesFromRegexRecursive($dir, $regexPattern, $localPath, ZipFile::METHOD_STORED); // No compression
$zipFile->addFilesFromRegexRecursive($dir, $regexPattern, $localPath, ZipFile::METHOD_BZIP2); // BZIP2 compression
```
Add a files **not recursively** from [RegEx (Regular Expression) pattern](https://en.wikipedia.org/wiki/Regular_expression) to the archive.
```php
$regexPattern = '/\.(jpe?g|png|gif)$/si'; // example regex pattern -> add all .jpg, .jpeg, .png and .gif files

$zipFile->addFilesFromRegex($dir, $regexPattern);

// with entry path
$localPath = "to/path/";
$zipFile->addFilesFromRegex($dir, $regexPattern, $localPath);

// with compression method for all files
$zipFile->addFilesFromRegex($dir, $regexPattern, $localPath, ZipFile::METHOD_DEFLATED); // Deflate compression
$zipFile->addFilesFromRegex($dir, $regexPattern, $localPath, ZipFile::METHOD_STORED); // No compression
$zipFile->addFilesFromRegex($dir, $regexPattern, $localPath, ZipFile::METHOD_BZIP2); // BZIP2 compression
```
Rename entry name.
```php
$zipFile->rename($oldName, $newName);
```
Delete entry by name.
```php
$zipFile->deleteFromName($entryName);
```
Delete entries from [glob pattern](https://en.wikipedia.org/wiki/Glob_(programming)).
```php
$globPattern = '**.{jpg,jpeg,png,gif}'; // example glob pattern -> delete all .jpg, .jpeg, .png and .gif files

$zipFile->deleteFromGlob($globPattern);
```
Delete entries from [RegEx (Regular Expression) pattern](https://en.wikipedia.org/wiki/Regular_expression).
```php
$regexPattern = '/\.(jpe?g|png|gif)$/si'; // example regex pattern -> delete all .jpg, .jpeg, .png and .gif files

$zipFile->deleteFromRegex($regexPattern);
```
Delete all entries.
```php
$zipFile->deleteAll();
```
Sets the compression level for entries.
```php
// This property is only used if the effective compression method is DEFLATED or BZIP2.
// Legal values are ZipFile::LEVEL_DEFAULT_COMPRESSION or range from
// ZipFile::LEVEL_BEST_SPEED to ZipFile::LEVEL_BEST_COMPRESSION.

$compressionMethod = ZipFile::LEVEL_BEST_COMPRESSION;

$zipFile->setCompressionLevel($compressionLevel);
```
Set comment archive.
```php
$zipFile->setArchiveComment($commentArchive);
```
Set comment zip entry.
```php
$zipFile->setEntryComment($entryName, $entryComment);
```
Set a new password.
```php
$zipFile->withNewPassword($password);
```
Set a new password and encryption method.
```php
$encryptionMethod = ZipFile::ENCRYPTION_METHOD_WINZIP_AES; // default value
$zipFile->withNewPassword($password, $encryptionMethod);

// Support encryption methods:
// ZipFile::ENCRYPTION_METHOD_TRADITIONAL - Traditional PKWARE Encryption
// ZipFile::ENCRYPTION_METHOD_WINZIP_AES - WinZip AES Encryption
```
Remove password from all entries.
```php
$zipFile->withoutPassword();
```
#### <a name="Documentation-ZipAlign-Usage"></a> ZipAlign Usage
Set archive alignment ([`zipalign`](https://developer.android.com/studio/command-line/zipalign.html)).
```php
// before save or output
$zipFile->setAlign(4); // alternative command: zipalign -f -v 4 filename.zip
```
#### <a name="Documentation-Save-Or-Output-Entries"></a> Save Zip File or Output
Save archive to a file.
```php
$zipFile->saveAsFile($filename);
```
Save archive to a stream.
```php
// $fp = fopen($filename, 'w+b');

$zipFile->saveAsStream($fp);
```
Returns the zip archive as a string.
```php
$rawZipArchiveBytes = $zipFile->outputAsString();
```
Output .ZIP archive as attachment and terminate.
```php
$zipFile->outputAsAttachment($outputFilename);
// or set mime type
$mimeType = 'application/zip'
$zipFile->outputAsAttachment($outputFilename, $mimeType);
```
Rewrite and reopen zip archive.
```php
$zipFile->rewrite();
```
#### <a name="Documentation-Close-Zip-Archive"></a> Close Zip Archive
Close zip archive.
```php
$zipFile->close();
```
### <a name="Running-Tests"></a> Running Tests
Installing development dependencies.
```bash
composer install --dev
```
Run tests
```bash
vendor/bin/phpunit -v -c bootstrap.xml
```
### <a name="Upgrade"></a> Upgrade version 2 to version 3
Update to the New Major Version via Composer
```json
{
    "require": {
        "nelexa/zip": "^3.0"
    }
}
```
Next, use Composer to download new versions of the libraries:
```bash
composer update nelexa/zip
```
Update your Code to Work with the New Version:
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
  + `ZipOutputFile::removePasswordAllEntries` to `ZipFile::withoutPassword`
  + `ZipOutputFile::setComment` to `ZipFile::setArchiveComment`
  + `ZipFile::getComment` to `ZipFile::getArchiveComment`
- Changed signature for methods `addDir`, `addFilesFromGlob`, `addFilesFromRegex`.
- Remove methods
  + `getLevel`
  + `setCompressionMethod`
  + `setEntryPassword`


