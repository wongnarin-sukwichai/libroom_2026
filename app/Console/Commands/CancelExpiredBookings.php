<?php

namespace App\Console\Commands;

use App\Mail\BookingCancelledUnconfirmed;
use App\Models\Booking;
use App\Models\BookingGroup;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CancelExpiredBookings extends Command
{
    protected $signature   = 'bookings:cancel-expired';
    protected $description = 'ยกเลิกกลุ่มจองที่ (1) token หมดอายุ (2) manual+ไม่มี kiosk ไม่ยืนยันใน 15 นาที (3) มี kiosk ไม่สแกนใน 15 นาที (4) mark no_show';

    public function handle(): void
    {
        $this->cancelExpiredPending();
        $this->cancelUnconfirmedManual();
        $this->cancelUnscannedKiosk();
        $this->markNoShow();
    }

    // --- pending ที่ token หมดอายุ (logic เดิม) ---
    private function cancelExpiredPending(): void
    {
        $expired = BookingGroup::where('status', 'pending')
            ->where('token_expires_at', '<', Carbon::now())
            ->get();

        if ($expired->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($expired) {
            foreach ($expired as $group) {
                $siblings = BookingGroup::where('lead_user_id', $group->lead_user_id)
                    ->where('room_id', $group->room_id)
                    ->where('date', $group->date)
                    ->where('status', 'pending')
                    ->get();

                foreach ($siblings as $g) {
                    $g->update([
                        'status'        => 'cancelled',
                        'cancelled_at'  => Carbon::now(),
                        'cancelled_by'  => 'ระบบ',
                        'cancel_reason' => 'หมดเวลารอสมาชิกครบตามจำนวนขั้นต่ำ',
                    ]);
                    $g->bookings()->update(['status' => 'cancelled']);
                }
            }
        });

        $this->info("ยกเลิก {$expired->count()} กลุ่มที่หมดอายุแล้ว");
    }

    // --- waiting_confirm ที่เลย slot_start + 15 นาที โดยไม่มี staff confirm ---
    private function cancelUnconfirmedManual(): void
    {
        // time_id คือเลขชั่วโมง เช่น 9, 10, 11
        // slot_start = date + time_id:00:00
        // ยกเลิกถ้า now >= slot_start + 15 นาที
        $overdue = BookingGroup::with(['room', 'lead'])
            ->where('status', 'waiting_confirm')
            ->whereHas('room', fn($q) => $q->where('confirm_type', 'manual'))
            ->whereRaw('TIMESTAMP(date, MAKETIME(time_id, 0, 0)) + INTERVAL 15 MINUTE <= NOW()')
            ->get();

        if ($overdue->isEmpty()) {
            return;
        }

        // จัดกลุ่มเป็น session (lead + room + date) เพื่อส่งอีเมลแค่ครั้งเดียวต่อ session
        $sessions = $overdue->groupBy(
            fn($g) => $g->lead_user_id . '|' . $g->room_id . '|' . $g->date->toDateString()
        );

        $cancelledCount = 0;

        DB::transaction(function () use ($sessions, &$cancelledCount) {
            foreach ($sessions as $sessionGroups) {
                $first = $sessionGroups->first();

                // ยกเลิกทุก slot ใน session เดียวกัน
                $siblings = BookingGroup::where('status', 'waiting_confirm')
                    ->where('lead_user_id', $first->lead_user_id)
                    ->where('room_id', $first->room_id)
                    ->where('date', $first->date)
                    ->get();

                foreach ($siblings as $g) {
                    $g->update([
                        'status'        => 'cancelled',
                        'cancelled_at'  => Carbon::now(),
                        'cancelled_by'  => 'ระบบ',
                        'cancel_reason' => 'เจ้าหน้าที่ไม่ยืนยันภายใน 15 นาทีหลังเวลาเริ่มใช้งาน',
                    ]);
                    $g->bookings()->update(['status' => 'cancelled']);
                    $cancelledCount++;
                }

                // ส่งอีเมลแจ้ง leader 1 ครั้งต่อ session
                if ($first->lead?->email) {
                    Mail::to($first->lead->email)->send(new BookingCancelledUnconfirmed($first));
                }
            }
        });

        $this->info("ยกเลิก {$cancelledCount} กลุ่ม (manual ไม่ได้ยืนยันใน 15 นาที) — แจ้ง {$sessions->count()} leader");
    }

    // --- confirmed + ห้องมี kiosk แต่เลย slot_start + 15 นาที ไม่มีใครสแกนเช็คอินเลยสักคน ---
    // ครอบทั้ง manual+kiosk (ครบสมาชิกแล้วข้าม waiting_confirm มา confirmed ทันที) และ auto+kiosk (ถ้ามีในอนาคต)
    // ไม่แตะ staff booking (source=staff, ไม่มี bookings แถวไหนเลย ไม่ใช่กรณีที่ต้องการยกเลิก)
    private function cancelUnscannedKiosk(): void
    {
        $overdue = BookingGroup::with(['room', 'lead'])
            ->where('status', 'confirmed')
            ->where('source', '!=', 'staff')
            ->whereHas('room', fn($q) => $q->where('access_control', '1'))
            ->whereRaw('TIMESTAMP(date, MAKETIME(time_id, 0, 0)) + INTERVAL 15 MINUTE <= NOW()')
            ->whereDoesntHave('bookings', fn($q) => $q->where('status', 'checked_in'))
            ->get();

        if ($overdue->isEmpty()) {
            return;
        }

        // จัดกลุ่มเป็น session (lead + room + date) — ยกเลิกทั้ง session เดียวกันพร้อมกัน
        $sessions = $overdue->groupBy(
            fn($g) => $g->lead_user_id . '|' . $g->room_id . '|' . $g->date->toDateString()
        );

        $cancelledCount = 0;

        DB::transaction(function () use ($sessions, &$cancelledCount) {
            foreach ($sessions as $sessionGroups) {
                $first = $sessionGroups->first();

                $siblings = BookingGroup::where('status', 'confirmed')
                    ->where('lead_user_id', $first->lead_user_id)
                    ->where('room_id', $first->room_id)
                    ->where('date', $first->date)
                    ->whereDoesntHave('bookings', fn($q) => $q->where('status', 'checked_in'))
                    ->get();

                foreach ($siblings as $g) {
                    $g->update([
                        'status'        => 'cancelled',
                        'cancelled_at'  => Carbon::now(),
                        'cancelled_by'  => 'ระบบ',
                        'cancel_reason' => 'ไม่สแกน QR ยืนยันภายใน 15 นาทีหลังเวลาเริ่มใช้งาน',
                    ]);
                    $g->bookings()->update(['status' => 'cancelled']);
                    $cancelledCount++;
                }
            }
        });

        $this->info("ยกเลิก {$cancelledCount} กลุ่ม (มี kiosk แต่ไม่สแกนใน 15 นาที)");
    }

    // --- slot จบแล้ว แต่ bookings ยัง confirmed (ไม่เคยเช็คอิน) → no_show ---
    private function markNoShow(): void
    {
        // slot จบเมื่อ: date + (time_id + 1):00:00 <= NOW()
        // ครอบทุกห้อง (auto/manual, มี/ไม่มี access control) — เช็คอินไม่ว่าจะผ่าน kiosk หรือปุ่มเจ้าหน้าที่
        $ended = BookingGroup::where('status', 'confirmed')
            ->whereRaw('TIMESTAMP(date, MAKETIME(time_id + 1, 0, 0)) <= NOW()')
            ->get();

        if ($ended->isEmpty()) {
            return;
        }

        $count = 0;
        foreach ($ended as $group) {
            $updated = $group->bookings()
                ->where('status', 'confirmed')
                ->update(['status' => 'no_show']);
            $count += $updated;
        }

        if ($count > 0) {
            $this->info("Mark no_show {$count} bookings (slot จบแล้วแต่ไม่เช็คอิน)");
        }
    }
}
