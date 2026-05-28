<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tool;

class ToolSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['name' => 'โต๊ะ',   'icon' => 'fa-table'],
            ['name' => 'เก้าอี้',   'icon' => 'fa-chair'],
            ['name' => 'TV', 'icon' => 'fa-tv'],
            ['name' => 'Computer', 'icon' => 'fa-computer'],
            ['name' => 'ปลั๊กไฟ', 'icon' => 'fa-plug'],
            ['name' => 'LAN', 'icon' => 'fa-link'],
            ['name' => 'Wi-Fi', 'icon' => 'fa-wifi'],
            ['name' => 'หูฟัง', 'icon' => 'fa-headphones'],
            ['name' => 'เครื่องปรับอากาศ', 'icon' => 'fa-wind'],
            ['name' => 'โปรเจคเตอร์',   'icon' => 'fa-video'],
            ['name' => 'เครื่องเสียง',   'icon' => 'fa-volume-up'],
            ['name' => 'ไมค์, ไมค์ลอย', 'icon' => 'fa-headphones'],    
            ['name' => 'เกมส์', 'icon' => 'fa-gamepad'], 
            ['name' => 'ที่นอน', 'icon' => 'fa-bed'],      
            ['name' => 'โซฟา', 'icon' => 'fa-couch'],       
        ];

        foreach ($data as $r) {
            Tool::updateOrCreate(
                ['name' => $r['name']],
                ['icon' => $r['icon']]
            );
        }
    }
}
