# CLAUDE.md — LibRoom V2

โปรเจกต์ระบบจองพื้นที่และบริการออนไลน์ สำนักวิทยบริการ มหาวิทยาลัยมหาสารคาม

---

## Tech Stack

- **Backend**: Laravel 11, PHP, MySQL (XAMPP)
- **Frontend**: Vue 3 (Composition API, `<script setup>`), Inertia.js, Tailwind CSS, Vite
- **Auth**: Google OAuth (MSU Account) via `GoogleController`, guard `auth:member`
- **Admin Auth**: guard `auth:admin`
- **UI Icons**: Font Awesome (CDN)
- **Alerts**: SweetAlert2

---

## การรันโปรเจกต์

```bash
php artisan serve      # backend
npm run dev            # frontend (ต้องการ Node.js 22+)
```

**Dev login:**
```
http://127.0.0.1:8000/dev/login-as-member   # member 1 (test.member@msu.ac.th)
http://127.0.0.1:8000/dev/login-as-member2  # member 2
http://127.0.0.1:8000/dev/login-as-member3  # member 3
http://127.0.0.1:8000/dev/login-as-admin    # admin (wongnarin.s@msu.ac.th)
```
ต้องรัน `php artisan db:seed` ก่อน

---

## โครงสร้าง Database

```
locations
  └── zones (loc_id → locations.id)
        ├── time_weekday → times.id  (config วันธรรมดา)
        ├── time_weekend → times.id  (config วันหยุด)
        ├── zone_daily_quota         (ชั่วโมงสูงสุด/วัน/user, ส่วนใหญ่ = 3)
        ├── min_capacity             (จำนวน member ขั้นต่ำสำหรับห้อง manual)
        ├── zone_tools (zone_id, tool_id, quantity)  ← "คลังอุปกรณ์ภายในโซน" (pool ให้ห้องเลือกติ๊ก — ไม่ auto-inherit)
        └── rooms (zone_id → zones.id)
              ├── confirm_type: auto | manual
              ├── access_control: '0' = ไม่มี kiosk (เจ้าหน้าที่เช็คอิน), '1' = มี kiosk (สแกนเช็คอิน+อนุมัติเอง)
              └── roomtools (room_id, tool_id, mode, quantity)  ← อุปกรณ์ที่ห้องนี้ "มีจริง" (แอดมินติ๊กเอง)
                    mode คงไว้ (ปัจจุบันใช้ 'add' อย่างเดียว)
                    → อุปกรณ์ของห้อง = Room::effectiveTools() = roomtools ของห้องนั้น (ไม่ inherit จาก zone)

tools  (คลังอุปกรณ์กลาง: name, icon) — ใช้ร่วมทุกโซน

zones.scan_prefix  — prefix สร้าง scan_code เช่น "3F-CH"
zones.scan_only    — '1' = ห้ามจองผ่านเว็บทั้งโซน ต้องสแกน QR ที่ตัวอุปกรณ์เท่านั้น (เช่น โซนเก้าอี้) — หน้าแรกยังดูตารางว่างได้ แต่กดจองไม่ได้
rooms.scan_code    — โค้ดบน QR sticker เช่น "3F-CH-012" (unique)
booking_groups.source  — web | qr | staff
scan_logs  — log ทุกครั้งที่สแกน /s/{code} (scan_code, room_id?, user_id?, outcome, ip, ua)

times
  ├── id=1  จันทร์-ศุกร์ (ปกติ)    hour=9,  total=10  → 09:00–19:00
  ├── id=2  จันทร์-ศุกร์ (งด OT)   hour=9,  total=7   → 09:00–16:00
  ├── id=3  จันทร์-ศุกร์ (OT 4ทุ่ม) hour=9, total=12  → 09:00–21:00
  ├── id=4  เสาร์-อาทิตย์ (ปกติ)   hour=10, total=7   → 10:00–17:00
  └── id=5  เสาร์-อาทิตย์ (OT 4ทุ่ม) hour=10, total=11 → 10:00–21:00

  ** times เป็น config record ไม่ใช่ slot รายชั่วโมง
  ** Slot generate dynamically จาก start_hour + end_hour (เวลาละ 1 ชั่วโมง)

booking_groups
  ├── room_id, date, time_id (= hour number เช่น 9,10,11...)
  ├── lead_user_id → members.id
  ├── join_token, token_expires_at  (สำหรับแชร์ลิงก์ invite สมาชิก)
  └── status: pending | waiting_confirm | confirmed | completed | cancelled

bookings
  ├── group_id → booking_groups.id
  ├── user_id  → members.id
  └── status: pending | confirmed | checked_in | no_show | cancelled

members    (login ด้วย Google OAuth, มี code สำหรับ kiosk)
  ├── faculty, branch, type   ← ดึงจากระบบ patron ด้วยรหัสนิสิต (best-effort, ไม่มีก็จองได้)
  └── patron_synced_at        ครั้งล่าสุดที่ลองดึง (set แม้ไม่พบ กันยิงซ้ำ)
holidays   (วันหยุดนักขัตฤกษ์)
kiosk_bypass_codes  (รหัสพิเศษสำหรับเจ้าหน้าที่ ผ่านได้ตลอด)
settings   (key–value config ทั้งระบบ)
  ├── booking_window_enabled     '1' = จำกัดเวลาเปิดจอง, '0' = ไม่จำกัด
  ├── booking_open_time          เวลาเปิดให้กดจอง เช่น "06:00"
  ├── booking_close_time         เวลาปิดรับจอง เช่น "19:00"
  └── member_daily_quota_hours   เพดานรวมทุก zone ต่อคนต่อวัน (default 3) — ดู App\Support\Quota
```

