<?php

namespace Database\Seeders;

use App\Models\Member;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    public function run(): void
    {
        //Login ด้วย http://127.0.0.1:8000/dev/login-as-member
        Member::firstOrCreate(
            ['email' => 'test.member@msu.ac.th'],
            [
                'google_id' => 'dev-test-google-id',
                'name'      => 'Test Member',
                'avatar'    => null,
                'code'      => '64010001',
                'type'      => 'student',
                'faculty'   => 'วิทยาการสารสนเทศ',
                'branch'    => 'วิทยาการคอมพิวเตอร์',
            ]
        );
    }
}
