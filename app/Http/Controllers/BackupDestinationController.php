<?php

namespace App\Http\Controllers;

use App\Actions\Backups\TestDestinationAction;
use App\Http\Requests\CreateBackupDestinationRequest;
use App\Models\BackupDestination;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BackupDestinationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('BackupDestinations/Index', [
            'destinations' => BackupDestination::select('id', 'name', 'driver', 'config', 'is_default', 'created_at')
                ->latest()
                ->get()
                ->map(function (BackupDestination $destination): array {
                    $config = $destination->config ?? [];

                    unset($config[$destination->driver === 's3' ? 'secret' : 'password']);

                    return [
                        'id' => $destination->id,
                        'name' => $destination->name,
                        'driver' => $destination->driver,
                        'config' => $config,
                        'is_default' => $destination->is_default,
                        'created_at' => $destination->created_at,
                    ];
                }),
        ]);
    }

    public function store(CreateBackupDestinationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['config'] ??= [];
        $destination = BackupDestination::create($data);
        $this->normalizeDefault($destination);

        return back()->with('success', 'Backup destination created.');
    }

    public function update(CreateBackupDestinationRequest $request, BackupDestination $backupDestination): RedirectResponse
    {
        $data = $request->validated();
        $incoming = array_filter($data['config'] ?? [], fn ($value) => $value !== null && $value !== '');
        $data['config'] = [...($backupDestination->config ?? []), ...$incoming];
        $backupDestination->update($data);
        $this->normalizeDefault($backupDestination);

        return back()->with('success', 'Backup destination updated.');
    }

    public function destroy(BackupDestination $backupDestination): RedirectResponse
    {
        $backupDestination->delete();

        return back()->with('success', 'Backup destination deleted.');
    }

    public function test(BackupDestination $backupDestination, TestDestinationAction $action): RedirectResponse
    {
        try {
            $action->execute($backupDestination);
        } catch (Throwable $exception) {
            return back()->withErrors(['destination' => $exception->getMessage()]);
        }

        return back()->with('success', 'Connection successful.');
    }

    private function normalizeDefault(BackupDestination $destination): void
    {
        if ($destination->is_default) {
            BackupDestination::whereKeyNot($destination->id)->update(['is_default' => false]);
        }
    }
}
