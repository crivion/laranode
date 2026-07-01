<?php

// tests/Feature/Websites/RuntimeSystemTest.php
//
// Real system integration tests gated behind LARANODE_SYSTEM_TESTS=1.
// Verifies that the FrankenPHP runtime install, unit management, Apache vhost
// switching, and the ACME challenge exception all work end-to-end in the
// local-dev container.
//
// Run inside the local-dev container:
//   LARANODE_SYSTEM_TESTS=1 php artisan test --filter=RuntimeSystemTest
//
// Pre-requisites (provisioned by local-dev/entrypoint-setup.sh):
//   - a2enmod proxy proxy_http enabled.
//   - /etc/sudoers.d/laranode-runtimes installed.
//   - testuser_ln system account exists.
//   - /etc/sudoers.d/laranode grants www-data NOPASSWD for runtime scripts.

use App\Jobs\SwitchRuntimeOperationJob;
use App\Models\Operation;
use App\Models\PhpVersion;
use App\Models\User;
use Illuminate\Support\Facades\Config;

// ---------------------------------------------------------------------------
// Constants for this test suite
// ---------------------------------------------------------------------------

const RT_TEST_DOMAIN = 'rtsystem.test';
const RT_TEST_SYSTEM_USER = 'testuser_ln';
const RT_TEST_PORT = 9100;
const RT_FRANKENPHP_BIN = '/usr/local/bin/frankenphp';
// Pinned SHA-256 must match FRANKENPHP_SHA256 in laranode-runtime-install.sh.
const RT_FRANKENPHP_SHA256 = 'becd9efc79783a4946fb4802433dc00be32de7e025b60fcab53db4d283a136e9';
const RT_TEST_UNIT = 'laranode-frankenphp-'.RT_TEST_DOMAIN.'.service';

// ---------------------------------------------------------------------------
// Gate + setup
// ---------------------------------------------------------------------------

