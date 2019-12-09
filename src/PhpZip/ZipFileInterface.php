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
 *
 * @author Ne-Lexa alexey@nelexa.ru
 * @license MIT
 */
interface ZipFileInterface extends \Countable, \ArrayAccess, \Iterator
{
    /**
     * Method for Stored (uncompressed) entries.
     *
     * @see ZipEntry::setMethod()
     */
    const METHOD_STORED = 0;

    /**
     * Method for Deflated compressed entries.
     *
     * @see ZipEntry::setMethod()
     */
    const METHOD_DEFLATED = 8;

    /**
     * Method for BZIP2 compressed entries.
     * Require php extension bz2.
     *
     * @see ZipEntry::setMethod()
     */
    const METHOD_BZIP2 = 12;

    /** Default compression level. */
    const LEVEL_DEFAULT_COMPRESSION = -1;

    /** Compression level for fastest compression. */
    const LEVEL_FAST = 2;

    /** Compression level for fastest compression. */
    const LEVEL_BEST_SPEED = 1;

    const LEVEL_SUPER_FAST = self::LEVEL_BEST_SPEED;

    /** Compression level for best compression. */
    const LEVEL_BEST_COMPRESSION = 9;

    /** No specified method for set encryption method to Traditional PKWARE encryption. */
    const ENCRYPTION_METHOD_TRADITIONAL = 0;

    /**
     * No specified method for set encryption method to WinZip AES encryption.
     * Default value 256 bit.
     */
    const ENCRYPTION_METHOD_WINZIP_AES = self::ENCRYPTION_METHOD_WINZIP_AES_256;

    /** No specified method for set encryption method to WinZip AES encryption 128 bit. */
    const ENCRYPTION_METHOD_WINZIP_AES_128 = 2;

    /** No specified method for set encryption method to WinZip AES encryption 194 bit. */
    const ENCRYPTION_METHOD_WINZIP_AES_192 = 3;

    /** No specified method for set encryption method to WinZip AES encryption 256 bit. */
    const ENCRYPTION_METHOD_WINZIP_AES_256 = 1;

    /**
     * Open zip archive from file.
     *
     * @param string $filename
     *
     * @throws ZipException if can't open file
     *
     * @return ZipFileInterface
     */
    public function openFile($filename);

    /**
     * Open zip archive from raw string data.
     *
     * @param string $data
     *
     * @throws ZipException if can't open temp stream
     *
     * @return ZipFileInterface
     */
    public function openFromString($data);

    /**
     * Open zip archive from stream resource.
     *
     * @param resource $handle
     *
     * @return ZipFileInterface
     */
    public function openFromStream($handle);

    /**
     * @return string[] returns the list files
     */
    public function getListFiles();

    /**
     * Returns the file comment.
     *
     * @return string the file comment
     */
    public function getArchiveComment();

    /**
     * Set archive comment.
     *
     * @param string|null $comment
     *
     * @return ZipFileInterface
     */
    public function setArchiveComment($comment = null);

    /**
     * Checks that the entry in the archive is a directory.
     * Returns true if and only if this ZIP entry represents a directory entry
     * (i.e. end with '/').
     *
     * @param string $entryName
     *
     * @throws ZipEntryNotFoundException
     *
     * @return bool
     */
    public function isDirectory($entryName);

    /**
     * Returns entry comment.
     *
     * @param string $entryName
     *
     * @throws ZipEntryNotFoundException
     *
     * @return string
     */
    public function getEntryComment($entryName);

    /**
     * Set entry comment.
     *
     * @param string      $entryName
     * @param string|null $comment
     *
     * @throws ZipEntryNotFoundException
     *
     * @return ZipFileInterface
     */
    public function setEntryComment($entryName, $comment = null);

    /**
     * Returns the entry contents.
     *
     * @param string $entryName
     *
     * @return string
     */
    public function getEntryContents($entryName);

    /**
     * Checks if there is an entry in the archive.
     *
     * @param string $entryName
     *
     * @return bool
     */
    public function hasEntry($entryName);

    /**
     * Get info by entry.
     *
     * @param string|ZipEntry $entryName
     *
     * @throws ZipEntryNotFoundException
     *
     * @return ZipInfo
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
     * Extract the archive contents.
     *
     * Extract the complete archive or the given files to the specified destination.
     *
     * @param string            $destination location where to extract the files
     * @param array|string|null $entries     The entries to extract. It accepts either
     *                                       a single entry name or an array of names.
     *
     * @throws ZipException
     *
     * @return ZipFileInterface
     */
    public function extractTo($destination, $entries = null);

