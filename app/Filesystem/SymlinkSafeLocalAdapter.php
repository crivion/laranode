<?php

namespace App\Filesystem;

use finfo;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToListContents;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToProvideChecksum;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use RuntimeException;

/**
 * LocalFilesystemAdapter rooted at a tenant's home, with writes that refuse to
 * follow a symlink.
 *
 * The stock adapter normalizes ".." away, but DISALLOW_LINKS is only consulted
 * while listing a directory - write() and writeStream() end in file_put_contents()
 * and copy() ends in copy(), both of which happily follow a symlink sitting at
 * the destination. A tenant who can create a link in their own home (over SSH,
 * or from their own site's PHP) could therefore use the panel's own file editor
 * to write through it into another tenant's tree.
 *
 * Mutations are delegated to a helper that walks from an open tenant-home file
 * descriptor using O_NOFOLLOW. The operation therefore stays anchored to the
 * validated tree even if a tenant concurrently swaps a path component.
 *
 * Reads go through the same walk. The stock read(), readStream(), listing and
 * metadata calls are plain fopen()/stat()/DirectoryIterator on the joined path,
 * and they follow a link anywhere in it. The panel runs as www-data, which is in
 * every tenant's group, so a link in one home could read another tenant's files.
 * Reads need no privileges, so they run the helper as www-data without sudo.
 */
class SymlinkSafeLocalAdapter extends LocalFilesystemAdapter
{
    public function __construct(
        private string $location,
        int $writeFlags = LOCK_EX,
        int $linkHandling = self::DISALLOW_LINKS,
        private ?string $systemUser = null,
        private ?string $binPath = null,
        private ?int $maxReadBytes = null,
    ) {
        parent::__construct($location, null, $writeFlags, $linkHandling);
    }

    public function fileExists(string $location): bool
    {
        try {
            return ($this->statWithoutFollowing($location)['type'] ?? null) === 'file';
        } catch (RuntimeException $exception) {
            throw UnableToCheckFileExistence::forLocation($location, $exception);
        }
    }

    public function directoryExists(string $location): bool
    {
        try {
            return ($this->statWithoutFollowing($location)['type'] ?? null) === 'dir';
        } catch (RuntimeException $exception) {
            throw UnableToCheckDirectoryExistence::forLocation($location, $exception);
        }
    }

    public function read(string $path): string
    {
        try {
            return SafeFileProcess::run('read', $this->location, [$path, $this->maxReadBytes ?? '-']);
        } catch (RuntimeException $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $output = SafeFileProcess::run('list', $this->location, [trim($path, '/'), $deep ? 'recursive' : 'flat']);
        } catch (RuntimeException $exception) {
            throw UnableToListContents::atLocation($path, $deep, $exception);
        }

        $visibility = new PortableVisibilityConverter;

        foreach (explode("\n", trim($output)) as $line) {
            if ($line === '') {
                continue;
            }

            $entry = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

            yield $entry['type'] === 'dir'
                ? new DirectoryAttributes($entry['path'], $visibility->inverseForDirectory($entry['mode']), $entry['mtime'])
                : new FileAttributes($entry['path'], $entry['size'], $visibility->inverseForFile($entry['mode']), $entry['mtime']);
        }
    }

    public function fileSize(string $path): FileAttributes
    {
        return new FileAttributes($path, $this->fileMetadata($path, 'fileSize')['size']);
    }

    public function lastModified(string $path): FileAttributes
    {
        return new FileAttributes($path, lastModified: $this->fileMetadata($path, 'lastModified')['mtime']);
    }

    public function visibility(string $path): FileAttributes
    {
        $mode = $this->fileMetadata($path, 'visibility')['mode'];

        return new FileAttributes($path, visibility: (new PortableVisibilityConverter)->inverseForFile($mode));
    }

    public function mimeType(string $path): FileAttributes
    {
        try {
            $mimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer($this->read($path));
        } catch (UnableToReadFile $exception) {
            throw UnableToRetrieveMetadata::mimeType($path, $exception->getMessage(), $exception);
        }

        return new FileAttributes($path, mimeType: $mimeType ?: null);
    }

