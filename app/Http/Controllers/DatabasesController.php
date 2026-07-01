<?php

namespace App\Http\Controllers;

use App\Databases\DatabaseSpec;
use App\Databases\EngineManager;
use App\Http\Requests\CreateDatabaseRequest;
use App\Http\Requests\DeleteDatabaseRequest;
use App\Http\Requests\UpdateDatabaseRequest;
use App\Models\Database;
use App\Services\Database\CreateDatabaseService;
use App\Services\Database\DeleteDatabaseService;
use App\Services\Database\GetDatabasesWithStatsService;
use App\Services\Database\UpdateDatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DatabasesController extends Controller
{
    public function __construct(
        private EngineManager $engineManager,
    ) {}

    public function index(Request $request): \Inertia\Response
    {
        $service = new GetDatabasesWithStatsService($this->engineManager);
        $databases = $service->handle();

        return Inertia::render('Databases/Index', [
            'databases' => $databases,
        ]);
    }

    public function getEngineOptions(Request $request): JsonResponse
    {
        $available = $this->engineManager->available();

        if (empty($available)) {
            return response()->json(['engines' => [], 'capabilities' => null]);
        }

        $engine = $request->query('engine');
        $capabilities = null;

        if ($engine && isset($available[$engine])) {
            $capabilities = $this->engineManager->for($engine)->capabilities();
        }

        $engines = array_keys($available);

        return response()->json([
            'engines' => $engines,
            'capabilities' => $capabilities,
        ]);
    }

    public function store(CreateDatabaseRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $engine = $validated['engine'];

        $driver = $this->engineManager->for($engine);

        $spec = new DatabaseSpec(
            name: $validated['name'],
            dbUser: $validated['db_user'],
            password: $validated['db_pass'],
            userId: $user->id,
            options: array_filter([
                'charset' => $validated['charset'] ?? null,
                'collation' => $validated['collation'] ?? null,
                'encoding' => $validated['encoding'] ?? null,
                'locale' => $validated['locale'] ?? null,
            ], fn ($v) => $v !== null),
        );

        $service = new CreateDatabaseService($driver);
        $service->handle($spec, $engine);

        session()->flash('success', 'Database created successfully!');

        return redirect()->route('databases.index');
    }

    public function update(UpdateDatabaseRequest $request): RedirectResponse
    {
        $user = $request->user();
        $databaseId = $request->integer('id');

        $database = Database::where('id', $databaseId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        Gate::authorize('update', $database);

        $driver = $this->engineManager->for($database->engine);
        $service = new UpdateDatabaseService($driver);
        $service->handle($database, $request->validated());

        session()->flash('success', 'Database updated successfully!');

        return redirect()->route('databases.index');
    }

    public function destroy(DeleteDatabaseRequest $request): RedirectResponse
    {
        $user = $request->user();
        $databaseId = $request->integer('id');

        $database = Database::where('id', $databaseId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        Gate::authorize('delete', $database);

        $driver = $this->engineManager->for($database->engine);
        $service = new DeleteDatabaseService($driver);
        $service->handle($database);

        session()->flash('success', 'Database deleted successfully!');

        return redirect()->route('databases.index');
    }
}
