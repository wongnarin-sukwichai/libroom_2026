<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('times', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('start', 5)->comment('เวลาเริ่มต้น HH:MM เช่น 09:00');
            $table->string('end', 5)->comment('เวลาสิ้นสุด HH:MM เช่น 19:00');
            $table->integer('owner')->comment('admin user id ที่สร้าง');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('times');
    }
};
