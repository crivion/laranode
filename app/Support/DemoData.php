<?php

namespace App\Support;

class DemoData
{
    public static function phpVersions(): array
    {
        return [
            ['version' => '8.4', 'status' => 'active', 'enabled' => true],
            ['version' => '8.3', 'status' => 'active', 'enabled' => true],
            ['version' => '8.2', 'status' => 'inactive', 'enabled' => false],
        ];
    }

    public static function charsets(): array
    {
        return [
            'charsets' => [
                ['name' => 'utf8mb4', 'description' => 'UTF-8 Unicode', 'default_collation' => 'utf8mb4_unicode_ci', 'maxlen' => 4],
                ['name' => 'utf8', 'description' => 'UTF-8 Unicode', 'default_collation' => 'utf8_general_ci', 'maxlen' => 3],
            ],
            'collations' => [
                ['name' => 'utf8mb4_unicode_ci', 'charset' => 'utf8mb4', 'id' => 224, 'default' => 'Yes', 'compiled' => 'Yes', 'sortlen' => 8],
                ['name' => 'utf8mb4_general_ci', 'charset' => 'utf8mb4', 'id' => 45, 'default' => '', 'compiled' => 'Yes', 'sortlen' => 1],
                ['name' => 'utf8_general_ci', 'charset' => 'utf8', 'id' => 33, 'default' => 'Yes', 'compiled' => 'Yes', 'sortlen' => 1],
            ],
        ];
    }

    public static function files(?string $path): array
    {
        $path = trim((string) $path, '/');
        $contents = match ($path) {
            'domains' => [
                ['type' => 'dir', 'path' => 'domains/demo.laranode.test'],
                ['type' => 'dir', 'path' => 'domains/shop.laranode.test'],
            ],
            'domains/demo.laranode.test' => [
                ['type' => 'dir', 'path' => 'domains/demo.laranode.test/public'],
                ['type' => 'file', 'path' => 'domains/demo.laranode.test/README.md', 'file_size' => 1840],
            ],
            'domains/demo.laranode.test/public' => [
                ['type' => 'file', 'path' => 'domains/demo.laranode.test/public/index.php', 'file_size' => 612],
                ['type' => 'file', 'path' => 'domains/demo.laranode.test/public/robots.txt', 'file_size' => 64],
            ],
            default => [
                ['type' => 'dir', 'path' => 'domains'],
                ['type' => 'dir', 'path' => 'backups'],
                ['type' => 'file', 'path' => 'welcome.txt', 'file_size' => 428],
            ],
        };

        $goBack = $path === '' ? null : (str_contains($path, '/') ? dirname($path) : '/');

        return ['files' => $contents, 'goBack' => $goBack];
    }

    public static function file(string $path): string
    {
        return str_ends_with($path, '.php')
            ? "<?php\n\n// Limited demo file — editing is simulated.\n\necho 'Hello from Laranode!';\n"
            : "# Laranode public demo\n\nThis is simulated file content. Changes are not written to disk.\n";
    }

    public static function firewallRules(): array
    {
        return [
            ['number' => '1', 'service' => '22/tcp', 'action' => 'ALLOW', 'direction' => 'IN', 'from' => 'Anywhere'],
            ['number' => '2', 'service' => '80,443/tcp', 'action' => 'ALLOW', 'direction' => 'IN', 'from' => 'Anywhere'],
            ['number' => '3', 'service' => '3306/tcp', 'action' => 'DENY', 'direction' => 'IN', 'from' => 'Anywhere'],
        ];
    }

    public static function history(): array
    {
        $cpu = $memory = $network = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $time = sprintf('%02d:00', $hour);
            $user = round(8 + sin($hour / 2) * 3 + ($hour % 5), 2);
            $system = round(3 + cos($hour / 3) * 1.5, 2);
            $cpu[] = ['time' => $time, 'user' => $user, 'system' => $system, 'idle' => 100 - $user - $system, 'total' => $user + $system];
            $used = round(1.55 + sin($hour / 4) * .18, 2);
            $memory[] = ['time' => $time, 'avail' => round(3.84 - $used, 2), 'used' => $used, 'percent' => round($used / 3.84 * 100, 2)];
            $rx = round(1.5 + abs(sin($hour)) * 6, 2);
            $tx = round(.8 + abs(cos($hour)) * 3, 2);
            $network[] = ['time' => $time, 'interface' => 'eth0', 'rxkbs' => $rx, 'txkbs' => $tx, 'totalkbs' => $rx + $tx];
        }

        return compact('cpu', 'memory', 'network');
    }
}
