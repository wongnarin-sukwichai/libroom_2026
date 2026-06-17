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
        └── rooms (zone_id → zones.id)
              └── confirm_type: auto | manual

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
holidays   (วันหยุดนักขัตฤกษ์)
kiosk_bypass_codes  (รหัสพิเศษสำหรับเจ้าหน้าที่ ผ่านได้ตลอด)
```

---

## Business Rules

- **Booking date**: จองได้เฉพาะ **วันนี้เท่านั้น** (date ถูก lock ที่ today จาก server)
- **Quota**: 1 user จองได้ไม่เกิน `zone_daily_quota` ชั่วโมง/วัน/zone (ส่วนใหญ่ = 3 ชม.)
- **Time slots**: generate จาก times config → 1 ชั่วโมงต่อ slot, ไม่กรอง past slots
- **Weekday/Weekend**: เช็คจากวันที่ → ใช้ time_weekday หรือ time_weekend ของ zone
- **Booked check**: booking_groups ที่ status IN (pending, waiting_confirm, confirmed) = ไม่ว่าง
- **Zone/Room status**: `'0'` = เปิด, `'1'` = ปิด
- **confirm_type = auto**: จอง → confirmed ทันที
- **confirm_type = manual**: จอง → pending (รอ member ครบ min_capacity) → waiting_confirm (รอ admin approve) → confirmed
- **Join flow**: leader แชร์ join_token (หมดอายุ 15 นาที) ให้ member อื่นมาเข้าร่วม session
- **Kiosk**: member แสดง code ที่ kiosk, ตรวจ booking_groups.status = confirmed + slot ปัจจุบัน

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

### Admin (auth:admin)

| Method | URL | คำอธิบาย |
|--------|-----|----------|
| GET | `/dashboard` | Admin dashboard (SPA) |
| GET | `/admin/overview-stats` | สถิติภาพรวม |
| GET | `/admin/bookings` | รายการ booking ทั้งหมด |
| POST | `/admin/bookings/approve` | Approve session |
| POST | `/admin/bookings/reject` | Reject session |
| POST | `/admin/bookings/staff` | Staff สร้าง booking แทน |
| GET/POST/PUT/DELETE | `/admin/rooms` | จัดการ rooms/zones/locations |
| GET/POST/DELETE | `/admin/holidays` | จัดการวันหยุด |
| GET/POST/PUT/DELETE | `/admin/times` | จัดการ service hours |
| GET/PUT | `/admin/members` | จัดการ members |
| GET/POST/PUT/DELETE | `/admin/users` | จัดการ admin users |
| GET/POST/DELETE | `/admin/kiosk-bypass` | จัดการ kiosk bypass codes |

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
- [x] Bookings: รายการจอง + approve/reject
- [x] Members: จัดการ member + member code
- [x] Rooms: toggle เปิด/ปิด location/zone/room + แก้ไข zone settings
- [x] Holidays: เพิ่ม/ลบวันหยุด
- [x] Service Hours: จัดการ times config
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

- [ ] **Kiosk ไม่ update checked_in**: `KioskController@getAccess` grant access แต่ไม่ update `bookings.status → checked_in` ทำให้ไม่รู้ว่าใครเข้าจริง
- [ ] **ไม่มี no_show / completed logic**: ไม่มี scheduled job มา mark booking ที่ผ่านไปแล้วเป็น `no_show` (ไม่มาแสกน) หรือ `completed` (ใช้งานจบ)
- [ ] **ไม่กรอง past slots**: frontend/backend ไม่ block การจอง slot ที่เวลาผ่านไปแล้วในวันเดียวกัน
- [ ] **Email / Notification**: ยังไม่มีระบบแจ้งเตือนเมื่อ approved/rejected

---

## Files สำคัญ

| File | หน้าที่ |
|------|--------|
| `routes/web.php` | Routes ทั้งหมด (web + admin) |
| `routes/api.php` | Kiosk API route |
| `app/Http/Controllers/LocationController.php` | โหลด Welcome page data |
| `app/Http/Controllers/BookingController.php` | slots, store, myBookings, cancel, join |
| `app/Http/Controllers/KioskController.php` | Kiosk access check |
| `app/Http/Controllers/Admin/` | Admin controllers ทั้งหมด |
| `app/Http/Controllers/Auth/GoogleController.php` | Google OAuth |
| `resources/js/Pages/Welcome.vue` | หน้าหลัก (booking modal) |
| `resources/js/Pages/MyBookings.vue` | ประวัติการจอง |
| `resources/js/Pages/Join.vue` | Join session page |
| `resources/js/Pages/Dashboard.vue` | Admin dashboard (SPA) |
| `resources/js/Components/Admin/` | Admin tab components |
| `database/seeders/` | Seeders ทั้งหมด |
