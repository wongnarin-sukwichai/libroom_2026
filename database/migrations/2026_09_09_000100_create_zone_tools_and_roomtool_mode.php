<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ชุดอุปกรณ์มาตรฐานต่อ zone
        Schema::create('zone_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
            $table->unsignedTinyInteger('quantity')->default(1);
            $table->timestamps();
            $table->unique(['zone_id', 'tool_id']);
        });

        // 2. roomtools = ส่วนต่างเฉพาะห้อง
        //    add    = ห้องนี้มีเพิ่ม / override quantity
        //    remove = ห้องนี้ไม่มี (แม้ zone จะมี)
        Schema::table('roomtools', function (Blueprint $table) {
            $table->enum('mode', ['add', 'remove'])->default('add')->after('tool_id');
        });

        // 3. ย้ายข้อมูลเดิม: tool ที่อยู่ในทุกห้องของ zone + quantity เท่ากัน → เป็น zone_tools
        $now = now();
        $zoneIds = DB::table('rooms')->distinct()->pluck('zone_id');

        foreach ($zoneIds as $zoneId) {
            $roomIds = DB::table('rooms')->where('zone_id', $zoneId)->pluck('id');
            if ($roomIds->count() < 1) {
                continue;
            }

            // [tool_id => [quantity => count]]
            $rows = DB::table('roomtools')->whereIn('room_id', $roomIds)->get(['room_id', 'tool_id', 'quantity']);
            $byTool = $rows->groupBy('tool_id');

            foreach ($byTool as $toolId => $toolRows) {
                $roomsWithTool = $toolRows->pluck('room_id')->unique();
                $quantities    = $toolRows->pluck('quantity')->unique();

                // อยู่ครบทุกห้อง + quantity เดียวกัน → promote เป็น zone_tools
                if ($roomsWithTool->count() === $roomIds->count() && $quantities->count() === 1) {
                    DB::table('zone_tools')->updateOrInsert(
                        ['zone_id' => $zoneId, 'tool_id' => $toolId],
                        ['quantity' => (int) $quantities->first(), 'created_at' => $now, 'updated_at' => $now],
                    );
                    // ลบ roomtools ที่ตรงกับ zone set (ไม่ต้องเก็บซ้ำ)
                    DB::table('roomtools')
                        ->whereIn('room_id', $roomIds)
                        ->where('tool_id', $toolId)
                        ->delete();
                }
                // ที่เหลือ (บางห้อง / quantity ต่างกัน) → คงเป็น roomtools mode='add' (default)
            }
        }
    }

    public function down(): void
    {
        Schema::table('roomtools', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
        Schema::dropIfExists('zone_tools');
    }
};
