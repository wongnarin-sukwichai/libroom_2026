<script setup lang="ts">
import type { Feedback } from '../../types/admin';

defineProps<{
    feedbacks: Feedback[];
}>();
</script>

<template>
    <div class="space-y-5">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="p-5 text-center bg-white border shadow-sm rounded-2xl border-slate-200">
                <div class="text-3xl text-amber-500">★★★★★</div>
                <h4 class="mt-1 text-xs font-bold text-slate-400">ความพึงพอใจเฉลี่ย</h4>
                <div class="mt-1 text-3xl font-extrabold text-blue-900">4.8 / 5.0</div>
            </div>
            <div class="p-5 text-center bg-white border shadow-sm rounded-2xl border-slate-200">
                <div class="text-3xl text-blue-900">
                    <i class="fa-solid fa-square-poll-vertical"></i>
                </div>
                <h4 class="mt-1 text-xs font-bold text-slate-400">ผู้เข้าประเมินสะสม</h4>
                <div class="mt-1 text-3xl font-extrabold text-blue-900">426 ราย</div>
            </div>
            <div class="p-5 text-center bg-white border shadow-sm rounded-2xl border-slate-200">
                <div class="text-3xl text-emerald-600">
                    <i class="fa-solid fa-face-smile"></i>
                </div>
                <h4 class="mt-1 text-xs font-bold text-slate-400">ผู้พึงพอใจสูงมาก</h4>
                <div class="mt-1 text-3xl font-extrabold text-emerald-600">98.2%</div>
            </div>
        </div>
        <div class="p-6 space-y-4 bg-white border shadow-sm rounded-2xl border-slate-200">
            <h4 class="pb-3 text-sm font-bold border-b text-slate-900 border-slate-100">
                ความเห็นล่าสุดจากนิสิต
            </h4>
            <div class="space-y-3">
                <div
                    v-for="f in feedbacks"
                    :key="f.id"
                    class="p-4 space-y-1 border bg-slate-50 rounded-xl border-slate-100"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-800">{{ f.studentInfo }}</span
                        ><span class="text-xs text-amber-500"
                            >{{ "★".repeat(f.stars) }}{{ "☆".repeat(5 - f.stars) }}</span
                        >
                    </div>
                    <p class="text-xs italic text-slate-500">"{{ f.comment }}"</p>
                </div>
            </div>
        </div>
    </div>
</template>
