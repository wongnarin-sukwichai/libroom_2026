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
        Schema::create('table_bookings', function (Blueprint $table) {
            $table->id();
            $table->integer('group_id');   // ← FK → table_booking_groups
            $table->integer('user_id');    // ← FK → users (แทน email/name/surname)
            $table->string('status')->comment('pending/confirmed/checked_in/no_show/cancelled');
            $table->timestamp('checkin_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_bookings');
    }
};
