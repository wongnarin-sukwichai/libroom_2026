<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * เปลี่ยนโมเดลอุปกรณ์: ห้องไม่ inherit จาก zone อีกต่อไป
 * ต้องกำหนดอุปกรณ์ที่ห้อง "มีจริง" เองผ่าน admin (แท็บ Rooms > อุปกรณ์)
 * zone_tools เหลือหน้าที่เป็น "คลังอุปกรณ์ภายในโซน" ให้เลือกติ๊ก
 *
 * → เคลียร์ roomtools ทั้งหมด ให้ทุกห้องเริ่มจากว่าง
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roomtools')->delete();
    }

    public function down(): void
    {
        // ไม่กู้คืน (ข้อมูลเดิม inherit จาก zone อยู่แล้ว)
    }
};
