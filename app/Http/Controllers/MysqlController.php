<?php

namespace App\Http\Controllers;

use App\Actions\MySQL\GetCharsetsAndCollationsAction;
use App\Actions\MySQL\GetDatabasesWithStatsAction;
use App\Http\Requests\CreateDatabaseRequest;
use App\Http\Requests\DeleteDatabaseRequest;
use App\Http\Requests\UpdateDatabaseRequest;
use App\Models\Database;
use App\Services\MySQL\CreateDatabaseService;
use App\Services\MySQL\DeleteDatabaseService;
use App\Services\MySQL\UpdateDatabaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class MysqlController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $user = $request->user();
        $databases = (new GetDatabasesWithStatsAction($user))->execute();

        return Inertia::render('Mysql/Index', [
            'databases' => $databases,
        ]);
    }

    public function getCharsetsAndCollations(GetCharsetsAndCollationsAction $action): JsonResponse
    {
        return response()->json($action->execute());
    }

    public function store(CreateDatabaseRequest $request): RedirectResponse
    {
        $user = $request->user();
        
        (new CreateDatabaseService($request->validated(), $user))->handle();

        session()->flash('success', 'Database created successfully!');

        return redirect()->route('mysql.index');
    }

    public function update(UpdateDatabaseRequest $request): RedirectResponse
    {
        $user = $request->user();
        $databaseId = $request->integer('id');

        $database = Database::where('id', $databaseId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->authorize('update', $database);

        (new UpdateDatabaseService($database, $request->validated()))->handle();

        session()->flash('success', 'Database updated successfully!');

        return redirect()->route('mysql.index');
    }

    public function destroy(DeleteDatabaseRequest $request): RedirectResponse
    {
        $user = $request->user();
        $databaseId = $request->integer('id');

        $database = Database::where('id', $databaseId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->authorize('delete', $database);

        (new DeleteDatabaseService($database))->handle();

        session()->flash('success', 'Database deleted successfully!');

        return redirect()->route('mysql.index');
    }
}
