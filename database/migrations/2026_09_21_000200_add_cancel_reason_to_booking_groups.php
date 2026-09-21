<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_groups', function (Blueprint $table) {
            // ใครยกเลิก: ชื่อเจ้าหน้าที่ / 'ระบบ' / 'สมาชิก'
            $table->string('cancelled_by', 100)->nullable()->after('cancelled_at');
            $table->string('cancel_reason', 255)->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('booking_groups', function (Blueprint $table) {
            $table->dropColumn(['cancelled_by', 'cancel_reason']);
        });
    }
};
