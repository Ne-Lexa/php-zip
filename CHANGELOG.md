# Changelog

## 3.1.0 (2017-11-14)
- Added class `ZipModel` for all changes.
- All manipulations with incoming and outgoing streams are in separate files: `ZipInputStream` and `ZipOutputStream`.
- Removed class `CentralDirectory`.
- Optimized extra fields classes.
- Fixed issue #4 (`count()` returns 0 when files are added in directories).
- Implemented issue #8 - support inline Content-Disposition and empty output filename.
- Optimized and tested on a php 32-bit platform (issue #5).
- Added output as PSR-7 Response.
- Added methods for canceling changes.
- Added [russian documentation](README.RU.md).
- Updated [documentation](README.md).
- Declared deprecated methods:
  + rename `ZipFile::withReadPassword` to `ZipFile::setReadPassword`
  + rename `ZipFile::withNewPassword` to `ZipFile::setPassword`
  + rename `ZipFile::withoutPassword` to `ZipFile::disableEncryption`

## 3.0.3 (2017-11-11)
Fix bug issue #8 - Error if the file is empty.

## 3.0.0 (2017-03-15)
Merge `ZipOutputFile` with ZipFile and optimize the zip archive update.

See the update instructions in README.md.

## 2.2.0 (2017-03-02)
Features:
  - create output object `ZipOutputFile` from `ZipFile` in method `ZipFile::edit()`.
  - create output object `ZipOutputFile` from filename in static method `ZipOutputFile::openFromFile(string $filename)`.