<?php

namespace Database\Seeders;

use App\Models\Backup;
use App\Models\BackupDestination;
use App\Models\BackupSchedule;
use App\Models\Database;
use App\Models\PhpVersion;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(config('laranode.demo.enabled'), 403, 'Demo data can only be seeded when LARANODE_DEMO_MODE is enabled.');

        $admin = User::firstOrNew(['email' => config('laranode.demo.email')]);
        $admin->fill([
            'name' => 'Laranode Demo',
            'username' => 'demo',
            'role' => 'admin',
            'domain_limit' => null,
            'database_limit' => null,
            'ssh_access' => false,
        ]);
        $admin->email_verified_at = now();
        $admin->password ??= Hash::make(bin2hex(random_bytes(24)));
        $admin->save();

        $customer = User::firstOrNew(['email' => 'alex@laranode.test']);
        $customer->fill([
            'name' => 'Alex Morgan',
            'username' => 'alex',
            'role' => 'user',
            'domain_limit' => 5,
            'database_limit' => 5,
            'ssh_access' => true,
        ]);
        $customer->email_verified_at = now();
        $customer->password ??= Hash::make(bin2hex(random_bytes(24)));
        $customer->save();

        $php84 = PhpVersion::updateOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);
        $php83 = PhpVersion::updateOrCreate(['version' => '8.3'], ['active' => true, 'is_default' => false]);

        $website = Website::where('url', 'demo.laranode.test')->firstOrNew();
        $website->forceFill([
            'user_id' => $admin->id,
            'url' => 'demo.laranode.test',
            'document_root' => '/public',
            'php_version_id' => $php84->id,
            'ssl_enabled' => true,
            'ssl_status' => 'active',
            'ssl_generated_at' => now()->subDays(12),
            'ssl_expires_at' => now()->addDays(78),
        ])->save();

        $shop = Website::where('url', 'shop.laranode.test')->firstOrNew();
        $shop->forceFill([
            'user_id' => $customer->id,
            'url' => 'shop.laranode.test',
            'document_root' => '/public',
            'php_version_id' => $php83->id,
            'ssl_enabled' => true,
            'ssl_status' => 'active',
            'ssl_generated_at' => now()->subDays(28),
            'ssl_expires_at' => now()->addDays(62),
        ])->save();

        $database = Database::updateOrCreate(
            ['name' => 'demo_app'],
            [
                'user_id' => $admin->id,
                'website_id' => $website->id,
                'db_user' => 'demo_app',
                'db_password' => bin2hex(random_bytes(16)),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]
        );

        $destination = BackupDestination::updateOrCreate(
            ['name' => 'Demo S3 Storage'],
            [
                'user_id' => null,
                'driver' => 's3',
                'config' => [
                    'bucket' => 'laranode-demo-backups',
                    'region' => 'fra1',
                    'endpoint' => 'https://fra1.digitaloceanspaces.com',
                    'key' => 'DEMO-KEY-NOT-REAL',
                    'secret' => 'DEMO-SECRET-NOT-REAL',
                ],
                'is_default' => true,
            ]
        );

        Backup::updateOrCreate(
            ['user_id' => $admin->id, 'path' => 'demo/completed-account.tar.gz'],
            [
                'website_id' => null,
                'database_id' => null,
                'destination_id' => $destination->id,
                'scope' => 'account',
                'kind' => 'manual',
                'status' => 'completed',
                'size_bytes' => 18743296,
                'manifest' => ['account' => ['username' => 'demo'], 'websites' => [['url' => 'demo.laranode.test']], 'databases' => [['name' => 'demo_app']]],
                'started_at' => now()->subMinutes(22),
                'finished_at' => now()->subMinutes(19),
            ]
        );

        Backup::updateOrCreate(
            ['user_id' => $admin->id, 'path' => 'demo/failed-files.tar.gz'],
            [
                'website_id' => $website->id,
                'database_id' => null,
                'destination_id' => $destination->id,
                'scope' => 'website_files',
                'kind' => 'manual',
                'status' => 'failed',
                'size_bytes' => 9248768,
                'manifest' => ['websites' => [['url' => 'demo.laranode.test']]],
                'error' => 'Example failure: remote destination temporarily unavailable.',
                'restore_attempts' => 1,
                'started_at' => now()->subDays(1),
                'finished_at' => now()->subDays(1)->addMinutes(2),
            ]
        );

        BackupSchedule::updateOrCreate(
            ['user_id' => $admin->id, 'scope' => 'website_database', 'database_id' => $database->id],
            [
                'website_id' => $website->id,
                'destination_id' => $destination->id,
                'frequency' => 'daily',
                'run_at' => '02:30',
                'retention_count' => 7,
                'enabled' => true,
                'last_run_at' => now()->subDay()->setTime(2, 30),
                'next_run_at' => now()->addDay()->setTime(2, 30),
            ]
        );
    }
}
