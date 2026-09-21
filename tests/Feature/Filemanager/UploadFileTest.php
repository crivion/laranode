<?php

use App\Actions\Filemanager\UploadFileAction;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    Process::fake();

    $this->action = new UploadFileAction;

    // stand in for /home, with two tenants side by side
    $this->home = sys_get_temp_dir().'/laranode-upload-'.uniqid();
    File::makeDirectory($this->home.'/attacker_ln/domains/site/public_html', 0777, true);
    File::makeDirectory($this->home.'/victim_ln/domains/victim-site/public_html', 0777, true);

    $this->attackerHome = $this->home.'/attacker_ln';
});

afterEach(function () {
    File::deleteDirectory($this->home);
});

function uploadRequest(array $overrides, string $homedir): Request
{
    $request = Request::create('', 'POST', array_merge([
        'chunkIndex' => 0,
        'totalChunks' => 1,
        'originalName' => 'shell.php',
        'path' => 'domains/site/public_html',
    ], $overrides), [], [
        'file' => UploadedFile::fake()->createWithContent('chunk', '<?php system($_GET[0]);'),
    ]);

    $request->setUserResolver(fn () => new class($homedir, 'attacker_ln')
    {
        public function __construct(public string $homedir, public string $systemUsername) {}
    });

    return $request;
}

test('it uploads a chunk inside the user home', function () {
    $response = $this->action->execute(uploadRequest([], $this->attackerHome));

    expect($response->getStatusCode())->toBe(200)
        ->and(File::exists($this->attackerHome.'/domains/site/public_html/shell.php'))->toBeTrue();
});

test('it appends successive chunks to the same file', function () {
    $this->action->execute(uploadRequest([], $this->attackerHome));
    $this->action->execute(uploadRequest(['chunkIndex' => 1, 'totalChunks' => 2], $this->attackerHome));

    expect(File::get($this->attackerHome.'/domains/site/public_html/shell.php'))
        ->toBe(str_repeat('<?php system($_GET[0]);', 2));
});

test('it refuses a path that traverses into another tenant', function () {
    $response = $this->action->execute(uploadRequest([
        'path' => '../victim_ln/domains/victim-site/public_html',
    ], $this->attackerHome));

    expect($response->getStatusCode())->toBe(422)
        ->and(json_decode($response->getContent()))->toHaveProperty('error', 'Invalid upload path')
        ->and(File::exists($this->home.'/victim_ln/domains/victim-site/public_html/shell.php'))->toBeFalse();
});

test('it refuses traversal buried mid-path', function () {
    $response = $this->action->execute(uploadRequest([
        'path' => 'domains/site/public_html/../../../../victim_ln/domains/victim-site/public_html',
    ], $this->attackerHome));

    expect($response->getStatusCode())->toBe(422)
        ->and(File::exists($this->home.'/victim_ln/domains/victim-site/public_html/shell.php'))->toBeFalse();
});

test('it refuses traversal smuggled through the file name', function () {
    $response = $this->action->execute(uploadRequest([
        'path' => 'domains/site/public_html',
        'originalName' => '../../../../victim_ln/shell.php',
    ], $this->attackerHome));

    expect($response->getStatusCode())->toBe(422)
        ->and(File::exists($this->home.'/victim_ln/shell.php'))->toBeFalse()
        ->and(File::exists($this->attackerHome.'/domains/site/public_html/shell.php'))->toBeFalse();
});

test('it refuses a symlink that escapes the home', function () {
    symlink($this->home.'/victim_ln/domains/victim-site/public_html', $this->attackerHome.'/escape');

    $response = $this->action->execute(uploadRequest([
        'path' => 'escape',
    ], $this->attackerHome));

    expect($response->getStatusCode())->toBe(422)
        ->and(File::exists($this->home.'/victim_ln/domains/victim-site/public_html/shell.php'))->toBeFalse();
});

test('it refuses a symlinked parent directory at write time', function () {
    symlink(
        $this->home.'/victim_ln/domains/victim-site/public_html',
        $this->attackerHome.'/domains/site/public_html/escape',
    );

    $response = $this->action->execute(uploadRequest([
        'path' => 'domains/site/public_html/escape',
    ], $this->attackerHome));

    expect($response->getStatusCode())->toBe(422)
        ->and(File::exists($this->home.'/victim_ln/domains/victim-site/public_html/shell.php'))->toBeFalse();
});

test('it refuses an out of range chunk index', function () {
    $response = $this->action->execute(uploadRequest([
        'chunkIndex' => 2,
        'totalChunks' => 2,
    ], $this->attackerHome));

    expect($response->getStatusCode())->toBe(422);
});

test('it refuses a symlinked destination file', function () {
    $victim = $this->home.'/victim_ln/domains/victim-site/public_html/shell.php';

    // the tenant plants the link inside their own home, over SSH or from their
    // own site's PHP - the directory it sits in is legitimately theirs
    symlink($victim, $this->attackerHome.'/domains/site/public_html/shell.php');

    $response = $this->action->execute(uploadRequest([], $this->attackerHome));

    expect($response->getStatusCode())->toBe(200)
        ->and(File::exists($victim))->toBeFalse()
        ->and(is_link($this->attackerHome.'/domains/site/public_html/shell.php'))->toBeFalse();
});

test('it refuses a symlink swapped in between chunks', function () {
    $victim = $this->home.'/victim_ln/domains/victim-site/public_html/shell.php';

    $this->action->execute(uploadRequest([], $this->attackerHome));

    // first chunk landed as a real file; the tenant now swaps it for a link
    unlink($this->attackerHome.'/domains/site/public_html/shell.php');
    symlink($victim, $this->attackerHome.'/domains/site/public_html/shell.php');

    $response = $this->action->execute(uploadRequest(['chunkIndex' => 1, 'totalChunks' => 2], $this->attackerHome));

    expect($response->getStatusCode())->toBe(422)
        ->and(File::exists($victim))->toBeFalse();
});

test('it refuses a hard-linked destination between chunks', function () {
    $victim = $this->home.'/victim_ln/domains/victim-site/public_html/shell.php';
    $destination = $this->attackerHome.'/domains/site/public_html/shell.php';

    $this->action->execute(uploadRequest([], $this->attackerHome));
    link($destination, $victim);

    $response = $this->action->execute(uploadRequest(['chunkIndex' => 1, 'totalChunks' => 2], $this->attackerHome));

    expect($response->getStatusCode())->toBe(422)
        ->and(File::get($victim))->toBe('<?php system($_GET[0]);');
});

test('the first chunk replaces a hard link without truncating its other name', function () {
    $victim = $this->home.'/victim_ln/domains/victim-site/public_html/shell.php';
    $destination = $this->attackerHome.'/domains/site/public_html/shell.php';
    File::put($victim, 'victim-content');
    link($victim, $destination);

    $response = $this->action->execute(uploadRequest([], $this->attackerHome));

    expect($response->getStatusCode())->toBe(200)
        ->and(File::get($victim))->toBe('victim-content')
        ->and(File::get($destination))->toBe('<?php system($_GET[0]);');
});

test('it refuses a path whose directory does not exist', function () {
    $response = $this->action->execute(uploadRequest([
        'path' => 'domains/site/nope',
    ], $this->attackerHome));

    expect($response->getStatusCode())->toBe(422);
});

test('it validates required fields', function () {
    expect(fn () => $this->action->execute(Request::create('', 'POST', [])))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});
