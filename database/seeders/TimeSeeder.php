<?php

namespace Database\Seeders;

use App\Models\Time;
use Illuminate\Database\Seeder;

class TimeSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['title' => 'จันทร์-ศุกร์ (ปกติ)',      'start' => '09:00', 'end' => '19:00'],
            ['title' => 'จันทร์-ศุกร์ (งด OT)',      'start' => '09:00', 'end' => '16:00'],
            ['title' => 'จันทร์-ศุกร์ (OT 4ทุ่ม)',   'start' => '09:00', 'end' => '21:00'],
            ['title' => 'เสาร์-อาทิตย์ (ปกติ)',      'start' => '10:00', 'end' => '17:00'],
            ['title' => 'เสาร์-อาทิตย์ (OT 4ทุ่ม)',  'start' => '10:00', 'end' => '21:00'],
        ];

        foreach ($data as $r) {
            Time::updateOrCreate(
                ['title' => $r['title']],
                ['start' => $r['start'], 'end' => $r['end'], 'owner' => 1]
            );
        }
    }
}
