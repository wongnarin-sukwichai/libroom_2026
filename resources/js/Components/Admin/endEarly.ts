import Swal from "sweetalert2";

/**
 * "เลิกใช้ก่อนเวลา" — ใช้ร่วมกันใน Bookings.vue (รายการ) และ BookingBoard.vue (ผังห้อง)
 * กติกา: ชั่วโมงที่เริ่มแล้ว (รวมชั่วโมงปัจจุบัน) เก็บไว้ / ชั่วโมงที่ยังไม่เริ่ม คืนห้อง+คืนโควตา
 * backend ตรวจซ้ำด้วยเวลา server เสมอ (POST /admin/bookings/end-early)
 */
export interface SessionLike {
    status: string;
    date: string;       // Y-m-d
    start_hour: number;
    end_hour: number;   // exclusive
}

const pad = (h: number) => `${String(h).padStart(2, "0")}:00`;

export const localToday = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
};

/** รอบนี้เริ่มใช้ไปแล้วหรือยัง (วันนี้ และเลยเวลาเริ่ม หรือเป็นวันที่ผ่านมาแล้ว) */
export function isStarted(s: SessionLike): boolean {
    const today = localToday();
    if (s.date < today) return true;
    return s.date === today && s.start_hour <= new Date().getHours();
}

/** แสดงปุ่ม "เลิกใช้ก่อนเวลา": ยืนยันแล้ว + วันนี้ + เริ่มแล้ว + ยังเหลือชั่วโมงที่ยังไม่เริ่ม */
export function canEndEarly(s: SessionLike): boolean {
    const h = new Date().getHours();
    return s.status === "confirmed" && s.date === localToday() && s.start_hour <= h && s.end_hour - 1 > h;
}

/** ปุ่ม "ยกเลิก" เดิม — เฉพาะรอบที่ยังไม่เริ่ม (ยกเลิกรอบที่เริ่มแล้วจะลบประวัติเช็คอิน/สถิติทิ้ง) */
export function canCancelWhole(s: SessionLike): boolean {
    return s.status === "confirmed" && !isStarted(s);
}

const esc = (v: string) =>
    v.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]!));

/** หน้าต่างยืนยัน — คืนค่า reason (string, อาจว่าง) ถ้ากดยืนยัน หรือ null ถ้าปิด */
export async function promptEndEarly(s: SessionLike, roomTitle: string, who: string): Promise<string | null> {
    const keepTo = new Date().getHours() + 1;
    const r = await Swal.fire({
        title: "เลิกใช้ก่อนเวลา?",
        html: `<div class="text-sm text-left">
                <b>${esc(roomTitle)}</b> <span class="text-slate-400">— ${esc(who)}</span>
                <div class="mt-3 space-y-1.5">
                  <div><span class="text-emerald-600 font-bold">✔ ใช้จริง</span> ${pad(s.start_hour)} – ${pad(keepTo)} น. <span class="text-slate-400">(เก็บไว้ในประวัติ)</span></div>
                  <div><span class="text-blue-600 font-bold">↩ คืนห้อง</span> ${pad(keepTo)} – ${pad(s.end_hour)} น. <span class="text-slate-400">(คนอื่นจองต่อได้ / คืนโควตา ${s.end_hour - keepTo} ชม.)</span></div>
                </div>
              </div>`,
        icon: "question",
        input: "text",
        inputPlaceholder: 'หมายเหตุ (ไม่บังคับ) เช่น "นิสิตแจ้งกลับก่อน"',
        showCancelButton: true,
        confirmButtonColor: "#2563eb",
        cancelButtonColor: "#94a3b8",
        confirmButtonText: "ยืนยันเลิกใช้",
        cancelButtonText: "ปิด",
        reverseButtons: true,
    });
    return r.isConfirmed ? (r.value ?? "") : null;
}
