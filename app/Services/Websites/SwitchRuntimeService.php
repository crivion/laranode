<?php

namespace App\Services\Websites;

use App\Models\Website;
use Exception;
use Illuminate\Support\Facades\Process;

class SwitchRuntimeException extends Exception {}

class SwitchRuntimeService
{
    private const SUPPORTED_RUNTIMES = ['php-fpm', 'frankenphp'];

    /**
     * Regex for valid domain names (no leading dot, no consecutive dots, no path traversal).
     * Matches ^[a-zA-Z0-9][a-zA-Z0-9.-]+$ but also rejects consecutive dots.
     */
    private const DOMAIN_PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9.-]+$/';

    public function __construct(
        private Website $website,
        private string $runtime,
        private mixed $emit
    ) {}

    /**
     * Switch the website's PHP runtime.
     *
     * Ordered steps:
     * 1. Validate runtime + domain.
     * 2. Refuse FrankenPHP for SSL-enabled sites (v1 limitation: certbot --webroot renewal gap).
     * 3. Stop old non-FPM runtime unit (if applicable).
     * 4. For non-FPM runtimes: allocate port, install, write unit, enable, start.
     *    Port is only saved after start succeeds.
     *    On start failure: attempt rollback (restart previous runtime) before throwing.
     * 5. Switch Apache vhost.
     * 6. Persist runtime + port.
     * 7. Emit success.
     *
     * @throws SwitchRuntimeException
     */
    public function handle(): void
    {
        if (! in_array($this->runtime, self::SUPPORTED_RUNTIMES, true)) {
            throw new SwitchRuntimeException("Unsupported runtime: {$this->runtime}");
        }

        $website = $this->website;
        $emit = $this->emit;
        $domain = $website->url;

        // Step 1: Validate domain at PHP level before any privileged calls.
        if (! $this->isValidDomain($domain)) {
            throw new SwitchRuntimeException(
                "Invalid domain name '{$domain}': must match ^[a-zA-Z0-9][a-zA-Z0-9.-]+\$ with no consecutive dots."
            );
        }

        $binPath = config('laranode.laranode_bin_path');
        $templateDir = base_path('laranode-scripts/templates');
        $systemUser = $website->user->systemUsername;
        $phpVersion = $website->phpVersion->version;
        $documentRoot = $website->document_root;
        $oldRuntime = $website->runtime;

        // Step 2: Refuse FrankenPHP for SSL-enabled sites (punch-list item 2).
        // v1 limitation: FrankenPHP's :80 proxy would break certbot --webroot renewal.
        // SSL coexistence is deferred to a future version; document the known gap.
        if ($this->runtime === 'frankenphp' && $website->ssl_enabled) {
            throw new SwitchRuntimeException(
                'Cannot switch to FrankenPHP: SSL is enabled on this site. '
                .'FrankenPHP is not supported for SSL-enabled sites in v1 because the :80 proxy '
                .'would break certbot --webroot renewal. Disable SSL first, then switch runtime.'
            );
        }

        // Step 3: Stop old non-FPM unit if applicable.
        if ($oldRuntime !== 'php-fpm') {
            $oldUnit = "laranode-{$oldRuntime}-{$domain}.service";
            $emit("Stopping old {$oldRuntime} unit ({$oldUnit})...");
            Process::run(['sudo', $binPath.'/laranode-runtime-manage.sh', 'stop', $oldUnit]);
            // Non-zero exit is logged but does not block switching.
        }

        $port = 0;
        $portOrNull = null;

        // Step 4: Non-FPM runtime setup.
        if ($this->runtime !== 'php-fpm') {
            // 4a. Allocate port.
            $port = (new PortAllocatorService)->allocate($website);
            $emit("Allocated port {$port} for {$this->runtime}.");

            // 4b. Install runtime binary.
            (new InstallRuntimeService)->ensureInstalled($this->runtime, $emit);

            // 4c. Write systemd unit + daemon-reload.
            $emit("Writing systemd unit for {$domain}...");
            $unitResult = Process::run([
                'sudo',
                $binPath.'/laranode-runtime-unit.sh',
                'write-unit',
                $domain,
                (string) $port,
                $systemUser,
                $documentRoot,
                $templateDir,
            ]);
            if ($unitResult->failed()) {
                throw new SwitchRuntimeException(
                    'Failed to write systemd unit: '.$unitResult->errorOutput()
                );
            }

            // 4d. Enable unit.
            $unitName = "laranode-{$this->runtime}-{$domain}.service";
            $emit("Enabling {$unitName}...");
            $enableResult = Process::run(['sudo', $binPath.'/laranode-runtime-manage.sh', 'enable', $unitName]);
            if ($enableResult->failed()) {
                throw new SwitchRuntimeException(
                    "Failed to enable {$unitName}: ".$enableResult->errorOutput()
                );
            }

            // 4e. Start unit.
            $emit("Starting {$unitName}...");
            $startResult = Process::run(['sudo', $binPath.'/laranode-runtime-manage.sh', 'start', $unitName]);
            if ($startResult->failed()) {
                // Rollback: restart the previous runtime so site is never left 502 (punch-list item 3).
                $this->rollbackToPreviousRuntime($oldRuntime, $domain, $binPath, $emit);

                // Port NOT saved on failed start (FIXED: review fix #9).
                throw new SwitchRuntimeException(
                    "Failed to start {$unitName}: ".$startResult->errorOutput()
                );
            }

            // 4f. Start succeeded — commit port for DB write.
            $portOrNull = $port;
        }

        // Step 5: Switch Apache vhost.
        $emit("Switching Apache vhost for {$domain} to {$this->runtime}...");
        $vhostResult = Process::run([
            'sudo',
            $binPath.'/laranode-vhost-switch.sh',
            $domain,
            $this->runtime,
            (string) $port,
            $systemUser,
            $phpVersion,
            $documentRoot,
            $templateDir,
        ]);
        if ($vhostResult->failed()) {
            throw new SwitchRuntimeException(
                'Failed to switch Apache vhost: '.$vhostResult->errorOutput()
            );
        }

        // Step 6: Persist runtime + port.
        $website->update([
            'runtime' => $this->runtime,
            'runtime_port' => $portOrNull,
        ]);

        // Step 7: Emit success.
        $emit("Runtime switched to {$this->runtime} successfully.");
    }

    /**
     * Validate a domain name.
     *
     * Rules (punch-list item 4):
     * - Must match ^[a-zA-Z0-9][a-zA-Z0-9.-]+$
     * - Must NOT contain consecutive dots (..)
     * - Must NOT contain path separators (/) or (..) sequences
     */
    private function isValidDomain(string $domain): bool
    {
        if (! preg_match(self::DOMAIN_PATTERN, $domain)) {
            return false;
        }

        // Reject consecutive dots (e.g. 'foo..bar.test')
        if (str_contains($domain, '..')) {
            return false;
        }

        // Reject path traversal (should be caught by regex, but belt-and-suspenders)
        if (str_contains($domain, '/') || str_contains($domain, '\\')) {
            return false;
        }

        return true;
    }

    /**
     * Attempt to restart the previous runtime after a new runtime start failure.
     *
     * This prevents leaving the site in a 502 state (punch-list item 3).
     * Failure to restart the previous runtime is logged but does not throw —
     * the primary exception (start failure of new runtime) is surfaced instead.
     */
    private function rollbackToPreviousRuntime(
        string $oldRuntime,
        string $domain,
        string $binPath,
        callable $emit
    ): void {
        if ($oldRuntime === 'php-fpm') {
            // FPM is managed via the pool config — restart via PHP-FPM service.
            // The FPM pool was never stopped (we only stopped non-FPM units), so
            // no explicit restart is needed; FPM continues serving the site.
            $emit('New runtime failed to start. Site remains on PHP-FPM (no action needed).');

            return;
        }

        // Old runtime was non-FPM: attempt to restart its unit.
        $oldUnit = "laranode-{$oldRuntime}-{$domain}.service";
        $emit("New runtime failed to start. Attempting rollback: restarting {$oldUnit}...");
        $rollbackResult = Process::run([
            'sudo',
            $binPath.'/laranode-runtime-manage.sh',
            'restart',
            $oldUnit,
        ]);

        if ($rollbackResult->failed()) {
            $emit("WARNING: Rollback failed — {$oldUnit} could not be restarted. Site may be unavailable.");
        } else {
            $emit("Rollback succeeded: {$oldUnit} is running again.");
        }
    }
}
