<?php

namespace App\Filesystem;

use League\Flysystem\Config;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToWriteFile;
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
 */
class SymlinkSafeLocalAdapter extends LocalFilesystemAdapter
{
    public function __construct(
        private string $location,
        int $writeFlags = LOCK_EX,
        int $linkHandling = self::DISALLOW_LINKS,
        private ?string $systemUser = null,
        private ?string $binPath = null,
    ) {
        parent::__construct($location, null, $writeFlags, $linkHandling);
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