    /**
     * Add entry from the string.
     *
     * @param string   $localName         zip entry name
     * @param string   $contents          string contents
     * @param int|null $compressionMethod Compression method.
     *                                    Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                                    If null, then auto choosing method.
     *
     * @return ZipFileInterface
     *
     * @see ZipFile::METHOD_STORED
     * @see ZipFile::METHOD_DEFLATED
     * @see ZipFile::METHOD_BZIP2
     */
    public function addFromString($localName, $contents, $compressionMethod = null);

    /**
     * Add entry from the file.
     *
     * @param string      $filename          destination file
     * @param string|null $localName         zip Entry name
     * @param int|null    $compressionMethod Compression method.
     *                                       Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or
     *                                       ZipFile::METHOD_BZIP2. If null, then auto choosing method.
     *
     * @return ZipFileInterface
     *
     * @see ZipFile::METHOD_STORED
     * @see ZipFile::METHOD_DEFLATED
     * @see ZipFile::METHOD_BZIP2
     */
    public function addFile($filename, $localName = null, $compressionMethod = null);

    /**
     * Add entry from the stream.
     *
     * @param resource $stream            stream resource
     * @param string   $localName         zip Entry name
     * @param int|null $compressionMethod Compression method.
     *                                    Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                                    If null, then auto choosing method.
     *
     * @return ZipFileInterface
     *
     * @see ZipFile::METHOD_STORED
     * @see ZipFile::METHOD_DEFLATED
     * @see ZipFile::METHOD_BZIP2
     */
    public function addFromStream($stream, $localName, $compressionMethod = null);

    /**
     * Add an empty directory in the zip archive.
     *
     * @param string $dirName
     *
     * @return ZipFileInterface
     */
    public function addEmptyDir($dirName);

    /**
     * Add directory not recursively to the zip archive.
     *
     * @param string   $inputDir          Input directory
     * @param string   $localPath         add files to this directory, or the root
     * @param int|null $compressionMethod Compression method.
     *                                    Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                                    If null, then auto choosing method.
     *
     * @return ZipFileInterface
     */
    public function addDir($inputDir, $localPath = '/', $compressionMethod = null);

    /**
     * Add recursive directory to the zip archive.
     *
     * @param string   $inputDir          Input directory
     * @param string   $localPath         add files to this directory, or the root
     * @param int|null $compressionMethod Compression method.
     *                                    Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or ZipFile::METHOD_BZIP2.
     *                                    If null, then auto choosing method.
     *
     * @return ZipFileInterface
     *
     * @see ZipFile::METHOD_STORED
     * @see ZipFile::METHOD_DEFLATED
     * @see ZipFile::METHOD_BZIP2
     */
    public function addDirRecursive($inputDir, $localPath = '/', $compressionMethod = null);

    /**
     * Add directories from directory iterator.
     *
     * @param \Iterator $iterator          directory iterator
     * @param string    $localPath         add files to this directory, or the root
     * @param int|null  $compressionMethod Compression method.
     *                                     Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or
     *                                     ZipFile::METHOD_BZIP2. If null, then auto choosing method.
     *
     * @return ZipFileInterface
     *
     * @see ZipFile::METHOD_STORED
     * @see ZipFile::METHOD_DEFLATED
     * @see ZipFile::METHOD_BZIP2
     */
    public function addFilesFromIterator(\Iterator $iterator, $localPath = '/', $compressionMethod = null);

