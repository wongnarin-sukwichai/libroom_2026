<script setup lang="ts">
import { ref, computed, onMounted, watch } from "vue";
import axios from "axios";
import Swal from "sweetalert2";
import { canEndEarly, canCancelWhole, promptEndEarly } from "./endEarly";

interface BRoom { id: number; title: string; confirm_type: "auto" | "manual"; access_control: "0" | "1"; status: "0" | "1" }
interface BZone { id: number; title: string; status: "0" | "1"; rooms: BRoom[] }
interface BLoc  { id: number; title: string; status: "0" | "1"; zones: BZone[] }

interface Occupant { name: string; status: string }
interface SlotGroup {
    ids: number[];
    status: string;
    source: string;
    start_hour: number;
    end_hour: number;
    hours: number;
    time_label: string;
    lead_name: string;
    lead_email: string | null;
    by_staff: boolean;
    walkin?: boolean;          // เจ้าหน้าที่จองแทนนิสิต
    admin_name?: string | null;
    expires_at?: string | null;
    occupants: Occupant[];
    checked_in: boolean;
    is_continuation: boolean;
}
interface DaySlot { hour: number; label: string; groups: SlotGroup[] }
interface DayData {
    room: {
        id: number; title: string; zone_title: string; loc_title: string;
        confirm_type: "auto" | "manual"; access_control: "0" | "1";
        is_open: boolean; min_capacity: number; max_capacity: number | null;
    };
    date: string;
    is_weekend: boolean;
    slots: DaySlot[];
}
type Summary = Record<number, { waiting: number; pending: number; booked: number }>;

const props = defineProps<{ date: string }>();
// แจ้งหน้า Bookings ให้ดึงตัวเลข "รายการรอเจ้าหน้าที่อนุมัติ" ใหม่ หลังกด action ในผังห้อง
const emit = defineEmits<{ changed: [] }>();