**ENV เพิ่ม (patron):** `PATRON_API_URL`, `PATRON_API_TOKEN` (ส่งเป็น query param `?token=`)

---

## Business Rules

- **Booking date**: จองได้เฉพาะ **วันนี้เท่านั้น** (date ถูก lock ที่ today จาก server)
- **Booking window**: กดจองได้เฉพาะช่วง `booking_open_time`–`booking_close_time` (global, อ้างอิงเวลา server Asia/Bangkok) — บังคับที่ `BookingController@store` ผ่าน `App\Support\BookingWindow`; staff/admin ใช้ `/admin/bookings/staff` จึงไม่ติด gate นี้; การ join session ที่ leader สร้างไว้แล้วไม่ถูกบล็อก
- **Quota (2 ชั้น, เช็คพร้อมกันเสมอผ่าน `App\Support\Quota`)**:
  - **Global**: 1 user จองได้ไม่เกิน `settings.member_daily_quota_hours` ชั่วโมง/วัน **รวมทุก zone** (default 3) — นับรวมทั้งที่เป็น leader และที่ join คนอื่น
  - **Zone**: ซ้อนอยู่ภายในเพดาน global — 1 user จองได้ไม่เกิน `zone_daily_quota` ชั่วโมง/วัน **ในโซนนั้น** (ส่วนใหญ่ = 3 ชม., เช่น zone คาราโอเกะ = 1 ชม.) ใช้ครบใน zone ที่จำกัดไว้ ยังเหลือสิทธิ์ไปใช้ zone อื่นได้ตามเพดาน global ที่เหลือ
  - คืนสิทธิ์ทันทีที่ยกเลิก ไม่ว่าจะยกเลิกโดยสมาชิกเอง เจ้าหน้าที่ หรือระบบ (cron)
  - Staff booking (`AdminBookingController@staffStore`, source=staff) ไม่ผูก `bookings.user_id` จึงไม่กินโควตาใครทั้งนั้น
