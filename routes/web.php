<?php

use App\Http\Controllers\AccountsController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CronJobsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabasesController;
use App\Http\Controllers\FilemanagerController;
use App\Http\Controllers\FirewallController;
use App\Http\Controllers\NotificationPreferencesController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\PHPManagerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StatsHistoryController;
use App\Http\Controllers\DbServiceController;
use App\Http\Controllers\WebsiteController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

// Dashboards [Admin | User]
Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');
Route::get('/dashboard/admin', [DashboardController::class, 'admin'])->middleware(['auth', AdminMiddleware::class])->name('dashboard.admin');
Route::get('/dashboard/admin/get/top-sort', [DashboardController::class, 'getTopSort'])->middleware(['auth', AdminMiddleware::class])->name('dashboard.admin.getTopSort');
Route::patch('/dashboard/admin/set/top-sort', [DashboardController::class, 'setTopSort'])->middleware(['auth', AdminMiddleware::class])->name('dashboard.admin.setTopSort');
Route::post('/dashboard/admin/gpu/rescan', [DashboardController::class, 'rescanGpu'])->middleware(['auth', AdminMiddleware::class])->name('dashboard.admin.gpuRescan');
Route::get('/dashboard/user', [DashboardController::class, 'user'])->middleware(['auth'])->name('dashboard.user');

// Accounts [Admin]
Route::resource('/accounts', AccountsController::class)->middleware(['auth', AdminMiddleware::class])->except(['create', 'edit', 'show']);
Route::get('/accounts/impersonate/{user}', [AccountsController::class, 'impersonate'])->middleware(['auth', AdminMiddleware::class])->name('accounts.impersonate');
Route::get('/accounts/leave-impersonation', [AccountsController::class, 'leaveImpersonation'])->middleware(['auth'])->name('accounts.leaveImpersonation');

// Websites [Admin | User]
Route::resource('/websites', WebsiteController::class)->middleware(['auth'])->except(['create', 'edit', 'show']);
Route::post('/websites/{website}/ssl/toggle', [WebsiteController::class, 'toggleSsl'])->middleware(['auth'])->name('websites.ssl.toggle');
Route::get('/websites/{website}/ssl/status', [WebsiteController::class, 'checkSslStatus'])->middleware(['auth'])->name('websites.ssl.status');
Route::post('/websites/{website}/runtime', [WebsiteController::class, 'switchRuntime'])->middleware(['auth'])->name('websites.runtime.switch');

// PHP FPM Pools [Admin | User]
Route::get('/php', [PHPManagerController::class, 'index'])->middleware(['auth', AdminMiddleware::class])->name('php.index');
Route::get('/php/get-versions', [PHPManagerController::class, 'getVersions'])->middleware(['auth'])->name('php.get-versions');
Route::get('/php/list', [PHPManagerController::class, 'list'])->middleware(['auth', AdminMiddleware::class])->name('php.list');
Route::post('/php/install', [PHPManagerController::class, 'install'])->middleware(['auth', AdminMiddleware::class])->name('php.install');
Route::delete('/php/uninstall', [PHPManagerController::class, 'uninstall'])->middleware(['auth', AdminMiddleware::class])->name('php.uninstall');
Route::post('/php/service/toggle', [PHPManagerController::class, 'toggleService'])->middleware(['auth', AdminMiddleware::class])->name('php.service.toggle');
Route::post('/php/service/restart', [PHPManagerController::class, 'restartService'])->middleware(['auth', AdminMiddleware::class])->name('php.service.restart');

// Databases management [Admin | User] — canonical routes
Route::middleware(['auth'])->group(function () {
    Route::get('/databases', [DatabasesController::class, 'index'])->name('databases.index');
    Route::get('/databases/engine-options', [DatabasesController::class, 'getEngineOptions'])->name('databases.engine-options');
    Route::post('/databases', [DatabasesController::class, 'store'])->name('databases.store');
    Route::patch('/databases', [DatabasesController::class, 'update'])->name('databases.update');
    Route::delete('/databases', [DatabasesController::class, 'destroy'])->name('databases.destroy');
});

// mysql.* back-compat aliases — same handler, NOT redirects (301 on POST/PATCH/DELETE becomes GET)
Route::middleware(['auth'])->group(function () {
    Route::get('/mysql', [DatabasesController::class, 'index'])->name('mysql.index');
    Route::get('/mysql/charsets-collations', [DatabasesController::class, 'getEngineOptions'])->name('mysql.charsets-collations');
    Route::post('/mysql', [DatabasesController::class, 'store'])->name('mysql.store');
    Route::patch('/mysql', [DatabasesController::class, 'update'])->name('mysql.update');
    Route::delete('/mysql', [DatabasesController::class, 'destroy'])->name('mysql.destroy');
});

