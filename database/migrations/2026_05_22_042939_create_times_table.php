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
            $table->integer('total')->comment('จำนวนเวลาทั้งหมด');
            $table->integer('hour')->comment('เวลาเริ่มต้น');
            $table->string('minute')->comment('text ต่อท้ายเฉยๆ');
            $table->string('start')->comment('** เวลาเริ่มต้น 08.00 **	');
            $table->string('end')->comment('** เวลาสิ้นสุด **');
            $table->integer('owner');
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
