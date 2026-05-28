<script setup lang="ts">
import type { Booking } from '../../types/admin';

defineProps<{
    bookings: Booking[];
    pendingCount: number;
}>();

const emit = defineEmits<{
    'update-status': [id: number, action: 'approve' | 'reject'];
}>();
</script>

<template>
    <div class="space-y-5">
        <div class="overflow-hidden bg-white border shadow-sm rounded-2xl border-slate-200">
            <div
                class="flex flex-col items-start justify-between gap-3 p-5 border-b border-slate-200 sm:flex-row sm:items-center"
            >
                <div>
                    <h3 class="text-base font-bold text-slate-900">คิวคำขอจองล่าสุด</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        ตรวจสอบ ประเมินสิทธิ์ และดำเนินการอนุมัติหรือปฏิเสธได้ทันที
                    </p>
                </div>
                <span
                    class="text-xs bg-amber-50 border border-amber-200 text-amber-700 px-3 py-1.5 rounded-xl font-bold shrink-0"
                    >{{ pendingCount }} รายการรอดำเนินการ</span
                >
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr
                            class="bg-slate-50 border-b border-slate-200 text-[11px] text-slate-500 font-bold uppercase tracking-wider"
                        >
                            <th class="p-4">ผู้ยื่นคำขอ</th>
                            <th class="p-4">พื้นที่ / ห้อง</th>
                            <th class="p-4">วันและเวลา</th>
                            <th class="p-4 text-center">สถานะ</th>
                            <th class="p-4 text-right">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs divide-y divide-slate-100 text-slate-600">
                        <tr
                            v-for="item in bookings"
                            :key="item.id"
                            class="transition-colors hover:bg-slate-50/70"
                        >
                            <td class="p-4">
                                <div class="font-bold text-slate-900">{{ item.studentName }}</div>
                                <div class="text-[10px] text-slate-400">
                                    {{ item.studentType }} • {{ item.studentId }}
                                </div>
                            </td>
                            <td class="p-4">
                                <span
                                    :class="{
                                        'bg-blue-50 text-blue-900 border-blue-200': item.zone.includes('หลัก'),
                                        'bg-orange-50 text-orange-900 border-orange-200': item.zone.includes('Co-Working'),
                                        'bg-green-50 text-green-900 border-green-200': item.zone.includes('กลุ่ม'),
                                    }"
                                    class="font-bold px-2 py-0.5 rounded text-[10px] border"
                                    >{{ item.zone }}</span
                                >
                                <div class="mt-1 font-semibold text-slate-800">{{ item.roomName }}</div>
                            </td>
                            <td class="p-4">
                                <div>{{ item.date }}</div>
                                <div class="font-bold text-slate-900 mt-0.5">
                                    <i class="mr-1 fa-solid fa-clock text-slate-400"></i>{{ item.timeSlot }}
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <span
                                    v-if="item.status === 'pending'"
                                    class="bg-amber-100 text-amber-800 font-bold px-2.5 py-1 rounded-full text-[10px] inline-flex items-center gap-1 border border-amber-200 animate-pulse"
                                    ><i class="fa-solid fa-circle-pause text-[7px]"></i>รอดำเนินการ</span
                                >
                                <span
                                    v-else-if="item.status === 'approved'"
                                    class="bg-green-100 text-green-800 font-bold px-2.5 py-1 rounded-full text-[10px] inline-flex items-center gap-1 border border-green-200"
                                    ><i class="fa-solid fa-circle-check text-[7px]"></i>อนุมัติสำเร็จ</span
                                >
                                <span
                                    v-else
                                    class="bg-red-100 text-red-800 font-bold px-2.5 py-1 rounded-full text-[10px] inline-flex items-center gap-1 border border-red-200"
                                    ><i class="fa-solid fa-circle-xmark text-[7px]"></i>ปฏิเสธแล้ว</span
                                >
                            </td>
                            <td class="p-4 text-right">
                                <div v-if="item.status === 'pending'" class="flex justify-end gap-1.5">
                                    <button
                                        @click="emit('update-status', item.id, 'approve')"
                                        class="bg-green-600 hover:bg-green-700 text-white font-bold px-3 py-1.5 rounded-lg text-[10px] transition-all flex items-center gap-1"
                                    >
                                        <i class="fa-solid fa-check"></i> อนุมัติ
                                    </button>
                                    <button
                                        @click="emit('update-status', item.id, 'reject')"
                                        class="bg-red-50 hover:bg-red-100 text-red-600 font-bold px-3 py-1.5 rounded-lg text-[10px] border border-red-200 transition-all"
                                    >
                                        ปฏิเสธ
                                    </button>
                                </div>
                                <span v-else class="text-[10px] text-slate-400">ดำเนินการแล้ว</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
