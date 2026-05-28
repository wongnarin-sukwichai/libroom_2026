<script setup lang="ts">
import type { Holiday } from '../../types/admin';

defineProps<{
    holidays: Holiday[];
}>();

const emit = defineEmits<{
    'delete-holiday': [id: number];
}>();
</script>

<template>
    <div class="space-y-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="flex items-center gap-3 p-4 bg-white border shadow-sm rounded-2xl border-slate-200">
                <div class="flex items-center justify-center w-10 h-10 text-red-500 rounded-xl bg-red-50 shrink-0">
                    <i class="fa-solid fa-calendar-xmark"></i>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-semibold">วันหยุดในระบบ</p>
                    <p class="text-xl font-bold text-slate-900">{{ holidays.length }} วัน</p>
                </div>
            </div>
            <div class="flex items-center gap-3 p-4 bg-white border shadow-sm rounded-2xl border-slate-200">
                <div class="flex items-center justify-center w-10 h-10 text-blue-600 rounded-xl bg-blue-50 shrink-0">
                    <i class="fa-solid fa-flag"></i>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-semibold">วันหยุดราชการ</p>
                    <p class="text-xl font-bold text-slate-900">
                        {{ holidays.filter((h) => h.type === "national").length }} วัน
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3 p-4 bg-white border shadow-sm rounded-2xl border-slate-200">
                <div
                    class="flex items-center justify-center w-10 h-10 rounded-xl bg-amber-50 text-amber-600 shrink-0"
                >
                    <i class="fa-solid fa-wrench"></i>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 font-semibold">ปิดปรับปรุง (ห้องสมุด)</p>
                    <p class="text-xl font-bold text-slate-900">
                        {{ holidays.filter((h) => h.type === "library").length }} ครั้ง
                    </p>
                </div>
            </div>
        </div>

        <div class="overflow-hidden bg-white border shadow-sm rounded-2xl border-slate-200">
            <div class="flex items-center justify-between p-5 border-b border-slate-200">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">ตารางวันหยุดและปิดให้บริการ</h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        ระบบจะปิดรับการจองในวันเหล่านี้โดยอัตโนมัติ
                    </p>
                </div>
                <button
                    class="flex items-center gap-1.5 bg-blue-900 hover:bg-blue-800 text-white text-xs font-bold px-3 py-2 rounded-xl transition-colors"
                >
                    <i class="fa-solid fa-plus"></i> เพิ่มวันหยุด
                </button>
            </div>
            <div class="divide-y divide-slate-100">
                <div
                    v-for="h in holidays"
                    :key="h.id"
                    class="flex items-center justify-between px-5 py-3.5 hover:bg-slate-50/70 transition-colors"
                >
                    <div class="flex items-center gap-3">
                        <div
                            :class="
                                h.type === 'national'
                                    ? 'bg-blue-100 text-blue-700'
                                    : 'bg-amber-100 text-amber-700'
                            "
                            class="flex items-center justify-center w-8 h-8 text-xs rounded-lg shrink-0"
                        >
                            <i
                                :class="h.type === 'national' ? 'fa-solid fa-flag' : 'fa-solid fa-wrench'"
                            ></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-900">{{ h.name }}</p>
                            <p class="text-[11px] text-slate-400">
                                <i class="mr-1 fa-regular fa-calendar text-slate-300"></i>{{ h.date }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            :class="
                                h.type === 'national'
                                    ? 'bg-blue-50 text-blue-700 border-blue-200'
                                    : 'bg-amber-50 text-amber-700 border-amber-200'
                            "
                            class="text-[10px] font-bold px-2 py-0.5 rounded border hidden sm:inline-block"
                        >
                            {{ h.type === "national" ? "ราชการ" : "ห้องสมุด" }}
                        </span>
                        <button
                            @click="emit('delete-holiday', h.id)"
                            class="p-1 transition-colors text-slate-400 hover:text-red-500"
                            title="ลบ"
                        >
                            <i class="text-xs fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
