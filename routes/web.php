<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn() => Inertia::render('Welcome'))->name('welcome');

Route::get('/dashboard', fn() => Inertia::render('Dashboard'))
    ->middleware('auth:admin')
    ->name('admin.dashboard');

Route::middleware(['auth:admin', 'role.admin'])->group(function () {
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::post('/admin/users', [AdminUserController::class, 'store']);
    Route::put('/admin/users/{user}', [AdminUserController::class, 'update']);
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy']);
});

// Dev only — simulate member login (ลบออกก่อน deploy จริง)
if (app()->environment('local')) {
    Route::get('/dev/login-as-member', function () {
        $member = \App\Models\Member::where('email', 'test.member@msu.ac.th')->firstOrFail();
        \Illuminate\Support\Facades\Auth::login($member);
        return redirect()->route('welcome');
    })->name('dev.login-member');
}

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);
Route::post('/logout', [GoogleController::class, 'logout'])->name('logout');
