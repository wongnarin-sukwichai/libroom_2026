<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import Swal from "sweetalert2";
import type { AdminUser } from "../types/admin";
import Overview from "../Components/Admin/Overview.vue";
import Bookings from "../Components/Admin/Bookings.vue";
import Members from "../Components/Admin/Members.vue";
import Rooms from "../Components/Admin/Rooms.vue";
import Holidays from "../Components/Admin/Holidays.vue";
import ServiceHours from "../Components/Admin/ServiceHours.vue";
import AdminUsers from "../Components/Admin/AdminUsers.vue";
import KioskAccess from "../Components/Admin/KioskAccess.vue";

interface ToastState {
    show: boolean;
    title: string;
    desc: string;
    isError: boolean;
}
type TabId =
    | "overview"
    | "bookings"
    | "members"
    | "rooms"
    | "holidays"
    | "service_hours"
    | "admin_users"
    | "kiosk";

// Auth
const page = usePage();
const admin = computed<AdminUser | null>(
    () => (page.props as any).auth?.admin ?? null,
);
const isAdmin = computed(() => admin.value?.role === 'admin');

const adminInitials = computed(() => {
    if (!admin.value?.name) return "AD";
    return admin.value.name
        .split(" ")
        .map((n: string) => n[0])
        .join("")
        .substring(0, 2)
        .toUpperCase();
});

// Mobile-aware sidebar
const isMobile = ref(false);
const isSidebarCollapsed = ref(false);
const updateMobile = () => {
    isMobile.value = window.innerWidth < 768;
    if (isMobile.value) isSidebarCollapsed.value = true;
};
onMounted(() => {
    updateMobile();
    window.addEventListener("resize", updateMobile);
});
onUnmounted(() => window.removeEventListener("resize", updateMobile));

const currentTab = ref<TabId>("overview");

const pendingCount = ref(0);

// Toast
const toast = ref<ToastState>({ show: false, title: "", desc: "", isError: false });
let toastTimeout: ReturnType<typeof setTimeout> | null = null;
const showToast = (title: string, desc: string, isError = false) => {
    toast.value = { show: true, title, desc, isError };
    if (toastTimeout) clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => (toast.value.show = false), 5000);
};

// Actions
const toggleSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
};
const switchTab = (id: TabId) => {
    if (id === 'admin_users' && !isAdmin.value) return;
    currentTab.value = id;
    if (isMobile.value) isSidebarCollapsed.value = true;
};

// ─── Staff Booking Modal ───────────────────────────────────────────────────
interface SBRoom   { id: number; title: string; detail: string; confirm_type: 'auto' | 'manual'; status: '0' | '1' }
interface SBZone   { id: number; title: string; title_eng: string; rooms: SBRoom[] }
interface SBLoc    { id: number; title: string; title_eng: string; zones: SBZone[] }
interface SBSlot   { id: number; start: string; end: string; title: string }

const showStaffBooking   = ref(false);
const sbLocations        = ref<SBLoc[]>([]);
const sbLoading          = ref(false);
const sbActiveLoc        = ref<SBLoc | null>(null);
const sbSelectedRoom     = ref<SBRoom | null>(null);
const sbDate             = ref('');
const sbTimes            = ref<SBSlot[]>([]);
const sbBookedIds        = ref<number[]>([]);
const sbSelectedTimeIds  = ref<number[]>([]);
const sbFetchingSlots    = ref(false);

const sbTodayStr = new Date().toISOString().split('T')[0];

const openStaffBooking = async () => {
    showStaffBooking.value   = true;
    sbLoading.value          = true;
    sbActiveLoc.value        = null;
    sbSelectedRoom.value     = null;
    sbDate.value             = sbTodayStr;
    sbTimes.value            = [];
    sbBookedIds.value        = [];
    sbSelectedTimeIds.value  = [];
    try {
        const res         = await fetch('/api/locations');
        sbLocations.value = await res.json();
        if (sbLocations.value.length) sbActiveLoc.value = sbLocations.value[0];
    } finally {
        sbLoading.value = false;
    }
};

