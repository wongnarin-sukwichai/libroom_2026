<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Holiday;
use App\Models\Member;
use App\Models\Room;
use App\Models\ScanLog;
use App\Models\Time;
use App\Support\BookingWindow;
use App\Support\Quota;
use App\Support\ScanCheckin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScanBookController extends Controller
{
    /** GET /s/{code} — สแกน QR ที่ตัว unit */
    public function show(string $code)
    {
        $now  = Carbon::now('Asia/Bangkok');
        $room = Room::with(['zone.location'])->where('scan_code', $code)->first();

        if (! $room) {
            ScanLog::record($code, null, Auth::id(), 'not_found');
            return inertia('ScanBook', ['scanCode' => $code, 'state' => 'not_found']);
        }

        $roomPayload = [
            'id'             => $room->id,
            'title'          => $room->title,
            'zone'           => $room->zone?->title,
            'loc'            => $room->zone?->location?->title,
            'equipment'      => $room->effectiveTools(),
            'confirm_type'   => $room->confirm_type,
            'access_control' => $room->access_control,
        ];

        $closed = $room->status === '1'
            || $room->zone?->status === '1'
            || $room->zone?->location?->status === '1';

        if ($closed) {
            ScanLog::record($code, $room->id, Auth::id(), 'room_closed');
            return inertia('ScanBook', ['scanCode' => $code, 'state' => 'room_closed', 'room' => $roomPayload]);
        }

        if (! Auth::check()) {
            session(['url.intended' => url("/s/{$code}")]);
            ScanLog::record($code, $room->id, null, 'need_login');
            return redirect()->route('auth.google');
        }

        /** @var Member $member */
        $member = Auth::user();

        // 1. เช็คอิน / เช็คอินไปแล้ว
        $checkin = ScanCheckin::attempt($room, $member, $now, force: true);
        if (in_array($checkin, [ScanCheckin::CHECKED_IN, ScanCheckin::ALREADY_CHECKED_IN], true)) {
            ScanLog::record($code, $room->id, $member->id, $checkin);
            return inertia('ScanBook', [
                'scanCode' => $code,
                'state'    => $checkin,
                'room'     => $roomPayload,
                'booking'  => $this->sessionInfo($room, $member, $now),
            ]);
        }

        // 2. ตัวเองมี booking active อยู่แล้วช่วงเวลานี้ที่ห้อง/จุดอื่น (กันจองซ้อน)
        if (Quota::hasTimeConflict($member->id, $now->toDateString(), [$now->hour])) {
            ScanLog::record($code, $room->id, $member->id, 'busy_self');
            return inertia('ScanBook', ['scanCode' => $code, 'state' => 'busy_self', 'room' => $roomPayload]);
        }

        // 3. คนอื่นจองชั่วโมงนี้
        $takenNow = BookingGroup::where('room_id', $room->id)
            ->where('date', $now->toDateString())
            ->where('time_id', $now->hour)
            ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
            ->exists();

        if ($takenNow) {
            ScanLog::record($code, $room->id, $member->id, 'booked_by_other');
            return inertia('ScanBook', ['scanCode' => $code, 'state' => 'busy', 'room' => $roomPayload]);
        }

        // 4. gate: booking window / holiday
        $window = BookingWindow::status();
        if (! $window['is_open_now']) {
            ScanLog::record($code, $room->id, $member->id, 'window_closed');
            return inertia('ScanBook', ['scanCode' => $code, 'state' => 'window_closed', 'room' => $roomPayload, 'window' => $window]);
        }
        if (Holiday::where('d', (string) $now->day)->where('m', (string) $now->month)->exists()) {
            ScanLog::record($code, $room->id, $member->id, 'holiday');
            return inertia('ScanBook', ['scanCode' => $code, 'state' => 'holiday', 'room' => $roomPayload]);
        }

        // 5. ว่าง → หน้าจอง
        $zone      = $room->zone;
        $curHour   = $now->hour;
        $endHour   = $this->zoneEndHour($zone, $now);

        $bookedIds = BookingGroup::where('room_id', $room->id)
            ->where('date', $now->toDateString())
            ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
            ->pluck('time_id')->all();

        $slots = [];
        for ($h = $curHour; $h < $endHour; $h++) {
            if (in_array($h, $bookedIds, true)) {
                break; // ต่อเนื่องเท่านั้น
            }
            $slots[] = ['time_id' => $h, 'label' => sprintf('%02d:00–%02d:00', $h, $h + 1)];
        }

        $zoneQuota   = $zone->zone_daily_quota ?? 3;
        $zoneLeft    = max(0, $zoneQuota - Quota::usedHours($member->id, $now->toDateString(), $zone->id));
        $globalLeft  = max(0, Quota::dailyLimit() - Quota::usedHours($member->id, $now->toDateString()));
        $quotaLeft   = min($zoneLeft, $globalLeft);

        if ($quotaLeft <= 0 || empty($slots)) {
            ScanLog::record($code, $room->id, $member->id, $quotaLeft <= 0 ? 'quota_exceeded' : 'error');
            return inertia('ScanBook', [
                'scanCode'  => $code,
                'state'     => $quotaLeft <= 0 ? 'quota_exceeded' : 'no_slot',
                'room'      => $roomPayload,
                'zoneQuota' => $zoneQuota,
            ]);
        }

        return inertia('ScanBook', [
            'scanCode'  => $code,
            'state'     => 'book',
            'room'      => $roomPayload,
            'curHour'   => $curHour,
            'slots'     => $slots,
            'maxHours'  => min(count($slots), $quotaLeft, 3),
            'quotaLeft' => $quotaLeft,
        ]);
    }

    /** POST /s/{code}/book — จอง (confirmed, source=qr) แล้วเช็คอินให้เลย */
    public function book(Request $request, string $code)
    {
        $hours = (int) $request->input('hours', 1);
        $now   = Carbon::now('Asia/Bangkok');
        $back  = redirect("/s/{$code}");

        $room = Room::with('zone')->where('scan_code', $code)->first();
        if (! $room || ! Auth::check()) {
            return $back;
        }

        /** @var Member $member */
        $member  = Auth::user();
        $date    = $now->toDateString();
        $curHour = $now->hour;
        $zone    = $room->zone;

        if ($room->status === '1' || $zone?->status === '1'
            || ! BookingWindow::isOpenNow()
            || Holiday::where('d', (string) $now->day)->where('m', (string) $now->month)->exists()) {
            return $back;
        }

        $endHour = $this->zoneEndHour($zone, $now);
        $hours   = max(1, min($hours, 3, $endHour - $curHour));
        if ($hours < 1) {
            return $back;
        }
        $timeIds = range($curHour, $curHour + $hours - 1);

        try {
            DB::transaction(function () use ($room, $zone, $timeIds, $member, $date) {
                foreach ($timeIds as $t) {
                    $taken = BookingGroup::where('room_id', $room->id)
                        ->where('date', $date)->where('time_id', $t)
                        ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
                        ->lockForUpdate()->exists();
                    if ($taken) {
                        throw new \RuntimeException('slot_taken');
                    }
                }

                if (Quota::hasTimeConflict($member->id, $date, $timeIds)) {
                    throw new \RuntimeException('time_conflict');
                }

                Quota::assertCanBook($member->id, $date, $zone->id, $zone->zone_daily_quota ?? 3, count($timeIds));

                foreach ($timeIds as $t) {
                    $g = BookingGroup::create([
                        'room_id'          => $room->id,
                        'date'             => $date,
                        'time_id'          => $t,
                        'lead_user_id'     => $member->id,
                        'status'           => 'confirmed',
                        'source'           => 'qr',
                        'join_token'       => Str::random(32),
                        'token_expires_at' => now()->addMinutes(15),
                    ]);
                    Booking::create(['group_id' => $g->id, 'user_id' => $member->id, 'status' => 'confirmed']);
                }
            });
        } catch (\Throwable $e) {
            return $back; // show() จะแสดงเหตุผล (busy / quota)
        }

        ScanCheckin::attempt($room, $member, $now, force: true);
        ScanLog::record($code, $room->id, $member->id, 'booked');

        return $back;
    }

    // ── helpers ───────────────────────────────────────────────

    private function zoneEndHour($zone, Carbon $now): int
    {
        $cfgId = $now->isWeekend() ? ($zone->time_weekend ?? null) : ($zone->time_weekday ?? null);
        return (int) (Time::find($cfgId)?->end_hour ?? 19);
    }

    private function sessionInfo(Room $room, Member $member, Carbon $now): ?array
    {
        $groups = BookingGroup::where('room_id', $room->id)
            ->where('date', $now->toDateString())
            ->where('time_id', '>=', $now->hour)
            ->where('status', 'confirmed')
            ->whereHas('bookings', fn($q) => $q->where('user_id', $member->id)->where('status', 'checked_in'))
            ->orderBy('time_id')->get();

        if ($groups->isEmpty()) {
            return null;
        }

        $start = (int) $groups->min('time_id');
        $end   = (int) $groups->max('time_id') + 1;

        return [
            'label' => sprintf('%02d:00 – %02d:00 น.', $start, $end),
            'hours' => $groups->count(),
        ];
    }
}