const todayStr = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`; }; // เวลาเครื่อง ไม่ใช่ UTC

const locations   = ref<BLoc[]>([]);
const activeLoc   = ref<BLoc | null>(null);
const selectedRoom = ref<BRoom | null>(null);
const summary     = ref<Summary>({});
const day         = ref<DayData | null>(null);
const loadingTree = ref(false);
const loadingDay  = ref(false);
const slotModal   = ref<DaySlot | null>(null);

const fmtDate = (iso: string) => {
    if (!iso) return "";
    const [y, m, d] = iso.split("-");
    return `${d}/${m}/${y}`;
};

const statusConfig: Record<string, { label: string; cls: string; dot: string }> = {
    pending:         { label: "รอสมาชิกครบ", cls: "bg-slate-100 text-slate-600 border-slate-200",   dot: "bg-slate-400" },
    // สีให้ตรงกับหน้ารายการ (Bookings.vue)
    waiting_confirm: { label: "รอเจ้าหน้าที่อนุมัติ", cls: "bg-amber-50 text-amber-700 border-amber-200", dot: "bg-amber-500" },
    confirmed:       { label: "ยืนยันแล้ว",   cls: "bg-emerald-100 text-emerald-700 border-emerald-200", dot: "bg-emerald-400" },
    checked_in:      { label: "เช็คอินแล้ว",  cls: "bg-blue-100 text-blue-700 border-blue-200",     dot: "bg-blue-500" },
    no_show:         { label: "ไม่มา",        cls: "bg-red-100 text-red-600 border-red-200",        dot: "bg-red-400" },
};
const sourceConfig: Record<string, { label: string; cls: string }> = {
    web:   { label: "เว็บ",       cls: "bg-slate-100 text-slate-500 border-slate-200" },
    qr:    { label: "QR",         cls: "bg-indigo-100 text-indigo-600 border-indigo-200" },
    // ม่วง — ไม่ใช้อำพัน เพราะอำพันสงวนไว้ให้ "รอเจ้าหน้าที่อนุมัติ"
    staff: { label: "เจ้าหน้าที่", cls: "bg-purple-100 text-purple-700 border-purple-200" },
};
// ป้ายที่มา/สถานะของการจองแทนนิสิต (walk-in) แยกจาก "เจ้าหน้าที่จองล่วงหน้า"
const sourceOf = (g: SlotGroup) => g.walkin
    ? { label: `เจ้าหน้าที่จองแทน${g.admin_name ? " · " + g.admin_name : ""}`, cls: sourceConfig.staff.cls }
    : (sourceConfig[g.source] ?? sourceConfig.web);
const WALKIN_WAIT = { label: "รอนิสิตยืนยัน", cls: "bg-violet-50 text-violet-700 border-violet-200", dot: "bg-violet-500" };
const statusOf = (g: SlotGroup) => (g.walkin && g.status === "pending") ? WALKIN_WAIT : (statusConfig[g.status] ?? { label: g.status, cls: "", dot: "" });

// ─── จองแทนนิสิต (walk-in) — เฉพาะช่องว่างของชั่วโมงปัจจุบัน วันนี้ ห้องเปิด ─────────
interface WMember { id: number; name: string; email: string; problem: string | null }
const nowHour = ref(new Date().getHours());
const canWalkin = (slot: DaySlot) =>
    isToday.value && slot.hour === nowHour.value && !slot.groups.length && !!day.value?.room.is_open;
const minutesLeft = () => 60 - new Date().getMinutes();

const wSlot      = ref<DaySlot | null>(null);
const wQuery     = ref("");
const wResults   = ref<WMember[]>([]);
const wSearching = ref(false);
const wMembers   = ref<WMember[]>([]);
const wConsent   = ref(false);
const wSaving    = ref(false);
const wError     = ref("");
const wDone      = ref<{ message: string; lead_name: string; qr_svg: string; url: string; expires_at: string } | null>(null);
let wTimer: ReturnType<typeof setTimeout> | undefined;

function openWalkin(slot: DaySlot) {
    nowHour.value = new Date().getHours();
    if (!canWalkin(slot)) return;
    wSlot.value = slot; wQuery.value = ""; wResults.value = []; wMembers.value = [];
    wConsent.value = false; wError.value = ""; wDone.value = null;
}
function closeWalkin() {
    const created = !!wDone.value;
    wSlot.value = null;
    if (created) { fetchDay(); fetchSummary(); emit("changed"); }
}
// ช่องค้นหาพิมพ์แค่ส่วนหน้าอีเมล (มี "@msu.ac.th" ต่อท้ายให้) — วางอีเมลเต็มมาก็ตัดส่วนหลัง @ ทิ้งเอง
const MSU_DOMAIN = "@msu.ac.th";
function onWalkinQuery() {
    const at = wQuery.value.indexOf("@");
    if (at !== -1) wQuery.value = wQuery.value.slice(0, at);
    wQuery.value = wQuery.value.replace(/\s+/g, "");
    clearTimeout(wTimer);
    wResults.value = [];
    if (wQuery.value.trim().length < 3) return;
    wTimer = setTimeout(async () => {
        wSearching.value = true;
        try {
            const { data } = await axios.get("/admin/bookings/walkin-lookup", {
                params: { room_id: day.value?.room.id, q: wQuery.value.trim() },
            });
            wResults.value = data;
        } finally { wSearching.value = false; }
    }, 350);
}
// Enter = เพิ่มคนที่อีเมลตรงพอดี (ส่วนหน้า + @msu.ac.th) หรือถ้ามีผลลัพธ์ที่ใช้ได้คนเดียว
function onWalkinEnter() {
    const full = (wQuery.value + MSU_DOMAIN).toLowerCase();
    const exact = wResults.value.find(r => r.email.toLowerCase() === full);
    const usable = wResults.value.filter(r => !r.problem && !wMembers.value.some(x => x.id === r.id));
    const pick = exact ?? (usable.length === 1 ? usable[0] : null);
    if (pick) addWalkinMember(pick);
}
function addWalkinMember(m: WMember) {
    if (m.problem || wMembers.value.some(x => x.id === m.id)) return;
    if (wMax.value && wMembers.value.length >= wMax.value) return;
    wMembers.value.push(m);
    wQuery.value = ""; wResults.value = [];
}
const removeWalkinMember = (i: number) => wMembers.value.splice(i, 1);
const makeLead = (i: number) => { const [m] = wMembers.value.splice(i, 1); wMembers.value.unshift(m); };
const wMin = computed(() => day.value?.room.min_capacity ?? 1);
const wMax = computed(() => day.value?.room.max_capacity ?? null);
const wReady = computed(() => wMembers.value.length >= wMin.value && wConsent.value && !wSaving.value);

async function submitWalkin() {
    if (!wReady.value || !day.value) return;
    wSaving.value = true; wError.value = "";
    try {
        const { data } = await axios.post("/admin/bookings/walkin", {
            room_id: day.value.room.id,
            member_ids: wMembers.value.map(m => m.id),
        });
        wDone.value = data;
    } catch (e: any) {
        wError.value = e.response?.data?.message ?? "เกิดข้อผิดพลาด";
    } finally { wSaving.value = false; }
}
const expiresLabel = (iso?: string | null) => {
    if (!iso) return "";
    const d = new Date(iso);
    return `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;
};
const occStatus = (s: string) => statusConfig[s] ?? { label: s, cls: "", dot: "bg-slate-300" };

