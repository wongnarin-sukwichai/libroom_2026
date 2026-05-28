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
        Schema::create('table_booking_groups', function (Blueprint $table) {
            $table->id();
            $table->integer('room_id');
            $table->date('date');
            $table->integer('time_id');
            $table->integer('lead_user_id');
            $table->enum('status', ['pending', 'waiting_confirm', 'confirmed', 'completed', 'cancelled'])->default('pending')->comment('pending=รอสมาชิก, waiting_confirm=ครบแต่รอเจ้าหน้าที่, confirmed=ยืนยัน, completed=เสร็จ, cancelled=ยกเลิก');
            $table->string('join_token')->unique();
            $table->timestamp('token_expires_at');
            $table->string('cancel_code')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_booking_groups');
    }
};