- **กันจองซ้อน (time conflict)**: 1 user ห้ามมี booking active มากกว่า 1 ที่ในช่วงเวลา (date+time_id) เดียวกัน ไม่ว่าจะคนละห้อง/คนละ zone — เช็คผ่าน `Quota::hasTimeConflict()` ที่ `BookingController@store`/`@join` และ `ScanBookController@show`/`@book` (แสดง state `busy_self` ตอนสแกน QR ถ้าตัวเองมี booking ที่อื่นอยู่แล้วชั่วโมงนี้)
- **Time slots**: generate จาก times config → 1 ชั่วโมงต่อ slot, ไม่กรอง past slots
- **Weekday/Weekend**: เช็คจากวันที่ → ใช้ time_weekday หรือ time_weekend ของ zone
- **Booked check**: booking_groups ที่ status IN (pending, waiting_confirm, confirmed) = ไม่ว่าง
- **Zone/Room status**: `'0'` = เปิด, `'1'` = ปิด
- **4 ประเภทห้อง (confirm_type × access_control) — ตารางช่วยจำ**: ตัดสินด้วย 2 แกนอิสระจากกัน — `confirm_type` (ต้องรอคนอนุมัติไหม) และ `access_control`/kiosk (มีกลไกเช็คอินด้วยตัวเองไหม — kiosk คือ "กลอนประตูไฟฟ้า" ไม่ใช่ด่านขออนุญาตจากคน)

  | | มี kiosk | ไม่มี kiosk |
  |---|---|---|
  | **auto** | confirmed ทันที, เช็คอินด้วยการแตะบัตรที่ kiosk (ปลดล็อกประตูไปในตัว) — ไม่ติดต่อใครเลย (ยังไม่มีห้องจริง, โปรเจกต์อนาคต) | confirmed ทันที, เช็คอินผ่าน `scan_code`/QR ถ้ามี (เช่น เก้าอี้) — ถ้าไม่มี `scan_code` ต้องพึ่งปุ่ม "เช็คอิน" ของแอดมิน (เช่น NAP, Pavilion) |
  | **manual** | ครบสมาชิก → ข้าม `waiting_confirm` ไป confirmed ทันที (kiosk verify แทนคน) → ต้องสแกนใน 15 นาที ไม่งั้น cron ตัด (เช่น ห้องเรียนรู้ A) | ครบสมาชิก → `waiting_confirm` → นิสิตต้องมาติดต่อเคาน์เตอร์ → เจ้าหน้าที่กด "อนุมัติ" ปุ่มเดียว = confirmed+checked_in พร้อมกัน ไม่อนุมัติใน 15 นาทีหลังเริ่ม slot → cron ตัด (เช่น study room) |

- **confirm_type = auto**: จอง → confirmed ทันที (ไม่สนใจ min_capacity เลย)
- **confirm_type = manual**: จอง → pending (รอ member ครบ min_capacity) → ครบแล้วแยกตาม kiosk ของห้อง (เช็คตอน store()/join() ที่ครบ capacity พอดี):
  - **ไม่มี kiosk** (เช่น study room): → **waiting_confirm** (รอเจ้าหน้าที่ — นิสิตต้องมาติดต่อเคาน์เตอร์เอง) → เจ้าหน้าที่เช็คว่ามาจริงแล้วกด "อนุมัติ" **1 ปุ่มเดียว = confirmed + checked_in พร้อมกัน** (`AdminBookingController@approveSession` เช็ค `room.access_control==='0'` แล้ว set checked_in ให้เลย ไม่ต้องกดเช็คอินแยกอีกปุ่ม) — ไม่อนุมัติภายใน 15 นาทีหลังเริ่ม slot → cron ยกเลิก (`cancelUnconfirmedManual`)
  - **มี kiosk** (เช่น ห้องเรียนรู้ A): → **ข้าม waiting_confirm ไป confirmed ทันที** (kiosk ทำหน้าที่ verify ตัวตนแทนเจ้าหน้าที่ ไม่ต้องรอ staff) → ต้องสแกน kiosk ภายใน 15 นาทีหลังเริ่ม slot ไม่งั้น cron ยกเลิก (`cancelUnscannedKiosk`)
