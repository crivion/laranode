<?php

namespace App\Http\Controllers;

use App\Actions\Backups\DownloadBackupAction;
use App\Actions\Backups\GetBackupsAction;
use App\Http\Requests\CreateBackupRequest;
use App\Http\Requests\RestoreBackupRequest;
use App\Jobs\RunRestoreJob;
use App\Models\Backup;
use App\Models\BackupDestination;
use App\Models\Database;
use App\Models\User;
use App\Models\Website;
use App\Services\Backups\CreateBackupService;
use App\Services\Backups\PruneBackupsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BackupController extends Controller
{
    public function index(Request $request, GetBackupsAction $action): Response
    {
        $user = $request->user();
        $userIds = $user->isAdmin() ? User::pluck('id') : collect([$user->id]);

        return Inertia::render('Backups/Index', [
            'backups' => $action->execute($request->boolean('snapshots')),
            'schedules' => \App\Models\BackupSchedule::mine()->with(['website:id,url', 'database:id,name', 'destination:id,name'])->latest()->get(),
            'websites' => Website::whereIn('user_id', $userIds)->select('id', 'user_id', 'url')->orderBy('url')->get(),
            'databases' => Database::whereIn('user_id', $userIds)->select('id', 'user_id', 'name')->orderBy('name')->get(),
            'users' => $user->isAdmin() ? User::select('id', 'username')->orderBy('username')->get() : [],
            'destinations' => BackupDestination::whereNull('user_id')->orWhere('user_id', $user->id)->select('id', 'name', 'driver', 'is_default')->get(),
            'showSnapshots' => $request->boolean('snapshots'),
        ]);
    }

    public function store(CreateBackupRequest $request, CreateBackupService $service): RedirectResponse
    {
        $service->handle($request->targetUser(), $request->validated());

        return back()->with('success', 'Backup queued.');
    }

    public function download(Backup $backup, DownloadBackupAction $action)
    {
        Gate::authorize('download', $backup);
        abort_unless($backup->status === 'completed', 409, 'Backup is not complete.');

        return $action->execute($backup);
    }

    public function restore(RestoreBackupRequest $request, Backup $backup): RedirectResponse
    {
        $backupId = DB::transaction(function () use ($backup) {
            $lockedBackup = Backup::query()->lockForUpdate()->findOrFail($backup->id);
            Gate::authorize('restore', $lockedBackup);
            abort_unless($lockedBackup->canAttemptRestore(), 409, 'This backup cannot be restored or has reached the restore-attempt limit.');
            $updates = [
                'status' => 'pending',
                'error' => null,
            ];
            if ($lockedBackup->status === 'failed') {
                $updates['restore_attempts'] = $lockedBackup->restore_attempts + 1;
            }
            $lockedBackup->update($updates);

            return $lockedBackup->id;
        });
        RunRestoreJob::dispatch($backupId, $request->user()->id);

        return back()->with('success', 'Restore queued. A safety snapshot will be created first.');
    }

    public function destroy(Backup $backup, PruneBackupsService $service): RedirectResponse
    {
        Gate::authorize('delete', $backup);
        abort_if(in_array($backup->status, ['pending', 'running']), 409, 'An active backup cannot be deleted.');
        $service->delete($backup);

        return back()->with('success', 'Backup deleted.');
    }

    public function statuses(Request $request): JsonResponse
    {
        return response()->json(Backup::mine()->select('id', 'status', 'error', 'finished_at')->latest()->get());
    }
}
