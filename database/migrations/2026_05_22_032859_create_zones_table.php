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
        Schema::create('table_zones', function (Blueprint $table) {
            $table->id();
            $table->string('pic')->nullable();
            $table->integer('loc_id');
            $table->string('title');
            $table->string('title_eng')->nullable();
            $table->string('detail')->nullable();
            $table->string('capacity')->nullable();
            $table->string('tool')->nullable();
            $table->tinyInteger('min_capacity')->unsigned()->default(1)->comment('จำนวนคนขั้นต่ำของทุกห้องใน zone นี้');
            $table->tinyInteger('zone_daily_quota')->unsigned()->nullable()->comment('ชม.สูงสุด/วัน/คน ใน zone นี้ (NULL = ใช้ global quota เท่านั้น)');
            $table->integer('time_weekday')->comment('FK → table_times จ-ศ');
            $table->integer('time_weekend')->comment('FK → table_times ส-อ');
            $table->enum('status', ['0', '1'])->default('1')->comment('0=เปิด,1=ปิด');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_zones');
    }
};
