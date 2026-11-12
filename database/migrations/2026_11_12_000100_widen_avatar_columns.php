<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * avatar VARCHAR(255) → TEXT (members + users)
 * ลิงก์รูปโปรไฟล์ Google บางบัญชียาว >1,000 ตัวอักษร (แบบ .../a-/ALV-Uj...) → insert ล้ม "Data too long" → login 500
 * raw SQL เพราะไม่มี doctrine/dbal (เหมือน migration อื่นในโปรเจกต์)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE members MODIFY avatar TEXT NULL');
        DB::statement('ALTER TABLE users MODIFY avatar TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE members MODIFY avatar VARCHAR(255) NULL');
        DB::statement('ALTER TABLE users MODIFY avatar VARCHAR(255) NULL');
    }
};