beforeEach(function () {
    if (! env('LARANODE_SYSTEM_TESTS')) {
        $this->markTestSkipped('LARANODE_SYSTEM_TESTS not set — skipping system integration tests.');
    }

    // Point services at the panel's checked-in scripts (not the /opt/laranode/bin copy).
    Config::set('laranode.laranode_bin_path', base_path('laranode-scripts/bin'));
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Run a laranode script directly (as root) and return ['exitCode', 'output'].
 */
function runScript(string $script, array $args): array
{
    $path = base_path('laranode-scripts/bin/'.ltrim($script, '/'));
    $cmd = 'bash '.escapeshellarg($path);
    foreach ($args as $a) {
        $cmd .= ' '.escapeshellarg((string) $a);
    }

    $out = [];
    $code = 0;
    exec($cmd.' 2>&1', $out, $code);

    return ['exitCode' => $code, 'output' => implode("\n", $out)];
}

/**
 * Ensure the test domain's directory structure exists and is accessible.
 * Creates:  /home/{user}/domains/{domain}/public_html/
 *           /home/{user}/logs/
 * and sets permissions so Apache (www-data) can traverse the home dir.
 */
function ensureDomainDocRoot(string $systemUser, string $domain, string $docRoot = '/public_html'): string
{
    $homeDir = '/home/'.$systemUser;
    $fullDocRoot = $homeDir.'/domains/'.$domain.$docRoot;

    if (! is_dir($fullDocRoot)) {
        mkdir($fullDocRoot, 0755, true);
    }

    $logsDir = $homeDir.'/logs';
    if (! is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }

    // Allow Apache (www-data) to traverse the home directory.
    chmod($homeDir, 0755);

    // Place a simple PHP index so FrankenPHP has something to serve.
    $indexFile = $fullDocRoot.'/index.php';
    if (! file_exists($indexFile)) {
        file_put_contents($indexFile, "<?php echo 'OK'; ?>\n");
    }

    // Fix ownership to the system user.
    exec('chown -R '.escapeshellarg($systemUser).':'.escapeshellarg($systemUser).' '.escapeshellarg($homeDir.'/domains'));

    return $fullDocRoot;
}

/**
 * Stop + remove the test FrankenPHP systemd unit.
 * Best-effort: ignores failures (unit may not exist).
 */
function teardownTestUnit(string $unitName): void
{
    $binPath = base_path('laranode-scripts/bin');
    exec('bash '.escapeshellarg($binPath.'/laranode-runtime-manage.sh').' remove '.escapeshellarg($unitName).' 2>/dev/null');
}

/**
 * Disable + remove the test Apache vhost.
 * Best-effort: ignores failures.
 */
function teardownTestVhost(string $domain): void
{
    exec('a2dissite '.escapeshellarg($domain).' 2>/dev/null');
    @unlink('/etc/apache2/sites-available/'.$domain.'.conf');
    exec('apache2ctl graceful 2>/dev/null');
}

/**
 * Create a DB Website row backed by testuser_ln, pointing to the test domain.
 * Returns [$user, $website].
 */
function makeSystemTestSite(string $domain, string $docRoot = '/public_html'): array
{
    $php = PhpVersion::firstOrCreate(['version' => '8.4'], ['active' => true, 'is_default' => true]);

    $user = User::factory()->isNotAdmin()->create([
        'username' => 'testuser',
        'email' => 'rttest@laranode.system.test',
    ]);

    $website = $user->websites()->create([
        'url' => $domain,
        'document_root' => $docRoot,
        'php_version_id' => $php->id,
        'runtime' => 'php-fpm',
        'runtime_port' => null,
    ]);

    $website->load('user');

    return [$user, $website];
}

// ---------------------------------------------------------------------------
// Test 1: Install FrankenPHP binary
// ---------------------------------------------------------------------------

test('laranode-runtime-install.sh installs FrankenPHP: exit 0, binary exists, --version exits 0', function () {
    $result = runScript('laranode-runtime-install.sh', ['frankenphp']);

    expect($result['exitCode'])
        ->toBe(0, 'Install script must exit 0. Output: '.$result['output']);

    expect(file_exists(RT_FRANKENPHP_BIN))
        ->toBeTrue('FrankenPHP binary must exist at '.RT_FRANKENPHP_BIN);

    exec(RT_FRANKENPHP_BIN.' --version 2>&1', $versionOut, $versionCode);
    expect($versionCode)
        ->toBe(0, '--version must exit 0. Output: '.implode("\n", $versionOut));
})->group('system');

// ---------------------------------------------------------------------------
// Test 2: Binary SHA-256 matches pinned value
// ---------------------------------------------------------------------------

test('installed FrankenPHP binary SHA-256 matches pinned value in install script', function () {
    expect(file_exists(RT_FRANKENPHP_BIN))
        ->toBeTrue('FrankenPHP binary must be installed first.');

    $actual = trim(shell_exec('sha256sum '.escapeshellarg(RT_FRANKENPHP_BIN).' | awk \'{print $1}\'') ?? '');

    expect($actual)
        ->toBe(RT_FRANKENPHP_SHA256, 'SHA-256 mismatch — binary may be corrupt or wrong version.');
})->group('system');

// ---------------------------------------------------------------------------
// Test 3: Idempotent install — no re-download (file timestamp unchanged)
// ---------------------------------------------------------------------------

test('laranode-runtime-install.sh is idempotent: running again does not re-download', function () {
    expect(file_exists(RT_FRANKENPHP_BIN))
        ->toBeTrue('FrankenPHP binary must be installed first.');

    $mtimeBefore = filemtime(RT_FRANKENPHP_BIN);

    $result = runScript('laranode-runtime-install.sh', ['frankenphp']);

    expect($result['exitCode'])
        ->toBe(0, 'Install script must exit 0 on idempotent run. Output: '.$result['output']);

    expect(str_contains($result['output'], 'already installed'))
        ->toBeTrue('Script must emit "already installed" message. Output: '.$result['output']);

    clearstatcache(true, RT_FRANKENPHP_BIN);
    $mtimeAfter = filemtime(RT_FRANKENPHP_BIN);

    expect($mtimeAfter)
        ->toBe($mtimeBefore, 'File mtime must be unchanged (no re-download on idempotent run).');
})->group('system');

// ---------------------------------------------------------------------------
// Test 4: Corrupt binary triggers re-download
// ---------------------------------------------------------------------------

test('laranode-runtime-install.sh re-downloads when binary is corrupt', function () {
    expect(file_exists(RT_FRANKENPHP_BIN))
        ->toBeTrue('FrankenPHP binary must be installed first (needed to corrupt it).');

    // Corrupt the binary.
    $backup = RT_FRANKENPHP_BIN.'.backup';
    copy(RT_FRANKENPHP_BIN, $backup);
    file_put_contents(RT_FRANKENPHP_BIN, 'garbage');

    try {
        $result = runScript('laranode-runtime-install.sh', ['frankenphp']);

        expect($result['exitCode'])
            ->toBe(0, 'Install script must exit 0 after re-downloading. Output: '.$result['output']);

        // Binary must be executable and functional after re-download.
        exec(RT_FRANKENPHP_BIN.' --version 2>&1', $versionOut, $versionCode);
        expect($versionCode)
            ->toBe(0, '--version must exit 0 after re-download. Output: '.implode("\n", $versionOut));

        // SHA must match pinned value after re-download.
        $actual = trim(shell_exec('sha256sum '.escapeshellarg(RT_FRANKENPHP_BIN).' | awk \'{print $1}\'') ?? '');
        expect($actual)
            ->toBe(RT_FRANKENPHP_SHA256, 'SHA-256 must match after re-download.');
    } finally {
        // Restore the original binary if the test fails mid-way.
        if (file_exists($backup) && ! (file_exists(RT_FRANKENPHP_BIN) && filesize(RT_FRANKENPHP_BIN) > 1000)) {
            copy($backup, RT_FRANKENPHP_BIN);
            chmod(RT_FRANKENPHP_BIN, 0755);
        }
        @unlink($backup);
    }
})->group('system');

// ---------------------------------------------------------------------------
// Test 5: Switch site to FrankenPHP (full operation stack)
// ---------------------------------------------------------------------------

test('switching site to FrankenPHP: Operation succeeded, unit active, runtime_port assigned', function () {
    $domain = RT_TEST_DOMAIN;
    $systemUser = RT_TEST_SYSTEM_USER;

    // Set up directory structure for the test domain.
    ensureDomainDocRoot($systemUser, $domain, '/public_html');

    [$user, $website] = makeSystemTestSite($domain, '/public_html');

    $op = Operation::create([
        'user_id' => $user->id,
        'type' => 'runtime.switch',
        'target' => $domain,
        'status' => 'queued',
    ]);

    try {
        (new SwitchRuntimeOperationJob($op, $website, 'frankenphp'))->handle();

        expect($op->fresh()->status)
            ->toBe('succeeded', 'Operation must be "succeeded".');

        $fresh = $website->fresh();
        expect($fresh->runtime)
            ->toBe('frankenphp', 'Website runtime must be "frankenphp".');
        expect($fresh->runtime_port)
            ->toBeInt()
            ->toBeGreaterThanOrEqual(9100)
            ->toBeLessThanOrEqual(9499);

        // Systemd unit must be active.
        exec('systemctl is-active '.escapeshellarg(RT_TEST_UNIT).' 2>&1', $activeOut, $activeCode);
        expect($activeCode)
            ->toBe(0, 'systemd unit must be active. Output: '.implode("\n", $activeOut));

    } finally {
        teardownTestUnit(RT_TEST_UNIT);
        teardownTestVhost($domain);
    }
})->group('system');

// ---------------------------------------------------------------------------
// Test 6: Apache proxy — curl with Host header returns non-502
// ---------------------------------------------------------------------------

test('FrankenPHP Apache proxy: curl returns non-502 HTTP status', function () {
    $domain = RT_TEST_DOMAIN;
    $systemUser = RT_TEST_SYSTEM_USER;

    ensureDomainDocRoot($systemUser, $domain, '/public_html');

    [$user, $website] = makeSystemTestSite($domain, '/public_html');

    $op = Operation::create([
        'user_id' => $user->id,
        'type' => 'runtime.switch',
        'target' => $domain,
        'status' => 'queued',
    ]);

    try {
        (new SwitchRuntimeOperationJob($op, $website, 'frankenphp'))->handle();

        expect($op->fresh()->status)->toBe('succeeded');

        // Give FrankenPHP a moment to be ready (it starts immediately, but curl might be faster).
        usleep(200000); // 200ms

        $httpCode = trim((string) shell_exec(
            'curl -s -o /dev/null -w "%{http_code}" -H '.escapeshellarg('Host: '.$domain).' http://127.0.0.1/'
        ));

        expect((int) $httpCode)
            ->not->toBe(502, "Apache proxy must not return 502 (Bad Gateway). HTTP code: {$httpCode}")
            ->not->toBe(0, 'curl must get a valid HTTP response.');

    } finally {
        teardownTestUnit(RT_TEST_UNIT);
        teardownTestVhost($domain);
    }
})->group('system');

// ---------------------------------------------------------------------------
// Test 7: ACME challenge served from disk (ProxyPass ! exception)
// ---------------------------------------------------------------------------

test('ACME challenge path served from disk (not proxied to FrankenPHP)', function () {
    $domain = RT_TEST_DOMAIN;
    $systemUser = RT_TEST_SYSTEM_USER;
    $fullDocRoot = ensureDomainDocRoot($systemUser, $domain, '/public_html');

    [$user, $website] = makeSystemTestSite($domain, '/public_html');

    $op = Operation::create([
        'user_id' => $user->id,
        'type' => 'runtime.switch',
        'target' => $domain,
        'status' => 'queued',
    ]);

    // Create the ACME challenge token file before switching runtime.
    $acmeDir = $fullDocRoot.'/.well-known/acme-challenge';
    if (! is_dir($acmeDir)) {
        mkdir($acmeDir, 0755, true);
    }
    $tokenContent = 'acme-system-test-token-'.uniqid();
    file_put_contents($acmeDir.'/testtoken', $tokenContent);
    // Ensure Apache can read the file.
    chmod($acmeDir.'/testtoken', 0644);

    try {
        (new SwitchRuntimeOperationJob($op, $website, 'frankenphp'))->handle();

        expect($op->fresh()->status)->toBe('succeeded');

        usleep(200000); // 200ms stabilisation

        // The ACME path must be served from disk, not proxied.
        $body = trim((string) shell_exec(
            'curl -s -H '.escapeshellarg('Host: '.$domain).' http://127.0.0.1/.well-known/acme-challenge/testtoken'
        ));
        $httpCode = trim((string) shell_exec(
            'curl -s -o /dev/null -w "%{http_code}" -H '.escapeshellarg('Host: '.$domain).' http://127.0.0.1/.well-known/acme-challenge/testtoken'
        ));

        expect((int) $httpCode)
            ->toBe(200, 'ACME challenge must return HTTP 200. Code: '.$httpCode.', body: '.$body);

        expect($body)
            ->toBe($tokenContent, 'ACME challenge must return the on-disk token content. Got: '.$body);

    } finally {
        @unlink($acmeDir.'/testtoken');
        teardownTestUnit(RT_TEST_UNIT);
        teardownTestVhost($domain);
    }
})->group('system');

// ---------------------------------------------------------------------------
// Test 8: Switch back to FPM — unit inactive, vhost reverted
// ---------------------------------------------------------------------------

test('switching back to FPM: Operation succeeded, unit inactive, no ProxyPass in vhost', function () {
    $domain = RT_TEST_DOMAIN;
    $systemUser = RT_TEST_SYSTEM_USER;

    ensureDomainDocRoot($systemUser, $domain, '/public_html');

    [$user, $website] = makeSystemTestSite($domain, '/public_html');

    $op1 = Operation::create([
        'user_id' => $user->id,
        'type' => 'runtime.switch',
        'target' => $domain,
        'status' => 'queued',
    ]);

    try {
        // First: switch to FrankenPHP.
        (new SwitchRuntimeOperationJob($op1, $website, 'frankenphp'))->handle();
        expect($op1->fresh()->status)->toBe('succeeded');

        $website->refresh();

        // Second: switch back to FPM.
        $op2 = Operation::create([
            'user_id' => $user->id,
            'type' => 'runtime.switch',
            'target' => $domain,
            'status' => 'queued',
        ]);

        (new SwitchRuntimeOperationJob($op2, $website, 'php-fpm'))->handle();

        expect($op2->fresh()->status)
            ->toBe('succeeded', 'Back-to-FPM operation must succeed.');

        $fresh = $website->fresh();
        expect($fresh->runtime)->toBe('php-fpm');
        expect($fresh->runtime_port)->toBeNull();

        // Systemd unit must be inactive (disabled + stopped by manage.sh remove via teardown in service).
        exec('systemctl is-active '.escapeshellarg(RT_TEST_UNIT).' 2>&1', $activeOut, $activeCode);
        expect($activeCode)
            ->not->toBe(0, 'Unit must be inactive after switching back to FPM.');

        // Apache vhost must NOT contain ProxyPass.
        $vhostConf = '/etc/apache2/sites-available/'.$domain.'.conf';
        if (file_exists($vhostConf)) {
            $vhostContents = file_get_contents($vhostConf);
            expect(str_contains($vhostContents, 'ProxyPass /'))
                ->toBeFalse('FPM vhost must not contain ProxyPass directive.');
        }

    } finally {
        teardownTestUnit(RT_TEST_UNIT);
        teardownTestVhost($domain);
    }
})->group('system');

// ---------------------------------------------------------------------------
// Test 9: Invalid runtime rejected
// ---------------------------------------------------------------------------

test('laranode-runtime-install.sh rejects invalid runtime with non-zero exit', function () {
    $result = runScript('laranode-runtime-install.sh', ['badvalue']);

    expect($result['exitCode'])
        ->not->toBe(0, 'Script must exit non-zero for invalid runtime. Output: '.$result['output']);
})->group('system');

// ---------------------------------------------------------------------------
// Test 10: Invalid unit name rejected by laranode-runtime-manage.sh
// ---------------------------------------------------------------------------

test('laranode-runtime-manage.sh rejects sshd.service (non-laranode unit) with non-zero exit', function () {
    $result = runScript('laranode-runtime-manage.sh', ['start', 'sshd.service']);

    expect($result['exitCode'])
        ->not->toBe(0, 'Script must reject a non-Laranode unit name. Output: '.$result['output']);
})->group('system');

// ---------------------------------------------------------------------------
// Test 11: Path-traversal unit name rejected
// ---------------------------------------------------------------------------

test('laranode-runtime-manage.sh rejects path-traversal unit name with non-zero exit', function () {
    $result = runScript('laranode-runtime-manage.sh', ['start', 'laranode-frankenphp-foo/../../sshd.service']);

    expect($result['exitCode'])
        ->not->toBe(0, 'Script must reject a path-traversal unit name. Output: '.$result['output']);
})->group('system');

// ---------------------------------------------------------------------------
// Test 12: Port 0 accepted for FPM revert
// ---------------------------------------------------------------------------

test('laranode-vhost-switch.sh accepts port=0 for php-fpm revert: exit 0, vhost written without ProxyPass', function () {
    $domain = RT_TEST_DOMAIN;
    $systemUser = RT_TEST_SYSTEM_USER;
    $templates = base_path('laranode-scripts/templates');

    ensureDomainDocRoot($systemUser, $domain, '/public_html');

    try {
        $result = runScript('laranode-vhost-switch.sh', [
            $domain, 'php-fpm', '0', $systemUser, '8.4', '/public_html', $templates,
        ]);

        expect($result['exitCode'])
            ->toBe(0, 'Script must exit 0 for FPM revert with port=0. Output: '.$result['output']);

        // Vhost must exist and must NOT contain ProxyPass.
        $vhostConf = '/etc/apache2/sites-available/'.$domain.'.conf';
        expect(file_exists($vhostConf))
            ->toBeTrue("Vhost file must be written at {$vhostConf}.");

        $contents = file_get_contents($vhostConf);
        expect(str_contains($contents, 'ProxyPass /'))
            ->toBeFalse('FPM vhost must not contain ProxyPass /.');

    } finally {
        teardownTestVhost($domain);
    }
})->group('system');

// ---------------------------------------------------------------------------
// Test 13: Domain leading-dot rejected
// ---------------------------------------------------------------------------

test('laranode-vhost-switch.sh rejects leading-dot domain (..evil.com) with non-zero exit', function () {
    $templates = base_path('laranode-scripts/templates');

    $result = runScript('laranode-vhost-switch.sh', [
        '..evil.com', 'frankenphp', '9100', RT_TEST_SYSTEM_USER, '8.4', '/public_html', $templates,
    ]);

    expect($result['exitCode'])
        ->not->toBe(0, 'Script must reject leading-dot domain. Output: '.$result['output']);
})->group('system');

// ---------------------------------------------------------------------------
// Test 14: laranode-runtime-unit.sh rejects invalid domain (..evil.com)
// ---------------------------------------------------------------------------

test('laranode-runtime-unit.sh rejects consecutive-dot domain (..evil.com) with non-zero exit', function () {
    $templates = base_path('laranode-scripts/templates');

    $result = runScript('laranode-runtime-unit.sh', [
        'write-unit', '..evil.com', '9100', RT_TEST_SYSTEM_USER, '/public_html', $templates,
    ]);

    expect($result['exitCode'])
        ->not->toBe(0, 'laranode-runtime-unit.sh must reject consecutive-dot domain. Output: '.$result['output']);
})->group('system');

// ---------------------------------------------------------------------------
// Test 15: laranode-runtime-unit.sh rejects path-traversal domain
// ---------------------------------------------------------------------------

test('laranode-runtime-unit.sh rejects path-traversal domain (evil.com/../../etc) with non-zero exit', function () {
    $templates = base_path('laranode-scripts/templates');

    $result = runScript('laranode-runtime-unit.sh', [
        'write-unit', 'evil.com/../../etc', '9100', RT_TEST_SYSTEM_USER, '/public_html', $templates,
    ]);

    expect($result['exitCode'])
        ->not->toBe(0, 'laranode-runtime-unit.sh must reject path-traversal domain. Output: '.$result['output']);
})->group('system');

// ---------------------------------------------------------------------------
// Test 16: laranode-runtime-manage.sh rejects consecutive-dot unit name
// ---------------------------------------------------------------------------

test('laranode-runtime-manage.sh rejects consecutive-dot unit name (laranode-frankenphp-foo..bar.service) with non-zero exit', function () {
    $result = runScript('laranode-runtime-manage.sh', ['start', 'laranode-frankenphp-foo..bar.service']);

    expect($result['exitCode'])
        ->not->toBe(0, 'Script must reject consecutive-dot unit name. Output: '.$result['output']);
})->group('system');
