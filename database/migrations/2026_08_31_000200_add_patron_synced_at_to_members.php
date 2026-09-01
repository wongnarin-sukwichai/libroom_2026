<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->timestamp('patron_synced_at')->nullable()->after('branch')
                ->comment('ครั้งล่าสุดที่ลองดึง faculty/branch จากระบบ patron (แม้ไม่พบก็อัปเดต)');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('patron_synced_at');
        });
    }
};
