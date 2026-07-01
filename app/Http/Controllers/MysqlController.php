<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateDatabaseRequest;
use App\Http\Requests\DeleteDatabaseRequest;
use App\Http\Requests\UpdateDatabaseRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class MysqlController extends Controller
{
    public function __construct(private DatabasesController $databases) {}

    public function index(Request $request): Response
    {
        return $this->databases->index($request);
    }

    public function getCharsetsAndCollations(Request $request): JsonResponse
    {
        return $this->databases->getEngineOptions($request);
    }

    public function store(CreateDatabaseRequest $request): RedirectResponse
    {
        return $this->databases->store($request);
    }

    public function update(UpdateDatabaseRequest $request): RedirectResponse
    {
        return $this->databases->update($request);
    }

    public function destroy(DeleteDatabaseRequest $request): RedirectResponse
    {
        return $this->databases->destroy($request);
    }
}
