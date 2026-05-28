<script setup lang="ts">
import type { AdminUser } from '../../types/admin';

defineProps<{
    admin: AdminUser | null;
    pendingCount: number;
    approvedCount: number;
    activeRooms: number;
    totalRooms: number;
    totalMembers: number;
}>();
</script>

<template>
    <div class="space-y-5">
        <!-- Welcome banner -->
        <div
            class="relative p-6 overflow-hidden text-white shadow-lg bg-gradient-to-r from-blue-900 to-indigo-900 rounded-2xl"
        >
            <div
                class="absolute inset-0 opacity-10 bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:12px_12px]"
            ></div>
            <div
                class="relative z-10 flex flex-col justify-between gap-4 md:flex-row md:items-center"
            >
                <div>
                    <h2 class="text-lg font-bold md:text-xl text-amber-400">
                        ยินดีต้อนรับกลับมา, {{ admin?.name ?? "แอดมิน" }}!
                    </h2>
                    <p class="max-w-xl mt-1 text-xs text-slate-200">
                        ภาพรวมการจองพื้นที่ทั้งหมด — โซนศึกษาหลัก,
                        Co-working Space และห้องศึกษากลุ่ม วันนี้
                    </p>
                </div>
                <span
                    class="bg-blue-950/80 border border-blue-700 text-green-400 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 shrink-0 w-fit"
                >
                    <i class="fa-solid fa-circle text-[8px] animate-pulse"></i>
                    เชื่อมต่อฐานข้อมูลปกติ
                </span>
            </div>
        </div>

        <!-- Primary KPI cards -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div
                class="flex items-center justify-between p-5 bg-white border border-l-4 shadow-sm rounded-2xl border-slate-200 border-l-amber-500"
            >
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-slate-400">รออนุมัติ</p>
                    <h3 class="text-2xl font-bold text-slate-900">{{ pendingCount }}</h3>
                    <span
                        class="text-[10px] bg-amber-50 text-amber-600 px-2 py-0.5 rounded border border-amber-200 font-bold"
                        ><i class="mr-1 fa-solid fa-clock"></i>รอดำเนินการ</span
                    >
                </div>
                <div
                    class="flex items-center justify-center text-lg w-11 h-11 bg-amber-500/10 text-amber-600 rounded-xl"
                >
                    <i class="fa-solid fa-user-clock"></i>
                </div>
            </div>
            <div
                class="flex items-center justify-between p-5 bg-white border border-l-4 shadow-sm rounded-2xl border-slate-200 border-l-green-500"
            >
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-slate-400">อนุมัติวันนี้</p>
                    <h3 class="text-2xl font-bold text-slate-900">{{ approvedCount }}</h3>
                    <span
                        class="text-[10px] bg-green-50 text-green-600 px-2 py-0.5 rounded border border-green-200 font-bold"
                        ><i class="mr-1 fa-solid fa-arrow-trend-up"></i>+12% เมื่อวาน</span
                    >
                </div>
                <div
                    class="flex items-center justify-center text-lg text-green-600 w-11 h-11 bg-green-500/10 rounded-xl"
                >
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
            </div>
            <div
                class="flex items-center justify-between p-5 bg-white border border-l-4 shadow-sm rounded-2xl border-slate-200 border-l-red-400"
            >
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-slate-400">ยกเลิกอัตโนมัติ</p>
                    <h3 class="text-2xl font-bold text-slate-900">2</h3>
                    <span
                        class="text-[10px] bg-red-50 text-red-600 px-2 py-0.5 rounded border border-red-200 font-bold"
                        ><i class="mr-1 fa-solid fa-ban"></i>ไม่เช็คอินวันนี้</span
                    >
                </div>
                <div
                    class="flex items-center justify-center text-lg text-red-500 w-11 h-11 bg-red-500/10 rounded-xl"
                >
                    <i class="fa-solid fa-calendar-xmark"></i>
                </div>
            </div>
            <div
                class="flex items-center justify-between p-5 bg-white border border-l-4 shadow-sm rounded-2xl border-slate-200 border-l-blue-600"
            >
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-slate-400">ห้องเปิดบริการ</p>
                    <h3 class="text-2xl font-bold text-slate-900">
                        {{ activeRooms }}<span class="text-base text-slate-400">/{{ totalRooms }}</span>
                    </h3>
                    <span
                        class="text-[10px] bg-blue-50 text-blue-600 px-2 py-0.5 rounded border border-blue-200 font-bold"
                        ><i class="mr-1 fa-solid fa-door-open"></i>พร้อมให้จอง</span
                    >
                </div>
                <div
                    class="flex items-center justify-center text-lg text-blue-600 w-11 h-11 bg-blue-500/10 rounded-xl"
                >
                    <i class="fa-solid fa-building-columns"></i>
                </div>
            </div>
        </div>

        <!-- Secondary insight cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div
                class="flex items-center gap-4 p-4 bg-white border shadow-sm rounded-2xl border-slate-200"
            >
                <div
                    class="flex items-center justify-center w-10 h-10 text-teal-600 bg-teal-500/10 rounded-xl shrink-0"
                >
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-semibold">อัตราเช็คอินสำเร็จ</p>
                    <p class="text-lg font-bold text-teal-700">95.6%</p>
                    <p class="text-[10px] text-slate-400">เฉลี่ยสัปดาห์นี้</p>
                </div>
            </div>
            <div
                class="flex items-center gap-4 p-4 bg-white border shadow-sm rounded-2xl border-slate-200"
            >
                <div
                    class="flex items-center justify-center w-10 h-10 text-purple-600 bg-purple-500/10 rounded-xl shrink-0"
                >
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-semibold">สมาชิกทั้งหมด</p>
                    <p class="text-lg font-bold text-purple-700">
                        {{ totalMembers }}
                        <span class="text-xs font-normal text-slate-400">ราย</span>
                    </p>
                    <p class="text-[10px] text-slate-400">ใช้งานแล้วอย่างน้อย 1 ครั้ง</p>
                </div>
            </div>
            <div
                class="flex items-center gap-4 p-4 bg-white border shadow-sm rounded-2xl border-slate-200"
            >
                <div
                    class="flex items-center justify-center w-10 h-10 text-orange-600 bg-orange-500/10 rounded-xl shrink-0"
                >
                    <i class="fa-solid fa-fire-flame-curved"></i>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-semibold">ช่วงเวลาพีควันนี้</p>
                    <p class="text-lg font-bold text-orange-600">13:00–15:00</p>
                    <p class="text-[10px] text-slate-400">ยอดจองสูงสุดของวัน</p>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
            <div
                class="p-6 space-y-4 bg-white border shadow-sm lg:col-span-8 rounded-2xl border-slate-200"
            >
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h4 class="flex items-center gap-2 text-sm font-bold text-slate-900">
                        <i class="text-blue-900 fa-solid fa-chart-column"></i>
                        สถิติการจองแยกโซน (สัปดาห์นี้)
                    </h4>
                    <span class="text-[11px] text-slate-400">หน่วย: ครั้ง</span>
                </div>
                <div class="pt-2 space-y-4">
                    <div class="space-y-1.5">
                        <div class="flex justify-between text-xs">
                            <span class="font-medium text-slate-700">อาคารวิทยบริการ A (โซนหลัก)</span
                            ><span class="font-bold text-blue-900">142 ครั้ง</span>
                        </div>
                        <div class="w-full h-3 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-blue-700 to-blue-950"
                                style="width: 68%"
                            ></div>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex justify-between text-xs">
                            <span class="font-medium text-slate-700"
                                >อาคารวิทยบริการ B (Co-Working Space)</span
                            ><span class="font-bold text-orange-500">210 ครั้ง</span>
                        </div>
                        <div class="w-full h-3 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-orange-400 to-amber-600"
                                style="width: 100%"
                            ></div>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex justify-between text-xs">
                            <span class="font-medium text-slate-700">MSU SPACE (ห้องศึกษากลุ่ม)</span
                            ><span class="font-bold text-green-600">95 ครั้ง</span>
                        </div>
                        <div class="w-full h-3 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-green-400 to-emerald-600"
                                style="width: 45%"
                            ></div>
                        </div>
                    </div>
                </div>
                <div class="pt-3 border-t border-slate-100">
                    <p class="text-[11px] text-slate-400">
                        <i class="mr-1 text-blue-400 fa-solid fa-circle-info"></i>รวมทั้งสัปดาห์:
                        <strong class="text-slate-700">447 ครั้ง</strong>
                        — เพิ่มขึ้น 8.3% จากสัปดาห์ก่อน
                    </p>
                </div>
            </div>
            <div
                class="flex flex-col justify-between p-6 bg-white border shadow-sm lg:col-span-4 rounded-2xl border-slate-200"
            >
                <div>
                    <h4
                        class="flex items-center gap-2 pb-3 text-sm font-bold border-b text-slate-900 border-slate-100"
                    >
                        <i class="text-orange-500 fa-solid fa-chart-pie"></i>
                        สัดส่วนประเภทผู้ใช้
                    </h4>
                    <div class="flex justify-center py-4">
                        <svg class="transform -rotate-90 w-28 h-28">
                            <circle cx="56" cy="56" r="44" fill="transparent" stroke="#f1f5f9" stroke-width="12" />
                            <circle cx="56" cy="56" r="44" fill="transparent" stroke="#1e40af" stroke-width="12" stroke-dasharray="276.46" stroke-dashoffset="55.29" />
                            <circle cx="56" cy="56" r="44" fill="transparent" stroke="#f97316" stroke-width="12" stroke-dasharray="276.46" stroke-dashoffset="220.78" />
                        </svg>
                    </div>
                </div>
                <div class="pt-2 space-y-2 text-xs border-t border-slate-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded bg-blue-900"></span
                            ><span class="text-slate-600">นิสิต ป.ตรี</span>
                        </div>
                        <span class="font-bold text-slate-700">80%</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded bg-orange-500"></span
                            ><span class="text-slate-600">ป.โท–เอก / บุคลากร</span>
                        </div>
                        <span class="font-bold text-slate-700">20%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
