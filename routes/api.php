<?php

use App\Http\Controllers\Admin\AdminOverviewController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\StatsApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.token')->group(function () {
    Route::get('/overview-service',          [AdminOverviewController::class, 'serviceStats']);
    Route::get('/overview-most',             [AdminOverviewController::class, 'mostStats']);
    Route::get('/getAccess/{roomId}/{code}', [KioskController::class, 'getAccess']);
});

// สถิติการเข้าใช้งานเดือนปัจจุบัน ให้ระบบอื่นดึงไปใช้ — token แยก (LIBROOM_STATS_API_TOKEN)
Route::get('/stats/usage', [StatsApiController::class, 'usage'])->middleware('api.token:stats_api_token');
