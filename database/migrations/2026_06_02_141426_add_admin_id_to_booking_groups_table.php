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
        Schema::table('booking_groups', function (Blueprint $table) {
            $table->integer('lead_user_id')->nullable()->change();
            $table->integer('admin_id')->nullable()->after('lead_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_groups', function (Blueprint $table) {
            $table->integer('lead_user_id')->nullable(false)->change();
            $table->dropColumn('admin_id');
        });
    }
};
