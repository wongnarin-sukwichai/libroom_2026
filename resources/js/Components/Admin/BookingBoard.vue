<script setup lang="ts">
import { ref, computed, onMounted, watch } from "vue";
import axios from "axios";
import Swal from "sweetalert2";

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
    occupants: Occupant[];
    checked_in: boolean;
    is_continuation: boolean;
}
interface DaySlot { hour: number; label: string; groups: SlotGroup[] }
interface DayData {
    room: { id: number; title: string; zone_title: string; loc_title: string; confirm_type: "auto" | "manual"; access_control: "0" | "1" };
    date: string;
    is_weekend: boolean;
    slots: DaySlot[];
}
type Summary = Record<number, { pending: number; booked: number }>;

const todayStr = () => new Date().toISOString().split("T")[0];

const locations   = ref<BLoc[]>([]);
const activeLoc   = ref<BLoc | null>(null);
const selectedRoom = ref<BRoom | null>(null);
const date        = ref(todayStr());
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
    pending:         { label: "รอสมาชิกครบ", cls: "bg-amber-100 text-amber-700 border-amber-200",   dot: "bg-amber-400" },
    waiting_confirm: { label: "รอเจ้าหน้าที่", cls: "bg-orange-100 text-orange-700 border-orange-200", dot: "bg-orange-400" },
    confirmed:       { label: "ยืนยันแล้ว",   cls: "bg-emerald-100 text-emerald-700 border-emerald-200", dot: "bg-emerald-400" },
    checked_in:      { label: "เช็คอินแล้ว",  cls: "bg-blue-100 text-blue-700 border-blue-200",     dot: "bg-blue-500" },
    no_show:         { label: "ไม่มา",        cls: "bg-red-100 text-red-600 border-red-200",        dot: "bg-red-400" },
};
const sourceConfig: Record<string, { label: string; cls: string }> = {
    web:   { label: "เว็บ",       cls: "bg-slate-100 text-slate-500 border-slate-200" },
    qr:    { label: "QR",         cls: "bg-indigo-100 text-indigo-600 border-indigo-200" },
    staff: { label: "เจ้าหน้าที่", cls: "bg-amber-100 text-amber-700 border-amber-200" },
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
        const { data } = await axios.get("/admin/bookings/board-summary", { params: { date: date.value } });
        summary.value = data;
    } catch { summary.value = {}; }
}

