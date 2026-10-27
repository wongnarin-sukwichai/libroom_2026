<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * session_id = ตัวระบุ "รอบการจอง" — ทุก slot ที่จองในครั้งเดียวกันมีค่าเดียวกัน
 * เดิมเดาจาก lead+room+date → จองห้องเดิมวันเดิม 2 รอบแล้วปนกัน (join/cancel/cron ผิดรอบ)
 * ไม่ backfill: แถวเก่า (null) ใช้ fallback lead+room+date ใน BookingGroup::sessionSiblings()
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_groups', function (Blueprint $table) {
            $table->string('session_id', 36)->nullable()->after('time_id')
                ->comment('UUID ร่วมกันทุก slot ในการจองครั้งเดียวกัน (null = ข้อมูลก่อน 2026-10-26)');
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_groups', function (Blueprint $table) {
            $table->dropIndex(['session_id']);
            $table->dropColumn('session_id');
        });
    }
};
