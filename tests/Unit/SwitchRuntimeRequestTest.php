<?php

use App\Http\Requests\SwitchRuntimeRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

// FormRequest validation unit tests — uses TestCase for the app container
// (needed for Validator facade and auth()) but does NOT need DB access.
uses(TestCase::class);

function validateRuntime(mixed $value): bool
{
    $request = new SwitchRuntimeRequest;
    $validator = Validator::make(
        ['runtime' => $value],
        $request->rules()
    );

    return $validator->passes();
}

// --- ACCEPTED runtimes ---

test('php-fpm passes validation', function () {
    expect(validateRuntime('php-fpm'))->toBeTrue();
});

test('frankenphp passes validation', function () {
    expect(validateRuntime('frankenphp'))->toBeTrue();
});

// --- REJECTED runtimes ---

test('swoole is rejected (deferred to v2)', function () {
    expect(validateRuntime('swoole'))->toBeFalse();
});

test('empty string is rejected', function () {
    expect(validateRuntime(''))->toBeFalse();
});

test('missing runtime field is rejected', function () {
    $request = new SwitchRuntimeRequest;
    $validator = Validator::make([], $request->rules());
    expect($validator->passes())->toBeFalse();
});

test('path traversal is rejected', function () {
    expect(validateRuntime('../etc/passwd'))->toBeFalse();
});

test('shell injection is rejected', function () {
    expect(validateRuntime('; rm -rf /'))->toBeFalse();
});

test('arbitrary string is rejected', function () {
    expect(validateRuntime('nginx'))->toBeFalse();
});

test('numeric value cast to string is rejected', function () {
    expect(validateRuntime('123'))->toBeFalse();
});

// --- authorize() ---

test('authorize returns true when user is authenticated', function () {
    $user = User::factory()->make();
    $this->actingAs($user);

    $request = new SwitchRuntimeRequest;
    $request->setContainer(app());

    expect($request->authorize())->toBeTrue();
});

test('authorize returns false when unauthenticated', function () {
    $request = new SwitchRuntimeRequest;
    $request->setContainer(app());

    expect($request->authorize())->toBeFalse();
});
