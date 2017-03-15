## 3.0.0 (2017-03-15)
Merge `ZipOutputFile` with ZipFile and optimize the zip archive update.

See the update instructions in README.md.

## 2.2.0 (2017-03-02)
Features:
  - create output object `ZipOutputFile` from `ZipFile` in method `ZipFile::edit()`.
  - create output object `ZipOutputFile` from filename in static method `ZipOutputFile::openFromFile(string $filename)`.