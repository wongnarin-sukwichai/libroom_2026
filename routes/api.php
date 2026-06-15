<?php

use App\Http\Controllers\Admin\AdminOverviewController;
use App\Http\Controllers\KioskController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.token')->group(function () {
    Route::get('/overview-service',          [AdminOverviewController::class, 'serviceStats']);
    Route::get('/overview-most',             [AdminOverviewController::class, 'mostStats']);
    Route::get('/getAccess/{roomId}/{code}', [KioskController::class, 'getAccess']);
});
