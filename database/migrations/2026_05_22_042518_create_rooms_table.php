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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('pic')->nullable();
            $table->integer('zone_id');
            $table->string('title');
            $table->string('detail')->nullable();
            $table->enum('confirm_type', ['auto', 'manual'])->default('auto')->comment('auto=confirmed ทันที (single only), manual=รอเจ้าหน้าที่');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
