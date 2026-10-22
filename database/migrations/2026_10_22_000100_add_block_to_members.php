<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ระงับสิทธิ์การจองของสมาชิก (admin กด block พร้อมข้อความกำกับ)
 * + เพิ่ม outcome 'blocked' ใน scan_logs (raw SQL เพราะไม่มี doctrine/dbal)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('patron_synced_at')
                ->comment('null = ปกติ, มีค่า = ถูกระงับการจอง');
            $table->text('block_reason')->nullable()->after('blocked_at');
            $table->unsignedBigInteger('blocked_by')->nullable()->after('block_reason')
                ->comment('users.id ของ admin ที่กด block');
        });

        DB::statement("ALTER TABLE scan_logs MODIFY outcome ENUM(
            'not_found', 'room_closed', 'need_login', 'booked_by_other',
            'already_checked_in', 'checked_in', 'booked',
            'window_closed', 'holiday', 'quota_exceeded', 'error',
            'busy_self', 'blocked'
        )");
    }

    public function down(): void
    {
        DB::statement("UPDATE scan_logs SET outcome = 'error' WHERE outcome = 'blocked'");
        DB::statement("ALTER TABLE scan_logs MODIFY outcome ENUM(
            'not_found', 'room_closed', 'need_login', 'booked_by_other',
            'already_checked_in', 'checked_in', 'booked',
            'window_closed', 'holiday', 'quota_exceeded', 'error',
            'busy_self'
        )");

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['blocked_at', 'block_reason', 'blocked_by']);
        });
    }
};