- **Join flow**: leader แชร์ join_token (หมดอายุ 15 นาที) ให้ member อื่นมาเข้าร่วม session
- **Check-in**: `bookings.status confirmed → checked_in` เกิดที่ (1) kiosk/scan-to-book สแกน (ห้อง `access_control='1'` หรือมี `scan_code`) (2) ปุ่ม "เช็คอิน" แยกในแท็บ admin (ห้อง `access_control='0'` ที่เป็น auto — ห้อง manual ไม่มี kiosk ถูก merge เข้ากับ "อนุมัติ" ไปแล้ว ไม่มีปุ่มเช็คอินแยกให้เห็น)
- **Kiosk** (`KioskController@getAccess`): member แสดง code + slot ปัจจุบันตรง →
  - ห้อง `access_control='1'`: รับทั้ง `waiting_confirm`/`confirmed` → promote `waiting_confirm→confirmed` + set `checked_in` ทุก slot ที่เหลือใน session (idempotent). `pending` (member ไม่ครบ) = ไม่ผ่าน
  - ห้อง `access_control='0'`: ต้อง `confirmed` มาก่อน (เจ้าหน้าที่ approve/checkin)
- **no_show**: `markNoShow` mark ทุกห้อง — `confirmed` ที่ slot จบแล้ว (เต็มชั่วโมง) ยังไม่ `checked_in` → `no_show` (แค่บันทึกสถิติ ไม่ปล่อย slot คืน ต่างจาก `cancelUnscannedKiosk`/`cancelUnconfirmedManual` ที่ปล่อยคืนทันทีตอน 15 นาที)
- **Scan-to-Book** (`/s/{code}` → `ScanBookController`): QR ติดที่ตัว unit → สแกน = "ฉันอยู่ตรงนี้ ตอนนี้"
  - ไม่ login → เก็บ intended → Google OAuth → กลับมา
  - มี booking ตอนนี้ → เช็คอินให้ (ผ่าน `App\Support\ScanCheckin` — ตัวเดียวกับ Kiosk)
  - คนอื่นจอง / นอกเวลา / วันหยุด / quota หมด → หน้าแจ้งเหตุ
  - ว่าง → เลือก 1–3 ชม. → จอง (confirmed, source=qr) + เช็คอินให้เลย
  - จองล่วงหน้าจาก QR ไม่ได้ (ไม่ตรงบริบท)

---

## API Endpoints

### Web (Inertia)

| Method | URL | คำอธิบาย |
|--------|-----|----------|
| GET | `/` | Welcome page |
| GET | `/rooms/{room}/slots?date=Y-m-d` | Time slots + booked_ids + quota สำหรับห้อง+วันที่ |
| POST | `/bookings` | สร้างการจอง (auth required) |
| GET | `/my-bookings` | ประวัติการจองของ member |
| POST | `/booking-groups/{group}/cancel` | ยกเลิกการจอง |
| GET | `/join/{token}` | หน้า join session |
| POST | `/join/{token}` | เข้าร่วม session |
| GET | `/auth/google` | Google OAuth redirect |
| POST | `/logout` | Logout |
| GET | `/s/{code}` | สแกน QR ที่ตัว unit → จอง/เช็คอิน (auth เช็คใน controller) |
| POST | `/s/{code}/book` | จองจาก QR (confirmed, source=qr) + เช็คอินให้เลย |

### Admin (auth:admin)

