<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        //Login ด้วย http://127.0.0.1:8000/dev/login-as-admin
        User::updateOrCreate(
            ['email' => 'wongnarin.s@msu.ac.th'],
            [
                'google_id' => 'pending_google_id',
                'name'      => 'วงศ์นรินทร์ สุขวิชัย',
                'avatar'    => null,
                'role'      => 'admin',
            ]
        );
    }
}
