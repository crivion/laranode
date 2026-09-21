<?php

use App\Filesystem\SymlinkSafeLocalAdapter;
use Illuminate\Support\Facades\File;
use League\Flysystem\Filesystem;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/laranode-adapter-'.uniqid();
    File::makeDirectory($this->root.'/attacker_ln/domains/site/public_html', 0777, true);
    File::makeDirectory($this->root.'/victim_ln/domains/victim-site/public_html', 0777, true);

    $this->attackerHome = $this->root.'/attacker_ln';
    $this->victim = $this->root.'/victim_ln/domains/victim-site/public_html/owned.php';

    $this->filesystem = new Filesystem(
        new SymlinkSafeLocalAdapter($this->attackerHome, LOCK_EX, SymlinkSafeLocalAdapter::SKIP_LINKS)
    );
});

afterEach(fn () => File::deleteDirectory($this->root));

test('it still writes normally', function () {
    $this->filesystem->write('domains/site/public_html/index.php', 'hello');

    expect(File::get($this->attackerHome.'/domains/site/public_html/index.php'))->toBe('hello');
});

test('it still creates missing parent directories', function () {
    $this->filesystem->write('domains/site/public_html/nested/deep/file.txt', 'hi');

    expect(File::get($this->attackerHome.'/domains/site/public_html/nested/deep/file.txt'))->toBe('hi');
});

test('it still blocks traversal', function () {
    expect(fn () => $this->filesystem->write('../victim_ln/x.php', 'nope'))
        ->toThrow(League\Flysystem\PathTraversalDetected::class);
});

test('write replaces a symlinked destination instead of following it', function () {
    symlink($this->victim, $this->attackerHome.'/domains/site/public_html/owned.php');

    $this->filesystem->write('domains/site/public_html/owned.php', '<?php system($_GET[0]);');

    expect(File::exists($this->victim))->toBeFalse()
        ->and(is_link($this->attackerHome.'/domains/site/public_html/owned.php'))->toBeFalse()
        ->and(File::get($this->attackerHome.'/domains/site/public_html/owned.php'))->toBe('<?php system($_GET[0]);');
});

test('write refuses a symlinked parent directory', function () {
    symlink(
        $this->root.'/victim_ln/domains/victim-site/public_html',
        $this->attackerHome.'/domains/site/public_html/escape',
    );

    expect(fn () => $this->filesystem->write('domains/site/public_html/escape/owned.php', 'payload'))
        ->toThrow(League\Flysystem\UnableToWriteFile::class)
        ->and(File::exists($this->victim))->toBeFalse();
});

test('write preserves existing mode and ownership', function () {
    $path = $this->attackerHome.'/domains/site/public_html/index.php';
    File::put($path, 'before');
    chmod($path, 0640);
    $before = stat($path);

    $this->filesystem->write('domains/site/public_html/index.php', 'after');

    $after = stat($path);

    expect($after['uid'])->toBe($before['uid'])
        ->and($after['gid'])->toBe($before['gid'])
        ->and($after['mode'] & 0777)->toBe(0640);
});

test('writeStream replaces a symlinked destination instead of following it', function () {
    symlink($this->victim, $this->attackerHome.'/domains/site/public_html/owned.php');

    $stream = fopen('php://temp', 'r+');
    fwrite($stream, 'payload');
    rewind($stream);

    $this->filesystem->writeStream('domains/site/public_html/owned.php', $stream);

    expect(File::exists($this->victim))->toBeFalse()
        ->and(File::get($this->attackerHome.'/domains/site/public_html/owned.php'))->toBe('payload');
});

test('copy replaces a symlinked destination instead of following it', function () {
    $this->filesystem->write('domains/site/public_html/source.txt', 'payload');
    symlink($this->victim, $this->attackerHome.'/domains/site/public_html/owned.php');

    $this->filesystem->copy('domains/site/public_html/source.txt', 'domains/site/public_html/owned.php');

    expect(File::exists($this->victim))->toBeFalse()
        ->and(File::get($this->attackerHome.'/domains/site/public_html/owned.php'))->toBe('payload');
});

test('copy refuses a symlinked source parent directory', function () {
    File::put($this->victim, 'secret');
    symlink(
        $this->root.'/victim_ln/domains/victim-site/public_html',
        $this->attackerHome.'/domains/site/public_html/escape',
    );

    expect(fn () => $this->filesystem->copy(
        'domains/site/public_html/escape/owned.php',
        'domains/site/public_html/stolen.php',
    ))->toThrow(League\Flysystem\UnableToWriteFile::class)
        ->and(File::exists($this->attackerHome.'/domains/site/public_html/stolen.php'))->toBeFalse();
});