    /**
     * Add files from glob pattern.
     *
     * @param string      $inputDir          Input directory
     * @param string      $globPattern       glob pattern
     * @param string|null $localPath         add files to this directory, or the root
     * @param int|null    $compressionMethod Compression method.
     *                                       Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or
     *                                       ZipFile::METHOD_BZIP2. If null, then auto choosing method.
     *
     * @return ZipFileInterface
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function addFilesFromGlob($inputDir, $globPattern, $localPath = '/', $compressionMethod = null);

    /**
     * Add files recursively from glob pattern.
     *
     * @param string      $inputDir          Input directory
     * @param string      $globPattern       glob pattern
     * @param string|null $localPath         add files to this directory, or the root
     * @param int|null    $compressionMethod Compression method.
     *                                       Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or
     *                                       ZipFile::METHOD_BZIP2. If null, then auto choosing method.
     *
     * @return ZipFileInterface
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function addFilesFromGlobRecursive($inputDir, $globPattern, $localPath = '/', $compressionMethod = null);

    /**
     * Add files from regex pattern.
     *
     * @param string      $inputDir          search files in this directory
     * @param string      $regexPattern      regex pattern
     * @param string|null $localPath         add files to this directory, or the root
     * @param int|null    $compressionMethod Compression method.
     *                                       Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or
     *                                       ZipFile::METHOD_BZIP2. If null, then auto choosing method.
     *
     * @return ZipFileInterface
     *
     * @internal param bool $recursive Recursive search
     */
    public function addFilesFromRegex($inputDir, $regexPattern, $localPath = '/', $compressionMethod = null);

    /**
     * Add files recursively from regex pattern.
     *
     * @param string      $inputDir          search files in this directory
     * @param string      $regexPattern      regex pattern
     * @param string|null $localPath         add files to this directory, or the root
     * @param int|null    $compressionMethod Compression method.
     *                                       Use ZipFile::METHOD_STORED, ZipFile::METHOD_DEFLATED or
     *                                       ZipFile::METHOD_BZIP2. If null, then auto choosing method.
     *
     * @return ZipFileInterface
     *
     * @internal param bool $recursive Recursive search
     */
    public function addFilesFromRegexRecursive($inputDir, $regexPattern, $localPath = '/', $compressionMethod = null);

    /**
     * Add array data to archive.
     * Keys is local names.
     * Values is contents.
     *
     * @param array $mapData associative array for added to zip
     */
    public function addAll(array $mapData);

    /**
     * Rename the entry.
     *
     * @param string $oldName old entry name
     * @param string $newName new entry name
     *
     * @throws ZipEntryNotFoundException
     *
     * @return ZipFileInterface
     */
    public function rename($oldName, $newName);

    /**
     * Delete entry by name.
     *
     * @param string $entryName zip Entry name
     *
     * @throws ZipEntryNotFoundException if entry not found
     *
     * @return ZipFileInterface
     */
    public function deleteFromName($entryName);

    /**
     * Delete entries by glob pattern.
     *
     * @param string $globPattern Glob pattern
     *
     * @return ZipFileInterface
     * @sse https://en.wikipedia.org/wiki/Glob_(programming) Glob pattern syntax
     */
    public function deleteFromGlob($globPattern);

    /**
     * Delete entries by regex pattern.
     *
     * @param string $regexPattern Regex pattern
     *
     * @return ZipFileInterface
     */
    public function deleteFromRegex($regexPattern);

    /**
     * Delete all entries.
     *
     * @return ZipFileInterface
     */
    public function deleteAll();

    /**
     * Set compression level for new entries.
     *
     * @param int $compressionLevel
     *
     * @return ZipFileInterface
     *
     * @see ZipFile::LEVEL_SUPER_FAST
     * @see ZipFile::LEVEL_FAST
     * @see ZipFile::LEVEL_BEST_COMPRESSION
     * @see ZipFile::LEVEL_DEFAULT_COMPRESSION
     */
    public function setCompressionLevel($compressionLevel = self::LEVEL_DEFAULT_COMPRESSION);

    /**
     * @param string $entryName
     * @param int    $compressionLevel
     *
     * @throws ZipException
     *
     * @return ZipFileInterface
     *
     * @see ZipFile::LEVEL_DEFAULT_COMPRESSION
     * @see ZipFile::LEVEL_SUPER_FAST
     * @see ZipFile::LEVEL_FAST
     * @see ZipFile::LEVEL_BEST_COMPRESSION
     */
    public function setCompressionLevelEntry($entryName, $compressionLevel);

    /**
     * @param string $entryName
     * @param int    $compressionMethod
     *
     * @throws ZipException
     *
     * @return ZipFileInterface
     *
     * @see ZipFile::METHOD_STORED
     * @see ZipFile::METHOD_DEFLATED
     * @see ZipFile::METHOD_BZIP2
     */
    public function setCompressionMethodEntry($entryName, $compressionMethod);

