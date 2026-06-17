<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import type { AdminUser } from '../../types/admin';

defineProps<{
    admin: AdminUser | null;
    pendingCount: number;
}>();

const approvedToday  = ref(0);
const cancelledToday = ref(0);
const activeRooms    = ref(0);
const totalRooms     = ref(0);
const totalMembers   = ref(0);

// getService — สถิติการเข้าใช้บริการรายพื้นที่ (Location) เดือนนี้
interface ServiceData { arec?: number; dlp?: number; space?: number }
const serviceData    = ref<ServiceData>({});
const serviceLoading = ref(true);
const SERVICE_META: Record<string, { label: string; barClass: string; textClass: string }> = {
    arec:  { label: 'อาคารวิทยบริการ A',  barClass: 'from-blue-700 to-blue-950',      textClass: 'text-blue-900' },
    dlp:   { label: 'อาคารวิทยบริการ B',  barClass: 'from-orange-400 to-amber-500',   textClass: 'text-orange-500' },
    space: { label: 'MSU Space',            barClass: 'from-green-500 to-emerald-600',  textClass: 'text-green-600' },
};
const serviceMax = computed(() =>
    Math.max(1, ...Object.values(serviceData.value).map(v => v ?? 0))
);

// getMost — top 5 zone จาก DB เดือนนี้
interface ZoneStat { title: string; count: number }
const mostData    = ref<ZoneStat[]>([]);
const mostLoading = ref(true);
const ZONE_BARS   = ['bg-blue-600','bg-orange-500','bg-emerald-500','bg-violet-500','bg-rose-500'];
const mostMax     = computed(() => Math.max(1, ...mostData.value.map(z => z.count)));

const thaiMonth = new Date().toLocaleString('th-TH', { month: 'long', year: 'numeric' });

onMounted(async () => {
    const [statsRes, svcRes, mostRes] = await Promise.allSettled([
        axios.get('/admin/overview-stats'),
        axios.get('/admin/overview-service'),
        axios.get('/admin/overview-most'),
    ]);

    if (statsRes.status === 'fulfilled') {
        approvedToday.value  = statsRes.value.data.approved_today;
        cancelledToday.value = statsRes.value.data.cancelled_today;
        activeRooms.value    = statsRes.value.data.active_rooms;
        totalRooms.value     = statsRes.value.data.total_rooms;
        totalMembers.value   = statsRes.value.data.total_members;
    }
    if (svcRes.status === 'fulfilled') {
    const d = svcRes.value.data;
    serviceData.value = (d && typeof d === 'object' && !Array.isArray(d)) ? d : {};
    }
    serviceLoading.value = false;

if (mostRes.status === 'fulfilled') {
    mostData.value = Array.isArray(mostRes.value.data) ? mostRes.value.data : [];
    }
    mostLoading.value = false;
});
</script>

