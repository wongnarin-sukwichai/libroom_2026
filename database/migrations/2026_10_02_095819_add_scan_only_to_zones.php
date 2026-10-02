<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            // '1' = ห้ามจองผ่านเว็บทั้งโซน ต้องสแกน QR ที่ตัวอุปกรณ์เท่านั้น (เช่น โซนเก้าอี้)
            $table->string('scan_only', 1)->default('0')->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn('scan_only');
        });
    }
};
