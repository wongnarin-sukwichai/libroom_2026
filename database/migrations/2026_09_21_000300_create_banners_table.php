<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('image_path', 255);      // เก็บ path ใต้ storage/public เช่น banners/xxxx.jpg
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['0', '1'])->default('0'); // '0' = แสดง, '1' = ปิด (ตามธรรมเนียม status เดิมของระบบ)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
