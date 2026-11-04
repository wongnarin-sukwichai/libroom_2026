<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingGroup;
use App\Models\Holiday;
use App\Models\Room;
use App\Models\Time;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminBookingController extends Controller
{
    public function index(Request $request)
    {
        // มีวันที่ = กรองวันนั้น, ไม่มี = ทุกวัน (ใช้กับ tab ยืนยันแล้ว/ยกเลิก เพื่อดูย้อนหลัง)
        $date = $request->filled('date') ? $request->date : null;

        // แยกตาม tab: รอดำเนินการ / จองล่วงหน้า / ยืนยันแล้ว / ยกเลิก
        $statusMap = [
            'pending'   => ['pending', 'waiting_confirm'],
            'upcoming'  => ['confirmed'],
            'confirmed' => ['confirmed'],
            'cancelled' => ['cancelled'],
            'checkin'   => ['confirmed'],
        ];
        $tab      = $request->get('tab', 'pending');
        $statuses = $statusMap[$tab] ?? $statusMap['pending'];

        // จองล่วงหน้า = เจ้าหน้าที่จองไว้ (admin_id != null) และวันที่ยังมาไม่ถึง (date > วันนี้)
        $isUpcoming    = $tab === 'upcoming';
        // แท็บยกเลิก: วันที่ = "วันที่กดยกเลิก" (ไม่ใช่วันที่ของการจอง) — ยกเลิกการจองล่วงหน้าวันนี้ก็เห็นวันนี้
        $isCancelled   = $tab === 'cancelled';
        $cancelledAt   = 'COALESCE(cancelled_at, updated_at)';
        $upcomingAfter = $isUpcoming ? Carbon::now('Asia/Bangkok')->toDateString() : null;
        // แท็บแบ่งตาม "ขั้นตอนถัดไป":
        //   รอดำเนินการ        = ต้องติดต่อเจ้าหน้าที่ (pending / waiting_confirm)
        //   รอเช็คอินหน้าประตู = ยืนยันแล้ว แต่ยังไม่มีใครเช็คอิน → แตะบัตร/สแกน QR หรือเจ้าหน้าที่กดเช็คอินแทน (ทุกห้อง)
        //   ยืนยันแล้ว          = กระบวนการเสร็จ: มีคนเช็คอินแล้ว หรือเป็นการจองของเจ้าหน้าที่ (ไม่มีรายชื่อนิสิต)
        // แท็บรอเช็คอิน ไม่ส่งวันที่ = วันนี้
        $isCheckin = $tab === 'checkin';
        if ($isCheckin && ! $date) {
            $date = Carbon::now('Asia/Bangkok')->toDateString();
        }

        $search = trim((string) $request->get('search', ''));

        $query = BookingGroup::with([
            'room'               => fn($q) => $q->select('id', 'zone_id', 'title', 'confirm_type', 'access_control'),
            'room.zone'          => fn($q) => $q->select('id', 'loc_id', 'title', 'min_capacity'),
            'room.zone.location' => fn($q) => $q->select('id', 'title'),
            'lead'               => fn($q) => $q->select('id', 'name', 'email', 'type'),
            'admin'              => fn($q) => $q->select('id', 'name', 'email'),
            'bookings'           => fn($q) => $q->select('id', 'group_id', 'user_id', 'status'),
            'bookings.member'    => fn($q) => $q->select('id', 'name', 'email'),
        ])
        ->when($date && ! $isCancelled, fn($q) => $q->where('date', $date))
        ->when($date && $isCancelled, fn($q) => $q->whereRaw("DATE({$cancelledAt}) = ?", [$date]))
        ->when($isUpcoming, fn($q) => $q
            ->whereNotNull('admin_id')
            ->where('date', '>', $upcomingAfter))
        ->whereIn('status', $statuses)
        ->when($isCheckin, fn($q) => $q
            ->whereNotNull('lead_user_id')
            ->whereDoesntHave('bookings', fn($b) => $b->where('status', 'checked_in')))
        ->when($tab === 'confirmed', fn($q) => $q->where(fn($w) => $w
            ->whereNull('lead_user_id')
            ->orWhereHas('bookings', fn($b) => $b->where('status', 'checked_in'))))
        ->when($search !== '', function ($q) use ($search) {
            $q->where(function ($sub) use ($search) {
                $like = "%{$search}%";
                $sub->whereHas('lead',  fn($l) => $l->where('name', 'like', $like)->orWhere('email', 'like', $like))
                    ->orWhereHas('admin', fn($a) => $a->where('name', 'like', $like)->orWhere('email', 'like', $like))
                    ->orWhereHas('room',  fn($r) => $r->where('title', 'like', $like));
            });
        })
        // "รอเจ้าหน้าที่อนุมัติ" ขึ้นบนสุดเสมอ (งานที่ต้องกด) → แล้วค่อย "รอสมาชิกครบ"
        ->orderByRaw("FIELD(status, 'waiting_confirm', 'pending', 'confirmed', 'cancelled')")
        // ยกเลิกล่าสุดขึ้นก่อน (slot ในรอบเดียวกันถูกยกเลิกพร้อมกัน → เวลาเท่ากัน จึงยังติดกันให้ groupIntoSessions รวมได้)
        ->when($isCancelled, fn($q) => $q->orderByRaw("{$cancelledAt} DESC"))
        ->orderBy('date')
        ->orderBy('lead_user_id')
        ->orderBy('room_id')
        ->orderBy('time_id');

        $all      = $query->get();
        $sessions = $this->groupIntoSessions($all);

        // รอเช็คอินหน้าประตู: แสดงเฉพาะรอบที่ยังแตะบัตรทัน (ยังไม่เลย HH:15 ของชั่วโมงแรก) — เลยแล้ว cron ยกเลิกเอง ไม่ต้องให้เจ้าหน้าที่เห็น
        // เรียงตามเวลาเริ่ม (ใกล้เส้นตายก่อน) — ทำหลังจัดกลุ่ม ไม่งั้นรอบหลายชั่วโมงจะแตก
        if ($isCheckin) {
            $now      = Carbon::now('Asia/Bangkok');
            $sessions = array_values(array_filter($sessions, fn($s) => ! $this->checkinWindowOver(
                $s['date'], $s['start_hour'], $s['end_hour'], ($s['access_control'] ?? '0') === '1', $now
            )));
            usort($sessions, fn($a, $b) => [$a['date'], $a['start_hour']] <=> [$b['date'], $b['start_hour']]);
        }

        // Manual pagination
        $perPage  = 10;
        $page     = max(1, (int)$request->get('page', 1));
        $total    = count($sessions);
        $items    = array_slice($sessions, ($page - 1) * $perPage, $perPage);

        $pendingCount = $this->staffActionCount();

        return response()->json([
            'data'          => array_values($items),
            'current_page'  => $page,
            'last_page'     => max(1, (int)ceil($total / $perPage)),
            'total'         => $total,
            'from'          => $total ? ($page - 1) * $perPage + 1 : 0,
            'to'            => min($page * $perPage, $total),
            'pending_count' => $pendingCount,
            'checkin_count' => $this->doorCheckinCount(),
        ]);
    }

    // GET /admin/bookings/pending-count — badge เมนู (Dashboard poll ทุก 30 วิ)
    public function pendingCount()
    {
        return response()->json(['count' => $this->staffActionCount()]);
    }

    /**
     * badge คิวงาน = จำนวน "รอบการจอง" ที่รอเจ้าหน้าที่ (waiting_confirm) — งานที่ staff ต้องลงมือจริง
     * ไม่นับ pending (รอสมาชิกครบ) เพราะเจ้าหน้าที่ทำอะไรไม่ได้ / นับเป็นรอบ ไม่ใช่รายชั่วโมง
     */
    /**
     * รอบนี้หมดช่วงรอเช็คอินแล้วหรือยัง (หมดแล้ว = ไม่แสดงในแท็บรอเช็คอิน)
     * ห้องมี kiosk: เลย HH:15 ของชั่วโมงแรก → cron ยกเลิกทั้งรอบ
     * ห้องไม่มี kiosk (เช่น ห้อง auto): ไม่มี cron ตัด → แสดงจนหมดเวลาที่จอง (แล้ว cron บันทึก no_show)
     */
    private function checkinWindowOver(string $date, int $startHour, int $endHour, bool $isKiosk, Carbon $now): bool
    {
        $day = Carbon::parse($date, 'Asia/Bangkok');
        return ($isKiosk ? $day->copy()->setTime($startHour, 15) : $day->copy()->setTime($endHour, 0))->lte($now);
    }

    /** จำนวนรอบ "รอเช็คอินหน้าประตู" วันนี้ที่ยังเช็คอินทัน — ตัวเลขบนแท็บ */
    private function doorCheckinCount(): int
    {
        $now = Carbon::now('Asia/Bangkok');

        return BookingGroup::with('room:id,access_control')
            ->where('date', $now->toDateString())
            ->where('status', 'confirmed')
            ->whereNotNull('lead_user_id')
            ->whereDoesntHave('bookings', fn($b) => $b->where('status', 'checked_in'))
            ->get(['id', 'session_id', 'lead_user_id', 'room_id', 'date', 'time_id'])
            ->groupBy(fn($g) => $g->sessionKey())
            ->reject(fn($slots) => $this->checkinWindowOver(
                $now->toDateString(), (int) $slots->min('time_id'), (int) $slots->max('time_id') + 1,
                $slots->first()->room?->access_control === '1', $now
            ))
            ->count();
    }

    private function staffActionCount(): int
    {
        return BookingGroup::where('status', 'waiting_confirm')
            ->get(['id', 'session_id', 'lead_user_id', 'room_id', 'date'])
            ->unique(fn($g) => $g->sessionKey())
            ->count();
    }

    private function groupIntoSessions($groups): array
    {
        $sessions = [];
        $current  = null;

        foreach ($groups as $g) {
            $sameSession = $current
                && $current['lead_user_id'] === $g->lead_user_id
                && $current['room_id']      === $g->room_id
                && $current['status']       === $g->status
                && $current['date']         === $g->date->format('Y-m-d')
                && $current['session_id']   === $g->session_id
                && $g->time_id === $current['last_time'] + 1;

            if ($sameSession) {
                $current['ids'][]     = $g->id;
                $current['last_time'] = $g->time_id;
                $current['end_hour']  = $g->time_id + 1;
                $current['hours']++;
                $current['bk_statuses'] = array_merge($current['bk_statuses'], $g->bookings->pluck('status')->all());
            } else {
                if ($current) $sessions[] = $this->formatSession($current);
                $current = [
                    'session_id'   => $g->session_id,
                    'lead_user_id' => $g->lead_user_id,
                    'room_id'      => $g->room_id,
                    'status'       => $g->status,
                    'source'       => $g->source,
                    'last_time'    => $g->time_id,
                    'ids'          => [$g->id],
                    'date'         => $g->date->format('Y-m-d'),
                    'start_hour'   => $g->time_id,
                    'end_hour'     => $g->time_id + 1,
                    'hours'        => 1,
                    'bk_statuses'   => $g->bookings->pluck('status')->all(),
                    'confirm_type'  => $g->room?->confirm_type,
                    'access_control' => $g->room?->access_control,
                    'room_title'   => $g->room?->title,
                    'zone_title'   => $g->room?->zone?->title,
                    'loc_title'    => $g->room?->zone?->location?->title,
                    'member_name'  => $g->lead?->name  ?? ($g->admin ? $g->admin->name  . ' (เจ้าหน้าที่)' : null),
                    'member_email' => $g->lead?->email ?? $g->admin?->email,
                    'cancelled_by'  => $g->cancelled_by,
                    'cancel_reason' => $g->cancel_reason,
                    'cancelled_at'  => ($g->cancelled_at ?? $g->updated_at)?->format('Y-m-d H:i'),
                    'min_capacity'  => (int) ($g->room?->zone?->min_capacity ?? 1),
                    'members'       => $this->sessionMembers($g),
                    'walkin'        => $g->isWalkin(),
                    'admin_name'    => $g->admin?->name,
                    'expires_at'    => $g->token_expires_at?->toIso8601String(),
                ];
            }
        }
        if ($current) $sessions[] = $this->formatSession($current);

        return $sessions;
    }

    /**
     * รายชื่อผู้เข้าร่วมของรอบ (ใช้ slot แรกของรอบ — ทุก slot มีสมาชิกชุดเดียวกัน) หัวหน้ากลุ่มขึ้นก่อน
     * รอบที่ยกเลิกแล้ว: แสดงทุกคน (ดูย้อนหลังว่าใครอยู่ในกลุ่ม) / รอบอื่น: ไม่นับคนที่ถอน (cancelled)
     */
    private function sessionMembers(BookingGroup $g): array
    {
        return $g->bookings
            ->when($g->status !== 'cancelled', fn($c) => $c->reject(fn($b) => $b->status === 'cancelled'))
            ->sortByDesc(fn($b) => $b->user_id === $g->lead_user_id)
            ->map(fn($b) => [
                'name'    => $b->member?->name ?? '—',
                'email'   => $b->member?->email,
                'is_lead' => $b->user_id === $g->lead_user_id,
                'status'  => $b->status,
            ])->values()->all();
    }

    private function formatSession(array $s): array
    {
        $pad = fn($h) => sprintf('%02d:00', $h);

        $bk = collect($s['bk_statuses'] ?? [])->reject(fn($st) => $st === 'cancelled');
        // เจ้าหน้าที่จองแทน (source=staff) ไม่มีแถว bookings ให้เช็คอินจริง — ถือว่า "เช็คอินแล้ว" ไปเลย
        // เพราะเจ้าหน้าที่ยืนยันล่วงหน้าอยู่แล้วตอนกดจอง ไม่ต้องมีขั้นเช็คอินแยกอีก
        $checkedIn = $s['source'] === 'staff'
            ? true
            : ($bk->isNotEmpty() && $bk->every(fn($st) => $st === 'checked_in'));

        return [
            'ids'            => $s['ids'],
            'date'           => $s['date'],
            'time_label'     => $pad($s['start_hour']) . ' – ' . $pad($s['end_hour']) . ' น.',
            'start_hour'     => $s['start_hour'],
            'end_hour'       => $s['end_hour'],
            'hours'          => $s['hours'],
            'members'        => $s['members'] ?? [],
            'member_count'   => count($s['members'] ?? []),
            'min_capacity'   => $s['min_capacity'] ?? 1,
            'walkin'         => $s['walkin'] ?? false,
            'admin_name'     => $s['admin_name'] ?? null,
            'expires_at'     => $s['expires_at'] ?? null,
            'status'         => $s['status'],
            'source'         => $s['source'] ?? 'web',
            'confirm_type'   => $s['confirm_type'],
            'access_control' => $s['access_control'] ?? '0',
            'checked_in'     => $checkedIn,
            'room_title'     => $s['room_title'],
            'zone_title'     => $s['zone_title'],
            'loc_title'      => $s['loc_title'],
            'member_name'    => $s['member_name'],
            'member_email'   => $s['member_email'],
            'cancelled_by'   => $s['cancelled_by']  ?? null,
            'cancel_reason'  => $s['cancel_reason'] ?? null,
            'cancelled_at'   => $s['status'] === 'cancelled' ? ($s['cancelled_at'] ?? null) : null,
        ];
    }

    // ─── ผังห้อง (board view) ─────────────────────────────────────────────
    /** ตารางเวลาของห้องเดียว + รายชื่อผู้จองในแต่ละช่อง */
    public function roomDay(Request $request)
    {
        $data = $request->validate([
            'room_id' => 'required|integer|exists:rooms,id',
            'date'    => 'required|date',
        ]);

        $room = Room::with([
            'zone'          => fn($q) => $q->select('id', 'loc_id', 'title', 'time_weekday', 'time_weekend', 'status', 'min_capacity', 'capacity'),
            'zone.location' => fn($q) => $q->select('id', 'title', 'status'),
        ])->findOrFail($data['room_id']);

        $isWeekend = Carbon::parse($data['date'])->isWeekend();
        $config    = Time::findOrFail($isWeekend ? $room->zone->time_weekend : $room->zone->time_weekday);

        // โครงช่องเวลา
        $slots = [];
        for ($h = $config->start_hour; $h < $config->end_hour; $h++) {
            $slots[$h] = [
                'hour'   => $h,
                'label'  => sprintf('%02d:00 – %02d:00 น.', $h, $h + 1),
                'groups' => [],
            ];
        }

        $groups = BookingGroup::with([
                'lead'            => fn($q) => $q->select('id', 'name', 'email'),
                'admin'           => fn($q) => $q->select('id', 'name'),
                'bookings'        => fn($q) => $q->select('id', 'group_id', 'user_id', 'status'),
                'bookings.member' => fn($q) => $q->select('id', 'name'),
            ])
            ->where('room_id', $room->id)
            ->where('date', $data['date'])
            ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
            ->orderBy('lead_user_id')
            ->orderBy('admin_id')
            ->orderBy('status')
            ->orderBy('time_id')
            ->get();

        // จับ session: lead/admin + status เดียวกัน + time_id ต่อเนื่อง
        $sessions = [];
        $cur      = null;
        $keyOf    = fn($g) => ($g->lead_user_id ?? 'a' . $g->admin_id) . '|' . $g->status . '|' . $g->session_id;

        foreach ($groups as $g) {
            if ($cur && $cur['key'] === $keyOf($g) && $g->time_id === $cur['last'] + 1) {
                $cur['ids'][]  = $g->id;
                $cur['last']   = $g->time_id;
                $cur['end']    = $g->time_id + 1;
                $cur['hours']++;
            } else {
                if ($cur) $sessions[] = $cur;
                $occ = $g->bookings->reject(fn($b) => $b->status === 'cancelled')->map(fn($b) => [
                    'name'   => $b->member?->name ?? '—',
                    'status' => $b->status,
                ])->values()->all();

                $cur = [
                    'key'        => $keyOf($g),
                    'ids'        => [$g->id],
                    'last'       => $g->time_id,
                    'start'      => $g->time_id,
                    'end'        => $g->time_id + 1,
                    'hours'      => 1,
                    'status'     => $g->status,
                    'source'     => $g->source,
                    'lead_name'  => $g->lead?->name ?? ($g->admin ? $g->admin->name . ' (เจ้าหน้าที่)' : '—'),
                    'lead_email' => $g->lead?->email,
                    'by_staff'   => $g->lead_user_id === null,
                    // จองแทนนิสิต = เจ้าหน้าที่สร้าง แต่มีรายชื่อนิสิต (ต่างจากจองล่วงหน้าที่ไม่มีรายชื่อ)
                    'walkin'     => $g->isWalkin(),
                    'expires_at' => $g->token_expires_at?->toIso8601String(),
                    'admin_name' => $g->admin?->name,
                    'created_at' => $g->created_at?->toIso8601String(),
                    'occupants'  => $occ,
                ];
            }
        }
        if ($cur) $sessions[] = $cur;

        foreach ($sessions as $s) {
            $bk         = collect($s['occupants']);
            // เจ้าหน้าที่จองแทน (source=staff) ไม่มี occupants ให้เช็คอินจริง — ถือว่าเช็คอินแล้วไปเลย (เหตุผลเดียวกับ formatSession ด้านบน)
            $checkedIn  = $s['source'] === 'staff'
                ? true
                : ($bk->isNotEmpty() && $bk->every(fn($o) => $o['status'] === 'checked_in'));
            $payload    = [
                'ids'        => $s['ids'],
                'status'     => $s['status'],
                'source'     => $s['source'] ?? 'web',
                'start_hour' => $s['start'],
                'end_hour'   => $s['end'],
                'hours'      => $s['hours'],
                'time_label' => sprintf('%02d:00 – %02d:00 น.', $s['start'], $s['end']),
                'lead_name'  => $s['lead_name'],
                'lead_email' => $s['lead_email'],
                'by_staff'   => $s['by_staff'],
                'walkin'     => $s['walkin'],
                'admin_name' => $s['admin_name'],
                'created_at' => $s['created_at'],
                'expires_at' => $s['expires_at'],
                'occupants'  => $s['occupants'],
                'checked_in' => $checkedIn,
            ];
            for ($h = $s['start']; $h < $s['end']; $h++) {
                if (!isset($slots[$h])) continue;
                $slots[$h]['groups'][] = $payload + ['is_continuation' => $h !== $s['start']];
            }
        }

        // ในแต่ละช่องเวลา: "รอเจ้าหน้าที่อนุมัติ" ขึ้นก่อนเสมอ (เหมือนหน้ารายการ)
        $rank = ['waiting_confirm' => 0, 'pending' => 1, 'confirmed' => 2];
        foreach ($slots as &$slot) {
            usort($slot['groups'], fn($a, $b) => ($rank[$a['status']] ?? 9) <=> ($rank[$b['status']] ?? 9));
        }
        unset($slot);

        return response()->json([
            'room' => [
                'id'             => $room->id,
                'title'          => $room->title,
                'zone_title'     => $room->zone?->title,
                'loc_title'      => $room->zone?->location?->title,
                'confirm_type'   => $room->confirm_type,
                'access_control' => $room->access_control,
                // สำหรับ modal จองแทนนิสิต
                'is_open'        => $room->status !== '1' && $room->zone?->status !== '1' && $room->zone?->location?->status !== '1',
                'min_capacity'   => (int) ($room->zone?->min_capacity ?? 1),
                'max_capacity'   => $this->zoneMaxCapacity($room->zone),
            ],
            'date'       => $data['date'],
            'is_weekend' => $isWeekend,
            'slots'      => array_values($slots),
        ]);
    }

    // ─── จองแทนนิสิต (walk-in) — เฉพาะชั่วโมงปัจจุบัน, ผังห้อง ───────────────
    // นิสิตมาเคาน์เตอร์ตอนห้องว่างกลางชั่วโมง → เจ้าหน้าที่กรอกรายชื่อ → "รอนิสิตยืนยัน" 10 นาที
    //   หัวหน้ากลุ่มกดยืนยันเอง (สแกน QR บนจอเจ้าหน้าที่ หรือหน้าการจองของฉัน) → confirmed + checked_in ทุกคน
    //   ปฏิเสธ → ยกเลิกทันที / ไม่กดใน 10 นาที → cron cancelExpiredPending (ตัวเดิม) ยกเลิกเอง — ไม่แก้ cron
    // แยกจาก "จองล่วงหน้า" (staffStore) ด้วย lead_user_id != null (source=staff + มีรายชื่อนิสิต)
    // join_token = token ยืนยัน (/walkin/{token}) — /join/{token} ปฏิเสธ walk-in (BookingController)
    public const WALKIN_CONFIRM_MINUTES = 10;

    /** ตรวจสมาชิก 1 คนว่าจองแทนชั่วโมงนี้ในโซนนี้ได้ไหม — null = ผ่าน, string = เหตุผลที่ไม่ผ่าน */
    private function walkinProblem(\App\Models\Member $m, $zone, string $date, int $hour): ?string
    {
        if ($m->isBlocked()) {
            return 'ถูกระงับสิทธิ์การจอง' . ($m->block_reason ? " ({$m->block_reason})" : '');
        }
        if (\App\Support\Quota::hasTimeConflict($m->id, $date, [$hour])) {
            return 'มีการจองที่อื่นในชั่วโมงนี้แล้ว';
        }
        $zoneQuota = (int) ($zone->zone_daily_quota ?? 3);
        if (\App\Support\Quota::usedHours($m->id, $date, $zone->id) + 1 > $zoneQuota) {
            return "ใช้โควตาโซนนี้ครบแล้ว ({$zoneQuota} ชม./วัน)";
        }
        $daily = \App\Support\Quota::dailyLimit();
        if (\App\Support\Quota::usedHours($m->id, $date) + 1 > $daily) {
            return "ใช้โควตารวมวันนี้ครบแล้ว ({$daily} ชม./วัน)";
        }
        return null;
    }

    /** ความจุสูงสุดจาก zones.capacity (ข้อความ เช่น "2-6 คน" → 6, "5 คนขึ้นไป"/"1 คน" → ไม่จำกัด/1) */
    private function zoneMaxCapacity($zone): ?int
    {
        $txt = (string) ($zone->capacity ?? '');
        if ($txt === '' || str_contains($txt, 'ขึ้นไป')) return null;
        preg_match_all('/\d+/', $txt, $m);
        return $m[0] ? (int) end($m[0]) : null;
    }

    /** ห้องเปิด + ไม่ใช่วันหยุด + ชั่วโมงปัจจุบันอยู่ในเวลาให้บริการของโซน — null = ผ่าน */
    private function walkinRoomProblem(Room $room, Carbon $now): ?string
    {
        $zone = $room->zone;
        if ($room->status === '1' || $zone?->status === '1' || $zone?->location?->status === '1') {
            return 'ห้องนี้ปิดให้บริการอยู่';
        }
        if (Holiday::where('d', (string) $now->day)->where('m', (string) $now->month)->exists()) {
            return 'วันนี้เป็นวันหยุด';
        }
        $config = Time::find($now->isWeekend() ? $zone->time_weekend : $zone->time_weekday);
        if (! $config || $now->hour < $config->start_hour || $now->hour >= $config->end_hour) {
            return 'ชั่วโมงนี้อยู่นอกเวลาให้บริการของห้อง';
        }
        return null;
    }

    // GET /admin/bookings/walkin-lookup?room_id=&q= — ค้นหาสมาชิกด้วยอีเมล (≥3 ตัว) + ผลตรวจรายคน
    public function walkinLookup(Request $request)
    {
        $data = $request->validate([
            'room_id' => 'required|integer|exists:rooms,id',
            'q'       => 'required|string|min:3|max:100',
        ]);

        $now  = Carbon::now('Asia/Bangkok');
        $room = Room::with('zone')->findOrFail($data['room_id']);

        return response()->json(
            \App\Models\Member::where('email', 'like', '%' . $data['q'] . '%')
                ->orderBy('email')
                ->limit(5)
                ->get()
                ->map(fn($m) => [
                    'id'      => $m->id,
                    'name'    => $m->name,
                    'email'   => $m->email,
                    'problem' => $this->walkinProblem($m, $room->zone, $now->toDateString(), $now->hour),
                ])
        );
    }

    // POST /admin/bookings/walkin {room_id, member_ids[]} — คนแรก = หัวหน้ากลุ่ม
    public function walkinStore(Request $request)
    {
        $data = $request->validate([
            'room_id'      => 'required|integer|exists:rooms,id',
            'member_ids'   => 'required|array|min:1',
            'member_ids.*' => 'integer|distinct|exists:members,id',
        ]);

        $now   = Carbon::now('Asia/Bangkok');
        $date  = $now->toDateString();
        $hour  = $now->hour;
        $room  = Room::with('zone.location')->findOrFail($data['room_id']);
        $zone  = $room->zone;
        $admin = Auth::guard('admin')->user();

        if ($p = $this->walkinRoomProblem($room, $now)) {
            return response()->json(['message' => $p], 422);
        }

        $min = (int) ($zone->min_capacity ?? 1);
        $max = $this->zoneMaxCapacity($zone);
        $n   = count($data['member_ids']);
        if ($n < $min) {
            return response()->json(['message' => "ห้องนี้ต้องมีผู้ใช้อย่างน้อย {$min} คน (กรอก {$n} คน)"], 422);
        }
        if ($max && $n > $max) {
            return response()->json(['message' => "ห้องนี้รับได้สูงสุด {$max} คน (กรอก {$n} คน)"], 422);
        }

        // เรียงตามลำดับที่ส่งมา (คนแรก = หัวหน้ากลุ่ม)
        $members = \App\Models\Member::whereIn('id', $data['member_ids'])->get()->sortBy(
            fn($m) => array_search($m->id, $data['member_ids'])
        )->values();

        try {
            $group = DB::transaction(function () use ($room, $date, $hour, $members, $zone, $admin) {
                $taken = BookingGroup::where('room_id', $room->id)->where('date', $date)->where('time_id', $hour)
                    ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
                    ->lockForUpdate()->exists();
                if ($taken) throw new \RuntimeException('ช่องเวลานี้มีผู้จองแล้ว');

                foreach ($members as $m) {
                    if ($p = $this->walkinProblem($m, $zone, $date, $hour)) {
                        throw new \RuntimeException("{$m->name}: {$p}");
                    }
                }

                // pending + token หมดอายุใน 10 นาที → ระหว่างรอ ห้อง+โควตาถูกกันไว้ (Quota นับ pending)
                $group = BookingGroup::create([
                    'room_id'          => $room->id,
                    'date'             => $date,
                    'time_id'          => $hour,
                    'session_id'       => (string) Str::uuid(),
                    'lead_user_id'     => $members->first()->id,
                    'admin_id'         => $admin?->id,
                    'status'           => 'pending',
                    'source'           => 'staff',
                    'join_token'       => Str::random(40),
                    'token_expires_at' => Carbon::now()->addMinutes(self::WALKIN_CONFIRM_MINUTES),
                ]);

                foreach ($members as $m) {
                    \App\Models\Booking::create(['group_id' => $group->id, 'user_id' => $m->id, 'status' => 'pending']);
                }

                return $group;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $url = route('walkin.show', $group->join_token);

        return response()->json([
            'message'    => sprintf('สร้างการจองแทนแล้ว %02d:00 – %02d:00 น. — รอ %s ยืนยันภายใน %d นาที',
                $hour, $hour + 1, $members->first()->name, self::WALKIN_CONFIRM_MINUTES),
            'lead_name'  => $members->first()->name,
            'url'        => $url,
            'qr_svg'     => (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->margin(1)->generate($url),
            'expires_at' => $group->token_expires_at->toIso8601String(),
        ]);
    }

    /** จำนวนคำขอรอ / จองแล้ว ต่อห้อง สำหรับวันหนึ่ง (จุดสีบนปุ่มห้อง) */
    public function boardSummary(Request $request)
    {
        $date = $request->validate(['date' => 'required|date'])['date'];

        // waiting = รอเจ้าหน้าที่อนุมัติ (ส้ม) / pending = รอสมาชิกครบ (เทา) — แยกกันให้สีตรงกับหน้ารายการ
        $rows = BookingGroup::selectRaw(
                "room_id,
                 SUM(status = 'waiting_confirm') as waiting,
                 SUM(status = 'pending') as pending,
                 SUM(status = 'confirmed') as booked"
            )
            ->where('date', $date)
            ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
            ->groupBy('room_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->room_id] = ['waiting' => (int) $r->waiting, 'pending' => (int) $r->pending, 'booked' => (int) $r->booked];
        }

        return response()->json($out);
    }

    /** ยกเลิกการจองที่ยืนยันแล้ว (pending/waiting ใช้ reject) */
    public function cancelSession(Request $request)
    {
        $data = $request->validate([
            'ids'    => 'required|array',
            'ids.*'  => 'integer',
            'reason' => 'nullable|string|max:255',
        ]);

        $groups   = BookingGroup::whereIn('id', $data['ids'])->where('status', 'confirmed')->get();
        $admin    = Auth::guard('admin')->user();
        $byLabel  = $admin ? "เจ้าหน้าที่ ({$admin->name})" : 'เจ้าหน้าที่';

        foreach ($groups as $g) {
            $g->update([
                'status'        => 'cancelled',
                'cancelled_at'  => Carbon::now(),
                'cancelled_by'  => $byLabel,
                'cancel_reason' => $data['reason'] ?? null,
            ]);
            $g->bookings()->update(['status' => 'cancelled']);
        }

        return response()->json(['message' => 'ยกเลิกเรียบร้อย', 'count' => $groups->count()]);
    }

    /**
     * เลิกใช้ก่อนเวลา (admin/staff เท่านั้น) — รอบที่ "เริ่มใช้แล้ว" วันนี้:
     * ชั่วโมงที่ผ่านมา + ชั่วโมงปัจจุบัน = เก็บไว้ (ประวัติเช็คอิน/สถิติ/โควตาที่ใช้จริง ไม่แตะ)
     * ชั่วโมงที่ยังไม่เริ่ม = ยกเลิก → ห้องว่างให้คนอื่นจอง + โควตาคืนเอง (Quota นับสดจากแถวที่ยังไม่ยกเลิก)
     */
    public function endEarly(Request $request)
    {
        $data = $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
            'reason' => 'nullable|string|max:255',
        ]);

        $now   = Carbon::now('Asia/Bangkok');
        $first = BookingGroup::whereIn('id', $data['ids'])->where('status', 'confirmed')->orderBy('time_id')->first();

        if (! $first || $first->date->toDateString() !== $now->toDateString()) {
            return response()->json(['message' => 'ใช้ได้เฉพาะรอบที่ยืนยันแล้วของวันนี้'], 422);
        }

        $session = $first->sessionSiblings()->where('status', 'confirmed')->orderBy('time_id')->get();

        if ($session->min('time_id') > $now->hour) {
            return response()->json(['message' => 'รอบนี้ยังไม่เริ่มใช้ — ใช้ปุ่ม "ยกเลิก" แทน'], 422);
        }

        $release = $session->filter(fn($g) => $g->time_id > $now->hour)->values();
        if ($release->isEmpty()) {
            return response()->json(['message' => 'อยู่ในชั่วโมงสุดท้ายแล้ว ไม่มีชั่วโมงเหลือให้คืน'], 422);
        }

        $admin  = Auth::guard('admin')->user();
        $reason = 'เลิกใช้ก่อนเวลา' . (! empty($data['reason']) ? ' — ' . $data['reason'] : '');

        DB::transaction(function () use ($release, $admin, $reason) {
            foreach ($release as $g) {
                $g->update([
                    'status'        => 'cancelled',
                    'cancelled_at'  => Carbon::now(),
                    'cancelled_by'  => $admin ? "เจ้าหน้าที่ ({$admin->name})" : 'เจ้าหน้าที่',
                    'cancel_reason' => $reason,
                ]);
                $g->bookings()->update(['status' => 'cancelled']);
            }
        });

        $from = $release->min('time_id');
        $to   = $release->max('time_id') + 1;

        return response()->json([
            'message'  => sprintf('คืนห้อง %02d:00 – %02d:00 น. แล้ว', $from, $to),
            'released' => $release->count(),
        ]);
    }

    public function staffStore(Request $request)
    {
        $data = $request->validate([
            'room_id'    => 'required|integer|exists:rooms,id',
            'date'       => 'required|date|after_or_equal:today',
            'time_ids'   => 'required|array|min:1',
            'time_ids.*' => 'integer',
        ]);

        $timeIds = collect($data['time_ids'])->sort()->values()->all();

        for ($i = 1; $i < count($timeIds); $i++) {
            if ($timeIds[$i] !== $timeIds[$i - 1] + 1) {
                return response()->json(['message' => 'ต้องเลือกช่วงเวลาที่ต่อเนื่องกันเท่านั้น'], 422);
            }
        }

        $room = Room::findOrFail($data['room_id']);

        if ($room->status === '1') {
            return response()->json(['message' => 'ห้องนี้ไม่พร้อมให้บริการ'], 422);
        }

        try {
            $groups = DB::transaction(function () use ($data, $timeIds, $room) {
                foreach ($timeIds as $timeId) {
                    $taken = BookingGroup::where('room_id', $data['room_id'])
                        ->where('date', $data['date'])
                        ->where('time_id', $timeId)
                        ->whereIn('status', ['pending', 'waiting_confirm', 'confirmed'])
                        ->lockForUpdate()
                        ->exists();

                    if ($taken) throw new \Exception('slot_taken');
                }

                $adminId   = Auth::guard('admin')->id();
                $groups    = [];
                $sessionId = (string) Str::uuid();

                foreach ($timeIds as $timeId) {
                    $group = BookingGroup::create([
                        'room_id'          => $data['room_id'],
                        'date'             => $data['date'],
                        'time_id'          => $timeId,
                        'session_id'       => $sessionId,
                        'lead_user_id'     => null,
                        'admin_id'         => $adminId,
                        'status'           => 'confirmed',
                        'source'           => 'staff',
                        'join_token'       => Str::random(32),
                        'token_expires_at' => Carbon::now()->addMinutes(15),
                    ]);

                    $groups[] = $group;
                }

                return $groups;
            });

            return response()->json([
                'message' => 'จองสำเร็จ',
                'count'   => count($groups),
            ]);

        } catch (\Exception $e) {
            $msg = $e->getMessage() === 'slot_taken'
                ? 'เสียใจด้วย ช่วงเวลานี้ถูกจองไปแล้ว'
                : 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
            return response()->json(['message' => $msg], 422);
        }
    }

    public function approveSession(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        // อนุมัติได้เฉพาะรอบที่สมาชิกครบแล้ว (waiting_confirm) — pending (รอสมาชิกครบ) ห้ามข้ามขั้น (2026-10-28)
        $groups = BookingGroup::with('room')
            ->whereIn('id', $ids)
            ->where('status', 'waiting_confirm')
            ->get();

        if ($groups->isEmpty()) {
            return response()->json(['message' => 'อนุมัติได้เฉพาะรายการที่สมาชิกครบแล้ว (รอเจ้าหน้าที่อนุมัติ)'], 422);
        }

        foreach ($groups as $g) {
            $g->update(['status' => 'confirmed']);

            if ($g->room?->access_control === '0') {
                // ห้องไม่มี kiosk — เจ้าหน้าที่เช็คคนมาจริงก่อนกดปุ่มนี้อยู่แล้ว ถือว่าเช็คอินไปในตัว
                $g->bookings()->where('status', 'pending')->update([
                    'status'     => 'checked_in',
                    'checkin_at' => Carbon::now('Asia/Bangkok'),
                ]);
            } else {
                // ห้องมี kiosk — อนุมัติเฉยๆ รอสแกน kiosk เพื่อเช็คอินจริงแยกต่างหาก
                $g->bookings()->where('status', 'pending')->update(['status' => 'confirmed']);
            }
        }

        return response()->json(['message' => 'อนุมัติสำเร็จ', 'count' => $groups->count()]);
    }

    // เจ้าหน้าที่กดเช็คอินให้ (ห้องที่ไม่ติด access control)
    public function checkinSession(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];

        $groups = BookingGroup::whereIn('id', $ids)->where('status', 'confirmed')->get();

        $now   = Carbon::now('Asia/Bangkok');
        $count = 0;

        foreach ($groups as $g) {
            $count += $g->bookings()->where('status', 'confirmed')->update([
                'status'     => 'checked_in',
                'checkin_at' => $now,
            ]);
        }

        return response()->json(['message' => 'เช็คอินแล้ว', 'count' => $count]);
    }

    public function rejectSession(Request $request)
    {
        $data = $request->validate([
            'ids'    => 'required|array',
            'ids.*'  => 'integer',
            'reason' => 'nullable|string|max:255',
        ]);

        $groups = BookingGroup::whereIn('id', $data['ids'])
            ->whereIn('status', ['pending', 'waiting_confirm'])
            ->get();

        $admin   = Auth::guard('admin')->user();
        $byLabel = $admin ? "เจ้าหน้าที่ ({$admin->name})" : 'เจ้าหน้าที่';

        foreach ($groups as $g) {
            $g->update([
                'status'        => 'cancelled',
                'cancelled_at'  => Carbon::now(),
                'cancelled_by'  => $byLabel,
                'cancel_reason' => $data['reason'] ?? null,
            ]);
            $g->bookings()->update(['status' => 'cancelled']);
        }

        return response()->json(['message' => 'ปฏิเสธเรียบร้อย', 'count' => $groups->count()]);
    }
}
