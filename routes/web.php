<?php

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

// Filemanager
Route::get('/filemanager', [FilemanagerController::class, 'index'])->middleware(['auth'])->name('filemanager');
Route::get('/filemanager/get-contents', [FilemanagerController::class, 'getContents'])->middleware(['auth'])->name('filemanager.getContents');
Route::post('/filemanager/create-file', [FilemanagerController::class, 'createFile'])->middleware(['auth'])->name('filemanager.createFile');

// Stats History
Route::get('/stats/history', [StatsHistoryController::class, 'cpuAndMemory'])->middleware(['auth', AdminMiddleware::class])->name('stats.history');

// Accounts
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