| Method | URL | คำอธิบาย |
|--------|-----|----------|
| GET | `/dashboard` | Admin dashboard (SPA) |
| GET | `/admin/overview-stats` | สถิติภาพรวม |
| GET | `/admin/bookings` | รายการ booking ทั้งหมด |
| GET | `/admin/bookings/room-day?room_id=&date=` | ผังห้อง: ช่องเวลา + รายชื่อผู้จองต่อช่อง (session ข้ามชั่วโมงโชว์ทุกช่อง + `is_continuation`) |
| GET | `/admin/bookings/board-summary?date=` | ผังห้อง: จำนวน pending/booked ต่อห้อง (จุดสีบนปุ่มห้อง) |
| POST | `/admin/bookings/approve` | Approve session (→ confirmed, ไม่เช็คอิน) |
| POST | `/admin/bookings/reject` | Reject session (pending/waiting → cancelled) |
| POST | `/admin/bookings/checkin` | เจ้าหน้าที่กดเช็คอิน (ห้อง access_control=0) |
| POST | `/admin/bookings/cancel` | ยกเลิก session ที่ยืนยันแล้ว (confirmed → cancelled) |
| POST | `/admin/bookings/staff` | Staff สร้าง booking แทน |
| POST | `/admin/rooms/{room}/toggle-access` | เปิด/ปิด access control ของห้อง |
| PUT | `/admin/zones/{zone}/tools` | ตั้งชุดอุปกรณ์มาตรฐานของ zone |
| PUT | `/admin/rooms/{room}/tools` | ตั้ง override อุปกรณ์เฉพาะห้อง (add/remove) |
| POST/PUT/DELETE | `/admin/tools` | คลังอุปกรณ์ (catalog) CRUD |
| GET/POST/PUT/DELETE | `/admin/rooms` | จัดการ rooms/zones/locations |
| GET/POST/DELETE | `/admin/holidays` | จัดการวันหยุด |
| GET/POST/PUT/DELETE | `/admin/times` | จัดการ service hours |
| GET/PUT | `/admin/members` | จัดการ members |
| GET/POST/PUT/DELETE | `/admin/users` | จัดการ admin users |
| GET/POST/DELETE | `/admin/kiosk-bypass` | จัดการ kiosk bypass codes |
| GET/PUT | `/admin/settings` | ช่วงเวลาเปิด-ปิดระบบจอง + โควตารวมทุกโซน/วัน (global) — แท็บ Service Hours |
| PUT | `/admin/zones/{zone}/scan-prefix` | ตั้ง prefix สำหรับ generate scan_code |
| POST/PUT | `/admin/rooms/{room}/scan-code` | สร้าง/แก้ scan_code ของห้อง |
| GET | `/admin/zones/{zone}/qr-sheet` | หน้าพิมพ์ QR ทั้งโซน (Blade) |

### API (api.token middleware)

| Method | URL | คำอธิบาย |
|--------|-----|----------|
| GET | `/api/getAccess/{roomId}/{code}` | Kiosk ตรวจสิทธิ์เข้าห้อง |

---

## สิ่งที่ทำเสร็จแล้ว

**Member Flow:**
- [x] Welcome page แสดง locations + zones + rooms จาก DB
- [x] Booking modal: เลือกห้อง → fetch time slots → เลือก slot → จอง
- [x] Time slots generate จาก times config (weekday/weekend)
- [x] แสดง slot ที่ถูกจองแล้ว (grayed out) + quota ที่เหลือ
- [x] Submit จองจริง พร้อม quota check, slot conflict check, DB transaction lock
- [x] Join flow: leader แชร์ link → member อื่น join → ครบ min_capacity → waiting_confirm
- [x] หน้า My Bookings (ประวัติการจอง + cancel)
- [x] Cancel booking

**Admin Panel (Dashboard.vue — SPA แบบ tab):**
- [x] Overview: สถิติภาพรวม
- [x] Bookings: 2 มุมมอง — **รายการ** (แยก tab รอดำเนินการ / จองล่วงหน้า / ยืนยันแล้ว / ยกเลิก + ค้นหา + filter วันที่ + approve/reject + paginate 10) / **ผังห้อง** (`BookingBoard.vue` — เลือกวัน → กริดปุ่มห้อง มีจุดสีบอกสถานะ → กดห้องดูตารางเวลา → กดช่องเวลาดูรายชื่อผู้จองทั้งหมด + approve/reject/checkin/cancel ในแผง)
  - tab "จองล่วงหน้า" = booking `date > วันนี้` (ปุ่ม "จองล่วงหน้า" เดิมชื่อ "จองห้องสำหรับเจ้าหน้าที่" → `staffStore`)
- [x] Members: จัดการ member + member code
- [x] Rooms: 3 sub-tab — สถานะพื้นที่ (toggle location/zone/room + kiosk) / ตั้งค่า Zone / **อุปกรณ์** (คลังอุปกรณ์ CRUD + ชุดมาตรฐานต่อ zone + override เฉพาะห้อง)
- [x] Holidays: เพิ่ม/ลบวันหยุด
- [x] Service Hours: จัดการ times config + ช่วงเวลาเปิด-ปิดระบบจอง (booking window, global)
- [x] Admin Users: จัดการ admin accounts (role: admin/staff)
- [x] Kiosk Access: จัดการ bypass codes

