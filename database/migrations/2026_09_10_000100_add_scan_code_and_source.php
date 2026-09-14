<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            // prefix สำหรับ generate scan_code ของห้องในโซนนี้ เช่น "3F-CH"
            $table->string('scan_prefix', 40)->nullable()->after('status');
        });

        Schema::table('rooms', function (Blueprint $table) {
            // โค้ดบน QR sticker เช่น "3F-CH-012"
            $table->string('scan_code', 60)->nullable()->unique()->after('access_control');
        });

        Schema::table('booking_groups', function (Blueprint $table) {
            $table->enum('source', ['web', 'qr', 'staff'])->default('web')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('zones', fn(Blueprint $t) => $t->dropColumn('scan_prefix'));
        Schema::table('rooms', function (Blueprint $t) {
            $t->dropUnique(['scan_code']);
            $t->dropColumn('scan_code');
        });
        Schema::table('booking_groups', fn(Blueprint $t) => $t->dropColumn('source'));
    }
};
