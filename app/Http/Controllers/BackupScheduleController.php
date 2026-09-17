<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateBackupScheduleRequest;
use App\Models\BackupSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class BackupScheduleController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('backups.index');
    }

    public function store(CreateBackupScheduleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->targetUser()->id;
        $schedule = new BackupSchedule($data);
        $schedule->next_run_at = $schedule->calculateNextRun();
        $schedule->save();

        return back()->with('success', 'Backup schedule created.');
    }

    public function update(CreateBackupScheduleRequest $request, BackupSchedule $backupSchedule): RedirectResponse
    {
        Gate::authorize('update', $backupSchedule);
        $backupSchedule->fill($request->validated());
        $backupSchedule->user_id = $request->targetUser()->id;
        $backupSchedule->next_run_at = $backupSchedule->calculateNextRun();
        $backupSchedule->save();

        return back()->with('success', 'Backup schedule updated.');
    }

    public function destroy(BackupSchedule $backupSchedule): RedirectResponse
    {
        Gate::authorize('delete', $backupSchedule);
        $backupSchedule->delete();

        return back()->with('success', 'Backup schedule deleted.');
    }
}