const sbFetchSlots = async () => {
    if (!sbSelectedRoom.value || !sbDate.value) return;
    sbFetchingSlots.value = true;
    try {
        const res             = await fetch(`/rooms/${sbSelectedRoom.value.id}/slots?date=${sbDate.value}`);
        const data            = await res.json();
        sbTimes.value         = data.times;
        sbBookedIds.value     = data.booked_ids;
        sbSelectedTimeIds.value = [];
    } finally {
        sbFetchingSlots.value = false;
    }
};

watch([sbSelectedRoom, sbDate], () => {
    if (sbSelectedRoom.value && sbDate.value) sbFetchSlots();
});

const sbSlotState = (id: number) => {
    if (sbBookedIds.value.includes(id)) return 'booked';
    if (sbSelectedTimeIds.value.includes(id)) return 'selected';
    return 'available';
};

const sbSelectSlot = (id: number) => {
    if (sbBookedIds.value.includes(id)) return;
    const ids = [...sbSelectedTimeIds.value].sort((a, b) => a - b);
    if (ids.includes(id)) {
        if (id === ids[ids.length - 1]) sbSelectedTimeIds.value = ids.slice(0, -1);
        else if (id === ids[0])         sbSelectedTimeIds.value = ids.slice(1);
        else                            sbSelectedTimeIds.value = ids.slice(0, ids.indexOf(id));
        return;
    }
    if (!ids.length) { sbSelectedTimeIds.value = [id]; return; }
    const min = ids[0], max = ids[ids.length - 1];
    if (id === max + 1) { sbSelectedTimeIds.value = [...ids, id]; return; }
    if (id === min - 1) { sbSelectedTimeIds.value = [id, ...ids]; return; }
    sbSelectedTimeIds.value = [id]; // non-consecutive → reset
};

const sbSummary = computed(() => {
    const ids = [...sbSelectedTimeIds.value].sort((a, b) => a - b);
    if (!ids.length) return null;
    const pad = (h: number) => String(h).padStart(2, '0');
    return {
        start: `${pad(ids[0])}:00`,
        end:   `${pad(ids[ids.length - 1] + 1)}:00`,
        hours: ids.length,
    };
});

const sbSubmitting = ref(false);

const sbSubmit = async () => {
    if (!sbSummary.value || !sbSelectedRoom.value || sbSubmitting.value) return;
    sbSubmitting.value = true;
    try {
        await axios.post('/admin/bookings/staff', {
            room_id:  sbSelectedRoom.value.id,
            date:     sbDate.value,
            time_ids: sbSelectedTimeIds.value,
        });
        showToast(
            'จองสำเร็จ',
            `${sbSelectedRoom.value.title} • ${sbDate.value} • ${sbSummary.value.start}–${sbSummary.value.end} น. (${sbSummary.value.hours} ชม.)`,
        );
        showStaffBooking.value = false;
    } catch (err: any) {
        const msg = err.response?.data?.message ?? 'เกิดข้อผิดพลาด กรุณาลองใหม่';
        Swal.fire({ title: 'ไม่สำเร็จ', text: msg, icon: 'error', confirmButtonColor: '#1e3a5f' });
    } finally {
        sbSubmitting.value = false;
    }
};
// ──────────────────────────────────────────────────────────────────────────

const logoutAdmin = async () => {
    const result = await Swal.fire({
        title: "ออกจากระบบ?",
        text: "คุณต้องการออกจากระบบหลังบ้านใช่หรือไม่",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#1e3a5f",
        cancelButtonColor: "#94a3b8",
        confirmButtonText: "ใช่, ออกจากระบบ",
        cancelButtonText: "ยกเลิก",
        reverseButtons: true,
    });
    if (result.isConfirmed) router.post("/logout");
};
</script>

