<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCronJobRequest;
use App\Models\CronJob;
use App\Models\Operation;
use App\Services\CronJobs\CreateCronJobException;
use App\Services\CronJobs\CreateCronJobService;
use App\Services\CronJobs\DeleteCronJobException;
use App\Services\CronJobs\DeleteCronJobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CronJobsController extends Controller
{
    public function index(): \Inertia\Response
    {
        $cronJobs = CronJob::mine()->orderBy('id')->get();

        return Inertia::render('CronJobs/Index', compact('cronJobs'));
    }

    public function store(StoreCronJobRequest $request, CreateCronJobService $createService): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Operation is created BEFORE the transaction so that a rollback
        // inside the transaction does not destroy the audit row.
        $op = Operation::create([
            'user_id' => $user->id,
            'type' => 'cron.create',
            'target' => $validated['command'],
            'status' => 'queued',
        ]);

        $op->markRunning();

        try {
            DB::transaction(function () use ($user, $validated, $createService) {
                CronJob::create([
                    'user_id' => $user->id,
                    'schedule' => $validated['schedule'],
                    'command' => $validated['command'],
                    'label' => $validated['label'] ?? null,
                ]);

                $createService->handle($user);
            });

            // markFinished OUTSIDE the transaction — a rollback must not destroy the audit row.
            $op->markFinished(0);
            session()->flash('success', 'Cron job created successfully.');
        } catch (CreateCronJobException $e) {
            $op->markFinished(1);
            session()->flash('error', 'Failed to create cron job: '.$e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $op->markFinished(1);
            session()->flash('error', 'An unexpected error occurred while creating the cron job.');
        }

        return redirect()->route('cron-jobs.index');
    }

    public function destroy(Request $request, CronJob $cronJob): RedirectResponse
    {
        Gate::authorize('delete', $cronJob);

        $user = $request->user();

        // Operation outside transaction so rollback cannot destroy audit row.
        $op = Operation::create([
            'user_id' => $user->id,
            'type' => 'cron.delete',
            'target' => $cronJob->command,
            'status' => 'queued',
        ]);

        $op->markRunning();

        try {
            DB::transaction(function () use ($user, $cronJob) {
                (new DeleteCronJobService)->handle($user, $cronJob);
                $cronJob->delete();
            });

            // markFinished OUTSIDE the transaction — a rollback must not destroy the audit row.
            $op->markFinished(0);
            session()->flash('success', 'Cron job deleted successfully.');
        } catch (DeleteCronJobException $e) {
            $op->markFinished(1);
            session()->flash('error', 'Failed to delete cron job: '.$e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $op->markFinished(1);
            session()->flash('error', 'An unexpected error occurred while deleting the cron job.');
        }

        return redirect()->route('cron-jobs.index');
    }

    public function toggleActive(Request $request, CronJob $cronJob): RedirectResponse
    {
        Gate::authorize('update', $cronJob);

        $user = $request->user();
        $originalActive = $cronJob->active;

        $op = Operation::create([
            'user_id' => $user->id,
            'type' => 'cron.toggle',
            'target' => $cronJob->command,
            'status' => 'queued',
        ]);

        $op->markRunning();

        try {
            DB::transaction(function () use ($user, $cronJob, $originalActive) {
                $cronJob->update(['active' => ! $originalActive]);
                (new CreateCronJobService)->handle($user);
            });

            // markFinished OUTSIDE the transaction — a rollback must not destroy the audit row.
            // No manual revert needed: if the transaction rolled back, the DB column is still $originalActive.
            $op->markFinished(0);
            session()->flash('success', 'Cron job '.($originalActive ? 'paused' : 'activated').' successfully.');
        } catch (CreateCronJobException $e) {
            $op->markFinished(1);
            session()->flash('error', 'Failed to toggle cron job: '.$e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $op->markFinished(1);
            session()->flash('error', 'An unexpected error occurred while toggling the cron job.');
        }

        return redirect()->route('cron-jobs.index');
    }
}
