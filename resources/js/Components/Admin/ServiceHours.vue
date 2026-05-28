<script setup lang="ts">
import type { ServiceDay } from '../../types/admin';

defineProps<{
    serviceHours: ServiceDay[];
}>();

const emit = defineEmits<{
    'toggle-day': [idx: number];
}>();
</script>

<template>
    <div class="space-y-5">
        <div class="p-5 text-white bg-gradient-to-r from-blue-900 to-indigo-900 rounded-2xl">
            <h3 class="text-base font-bold">กำหนดเวลาเปิด-ปิดให้บริการ</h3>
            <p class="mt-1 text-xs text-slate-200">
                ระบบแบ่งช่วงเวลาจองเป็น 13 สล็อต ทุก 1 ชั่วโมง ตั้งแต่ 08:00–21:00 น. —
                สล็อตที่พ้นเวลาปิดจะถูกซ่อนโดยอัตโนมัติ
            </p>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="(day, idx) in serviceHours"
                :key="day.day"
                :class="day.isOpen ? 'border-slate-200' : 'border-red-100 bg-red-50/30'"
                class="p-4 transition-all bg-white border shadow-sm rounded-2xl"
            >
                <div class="flex items-center justify-between mb-3">
                    <h4
                        :class="day.isOpen ? 'text-slate-900' : 'text-slate-400'"
                        class="text-sm font-bold"
                    >
                        {{ day.day }}
                    </h4>
                    <button
                        @click="emit('toggle-day', idx)"
                        :class="
                            day.isOpen
                                ? 'bg-green-100 text-green-700 border-green-200 hover:bg-green-200'
                                : 'bg-red-100 text-red-600 border-red-200 hover:bg-red-200'
                        "
                        class="text-[10px] font-bold px-2.5 py-1 rounded-lg border transition-colors"
                    >
                        <i
                            :class="day.isOpen ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'"
                            class="mr-1"
                        ></i>
                        {{ day.isOpen ? "เปิด" : "ปิด" }}
                    </button>
                </div>
                <div v-if="day.isOpen" class="space-y-1.5">
                    <div class="flex items-center gap-2 text-xs text-slate-600">
                        <i class="w-3 text-blue-400 fa-solid fa-clock"></i>
                        <span>{{ day.openTime }} – {{ day.closeTime }} น.</span>
                    </div>
                    <div class="flex items-center gap-2 text-[10px] text-slate-400">
                        <i class="w-3 fa-solid fa-layer-group text-slate-300"></i>
                        <span
                            >{{ Math.round(parseInt(day.closeTime) - parseInt(day.openTime)) }}
                            สล็อต</span
                        >
                    </div>
                </div>
                <p v-else class="text-[11px] text-slate-400 mt-1">
                    <i class="mr-1 fa-solid fa-ban"></i>ไม่มีการให้บริการ
                </p>
            </div>
        </div>
        <div class="flex items-start gap-3 p-4 border bg-amber-50 border-amber-200 rounded-xl">
            <i class="fa-solid fa-circle-info text-amber-500 mt-0.5 shrink-0"></i>
            <p class="text-xs text-amber-800">
                การเปลี่ยนแปลงเวลาให้บริการจะมีผลกับทุกโซนและทุกห้องในระบบ
                ผู้ใช้จะไม่เห็นช่วงเวลาที่อยู่นอกเวลาให้บริการในหน้าจอง
            </p>
        </div>
    </div>
</template>
