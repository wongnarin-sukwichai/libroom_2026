<?php

use App\Http\Controllers\Admin\AdminBookingController;
use App\Http\Controllers\Admin\AdminHolidayController;
use App\Http\Controllers\Admin\AdminMemberController;
use App\Http\Controllers\Admin\AdminOverviewController;
use App\Http\Controllers\Admin\AdminRoomController;
use App\Http\Controllers\Admin\AdminTimeController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\LocationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [LocationController::class, 'index'])->name('welcome');

Route::get('/dashboard', fn() => Inertia::render('Dashboard'))
    ->middleware('auth:admin')
    ->name('admin.dashboard');

Route::middleware(['auth:admin', 'role.admin'])->group(function () {
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::post('/admin/users', [AdminUserController::class, 'store']);
    Route::put('/admin/users/{user}', [AdminUserController::class, 'update']);
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy']);
});

Route::middleware('auth:admin')->group(function () {
    Route::get('/admin/members',            [AdminMemberController::class, 'index']);
    Route::get('/admin/overview-stats',    [AdminOverviewController::class, 'stats']);
    Route::get('/admin/bookings',         [AdminBookingController::class, 'index']);
    Route::post('/admin/bookings/staff',   [AdminBookingController::class, 'staffStore']);
    Route::post('/admin/bookings/approve', [AdminBookingController::class, 'approveSession']);
    Route::post('/admin/bookings/reject',  [AdminBookingController::class, 'rejectSession']);

    Route::get('/admin/holidays',              [AdminHolidayController::class, 'index']);
    Route::post('/admin/holidays',             [AdminHolidayController::class, 'store']);
    Route::delete('/admin/holidays/{holiday}', [AdminHolidayController::class, 'destroy']);

    Route::get('/admin/times',          [AdminTimeController::class, 'index']);
    Route::post('/admin/times',         [AdminTimeController::class, 'store']);
    Route::put('/admin/times/{time}',   [AdminTimeController::class, 'update']);
    Route::delete('/admin/times/{time}',[AdminTimeController::class, 'destroy']);

    Route::get('/admin/rooms',                              [AdminRoomController::class, 'index']);
    Route::post('/admin/locations/{location}/toggle',       [AdminRoomController::class, 'toggleLocation']);
    Route::post('/admin/zones/{zone}/toggle',               [AdminRoomController::class, 'toggleZone']);
    Route::post('/admin/rooms/{room}/toggle',               [AdminRoomController::class, 'toggleRoom']);
    Route::put('/admin/zones/{zone}/settings',              [AdminRoomController::class, 'updateZoneSettings']);

});

// Dev only — simulate member/admin login (ลบออกก่อน deploy จริง)
if (app()->environment('local')) {
    Route::get('/dev/login-as-member', function () {
        $member = \App\Models\Member::where('email', 'test.member@msu.ac.th')->firstOrFail();
        \Illuminate\Support\Facades\Auth::login($member);
        return redirect()->route('welcome');
    })->name('dev.login-member');

    Route::get('/dev/login-as-member2', function () {
        $member = \App\Models\Member::where('email', 'test.member2@msu.ac.th')->firstOrFail();
        \Illuminate\Support\Facades\Auth::login($member);
        return redirect()->route('welcome');
    })->name('dev.login-member2');

    Route::get('/dev/login-as-member3', function () {
        $member = \App\Models\Member::where('email', 'test.member3@msu.ac.th')->firstOrFail();
        \Illuminate\Support\Facades\Auth::login($member);
        return redirect()->route('welcome');
    })->name('dev.login-member3');

    Route::get('/dev/login-as-admin', function () {
        $admin = \App\Models\User::where('email', 'wongnarin.s@msu.ac.th')->firstOrFail();
        \Illuminate\Support\Facades\Auth::guard('admin')->login($admin);
        return redirect()->route('admin.dashboard');
    })->name('dev.login-admin');
}

Route::get('/rooms/{room}/slots', [BookingController::class, 'slots'])->name('booking.slots');
Route::post('/bookings', [BookingController::class, 'store'])->middleware('auth')->name('booking.store');
Route::get('/my-bookings', [BookingController::class, 'myBookings'])->middleware('auth')->name('my-bookings');
Route::post('/booking-groups/{group}/cancel', [BookingController::class, 'cancel'])->middleware('auth')->name('booking.cancel');
Route::get('/join/{token}', [BookingController::class, 'joinPage'])->middleware('auth')->name('booking.join');
Route::post('/join/{token}', [BookingController::class, 'join'])->middleware('auth')->name('booking.join.submit');

Route::get('/api/locations', [LocationController::class, 'forBooking'])->middleware('auth:admin');

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);
Route::post('/logout', [GoogleController::class, 'logout'])->name('logout');