**Kiosk:**
- [x] API ตรวจสิทธิ์เข้าห้อง (room_id + member code → ตรวจ confirmed booking ชั่วโมงปัจจุบัน)
- [x] Admin bypass code (เจ้าหน้าที่เข้าได้ตลอด ไม่ต้องมี booking)

**Auth & Dev:**
- [x] Google OAuth (MSU Account)
- [x] Dev login routes (member 1/2/3, admin)
- [x] Holiday check ตอน submit

---

## สิ่งที่ยังไม่ได้ทำ / Known Gaps

- [x] ~~Kiosk ไม่ update checked_in~~ — kiosk (ห้อง access_control=1) สแกน = อนุมัติ+เช็คอินเอง / ห้อง access_control=0 ใช้ปุ่ม "เช็คอิน" ในแท็บ admin
- [x] ~~no_show logic~~ — `CancelExpiredBookings::markNoShow()` mark ทุกห้อง (ยังต้องตั้ง cron `schedule:run` ตอน deploy)
- [ ] **ไม่มี completed logic**: ไม่มี job mark booking ที่ใช้งานจบเป็น `completed` (enum ถูกลบจาก booking_groups แล้ว)
- [ ] **ไม่กรอง past slots**: frontend/backend ไม่ block การจอง slot ที่เวลาผ่านไปแล้วในวันเดียวกัน
- [ ] **Email / Notification**: ยังไม่มีระบบแจ้งเตือนเมื่อ approved/rejected
- [ ] **Scheduler ยังไม่รันบน local** — `bookings:cancel-expired` ต้องตั้ง cron ตอน deploy

---

## Files สำคัญ

| File | หน้าที่ |
|------|--------|
| `routes/web.php` | Routes ทั้งหมด (web + admin) |
| `routes/api.php` | Kiosk API route |
| `app/Http/Controllers/LocationController.php` | โหลด Welcome page data |
| `app/Http/Controllers/BookingController.php` | slots, store, myBookings, cancel, join |
| `app/Http/Controllers/KioskController.php` | Kiosk access check |
| `app/Support/BookingWindow.php` | Logic ช่วงเวลาเปิด-ปิดระบบจอง (อ่านจาก `settings`) |
| `app/Support/Quota.php` | Logic โควตา 2 ชั้น (global + zone) + กันจองซ้อนเวลาเดียวกัน — ใช้ร่วมกันใน store/join/scan-book |
| `app/Http/Controllers/Admin/AdminSettingController.php` | GET/PUT `/admin/settings` |
| `app/Http/Controllers/Admin/` | Admin controllers ทั้งหมด |
| `app/Http/Controllers/Auth/GoogleController.php` | Google OAuth (+ `defer()` เรียก PatronService หลัง login) |
| `app/Support/PatronService.php` | ดึง faculty/branch/type จาก `libapp.msu.ac.th` (config: `services.patron`) |
| `app/Console/Commands/SyncPatronDetails.php` | `members:sync-patron` — backfill/refresh (schedule รายเดือน) |
| `app/Console/Commands/CancelExpiredBookings.php` | `bookings:cancel-expired` — cron everyMinute: join-token หมดอายุ, manual ไม่มี kiosk ไม่อนุมัติใน 15 นาที, มี kiosk ไม่สแกนใน 15 นาที, mark no_show |
| `resources/js/Pages/Welcome.vue` | หน้าหลัก (booking modal) |
| `resources/js/Pages/MyBookings.vue` | ประวัติการจอง |
| `resources/js/Pages/Join.vue` | Join session page |
| `resources/js/Pages/Dashboard.vue` | Admin dashboard (SPA) |
| `resources/js/Components/Admin/` | Admin tab components |
| `database/seeders/` | Seeders ทั้งหมด |
