<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MdrChecklistController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\NcrController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\QcFindingController;
use App\Http\Controllers\RfiController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'role:admin|production|qc_qa|warehouse|ppic'])->group(function () {
    Route::get('/dashboard', [ModuleController::class, 'dashboard'])->name('dashboard');

    Route::get('/profile/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/profile/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleActive'])->name('users.toggle-status');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/rfi', [RfiController::class, 'index'])
        ->middleware('module:rfi')
        ->name('rfi.index');
    Route::get('/rfi/{kategori}', [RfiController::class, 'category'])
        ->where('kategori', '[A-F]')
        ->middleware('module:rfi')
        ->name('rfi.category');
    Route::get('/rfi/{kategori}/create', [RfiController::class, 'create'])
        ->where('kategori', '[A-F]')
        ->middleware('module:rfi')
        ->name('rfi.create');
    Route::post('/rfi/{kategori}', [RfiController::class, 'store'])
        ->where('kategori', '[A-F]')
        ->middleware('module:rfi')
        ->name('rfi.store');
    Route::get('/rfi/{kategori}/{rfi}/edit', [RfiController::class, 'edit'])
        ->where('kategori', '[A-F]')
        ->middleware('module:rfi')
        ->name('rfi.edit');
    Route::get('/rfi/{kategori}/{rfi}', [RfiController::class, 'show'])
        ->where('kategori', '[A-F]')
        ->middleware('module:rfi')
        ->name('rfi.show');
    Route::put('/rfi/{kategori}/{rfi}', [RfiController::class, 'update'])
        ->where('kategori', '[A-F]')
        ->middleware('module:rfi')
        ->name('rfi.update');
    Route::delete('/rfi/{kategori}/{rfi}', [RfiController::class, 'destroy'])
        ->where('kategori', '[A-F]')
        ->middleware('module:rfi')
        ->name('rfi.destroy');
    Route::post('/rfi/{kategori}/{rfi}/review', [RfiController::class, 'review'])
        ->where('kategori', '[A-F]')
        ->middleware('module:rfi')
        ->name('rfi.review');
    Route::post('/rfi/{kategori}/{rfi}/finding', [RfiController::class, 'makeFinding'])
        ->where('kategori', '[A-F]')
        ->middleware('module:rfi')
        ->name('rfi.finding');
    Route::get('/qc-finding', [QcFindingController::class, 'index'])
        ->middleware('module:qc_finding')
        ->name('qc-finding.index');
    Route::get('/qc-finding/project/{project}', [QcFindingController::class, 'project'])
        ->middleware('module:qc_finding')
        ->name('qc-finding.project');
    Route::post('/qc-finding/{finding}/incoming-action', [QcFindingController::class, 'actionIncomingMaterial'])
        ->middleware('module:qc_finding')
        ->name('qc-finding.incoming-action');
    Route::post('/qc-finding/incoming/{rfi}', [QcFindingController::class, 'storeIncomingMaterial'])
        ->middleware('module:qc_finding')
        ->name('qc-finding.incoming.store');
    Route::post('/qc-finding/{finding}/submissions', [QcFindingController::class, 'storeSubmission'])
        ->middleware('module:qc_finding')
        ->name('qc-finding.submissions.store');
    Route::post('/qc-finding/{finding}/verify', [QcFindingController::class, 'verifySubmission'])
        ->middleware('module:qc_finding')
        ->name('qc-finding.verify');
    Route::get('/qc-finding/evidence/{evidence}', [QcFindingController::class, 'downloadEvidence'])
        ->middleware('module:qc_finding')
        ->name('qc-finding.evidence.download');
    Route::post('/qc-finding/{finding}/action-plan', [QcFindingController::class, 'updateActionPlan'])
        ->middleware('module:qc_finding')
        ->name('qc-finding.action-plan.update');
    Route::post('/qc-finding/{finding}/due-date', [QcFindingController::class, 'updateDueDate'])
        ->middleware('module:qc_finding')
        ->name('qc-finding.due-date.update');
    Route::get('/ncr', [NcrController::class, 'index'])
        ->middleware('module:ncr')
        ->name('ncr.index');
    Route::get('/ncr/create', [NcrController::class, 'create'])
        ->middleware('module:ncr')
        ->name('ncr.create');
    Route::post('/ncr', [NcrController::class, 'store'])
        ->middleware('module:ncr')
        ->name('ncr.store');
    Route::get('/ncr/{ncr}', [NcrController::class, 'show'])
        ->middleware('module:ncr')
        ->name('ncr.show');
    Route::get('/ncr/{ncr}/edit', [NcrController::class, 'edit'])
        ->middleware('module:ncr')
        ->name('ncr.edit');
    Route::put('/ncr/{ncr}', [NcrController::class, 'update'])
        ->middleware('module:ncr')
        ->name('ncr.update');
    Route::delete('/ncr/{ncr}', [NcrController::class, 'destroy'])
        ->middleware('module:ncr')
        ->name('ncr.destroy');
    Route::get('/mdr-checklist', [MdrChecklistController::class, 'index'])
        ->middleware('module:mdr')
        ->name('mdr-checklist.index');
    Route::get('/mdr-checklist/create', [MdrChecklistController::class, 'create'])
        ->middleware('module:mdr')
        ->name('mdr-checklist.create');
    Route::post('/mdr-checklist', [MdrChecklistController::class, 'store'])
        ->middleware('module:mdr')
        ->name('mdr-checklist.store');
    Route::get('/mdr-checklist/{mdr}', [MdrChecklistController::class, 'show'])
        ->middleware('module:mdr')
        ->name('mdr-checklist.show');
    Route::put('/mdr-checklist/{mdr}', [MdrChecklistController::class, 'update'])
        ->middleware('module:mdr')
        ->name('mdr-checklist.update');
    Route::delete('/mdr-checklist/{mdr}', [MdrChecklistController::class, 'destroy'])
        ->middleware('module:mdr')
        ->name('mdr-checklist.destroy');
});
