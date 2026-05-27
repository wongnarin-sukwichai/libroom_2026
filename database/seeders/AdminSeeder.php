<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'wongnarin.s@msu.ac.th'],
            [
                'google_id' => 'pending_google_id',
                'name'      => 'Wongnarin S',
                'avatar'    => null,
            ]
        );
    }
}