test('it leaves no temporary files behind', function () {
    $this->filesystem->write('domains/site/public_html/index.php', 'hello');

    $leftovers = preg_grep('/laranode-write/', scandir($this->attackerHome.'/domains/site/public_html'));

    expect($leftovers)->toBeEmpty();
});

test('move still renames within the home', function () {
    $this->filesystem->write('domains/site/public_html/a.txt', 'payload');

    $this->filesystem->move('domains/site/public_html/a.txt', 'domains/site/public_html/b.txt');

    expect(File::exists($this->attackerHome.'/domains/site/public_html/a.txt'))->toBeFalse()
        ->and(File::get($this->attackerHome.'/domains/site/public_html/b.txt'))->toBe('payload');
});

test('move still creates missing destination directories', function () {
    $this->filesystem->write('domains/site/public_html/a.txt', 'payload');

    $this->filesystem->move('domains/site/public_html/a.txt', 'domains/site/public_html/nested/deep/a.txt');

    expect(File::get($this->attackerHome.'/domains/site/public_html/nested/deep/a.txt'))->toBe('payload');
});

test('move refuses a symlinked destination directory', function () {
    // the paste primitive: attacker content into another tenant's web root
    $this->filesystem->write('evil.php', '<?php system($_GET[0]);');
    symlink($this->root.'/victim_ln/domains/victim-site/public_html', $this->attackerHome.'/link');

    expect(fn () => $this->filesystem->move('evil.php', 'link/evil.php'))
        ->toThrow(League\Flysystem\UnableToMoveFile::class);

    expect(File::exists($this->root.'/victim_ln/domains/victim-site/public_html/evil.php'))->toBeFalse()
        ->and(File::exists($this->attackerHome.'/evil.php'))->toBeTrue();
});

test('move refuses a symlinked source directory', function () {
    // the read primitive: pulling another tenant's file into the attacker's home
    File::put($this->root.'/victim_ln/domains/victim-site/public_html/.env', 'DB_PASSWORD=secret');
    symlink($this->root.'/victim_ln/domains/victim-site/public_html', $this->attackerHome.'/link');

    expect(fn () => $this->filesystem->move('link/.env', 'stolen.env'))
        ->toThrow(League\Flysystem\UnableToMoveFile::class);

    expect(File::exists($this->attackerHome.'/stolen.env'))->toBeFalse()
        ->and(File::get($this->root.'/victim_ln/domains/victim-site/public_html/.env'))->toBe('DB_PASSWORD=secret');
});

test('delete still removes a file in the home', function () {
    $this->filesystem->write('domains/site/public_html/a.txt', 'payload');

    $this->filesystem->delete('domains/site/public_html/a.txt');

    expect(File::exists($this->attackerHome.'/domains/site/public_html/a.txt'))->toBeFalse();
});

test('delete is a no-op for a missing file', function () {
    $this->filesystem->delete('domains/site/public_html/gone.txt');
})->throwsNoExceptions();

test('delete refuses to reach through a symlinked directory', function () {
    File::put($this->root.'/victim_ln/domains/victim-site/public_html/.env', 'DB_PASSWORD=secret');
    symlink($this->root.'/victim_ln/domains/victim-site/public_html', $this->attackerHome.'/link');

    try {
        $this->filesystem->delete('link/.env');
    } catch (League\Flysystem\UnableToDeleteFile) {
        // rejected outright is also an acceptable outcome
    }

    expect(File::get($this->root.'/victim_ln/domains/victim-site/public_html/.env'))->toBe('DB_PASSWORD=secret');
});

test('delete removes a symlink itself rather than its target', function () {
    File::put($this->root.'/victim_ln/domains/victim-site/public_html/.env', 'DB_PASSWORD=secret');
    symlink($this->root.'/victim_ln/domains/victim-site/public_html', $this->attackerHome.'/link');

    $this->filesystem->delete('link');

    expect(is_link($this->attackerHome.'/link'))->toBeFalse()
        ->and(File::get($this->root.'/victim_ln/domains/victim-site/public_html/.env'))->toBe('DB_PASSWORD=secret');
});

test('deleteDirectory still removes a tree in the home', function () {
    $this->filesystem->write('domains/site/public_html/nested/deep/a.txt', 'payload');

    $this->filesystem->deleteDirectory('domains/site/public_html/nested');

    expect(File::exists($this->attackerHome.'/domains/site/public_html/nested'))->toBeFalse()
        ->and(File::isDirectory($this->attackerHome.'/domains/site/public_html'))->toBeTrue();
});