<template>
    <div class="flex min-h-screen bg-slate-100 text-slate-800 font-anuphan">
        <!-- Overlay backdrop on mobile when sidebar open -->
        <Transition name="fade">
            <div
                v-if="isMobile && !isSidebarCollapsed"
                class="fixed inset-0 z-20 bg-black/40 backdrop-blur-sm"
                @click="isSidebarCollapsed = true"
            ></div>
        </Transition>

        <!-- ===== SIDEBAR ===== -->
        <aside
            :class="[
                isSidebarCollapsed
                    ? isMobile
                        ? 'w-0 overflow-hidden'
                        : 'w-20'
                    : 'w-64',
                isMobile ? 'fixed inset-y-0 left-0 z-30' : 'relative',
            ]"
            class="flex flex-col justify-between transition-all duration-300 ease-in-out border-r shadow-xl bg-slate-900 text-slate-300 border-slate-800 shrink-0"
        >
            <!-- Logo header -->
            <div>
                <div class="h-[3px] bg-gradient-to-r from-amber-500 via-amber-400 to-amber-600"></div>
                <div
                    class="flex items-center justify-between p-4 border-b bg-slate-950 border-slate-800"
                >
                    <div class="flex items-center gap-2 overflow-hidden">
                        <div
                            class="bg-amber-500 text-slate-950 font-bold p-2.5 rounded-lg text-sm shrink-0 shadow-lg"
                        >
                            MSU
                        </div>
                        <div v-show="!isSidebarCollapsed" class="transition-opacity duration-200">
                            <span class="block text-xs font-bold leading-tight tracking-wider text-white"
                                >ADMIN BACKEND</span
                            >
                            <span class="text-[9px] text-amber-400 font-medium">MSU Library Portal</span>
                        </div>
                    </div>
                </div>

                <!-- Nav -->
                <nav
                    class="p-3 space-y-0.5 overflow-y-auto"
                    style="max-height: calc(100vh - 160px)"
                >
                    <!-- Section: ระบบหลัก -->
                    <p
                        v-show="!isSidebarCollapsed"
                        class="px-3 pt-3 pb-1 text-[9px] font-bold uppercase tracking-widest text-slate-500"
                    >
                        ระบบหลัก
                    </p>
                    <button
                        @click="switchTab('overview')"
                        :class="
                            currentTab === 'overview'
                                ? 'nav-active text-white'
                                : 'hover:bg-slate-800 hover:text-white'
                        "
                        class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl"
                    >
                        <i
                            class="w-4 text-sm text-center fa-solid fa-chart-line"
                            :class="currentTab === 'overview' ? 'text-amber-400' : ''"
                        ></i>
                        <span v-show="!isSidebarCollapsed">ภาพรวมระบบ</span>
                    </button>

                    <!-- Section: การจัดการ -->
                    <p
                        v-show="!isSidebarCollapsed"
                        class="px-3 pt-4 pb-1 text-[9px] font-bold uppercase tracking-widest text-slate-500"
                    >
                        การจัดการ
                    </p>
                    <button
                        v-if="isAdmin"
                        @click="switchTab('admin_users')"
                        :class="
                            currentTab === 'admin_users'
                                ? 'nav-active text-white'
                                : 'hover:bg-slate-800 hover:text-white'
                        "
                        class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl"
                    >
                        <i
                            class="w-4 text-sm text-center fa-solid fa-user-shield"
                            :class="currentTab === 'admin_users' ? 'text-amber-400' : ''"
                        ></i>
                        <span v-show="!isSidebarCollapsed">จัดการผู้ดูแลระบบ</span>
                    </button>
                    <button
                        @click="switchTab('members')"
                        :class="
                            currentTab === 'members'
                                ? 'nav-active text-white'
                                : 'hover:bg-slate-800 hover:text-white'
                        "
                        class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl"
                    >
                        <i
                            class="w-4 text-sm text-center fa-solid fa-id-card"
                            :class="currentTab === 'members' ? 'text-amber-400' : ''"
                        ></i>
                        <span v-show="!isSidebarCollapsed">จัดการสมาชิก</span>
                    </button>
                    <button
                        @click="switchTab('bookings')"
                        :class="
                            currentTab === 'bookings'
                                ? 'nav-active text-white'
                                : 'hover:bg-slate-800 hover:text-white'
                        "
                        class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl"
                    >
                        <i
                            class="w-4 text-sm text-center fa-solid fa-calendar-check"
                            :class="currentTab === 'bookings' ? 'text-amber-400' : ''"
                        ></i>
                        <span v-show="!isSidebarCollapsed">จัดการคำขอจอง</span>
                        <span
                            v-if="pendingCount > 0"
                            :class="isSidebarCollapsed ? 'absolute top-1.5 right-1.5' : 'ml-auto'"
                            class="bg-amber-500 text-slate-950 px-1.5 py-0.5 rounded-full text-[9px] font-extrabold"
                            >{{ pendingCount }}</span
                        >
                    </button>

                    <button
                        v-if="isAdmin"
                        @click="switchTab('kiosk')"
                        :class="
                            currentTab === 'kiosk'
                                ? 'nav-active text-white'
                                : 'hover:bg-slate-800 hover:text-white'
                        "
                        class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl"
                    >
                        <i
                            class="w-4 text-sm text-center fa-solid fa-door-open"
                            :class="currentTab === 'kiosk' ? 'text-amber-400' : ''"
                        ></i>
                        <span v-show="!isSidebarCollapsed">Kiosk Bypass</span>
                    </button>

                    <!-- Section: ตั้งค่าระบบ -->
                    <p
                        v-show="!isSidebarCollapsed"
                        class="px-3 pt-4 pb-1 text-[9px] font-bold uppercase tracking-widest text-slate-500"
                    >
                        ตั้งค่าระบบ
                    </p>
                    <button
                        @click="switchTab('rooms')"
                        :class="
                            currentTab === 'rooms'
                                ? 'nav-active text-white'
                                : 'hover:bg-slate-800 hover:text-white'
                        "
                        class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl"
                    >
                        <i
                            class="w-4 text-sm text-center fa-solid fa-building-columns"
                            :class="currentTab === 'rooms' ? 'text-amber-400' : ''"
                        ></i>
                        <span v-show="!isSidebarCollapsed">จัดการพื้นที่ห้องบริการ</span>
                    </button>
                    <button
                        @click="switchTab('holidays')"
                        :class="
                            currentTab === 'holidays'
                                ? 'nav-active text-white'
                                : 'hover:bg-slate-800 hover:text-white'
                        "
                        class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl"
                    >
                        <i
                            class="w-4 text-sm text-center fa-solid fa-calendar-xmark"
                            :class="currentTab === 'holidays' ? 'text-amber-400' : ''"
                        ></i>
                        <span v-show="!isSidebarCollapsed">วันหยุด & ปิดให้บริการ</span>
                    </button>
                    <button
                        @click="switchTab('service_hours')"
                        :class="
                            currentTab === 'service_hours'
                                ? 'nav-active text-white'
                                : 'hover:bg-slate-800 hover:text-white'
                        "
                        class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl"
                    >
                        <i
                            class="w-4 text-sm text-center fa-solid fa-business-time"
                            :class="currentTab === 'service_hours' ? 'text-amber-400' : ''"
                        ></i>
                        <span v-show="!isSidebarCollapsed">เวลาให้บริการ</span>
                    </button>

                    <!-- Section: รายงาน -->
                    <p
                        v-show="!isSidebarCollapsed"
                        class="px-3 pt-4 pb-1 text-[9px] font-bold uppercase tracking-widest text-slate-500"
                    >
                        รายงาน & ข้อมูล
                    </p>
                    <a
                        href="https://docs.google.com/forms/d/e/1FAIpQLSfG97U9yb9PcTXM3ORInGrNUfqQi3TYbxcsj7Y320h8QEEs7w/viewform?usp=dialog"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl hover:bg-slate-800 hover:text-white"
                    >
                        <i class="w-4 text-sm text-center fa-solid fa-star-half-stroke"></i>
                        <span v-show="!isSidebarCollapsed">ผลประเมิน & รายงาน</span>
                    </a>
                    <a
                        href="/pdf/tools.pdf"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl hover:bg-slate-800 hover:text-white"
                    >
                        <i class="w-4 text-sm text-center fa-solid fa-book-open"></i>
                        <span v-show="!isSidebarCollapsed">คู่มือการใช้งาน</span>
                    </a>
                </nav>
            </div>

            <!-- Sidebar footer: admin info -->
            <div class="p-3 border-t border-slate-800 bg-slate-950/40">
                <div class="flex items-center gap-3 p-2 rounded-xl bg-slate-900/65">
                    <img
                        v-if="admin?.avatar"
                        :src="admin.avatar"
                        :alt="admin.name"
                        class="object-cover rounded-full shadow w-9 h-9 shrink-0 ring-2 ring-amber-500/50"
                    />
                    <div
                        v-else
                        class="flex items-center justify-center text-xs font-bold text-white rounded-full shadow w-9 h-9 bg-gradient-to-tr from-blue-600 to-amber-500 shrink-0"
                    >
                        {{ adminInitials }}
                    </div>
                    <div v-show="!isSidebarCollapsed" class="overflow-hidden">
                        <h5 class="font-bold text-[11px] text-white leading-tight truncate">
                            {{ admin?.name ?? "ผู้ดูแลระบบ" }}
                        </h5>
                        <p class="text-[9px] text-slate-500">ผู้ดูแลระบบห้องสมุด มมส</p>
                    </div>
                    <button
                        @click="logoutAdmin"
                        v-show="!isSidebarCollapsed"
                        class="p-1 ml-auto transition-colors text-slate-500 hover:text-red-400"
                        title="ออกจากระบบ"
                    >
                        <i class="text-xs fa-solid fa-power-off"></i>
                    </button>
                </div>
            </div>
        </aside>

        <!-- ===== MAIN CONTENT ===== -->
        <div class="flex flex-col flex-grow min-w-0">
            <!-- Header -->
            <header
                class="sticky top-0 z-20 flex items-center justify-between px-4 py-3 bg-white border-b-2 shadow-sm border-amber-200 md:px-6"
            >
                <div class="flex items-center gap-3">
                    <button
                        @click="toggleSidebar"
                        class="flex items-center justify-center w-10 h-10 transition-all border shadow-inner rounded-xl hover:bg-slate-100 text-slate-600 active:scale-95 border-slate-200"
                    >
                        <i
                            class="fa-solid"
                            :class="isSidebarCollapsed ? 'fa-bars' : 'fa-bars-staggered'"
                        ></i>
                    </button>
                </div>
                <div class="flex items-center gap-2 md:gap-3">
                    <div class="flex items-center gap-2 pl-2 border-l border-slate-200 md:pl-3">
                        <img
                            v-if="admin?.avatar"
                            :src="admin.avatar"
                            :alt="admin.name"
                            class="object-cover rounded-full shadow-sm w-9 h-9 ring-2 ring-blue-900/20"
                        />
                        <div
                            v-else
                            class="flex items-center justify-center text-xs font-bold text-white rounded-full shadow-sm w-9 h-9 bg-gradient-to-tr from-blue-700 to-indigo-500"
                        >
                            {{ adminInitials }}
                        </div>
                        <div class="hidden md:block">
                            <p class="text-xs font-bold leading-tight text-slate-900">
                                {{ admin?.name ?? "ผู้ดูแลระบบ" }}
                            </p>
                            <p class="text-[10px] text-slate-400 leading-tight">
                                {{ admin?.email ?? "" }}
                            </p>
                        </div>
                        <button
                            @click="logoutAdmin"
                            class="hidden ml-1 text-xs transition-colors text-slate-400 hover:text-red-500 md:block"
                            title="ออกจากระบบ"
                        >
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Tab Content -->
            <main class="flex-grow p-4 overflow-y-auto md:p-6">
                <Transition name="tab" mode="out-in">
                    <div :key="currentTab">
                        <Overview
                            v-if="currentTab === 'overview'"
                            :admin="admin"
                            :pending-count="pendingCount"
                        />
                        <div v-else-if="currentTab === 'bookings'" class="space-y-4">
                            <div class="flex justify-end">
                                <button
                                    @click="openStaffBooking"
                                    class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-4 py-2 rounded-xl shadow transition-all"
                                >
                                    <i class="fa-solid fa-calendar-plus"></i>
                                    จองห้องสำหรับเจ้าหน้าที่
                                </button>
                            </div>
                            <Bookings @pending-count="pendingCount = $event" />
                        </div>
                        <Members v-else-if="currentTab === 'members'" />
                        <Rooms v-else-if="currentTab === 'rooms'" />
                        <Holidays v-else-if="currentTab === 'holidays'" />
                        <ServiceHours v-else-if="currentTab === 'service_hours'" />
                        <AdminUsers v-else-if="currentTab === 'admin_users'" />
                        <KioskAccess v-else-if="currentTab === 'kiosk'" />
                    </div>
                </Transition>
            </main>

            <!-- Footer -->
            <footer
                class="py-4 text-xs text-center border-t bg-slate-900 text-slate-400 border-slate-800"
            >
                &copy; 2026 สำนักวิทยบริการ มหาวิทยาลัยมหาสารคาม &mdash;
                ระบบบริการจองพื้นที่ออนไลน์ (MSU Library System v2)
            </footer>
        </div>

        <!-- Staff Booking Modal -->
        <Transition name="fade">
        <div v-if="showStaffBooking"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
            @click.self="showStaffBooking = false">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-calendar-plus text-indigo-500"></i>
                        <h3 class="text-sm font-bold text-slate-900">จองห้องสำหรับเจ้าหน้าที่</h3>
                    </div>
                    <button @click="showStaffBooking = false" class="text-slate-400 hover:text-slate-700">
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <!-- Loading -->
                    <div v-if="sbLoading" class="py-8 text-center text-xs text-slate-400">
                        <i class="fa-solid fa-spinner fa-spin mr-1"></i> กำลังโหลด...
                    </div>

                    <template v-else>
                        <!-- Location tabs -->
                        <div class="flex gap-2 flex-wrap">
                            <button
                                v-for="loc in sbLocations" :key="loc.id"
                                @click="sbActiveLoc = loc; sbSelectedRoom = null; sbTimes = []; sbSelectedTimeIds = []"
                                :class="sbActiveLoc?.id === loc.id
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : 'bg-white text-slate-700 border-slate-200 hover:border-indigo-400'"
                                class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition-all"
                            >{{ loc.title }}</button>
                        </div>

                        <!-- Zone + Room list -->
                        <div v-if="sbActiveLoc" class="space-y-3">
                            <div v-for="zone in sbActiveLoc.zones" :key="zone.id">
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">{{ zone.title }}</p>
                                <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                                    <button
                                        v-for="room in zone.rooms" :key="room.id"
                                        @click="room.status !== '1' && (sbSelectedRoom = room)"
                                        :disabled="room.status === '1'"
                                        :class="sbSelectedRoom?.id === room.id
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                            : room.status === '1'
                                                ? 'opacity-50 cursor-not-allowed border-slate-200'
                                                : 'border-slate-200 hover:border-indigo-300 hover:bg-slate-50'"
                                        class="text-left text-xs p-2.5 border rounded-xl transition-all"
                                    >
                                        <div class="font-bold leading-tight">{{ room.title }}</div>
                                        <div v-if="room.detail" class="text-[10px] text-slate-400 mt-0.5 line-clamp-1">{{ room.detail }}</div>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Date + Slots (แสดงเมื่อเลือกห้องแล้ว) -->
                        <template v-if="sbSelectedRoom">
                            <div>
                                <label class="text-xs font-bold text-slate-700 block mb-1.5">เลือกวันที่</label>
                                <input type="date" v-model="sbDate" :min="sbTodayStr"
                                    class="w-full text-xs px-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-200" />
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="text-xs font-bold text-slate-700">เลือกช่วงเวลา</label>
                                    <div class="flex items-center gap-3 text-[10px] text-slate-500">
                                        <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 bg-indigo-600 rounded"></span>เลือก</span>
                                        <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 bg-red-200 rounded"></span>ไม่ว่าง</span>
                                    </div>
                                </div>
                                <div v-if="sbFetchingSlots" class="py-5 text-center text-xs text-slate-400">
                                    <i class="fa-solid fa-spinner fa-spin mr-1"></i> กำลังโหลด...
                                </div>
                                <div v-else-if="sbTimes.length" class="grid grid-cols-3 gap-1.5 sm:grid-cols-4">
                                    <button v-for="slot in sbTimes" :key="slot.id" type="button"
                                        @click="sbSelectSlot(slot.id)"
                                        :disabled="sbBookedIds.includes(slot.id)"
                                        :class="{
                                            'bg-indigo-600 border-indigo-700 text-white font-bold': sbSlotState(slot.id) === 'selected',
                                            'bg-red-50 border-red-200 text-red-400 cursor-not-allowed line-through': sbSlotState(slot.id) === 'booked',
                                            'bg-white border-slate-200 text-slate-600 hover:border-indigo-400 hover:bg-indigo-50': sbSlotState(slot.id) === 'available',
                                        }"
                                        class="px-1 py-2.5 text-[11px] border rounded-lg text-center transition-all font-medium"
                                    >{{ slot.start }}–{{ slot.end }}</button>
                                </div>
                                <div v-else-if="sbDate" class="py-5 text-center text-xs text-slate-400 border border-dashed rounded-lg">
                                    ไม่มีช่วงเวลาให้บริการในวันนี้
                                </div>
                            </div>

                            <!-- Summary + Submit -->
                            <div v-if="sbSummary" class="flex items-center gap-3 p-3.5 bg-indigo-50 border border-indigo-200 rounded-xl text-xs text-indigo-900">
                                <i class="text-base text-indigo-400 fa-solid fa-clock shrink-0"></i>
                                <div class="flex-1">
                                    <div class="font-bold">{{ sbSelectedRoom.title }} — {{ sbSummary.start }} – {{ sbSummary.end }} น.</div>
                                    <div class="text-indigo-500 mt-0.5">{{ sbDate }} • {{ sbSummary.hours }} ชั่วโมง</div>
                                </div>
                            </div>
                            <button
                                @click="sbSubmit"
                                :disabled="!sbSummary || sbSubmitting"
                                :class="sbSummary && !sbSubmitting ? 'bg-indigo-600 hover:bg-indigo-700 cursor-pointer' : 'bg-slate-300 cursor-not-allowed'"
                                class="w-full text-white font-bold py-2.5 rounded-lg text-xs shadow-md transition-all flex items-center justify-center gap-1.5"
                            >
                                <i :class="sbSubmitting ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-circle-check'"></i>
                                {{ sbSubmitting ? 'กำลังจอง...' : 'ยืนยันการจอง' }}
                            </button>
                        </template>
                    </template>
                </div>
            </div>
        </div>
        </Transition>

        <!-- Toast -->
        <Transition name="fade">
        <div
            v-if="toast.show"
            class="fixed z-50 flex items-start w-full max-w-sm gap-3 p-4 bg-white border-l-4 shadow-2xl top-6 right-6 rounded-xl"
            :style="{ borderLeftColor: toast.isError ? '#ef4444' : '#22c55e' }"
        >
            <div
                :class="toast.isError ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'"
                class="p-2 rounded-full mt-0.5"
            >
                <i
                    :class="toast.isError ? 'fa-solid fa-circle-xmark' : 'fa-solid fa-circle-check'"
                    class="text-lg"
                ></i>
            </div>
            <div class="flex-grow">
                <h4 class="text-sm font-bold text-slate-900">{{ toast.title }}</h4>
                <p class="text-xs text-slate-500 mt-0.5">{{ toast.desc }}</p>
            </div>
            <button
                @click="toast.show = false"
                class="transition-colors text-slate-400 hover:text-slate-600"
            >
                <i class="text-sm fa-solid fa-xmark"></i>
            </button>
        </div>
        </Transition>
    </div>
</template>

<style scoped>
.font-anuphan {
    font-family: "Anuphan", sans-serif;
}
.nav-active {
    background-color: #1e293b;
}
.nav-active::before {
    content: "";
    position: absolute;
    left: 0;
    top: 8px;
    bottom: 8px;
    width: 3px;
    background-color: #fbbf24;
    border-radius: 0 3px 3px 0;
}
</style>
