<?php

namespace NyonCode\LaravelPackageConsole\Support;

use RuntimeException;
use Symfony\Component\Finder\Finder;

class Filesystem
{
    /**
     * Create a directory
     */
    public function makeDirectory(
        $path,
        int $mode = 0755,
        bool $recursive = false,
        bool $force = false
    ): bool {
        if ($force) {
            return @mkdir($path, $mode, $recursive);
        }

        return mkdir($path, $mode, $recursive);
    }

    /**
     * Determine if the given path is a directory.
     */
    public function isDirectory(string $directory): bool
    {
        return is_dir($directory);
    }

    /**
     * Get the contents of a file.
     *
     *
     * @throws RuntimeException
     */
    public function get(string $path, bool $lock = false): string
    {
        if ($this->isFile($path)) {
            return $lock ? $this->sharedGet($path) : file_get_contents($path);
        }

        throw new RuntimeException("File does not exist at path [$path].");
    }

    /**
     * Determine if the given path is a file.
     */
    public function isFile(string $file): bool
    {
        return is_file($file);
    }

    /**
     * Get contents of a file with shared access.
     */
    public function sharedGet(string $path): string
    {
        $contents = '';

        $handle = fopen($path, 'rb');

        if ($handle) {
            try {
                if (flock($handle, LOCK_SH)) {
                    clearstatcache(true, $path);

                    $contents = fread($handle, $this->size($path) ?: 1);

                    flock($handle, LOCK_UN);
                }
            } finally {
                fclose($handle);
            }
        }

        return $contents;
    }

    /**
     * Get the file size of a given file.
     */
    public function size(string $path): int
    {
        return filesize($path);
    }

    /**
     * Check if a directory is empty.
     */
    public function isEmptyDirectory(
        string $directory,
        bool $ignoreDots = true
    ): bool {
        return ! Finder::create()
            ->in($directory)
            ->ignoreDotFiles($ignoreDots)
            ->depth(0)
            ->hasResults();
    }

    /**
     * Determine if the given path is readable.
     */
    public function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    /**
     * Determine if the given path is writable.
     */
    public function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    /**
     * Write the contents of a file.
     */
    public function put(
        string $path,
        string $contents,
        bool $lock = false
    ): int|bool {
        return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }
}
