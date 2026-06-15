<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('booking_groups')->where('status', 'completed')->update(['status' => 'confirmed']);

        DB::statement("ALTER TABLE booking_groups MODIFY COLUMN status ENUM('pending','waiting_confirm','confirmed','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE booking_groups MODIFY COLUMN status ENUM('pending','waiting_confirm','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
