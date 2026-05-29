<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;
use App\Models\Zone;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        // ดึง zone_id จาก title เพื่อไม่ให้ hardcode id
        $zoneId = fn(string $title) => Zone::where('title', $title)->value('id');

        $data = [

            // ─── อาคารวิทยบริการ A ───────────────────────────────────────

            // ห้องเรียนรู้ A — group, manual (min_capacity=3)
            ['zone' => 'ห้องเรียนรู้ A (ชั้น 4)', 'title' => 'ห้อง A-01', 'detail' => 'ห้องประชุมขนาดเล็ก', 'confirm_type' => 'manual'],
            ['zone' => 'ห้องเรียนรู้ A (ชั้น 4)', 'title' => 'ห้อง A-02', 'detail' => 'ห้องประชุมขนาดเล็ก', 'confirm_type' => 'manual'],
            ['zone' => 'ห้องเรียนรู้ A (ชั้น 4)', 'title' => 'ห้อง A-03', 'detail' => 'ห้องประชุมขนาดเล็ก', 'confirm_type' => 'manual'],
            ['zone' => 'ห้องเรียนรู้ A (ชั้น 4)', 'title' => 'ห้อง A-04', 'detail' => 'ห้องประชุมขนาดเล็ก', 'confirm_type' => 'manual'],
            ['zone' => 'ห้องเรียนรู้ A (ชั้น 4)', 'title' => 'ห้อง A-05', 'detail' => 'ห้องประชุมขนาดเล็ก', 'confirm_type' => 'manual'],
            ['zone' => 'ห้องเรียนรู้ A (ชั้น 4)', 'title' => 'ห้อง A-06', 'detail' => 'ห้องประชุมขนาดเล็ก', 'confirm_type' => 'manual'],
            ['zone' => 'ห้องเรียนรู้ A (ชั้น 4)', 'title' => 'ห้อง A-07', 'detail' => 'ห้องประชุมขนาดเล็ก', 'confirm_type' => 'manual'],
            ['zone' => 'ห้องเรียนรู้ A (ชั้น 4)', 'title' => 'ห้อง A-08', 'detail' => 'ห้องประชุมขนาดเล็ก', 'confirm_type' => 'manual'],

            // ห้องเรียนรู้ B — group + Smart TV, manual (min_capacity=3)
            ['zone' => 'ห้องเรียนรู้ B (ชั้น 4)', 'title' => 'ห้อง B-01', 'detail' => 'ห้องนำเสนองาน มี Smart TV', 'confirm_type' => 'manual'],
            ['zone' => 'ห้องเรียนรู้ B (ชั้น 4)', 'title' => 'ห้อง B-02', 'detail' => 'ห้องนำเสนองาน มี Smart TV', 'confirm_type' => 'manual'],
            ['zone' => 'ห้องเรียนรู้ B (ชั้น 4)', 'title' => 'ห้อง B-03', 'detail' => 'ห้องนำเสนองาน มี Smart TV', 'confirm_type' => 'manual'],
            ['zone' => 'ห้องเรียนรู้ B (ชั้น 4)', 'title' => 'ห้อง B-04', 'detail' => 'ห้องนำเสนองาน มี Smart TV', 'confirm_type' => 'manual'],
            ['zone' => 'ห้องเรียนรู้ B (ชั้น 4)', 'title' => 'ห้อง B-05', 'detail' => 'ห้องนำเสนองาน', 'confirm_type' => 'manual'],
            ['zone' => 'ห้องเรียนรู้ B (ชั้น 4)', 'title' => 'ห้อง B-06', 'detail' => 'ห้องนำเสนองาน', 'confirm_type' => 'manual'],
            ['zone' => 'ห้องเรียนรู้ B (ชั้น 4)', 'title' => 'ห้อง B-07', 'detail' => 'ห้องนำเสนองาน', 'confirm_type' => 'manual'],
            ['zone' => 'ห้องเรียนรู้ B (ชั้น 4)', 'title' => 'ห้อง B-08', 'detail' => 'ห้องนำเสนองาน', 'confirm_type' => 'manual'],


            // ห้องเรียนรู้ C — large, manual (min_capacity=3)
            ['zone' => 'ห้องเรียนรู้ C (ชั้น 4)', 'title' => 'ห้อง C-01', 'detail' => 'ห้องประชุมขนาดใหญ่ มี Smart TV รองรับได้สูงสุด 10 คน', 'confirm_type' => 'manual'],
            ['zone' => 'ห้องเรียนรู้ C (ชั้น 4)', 'title' => 'ห้อง C-02', 'detail' => 'ห้องประชุมขนาดใหญ่', 'confirm_type' => 'manual'],
            ['zone' => 'ห้องเรียนรู้ C (ชั้น 4)', 'title' => 'ห้อง C-03', 'detail' => 'ห้องประชุมขนาดใหญ่ มี Smart TV รองรับได้สูงสุด 10 คน', 'confirm_type' => 'manual'],

            // PAVILION — private pod, auto (min_capacity=1)
            ['zone' => 'PAVILION ROOM (ชั้น 3)', 'title' => 'Pavilion 01', 'detail' => 'ห้องส่วนตัว มีปลั๊กไฟและ LAN', 'confirm_type' => 'auto'],
            ['zone' => 'PAVILION ROOM (ชั้น 3)', 'title' => 'Pavilion 02', 'detail' => 'ห้องส่วนตัว มีปลั๊กไฟและ LAN', 'confirm_type' => 'auto'],
            ['zone' => 'PAVILION ROOM (ชั้น 3)', 'title' => 'Pavilion 03', 'detail' => 'ห้องส่วนตัว มีปลั๊กไฟและ LAN', 'confirm_type' => 'auto'],
            ['zone' => 'PAVILION ROOM (ชั้น 3)', 'title' => 'Pavilion 04', 'detail' => 'ห้องส่วนตัว มีปลั๊กไฟและ LAN', 'confirm_type' => 'auto'],
            ['zone' => 'PAVILION ROOM (ชั้น 3)', 'title' => 'Pavilion 05', 'detail' => 'ห้องส่วนตัว มีปลั๊กไฟและ LAN', 'confirm_type' => 'auto'],
            ['zone' => 'PAVILION ROOM (ชั้น 3)', 'title' => 'Pavilion 06', 'detail' => 'ห้องส่วนตัว มีปลั๊กไฟและ LAN', 'confirm_type' => 'auto'],
            ['zone' => 'PAVILION ROOM (ชั้น 3)', 'title' => 'Pavilion 07', 'detail' => 'ห้องส่วนตัว มีปลั๊กไฟและ LAN', 'confirm_type' => 'auto'],

            // NAP Zone — pod เดี่ยว, auto (min_capacity=1)
            ['zone' => 'NAP Zone (ชั้น 3)', 'title' => 'NAP-01', 'detail' => 'ห้องพักผ่อนส่วนตัว สภาพแวดล้อมเงียบสงบ', 'confirm_type' => 'auto'],
            ['zone' => 'NAP Zone (ชั้น 3)', 'title' => 'NAP-02', 'detail' => 'ห้องพักผ่อนส่วนตัว สภาพแวดล้อมเงียบสงบ', 'confirm_type' => 'auto'],
            ['zone' => 'NAP Zone (ชั้น 3)', 'title' => 'NAP-03', 'detail' => 'ห้องพักผ่อนส่วนตัว สภาพแวดล้อมเงียบสงบ', 'confirm_type' => 'auto'],
            ['zone' => 'NAP Zone (ชั้น 3)', 'title' => 'NAP-04', 'detail' => 'ห้องพักผ่อนส่วนตัว สภาพแวดล้อมเงียบสงบ', 'confirm_type' => 'auto'],
            ['zone' => 'NAP Zone (ชั้น 3)', 'title' => 'NAP-05', 'detail' => 'ห้องพักผ่อนส่วนตัว สภาพแวดล้อมเงียบสงบ', 'confirm_type' => 'auto'],
            ['zone' => 'NAP Zone (ชั้น 3)', 'title' => 'NAP-06', 'detail' => 'ห้องพักผ่อนส่วนตัว สภาพแวดล้อมเงียบสงบ', 'confirm_type' => 'auto'],
            ['zone' => 'NAP Zone (ชั้น 3)', 'title' => 'NAP-07', 'detail' => 'ห้องพักผ่อนส่วนตัว สภาพแวดล้อมเงียบสงบ', 'confirm_type' => 'auto'],
            ['zone' => 'NAP Zone (ชั้น 3)', 'title' => 'NAP-08', 'detail' => 'ห้องพักผ่อนส่วนตัว สภาพแวดล้อมเงียบสงบ', 'confirm_type' => 'auto'],
            ['zone' => 'NAP Zone (ชั้น 3)', 'title' => 'NAP-09', 'detail' => 'ห้องพักผ่อนส่วนตัว สภาพแวดล้อมเงียบสงบ', 'confirm_type' => 'auto'],
            ['zone' => 'NAP Zone (ชั้น 3)', 'title' => 'NAP-10', 'detail' => 'ห้องพักผ่อนส่วนตัว สภาพแวดล้อมเงียบสงบ', 'confirm_type' => 'auto'],
            ['zone' => 'NAP Zone (ชั้น 3)', 'title' => 'NAP-11', 'detail' => 'ห้องพักผ่อนส่วนตัว สภาพแวดล้อมเงียบสงบ', 'confirm_type' => 'auto'],
            ['zone' => 'NAP Zone (ชั้น 3)', 'title' => 'NAP-12', 'detail' => 'ห้องพักผ่อนส่วนตัว สภาพแวดล้อมเงียบสงบ', 'confirm_type' => 'auto'],

            // Computer Zone ชั้น 3 — เครื่องเดี่ยว, auto (min_capacity=1)
            ['zone' => 'Computer Zone (ชั้น 3)', 'title' => 'Computer A-01', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 3)', 'title' => 'Computer A-02', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 3)', 'title' => 'Computer A-03', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 3)', 'title' => 'Computer A-04', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 3)', 'title' => 'Computer A-05', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 3)', 'title' => 'Computer A-06', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 3)', 'title' => 'Computer A-07', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 3)', 'title' => 'Computer A-08', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],

            // Chair Zone — นั่งพักส่วนตัว 69 ที่นั่ง, auto (min_capacity=1)
            ...array_map(fn($i) => [
                'zone'         => 'Chair Zone (ชั้น 3)',
                'title'        => sprintf('Chair-%02d', $i),
                'detail'       => 'เหมาะสำหรับอ่านหนังสือและผ่อนคลาย',
                'confirm_type' => 'auto',
            ], range(1, 69)),

            // PLAYGROUND — พื้นที่ใหญ่, manual (min_capacity=3)
            ['zone' => 'PLAYGROUND (ชั้น 1)', 'title' => 'Playground-01', 'detail' => 'พื้นที่กิจกรรม มีกระจกบานใหญ่ เหมาะสำหรับ Cover Dance', 'confirm_type' => 'manual'],
            ['zone' => 'PLAYGROUND (ชั้น 1)', 'title' => 'Playground-02', 'detail' => 'พื้นที่กิจกรรม มีกระจกบานใหญ่ เหมาะสำหรับ Cover Dance', 'confirm_type' => 'manual'],
            // ─── DLP ─────────────────────────────────────────────────────

            // Study Room DLP — กลุ่ม, manual (min_capacity=2)
            ['zone' => 'Study Room (ชั้น 2)', 'title' => 'Study Room-01', 'detail' => 'ห้องศึกษาพร้อม TV และโซฟา', 'confirm_type' => 'manual'],
            ['zone' => 'Study Room (ชั้น 2)', 'title' => 'Study Room-02', 'detail' => 'ห้องศึกษาพร้อม TV และโซฟา', 'confirm_type' => 'manual'],
            ['zone' => 'Study Room (ชั้น 2)', 'title' => 'Study Room-03', 'detail' => 'ห้องศึกษาพร้อม TV และโซฟา', 'confirm_type' => 'manual'],
            ['zone' => 'Study Room (ชั้น 2)', 'title' => 'Study Room-04', 'detail' => 'ห้องศึกษาพร้อม TV และโซฟา', 'confirm_type' => 'manual'],
            ['zone' => 'Study Room (ชั้น 2)', 'title' => 'Study Room-05', 'detail' => 'ห้องศึกษาพร้อม TV และโซฟา', 'confirm_type' => 'manual'],
            ['zone' => 'Study Room (ชั้น 2)', 'title' => 'Study Room-06', 'detail' => 'ห้องศึกษาพร้อม TV และโซฟา', 'confirm_type' => 'manual'],

            // ทีวีออนไลน์ — กลุ่ม, manual (min_capacity=2)
            ['zone' => 'ทีวีออนไลน์เพื่อการศึกษา (ชั้น 2)', 'title' => 'TV-01', 'detail' => 'ห้องรับชมสื่อการศึกษา มีโซฟานั่งสบาย', 'confirm_type' => 'manual'],
            ['zone' => 'ทีวีออนไลน์เพื่อการศึกษา (ชั้น 2)', 'title' => 'TV-02', 'detail' => 'ห้องรับชมสื่อการศึกษา มีโซฟานั่งสบาย', 'confirm_type' => 'manual'],
            ['zone' => 'ทีวีออนไลน์เพื่อการศึกษา (ชั้น 2)', 'title' => 'TV-03', 'detail' => 'ห้องรับชมสื่อการศึกษา มีโซฟานั่งสบาย', 'confirm_type' => 'manual'],
            ['zone' => 'ทีวีออนไลน์เพื่อการศึกษา (ชั้น 2)', 'title' => 'TV-04', 'detail' => 'ห้องรับชมสื่อการศึกษา มีโซฟานั่งสบาย', 'confirm_type' => 'manual'],
            ['zone' => 'ทีวีออนไลน์เพื่อการศึกษา (ชั้น 2)', 'title' => 'TV-05', 'detail' => 'ห้องรับชมสื่อการศึกษา มีโซฟานั่งสบาย', 'confirm_type' => 'manual'],
            ['zone' => 'ทีวีออนไลน์เพื่อการศึกษา (ชั้น 2)', 'title' => 'TV-06', 'detail' => 'ห้องรับชมสื่อการศึกษา มีโซฟานั่งสบาย', 'confirm_type' => 'manual'],
            ['zone' => 'ทีวีออนไลน์เพื่อการศึกษา (ชั้น 2)', 'title' => 'TV-07', 'detail' => 'ห้องรับชมสื่อการศึกษา มีโซฟานั่งสบาย', 'confirm_type' => 'manual'],
            ['zone' => 'ทีวีออนไลน์เพื่อการศึกษา (ชั้น 2)', 'title' => 'TV-08', 'detail' => 'ห้องรับชมสื่อการศึกษา มีโซฟานั่งสบาย', 'confirm_type' => 'manual'],

            // Computer Zone ชั้น 2 DLP — เครื่องเดี่ยว, auto (min_capacity=1)
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-01', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-02', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-03', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-04', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-05', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-06', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-07', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-08', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-09', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-10', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-11', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-12', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-13', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-14', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-15', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-16', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-17', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-18', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-19', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-20', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-21', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-22', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-23', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-24', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-25', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-26', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-27', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-28', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-29', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],
            ['zone' => 'Computer Zone (ชั้น 2)', 'title' => 'Computer B-30', 'detail' => 'เครื่องคอมพิวเตอร์ส่วนตัว มี LAN', 'confirm_type' => 'auto'],

            // Game Playstation — กลุ่ม, manual (min_capacity=2)
            ['zone' => 'Game Playstation (ชั้น 2)', 'title' => 'PS-01', 'detail' => 'ห้อง PlayStation 5 มีจอยคอนโทรลเลอร์ 2 ตัว', 'confirm_type' => 'manual'],

            // Game Online — เครื่องเดี่ยว, auto (min_capacity=1)
            ['zone' => 'Game Online (ชั้น 2)', 'title' => 'Game Online-ES 2A', 'detail' => 'เครื่องเกมออนไลน์ สเปกสูง มีหูฟัง', 'confirm_type' => 'auto'],
            ['zone' => 'Game Online (ชั้น 2)', 'title' => 'Game Online-ES 2B', 'detail' => 'เครื่องเกมออนไลน์ สเปกสูง มีหูฟัง', 'confirm_type' => 'auto'],
            ['zone' => 'Game Online (ชั้น 2)', 'title' => 'Game Online-ES 2C', 'detail' => 'เครื่องเกมออนไลน์ สเปกสูง มีหูฟัง', 'confirm_type' => 'auto'],
            ['zone' => 'Game Online (ชั้น 2)', 'title' => 'Game Online-ES 3A', 'detail' => 'เครื่องเกมออนไลน์ สเปกสูง มีหูฟัง', 'confirm_type' => 'auto'],
            ['zone' => 'Game Online (ชั้น 2)', 'title' => 'Game Online-ES 3B', 'detail' => 'เครื่องเกมออนไลน์ สเปกสูง มีหูฟัง', 'confirm_type' => 'auto'],
            ['zone' => 'Game Online (ชั้น 2)', 'title' => 'Game Online-ES 3C', 'detail' => 'เครื่องเกมออนไลน์ สเปกสูง มีหูฟัง', 'confirm_type' => 'auto'],

            // Pool Table — กลุ่ม, manual (min_capacity=2)
            ['zone' => 'Pool Table (ชั้น 2)', 'title' => 'โต๊ะพูล 1', 'detail' => 'โต๊ะสนุ๊กมาตรฐาน พร้อมไม้และลูก', 'confirm_type' => 'manual'],

            // Library Space — คาราโอเกะ, manual (min_capacity=2)
            ['zone' => 'Library Space (ชั้น 2)', 'title' => 'ห้องคาราโอเกะ-01', 'detail' => 'ห้องร้องคาราโอเกะ มีระบบเสียงและจอแสดงเนื้อเพลง', 'confirm_type' => 'manual'],
            ['zone' => 'Library Space (ชั้น 2)', 'title' => 'ห้องคาราโอเกะ-02', 'detail' => 'ห้องร้องคาราโอเกะ มีระบบเสียงและจอแสดงเนื้อเพลง', 'confirm_type' => 'manual'],
            ['zone' => 'Library Space (ชั้น 2)', 'title' => 'ห้องคาราโอเกะ-03', 'detail' => 'ห้องร้องคาราโอเกะ มีระบบเสียงและจอแสดงเนื้อเพลง', 'confirm_type' => 'manual'],
            
        ];

        foreach ($data as $r) {
            $zoneIdValue = $zoneId($r['zone']);
            if (!$zoneIdValue) continue;

            Room::updateOrCreate(
                ['zone_id' => $zoneIdValue, 'title' => $r['title']],
                [
                    'detail'       => $r['detail'],
                    'confirm_type' => $r['confirm_type'],
                ]
            );
        }
    }
}
