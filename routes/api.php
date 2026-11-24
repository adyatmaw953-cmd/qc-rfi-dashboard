<?php

use App\Http\Controllers\Api\MdrApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->middleware([
    'auth:sanctum',
    'ability:mdr:read',
    'throttle:60,1',
])->group(function () {
    Route::get('/mdrs', [MdrApiController::class, 'index'])->name('api.v1.mdrs.index');
    Route::get('/mdrs/{mdr}', [MdrApiController::class, 'show'])->name('api.v1.mdrs.show');
});
