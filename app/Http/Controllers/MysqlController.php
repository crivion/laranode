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
use App\Support\DemoData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MysqlController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $user = $request->user();
        $databases = config('laranode.demo.enabled')
            ? Database::with('website:id,url')->where('user_id', $user->id)->get()->map(fn (Database $database) => [
                'id' => $database->id,
                'name' => $database->name,
                'user' => $user->username,
                'db_user' => $database->db_user,
                'tables' => 24,
                'sizeMb' => 18.7,
                'charset' => $database->charset,
                'collation' => $database->collation,
                'website_id' => $database->website_id,
                'website_url' => $database->website?->url,
            ])->all()
            : (new GetDatabasesWithStatsAction($user))->execute();

        return Inertia::render('Mysql/Index', [
            'databases' => $databases,
            'websites' => $user->websites()->select('id', 'url')->orderBy('url')->get(),
        ]);
    }

    public function getCharsetsAndCollations(GetCharsetsAndCollationsAction $action): JsonResponse
    {
        if (config('laranode.demo.enabled')) {
            return response()->json(DemoData::charsets());
        }

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

        Gate::authorize('update', $database);

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

        Gate::authorize('delete', $database);

        (new DeleteDatabaseService($database))->handle();

        session()->flash('success', 'Database deleted successfully!');

        return redirect()->route('mysql.index');
    }
}
