<?php

namespace App\Services\Websites;

use App\Models\User;
use App\Models\Website;
use Exception;
use Illuminate\Support\Facades\Process;

class DeleteWebsiteException extends Exception {}

class DeleteWebsiteService
{
    public function __construct(private Website $website, private User $user) {}

    public function handle(): void
    {
        $this->deleteWebsiteFiles();
        $this->disableWebsite();
        $this->removeVhostFile();
        $this->syncPhpFpmPools();

        // TODO: delete databases associated with this website

        // if all was successful, delete website from database
        $this->website->delete();
    }

    private function deleteWebsiteFiles(): void
    {
        $deleteWebsite = Process::run('rm -rf ' . $this->website->websiteRoot);

        if ($deleteWebsite->failed()) {
            throw new DeleteWebsiteException('Failed to delete website files: ' . $deleteWebsite->errorOutput());
        }
    }

    private function disableWebsite(): void
    {
        $disableWebsite = Process::run('sudo a2dissite ' . $this->website->url . '.conf');

        if ($disableWebsite->failed()) {
            throw new DeleteWebsiteException('Failed to disable (a2dissite) website: ' . $disableWebsite->errorOutput());
        }
    }

    private function removeVhostFile(): void
    {
        $removeVhostFile = Process::run('sudo rm /etc/apache2/sites-available/' . $this->website->url . '.conf');

        if ($removeVhostFile->failed()) {
            throw new DeleteWebsiteException('Failed to remove vhost file: ' . $removeVhostFile->errorOutput());
        }
    }

    public function syncPhpFpmPools(): void
    {
        $phpVersion = $this->website->phpVersion;
        $thisPhpVersion = $phpVersion->version;

        // check if user has other websites with the same php version, do nothing
        $sitesUsingThisPHPVersion = $this->website
            ->user
            ->websites
            ->where('php_version_id', $phpVersion->id)
            ->count();


        if ($sitesUsingThisPHPVersion > 1) {
            return;
        }

        // user doesn't have other websites with the same php version, remove pool
        $removePhpFpmPool = Process::run([
            'sudo',
            config('laranode.laranode_bin_path') . '/laranode-remove-php-fpm-pool-for-user.sh',
            $this->website->user->systemUsername,
            $thisPhpVersion,
        ]);

        if ($removePhpFpmPool->failed()) {
            throw new DeleteWebsiteException('Failed to remove php-fpm pool: ' . $removePhpFpmPool->errorOutput());
        }
    }
}
