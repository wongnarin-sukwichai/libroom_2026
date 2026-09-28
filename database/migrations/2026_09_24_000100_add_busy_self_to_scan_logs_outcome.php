<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * เพิ่ม outcome 'busy_self' — สแกน QR จุดอื่นขณะที่ตัวเองมี booking active อยู่แล้วช่วงเวลานี้ (กันจองซ้อน)
 * ใช้ raw SQL เพราะ doctrine/dbal ไม่ได้ติดตั้ง (จำเป็นสำหรับ Blueprint::enum()->change())
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE scan_logs MODIFY outcome ENUM(
            'not_found', 'room_closed', 'need_login', 'booked_by_other',
            'already_checked_in', 'checked_in', 'booked',
            'window_closed', 'holiday', 'quota_exceeded', 'error',
            'busy_self'
        )");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE scan_logs MODIFY outcome ENUM(
            'not_found', 'room_closed', 'need_login', 'booked_by_other',
            'already_checked_in', 'checked_in', 'booked',
            'window_closed', 'holiday', 'quota_exceeded', 'error'
        )");
    }
};