async function fetchTree() {
    loadingTree.value = true;
    try {
        const { data } = await axios.get("/admin/rooms");
        locations.value = data.locations;
        if (!activeLoc.value && locations.value.length) activeLoc.value = locations.value[0];
        else if (activeLoc.value) activeLoc.value = locations.value.find(l => l.id === activeLoc.value!.id) ?? locations.value[0] ?? null;
    } finally {
        loadingTree.value = false;
    }
}

async function fetchSummary() {
    try {
        const { data } = await axios.get("/admin/bookings/board-summary", { params: { date: props.date } });
        summary.value = data;
    } catch { summary.value = {}; }
}

async function fetchDay() {
    if (!selectedRoom.value) { day.value = null; return; }
    loadingDay.value = true;
    try {
        const { data } = await axios.get("/admin/bookings/room-day", {
            params: { room_id: selectedRoom.value.id, date: props.date },
        });
        day.value = data;
    } finally {
        loadingDay.value = false;
    }
}

function pickRoom(room: BRoom) {
    selectedRoom.value = room;
    slotModal.value = null;
    fetchDay();
}

function backToGrid() {
    selectedRoom.value = null;
    day.value = null;
    slotModal.value = null;
}

watch(() => props.date, () => {
    fetchSummary();
    if (selectedRoom.value) fetchDay();
});

// สถานะห้อง (ใช้ทั้งจุดสี badge ไอคอน และสีตัวหนังสือในการ์ด)
const roomStatus = (room: BRoom) => {
    if (room.status === "1")
        return { dot: "bg-slate-300", label: "ปิด",       badgeBg: "bg-slate-100", icon: "text-slate-400", text: "text-slate-400" };
    const s = summary.value[room.id];
    // ลำดับความสำคัญ: รอเจ้าหน้าที่อนุมัติ (อำพัน) > รอสมาชิกครบ (เทา) > มีจอง (น้ำเงิน) — สีเดียวกับหน้ารายการ
    if (s?.waiting)
        return { dot: "bg-amber-500", label: "รอเจ้าหน้าที่อนุมัติ", badgeBg: "bg-amber-50", icon: "text-amber-500", text: "text-amber-700" };
    if (s?.pending)
        return { dot: "bg-slate-400", label: "รอสมาชิกครบ", badgeBg: "bg-slate-100", icon: "text-slate-500", text: "text-slate-600" };
    if (s?.booked)
        return { dot: "bg-blue-500",  label: "มีจอง",     badgeBg: "bg-blue-100",  icon: "text-blue-500",  text: "text-blue-600" };
    return { dot: "bg-slate-300", label: "ว่าง", badgeBg: "bg-slate-100", icon: "text-slate-400", text: "text-slate-500" };
};

const isToday = computed(() => props.date === todayStr());

// actions ─────────────────────────────────────────────
const acting = ref(false);

// อนุมัติได้เฉพาะเมื่อสมาชิกครบแล้ว (waiting_confirm) / ปฏิเสธได้ทั้งรอสมาชิกครบและรออนุมัติ
function canApprove(g: SlotGroup) {
    return day.value?.room.confirm_type === "manual" && g.status === "waiting_confirm";
}
function canReject(g: SlotGroup) {
    // จองแทนนิสิตที่รอยืนยัน — เจ้าหน้าที่ยกเลิกได้ (กดผิด) ไม่ว่าห้องประเภทไหน
    if (g.walkin && g.status === "pending") return true;
    return day.value?.room.confirm_type === "manual" &&
        (g.status === "pending" || g.status === "waiting_confirm");
}
// ห้องมี kiosk ก็ให้เจ้าหน้าที่เช็คอินแทนได้ (นิสิตมาเคาน์เตอร์ / ไม่มีบัตร / kiosk เสีย)
function canCheckin(g: SlotGroup) {
    return g.status === "confirmed" &&
        !g.checked_in &&
        isToday.value;
}
// รอบที่เริ่มใช้แล้ว → ซ่อน "ยกเลิก" (ลบประวัติเช็คอิน/สถิติ) ใช้ "เลิกใช้ก่อนเวลา" แทน
const sessionOf = (g: SlotGroup) => ({ status: g.status, date: props.date, start_hour: g.start_hour, end_hour: g.end_hour });
function canCancel(g: SlotGroup) {
    return canCancelWhole(sessionOf(g));
}
function canEnd(g: SlotGroup) {
    return canEndEarly(sessionOf(g));
}
async function endEarly(g: SlotGroup) {
    const reason = await promptEndEarly(sessionOf(g), day.value?.room.title ?? "", g.lead_name);
    if (reason === null) return;
    acting.value = true;
    try {
        const { data } = await axios.post("/admin/bookings/end-early", { ids: g.ids, reason: reason || undefined });
        await Promise.all([fetchDay(), fetchSummary()]);
        emit("changed");
        const h = slotModal.value?.hour;
        slotModal.value = h != null ? day.value?.slots.find(s => s.hour === h) ?? null : null;
        Swal.fire({ title: "เลิกใช้ก่อนเวลาแล้ว", text: data.message, icon: "success", timer: 1600, showConfirmButton: false });
    } catch (e: any) {
        Swal.fire("ไม่สำเร็จ", e.response?.data?.message ?? "เกิดข้อผิดพลาด", "error");
    } finally {
        acting.value = false;
    }
}

