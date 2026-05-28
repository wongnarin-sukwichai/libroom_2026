<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Holiday;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        Holiday::updateOrCreate(
            [
                'd' => '1',
                'm'      => '1',
                'detail'    => 'วันหยุดปีใหม่',
                'owner'      => '1',
            ]
        );
    }
}
