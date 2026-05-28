<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Time;

class TimeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['title' => 'จันทร์-ศุกร์ (ปกติ)',     'total' => 10, 'hour' => 9,  'start' => '09.00 น.', 'end' => '19.00 น.'],
            ['title' => 'จันทร์-ศุกร์ (งด OT)',     'total' => 7,  'hour' => 9,  'start' => '09.00 น.', 'end' => '16.00 น.'],
            ['title' => 'จันทร์-ศุกร์ (OT 4ทุ่ม)',  'total' => 12, 'hour' => 9,  'start' => '09.00 น.', 'end' => '21.00 น.'],
            ['title' => 'เสาร์-อาทิตย์ (ปกติ)',     'total' => 7,  'hour' => 10, 'start' => '10.00 น.', 'end' => '17.00 น.'],
            ['title' => 'เสาร์-อาทิตย์ (OT 4ทุ่ม)', 'total' => 11, 'hour' => 10, 'start' => '10.00 น.', 'end' => '21.00 น.'],
        ];

        foreach ($data as $r) {
            Time::updateOrCreate(
                ['title' => $r['title']],
                [
                    'total'  => $r['total'],
                    'hour'   => $r['hour'],
                    'minute' => '.00 น.',
                    'start'  => $r['start'],
                    'end'    => $r['end'],
                    'owner'  => 1,
                ]
            );
        }
    }
}
