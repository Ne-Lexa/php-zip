<?php

namespace PhpZip;

use PhpZip\Exception\ZipEntryNotFoundException;
use PhpZip\Exception\ZipException;
use PhpZip\Model\ZipEntry;
use PhpZip\Model\ZipEntryMatcher;
use PhpZip\Model\ZipInfo;
use Psr\Http\Message\ResponseInterface;

/**
 * Create, open .ZIP files, modify, get info and extract files.
 *
 * Implemented support traditional PKWARE encryption and WinZip AES encryption.
 * Implemented support ZIP64.
 * Implemented support skip a preamble like the one found in self extracting archives.
 * Support ZipAlign functional.
 *
 * @see https://pkware.cachefly.net/webdocs/casestudies/APPNOTE.TXT .ZIP File Format Specification
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
interface ZipFileInterface extends \Countable, \ArrayAccess, \Iterator
{
    /**
     * Method for Stored (uncompressed) entries.
     * @see ZipEntry::setMethod()
     */
    const METHOD_STORED = 0;
    /**
     * Method for Deflated compressed entries.
     * @see ZipEntry::setMethod()
     */
    const METHOD_DEFLATED = 8;
    /**
     * Method for BZIP2 compressed entries.
     * Require php extension bz2.
     * @see ZipEntry::setMethod()
     */
    const METHOD_BZIP2 = 12;

    /**
     * Default compression level.
     */
    const LEVEL_DEFAULT_COMPRESSION = -1;
    /**
     * Compression level for fastest compression.
     */
    const LEVEL_FAST = 2;
    /**
     * Compression level for fastest compression.
     */
    const LEVEL_BEST_SPEED = 1;
    const LEVEL_SUPER_FAST = self::LEVEL_BEST_SPEED;
    /**
     * Compression level for best compression.
     */
    const LEVEL_BEST_COMPRESSION = 9;

    /**
     * No specified method for set encryption method to Traditional PKWARE encryption.
     */
    const ENCRYPTION_METHOD_TRADITIONAL = 0;
    /**
     * No specified method for set encryption method to WinZip AES encryption.
     * Default value 256 bit
     */
    const ENCRYPTION_METHOD_WINZIP_AES = self::ENCRYPTION_METHOD_WINZIP_AES_256;
    /**
     * No specified method for set encryption method to WinZip AES encryption 128 bit.
     */
    const ENCRYPTION_METHOD_WINZIP_AES_128 = 2;
    /**
     * No specified method for set encryption method to WinZip AES encryption 194 bit.
     */
    const ENCRYPTION_METHOD_WINZIP_AES_192 = 3;
    /**
     * No specified method for set encryption method to WinZip AES encryption 256 bit.
     */
    const ENCRYPTION_METHOD_WINZIP_AES_256 = 1;

    /**
     * Open zip archive from file
     *
     * @param string $filename
     * @return ZipFileInterface
     * @throws ZipException             if can't open file.
     */
    public function openFile($filename);

    /**
     * Open zip archive from raw string data.
     *
     * @param string $data
     * @return ZipFileInterface
     * @throws ZipException             if can't open temp stream.
     */
    public function openFromString($data);

    /**
     * Open zip archive from stream resource
     *
     * @param resource $handle
     * @return ZipFileInterface
     */
    public function openFromStream($handle);

    /**
     * @return string[] Returns the list files.
     */
    public function getListFiles();

    /**
     * Returns the file comment.
     *
     * @return string The file comment.
     */
    public function getArchiveComment();

    /**
     * Set archive comment.
     *
     * @param null|string $comment
     * @return ZipFileInterface
     */
    public function setArchiveComment($comment = null);

    /**
     * Checks that the entry in the archive is a directory.
     * Returns true if and only if this ZIP entry represents a directory entry
     * (i.e. end with '/').
     *
     * @param string $entryName
     * @return bool
     * @throws ZipEntryNotFoundException
     */
    public function isDirectory($entryName);

    /**
     * Returns entry comment.
     *
     * @param string $entryName
     * @return string
     * @throws ZipEntryNotFoundException
     */
    public function getEntryComment($entryName);

    /**
     * Set entry comment.
     *
     * @param string $entryName
     * @param string|null $comment
     * @return ZipFileInterface
     * @throws ZipEntryNotFoundException
     */
    public function setEntryComment($entryName, $comment = null);

    /**
     * Returns the entry contents.
     *
     * @param string $entryName
     * @return string
     */
    public function getEntryContents($entryName);

    /**
     * Checks if there is an entry in the archive.
     *
     * @param string $entryName
     * @return bool
     */
    public function hasEntry($entryName);

    /**
     * Get info by entry.
     *
     * @param string|ZipEntry $entryName
     * @return ZipInfo
     * @throws ZipEntryNotFoundException
     */
    public function getEntryInfo($entryName);

    /**
     * Get info by all entries.
     *
     * @return ZipInfo[]
     */
    public function getAllInfo();

    /**
     * @return ZipEntryMatcher
     */
    public function matcher();

    /**
     * Extract the archive contents
     *
     * Extract the complete archive or the given files to the specified destination.
     *
     * @param string $destination Location where to extract the files.
     * @param array|string|null $entries The entries to extract. It accepts either
     *                                   a single entry name or an array of names.
     * @return ZipFileInterface
     * @throws ZipException
     */
    public function extractTo($destination, $entries = null);

    /**
     * Add entry from the string.
     *
     * @param string $localName Zip entry name.
     * @param string $contents String contents.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @see ZipFileInterface::METHOD_STORED
     * @see ZipFileInterface::METHOD_DEFLATED
     * @see ZipFileInterface::METHOD_BZIP2
     */
    public function addFromString($localName, $contents, $compressionMethod = null);

    /**
     * Add entry from the file.
     *
     * @param string $filename Destination file.
     * @param string|null $localName Zip Entry name.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @see ZipFileInterface::METHOD_STORED
     * @see ZipFileInterface::METHOD_DEFLATED
     * @see ZipFileInterface::METHOD_BZIP2
     */
    public function addFile($filename, $localName = null, $compressionMethod = null);

    /**
     * Add entry from the stream.
     *
     * @param resource $stream Stream resource.
     * @param string $localName Zip Entry name.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @see ZipFileInterface::METHOD_STORED
     * @see ZipFileInterface::METHOD_DEFLATED
     * @see ZipFileInterface::METHOD_BZIP2
     */
    public function addFromStream($stream, $localName, $compressionMethod = null);

    /**
     * Add an empty directory in the zip archive.
     *
     * @param string $dirName
     * @return ZipFileInterface
     */
    public function addEmptyDir($dirName);

    /**
     * Add directory not recursively to the zip archive.
     *
     * @param string $inputDir Input directory
     * @param string $localPath Add files to this directory, or the root.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     */
    public function addDir($inputDir, $localPath = "/", $compressionMethod = null);

    /**
     * Add recursive directory to the zip archive.
     *
     * @param string $inputDir Input directory
     * @param string $localPath Add files to this directory, or the root.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @see ZipFileInterface::METHOD_STORED
     * @see ZipFileInterface::METHOD_DEFLATED
     * @see ZipFileInterface::METHOD_BZIP2
     */
    public function addDirRecursive($inputDir, $localPath = "/", $compressionMethod = null);

    /**
     * Add directories from directory iterator.
     *
     * @param \Iterator $iterator Directory iterator.
     * @param string $localPath Add files to this directory, or the root.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @see ZipFileInterface::METHOD_STORED
     * @see ZipFileInterface::METHOD_DEFLATED
     * @see ZipFileInterface::METHOD_BZIP2
     */
    public function addFilesFromIterator(\Iterator $iterator, $localPath = '/', $compressionMethod = null);

    /**
     * Add files from glob pattern.
     *
     * @param string $inputDir Input directory
     * @param string $globPattern Glob pattern.
     * @param string|null $localPath Add files to this directory, or the root.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function addFilesFromGlob($inputDir, $globPattern, $localPath = '/', $compressionMethod = null);

    /**
     * Add files recursively from glob pattern.
     *
     * @param string $inputDir Input directory
     * @param string $globPattern Glob pattern.
     * @param string|null $localPath Add files to this directory, or the root.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function addFilesFromGlobRecursive($inputDir, $globPattern, $localPath = '/', $compressionMethod = null);

    /**
     * Add files from regex pattern.
     *
     * @param string $inputDir Search files in this directory.
     * @param string $regexPattern Regex pattern.
     * @param string|null $localPath Add files to this directory, or the root.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @internal param bool $recursive Recursive search.
     */
    public function addFilesFromRegex($inputDir, $regexPattern, $localPath = "/", $compressionMethod = null);

    /**
     * Add files recursively from regex pattern.
     *
     * @param string $inputDir Search files in this directory.
     * @param string $regexPattern Regex pattern.
     * @param string|null $localPath Add files to this directory, or the root.
     * @param int|null $compressionMethod Compression method.
     *                 Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                 If null, then auto choosing method.
     * @return ZipFileInterface
     * @internal param bool $recursive Recursive search.
     */
    public function addFilesFromRegexRecursive($inputDir, $regexPattern, $localPath = "/", $compressionMethod = null);

    /**
     * Add array data to archive.
     * Keys is local names.
     * Values is contents.
     *
     * @param array $mapData Associative array for added to zip.
     */
    public function addAll(array $mapData);

    /**
     * Rename the entry.
     *
     * @param string $oldName Old entry name.
     * @param string $newName New entry name.
     * @return ZipFileInterface
     * @throws ZipEntryNotFoundException
     */
    public function rename($oldName, $newName);

    /**
     * Delete entry by name.
     *
     * @param string $entryName Zip Entry name.
     * @return ZipFileInterface
     * @throws ZipEntryNotFoundException If entry not found.
     */
    public function deleteFromName($entryName);

    /**
     * Delete entries by glob pattern.
     *
     * @param string $globPattern Glob pattern
     * @return ZipFileInterface
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function deleteFromGlob($globPattern);

    /**
     * Delete entries by regex pattern.
     *
     * @param string $regexPattern Regex pattern
     * @return ZipFileInterface
     */
    public function deleteFromRegex($regexPattern);

    /**
     * Delete all entries
     * @return ZipFileInterface
     */
    public function deleteAll();

    /**
     * Set compression level for new entries.
     *
     * @param int $compressionLevel
     * @see ZipFileInterface::LEVEL_DEFAULT_COMPRESSION
     * @see ZipFileInterface::LEVEL_SUPER_FAST
     * @see ZipFileInterface::LEVEL_FAST
     * @see ZipFileInterface::LEVEL_BEST_COMPRESSION
     * @return ZipFileInterface
     */
    public function setCompressionLevel($compressionLevel = self::LEVEL_DEFAULT_COMPRESSION);

    /**
     * @param string $entryName
     * @param int $compressionLevel
     * @return ZipFileInterface
     * @throws ZipException
     * @see ZipFileInterface::LEVEL_DEFAULT_COMPRESSION
     * @see ZipFileInterface::LEVEL_SUPER_FAST
     * @see ZipFileInterface::LEVEL_FAST
     * @see ZipFileInterface::LEVEL_BEST_COMPRESSION
     */
    public function setCompressionLevelEntry($entryName, $compressionLevel);

    /**
     * @param string $entryName
     * @param int $compressionMethod
     * @return ZipFileInterface
     * @throws ZipException
     * @see ZipFileInterface::METHOD_STORED
     * @see ZipFileInterface::METHOD_DEFLATED
     * @see ZipFileInterface::METHOD_BZIP2
     */
    public function setCompressionMethodEntry($entryName, $compressionMethod);

    /**
     * zipalign is optimization to Android application (APK) files.
     *
     * @param int|null $align
     * @return ZipFileInterface
     * @link https://developer.android.com/studio/command-line/zipalign.html
     */
    public function setZipAlign($align = null);

    /**
     * Set password to all input encrypted entries.
     *
     * @param string $password Password
     * @return ZipFileInterface
     * @deprecated using ZipFileInterface::setReadPassword()
     */
    public function withReadPassword($password);

    /**
     * Set password to all input encrypted entries.
     *
     * @param string $password Password
     * @return ZipFileInterface
     */
    public function setReadPassword($password);

    /**
     * Set password to concrete input entry.
     *
     * @param string $entryName
     * @param string $password Password
     * @return ZipFileInterface
     */
    public function setReadPasswordEntry($entryName, $password);

    /**
     * Set password for all entries for update.
     *
     * @param string $password If password null then encryption clear
     * @param int|null $encryptionMethod Encryption method
     * @return ZipFileInterface
     * @deprecated using ZipFileInterface::setPassword()
     */
    public function withNewPassword($password, $encryptionMethod = self::ENCRYPTION_METHOD_WINZIP_AES_256);

    /**
     * Sets a new password for all files in the archive.
     *
     * @param string $password
     * @param int|null $encryptionMethod Encryption method
     * @return ZipFileInterface
     */
    public function setPassword($password, $encryptionMethod = self::ENCRYPTION_METHOD_WINZIP_AES_256);

    /**
     * Sets a new password of an entry defined by its name.
     *
     * @param string $entryName
     * @param string $password
     * @param int|null $encryptionMethod
     * @return ZipFileInterface
     */
    public function setPasswordEntry($entryName, $password, $encryptionMethod = null);

    /**
     * Remove password for all entries for update.
     * @return ZipFileInterface
     * @deprecated using ZipFileInterface::disableEncryption()
     */
    public function withoutPassword();

    /**
     * Disable encryption for all entries that are already in the archive.
     * @return ZipFileInterface
     */
    public function disableEncryption();

    /**
     * Disable encryption of an entry defined by its name.
     * @param string $entryName
     * @return ZipFileInterface
     */
    public function disableEncryptionEntry($entryName);

    /**
     * Undo all changes done in the archive
     * @return ZipFileInterface
     */
    public function unchangeAll();

    /**
     * Undo change archive comment
     * @return ZipFileInterface
     */
    public function unchangeArchiveComment();

    /**
     * Revert all changes done to an entry with the given name.
     *
     * @param string|ZipEntry $entry Entry name or ZipEntry
     * @return ZipFileInterface
     */
    public function unchangeEntry($entry);

    /**
     * Save as file.
     *
     * @param string $filename Output filename
     * @return ZipFileInterface
     * @throws ZipException
     */
    public function saveAsFile($filename);

    /**
     * Save as stream.
     *
     * @param resource $handle Output stream resource
     * @return ZipFileInterface
     * @throws ZipException
     */
    public function saveAsStream($handle);

    /**
     * Output .ZIP archive as attachment.
     * Die after output.
     *
     * @param string $outputFilename Output filename
     * @param string|null $mimeType Mime-Type
     * @param bool $attachment Http Header 'Content-Disposition' if true then attachment otherwise inline
     */
    public function outputAsAttachment($outputFilename, $mimeType = null, $attachment = true);

    /**
     * Output .ZIP archive as PSR-7 Response.
     *
     * @param ResponseInterface $response Instance PSR-7 Response
     * @param string $outputFilename Output filename
     * @param string|null $mimeType Mime-Type
     * @param bool $attachment Http Header 'Content-Disposition' if true then attachment otherwise inline
     * @return ResponseInterface
     */
    public function outputAsResponse(ResponseInterface $response, $outputFilename, $mimeType = null, $attachment = true);

    /**
     * Returns the zip archive as a string.
     * @return string
     */
    public function outputAsString();

    /**
     * Save and reopen zip archive.
     * @return ZipFileInterface
     * @throws ZipException
     */
    public function rewrite();

    /**
     * Close zip archive and release input stream.
     */
    public function close();
}
