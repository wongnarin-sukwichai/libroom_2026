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
        Schema::create('table_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('pic')->nullable();
            $table->integer('zone_id');
            $table->string('title');
            $table->string('detail')->nullable();
            $table->integer('min_capacity')->default(1)->comment('1, 3, 5');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_rooms');
    }
};
