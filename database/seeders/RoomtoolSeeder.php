<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tool;
use App\Models\Zone;
use App\Models\ZoneTool;

/**
 * Seed ชุดอุปกรณ์ "มาตรฐานของ zone" (zone_tools)
 * ห้องแต่ละห้องรับชุดนี้ไปโดยอัตโนมัติ — override เฉพาะห้องทำผ่าน roomtools (admin UI)
 */
class RoomtoolSeeder extends Seeder
{
    public function run(): void
    {
        $toolId = fn(string $name) => Tool::where('name', $name)->value('id');

        // tool set per zone: [tool_name => quantity]
        $zoneSets = [

            // ─── อาคารวิทยบริการ A ────────────────────────────────────────
            'ห้องเรียนรู้ A (ชั้น 4)' => [
                ['name' => 'โต๊ะ', 'qty' => 1], ['name' => 'เก้าอี้', 'qty' => 6],
                ['name' => 'Wi-Fi', 'qty' => 1], ['name' => 'ปลั๊กไฟ', 'qty' => 1],
            ],
            'ห้องเรียนรู้ B (ชั้น 4)' => [
                ['name' => 'TV', 'qty' => 1], ['name' => 'โต๊ะ', 'qty' => 1], ['name' => 'เก้าอี้', 'qty' => 6],
                ['name' => 'Wi-Fi', 'qty' => 1], ['name' => 'ปลั๊กไฟ', 'qty' => 1],
            ],
            'ห้องเรียนรู้ C (ชั้น 4)' => [
                ['name' => 'TV', 'qty' => 1], ['name' => 'โต๊ะ', 'qty' => 2], ['name' => 'เก้าอี้', 'qty' => 10],
                ['name' => 'Wi-Fi', 'qty' => 1], ['name' => 'ปลั๊กไฟ', 'qty' => 1],
            ],
            'PAVILION ROOM (ชั้น 3)' => [
                ['name' => 'โต๊ะ', 'qty' => 1], ['name' => 'เก้าอี้', 'qty' => 2], ['name' => 'Wi-Fi', 'qty' => 1],
                ['name' => 'LAN', 'qty' => 1], ['name' => 'ปลั๊กไฟ', 'qty' => 2],
            ],
            'NAP Zone (ชั้น 3)' => [
                ['name' => 'ที่นอน', 'qty' => 1], ['name' => 'Wi-Fi', 'qty' => 1],
            ],
            'Computer Zone (ชั้น 3)' => [
                ['name' => 'Computer', 'qty' => 1], ['name' => 'โต๊ะ', 'qty' => 1], ['name' => 'เก้าอี้', 'qty' => 1],
                ['name' => 'Wi-Fi', 'qty' => 1], ['name' => 'LAN', 'qty' => 1], ['name' => 'ปลั๊กไฟ', 'qty' => 1],
            ],
            'Chair Zone (ชั้น 3)' => [
                ['name' => 'เก้าอี้', 'qty' => 1], ['name' => 'Wi-Fi', 'qty' => 1],
            ],
            'PLAYGROUND (ชั้น 1)' => [
                ['name' => 'เครื่องปรับอากาศ', 'qty' => 2], ['name' => 'Wi-Fi', 'qty' => 1], ['name' => 'เครื่องเสียง', 'qty' => 1],
            ],

            // ─── DLP ──────────────────────────────────────────────────────
            'Study Room (ชั้น 2)' => [
                ['name' => 'TV', 'qty' => 1], ['name' => 'Wi-Fi', 'qty' => 1], ['name' => 'โซฟา', 'qty' => 1],
            ],
            'ทีวีออนไลน์เพื่อการศึกษา (ชั้น 2)' => [
                ['name' => 'TV', 'qty' => 1], ['name' => 'โซฟา', 'qty' => 1], ['name' => 'Wi-Fi', 'qty' => 1],
            ],
            'Computer Zone (ชั้น 2)' => [
                ['name' => 'Computer', 'qty' => 1], ['name' => 'โต๊ะ', 'qty' => 1], ['name' => 'เก้าอี้', 'qty' => 1],
                ['name' => 'Wi-Fi', 'qty' => 1], ['name' => 'LAN', 'qty' => 1], ['name' => 'ปลั๊กไฟ', 'qty' => 1],
            ],
            'Game Playstation (ชั้น 2)' => [
                ['name' => 'เกมส์', 'qty' => 1], ['name' => 'โซฟา', 'qty' => 1], ['name' => 'Wi-Fi', 'qty' => 1],
            ],
            'Game Online (ชั้น 2)' => [
                ['name' => 'Computer', 'qty' => 1], ['name' => 'โต๊ะ', 'qty' => 1], ['name' => 'เก้าอี้', 'qty' => 1],
                ['name' => 'Wi-Fi', 'qty' => 1], ['name' => 'LAN', 'qty' => 1], ['name' => 'หูฟัง', 'qty' => 1], ['name' => 'ปลั๊กไฟ', 'qty' => 1],
            ],
            'Pool Table (ชั้น 2)' => [
                ['name' => 'โต๊ะ', 'qty' => 1],
            ],
            'Library Space (ชั้น 2)' => [
                ['name' => 'Computer', 'qty' => 1], ['name' => 'เก้าอี้', 'qty' => 4], ['name' => 'Wi-Fi', 'qty' => 1],
                ['name' => 'เครื่องเสียง', 'qty' => 1], ['name' => 'ไมค์, ไมค์ลอย', 'qty' => 2],
            ],
            'Meeting MSU Space (ชั้น 2)' => [
                ['name' => 'โต๊ะ', 'qty' => 2], ['name' => 'เก้าอี้', 'qty' => 10], ['name' => 'Wi-Fi', 'qty' => 1],
                ['name' => 'เครื่องปรับอากาศ', 'qty' => 2], ['name' => 'ปลั๊กไฟ', 'qty' => 1],
            ],
        ];

        foreach ($zoneSets as $zoneTitle => $set) {
            $zoneId = Zone::where('title', $zoneTitle)->value('id');
            if (! $zoneId) {
                continue;
            }
            foreach ($set as $t) {
                $tid = $toolId($t['name']);
                if (! $tid) {
                    continue;
                }
                ZoneTool::updateOrCreate(
                    ['zone_id' => $zoneId, 'tool_id' => $tid],
                    ['quantity' => $t['qty']],
                );
            }
        }
    }
}