    /**
     * zipalign is optimization to Android application (APK) files.
     *
     * @param int|null $align
     *
     * @return ZipFileInterface
     *
     * @see https://developer.android.com/studio/command-line/zipalign.html
     */
    public function setZipAlign($align = null);

    /**
     * Set password to all input encrypted entries.
     *
     * @param string $password Password
     *
     * @return ZipFileInterface
     *
     * @deprecated using ZipFile::setReadPassword()
     */
    public function withReadPassword($password);

    /**
     * Set password to all input encrypted entries.
     *
     * @param string $password Password
     *
     * @return ZipFileInterface
     */
    public function setReadPassword($password);

    /**
     * Set password to concrete input entry.
     *
     * @param string $entryName
     * @param string $password  Password
     *
     * @return ZipFileInterface
     */
    public function setReadPasswordEntry($entryName, $password);

    /**
     * Set password for all entries for update.
     *
     * @param string   $password         If password null then encryption clear
     * @param int|null $encryptionMethod Encryption method
     *
     * @return ZipFileInterface
     *
     * @deprecated using ZipFile::setPassword()
     */
    public function withNewPassword($password, $encryptionMethod = self::ENCRYPTION_METHOD_WINZIP_AES_256);

    /**
     * Sets a new password for all files in the archive.
     *
     * @param string   $password
     * @param int|null $encryptionMethod Encryption method
     *
     * @return ZipFileInterface
     */
    public function setPassword($password, $encryptionMethod = self::ENCRYPTION_METHOD_WINZIP_AES_256);

    /**
     * Sets a new password of an entry defined by its name.
     *
     * @param string   $entryName
     * @param string   $password
     * @param int|null $encryptionMethod
     *
     * @return ZipFileInterface
     */
    public function setPasswordEntry($entryName, $password, $encryptionMethod = null);

    /**
     * Remove password for all entries for update.
     *
     * @return ZipFileInterface
     *
     * @deprecated using ZipFile::disableEncryption()
     */
    public function withoutPassword();

    /**
     * Disable encryption for all entries that are already in the archive.
     *
     * @return ZipFileInterface
     */
    public function disableEncryption();

    /**
     * Disable encryption of an entry defined by its name.
     *
     * @param string $entryName
     *
     * @return ZipFileInterface
     */
    public function disableEncryptionEntry($entryName);

    /**
     * Undo all changes done in the archive.
     *
     * @return ZipFileInterface
     */
    public function unchangeAll();

    /**
     * Undo change archive comment.
     *
     * @return ZipFileInterface
     */
    public function unchangeArchiveComment();

    /**
     * Revert all changes done to an entry with the given name.
     *
     * @param string|ZipEntry $entry Entry name or ZipEntry
     *
     * @return ZipFileInterface
     */
    public function unchangeEntry($entry);

    /**
     * Save as file.
     *
     * @param string $filename Output filename
     *
     * @throws ZipException
     *
     * @return ZipFileInterface
     */
    public function saveAsFile($filename);

    /**
     * Save as stream.
     *
     * @param resource $handle Output stream resource
     *
     * @throws ZipException
     *
     * @return ZipFileInterface
     */
    public function saveAsStream($handle);

    /**
     * Output .ZIP archive as attachment.
     * Die after output.
     *
     * @param string      $outputFilename Output filename
     * @param string|null $mimeType       Mime-Type
     * @param bool        $attachment     Http Header 'Content-Disposition' if true then attachment otherwise inline
     */
    public function outputAsAttachment($outputFilename, $mimeType = null, $attachment = true);

    /**
     * Output .ZIP archive as PSR-7 Response.
     *
     * @param ResponseInterface $response       Instance PSR-7 Response
     * @param string            $outputFilename Output filename
     * @param string|null       $mimeType       Mime-Type
     * @param bool              $attachment     Http Header 'Content-Disposition' if true then attachment otherwise inline
     *
     * @return ResponseInterface
     */
    public function outputAsResponse(
        ResponseInterface $response,
        $outputFilename,
        $mimeType = null,
        $attachment = true
    );

    /**
     * Returns the zip archive as a string.
     *
     * @return string
     */
    public function outputAsString();

    /**
     * Save and reopen zip archive.
     *
     * @throws ZipException
     *
     * @return ZipFileInterface
     */
    public function rewrite();

    /**
     * Close zip archive and release input stream.
     */
    public function close();
}
