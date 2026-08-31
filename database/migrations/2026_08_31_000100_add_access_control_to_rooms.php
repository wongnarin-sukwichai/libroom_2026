<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->enum('access_control', ['0', '1'])
                ->default('0')
                ->after('confirm_type')
                ->comment('0=ไม่มี kiosk (เจ้าหน้าที่เช็คอิน), 1=มี kiosk (สแกนเช็คอิน+อนุมัติเอง)');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('access_control');
        });
    }
};