async function runAction(url: string, g: SlotGroup, confirmText: string, okText: string, color: string, icon: string, withReason = false) {
    const r = await Swal.fire({
        title: confirmText,
        html: `<div class="text-sm text-left"><b>${day.value?.room.title}</b><br>${fmtDate(props.date)} • ${g.time_label}<br><span class="text-slate-400">${g.lead_name}</span></div>`,
        icon,
        input: withReason ? "text" : undefined,
        inputPlaceholder: withReason ? "เหตุผล (ไม่บังคับ)" : undefined,
        showCancelButton: true,
        confirmButtonColor: color,
        cancelButtonColor: "#94a3b8",
        confirmButtonText: okText,
        cancelButtonText: "ยกเลิก",
        reverseButtons: true,
    } as any);
    if (!r.isConfirmed) return;
    acting.value = true;
    try {
        await axios.post(url, { ids: g.ids, reason: withReason ? (r.value || undefined) : undefined });
        await Promise.all([fetchDay(), fetchSummary()]);
        emit("changed");
        const h = slotModal.value?.hour;
        slotModal.value = h != null ? day.value?.slots.find(s => s.hour === h) ?? null : null;
        Swal.fire({ title: "สำเร็จ", icon: "success", timer: 1000, showConfirmButton: false });
    } catch (e: any) {
        Swal.fire("ไม่สำเร็จ", e.response?.data?.message ?? "เกิดข้อผิดพลาด", "error");
    } finally {
        acting.value = false;
    }
}

const approve = (g: SlotGroup) => runAction("/admin/bookings/approve", g, "อนุมัติการจอง?", "อนุมัติ", "#16a34a", "question");
const reject  = (g: SlotGroup) => runAction("/admin/bookings/reject",  g, "ปฏิเสธการจอง?",  "ปฏิเสธ",  "#dc2626", "warning", true);
const checkin = (g: SlotGroup) => runAction("/admin/bookings/checkin", g, "เช็คอินการจองนี้?", "เช็คอิน", "#2563eb", "question");
const cancel  = (g: SlotGroup) => runAction("/admin/bookings/cancel",  g, "ยกเลิกการจองนี้?", "ยกเลิกการจอง", "#dc2626", "warning", true);

onMounted(async () => {
    await fetchTree();
    fetchSummary();
});
</script>

