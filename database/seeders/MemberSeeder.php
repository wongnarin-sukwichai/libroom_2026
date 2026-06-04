<?php

namespace Database\Seeders;

use App\Models\Member;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    public function run(): void
    {
        //http://127.0.0.1:8000/dev/login-as-member
        Member::firstOrCreate(
            ['email' => 'test.member@msu.ac.th'],
            [
                'google_id' => 'dev-test-google-id-1',
                'name'      => 'Test Member 1',
                'avatar'    => null,
                'code'      => '64010001',
                'type'      => 'student',
                'faculty'   => 'วิทยาการสารสนเทศ',
                'branch'    => 'วิทยาการคอมพิวเตอร์',
            ]
        );

        //http://127.0.0.1:8000/dev/login-as-member2
        Member::firstOrCreate(
            ['email' => 'test.member2@msu.ac.th'],
            [
                'google_id' => 'dev-test-google-id-2',
                'name'      => 'Test Member 2',
                'avatar'    => null,
                'code'      => '64010002',
                'type'      => 'student',
                'faculty'   => 'วิทยาการสารสนเทศ',
                'branch'    => 'เทคโนโลยีสารสนเทศ',
            ]
        );
        //http://127.0.0.1:8000/dev/login-as-member3
        Member::firstOrCreate(
            ['email' => 'test.member3@msu.ac.th'],
            [
                'google_id' => 'dev-test-google-id-3',
                'name'      => 'Test Member 3',
                'avatar'    => null,
                'code'      => '64010003',
                'type'      => 'student',
                'faculty'   => 'วิทยาการสารสนเทศ',
                'branch'    => 'ภูมิสารสนเทศ',
            ]
        );
    }
}
