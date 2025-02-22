<?php

use App\Http\Controllers\AccountsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FilemanagerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StatsHistoryController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect('/dashboard');
});

// Dashboards [Admin | User]
Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');
Route::get('/dashboard/admin', [DashboardController::class, 'admin'])->middleware(['auth', AdminMiddleware::class])->name('dashboard.admin');
Route::get('/dashboard/admin/get/top-sort', [DashboardController::class, 'getTopSort'])->middleware(['auth', AdminMiddleware::class])->name('dashboard.admin.getTopSort');
Route::patch('/dashboard/admin/set/top-sort', [DashboardController::class, 'setTopSort'])->middleware(['auth', AdminMiddleware::class])->name('dashboard.admin.setTopSort');
Route::get('/dashboard/user', [DashboardController::class, 'user'])->middleware(['auth'])->name('dashboard.user');


// Accounts [Admin]
Route::resource('/accounts', AccountsController::class)->middleware(['auth', AdminMiddleware::class])->except(['create', 'edit', 'show']);
Route::get('/accounts/impersonate/{user}', [AccountsController::class, 'impersonate'])->middleware(['auth', AdminMiddleware::class])->name('accounts.impersonate');
Route::get('/accounts/leave-impersonation', [AccountsController::class, 'leaveImpersonation'])->middleware(['auth'])->name('accounts.leaveImpersonation');

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

// Accounts
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