<template>
    <div class="space-y-4">
        <!-- Breadcrumb (โชว์เฉพาะตอนเลือกห้องแล้ว — วันที่ย้ายไปอยู่แถวเดียวกับปุ่มรายการ/ผังห้องของหน้า Bookings แล้ว) -->
        <div v-if="selectedRoom" class="flex items-center gap-1.5 text-xs text-slate-500">
            <button @click="backToGrid" class="font-bold text-blue-700 hover:underline">
                <i class="fa-solid fa-chevron-left text-[10px] mr-1"></i>เลือกห้อง
            </button>
            <span class="text-slate-300">/</span>
            <span class="font-bold text-slate-800">{{ selectedRoom.title }}</span>
        </div>

        <!-- ══ ROOM GRID ══ -->
        <template v-if="!selectedRoom">
            <div v-if="loadingTree" class="py-16 text-xs text-center text-slate-400">
                <i class="mr-1 fa-solid fa-spinner fa-spin"></i> กำลังโหลด...
            </div>
            <template v-else>
                <!-- location tabs (ซ้าย) + legend สถานะห้อง (ขวา) -->
                <div class="flex flex-wrap items-center gap-3">
                    <!-- แถบเลือกอาคาร: ใหญ่ขึ้น + อาคารที่เลือกพื้นเหลือง ให้เห็นชัดว่ากำลังดูอาคารไหน -->
                    <div v-if="locations.length > 1" class="flex flex-wrap gap-1.5 p-1.5 bg-slate-100 rounded-2xl w-fit">
                        <button
                            v-for="loc in locations" :key="loc.id"
                            @click="activeLoc = loc"
                            :class="activeLoc?.id === loc.id
                                ? 'bg-amber-400 text-slate-900 shadow-md'
                                : 'text-slate-500 hover:text-slate-800 hover:bg-white/70'"
                            class="flex items-center gap-2 px-5 py-2.5 text-sm font-bold rounded-xl transition-all"
                        >
                            <i class="fa-solid fa-building" :class="activeLoc?.id === loc.id ? 'text-slate-900' : 'text-slate-400'"></i>
                            {{ loc.title }}
                        </button>
                    </div>

                    <div class="flex items-center flex-wrap gap-3 text-[11px] text-slate-400 ml-auto">
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span>รอเจ้าหน้าที่อนุมัติ</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-slate-400"></span>รอสมาชิกครบ</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 bg-blue-500 rounded-full"></span>มีจอง</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-slate-300"></span>ว่าง / ปิด</span>
                    </div>
                </div>

                <div v-for="zone in activeLoc?.zones ?? []" :key="zone.id"
                    class="overflow-hidden bg-white border shadow-sm border-slate-200 rounded-2xl">
                    <div class="flex items-center gap-2 px-5 py-3 border-b bg-slate-50/60 border-slate-100">
                        <span class="text-sm font-bold text-slate-900">{{ zone.title }}</span>
                        <span v-if="zone.status === '1'" class="text-[10px] font-bold text-red-500 bg-red-50 border border-red-200 px-1.5 py-0.5 rounded-full">ปิดโซน</span>
                        <span class="text-[10px] text-slate-400">{{ zone.rooms.length }} ห้อง</span>
                    </div>
                    <div class="p-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5">
                        <button
                            v-for="room in zone.rooms" :key="room.id"
                            @click="pickRoom(room)"
                            class="flex items-center gap-3 p-3 text-left transition-colors bg-white border rounded-2xl border-slate-200 hover:border-blue-300 hover:shadow-md"
                        >
                            <div class="flex items-center justify-center w-11 h-11 rounded-xl shrink-0" :class="roomStatus(room).badgeBg">
                                <i class="text-lg fa-solid fa-door-open" :class="roomStatus(room).icon"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-bold truncate text-slate-800">{{ room.title }}</div>
                                <div class="text-[11px] mt-0.5 flex items-center gap-1.5" :class="roomStatus(room).text">
                                    <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="roomStatus(room).dot"></span>
                                    <span>{{ roomStatus(room).label }}</span>
                                    <span v-if="room.access_control === '1'" class="hidden text-indigo-400 sm:inline"><i class="fa-solid fa-qrcode"></i></span>
                                    <span v-if="room.confirm_type === 'manual'" class="hidden sm:inline text-amber-500">manual</span>
                                </div>
                            </div>
                            <span class="flex items-center justify-center w-6 h-6 bg-white border rounded-full border-slate-5 shrink-0">
                                <i class="text-[10px] fa-solid fa-chevron-right text-slate-900"></i>
                            </span>
                        </button>
                    </div>
                </div>

                <div v-if="!(activeLoc?.zones?.length)" class="text-xs text-center py-14 text-slate-400">
                    <i class="block mb-2 text-2xl fa-solid fa-inbox"></i>ไม่มีโซนในสถานที่นี้
                </div>
            </template>
        </template>

        <!-- ══ ROOM TIMELINE ══ -->
        <template v-else>
            <div v-if="loadingDay" class="py-16 text-xs text-center text-slate-400">
                <i class="mr-1 fa-solid fa-spinner fa-spin"></i> กำลังโหลด...
            </div>
            <div v-else-if="day" class="overflow-hidden bg-white border shadow-sm border-slate-200 rounded-2xl">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">{{ day.room.title }}</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">
                        {{ day.room.loc_title }} › {{ day.room.zone_title }} •
                        {{ day.room.confirm_type === 'manual' ? 'ยืนยันโดยเจ้าหน้าที่' : 'ยืนยันอัตโนมัติ' }} •
                        {{ day.room.access_control === '1' ? 'มี kiosk' : 'ไม่มี kiosk' }}
                    </p>
                </div>

                <div class="divide-y divide-slate-50">
                    <button
                        v-for="slot in day.slots" :key="slot.hour"
                        @click="slot.groups.length ? (slotModal = slot) : openWalkin(slot)"
                        :disabled="!slot.groups.length && !canWalkin(slot)"
                        class="flex items-start w-full gap-4 px-5 py-3 text-left transition-colors"
                        :class="slot.groups.length || canWalkin(slot) ? 'hover:bg-slate-50 cursor-pointer' : 'cursor-default'"
                    >
                        <div class="w-28 shrink-0 text-xs font-bold text-slate-700 pt-0.5">{{ slot.label }}</div>
                        <div class="flex-1 min-w-0">
                            <!-- ช่องว่างของชั่วโมงปัจจุบัน = จองแทนนิสิตได้ -->
                            <div v-if="!slot.groups.length && canWalkin(slot)"
                                class="inline-flex items-center gap-1.5 text-[11px] font-bold text-indigo-600">
                                <i class="fa-solid fa-user-plus"></i> ว่าง · กดเพื่อจองแทนนิสิต (เหลือ {{ minutesLeft() }} นาที)
                            </div>
                            <div v-else-if="!slot.groups.length" class="text-[11px] text-slate-300">ว่าง</div>
                            <div v-else class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="(g, i) in slot.groups" :key="i"
                                    class="inline-flex items-center gap-1.5 text-[11px] px-2 py-1 rounded-lg border"
                                    :class="statusOf(g).cls || 'bg-slate-100 border-slate-200'"
                                >
                                    <span class="font-bold truncate max-w-[140px]">{{ g.lead_name }}</span>
                                    <span class="opacity-60">·</span>
                                    <!-- ชื่อสถานะ (เหมือนป้ายในหน้ารายการ) -->
                                    <span class="font-semibold">{{ statusOf(g).label }}</span>
                                    <span class="opacity-60">·</span>
                                    <span><i class="fa-solid fa-user-group text-[9px] mr-0.5"></i>{{ g.occupants.length }}</span>
                                    <span v-if="g.is_continuation" class="opacity-60">(ต่อเนื่อง)</span>
                                    <span v-else-if="g.hours > 1" class="opacity-60">{{ g.hours }} ชม.</span>
                                    <span v-if="g.checked_in" class="text-blue-600"><i class="fa-solid fa-circle-check"></i></span>
                                </span>
                            </div>
                        </div>
                        <i v-if="slot.groups.length || canWalkin(slot)" class="fa-solid fa-chevron-right text-[10px] text-slate-300 mt-1"></i>
                    </button>
                </div>
            </div>
        </template>

        <!-- ══ SLOT DETAIL MODAL ══ -->
        <div v-if="slotModal" class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center bg-black/40 sm:p-4"
            @click.self="slotModal = null">
            <div class="bg-white w-full sm:max-w-lg sm:rounded-2xl rounded-t-2xl shadow-xl max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">{{ day?.room.title }}</h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">{{ fmtDate(props.date) }} • {{ slotModal.label }}</p>
                    </div>
                    <button @click="slotModal = null" class="text-slate-400 hover:text-slate-700">
                        <i class="text-lg fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-4 space-y-3 overflow-y-auto">
                    <div v-for="(g, i) in slotModal.groups" :key="i"
                        class="border border-slate-200 rounded-xl p-3.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-slate-900">{{ g.lead_name }}</div>
                                <div v-if="g.lead_email" class="text-[10px] text-slate-400">{{ g.lead_email }}</div>
                            </div>
                            <div class="flex flex-wrap justify-end gap-1 shrink-0">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full border" :class="sourceOf(g).cls">
                                    {{ sourceOf(g).label }}
                                </span>
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full border" :class="statusOf(g).cls">
                                    {{ statusOf(g).label }}
                                </span>
                            </div>
                        </div>
                        <div v-if="g.walkin && g.status === 'pending'" class="mt-1.5 text-[11px] text-violet-700">
                            <i class="mr-1 fa-solid fa-hourglass-half"></i>รอ {{ g.lead_name }} กดยืนยันภายใน {{ expiresLabel(g.expires_at) }} น.
                            — ไม่ยืนยัน ระบบยกเลิกเอง
                        </div>

                        <div class="text-[11px] text-slate-500 mt-1.5">
                            <i class="mr-1 fa-solid fa-clock text-slate-400"></i>{{ g.time_label }}
                            <span v-if="g.hours > 1" class="text-slate-400">({{ g.hours }} ชม.)</span>
                        </div>

                        <!-- occupants -->
                        <div class="mt-2.5 border-t border-slate-100 pt-2 space-y-1">
                            <div v-for="(o, j) in g.occupants" :key="j" class="flex items-center gap-2 text-[11px]">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="occStatus(o.status).dot"></span>
                                <span class="truncate text-slate-700">{{ o.name }}</span>
                                <span class="ml-auto text-slate-300">{{ occStatus(o.status).label }}</span>
                            </div>
                            <div v-if="!g.occupants.length" class="text-[11px] text-slate-300">ยังไม่มีสมาชิก</div>
                        </div>

                        <!-- actions -->
                        <div class="flex flex-wrap gap-1.5 mt-3">
                            <button v-if="canApprove(g)" :disabled="acting" @click="approve(g)"
                                class="bg-green-600 hover:bg-green-700 text-white font-bold px-3 py-1.5 rounded-lg text-[10px] disabled:opacity-50">
                                <i class="mr-1 fa-solid fa-check"></i>อนุมัติ
                            </button>
                            <button v-if="canReject(g)" :disabled="acting" @click="reject(g)"
                                class="bg-red-50 hover:bg-red-100 text-red-600 font-bold px-3 py-1.5 rounded-lg text-[10px] border border-red-200 disabled:opacity-50">
                                ปฏิเสธ
                            </button>
                            <button v-if="canCheckin(g)" :disabled="acting" @click="checkin(g)"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-3 py-1.5 rounded-lg text-[10px] disabled:opacity-50">
                                <i class="mr-1 fa-solid fa-door-open"></i>เช็คอิน
                            </button>
                            <button v-if="canEnd(g)" :disabled="acting" @click="endEarly(g)"
                                title="คืนชั่วโมงที่ยังไม่เริ่ม — ชั่วโมงที่ใช้แล้วเก็บไว้"
                                class="bg-white hover:bg-blue-50 text-blue-600 font-bold px-3 py-1.5 rounded-lg text-[10px] border border-blue-200 disabled:opacity-50">
                                <i class="mr-1 fa-solid fa-hourglass-end"></i>เลิกใช้ก่อนเวลา
                            </button>
                            <button v-if="canCancel(g)" :disabled="acting" @click="cancel(g)"
                                class="bg-white hover:bg-red-50 text-red-500 font-bold px-3 py-1.5 rounded-lg text-[10px] border border-red-200 disabled:opacity-50">
                                <i class="mr-1 fa-solid fa-ban"></i>ยกเลิก
                            </button>
                            <span v-if="g.status === 'confirmed' && g.checked_in"
                                class="text-[10px] font-bold text-emerald-600 inline-flex items-center gap-1 px-1 py-1.5">
                                <i class="fa-solid fa-circle-check"></i>เช็คอินครบแล้ว
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ WALK-IN MODAL: เจ้าหน้าที่จองแทนนิสิต (ชั่วโมงปัจจุบัน) ══ -->
        <div v-if="wSlot && day" class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center bg-black/40 sm:p-4"
            @click.self="closeWalkin">
            <div class="bg-white w-full sm:max-w-lg sm:rounded-2xl rounded-t-2xl shadow-xl max-h-[90vh] flex flex-col">
                <div class="flex items-start justify-between gap-2 px-5 py-4 border-b border-slate-100">
                    <div>
                        <div class="text-sm font-bold text-slate-900"><i class="mr-1.5 fa-solid fa-user-plus text-indigo-500"></i>จองแทนนิสิต</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">
                            {{ day.room.title }} · {{ wSlot.label }} <span class="font-semibold text-indigo-600">(เหลือ {{ minutesLeft() }} นาที)</span>
                        </div>
                    </div>
                    <button @click="closeWalkin" class="text-slate-400 hover:text-slate-600"><i class="text-lg fa-solid fa-xmark"></i></button>
                </div>

                <!-- ผลลัพธ์: QR ให้หัวหน้ากลุ่มสแกนยืนยัน -->
                <div v-if="wDone" class="p-5 space-y-3 overflow-y-auto text-center">
                    <div class="text-xs font-bold text-emerald-700"><i class="mr-1 fa-solid fa-circle-check"></i>{{ wDone.message }}</div>
                    <div class="text-sm font-bold text-slate-900">ให้ <span class="text-indigo-700">{{ wDone.lead_name }}</span> สแกน QR นี้ด้วยมือถือ แล้วกด "ยืนยัน"</div>
                    <div class="flex justify-center p-3 bg-white border rounded-xl border-slate-200 w-fit mx-auto" v-html="wDone.qr_svg"></div>
                    <div class="text-[11px] text-slate-500">
                        หรือเปิดเมนู <b>การจองของฉัน</b> ในบัญชีของหัวหน้ากลุ่ม · ต้องยืนยันภายใน <b>{{ expiresLabel(wDone.expires_at) }} น.</b>
                        ไม่อย่างนั้นระบบจะยกเลิกและคืนห้องเอง
                    </div>
                    <button @click="closeWalkin" class="px-5 py-2 text-xs font-bold text-white bg-blue-900 rounded-lg hover:bg-blue-800">ปิด</button>
                </div>

                <!-- ฟอร์ม -->
                <div v-else class="p-5 space-y-4 overflow-y-auto">
                    <div>
                        <label class="block mb-1 text-xs font-bold text-slate-700">ค้นหาด้วยอีเมล — พิมพ์แค่ส่วนหน้า (อย่างน้อย 3 ตัวอักษร) แล้วกด Enter</label>
                        <div class="flex items-stretch overflow-hidden border rounded-lg border-slate-300 focus-within:ring-2 focus-within:ring-indigo-200">
                            <div class="relative flex-1">
                                <input v-model="wQuery" @input="onWalkinQuery" @keydown.enter.prevent="onWalkinEnter"
                                    type="text" inputmode="email" autocomplete="off" placeholder="เช่น 6501234"
                                    class="w-full px-3 py-2 text-xs border-0 focus:outline-none focus:ring-0" />
                                <i v-if="wSearching" class="absolute text-xs -translate-y-1/2 fa-solid fa-spinner fa-spin right-2 top-1/2 text-slate-400"></i>
                            </div>
                            <span class="flex items-center px-3 text-xs font-semibold border-l select-none bg-slate-100 text-slate-500 border-slate-300">@msu.ac.th</span>
                        </div>
                        <div v-if="wResults.length" class="mt-1 overflow-hidden border divide-y rounded-lg border-slate-200 divide-slate-100">
                            <button v-for="r in wResults" :key="r.id" type="button" @click="addWalkinMember(r)"
                                :disabled="!!r.problem || wMembers.some(x => x.id === r.id)"
                                class="flex items-start w-full gap-2 px-3 py-2 text-left text-[11px] hover:bg-slate-50 disabled:cursor-not-allowed">
                                <i class="mt-0.5 fa-solid" :class="r.problem ? 'fa-circle-xmark text-red-500' : 'fa-circle-plus text-emerald-600'"></i>
                                <div class="min-w-0">
                                    <div class="font-semibold truncate text-slate-800">{{ r.name }}</div>
                                    <div class="truncate text-slate-400">{{ r.email }}</div>
                                    <div v-if="r.problem" class="font-semibold text-red-600">{{ r.problem }}</div>
                                    <div v-else-if="wMembers.some(x => x.id === r.id)" class="text-slate-400">เพิ่มแล้ว</div>
                                </div>
                            </button>
                        </div>
                        <div v-else-if="wQuery.trim().length >= 3 && !wSearching" class="mt-1 text-[11px] text-slate-400">
                            ไม่พบอีเมลนี้ — นิสิตต้องเข้าสู่ระบบด้วยอีเมล @msu.ac.th อย่างน้อย 1 ครั้งก่อน
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1 text-xs">
                            <span class="font-bold text-slate-700">ผู้ใช้ห้อง</span>
                            <span class="font-bold" :class="wMembers.length >= wMin ? 'text-emerald-600' : 'text-slate-500'">
                                {{ wMembers.length }} / {{ wMin }} คน (ขั้นต่ำ){{ wMax ? ` · สูงสุด ${wMax}` : '' }}
                            </span>
                        </div>
                        <ul v-if="wMembers.length" class="border divide-y rounded-lg border-slate-200 divide-slate-100">
                            <li v-for="(m, i) in wMembers" :key="m.id" class="flex items-center gap-2 px-3 py-2 text-[11px]">
                                <i class="w-3 text-center" :class="i === 0 ? 'fa-solid fa-crown text-amber-500' : 'fa-solid fa-user text-slate-300'"
                                    :title="i === 0 ? 'หัวหน้ากลุ่ม (คนกดยืนยัน)' : 'สมาชิก'"></i>
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold truncate text-slate-800">{{ m.name }}</div>
                                    <div class="truncate text-slate-400">{{ m.email }}</div>
                                </div>
                                <button v-if="i > 0" @click="makeLead(i)" class="text-[10px] text-amber-600 hover:underline shrink-0">ตั้งเป็นหัวหน้า</button>
                                <button @click="removeWalkinMember(i)" class="text-slate-400 hover:text-red-500 shrink-0"><i class="fa-solid fa-xmark"></i></button>
                            </li>
                        </ul>
                        <div v-else class="text-[11px] text-slate-400">ยังไม่มีรายชื่อ — คนแรกที่เพิ่มคือหัวหน้ากลุ่ม (คนที่ต้องกดยืนยัน)</div>
                    </div>

                    <label class="flex items-start gap-2 p-3 text-[11px] border rounded-lg cursor-pointer bg-amber-50 border-amber-200 text-amber-900">
                        <input v-model="wConsent" type="checkbox" class="mt-0.5 rounded border-amber-300 text-amber-600" />
                        <span>แจ้งนิสิตแล้ว: ใช้ห้องได้ <b>{{ minutesLeft() }} นาที</b> (ถึง {{ String(wSlot.hour + 1).padStart(2, '0') }}:00 น.)
                            แต่หักโควตา<b>คนละ 1 ชั่วโมง</b> และหัวหน้ากลุ่มต้องกดยืนยันในมือถือภายใน 10 นาที</span>
                    </label>

                    <div v-if="wError" class="px-3 py-2 text-xs text-red-600 border border-red-200 rounded-lg bg-red-50">
                        <i class="mr-1 fa-solid fa-circle-exclamation"></i>{{ wError }}
                    </div>

                    <div class="flex justify-end gap-2">
                        <button @click="closeWalkin" class="px-4 py-2 text-xs font-bold rounded-lg text-slate-600 bg-slate-100 hover:bg-slate-200">ปิด</button>
                        <button @click="submitWalkin" :disabled="!wReady"
                            class="flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed">
                            <i :class="wSaving ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-paper-plane'"></i>
                            ส่งให้นิสิตยืนยัน
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