    public function checksum(string $path, Config $config): string
    {
        try {
            return hash((string) $config->get('checksum_algo', 'md5'), $this->read($path));
        } catch (UnableToReadFile $exception) {
            throw new UnableToProvideChecksum($exception->getMessage(), $path, $exception);
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->writeWithoutFollowing($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->writeWithoutFollowing($path, $contents, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            SafeFileProcess::run(
                'copy',
                $this->location,
                [$source, $destination],
                null,
                $this->systemUser,
                $this->binPath,
            );
        } catch (RuntimeException $exception) {
            throw UnableToWriteFile::atLocation($destination, $exception->getMessage());
        }

        if ($visibility = $config->get(Config::OPTION_VISIBILITY)) {
            $this->setVisibility($destination, (string) $visibility);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        // the stock createDirectory() decides "already there?" with is_dir(),
        // which follows a symlink, then calls mkdir() recursively - so a link
        // planted in the tenant's own home let directories be created inside
        // another tenant's tree
        try {
            SafeFileProcess::run(
                'directory',
                $this->location,
                [$path],
                null,
                $this->systemUser,
                $this->binPath,
            );
        } catch (RuntimeException $exception) {
            throw UnableToCreateDirectory::atLocation($path, $exception->getMessage());
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        // the stock move() is a bare rename(), and rename() resolves symlinks in
        // the destination's *directory* components - only the trailing one is
        // exempt - so a link planted in the tenant's own home redirected the
        // move into another tenant's tree
        try {
            SafeFileProcess::run(
                'rename',
                $this->location,
                [$source, $destination],
                null,
                $this->systemUser,
                $this->binPath,
            );
        } catch (RuntimeException $exception) {
            throw UnableToMoveFile::because($exception->getMessage(), $source, $destination);
        }

        if ($visibility = $config->get(Config::OPTION_VISIBILITY)) {
            $this->setVisibility($destination, (string) $visibility);
        }
    }

    public function delete(string $path): void
    {
        // unlink() leaves a trailing symlink alone but still resolves every
        // component before it
        try {
            SafeFileProcess::run(
                'remove',
                $this->location,
                [$path, 'file'],
                null,
                $this->systemUser,
                $this->binPath,
            );
        } catch (RuntimeException $exception) {
            throw UnableToDeleteFile::atLocation($path, $exception->getMessage());
        }
    }

    public function deleteDirectory(string $path): void
    {
        // the stock recursive delete resolves the path with is_dir(), then
        // unlinks through getRealPath() - pointed at a link it wiped whatever
        // tree the link led to. The helper descends with O_NOFOLLOW instead and
        // unlinks any nested symlink rather than following it.
        try {
            SafeFileProcess::run(
                'remove',
                $this->location,
                [$path, 'recursive'],
                null,
                $this->systemUser,
                $this->binPath,
            );
        } catch (RuntimeException $exception) {
            throw UnableToDeleteDirectory::atLocation($path, $exception->getMessage());
        }
    }

    /** @return array{type: string, size: int, mtime: int, mode: int}|null */
    private function statWithoutFollowing(string $path): ?array
    {
        return json_decode(SafeFileProcess::run('stat', $this->location, [trim($path, '/')]), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array{type: string, size: int, mtime: int, mode: int} */
    private function fileMetadata(string $path, string $type): array
    {
        try {
            $details = $this->statWithoutFollowing($path);
        } catch (RuntimeException $exception) {
            throw UnableToRetrieveMetadata::create($path, $type, $exception->getMessage(), $exception);
        }

        if (($details['type'] ?? null) !== 'file') {
            throw UnableToRetrieveMetadata::create($path, $type, 'Not a regular file');
        }

        return $details;
    }

    /**
     * @param  resource|string  $contents
     */
    private function writeWithoutFollowing(string $path, $contents, Config $config): void
    {
        try {
            SafeFileProcess::run(
                'replace',
                $this->location,
                [$path],
                $contents,
                $this->systemUser,
                $this->binPath,
            );
        } catch (RuntimeException $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage());
        }

        if ($visibility = $config->get(Config::OPTION_VISIBILITY)) {
            $this->setVisibility($path, (string) $visibility);
        }
    }
}
