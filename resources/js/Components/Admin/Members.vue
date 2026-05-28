<script setup lang="ts">
import type { Member } from '../../types/admin';

defineProps<{
    members: Member[];
}>();

const memberInitials = (name: string) =>
    name.split(" ").map((n) => n[0]).join("").substring(0, 2).toUpperCase();
</script>

<template>
    <div class="space-y-5">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="p-4 text-center bg-white border shadow-sm rounded-2xl border-slate-200">
                <p class="text-xs font-semibold text-slate-400">สมาชิกทั้งหมด</p>
                <p class="mt-1 text-2xl font-bold text-blue-900">{{ members.length }}</p>
            </div>
            <div class="p-4 text-center bg-white border shadow-sm rounded-2xl border-slate-200">
                <p class="text-xs font-semibold text-slate-400">นิสิต ป.ตรี</p>
                <p class="mt-1 text-2xl font-bold text-blue-700">
                    {{ members.filter((m) => m.type === "นิสิต ป.ตรี").length }}
                </p>
            </div>
            <div class="p-4 text-center bg-white border shadow-sm rounded-2xl border-slate-200">
                <p class="text-xs font-semibold text-slate-400">บัณฑิตศึกษา</p>
                <p class="mt-1 text-2xl font-bold text-indigo-700">
                    {{ members.filter((m) => m.type === "บัณฑิตศึกษา").length }}
                </p>
            </div>
            <div class="p-4 text-center bg-white border shadow-sm rounded-2xl border-slate-200">
                <p class="text-xs font-semibold text-slate-400">บุคลากร/อาจารย์</p>
                <p class="mt-1 text-2xl font-bold text-amber-600">
                    {{ members.filter((m) => m.type === "บุคลากร/อาจารย์").length }}
                </p>
            </div>
        </div>

        <div class="overflow-hidden bg-white border shadow-sm rounded-2xl border-slate-200">
            <div class="flex items-center justify-between p-5 border-b border-slate-200">
                <h3 class="text-sm font-bold text-slate-900">รายชื่อสมาชิกทั้งหมด</h3>
                <div class="relative hidden sm:block">
                    <i
                        class="absolute text-xs -translate-y-1/2 left-3 top-1/2 fa-solid fa-magnifying-glass text-slate-400"
                    ></i>
                    <input
                        type="text"
                        placeholder="ค้นหาชื่อหรือรหัส..."
                        class="pl-8 pr-3 py-1.5 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200 w-48"
                    />
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr
                            class="bg-slate-50 border-b border-slate-200 text-[11px] text-slate-500 font-bold uppercase tracking-wider"
                        >
                            <th class="p-4">สมาชิก</th>
                            <th class="p-4">รหัส</th>
                            <th class="hidden p-4 md:table-cell">คณะ</th>
                            <th class="p-4">ประเภท</th>
                            <th class="p-4 text-center">จองทั้งหมด</th>
                            <th class="hidden p-4 sm:table-cell">จองล่าสุด</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs divide-y divide-slate-100 text-slate-600">
                        <tr
                            v-for="m in members"
                            :key="m.id"
                            class="transition-colors hover:bg-slate-50/70"
                        >
                            <td class="p-4">
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="flex items-center justify-center w-8 h-8 rounded-full text-[10px] font-bold text-white bg-gradient-to-tr from-blue-600 to-indigo-500 shrink-0"
                                    >
                                        {{ memberInitials(m.name) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900">{{ m.name }}</div>
                                        <div class="text-[10px] text-slate-400">{{ m.email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 font-mono text-slate-600">{{ m.code }}</td>
                            <td class="hidden p-4 md:table-cell text-slate-500">{{ m.faculty }}</td>
                            <td class="p-4">
                                <span
                                    :class="{
                                        'bg-blue-50 text-blue-800 border-blue-200': m.type === 'นิสิต ป.ตรี',
                                        'bg-indigo-50 text-indigo-800 border-indigo-200': m.type === 'บัณฑิตศึกษา',
                                        'bg-amber-50 text-amber-800 border-amber-200': m.type === 'บุคลากร/อาจารย์',
                                    }"
                                    class="text-[10px] px-2 py-0.5 rounded border font-semibold"
                                    >{{ m.type }}</span
                                >
                            </td>
                            <td class="p-4 font-bold text-center text-slate-900">{{ m.totalBookings }}</td>
                            <td class="hidden p-4 sm:table-cell text-slate-400">{{ m.lastBooking }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
