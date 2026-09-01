<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ยกเลิก booking_groups ที่ token หมดอายุและยังไม่ครบ min_capacity ทุก 1 นาที
Schedule::command('bookings:cancel-expired')->everyMinute();

// refresh faculty/branch จากระบบ patron เดือนละครั้ง (เผื่อย้ายคณะ/เปลี่ยนหลักสูตร)
Schedule::command('members:sync-patron --stale=25')->monthlyOn(1, '03:00');