test('deleteDirectory does not wipe the tree a symlink points at', function () {
    File::put($this->root.'/victim_ln/domains/victim-site/public_html/index.php', 'victim site');
    File::makeDirectory($this->root.'/victim_ln/domains/victim-site/public_html/assets', 0777, true);
    File::put($this->root.'/victim_ln/domains/victim-site/public_html/assets/app.js', 'victim js');

    symlink($this->root.'/victim_ln/domains/victim-site/public_html', $this->attackerHome.'/link');

    try {
        $this->filesystem->deleteDirectory('link');
    } catch (League\Flysystem\UnableToDeleteDirectory) {
        // rejected outright is also an acceptable outcome
    }

    expect(File::get($this->root.'/victim_ln/domains/victim-site/public_html/index.php'))->toBe('victim site')
        ->and(File::get($this->root.'/victim_ln/domains/victim-site/public_html/assets/app.js'))->toBe('victim js');
});

test('deleteDirectory unlinks a nested symlink instead of descending it', function () {
    File::put($this->root.'/victim_ln/domains/victim-site/public_html/index.php', 'victim site');

    $this->filesystem->write('tree/keep.txt', 'mine');
    symlink($this->root.'/victim_ln/domains/victim-site/public_html', $this->attackerHome.'/tree/link');

    $this->filesystem->deleteDirectory('tree');

    expect(File::exists($this->attackerHome.'/tree'))->toBeFalse()
        ->and(File::get($this->root.'/victim_ln/domains/victim-site/public_html/index.php'))->toBe('victim site');
});

test('createDirectory still creates a directory', function () {
    $this->filesystem->createDirectory('domains/site/public_html/assets');

    expect(File::isDirectory($this->attackerHome.'/domains/site/public_html/assets'))->toBeTrue();
});

test('createDirectory still creates nested directories', function () {
    $this->filesystem->createDirectory('domains/site/public_html/a/b/c');

    expect(File::isDirectory($this->attackerHome.'/domains/site/public_html/a/b/c'))->toBeTrue();
});

test('createDirectory is a no-op when the directory already exists', function () {
    $this->filesystem->createDirectory('domains/site/public_html/assets');
    $this->filesystem->createDirectory('domains/site/public_html/assets');

    expect(File::isDirectory($this->attackerHome.'/domains/site/public_html/assets'))->toBeTrue();
});

test('createDirectory refuses a symlinked parent directory', function () {
    symlink($this->root.'/victim_ln/domains/victim-site/public_html', $this->attackerHome.'/link');

    expect(fn () => $this->filesystem->createDirectory('link/planted'))
        ->toThrow(League\Flysystem\UnableToCreateDirectory::class);

    expect(File::exists($this->root.'/victim_ln/domains/victim-site/public_html/planted'))->toBeFalse();
});

test('createDirectory refuses to treat a symlink as an existing directory', function () {
    symlink($this->root.'/victim_ln/domains/victim-site/public_html', $this->attackerHome.'/link');

    expect(fn () => $this->filesystem->createDirectory('link'))
        ->toThrow(League\Flysystem\UnableToCreateDirectory::class);
});

test('listing a directory containing a symlink skips it instead of throwing', function () {
    // DISALLOW_LINKS threw here, which made any directory holding a link
    // unbrowsable - a tenant's own public/storage link was enough to break it
    $this->filesystem->write('domains/site/public_html/real.txt', 'mine');
    symlink($this->root.'/victim_ln/domains/victim-site/public_html', $this->attackerHome.'/domains/site/public_html/linked');

    $listed = collect($this->filesystem->listContents('domains/site/public_html')->toArray())
        ->map(fn ($item) => basename($item->path()))
        ->all();

    expect($listed)->toContain('real.txt')
        ->and($listed)->not->toContain('linked');
});

test('a skipped symlink is still refused as a write target', function () {
    // listing it leniently must not make it reachable
    symlink($this->root.'/victim_ln/domains/victim-site/public_html', $this->attackerHome.'/link');

    expect(fn () => $this->filesystem->write('link/evil.php', '<?php system($_GET[0]);'))
        ->toThrow(League\Flysystem\UnableToWriteFile::class);

    expect(File::exists($this->root.'/victim_ln/domains/victim-site/public_html/evil.php'))->toBeFalse();
});