<template>
    <div class="space-y-5">
        <!-- Welcome banner -->
        <div class="relative p-6 overflow-hidden text-white shadow-lg bg-gradient-to-r from-blue-900 to-indigo-900 rounded-2xl">
            <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:12px_12px]"></div>
            <div class="relative z-10 flex flex-col justify-between gap-4 md:flex-row md:items-center">
                <div>
                    <h2 class="text-lg font-bold md:text-xl text-amber-400">
                        ยินดีต้อนรับกลับมา, {{ admin?.name ?? "แอดมิน" }}!
                    </h2>
                    <p class="max-w-xl mt-1 text-xs text-slate-200">
                        ภาพรวมการจองพื้นที่ทั้งหมด — โซนศึกษาหลัก, Co-working Space และห้องศึกษากลุ่ม วันนี้
                    </p>
                </div>
                <span class="bg-blue-950/80 border border-blue-700 text-green-400 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 shrink-0 w-fit">
                    <i class="fa-solid fa-circle text-[8px] animate-pulse"></i>
                    เชื่อมต่อฐานข้อมูลปกติ
                </span>
            </div>
        </div>

        <!-- Primary KPI cards -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="flex items-center justify-between p-5 bg-white border border-l-4 shadow-sm rounded-2xl border-slate-200 border-l-amber-500">
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-slate-400">รออนุมัติ</p>
                    <h3 class="text-2xl font-bold text-slate-900">{{ pendingCount }}</h3>
                    <span class="text-[10px] bg-amber-50 text-amber-600 px-2 py-0.5 rounded border border-amber-200 font-bold">
                        <i class="mr-1 fa-solid fa-clock"></i>รอดำเนินการ
                    </span>
                </div>
                <div class="flex items-center justify-center text-lg w-11 h-11 bg-amber-500/10 text-amber-600 rounded-xl">
                    <i class="fa-solid fa-user-clock"></i>
                </div>
            </div>
            <div class="flex items-center justify-between p-5 bg-white border border-l-4 shadow-sm rounded-2xl border-slate-200 border-l-green-500">
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-slate-400">ยืนยันวันนี้</p>
                    <h3 class="text-2xl font-bold text-slate-900">{{ approvedToday }}</h3>
                    <span class="text-[10px] bg-green-50 text-green-600 px-2 py-0.5 rounded border border-green-200 font-bold">
                        <i class="mr-1 fa-solid fa-calendar-check"></i>confirmed
                    </span>
                </div>
                <div class="flex items-center justify-center text-lg text-green-600 w-11 h-11 bg-green-500/10 rounded-xl">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
            </div>
            <div class="flex items-center justify-between p-5 bg-white border border-l-4 shadow-sm rounded-2xl border-slate-200 border-l-red-400">
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-slate-400">ยกเลิกวันนี้</p>
                    <h3 class="text-2xl font-bold text-slate-900">{{ cancelledToday }}</h3>
                    <span class="text-[10px] bg-red-50 text-red-600 px-2 py-0.5 rounded border border-red-200 font-bold">
                        <i class="mr-1 fa-solid fa-ban"></i>cancelled
                    </span>
                </div>
                <div class="flex items-center justify-center text-lg text-red-500 w-11 h-11 bg-red-500/10 rounded-xl">
                    <i class="fa-solid fa-calendar-xmark"></i>
                </div>
            </div>
            <div class="flex items-center justify-between p-5 bg-white border border-l-4 shadow-sm rounded-2xl border-slate-200 border-l-blue-600">
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-slate-400">ห้องเปิดบริการ</p>
                    <h3 class="text-2xl font-bold text-slate-900">
                        {{ activeRooms }}<span class="text-base text-slate-400">/{{ totalRooms }}</span>
                    </h3>
                    <span class="text-[10px] bg-blue-50 text-blue-600 px-2 py-0.5 rounded border border-blue-200 font-bold">
                        <i class="mr-1 fa-solid fa-door-open"></i>พร้อมให้จอง
                    </span>
                </div>
                <div class="flex items-center justify-center text-lg text-blue-600 w-11 h-11 bg-blue-500/10 rounded-xl">
                    <i class="fa-solid fa-building-columns"></i>
                </div>
            </div>
        </div>

        <!-- Secondary insight cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="flex items-center gap-4 p-4 bg-white border shadow-sm rounded-2xl border-slate-200">
                <div class="flex items-center justify-center w-10 h-10 text-teal-600 bg-teal-500/10 rounded-xl shrink-0">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-semibold">อัตราเช็คอินสำเร็จ</p>
                    <p class="text-lg font-bold text-teal-700">95.6%</p>
                    <p class="text-[10px] text-slate-400">เฉลี่ยสัปดาห์นี้</p>
                </div>
            </div>
            <div class="flex items-center gap-4 p-4 bg-white border shadow-sm rounded-2xl border-slate-200">
                <div class="flex items-center justify-center w-10 h-10 text-purple-600 bg-purple-500/10 rounded-xl shrink-0">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-semibold">สมาชิกทั้งหมด</p>
                    <p class="text-lg font-bold text-purple-700">
                        {{ totalMembers }}<span class="text-xs font-normal text-slate-400"> ราย</span>
                    </p>
                    <p class="text-[10px] text-slate-400">ใช้งานแล้วอย่างน้อย 1 ครั้ง</p>
                </div>
            </div>
            <div class="flex items-center gap-4 p-4 bg-white border shadow-sm rounded-2xl border-slate-200">
                <div class="flex items-center justify-center w-10 h-10 text-orange-600 bg-orange-500/10 rounded-xl shrink-0">
                    <i class="fa-solid fa-fire-flame-curved"></i>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-semibold">ช่วงเวลาพีควันนี้</p>
                    <p class="text-lg font-bold text-orange-600">13:00–15:00</p>
                    <p class="text-[10px] text-slate-400">ยอดจองสูงสุดของวัน</p>
                </div>
            </div>
        </div>

        <!-- Charts (live data) -->
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">

            <!-- getService: สถิติการเข้าใช้บริการรายพื้นที่ เดือนนี้ -->
            <div class="p-6 space-y-4 bg-white border shadow-sm lg:col-span-8 rounded-2xl border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h4 class="flex items-center gap-2 text-sm font-bold text-slate-900">
                        <i class="text-blue-900 fa-solid fa-chart-column"></i>
                        สถิติการเข้าใช้บริการรายพื้นที่
                    </h4>
                    <span class="text-[11px] text-slate-400">{{ thaiMonth }} · หน่วย: ครั้ง</span>
                </div>

                <!-- Loading skeleton -->
                <div v-if="serviceLoading" class="pt-2 space-y-4 animate-pulse">
                    <div v-for="i in 3" :key="i" class="space-y-1.5">
                        <div class="w-1/2 h-3 rounded bg-slate-100"></div>
                        <div class="h-3 rounded-full bg-slate-100"></div>
                    </div>
                </div>

                <!-- No data -->
                <div v-else-if="!Object.keys(serviceData).length" class="py-6 text-xs text-center text-slate-400">
                    <i class="mr-1 fa-solid fa-triangle-exclamation"></i> ไม่สามารถโหลดข้อมูลได้
                </div>

                <!-- Bars -->
                <div v-else class="pt-2 space-y-4">
                    <div
                        v-for="(meta, key) in SERVICE_META" :key="key"
                        class="space-y-1.5"
                    >
                        <div class="flex justify-between text-xs">
                            <span class="font-medium text-slate-700">{{ meta.label }}</span>
                            <span class="font-bold" :class="meta.textClass">
                                {{ (serviceData[key as keyof ServiceData] ?? 0).toLocaleString() }} ครั้ง
                            </span>
                        </div>
                        <div class="w-full h-3 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full transition-all duration-700 rounded-full bg-gradient-to-r"
                                :class="meta.barClass"
                                :style="{ width: Math.round(((serviceData[key as keyof ServiceData] ?? 0) / serviceMax) * 100) + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- getMost: top 5 zone จาก DB เดือนนี้ -->
            <div class="flex flex-col p-6 bg-white border shadow-sm lg:col-span-4 rounded-2xl border-slate-200">
                <h4 class="flex items-center gap-2 pb-3 text-sm font-bold border-b text-slate-900 border-slate-100">
                    <i class="text-orange-500 fa-solid fa-ranking-star"></i>
                    โซนยอดนิยม Top 5
                </h4>
                <p class="text-[10px] text-slate-400 mt-2 mb-3">{{ thaiMonth }}</p>

                <!-- Loading skeleton -->
                <div v-if="mostLoading" class="space-y-3 animate-pulse">
                    <div v-for="i in 5" :key="i" class="space-y-1">
                        <div class="h-2.5 rounded bg-slate-100 w-3/4"></div>
                        <div class="h-2.5 rounded-full bg-slate-100"></div>
                    </div>
                </div>

                <!-- No data -->
                <div v-else-if="!mostData.length" class="flex items-center justify-center flex-1 py-6 text-xs text-slate-400">
                    <div class="text-center">
                        <i class="block mb-1 text-xl fa-solid fa-inbox"></i>
                        ยังไม่มีข้อมูลเดือนนี้
                    </div>
                </div>

                <!-- Bars -->
                <div v-else class="flex-1 space-y-3">
                    <div v-for="(zone, idx) in mostData" :key="idx" class="space-y-1">
                        <div class="flex justify-between text-[11px]">
                            <span class="pr-2 font-medium leading-tight truncate text-slate-700">
                                <span class="mr-1 font-bold text-slate-400">#{{ idx + 1 }}</span>{{ zone.title }}
                            </span>
                            <span class="font-bold text-slate-600 shrink-0">{{ (zone.count ?? 0).toLocaleString() }}</span>
                        </div>
                        <div class="w-full h-2 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full transition-all duration-700 rounded-full"
                                :class="ZONE_BARS[idx]"
                                :style="{ width: Math.round((zone.count / mostMax) * 100) + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>
