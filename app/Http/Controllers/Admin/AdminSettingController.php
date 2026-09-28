<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\BookingWindow;
use App\Support\Quota;
use Illuminate\Http\Request;

class AdminSettingController extends Controller
{
    /** ค่าปัจจุบัน + สถานะหน้าต่างการจอง + โควตารวมทุกโซนต่อวัน */
    public function index()
    {
        return response()->json([
            ...BookingWindow::status(),
            'member_daily_quota_hours' => Quota::dailyLimit(),
        ]);
    }

    /** บันทึกช่วงเวลาเปิด-ปิดระบบจอง (global) + โควตารวมทุกโซนต่อคนต่อวัน */
    public function update(Request $request)
    {
        $data = $request->validate([
            'booking_window_enabled'   => ['required', 'boolean'],
            'booking_open_time'        => ['required', 'date_format:H:i'],
            'booking_close_time'       => ['required', 'date_format:H:i', 'after:booking_open_time'],
            'member_daily_quota_hours' => ['required', 'integer', 'min:1', 'max:24'],
        ], [
            'booking_close_time.after' => 'เวลาปิดต้องอยู่หลังเวลาเปิด',
        ]);

        Setting::put('booking_window_enabled', $data['booking_window_enabled'] ? '1' : '0');
        Setting::put('booking_open_time', $data['booking_open_time']);
        Setting::put('booking_close_time', $data['booking_close_time']);
        Setting::put('member_daily_quota_hours', $data['member_daily_quota_hours']);

        return response()->json([
            ...BookingWindow::status(),
            'member_daily_quota_hours' => Quota::dailyLimit(),
        ]);
    }
}
