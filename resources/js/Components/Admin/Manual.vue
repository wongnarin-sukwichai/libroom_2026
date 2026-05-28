<script setup lang="ts">
import { ref } from 'vue';

const openSections = ref<number[]>([0]);

const toggle = (idx: number) => {
    if (openSections.value.includes(idx)) {
        openSections.value = openSections.value.filter((i) => i !== idx);
    } else {
        openSections.value.push(idx);
    }
};

const sections = [
    {
        icon: 'fa-calendar-plus',
        title: 'ขั้นตอนการจองห้องสำหรับนิสิต',
        content: [
            'เข้าสู่ระบบด้วย Google Account มหาวิทยาลัย (@msu.ac.th)',
            'เลือกโซนพื้นที่ที่ต้องการ จากนั้นเลือกห้องย่อยที่ต้องการ',
            'เลือกวันที่และช่วงเวลา (สล็อตละ 1 ชั่วโมง สูงสุด 3 ชั่วโมง/วัน)',
            'ยืนยันการจอง รอการอนุมัติจากเจ้าหน้าที่ภายใน 15 นาที',
            'เช็คอินที่จุดบริการภายใน 15 นาทีหลังเวลาเริ่มต้น',
        ],
    },
    {
        icon: 'fa-shield-halved',
        title: 'กฎระเบียบการใช้พื้นที่',
        content: [
            'ใช้เพื่อกิจกรรมทางวิชาการและการเรียนรู้เท่านั้น',
            'ห้ามนำอาหารที่มีกลิ่นแรงเข้าในพื้นที่บริการ',
            'รักษาความสะอาดและความเรียบร้อยทุกครั้ง',
            'ห้ามส่งเสียงดังรบกวนผู้ใช้บริการท่านอื่น',
            'คืนกุญแจและอุปกรณ์ให้ครบก่อนออกจากพื้นที่',
        ],
    },
    {
        icon: 'fa-rotate-right',
        title: 'การเช็คอินและยกเลิกการจอง',
        content: [
            'เช็คอินได้ที่เคาน์เตอร์บริการหรือผ่าน QR Code หน้าห้อง',
            'หากไม่เช็คอินภายใน 15 นาที ระบบจะยกเลิกอัตโนมัติ',
            'ยกเลิกล่วงหน้าได้อย่างน้อย 30 นาทีก่อนเวลาจอง',
            'การยกเลิกซ้ำเกิน 3 ครั้ง/เดือน อาจถูกระงับสิทธิ์ชั่วคราว',
        ],
    },
    {
        icon: 'fa-circle-question',
        title: 'คำถามที่พบบ่อย (FAQ)',
        content: [
            'Q: จองได้กี่ชั่วโมงต่อวัน? — A: สูงสุด 3 ชั่วโมง/วัน/ห้อง',
            'Q: การจองกลุ่มทำอย่างไร? — A: ผู้จองหลักสร้างการจองและแชร์ QR Code ให้สมาชิกกลุ่ม',
            'Q: ลืมเช็คอินทำอย่างไร? — A: ติดต่อเจ้าหน้าที่เคาน์เตอร์ภายในเวลาให้บริการ',
            'Q: จองซ้ำในเวลาเดิมได้ไหม? — A: ไม่ได้ ระบบจะตรวจสอบความซ้ำซ้อนอัตโนมัติ',
        ],
    },
];
</script>

<template>
    <div class="space-y-5">
        <div
            class="flex flex-wrap items-center justify-between gap-3 p-5 text-white bg-gradient-to-r from-blue-900 to-indigo-900 rounded-2xl"
        >
            <div>
                <h3 class="text-base font-bold">คู่มือการใช้งานระบบ LibRoom</h3>
                <p class="mt-1 text-xs text-slate-200">
                    เอกสารอ้างอิงสำหรับผู้ดูแลระบบและนิสิตผู้ใช้บริการ
                </p>
            </div>
            <button
                class="flex items-center gap-2 px-4 py-2 text-xs font-bold text-white transition-colors border bg-white/10 hover:bg-white/20 border-white/20 rounded-xl"
            >
                <i class="text-red-300 fa-solid fa-file-pdf"></i>
                ดาวน์โหลด PDF
            </button>
        </div>
        <div class="space-y-3">
            <div
                v-for="(section, idx) in sections"
                :key="idx"
                class="overflow-hidden bg-white border shadow-sm rounded-2xl border-slate-200"
            >
                <button
                    @click="toggle(idx)"
                    class="flex items-center justify-between w-full p-5 text-left transition-colors hover:bg-slate-50"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex items-center justify-center w-8 h-8 text-xs text-blue-700 rounded-lg bg-blue-50 shrink-0"
                        >
                            <i :class="`fa-solid ${section.icon}`"></i>
                        </div>
                        <span class="text-sm font-bold text-slate-900">{{ section.title }}</span>
                    </div>
                    <i
                        class="text-xs transition-transform fa-solid text-slate-400"
                        :class="openSections.includes(idx) ? 'fa-chevron-up' : 'fa-chevron-down'"
                    ></i>
                </button>
                <div v-show="openSections.includes(idx)" class="px-5 pb-5">
                    <ul class="space-y-2">
                        <li
                            v-for="(item, i) in section.content"
                            :key="i"
                            class="flex items-start gap-2.5 text-xs text-slate-600"
                        >
                            <i class="fa-solid fa-circle-dot text-blue-300 text-[9px] mt-1 shrink-0"></i>
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
