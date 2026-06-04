<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingGroup;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CancelExpiredBookings extends Command
{
    protected $signature   = 'bookings:cancel-expired';
    protected $description = 'ยกเลิกกลุ่มจองที่ token หมดอายุแล้วและยังไม่ครบ min_capacity';

    public function handle(): void
    {
        $expired = BookingGroup::where('status', 'pending')
            ->where('token_expires_at', '<', Carbon::now())
            ->get();

        if ($expired->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($expired) {
            foreach ($expired as $group) {
                // ยกเลิกทุก slot ในเซสชันเดียวกัน
                $siblings = BookingGroup::where('lead_user_id', $group->lead_user_id)
                    ->where('room_id', $group->room_id)
                    ->where('date', $group->date)
                    ->where('status', 'pending')
                    ->get();

                foreach ($siblings as $g) {
                    $g->update(['status' => 'cancelled', 'cancelled_at' => Carbon::now()]);
                    $g->bookings()->update(['status' => 'cancelled']);
                }
            }
        });

        $this->info("ยกเลิก {$expired->count()} กลุ่มที่หมดอายุแล้ว");
    }
}
