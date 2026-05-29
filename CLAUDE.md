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

**Dev login (ทดสอบ member):**
```
http://127.0.0.1:8000/dev/login-as-member
```
ต้องรัน `php artisan db:seed --class=MemberSeeder` ก่อน

---

## โครงสร้าง Database

```
locations
  └── zones (loc_id → locations.id)
        ├── time_weekday → times.id  (config วันธรรมดา)
        ├── time_weekend → times.id  (config วันหยุด)
        ├── zone_daily_quota         (ชั่วโมงสูงสุด/วัน/user, ส่วนใหญ่ = 3)
        └── rooms (zone_id → zones.id)

times
  ├── id=1  จันทร์-ศุกร์ (ปกติ)    hour=9,  total=10  → 09:00–19:00
  ├── id=2  จันทร์-ศุกร์ (งด OT)   hour=9,  total=7   → 09:00–16:00
  ├── id=3  จันทร์-ศุกร์ (OT 4ทุ่ม) hour=9, total=12  → 09:00–21:00
  ├── id=4  เสาร์-อาทิตย์ (ปกติ)   hour=10, total=7   → 10:00–17:00
  └── id=5  เสาร์-อาทิตย์ (OT 4ทุ่ม) hour=10, total=11 → 10:00–21:00

  ** times เป็น config record ไม่ใช่ slot รายชั่วโมง
  ** Slot generate dynamically จาก hour + total (เวลาละ 1 ชั่วโมง)

booking_groups
  ├── room_id, date, time_id (= hour number เช่น 9,10,11...)
  ├── lead_user_id → members.id
  └── status: pending | waiting_confirm | confirmed | completed | cancelled

bookings
  ├── group_id → booking_groups.id
  ├── user_id  → members.id
  └── status: pending | confirmed | checked_in | no_show | cancelled

members    (login ด้วย Google OAuth)
holidays   (วันหยุดนักขัตฤกษ์)
```

---

## Business Rules

- **Quota**: 1 user จองได้ไม่เกิน `zone_daily_quota` ชั่วโมง/วัน/zone (ส่วนใหญ่ = 3 ชม.)
- **Time slots**: generate จาก times config → 1 ชั่วโมงต่อ slot
- **Weekday/Weekend**: เช็คจากวันที่ที่เลือก → ใช้ time_weekday หรือ time_weekend
- **Booked check**: booking_groups ที่ status IN (pending, waiting_confirm, confirmed) = ไม่ว่าง
- **Zone status**: `'0'` = เปิด, `'1'` = ปิด

---

## API Endpoints ที่สร้างแล้ว

| Method | URL | คำอธิบาย |
|--------|-----|----------|
| GET | `/` | Welcome page (locations + zones + rooms) |
| GET | `/rooms/{room}/slots?date=Y-m-d` | ดึง time slots + booked_ids สำหรับห้อง+วันที่ |
| GET | `/dev/login-as-member` | Dev-only login (local env เท่านั้น) |
| GET | `/auth/google` | Google OAuth redirect |
| POST | `/logout` | Logout |

---

## สิ่งที่ทำเสร็จแล้ว

- [x] แสดง locations + zones จาก DB ที่ Welcome.vue
- [x] Modal จอง: เลือกห้อง → เลือกวันที่ → fetch time slots จาก DB จริง
- [x] Time slots generate จาก times config (weekday/weekend)
- [x] แสดง slot ที่ถูกจองแล้ว (grayed out) จาก booking_groups
- [x] Dev login route + MemberSeeder

## สิ่งที่ยังไม่ได้ทำ

- [ ] Submit จองจริง (POST → สร้าง booking_group + booking)
- [ ] Quota check ตอน submit (เช็ค zone_daily_quota)
- [ ] หน้าประวัติการจองของ member
- [ ] ระบบ check-in
- [ ] Admin panel จัดการ booking

---

## Files สำคัญ

| File | หน้าที่ |
|------|--------|
| `routes/web.php` | Routes ทั้งหมด |
| `app/Http/Controllers/LocationController.php` | โหลด locations+zones+rooms → Welcome |
| `app/Http/Controllers/BookingController.php` | slots API |
| `app/Http/Controllers/Auth/GoogleController.php` | Google OAuth |
| `resources/js/Pages/Welcome.vue` | หน้าหลัก (locations, zones, booking modal) |
| `database/seeders/TimeSeeder.php` | seed ข้อมูล times config |
| `database/seeders/ZoneSeeder.php` | seed ข้อมูล zones |
| `database/seeders/MemberSeeder.php` | seed member ทดสอบ (test.member@msu.ac.th) |
