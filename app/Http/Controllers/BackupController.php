<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateBackupRequest;
use App\Http\Requests\CreateScheduledBackupRequest;
use App\Http\Requests\RestoreBackupRequest;
use App\Models\Backup;
use App\Models\ScheduledBackup;
use App\Services\Backups\BackupService;
use App\Services\Backups\RestoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $backups = Backup::query()
            ->mine()
            ->with('operation')
            ->latest()
            ->paginate(20);

        $schedules = ScheduledBackup::query()
            ->mine()
            ->latest()
            ->get();

        return Inertia::render('Backups/Index', [
            'backups' => $backups,
            'schedules' => $schedules,
        ]);
    }

    public function store(CreateBackupRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $service = new BackupService;
        $operation = $service->handle($validated, $request->user());

        return response()->json(['operation_id' => $operation->id]);
    }

    public function destroy(Request $request, Backup $backup): RedirectResponse
    {
        Gate::authorize('delete', $backup);

        if ($backup->disk_name && $backup->path) {
            $this->ensureDiskRegistered($backup);
            Storage::disk($backup->disk_name)->delete($backup->path);
        }

        $backup->delete();

        session()->flash('success', 'Backup deleted.');

        return redirect()->route('backups.index');
    }

    public function restore(RestoreBackupRequest $request, Backup $backup): JsonResponse
    {
        Gate::authorize('restore', $backup);

        $validated = $request->validated();
        $service = new RestoreService;
        $operation = $service->handle($backup, $validated['new_target'], $request->user());

        return response()->json(['operation_id' => $operation->id]);
    }

    public function download(Request $request, Backup $backup): StreamedResponse|RedirectResponse
    {
        Gate::authorize('download', $backup);

        $this->ensureDiskRegistered($backup);

        $disk = Storage::disk($backup->disk_name);

        if ($backup->storage === 's3') {
            /** @var \League\Flysystem\AwsS3V3\AwsS3V3Adapter $adapter */
            $adapter = $disk->getAdapter();
            $client = $adapter->getClient();
            $command = $client->getCommand('GetObject', [
                'Bucket' => Config::get("filesystems.disks.{$backup->disk_name}.bucket"),
                'Key' => $backup->path,
            ]);
            $presignedRequest = $client->createPresignedRequest($command, '+15 minutes');

            return redirect((string) $presignedRequest->getUri());
        }

        return $disk->download($backup->path, basename($backup->path));
    }

    public function storeSchedule(CreateScheduledBackupRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        ScheduledBackup::create([
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'target' => $validated['target'],
            'storage' => $validated['storage'],
            'cron_expression' => $validated['cron_expression'],
            'retention_count' => $validated['retention_count'],
            's3_key' => $validated['s3_key'] ?? null,
            's3_secret' => $validated['s3_secret'] ?? null,
            's3_region' => $validated['s3_region'] ?? null,
            's3_bucket' => $validated['s3_bucket'] ?? null,
            's3_endpoint' => $validated['s3_endpoint'] ?? null,
            'enabled' => $validated['enabled'] ?? true,
        ]);

        session()->flash('success', 'Scheduled backup created.');

        return redirect()->route('backups.index');
    }

    public function destroySchedule(Request $request, ScheduledBackup $scheduledBackup): RedirectResponse
    {
        Gate::authorize('delete', $scheduledBackup);

        $scheduledBackup->delete();

        session()->flash('success', 'Scheduled backup deleted.');

        return redirect()->route('backups.index');
    }

    /**
     * If the backup uses S3 storage, register the disk config from encrypted
     * credentials on the Backup row so Storage::disk() resolves correctly.
     */
    private function ensureDiskRegistered(Backup $backup): void
    {
        $s3Config = $backup->s3DiskConfig();
        if ($s3Config !== null) {
            Config::set("filesystems.disks.{$backup->disk_name}", $s3Config);
        }
    }
}
