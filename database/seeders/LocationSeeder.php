<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Location;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'title'     => 'อาคารวิทยบริการ A',
                'title_eng' => 'Academic Resources Building A',
                'detail'    => 'พื้นที่ให้บริการทรัพยากรสารสนเทศ พื้นที่นั่งอ่านหนังสือ ห้องเรียนรู้กลุ่ม/เดี่ยว เน้นความเงียบสงบ เพื่อการทบทวนตำรา',
                'status'    => '1',
            ],
            [
                'title'     => 'อาคารวิทยบริการ B',
                'title_eng' => 'Academic Resources Building B',
                'detail'    => 'พื้นที่สร้างสรรค์สุดผ่อนคลาย ห้องฉายภาพยนต์ ทีวีออนไลน์ Netflix E-Sport Zone',
                'status'    => '1',
            ],
            [
                'title'     => 'MSU SPACE',
                'title_eng' => 'MSU SPACE',
                'detail'    => 'ห้องประชุมส่วนตัวรองรับกลุ่มใหญ่ที่ต้องการสัมมนาหรืองานนำเสนอระดับคณะ/หน่วยงาน',
                'status'    => '1',
            ],
        ];

        foreach ($data as $r) {
            Location::updateOrCreate(
                ['title' => $r['title']],
                [
                    'title_eng' => $r['title_eng'],
                    'detail'    => $r['detail'],
                    'status'    => $r['status'],
                ]
            );
        }
    }
}
