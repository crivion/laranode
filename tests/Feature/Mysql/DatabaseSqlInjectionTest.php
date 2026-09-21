<?php

use App\Actions\MySQL\GetCharsetsAndCollationsAction;
use App\Models\Database;
use App\Models\User;
use App\Rules\SupportedCollation;
use App\Services\MySQL\CreateDatabaseService;
use App\Services\MySQL\UpdateDatabaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Payload from GHSA-68m9-96fg-rqm5: closes the literal and adds a second
// account to the same ALTER USER statement.
const TAKEOVER_PASSWORD = "x', 'laranode'@'localhost' IDENTIFIED BY 'PanelTakeover!42";

beforeEach(function () {
    config()->set('app.key', str_repeat('a', 32));
    config()->set('laranode.demo.enabled', false);

    $this->mock(GetCharsetsAndCollationsAction::class)
        ->shouldReceive('execute')
        ->andReturn([
            'charsets' => [['name' => 'utf8mb4'], ['name' => 'latin1']],
            'collations' => [
                ['name' => 'utf8mb4_unicode_ci', 'charset' => 'utf8mb4'],
                ['name' => 'latin1_swedish_ci', 'charset' => 'latin1'],
            ],
        ]);

    $this->user = User::factory()->create(['username' => 'alice']);
    $this->database = Database::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'alice_app',
        'db_user' => 'alice_app',
    ]);
});

function alterUserQuery(array $queries): string
{
    return collect($queries)->pluck('query')->first(fn ($query) => str_starts_with($query, 'ALTER USER'));
}

test('the database password is sent as a single quoted literal when updating', function () {
    $queries = DB::pretend(fn () => (new UpdateDatabaseService($this->database, [
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'db_password' => TAKEOVER_PASSWORD,
    ]))->handle());

    expect(alterUserQuery($queries))
        ->toBe("ALTER USER `alice_app`@'localhost' IDENTIFIED BY ".DB::getPdo()->quote(TAKEOVER_PASSWORD))
        ->not->toContain("IDENTIFIED BY 'x', 'laranode'");
});

test('the database password is sent as a single quoted literal when creating', function () {
    $queries = DB::pretend(fn () => (new CreateDatabaseService([
        'name' => 'alice_new',
        'db_user' => 'alice_new',
        'db_pass' => TAKEOVER_PASSWORD,
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ], $this->user))->handle());

    $createUser = collect($queries)->pluck('query')->first(fn ($query) => str_starts_with($query, 'CREATE USER'));

    expect($createUser)
        ->toBe("CREATE USER IF NOT EXISTS `alice_new`@'localhost' IDENTIFIED BY ".DB::getPdo()->quote(TAKEOVER_PASSWORD));
});

test('charset and collation must be a pair the server supports', function (string $method, array $payload, string $invalid) {
    $base = $method === 'post'
        ? ['name' => 'alice_new', 'db_user' => 'alice_new', 'db_pass' => 'password123']
        : ['id' => $this->database->id];

    $this->actingAs($this->user)
        ->{$method}(route($method === 'post' ? 'mysql.store' : 'mysql.update'), array_merge($base, $payload))
        ->assertSessionHasErrors($invalid);
})->with([
    'charset injection on create' => ['post', ['charset' => 'utf8mb4 COLLATE utf8mb4_bin, ENCRYPTION = "N"', 'collation' => 'utf8mb4_unicode_ci'], 'charset'],
    'collation injection on update' => ['patch', ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci; DROP DATABASE laranode'], 'collation'],
    'unknown collation' => ['patch', ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_made_up'], 'collation'],
    'mismatched pair' => ['patch', ['charset' => 'utf8mb4', 'collation' => 'latin1_swedish_ci'], 'collation'],
]);

test('supported charset and collation pairs pass', function () {
    $validator = Validator::make(
        ['collation' => 'latin1_swedish_ci'],
        ['collation' => [new SupportedCollation('latin1')]],
    );

    expect($validator->passes())->toBeTrue();
});
