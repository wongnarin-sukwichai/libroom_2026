<script setup lang="ts">
import { computed } from 'vue';
import type { Room } from '../../types/admin';

const props = defineProps<{
    rooms: Room[];
}>();

const emit = defineEmits<{
    'toggle-room': [id: number];
}>();

const groupedRooms = computed(() => {
    const zones = [
        "โซนอาคารหลัก (ชั้น 1)",
        "โซน Co-Working Space",
        "โซนห้องศึกษากลุ่ม (ชั้น 3-4)",
    ];
    return zones.map((z) => ({
        name: z,
        items: props.rooms.filter((r) => r.zone === z),
    }));
});
</script>

<template>
    <div class="space-y-5">
        <div class="p-5 text-white bg-gradient-to-r from-blue-900 to-indigo-900 rounded-2xl">
            <h3 class="text-base font-bold">จัดการพื้นที่ห้องบริการ (Location / Zone / Room)</h3>
            <p class="mt-1 text-xs text-slate-200">
                เปิดหรือปิดปรับปรุงชั่วคราวแต่ละพื้นที่ย่อย
                การเปลี่ยนแปลงมีผลทันทีบนหน้าจองของผู้ใช้
            </p>
        </div>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
            <div
                v-for="group in groupedRooms"
                :key="group.name"
                class="p-5 space-y-4 bg-white border shadow-sm rounded-2xl border-slate-200"
            >
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h4 class="text-sm font-bold text-slate-900">{{ group.name }}</h4>
                    <span
                        class="text-[10px] font-bold"
                        :class="group.items.some((r) => r.active) ? 'text-green-600' : 'text-red-500'"
                    >
                        <i class="fa-solid fa-circle text-[8px] mr-1"></i
                        >{{ group.items.filter((r) => r.active).length }}/{{ group.items.length }} เปิด
                    </span>
                </div>
                <div class="space-y-3">
                    <div
                        v-for="room in group.items"
                        :key="room.id"
                        class="flex items-center justify-between gap-2 text-xs"
                    >
                        <span class="leading-tight text-slate-700">{{ room.name }}</span>
                        <button
                            @click="emit('toggle-room', room.id)"
                            :class="
                                room.active
                                    ? 'bg-green-600 hover:bg-green-700'
                                    : 'bg-slate-400 hover:bg-slate-500'
                            "
                            class="text-white text-[10px] px-2.5 py-1 rounded font-bold transition-colors shrink-0 min-w-[72px]"
                        >
                            {{ room.active ? "เปิดอยู่" : "ปิดอยู่" }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