// Firewall [Admin]
Route::middleware(['auth', AdminMiddleware::class])->group(function () {
    Route::get('/admin/firewall', [FirewallController::class, 'index'])->name('firewall.index');
    Route::post('/admin/firewall/toggle', [FirewallController::class, 'toggle'])->name('firewall.toggle');
    Route::post('/admin/firewall/safe-setup', [FirewallController::class, 'safeSetup'])->name('firewall.safe-setup');
    Route::post('/admin/firewall/rules', [FirewallController::class, 'store'])->name('firewall.store');
    Route::delete('/admin/firewall/rules/{id}', [FirewallController::class, 'destroy'])->name('firewall.destroy');
});

// Filemanager [Admin | User]
Route::get('/filemanager', [FilemanagerController::class, 'index'])->middleware(['auth'])->name('filemanager');
Route::get('/filemanager/get-directory-contents', [FilemanagerController::class, 'getDirectoryContents'])->middleware(['auth'])->name('filemanager.getDirectorContents');
Route::get('/filemanager/get-file-contents', [FilemanagerController::class, 'getFileContents'])->middleware(['auth'])->name('filemanager.getFileContents');
Route::patch('/filemanager/update-file-contents', [FilemanagerController::class, 'updateFileContents'])->middleware(['auth'])->name('filemanager.updateFileContents');
Route::post('/filemanager/create-file', [FilemanagerController::class, 'createFile'])->middleware(['auth'])->name('filemanager.createFile');
Route::patch('/filemanager/rename-file', [FilemanagerController::class, 'renameFile'])->middleware(['auth'])->name('filemanager.renameFile');
Route::patch('/filemanager/paste-files', [FilemanagerController::class, 'pasteFiles'])->middleware(['auth'])->name('filemanager.pasteFiles');
Route::post('/filemanager/delete-files', [FilemanagerController::class, 'deleteFiles'])->middleware(['auth'])->name('filemanager.deleteFiles');
Route::post('/filemanager/upload-file', [FilemanagerController::class, 'uploadFile'])->middleware(['auth'])->name('filemanager.uploadFile');

// Stats History [Admin]
Route::get('/stats/history', [StatsHistoryController::class, 'cpuAndMemory'])->middleware(['auth', AdminMiddleware::class])->name('stats.history');

// Operations audit log [Admin]
Route::get('/admin/operations', [\App\Http\Controllers\OperationsController::class, 'index'])
    ->middleware(['auth', AdminMiddleware::class])->name('operations.index');

// DB Service Control [Admin]
Route::middleware(['auth', AdminMiddleware::class])->group(function () {
    Route::post('/admin/databases/service', [DbServiceController::class, 'action'])
        ->name('databases.service.action');
    Route::get('/admin/databases/service/status', [DbServiceController::class, 'status'])
        ->name('databases.service.status');
});

// Accounts
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Cron Jobs [Admin | User]
Route::middleware(['auth'])->group(function () {
    Route::resource('/cron-jobs', CronJobsController::class)->except(['create', 'edit', 'show']);
    Route::post('/cron-jobs/{cronJob}/toggle', [CronJobsController::class, 'toggleActive'])->name('cron-jobs.toggle');
});

// Backups [Admin | User]
Route::middleware(['auth'])->group(function () {
    Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
    Route::delete('/backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');
    Route::post('/backups/{backup}/restore', [BackupController::class, 'restore'])->name('backups.restore');
    Route::get('/backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
    Route::post('/backups/schedules', [BackupController::class, 'storeSchedule'])->name('backups.schedules.store');
    Route::delete('/backups/schedules/{scheduledBackup}', [BackupController::class, 'destroySchedule'])->name('backups.schedules.destroy');
});

// Notifications [Admin | User]
// NOTE: read-all is registered BEFORE {id}/read so the literal segment is not consumed as {id}.
Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationsController::class, 'markAllRead'])->name('notifications.readAll');
    Route::patch('/notifications/{id}/read', [NotificationsController::class, 'markRead'])->name('notifications.read');
    Route::get('/profile/notifications', [NotificationPreferencesController::class, 'index'])->name('notifications.preferences');
    Route::patch('/profile/notifications/webhook', [NotificationPreferencesController::class, 'updateWebhook'])->name('notifications.preferences.webhook');
    Route::patch('/profile/notifications', [NotificationPreferencesController::class, 'update'])->name('notifications.preferences.update');
});

// Analytics [Admin | User]
Route::get('/analytics', [AnalyticsController::class, 'index'])->middleware(['auth'])->name('analytics.index');

require __DIR__.'/auth.php';
