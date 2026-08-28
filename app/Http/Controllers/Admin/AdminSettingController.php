<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\BookingWindow;
use Illuminate\Http\Request;

class AdminSettingController extends Controller
{
    /** ค่าปัจจุบัน + สถานะหน้าต่างการจอง */
    public function index()
    {
        return response()->json(BookingWindow::status());
    }

    /** บันทึกช่วงเวลาเปิด-ปิดระบบจอง (global) */
    public function update(Request $request)
    {
        $data = $request->validate([
            'booking_window_enabled' => ['required', 'boolean'],
            'booking_open_time'      => ['required', 'date_format:H:i'],
            'booking_close_time'     => ['required', 'date_format:H:i', 'after:booking_open_time'],
        ], [
            'booking_close_time.after' => 'เวลาปิดต้องอยู่หลังเวลาเปิด',
        ]);

        Setting::put('booking_window_enabled', $data['booking_window_enabled'] ? '1' : '0');
        Setting::put('booking_open_time', $data['booking_open_time']);
        Setting::put('booking_close_time', $data['booking_close_time']);

        return response()->json(BookingWindow::status());
    }
}