async function fetchDay() {
    if (!selectedRoom.value) { day.value = null; return; }
    loadingDay.value = true;
    try {
        const { data } = await axios.get("/admin/bookings/room-day", {
            params: { room_id: selectedRoom.value.id, date: date.value },
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

watch(date, () => {
    fetchSummary();
    if (selectedRoom.value) fetchDay();
});

const roomDot = (room: BRoom) => {
    if (room.status === "1") return { cls: "bg-slate-200", label: "ปิด" };
    const s = summary.value[room.id];
    if (s?.pending) return { cls: "bg-amber-400", label: `รอ ${s.pending}` };
    if (s?.booked)  return { cls: "bg-blue-500",  label: `จอง ${s.booked}` };
    return { cls: "bg-slate-200", label: "ว่าง" };
};

const isToday = computed(() => date.value === todayStr());

// actions ─────────────────────────────────────────────
const acting = ref(false);

function canApprove(g: SlotGroup) {
    return day.value?.room.confirm_type === "manual" &&
        (g.status === "pending" || g.status === "waiting_confirm");
}
function canCheckin(g: SlotGroup) {
    return g.status === "confirmed" &&
        day.value?.room.access_control === "0" &&
        !g.checked_in &&
        isToday.value;
}
function canCancel(g: SlotGroup) {
    return g.status === "confirmed";
}

async function runAction(url: string, g: SlotGroup, confirmText: string, okText: string, color: string, icon: string, withReason = false) {
    const r = await Swal.fire({
        title: confirmText,
        html: `<div class="text-sm text-left"><b>${day.value?.room.title}</b><br>${fmtDate(date.value)} • ${g.time_label}<br><span class="text-slate-400">${g.lead_name}</span></div>`,
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
        <!-- Date + breadcrumb -->
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative">
                <input
                    :value="fmtDate(date)"
                    readonly
                    class="px-3 py-2 text-xs bg-white border cursor-pointer border-slate-200 rounded-xl text-slate-700 w-32 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    @click="($refs.hiddenDate as HTMLInputElement).showPicker()"
                />
                <input ref="hiddenDate" v-model="date" type="date"
                    class="absolute inset-0 opacity-0 pointer-events-none" />
            </div>
            <button
                @click="date = todayStr()"
                :class="isToday ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50'"
                class="px-3 py-2 text-xs font-bold border rounded-xl transition-colors"
            >วันนี้</button>

            <div v-if="selectedRoom" class="flex items-center gap-1.5 text-xs text-slate-500 ml-1">
                <button @click="backToGrid" class="font-bold text-blue-700 hover:underline">
                    <i class="fa-solid fa-chevron-left text-[10px] mr-1"></i>เลือกห้อง
                </button>
                <span class="text-slate-300">/</span>
                <span class="font-bold text-slate-800">{{ selectedRoom.title }}</span>
            </div>

            <div class="ml-auto flex items-center gap-3 text-[11px] text-slate-400">
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-400"></span>มีคำขอรอ</span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500"></span>มีจอง</span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-slate-200"></span>ว่าง / ปิด</span>
            </div>
        </div>

        <!-- ══ ROOM GRID ══ -->
        <template v-if="!selectedRoom">
            <div v-if="loadingTree" class="py-16 text-xs text-center text-slate-400">
                <i class="mr-1 fa-solid fa-spinner fa-spin"></i> กำลังโหลด...
            </div>
            <template v-else>
                <!-- location tabs -->
                <div v-if="locations.length > 1" class="flex flex-wrap gap-1 p-1 bg-slate-100 rounded-xl w-fit">
                    <button
                        v-for="loc in locations" :key="loc.id"
                        @click="activeLoc = loc"
                        :class="activeLoc?.id === loc.id ? 'bg-white text-blue-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all"
                    >{{ loc.title }}</button>
                </div>

                <div v-for="zone in activeLoc?.zones ?? []" :key="zone.id"
                    class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-5 py-3 bg-slate-50/60 border-b border-slate-100 flex items-center gap-2">
                        <span class="text-sm font-bold text-slate-900">{{ zone.title }}</span>
                        <span v-if="zone.status === '1'" class="text-[10px] font-bold text-red-500 bg-red-50 border border-red-200 px-1.5 py-0.5 rounded-full">ปิดโซน</span>
                        <span class="text-[10px] text-slate-400">{{ zone.rooms.length }} ห้อง</span>
                    </div>
                    <div class="p-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                        <button
                            v-for="room in zone.rooms" :key="room.id"
                            @click="pickRoom(room)"
                            class="text-left px-3 py-2.5 rounded-xl border border-slate-200 hover:border-blue-300 hover:bg-blue-50/40 transition-colors"
                        >
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-xs font-bold text-slate-800 truncate">{{ room.title }}</span>
                                <span class="w-2 h-2 rounded-full shrink-0" :class="roomDot(room).cls"></span>
                            </div>
                            <div class="text-[10px] text-slate-400 mt-0.5 flex items-center gap-1.5">
                                <span>{{ roomDot(room).label }}</span>
                                <span v-if="room.access_control === '1'" class="text-indigo-400"><i class="fa-solid fa-qrcode"></i></span>
                                <span v-if="room.confirm_type === 'manual'" class="text-amber-500">manual</span>
                            </div>
                        </button>
                    </div>
                </div>

                <div v-if="!(activeLoc?.zones?.length)" class="py-14 text-xs text-center text-slate-400">
                    <i class="block mb-2 text-2xl fa-solid fa-inbox"></i>ไม่มีโซนในสถานที่นี้
                </div>
            </template>
        </template>

        <!-- ══ ROOM TIMELINE ══ -->
        <template v-else>
            <div v-if="loadingDay" class="py-16 text-xs text-center text-slate-400">
                <i class="mr-1 fa-solid fa-spinner fa-spin"></i> กำลังโหลด...
            </div>
            <div v-else-if="day" class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
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
                        @click="slot.groups.length && (slotModal = slot)"
                        :disabled="!slot.groups.length"
                        class="w-full text-left px-5 py-3 flex items-start gap-4 transition-colors"
                        :class="slot.groups.length ? 'hover:bg-slate-50 cursor-pointer' : 'cursor-default'"
                    >
                        <div class="w-28 shrink-0 text-xs font-bold text-slate-700 pt-0.5">{{ slot.label }}</div>
                        <div class="flex-1 min-w-0">
                            <div v-if="!slot.groups.length" class="text-[11px] text-slate-300">ว่าง</div>
                            <div v-else class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="(g, i) in slot.groups" :key="i"
                                    class="inline-flex items-center gap-1.5 text-[11px] px-2 py-1 rounded-lg border"
                                    :class="statusConfig[g.status]?.cls ?? 'bg-slate-100 border-slate-200'"
                                >
                                    <span class="font-bold truncate max-w-[140px]">{{ g.lead_name }}</span>
                                    <span class="opacity-60">·</span>
                                    <span><i class="fa-solid fa-user-group text-[9px] mr-0.5"></i>{{ g.occupants.length }}</span>
                                    <span v-if="g.is_continuation" class="opacity-60">(ต่อเนื่อง)</span>
                                    <span v-else-if="g.hours > 1" class="opacity-60">{{ g.hours }} ชม.</span>
                                    <span v-if="g.checked_in" class="text-blue-600"><i class="fa-solid fa-circle-check"></i></span>
                                </span>
                            </div>
                        </div>
                        <i v-if="slot.groups.length" class="fa-solid fa-chevron-right text-[10px] text-slate-300 mt-1"></i>
                    </button>
                </div>
            </div>
        </template>

        <!-- ══ SLOT DETAIL MODAL ══ -->
        <div v-if="slotModal" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40 p-0 sm:p-4"
            @click.self="slotModal = null">
            <div class="bg-white w-full sm:max-w-lg sm:rounded-2xl rounded-t-2xl shadow-xl max-h-[85vh] flex flex-col">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">{{ day?.room.title }}</h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">{{ fmtDate(date) }} • {{ slotModal.label }}</p>
                    </div>
                    <button @click="slotModal = null" class="text-slate-400 hover:text-slate-700">
                        <i class="fa-solid fa-xmark text-lg"></i>
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
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full border" :class="sourceConfig[g.source]?.cls ?? sourceConfig.web.cls">
                                    {{ sourceConfig[g.source]?.label ?? g.source }}
                                </span>
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full border" :class="statusConfig[g.status]?.cls">
                                    {{ statusConfig[g.status]?.label ?? g.status }}
                                </span>
                            </div>
                        </div>

                        <div class="text-[11px] text-slate-500 mt-1.5">
                            <i class="fa-solid fa-clock text-slate-400 mr-1"></i>{{ g.time_label }}
                            <span v-if="g.hours > 1" class="text-slate-400">({{ g.hours }} ชม.)</span>
                        </div>

                        <!-- occupants -->
                        <div class="mt-2.5 border-t border-slate-100 pt-2 space-y-1">
                            <div v-for="(o, j) in g.occupants" :key="j" class="flex items-center gap-2 text-[11px]">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="occStatus(o.status).dot"></span>
                                <span class="text-slate-700 truncate">{{ o.name }}</span>
                                <span class="text-slate-300 ml-auto">{{ occStatus(o.status).label }}</span>
                            </div>
                            <div v-if="!g.occupants.length" class="text-[11px] text-slate-300">ยังไม่มีสมาชิก</div>
                        </div>

                        <!-- actions -->
                        <div class="flex flex-wrap gap-1.5 mt-3">
                            <button v-if="canApprove(g)" :disabled="acting" @click="approve(g)"
                                class="bg-green-600 hover:bg-green-700 text-white font-bold px-3 py-1.5 rounded-lg text-[10px] disabled:opacity-50">
                                <i class="fa-solid fa-check mr-1"></i>อนุมัติ
                            </button>
                            <button v-if="canApprove(g)" :disabled="acting" @click="reject(g)"
                                class="bg-red-50 hover:bg-red-100 text-red-600 font-bold px-3 py-1.5 rounded-lg text-[10px] border border-red-200 disabled:opacity-50">
                                ปฏิเสธ
                            </button>
                            <button v-if="canCheckin(g)" :disabled="acting" @click="checkin(g)"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-3 py-1.5 rounded-lg text-[10px] disabled:opacity-50">
                                <i class="fa-solid fa-door-open mr-1"></i>เช็คอิน
                            </button>
                            <button v-if="canCancel(g)" :disabled="acting" @click="cancel(g)"
                                class="bg-white hover:bg-red-50 text-red-500 font-bold px-3 py-1.5 rounded-lg text-[10px] border border-red-200 disabled:opacity-50">
                                <i class="fa-solid fa-ban mr-1"></i>ยกเลิก
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
    </div>
</template>
