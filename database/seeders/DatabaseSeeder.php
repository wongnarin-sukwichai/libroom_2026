<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AdminSeeder::class);
        $this->call(MemberSeeder::class);
        $this->call(HolidaySeeder::class);
        $this->call(TimeSeeder::class);
        $this->call(LocationSeeder::class);
        $this->call(ZoneSeeder::class);
        $this->call(ToolSeeder::class);
        $this->call(RoomSeeder::class);
        $this->call(RoomtoolSeeder::class);
    }
}
