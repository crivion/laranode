<?php

namespace App\Services\Dashboard;

use App\Models\Option;
use Illuminate\Support\Facades\Process;

/**
 * GPU detection + live stats. A GPU is detected ONCE (at install or on a manual
 * rescan) and the result — vendor/name/tool, or "not present" — is persisted to
 * the Option store. The per-poll stats path probes the GPU only when a GPU was
 * detected, so machines without a GPU are never re-scanned on every dashboard
 * tick. Supports NVIDIA (nvidia-smi) and AMD (rocm-smi).
 */
class GpuStatsService
{
    public const OPTION = 'gpu_profile';

    /**
     * Probe the host for a GPU and persist the profile. Idempotent; call at
     * install and from the manual rescan endpoint only.
     *
     * @return array{detected:bool,vendor:?string,name:?string,tool:?string}
     */
    public function detect(): array
    {
        $profile = $this->detectNvidia()
            ?? $this->detectAmd()
            ?? ['detected' => false, 'vendor' => null, 'name' => null, 'tool' => null];

        Option::update_option(self::OPTION, json_encode($profile));

        return $profile;
    }

    /**
     * The persisted profile (never probes the hardware).
     *
     * @return array{detected:bool,vendor:?string,name:?string,tool:?string}
     */
    public function profile(): array
    {
        $raw = Option::get_option(self::OPTION);
        $decoded = $raw ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : ['detected' => false, 'vendor' => null, 'name' => null, 'tool' => null];
    }

    /**
     * Live GPU stats, or null when no GPU was detected (no probe is run in that
     * case — this keeps GPU-less hosts from shelling out every poll).
     *
     * @return array{vendor:string,name:?string,util:float,vramUsed:float,vramTotal:float,temp:int,power:int}|null
     */
    public function stats(): ?array
    {
        $profile = $this->profile();
        if (empty($profile['detected'])) {
            return null;
        }

        return match ($profile['vendor'] ?? null) {
            'nvidia' => $this->nvidiaStats($profile['name'] ?? null),
            'amd' => $this->amdStats($profile['name'] ?? null),
            default => null,
        };
    }

    private function hasBinary(string $bin): bool
    {
        return Process::run(['bash', '-lc', 'command -v '.$bin])->successful();
    }

    private function detectNvidia(): ?array
    {
        if (! $this->hasBinary('nvidia-smi')) {
            return null;
        }

        $proc = Process::run(['nvidia-smi', '--query-gpu=name', '--format=csv,noheader']);
        if ($proc->failed()) {
            return null;
        }

        $name = trim((string) strtok($proc->output(), "\n"));
        if ($name === '') {
            return null;
        }

        return ['detected' => true, 'vendor' => 'nvidia', 'name' => $name, 'tool' => 'nvidia-smi'];
    }

    private function detectAmd(): ?array
    {
        if (! $this->hasBinary('rocm-smi')) {
            return null;
        }

        $proc = Process::run(['rocm-smi', '--showproductname', '--json']);
        if ($proc->failed()) {
            return null;
        }

        $name = self::parseAmdName($proc->output());
        if ($name === null) {
            return null;
        }

        return ['detected' => true, 'vendor' => 'amd', 'name' => $name, 'tool' => 'rocm-smi'];
    }

    private function nvidiaStats(?string $name): ?array
    {
        $proc = Process::run([
            'nvidia-smi',
            '--query-gpu=utilization.gpu,memory.used,memory.total,temperature.gpu,power.draw',
            '--format=csv,noheader,nounits',
        ]);
        if ($proc->failed()) {
            return null;
        }

        return self::parseNvidiaStats($proc->output(), $name);
    }

    private function amdStats(?string $name): ?array
    {
        $proc = Process::run([
            'rocm-smi', '--showuse', '--showmeminfo', 'vram', '--showtemp', '--showpower', '--json',
        ]);
        if ($proc->failed()) {
            return null;
        }

        return self::parseAmdStats($proc->output(), $name);
    }

    // ---- pure parsers (unit-tested without a GPU) -------------------------

    /**
     * Parse `nvidia-smi --query-gpu=...,--format=csv,noheader,nounits`.
     * Line: "42, 1536, 8192, 61, 120.50"  (util%, memUsed MiB, memTotal MiB, tempC, powerW)
     */
    public static function parseNvidiaStats(string $output, ?string $name): ?array
    {
        $line = trim((string) strtok($output, "\n"));
        if ($line === '') {
            return null;
        }

        $p = array_map('trim', explode(',', $line));

        return [
            'vendor' => 'nvidia',
            'name' => $name,
            'util' => (float) ($p[0] ?? 0),
            'vramUsed' => round(((float) ($p[1] ?? 0)) / 1024, 2),   // MiB -> GB
            'vramTotal' => round(((float) ($p[2] ?? 0)) / 1024, 2),
            'temp' => (int) ($p[3] ?? 0),
            'power' => (int) round((float) ($p[4] ?? 0)),
        ];
    }

    /** First product name from `rocm-smi --showproductname --json`. */
    public static function parseAmdName(string $output): ?string
    {
        $data = json_decode($output, true);
        if (! is_array($data)) {
            return null;
        }

        foreach ($data as $card) {
            if (! is_array($card)) {
                continue;
            }
            foreach ($card as $key => $val) {
                if (stripos($key, 'Card series') !== false || stripos($key, 'Card model') !== false || stripos($key, 'product name') !== false) {
                    return trim((string) $val);
                }
            }
        }

        return 'AMD GPU';
    }

    /**
     * Parse `rocm-smi --showuse --showmeminfo vram --showtemp --showpower --json`.
     * Keys vary across rocm versions, so match defensively by substring.
     */
    public static function parseAmdStats(string $output, ?string $name): ?array
    {
        $data = json_decode($output, true);
        if (! is_array($data)) {
            return null;
        }

        $card = null;
        foreach ($data as $value) {
            if (is_array($value)) {
                $card = $value;
                break;
            }
        }
        if ($card === null) {
            return null;
        }

        $find = function (array $needles) use ($card): ?string {
            foreach ($card as $key => $val) {
                foreach ($needles as $needle) {
                    if (stripos($key, $needle) !== false) {
                        return (string) $val;
                    }
                }
            }

            return null;
        };

        $usedBytes = (float) ($find(['VRAM Total Used Memory']) ?? 0);
        $totalBytes = (float) ($find(['VRAM Total Memory']) ?? 0);

        return [
            'vendor' => 'amd',
            'name' => $name,
            'util' => (float) ($find(['GPU use (%)', 'GPU use']) ?? 0),
            'vramUsed' => round($usedBytes / 1024 / 1024 / 1024, 2),   // bytes -> GB
            'vramTotal' => round($totalBytes / 1024 / 1024 / 1024, 2),
            'temp' => (int) round((float) ($find(['Temperature (Sensor edge)', 'Temperature']) ?? 0)),
            'power' => (int) round((float) ($find(['Average Graphics Package Power', 'Power']) ?? 0)),
        ];
    }
}
