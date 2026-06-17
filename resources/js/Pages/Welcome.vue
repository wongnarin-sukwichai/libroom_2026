<script setup>
import { usePage, router } from "@inertiajs/vue3";
import { ref, computed, onMounted, watch } from "vue";

const props = defineProps({
    locations:      Array,
    todayIsHoliday: { type: Boolean, default: false },
    todayDate:      { type: String,  default: () => new Date().toISOString().split('T')[0] },
});

// คืนชื่อ location ตามภาษาปัจจุบัน
const locTitle = (loc) =>
    currentLang.value === 'en'
        ? (loc?.title_eng ?? loc?.title)
        : loc?.title;

const locationIcons = ['fa-graduation-cap', 'fa-laptop-code', 'fa-users-gear'];

const currentLocation = computed(() => props.locations?.[activeArea.value - 1]);
const currentZones    = computed(() => currentLocation.value?.zones ?? []);
const zoneTitle  = (zone) => currentLang.value === 'en' ? (zone?.title_eng ?? zone?.title) : zone?.title;
const zoneDetail = (zone) => zone?.detail ?? '';
import Swal from "sweetalert2";

// --- 1. ระบบจัดการเปลี่ยนภาษา (Localization Dictionary) ---
const currentLang = ref("th");

const translations = {
    th: {
        login: "เข้าสู่ระบบ",
        logout: "ออกจากระบบ",
        navRules: "ข้อปฏิบัติการใช้งาน",
        navManual: "คู่มือการใช้งาน",
        navFeedback: "แบบประเมินความพึงพอใจ",
        quickStatTitle: "ประกาศสำคัญ / Important",
        ann1: "กรุณาเข้าเช็คอินภายใน 15 นาทีหลังจากเวลาที่จอง",
        ann2: "การจองห้องใช้เพื่อกิจกรรมทางวิชาการและการเรียนรู้เท่านั้น",
        ann3: "ขอสงวนสิทธิ์การใช้งาน หากไม่ปฏิบัติตามข้อตกลง",
        mainTitle: "เลือกพื้นที่ให้บริการจอง",
        mainSub:
            "ท่านสามารถเลือกโซนพื้นที่ที่ต้องการใช้งาน เพื่อดูห้องย่อย ค้นหารายละเอียด และทำรายการจองออนไลน์ได้ทันที",
        activeArea: "พร้อมให้บริการ",
        btnBook: "รายละเอียด",
        btnUnavailable: "เต็มแล้ว",
        statArea: "พื้นที่ใหญ่ให้บริการ",
        statRoom: "ห้อง/คอกย่อยทั้งหมด",
        statDaily: "ผู้ใช้งานต่อวันโดยเฉลี่ย",
        statSatisfaction: "ความพึงพอใจการใช้งาน",
        footerName: "สำนักวิทยบริการ มหาวิทยาลัยมหาสารคาม",
        footerQuickLink: "ลิงก์ที่เป็นประโยชน์",
        footerSocial: "โซเชียลมีเดีย / การติดต่อ",
        footerDeveloped: "ระบบการจองพื้นที่และบริการออนไลน์เวอร์ชัน 2",
        cookieTitle: "การยินยอมเพื่อใช้คุกกี้ (Cookie Consent)",
        cookieDesc:
            "เว็บไซต์นี้ใช้คุกกี้เพื่อวัตถุประสงค์ในการจองพื้นที่ จัดเก็บข้อมูลผู้ใช้และอำนวยความสะดวกในการบริการ ท่านยินยอมให้เราเก็บประวัติการจองและบันทึกล็อกอินในระบบเพื่อประสิทธิภาพสูงสุดตามนโยบายคุ้มครองข้อมูลส่วนบุคคล (PDPA)",
        cookieAccept: "ยอมรับทั้งหมด",
        cookieDecline: "ปฏิเสธ",
        rulesHeader: "ระเบียบและข้อปฏิบัติในการเข้าใช้บริการพื้นที่",
        rulesNotice:
            "กรุณาปฏิบัติตามกฎกติกาอย่างเคร่งครัดเพื่อความเป็นระเบียบและบรรยากาศที่ดีสำหรับทุกคน",
        rule1: "ยืนยันการใช้สิทธิ์: ผู้จองห้องจะต้องมารายงานตัวเพื่อเช็คอินที่ตู้อัตโนมัติหรือเคาน์เตอร์บริการภายใน 15 นาทีหลังจากถึงเวลานัด มิเช่นนั้นระบบจะยกเลิกการจองโดยอัตโนมัติ",
        rule2: "จำนวนผู้ใช้จริง: จำนวนผู้เข้าใช้พื้นที่จริงจะต้องสอดคล้องกับขนาดและเงื่อนไขความจุขั้นต่ำของแต่ละห้อง",
        rule3: "การดูแลทรัพย์สิน: ห้ามวางทรัพย์สินของมีค่าทิ้งไว้โดยไม่มีผู้ดูแล และโปรดใช้อุปกรณ์ไอที โต๊ะ เก้าอี้ สมาร์ททีวีของห้องสมุดด้วยความระมัดระวัง",
        rule4: "การรักษาความสะอาด: ห้ามนำอาหาร ของว่าง และเครื่องดื่ม (ยกเว้นน้ำดื่มบรรจุขวดปิดฝาสนิท) เข้ามาในห้องเด็ดขาด และโปรดนำขยะออกไปทิ้งหลังเสร็จการใช้งาน",
        rule5: "การใช้เสียง: รักษาความสงบเรียบร้อย งดการเปิดเสียงเพลงหรือส่งเสียงดังรบกวนบุคคลรอบข้าง ยกเว้นในโซนกิจกรรมผ่อนคลายที่อนุญาต",
        btnClose: "ตกลงและรับทราบ",
        manualHeader: "ขั้นตอนและคู่มือการจองพื้นที่ออนไลน์",
        step1: "เข้าสู่ระบบ",
        step1Desc: "เข้าสู่ระบบโดยระบุ MSU Account ของท่านด้านบนสุดขวาของเว็บ",
        step2: "เลือกห้องที่ชอบ",
        step2Desc: "เลือกพื้นที่และห้องบริการที่สอดคล้องกับการใช้งานและกดจอง",
        step3: "กำหนดวันเวลา",
        step3Desc: "กำหนดวันและระบุช่วงเวลาให้ถูกต้อง จากนั้นยืนยันการจอง",
        manualTipsTitle: "ข้อแนะนำเพิ่มเติม:",
        manualTip1: "ท่านสามารถทำการจองล่วงหน้าได้ไม่เกิน 7 วันทำการ",
        manualTip2:
            "นิสิตแต่ละท่านสามารถสร้างการจองได้เพียง 1 รายการในช่วงเวลาเดียวกัน",
        manualTip3:
            "สามารถจัดการหรือกดยกเลิกการจองได้ผ่านหน้าระวัติของฉันหลังจากล็อกอิน",
        evaluationHeader: "แบบประเมินความพึงพอใจการใช้บริการ",
        evalDesc:
            "ความคิดเห็นของท่านมีคุณค่ามากในการพัฒนาคุณภาพและปรับปรุงบริการให้ดียิ่งขึ้น",
        evalTopic: "หัวข้อที่ประเมิน / Service Topic",
        evalRating: "ระดับความพึงพอใจ / Service Rating",
        evalComments: "ข้อเสนอแนะเพิ่มเติม / Other Suggestions",
        evalSubmit: "ส่งคำประเมิน",
        ratingExcellent: "ดีเยี่ยม",
        ratingGood: "ดี",
        ratingModerate: "ปานกลาง",
        ratingFair: "ต้องปรับปรุง",
        bookConfirmHeader: "ระบุเวลาที่ต้องการจอง",
        bookingRoomLabel: "พื้นที่ที่คุณกำลังจอง:",
        bookingLoginAlert:
            "ขออภัย คุณจำเป็นต้องเข้าสู่ระบบก่อนทำการจองห้อง กรุณากดปุ่มด้านล่างนี้เพื่อล็อกอินก่อนทำรายการ",
        bookDate: "ระบุวันที่ประสงค์จอง",
        bookTerms:
            "ฉันขอยืนยันว่าจะมาเช็คอินเข้าใช้ห้องตรงเวลา และจะรักษาความเป็นระเบียบเรียบร้อยภายในพื้นที่บริการเป็นอย่างดี",
        btnConfirmComplete: "ยืนยันทำรายการจอง",
    },
    en: {
        login: "Sign In",
        logout: "Logout",
        navRules: "Rules & Policies",
        navManual: "User Manual",
        navFeedback: "Satisfaction Survey",
        quickStatTitle: "Important Announcement",
        ann1: "Please check-in within 15 minutes of your booked time slot.",
        ann2: "Room bookings are strictly for educational and academic use.",
        ann3: "The library reserves the right to deny service for noise violation.",
        mainTitle: "Select Booking Area",
        mainSub:
            "Choose a primary area from the list to explore and make an instant online reservation.",
        activeArea: "Available for Booking",
        btnBook: "Book Now",
        btnUnavailable: "Fully Booked",
        statArea: "Active Study Zones",
        statRoom: "Total Active Pods",
        statDaily: "Avg. Daily Visitors",
        statSatisfaction: "Satisfaction Index",
        footerName: "Mahasarakham University Academic Resource Center",
        footerQuickLink: "Useful Links",
        footerSocial: "Social Medias / Contact Us",
        footerDeveloped: "Study Space Online Booking Ver 2.5",
        cookieTitle: "Cookie Consent Information",
        cookieDesc:
            "We use cookies to enhance booking flow, track state session and deliver superior user experience in compliance with Personal Data Protection Act (PDPA) policies.",
        cookieAccept: "Accept All",
        cookieDecline: "Decline",
        rulesHeader: "Rules and Conditions of Use",
        rulesNotice:
            "Please behave ethically and strictly follow regulations to maintain a positive study environment.",
        rule1: "Verification: Users must check-in at the kiosk or service counter within 15 minutes of scheduled time. Automatic cancellation applies.",
        rule2: "Attendance: Physical users in the room must meet the minimum capacity requirement of the reserved room.",
        rule3: "Properties: Valuables should not be left unattended. Use all technical apparatuses with utmost care.",
        rule4: "Cleanliness: Strictly no external meals, snacks or soft drinks. Pure bottled water is permitted.",
        rule5: "Sound Limits: Keep voices down. Avoid noisy behaviors unless explicitly in high-vocal activity zones.",
        btnClose: "Acknowledge",
        manualHeader: "How to Reservate Room Online",
        step1: "Authentication",
        step1Desc:
            "Sign in using your MSU Account credentials on the top-right button.",
        step2: "Pick Space",
        step2Desc:
            "Navigate through zones and select a room matching your desired capacity.",
        step3: "Confirm Booking",
        step3Desc:
            "Specify date and timeframe of your booking, accept regulations, and confirm.",
        manualTipsTitle: "Important Guidelines:",
        manualTip1: "Room bookings can be made up to 7 days in advance.",
        manualTip2:
            "Each user is restricted to a single pending booking list at any one time.",
        manualTip3:
            "Cancel or manage reservations inside 'My Bookings' screen on your account.",
        evaluationHeader: "Satisfaction Evaluation Survey",
        evalDesc:
            "Your feedback is highly valued to improve library services and digital systems.",
        evalTopic: "Service Topic",
        evalRating: "Service Rating",
        evalComments: "Other Suggestions",
        evalSubmit: "Submit Review",
        ratingExcellent: "Excellent",
        ratingGood: "Good",
        ratingModerate: "Moderate",
        ratingFair: "Needs Improvement",
        bookConfirmHeader: "Specify Booking Schedule",
        bookingRoomLabel: "Target Room Name:",
        bookingLoginAlert:
            "Sorry, you must authenticate first. Click the sign-in button below to perform a quick login.",
        bookDate: "Choose Date",
        bookTerms:
            "I promise to arrive on schedule, respect other guests and maintain the tidiness of library spaces.",
        btnConfirmComplete: "Complete Booking",
    },
};

const t = (key) => {
    return (
        translations[currentLang.value]?.[key] ||
        translations["th"]?.[key] ||
        key
    );
};

const changeLanguage = (lang) => {
    currentLang.value = lang;
};

// --- 2. การควบคุมแท็บเลือกพื้นที่ ---
const activeArea = ref(1);

const switchArea = (areaId) => {
    activeArea.value = areaId;
};

// --- 3. ระบบควบคุมการเปิด/ปิด Modals ---
const modals = ref({
    rules: false,
    manual: false,
    evaluation: false,
    booking: false,
    joinShare: false,
});

const openModal = (name) => {
    modals.value[name] = true;
};

const closeModal = (name) => {
    modals.value[name] = false;
};


// --- 4. การจัดการสถานะ Auth ---
const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);

const handleLogout = async () => {
    const result = await Swal.fire({
        title: currentLang.value === "th" ? "ออกจากระบบ?" : "Sign Out?",
        text:
            currentLang.value === "th"
                ? "คุณต้องการออกจากระบบใช่หรือไม่"
                : "Are you sure you want to sign out?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#dc2626",
        cancelButtonColor: "#64748b",
        confirmButtonText:
            currentLang.value === "th" ? "ออกจากระบบ" : "Sign Out",
        cancelButtonText:
            currentLang.value === "th" ? "ยกเลิก" : "Cancel",
        reverseButtons: true,
    });

    if (result.isConfirmed) {
        router.post("/logout");
    }
};

// --- 5. ข้อมูลการจองห้อง (Booking System) ---
const selectedZone = ref(null);
const selectedRoom = ref(null);
const availableTimes = ref([]);
const bookedTimeIds = ref([]);
const isFetchingSlots = ref(false);

const bookingForm = ref({
    date:            "",
    selectedTimeIds: [],
    terms:           false,
});
const usedHoursToday = ref(0);
const dailyQuota     = ref(3);

const initiateBooking = (zone) => {
    selectedZone.value = zone;
    selectedRoom.value = zone.rooms?.length === 1 ? zone.rooms[0] : null;
    bookingForm.value  = { date: props.todayDate, selectedTimeIds: [], terms: false };
    availableTimes.value = [];
    bookedTimeIds.value  = [];
    usedHoursToday.value = 0;
    openModal("booking");
};

const fetchSlots = async () => {
    if (!selectedRoom.value || !bookingForm.value.date) return;
    isFetchingSlots.value = true;
    try {
        const res  = await fetch(`/rooms/${selectedRoom.value.id}/slots?date=${bookingForm.value.date}`);
        const data = await res.json();
        availableTimes.value              = data.times;
        bookedTimeIds.value               = data.booked_ids;
        usedHoursToday.value              = data.used_hours  ?? 0;
        dailyQuota.value                  = data.daily_quota ?? 3;
        bookingForm.value.selectedTimeIds = [];
    } finally {
        isFetchingSlots.value = false;
    }
};

watch(selectedRoom, () => fetchSlots());

const totalQuota = computed(() => dailyQuota.value);
const quota      = computed(() => Math.max(0, totalQuota.value - usedHoursToday.value));

const getSlotState = (timeId) => {
    if (bookedTimeIds.value.includes(timeId)) return 'booked';

    const ids = bookingForm.value.selectedTimeIds;
    if (ids.includes(timeId)) return 'selected';

    if (ids.length >= quota.value) return 'dim';

    // ถ้ามี selection อยู่แล้ว → dim slot ที่ไม่ต่อเนื่อง
    if (ids.length > 0) {
        const sorted = [...ids].sort((a, b) => a - b);
        const min = sorted[0], max = sorted[sorted.length - 1];
        if (timeId !== min - 1 && timeId !== max + 1) return 'dim';
    }

    return 'available';
};

const selectSlot = (timeId) => {
    if (bookedTimeIds.value.includes(timeId)) return;

    const ids    = [...bookingForm.value.selectedTimeIds].sort((a, b) => a - b);
    const q      = quota.value;

    if (ids.includes(timeId)) {
        if (timeId === ids[ids.length - 1]) {
            bookingForm.value.selectedTimeIds = ids.slice(0, -1);
        } else if (timeId === ids[0]) {
            bookingForm.value.selectedTimeIds = ids.slice(1);
        } else {
            bookingForm.value.selectedTimeIds = ids.slice(0, ids.indexOf(timeId));
        }
        return;
    }

    if (ids.length === 0) { bookingForm.value.selectedTimeIds = [timeId]; return; }

    const min = ids[0], max = ids[ids.length - 1];

    if (timeId === max + 1 && ids.length < q) {
        bookingForm.value.selectedTimeIds = [...ids, timeId]; return;
    }
    if (timeId === min - 1 && ids.length < q) {
        bookingForm.value.selectedTimeIds = [timeId, ...ids]; return;
    }

    // non-adjacent หรือ quota เต็ม → reset
    bookingForm.value.selectedTimeIds = [timeId];
};

const pad = (h) => String(h).padStart(2, '0');

const bookingSummary = computed(() => {
    const ids = [...bookingForm.value.selectedTimeIds].sort((a, b) => a - b);
    if (!ids.length) return null;
    const start = ids[0], end = ids[ids.length - 1];
    return {
        ids,
        start: `${pad(start)}:00`,
        end:   `${pad(end + 1)}:00`,
        hours: ids.length,
        title: `${pad(start)}:00 – ${pad(end + 1)}:00 น.`,
    };
});

const isSubmitting  = ref(false);
const joinUrl       = ref(null);
const joinCapacity  = ref({ need: 0, current: 1 });

const handleBookingSubmit = async () => {
    if (!bookingSummary.value || isSubmitting.value) return;

    isSubmitting.value = true;
    try {
        const res = await fetch('/bookings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({
                room_id:  selectedRoom.value.id,
                time_ids: bookingForm.value.selectedTimeIds,
            }),
        });

        const json = await res.json();

        if (!res.ok) {
            showToast(
                currentLang.value === 'th' ? 'ไม่สามารถจองได้' : 'Booking Failed',
                json.message ?? 'เกิดข้อผิดพลาด',
                true,
            );
            return;
        }

        closeModal('booking');

        // manual room ที่ต้องการเพื่อนเพิ่ม → แสดง modal แชร์ลิงก์
        if (json.join_url) {
            joinUrl.value      = json.join_url;
            joinCapacity.value = { need: json.min_capacity, current: json.member_count };
            openModal('joinShare');
            await fetchSlots();
            return;
        }

        const isAuto = json.confirm_type === 'auto';
        showToast(
            currentLang.value === 'th' ? 'จองสำเร็จ!' : 'Booking Complete!',
            currentLang.value === 'th'
                ? `${selectedRoom.value.title} • ${bookingSummary.value.start}–${bookingSummary.value.end} น. (${bookingSummary.value.hours} ชม.) ${isAuto ? '✓ ยืนยันแล้ว' : '— รอเจ้าหน้าที่ยืนยัน'}`
                : `${selectedRoom.value.title} • ${bookingSummary.value.start}–${bookingSummary.value.end} (${bookingSummary.value.hours}h)`,
        );

        await fetchSlots();

    } catch {
        showToast('เกิดข้อผิดพลาด', 'กรุณาลองใหม่อีกครั้ง', true);
    } finally {
        isSubmitting.value = false;
    }
};

const copyJoinUrl = () => {
    if (!joinUrl.value) return;
    navigator.clipboard.writeText(joinUrl.value);
    showToast('คัดลอกแล้ว', 'ลิงก์พร้อมส่งให้เพื่อนแล้ว');
};

// --- 6. ฟอร์มประเมินความพึงพอใจ ---
const evaluationForm = ref({
    topic: "1",
    rating: "5",
    comments: "",
});

const handleEvaluationSubmit = () => {
    closeModal("evaluation");
    showToast(
        currentLang.value === "th" ? "ส่งผลประเมินเรียบร้อย" : "Feedback Sent",
        currentLang.value === "th"
            ? "ขอบพระคุณสำหรับข้อมูลเพื่อการพัฒนาที่ดียิ่งขึ้น"
            : "Thank you very much for your feedback!",
    );
    // รีเซ็ตฟอร์ม
    evaluationForm.value = {
        topic: "1",
        rating: "5",
        comments: "",
    };
};

// --- 7. การยินยอม Cookie (PDPA) ---
const showCookieConsent = ref(true);

onMounted(() => {
    const consent = sessionStorage.getItem("cookieConsent");
    if (consent) {
        showCookieConsent.value = false;
    }
});

const handleCookieConsent = (decision) => {
    sessionStorage.setItem("cookieConsent", decision);
    showCookieConsent.value = false;

    if (decision === "accepted") {
        showToast(
            currentLang.value === "th"
                ? "ยินยอมนโยบายคุกกี้"
                : "Cookie Policy Accepted",
            currentLang.value === "th"
                ? "ขอบคุณที่ให้การยินยอมในการใช้บริการแพลตฟอร์ม"
                : "Thank you for accepting our cookie statement.",
        );
    }
};

// --- 8. ระบบ Toast Notification ---
const toast = ref({
    show: false,
    title: "",
    desc: "",
    isError: false,
});
let toastTimeout = null;

const showToast = (title, desc, isError = false) => {
    toast.value = {
        show: true,
        title,
        desc,
        isError,
    };

    clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        hideToast();
    }, 6000);
};

const hideToast = () => {
    toast.value.show = false;
};
</script>

<template>
    <div
        class="flex flex-col min-h-screen font-sans bg-slate-50 text-slate-800"
    >
        <!-- แถบด้านบนสุด (Top Utility Bar) -->
        <div
            class="px-4 py-2 text-xs text-white shadow-sm bg-gradient-to-r from-rose-400 via-fuchsia-500 to-indigo-500"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-2 mx-auto max-w-7xl"
            >
                <div class="flex flex-wrap items-center gap-4">
                    <span class="flex items-center gap-1">
                        <i class="fa-solid fa-phone"></i> 0-4375-4322-40 ต่อ
                        2405, 2491
                    </span>
                    <span class="items-center hidden gap-1 md:inline-flex"
                        >|</span
                    >
                    <span class="flex items-center gap-1">
                        <i class="fa-solid fa-envelope"></i> library@msu.ac.th
                    </span>
                    <span class="items-center hidden gap-1 md:inline-flex"
                        >|</span
                    >
                    <span
                        class="flex items-center gap-1 font-semibold text-amber-400"
                    >
                        <i class="fa-solid fa-clock"></i> เปิดบริการ 24 ชั่วโมง
                        (24 Hours)
                    </span>
                </div>
                <!-- สลับภาษา & ล็อกอิน -->
                <div class="flex items-center gap-4">
                    <div
                        class="flex items-center overflow-hidden rounded bg-blue-950"
                    >
                        <button
                            @click="changeLanguage('th')"
                            :class="
                                currentLang === 'th'
                                    ? 'bg-white text-slate-900'
                                    : 'text-white hover:bg-blue-800'
                            "
                            class="px-2.5 py-1 font-bold text-[11px] transition-all"
                        >
                            TH
                        </button>
                        <button
                            @click="changeLanguage('en')"
                            :class="
                                currentLang === 'en'
                                    ? 'bg-white text-slate-900'
                                    : 'text-white hover:bg-blue-800'
                            "
                            class="px-2.5 py-1 font-bold text-[11px] transition-all"
                        >
                            EN
                        </button>
                    </div>
                    <div v-if="!authUser">
                        <a
                            href="/libroom/auth/google"
                            class="bg-white hover:bg-blue-950 text-slate-950 hover:text-white font-semibold px-3 py-1 rounded shadow transition-all flex items-center gap-1 text-[11px] md:text-xs"
                        >
                            <i class="fa-brands fa-google"></i>
                            <span>{{ t("login") }}</span>
                        </a>
                    </div>
                    <div v-else class="flex items-center gap-2">
                        <span class="text-xs font-medium text-amber-400">
                            <i class="fa-solid fa-circle-user"></i>
                            <span class="ml-1">{{ authUser.name }}</span>
                        </span>
                        <a href="/my-bookings"
                            class="bg-white/20 hover:bg-white/30 text-white px-2 py-0.5 rounded text-[10px] transition-all flex items-center gap-1">
                            <i class="fa-solid fa-calendar-check"></i>
                            <span>การจองของฉัน</span>
                        </a>
                        <button
                            @click="handleLogout"
                            class="bg-red-600 hover:bg-red-700 text-white px-2 py-0.5 rounded text-[10px] transition-all"
                        >
                            <span>{{ t("logout") }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ส่วนหัวของเว็บไซต์ (Header & Banner Design) -->
        <header
            class="sticky top-0 z-40 bg-white border-b shadow-sm border-slate-200"
        >
            <div
                class="flex flex-col items-center justify-between gap-4 px-4 py-3 mx-auto max-w-7xl md:flex-row"
            >
                <!-- โลโก้ และ ชื่อสำนักวิทยบริการ -->
                <div class="flex items-center gap-3">
                    <div
                        class="flex items-center p-2 text-white rounded-lg shadow bg-gradient-to-r from-rose-400 via-fuchsia-500 to-indigo-500"
                    >
                        <div
                            class="pr-2 mr-2 text-xs font-bold text-center border-r border-white"
                        >
                            <div class="text-lg leading-none text-amber-400">
                                MSU
                            </div>
                            <div
                                class="text-[9px] tracking-widest text-slate-300"
                            >
                                LIBRARY
                            </div>
                        </div>
                        <div>
                            <h1
                                class="text-sm font-bold leading-tight tracking-wide text-white md:text-base font-prompt"
                            >
                                STUDY ROOM SERVICE
                            </h1>
                            <p
                                class="text-[10px] text-amber-400 font-medium tracking-wider"
                            >
                                ACADEMIC RESOURCE CENTER MSU
                            </p>
                        </div>
                    </div>
                </div>

                <!-- เมนูหลักตามที่โจทย์กำหนด (ข้อปฏิบัติ, คู่มือ, ประเมินความพึงพอใจ) -->
                <nav
                    class="flex flex-wrap items-center gap-2 text-xs md:gap-4 md:text-sm"
                >
                    <button
                        @click="openModal('rules')"
                        class="flex items-center gap-1.5 px-3 py-2 text-slate-600 hover:text-blue-900 hover:bg-slate-100 rounded-lg transition-all font-medium"
                    >
                        <i class="text-blue-600 fa-solid fa-file-shield"></i>
                        <span>{{ t("navRules") }}</span>
                    </button>
                    <a
                        href="/pdf/tools.pdf"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-1.5 px-3 py-2 text-slate-600 hover:text-blue-900 hover:bg-slate-100 rounded-lg transition-all font-medium"
                    >
                        <i class="text-orange-500 fa-solid fa-book-open"></i>
                        <span>{{ t("navManual") }}</span>
                    </a>
                    <a
                        href="https://docs.google.com/forms/d/e/1FAIpQLSfG97U9yb9PcTXM3ORInGrNUfqQi3TYbxcsj7Y320h8QEEs7w/viewform?usp=dialog"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-1.5 px-3 py-2 text-slate-600 hover:text-blue-900 hover:bg-slate-100 rounded-lg transition-all font-medium"
                    >
                        <i
                            class="text-green-600 fa-solid fa-square-poll-vertical"
                        ></i>
                        <span>{{ t("navFeedback") }}</span>
                    </a>
                </nav>
            </div>
        </header>

        <!-- ฮีโร่แบนเนอร์จำลองตามสีสไตล์ของแบนเนอร์จริง -->
        <section
            class="relative px-4 py-8 mt-8 overflow-hidden text-white md:py-12"
            style="
                background: url(&quot;/imgs/banner.jpg&quot;) center/cover
                    no-repeat;
                background-size: 55%;
            "
        >
            <div
                class="absolute inset-0 opacity-10 mix-blend-overlay bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:16px_16px]"
            ></div>
            <div
                class="relative z-10 grid items-center grid-cols-1 gap-6 mx-auto max-w-7xl lg:grid-cols-12"
            >
                <div class="space-y-4 lg:col-span-7"></div>
                <!-- ประกาศสำคัญด้านข้าง -->
                <div
                    class="p-6 text-center border shadow-xl bg-white/90 lg:col-span-5 rounded-2xl border-white/50 md:text-left"
                >
                    <h3
                        class="mb-3 text-base font-bold text-amber-500 font-prompt"
                    >
                        <i class="mr-1 fa-solid fa-bullhorn"></i>
                        <span>{{ t("quickStatTitle") }}</span>
                    </h3>
                    <ul
                        class="text-xs text-gray-900 space-y-2.5 list-disc list-inside"
                    >
                        <li>{{ t("ann1") }}</li>
                        <li>{{ t("ann2") }}</li>
                        <li>{{ t("ann3") }}</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ส่วนเนื้อหาหลัก: พื้นที่ให้บริการ 3 พื้นที่หลัก -->
        <main class="flex-grow w-full px-4 py-8 mx-auto max-w-7xl">
            <!-- หัวข้อหน้าเว็บ -->
            <div class="mb-8 text-center">
                <h2
                    class="flex items-center justify-center gap-2 text-2xl font-extrabold text-blue-900 md:text-3xl font-prompt"
                >
                    <i class="text-orange-500 fa-solid fa-chalkboard-user"></i>
                    <span>{{ t("mainTitle") }}</span>
                </h2>
                <p class="max-w-2xl mx-auto mt-2 text-sm text-slate-500">
                    {{ t("mainSub") }}
                </p>
            </div>

            <!-- แบนเนอร์วันหยุด -->
            <div v-if="props.todayIsHoliday"
                class="flex items-center max-w-2xl gap-4 p-4 mx-auto mb-8 border border-red-200 shadow-sm bg-red-50 rounded-2xl"
            >
                <div class="flex items-center justify-center w-12 h-12 bg-red-100 shrink-0 rounded-xl">
                    <i class="text-xl text-red-500 fa-solid fa-calendar-xmark"></i>
                </div>
                <div>
                    <div class="text-sm font-bold text-red-700">งดให้บริการเนื่องในวันหยุดและวันหยุดนักขัตฤกษ์</div>
                    <div class="text-xs text-red-500 mt-0.5">ไม่สามารถจองใช้บริการได้ในวันนี้ กรุณากลับมาจองในวันทำการถัดไป</div>
                </div>
            </div>

            <!-- แท็บสลับพื้นที่การดูข้อมูล -->
            <div
                class="flex flex-col gap-2 p-2 mb-8 bg-white border shadow-sm rounded-xl border-slate-200 sm:flex-row"
            >
                <button
                    v-for="(loc, i) in locations"
                    :key="loc.id"
                    @click="switchArea(i + 1)"
                    :class="
                        activeArea === i + 1
                            ? 'bg-gradient-to-r from-rose-400 via-fuchsia-500 to-indigo-500 text-white shadow'
                            : 'text-slate-600 hover:bg-slate-100'
                    "
                    class="flex items-center justify-center flex-1 gap-2 px-4 py-3 text-sm font-bold transition-all rounded-lg"
                >
                    <i class="fa-solid" :class="locationIcons[i]"></i>
                    <span>{{ locTitle(loc) }}</span>
                </button>
            </div>

            <Transition name="tab" mode="out-in">
            <div :key="activeArea">

            <!-- location section (unified, driven by currentLocation) -->
            <div v-if="!props.todayIsHoliday" class="space-y-6">
                <div
                    class="flex flex-col items-center justify-between gap-4 p-5 border bg-gradient-to-r rounded-xl md:flex-row"
                    :class="currentLocation?.status === '0'
                        ? 'from-green-50 to-emerald-50 border-green-200/65'
                        : 'from-red-50 to-orange-50 border-red-200/65'"
                >
                    <div>
                        <h3 class="flex items-center gap-2 text-lg font-bold text-slate-900 font-prompt">
                            <span
                                class="w-2.5 h-2.5 rounded-full"
                                :class="currentLocation?.status === '0' ? 'bg-green-500' : 'bg-red-400'"
                            ></span>
                            <span>{{ locTitle(currentLocation) }}</span>
                        </h3>
                        <p class="mt-1 text-xs text-slate-600">{{ currentLocation?.detail }}</p>
                    </div>
                    <div
                        class="text-xs font-bold bg-white px-3 py-1.5 rounded-lg border shadow-sm"
                        :class="currentLocation?.status === '0' ? 'text-green-800 border-green-200' : 'text-red-700 border-red-200'"
                    >
                        <i
                            class="mr-1 fa-solid"
                            :class="currentLocation?.status === '0' ? 'fa-circle-check text-green-500' : 'fa-circle-xmark text-red-500'"
                        ></i>
                        <span>{{ currentLocation?.status === '0' ? t("activeArea") : 'ไม่พร้อมใช้งาน' }}</span>
                    </div>
                </div>

                <div
                    v-if="currentLocation?.status === '0'"
                    class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="zone in currentZones"
                        :key="zone.id"
                        class="flex flex-col overflow-hidden transition-all bg-white border shadow-sm rounded-xl border-slate-200 hover:shadow-md"
                    >
                        <div class="relative flex items-center justify-center h-48 bg-slate-100">
                            <img
                                v-if="zone.pic"
                                :src="`/imgs/zones/${zone.pic}`"
                                :alt="zoneTitle(zone)"
                                class="object-cover w-full h-full"
                            />
                            <i v-else class="text-5xl text-slate-300 fa-solid fa-image"></i>
                            <span class="absolute top-3 left-3 text-white text-xs px-2.5 py-1 rounded-full font-bold"
                                :class="zone.status === '0' ? 'bg-blue-900' : 'bg-red-600'"
                            >{{ zone.status === '0' ? zoneTitle(zone) : 'ปิดให้บริการ' }}</span>
                        </div>
                        <div class="flex flex-col justify-between flex-grow p-5">
                            <div>
                                <h4 class="flex items-center justify-between mb-1 text-base font-bold text-slate-900 font-prompt">
                                    <span>{{ zoneTitle(zone) }}</span>
                                    <span
                                        class="text-xs px-2 py-0.5 rounded border"
                                        :class="zone.status === '0'
                                            ? 'text-green-600 bg-green-50 border-green-200'
                                            : 'text-red-600 bg-red-50 border-red-200'"
                                    ><i class="fa-solid fa-circle text-[6px] mr-1"></i><span>{{ zone.status === '0' ? 'ว่าง' : 'ไม่ว่าง' }}</span></span>
                                </h4>
                                <p class="mb-4 text-xs text-slate-500">{{ zoneDetail(zone) }}</p>
                                <div class="p-3 mb-6 space-y-2 text-xs rounded-lg text-slate-600 bg-slate-50">
                                    <div class="flex justify-between">
                                        <span><i class="fa-solid fa-users mr-1.5 text-slate-400"></i>ความจุ</span>
                                        <span class="font-bold">{{ zone.capacity }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span><i class="fa-solid fa-plug mr-1.5 text-slate-400"></i>สิ่งอำนวยความสะดวก</span>
                                        <span class="font-bold text-right max-w-[55%]">{{ zone.tool }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span><i class="fa-solid fa-clock mr-1.5 text-slate-400"></i>โควต้าต่อวัน</span>
                                        <span class="font-bold">{{ zone.zone_daily_quota ? zone.zone_daily_quota + ' ชม.' : 'ไม่จำกัด' }}</span>
                                    </div>
                                </div>
                            </div>
                            <button
                                @click="initiateBooking(zone)"
                                :disabled="zone.status !== '0'"
                                :class="zone.status === '0' ? 'bg-blue-900 hover:bg-blue-950 text-white' : 'bg-slate-300 text-slate-500 cursor-not-allowed'"
                                class="w-full mt-4 font-bold py-2 px-4 rounded-lg text-xs transition-all flex items-center justify-center gap-1.5"
                            >
                                <i :class="zone.status === '0' ? 'fa-solid fa-calendar-check' : 'fa-solid fa-ban'"></i>
                                <span>{{ zone.status === '0' ? t('btnBook') : t('btnUnavailable') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            </div>
            </Transition>
        </main>

        

        <!-- ข้อมูลสถิติของห้องสมุดภาพรวม -->
        <section
            class="py-10 mt-12 text-white bg-blue-900 border-t-4 border-amber-500"
        >
            <div
                class="grid grid-cols-2 gap-6 px-4 mx-auto text-center max-w-7xl md:grid-cols-4"
            >
                <div class="space-y-1">
                    <div
                        class="text-3xl font-extrabold md:text-4xl text-amber-400 font-prompt"
                    >
                        3
                    </div>
                    <div
                        class="text-xs tracking-wider uppercase text-slate-300"
                    >
                        {{ t("statArea") }}
                    </div>
                </div>
                <div class="space-y-1">
                    <div
                        class="text-3xl font-extrabold md:text-4xl text-amber-400 font-prompt"
                    >
                        48
                    </div>
                    <div
                        class="text-xs tracking-wider uppercase text-slate-300"
                    >
                        {{ t("statRoom") }}
                    </div>
                </div>
                <div class="space-y-1">
                    <div
                        class="text-3xl font-extrabold md:text-4xl text-amber-400 font-prompt"
                    >
                        1,200+
                    </div>
                    <div
                        class="text-xs tracking-wider uppercase text-slate-300"
                    >
                        {{ t("statDaily") }}
                    </div>
                </div>
                <div class="space-y-1">
                    <div
                        class="text-3xl font-extrabold md:text-4xl text-amber-400 font-prompt"
                    >
                        98.2%
                    </div>
                    <div
                        class="text-xs tracking-wider uppercase text-slate-300"
                    >
                        {{ t("statSatisfaction") }}
                    </div>
                </div>
            </div>
        </section>

        <!-- FOOTER ข้อมูลการติดต่อ -->
        <footer class="mt-auto text-white bg-slate-900">
            <div
                class="h-2 bg-gradient-to-r from-blue-700 via-blue-900 to-amber-500"
            ></div>
            <div class="px-4 py-8 mx-auto max-w-7xl">
                <div class="grid grid-cols-1 gap-8 md:grid-cols-12">
                    <!-- คอลัมน์ที่ 1 -->
                    <div class="space-y-4 md:col-span-6">
                        <div class="flex items-center gap-3">
                            <div
                                class="bg-blue-800 p-1.5 rounded text-xs font-bold text-amber-400"
                            >
                                MSU
                            </div>
                            <h4
                                class="text-sm font-bold text-slate-100 font-prompt"
                            >
                                {{ t("footerName") }}
                            </h4>
                        </div>
                        <p
                            class="max-w-md text-xs leading-relaxed text-slate-400"
                        >
                            ต.ขามเรียง อ.กันทรวิชัย จ.มหาสารคาม 44150 <br />
                            โทร : 0-4375-4322-40 ต่อ 2491, 2405
                            <br />
                            แฟกซ์ : 0-4375-4358 <br />
                            อีเมล : library@msu.ac.th
                        </p>
                    </div>

                    <!-- คอลัมน์ที่ 2 -->
                    <div class="space-y-3 md:col-span-3">
                        <h5
                            class="text-xs font-bold tracking-wider uppercase text-amber-400 font-prompt"
                        >
                            {{ t("footerQuickLink") }}
                        </h5>
                        <ul class="space-y-2 text-xs text-slate-400">
                            <li>
                                <a
                                    href="https://library.msu.ac.th"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="transition-colors hover:text-amber-400"
                                    ><i
                                        class="fa-solid fa-chevron-right text-[8px] mr-1"
                                    ></i>
                                    เว็บไซต์สำนักวิทยบริการ</a
                                >
                            </li>
                            <li>
                                <button
                                    @click="openModal('rules')"
                                    class="text-left transition-colors hover:text-amber-400"
                                >
                                    <i
                                        class="fa-solid fa-chevron-right text-[8px] mr-1"
                                    ></i>
                                    <span>{{ t("navRules") }}</span>
                                </button>
                            </li>
                            <li>
                                <a
                                    href="/pdf/tools.pdf"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-left transition-colors hover:text-amber-400"
                                >
                                    <i
                                        class="fa-solid fa-chevron-right text-[8px] mr-1"
                                    ></i>
                                    <span>{{ t("navManual") }}</span>
                                </a>
                            </li>
                            <li>
                                <a
                                    href="https://docs.google.com/forms/d/e/1FAIpQLSfG97U9yb9PcTXM3ORInGrNUfqQi3TYbxcsj7Y320h8QEEs7w/viewform?usp=dialog"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-left transition-colors hover:text-amber-400"
                                >
                                    <i
                                        class="fa-solid fa-chevron-right text-[8px] mr-1"
                                    ></i>
                                    <span>{{ t("navFeedback") }}</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- คอลัมน์ที่ 3 -->
                    <div class="space-y-3 md:col-span-3">
                        <h5
                            class="text-xs font-bold tracking-wider uppercase text-amber-400 font-prompt"
                        >
                            {{ t("footerSocial") }}
                        </h5>
                        <div
                            class="flex flex-col gap-2.5 text-xs text-slate-400"
                        >
                            <a
                                href="#"
                                class="flex items-center gap-2 transition-colors hover:text-green-400"
                            >
                                <i
                                    class="text-lg text-green-500 fa-brands fa-whatsapp"
                                ></i>
                                <span>@msulibrary</span>
                            </a>
                            <a
                                href="#"
                                class="flex items-center gap-2 transition-colors hover:text-blue-400"
                            >
                                <i
                                    class="text-lg text-blue-500 fa-brands fa-facebook"
                                ></i>
                                <span>MSU Academic Resource Center</span>
                            </a>
                            <a
                                href="https://library.msu.ac.th"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center gap-2 transition-colors hover:text-amber-400"
                            >
                                <i
                                    class="text-lg fa-solid fa-globe text-amber-500"
                                ></i>
                                <span>library.msu.ac.th</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ลิขสิทธิ์ @2026 -->
                <div
                    class="flex flex-col items-center justify-between gap-4 pt-4 mt-8 text-xs border-t border-slate-800 sm:flex-row text-slate-500"
                >
                    <p>
                        &copy; 2026 สำนักวิทยบริการ มหาวิทยาลัยมหาสารคาม.
                        สงวนลิขสิทธิ์ทั้งหมด.
                    </p>
                    <p class="text-[10px]">{{ t("footerDeveloped") }}</p>
                </div>
            </div>
        </footer>

        <!-- ================= MODALS SECTION ================= -->

        <!-- 1. คุกกี้ ยอมรับนโยบายความเป็นส่วนตัว (Cookie Consent Bar) -->
        <Transition name="fade">
        <div
            v-if="showCookieConsent"
            class="fixed inset-x-0 bottom-0 z-50 p-4 text-white border-t shadow-2xl bg-slate-900/95 backdrop-blur-md border-slate-800"
        >
            <div
                class="flex flex-col items-center justify-between gap-4 mx-auto max-w-7xl md:flex-row"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="bg-amber-500/10 text-amber-400 p-2 rounded-lg mt-0.5"
                    >
                        <i class="text-lg fa-solid fa-cookie-bite"></i>
                    </div>
                    <div>
                        <h4
                            class="text-sm font-bold text-slate-100 font-prompt"
                        >
                            {{ t("cookieTitle") }}
                        </h4>
                        <p
                            class="max-w-4xl mt-1 text-xs leading-relaxed text-slate-400"
                        >
                            {{ t("cookieDesc") }}
                        </p>
                    </div>
                </div>
                <div
                    class="flex items-center justify-end w-full gap-2 md:w-auto shrink-0"
                >
                    <button
                        @click="handleCookieConsent('declined')"
                        class="w-1/2 px-4 py-2 text-xs font-semibold transition-all border rounded-lg border-slate-700 hover:border-slate-500 text-slate-300 md:w-auto"
                    >
                        {{ t("cookieDecline") }}
                    </button>
                    <button
                        @click="handleCookieConsent('accepted')"
                        class="w-1/2 px-5 py-2 text-xs font-bold transition-all rounded-lg shadow-md bg-amber-500 hover:bg-amber-600 text-slate-950 md:w-auto"
                    >
                        {{ t("cookieAccept") }}
                    </button>
                </div>
            </div>
        </div>
        </Transition>

        <!-- 2. ป๊อปอัพ ข้อปฏิบัติการใช้งาน (Rules Modal) -->
        <Transition name="fade">
        <div
            v-show="modals.rules"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        >
            <div
                class="w-full max-w-2xl overflow-hidden bg-white border shadow-2xl rounded-2xl border-slate-200"
            >
                <div
                    class="flex items-center justify-between p-5 text-white bg-blue-900"
                >
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-file-shield text-amber-400"></i>
                        <h3 class="text-sm font-bold font-prompt md:text-base">
                            {{ t("rulesHeader") }}
                        </h3>
                    </div>
                    <button
                        @click="closeModal('rules')"
                        class="transition-colors text-slate-300 hover:text-white"
                    >
                        <i class="text-lg fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div
                    class="p-6 max-h-[70vh] overflow-y-auto space-y-4 text-xs md:text-sm text-slate-600 leading-relaxed"
                >
                    <div
                        class="bg-amber-50 border border-amber-200 rounded-lg p-3.5 text-amber-800 text-xs font-medium"
                    >
                        <i class="mr-1 fa-solid fa-triangle-exclamation"></i>
                        <span>{{ t("rulesNotice") }}</span>
                    </div>
                    <ol class="pl-1 space-y-3 list-decimal list-inside">
                        <li>
                            <strong>{{ t("rule1").split(":")[0] }}:</strong>
                            {{ t("rule1").split(":")[1] }}
                        </li>
                        <li>
                            <strong>{{ t("rule2").split(":")[0] }}:</strong>
                            {{ t("rule2").split(":")[1] }}
                        </li>
                        <li>
                            <strong>{{ t("rule3").split(":")[0] }}:</strong>
                            {{ t("rule3").split(":")[1] }}
                        </li>
                        <li>
                            <strong>{{ t("rule4").split(":")[0] }}:</strong>
                            {{ t("rule4").split(":")[1] }}
                        </li>
                        <li>
                            <strong>{{ t("rule5").split(":")[0] }}:</strong>
                            {{ t("rule5").split(":")[1] }}
                        </li>
                    </ol>
                </div>
                <div
                    class="flex justify-end px-6 py-4 border-t bg-slate-50 border-slate-200"
                >
                    <button
                        @click="closeModal('rules')"
                        class="px-5 py-2 text-xs font-bold text-white transition-all bg-blue-900 rounded-lg hover:bg-blue-950"
                    >
                        {{ t("btnClose") }}
                    </button>
                </div>
            </div>
        </div>
        </Transition>

        <!-- 4. ป๊อปอัพ คู่มือการใช้งาน (Manual Modal) -->
        <Transition name="fade">
        <div
            v-show="modals.manual"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        >
            <div
                class="w-full max-w-2xl overflow-hidden bg-white border shadow-2xl rounded-2xl border-slate-200"
            >
                <div
                    class="flex items-center justify-between p-5 text-white bg-blue-900"
                >
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-book-open text-amber-400"></i>
                        <h3 class="text-sm font-bold font-prompt md:text-base">
                            {{ t("manualHeader") }}
                        </h3>
                    </div>
                    <button
                        @click="closeModal('manual')"
                        class="transition-colors text-slate-300 hover:text-white"
                    >
                        <i class="text-lg fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div
                    class="p-6 max-h-[70vh] overflow-y-auto space-y-5 text-xs md:text-sm text-slate-600"
                >
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <!-- ขั้นตอนที่ 1 -->
                        <div
                            class="relative p-4 space-y-2 text-center border border-slate-200 rounded-xl bg-slate-50"
                        >
                            <span
                                class="absolute flex items-center justify-center w-6 h-6 text-xs font-bold -translate-x-1/2 rounded-full -top-3 left-1/2 bg-amber-500 text-slate-950"
                                >1</span
                            >
                            <div class="pt-2 text-2xl text-blue-900">
                                <i class="fa-solid fa-right-to-bracket"></i>
                            </div>
                            <h4 class="font-bold text-slate-900">
                                {{ t("step1") }}
                            </h4>
                            <p class="text-[11px] text-slate-500">
                                {{ t("step1Desc") }}
                            </p>
                        </div>
                        <!-- ขั้นตอนที่ 2 -->
                        <div
                            class="relative p-4 space-y-2 text-center border border-slate-200 rounded-xl bg-slate-50"
                        >
                            <span
                                class="absolute flex items-center justify-center w-6 h-6 text-xs font-bold -translate-x-1/2 rounded-full -top-3 left-1/2 bg-amber-500 text-slate-950"
                                >2</span
                            >
                            <div class="pt-2 text-2xl text-blue-900">
                                <i class="fa-solid fa-hand-pointer"></i>
                            </div>
                            <h4 class="font-bold text-slate-900">
                                {{ t("step2") }}
                            </h4>
                            <p class="text-[11px] text-slate-500">
                                {{ t("step2Desc") }}
                            </p>
                        </div>
                        <!-- ขั้นตอนที่ 3 -->
                        <div
                            class="relative p-4 space-y-2 text-center border border-slate-200 rounded-xl bg-slate-50"
                        >
                            <span
                                class="absolute flex items-center justify-center w-6 h-6 text-xs font-bold -translate-x-1/2 rounded-full -top-3 left-1/2 bg-amber-500 text-slate-950"
                                >3</span
                            >
                            <div class="pt-2 text-2xl text-blue-900">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </div>
                            <h4 class="font-bold text-slate-900">
                                {{ t("step3") }}
                            </h4>
                            <p class="text-[11px] text-slate-500">
                                {{ t("step3Desc") }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="pt-4 space-y-2 text-xs border-t border-slate-200"
                    >
                        <h4 class="font-bold text-slate-900 font-prompt">
                            <i class="fa-solid fa-lightbulb text-amber-500"></i>
                            {{ t("manualTipsTitle") }}
                        </h4>
                        <ul class="list-disc list-inside space-y-1.5 pl-1">
                            <li>{{ t("manualTip1") }}</li>
                            <li>{{ t("manualTip2") }}</li>
                            <li>{{ t("manualTip3") }}</li>
                        </ul>
                    </div>
                </div>
                <div
                    class="flex justify-end px-6 py-4 border-t bg-slate-50 border-slate-200"
                >
                    <button
                        @click="closeModal('manual')"
                        class="px-5 py-2 text-xs font-bold text-white transition-all bg-blue-900 rounded-lg hover:bg-blue-950"
                    >
                        {{ t("btnClose") }}
                    </button>
                </div>
            </div>
        </div>
        </Transition>

        <!-- 5. ป๊อปอัพ แบบประเมินความพึงพอใจ (Evaluation Modal) -->
        <Transition name="fade">
        <div
            v-show="modals.evaluation"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        >
            <div
                class="w-full max-w-lg overflow-hidden bg-white border shadow-2xl rounded-2xl border-slate-200"
            >
                <div
                    class="flex items-center justify-between p-5 text-white bg-blue-900"
                >
                    <div class="flex items-center gap-2">
                        <i
                            class="fa-solid fa-square-poll-vertical text-amber-400"
                        ></i>
                        <h3 class="text-sm font-bold font-prompt md:text-base">
                            {{ t("evaluationHeader") }}
                        </h3>
                    </div>
                    <button
                        @click="closeModal('evaluation')"
                        class="transition-colors text-slate-300 hover:text-white"
                    >
                        <i class="text-lg fa-solid fa-xmark"></i>
                    </button>
                </div>
                <form
                    @submit.prevent="handleEvaluationSubmit"
                    class="p-6 space-y-4 text-xs md:text-sm text-slate-600"
                >
                    <p class="text-xs text-slate-500">{{ t("evalDesc") }}</p>

                    <div>
                        <label
                            class="block mb-1 text-xs font-bold text-slate-700"
                            >{{ t("evalTopic") }}</label
                        >
                        <select
                            v-model="evaluationForm.topic"
                            class="w-full text-xs p-2.5 rounded-lg border border-slate-300 focus:border-blue-500 outline-none"
                        >
                            <option value="1">
                                ด้านระบบการจองและแพลตฟอร์มออนไลน์
                            </option>
                            <option value="2">
                                ด้านสภาพแวดล้อมและความสะอาดในห้องบริการ
                            </option>
                            <option value="3">
                                ด้านอุปกรณ์ ความเร็วเครือข่ายอินเทอร์เน็ต
                                และไอที
                            </option>
                            <option value="4">
                                ด้านพฤติกรรมการให้บริการของบุคลากร/เจ้าหน้าที่
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="block mb-2 text-xs font-bold text-slate-700"
                            >{{ t("evalRating") }}</label
                        >
                        <div
                            class="flex items-center justify-around p-3 border bg-slate-50 rounded-xl border-slate-200"
                        >
                            <label
                                class="flex flex-col items-center gap-1.5 cursor-pointer"
                            >
                                <input
                                    type="radio"
                                    v-model="evaluationForm.rating"
                                    value="5"
                                    class="text-blue-600"
                                />
                                <span class="text-sm font-bold text-amber-500"
                                    >★★★★★</span
                                >
                                <span class="text-[9px] text-slate-500">{{
                                    t("ratingExcellent")
                                }}</span>
                            </label>
                            <label
                                class="flex flex-col items-center gap-1.5 cursor-pointer"
                            >
                                <input
                                    type="radio"
                                    v-model="evaluationForm.rating"
                                    value="4"
                                    class="text-blue-600"
                                />
                                <span class="text-sm font-bold text-amber-500"
                                    >★★★★</span
                                >
                                <span class="text-[9px] text-slate-500">{{
                                    t("ratingGood")
                                }}</span>
                            </label>
                            <label
                                class="flex flex-col items-center gap-1.5 cursor-pointer"
                            >
                                <input
                                    type="radio"
                                    v-model="evaluationForm.rating"
                                    value="3"
                                    class="text-blue-600"
                                />
                                <span class="text-sm font-bold text-amber-500"
                                    >★★★</span
                                >
                                <span class="text-[9px] text-slate-500">{{
                                    t("ratingModerate")
                                }}</span>
                            </label>
                            <label
                                class="flex flex-col items-center gap-1.5 cursor-pointer"
                            >
                                <input
                                    type="radio"
                                    v-model="evaluationForm.rating"
                                    value="2"
                                    class="text-blue-600"
                                />
                                <span class="text-sm font-bold text-amber-500"
                                    >★★</span
                                >
                                <span class="text-[9px] text-slate-500">{{
                                    t("ratingFair")
                                }}</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label
                            class="block mb-1 text-xs font-bold text-slate-700"
                            >{{ t("evalComments") }}</label
                        >
                        <textarea
                            v-model="evaluationForm.comments"
                            rows="3"
                            placeholder="ท่านอยากเสนอแนะเรื่องอะไรเพิ่มเติมหรือไม่..."
                            class="w-full text-xs p-2.5 rounded-lg border border-slate-300 focus:border-blue-500 outline-none resize-none"
                        ></textarea>
                    </div>

                    <button
                        type="submit"
                        class="w-full bg-blue-900 hover:bg-blue-950 text-white font-bold py-2.5 rounded-lg text-xs shadow-md transition-all mt-2 flex items-center justify-center gap-1.5"
                    >
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>{{ t("evalSubmit") }}</span>
                    </button>
                </form>
            </div>
        </div>
        </Transition>

        <!-- 6. ป๊อปอัพยืนยันการทำรายการจอง (Booking Confirmation Modal) -->
        <Transition name="fade">
        <div
            v-show="modals.booking"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        >
            <div class="w-full max-w-lg overflow-hidden bg-white border shadow-2xl rounded-2xl border-slate-200">
                <!-- Header -->
                <div class="flex items-center justify-between p-5 text-white bg-blue-900">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-calendar-check text-amber-400"></i>
                        <h3 class="text-sm font-bold font-prompt md:text-base">
                            {{ selectedRoom ? t("bookConfirmHeader") : "เลือกห้องที่ต้องการจอง" }}
                        </h3>
                    </div>
                    <button @click="closeModal('booking')" class="transition-colors text-slate-300 hover:text-white">
                        <i class="text-lg fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                    <!-- Zone name badge -->
                    <div class="p-3 border bg-slate-50 border-slate-200 rounded-xl">
                        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">พื้นที่</div>
                        <div class="font-bold text-blue-900 text-sm pt-0.5">{{ selectedZone ? zoneTitle(selectedZone) : '' }}</div>
                    </div>

                    <!-- แบนเนอร์วันหยุด (แสดงทันทีก่อนทุกขั้นตอน) -->
                    <div v-if="props.todayIsHoliday"
                        class="flex items-start gap-3 p-3.5 bg-red-50 border border-red-200 rounded-xl text-red-800">
                        <i class="fa-solid fa-calendar-xmark text-base text-red-500 mt-0.5 shrink-0"></i>
                        <div>
                            <div class="text-xs font-bold">งดให้บริการวันนี้เนื่องในวันหยุดและวันหยุดนักขัตฤกษ์</div>
                            <div class="text-[11px] text-red-500 mt-0.5">ไม่สามารถจองใช้บริการได้ในวันนี้ กรุณากลับมาจองในวันทำการถัดไป</div>
                        </div>
                    </div>

                    <!-- แจ้งเตือนถ้ายังไม่ล็อกอิน -->
                    <div v-if="!authUser" class="bg-orange-50 border border-orange-200 text-orange-800 text-xs p-3.5 rounded-lg flex items-start gap-2">
                        <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0"></i>
                        <div>
                            <span>{{ t("bookingLoginAlert") }}</span>
                            <a href="/libroom/auth/google" class="text-blue-900 hover:underline font-bold flex items-center gap-1 mt-1.5">
                                <i class="fa-brands fa-google"></i>
                                เข้าสู่ระบบด้วย Google
                            </a>
                        </div>
                    </div>

                    <!-- Step 1: เลือกห้อง (ถ้า zone มีหลายห้อง) -->
                    <div v-else-if="!selectedRoom" class="space-y-2">
                        <p class="text-xs font-bold text-slate-700">
                            <i class="fa-solid fa-door-open mr-1.5 text-slate-400"></i>
                            เลือกห้องที่ต้องการจอง
                        </p>
                        <button
                            v-for="room in selectedZone?.rooms"
                            :key="room.id"
                            @click="room.status !== '1' && (selectedRoom = room)"
                            :disabled="room.status === '1'"
                            :class="room.status === '1'
                                ? 'opacity-60 cursor-not-allowed border-slate-200 bg-slate-50'
                                : 'hover:border-blue-400 hover:bg-blue-50 cursor-pointer'"
                            class="w-full text-left p-3.5 border border-slate-200 rounded-xl transition-all"
                        >
                            <!-- แถวหัว: ชื่อห้อง + badge สถานะ -->
                            <div class="flex items-center justify-between gap-2">
                                <div class="text-sm font-bold text-slate-900">{{ room.title }}</div>
                                <span
                                    :class="{
                                        'bg-red-100 text-red-700 border-red-200':      room.status === '1',
                                        'bg-amber-100 text-amber-700 border-amber-200': room.status !== '1' && room.confirm_type === 'manual',
                                        'bg-sky-100 text-sky-700 border-sky-200':       room.status !== '1' && room.confirm_type === 'auto',
                                    }"
                                    class="text-[10px] font-semibold px-2 py-0.5 rounded-full border whitespace-nowrap shrink-0"
                                >
                                    <template v-if="room.status === '1'">
                                        <i class="fa-solid fa-circle-xmark mr-0.5"></i>ไม่ว่าง
                                    </template>
                                    <template v-else-if="room.confirm_type === 'manual'">
                                        <i class="fa-solid fa-user-check mr-0.5"></i>ติดต่อเจ้าหน้าที่เพื่อยืนยัน
                                    </template>
                                    <template v-else>
                                        <i class="fa-solid fa-circle-check mr-0.5"></i>จองแล้วใช้บริการได้เลย
                                    </template>
                                </span>
                            </div>
                            <!-- รายละเอียดห้อง -->
                            <div v-if="room.detail" class="text-xs text-slate-500 mt-0.5">{{ room.detail }}</div>
                            <!-- roomtools badges -->
                            <div v-if="room.tools?.length" class="mt-1.5 flex flex-wrap gap-1">
                                <span
                                    v-for="rt in room.tools"
                                    :key="rt.id"
                                    class="text-[10px] bg-slate-100 text-slate-600 border border-slate-200 px-1.5 py-0.5 rounded-full flex items-center gap-0.5"
                                >
                                    <i v-if="rt.tool?.icon" :class="`fa-solid ${rt.tool.icon} text-[9px]`"></i>
                                    {{ rt.tool?.name }}<template v-if="rt.quantity > 1"> ×{{ rt.quantity }}</template>
                                </span>
                            </div>
                        </button>
                    </div>

                    <!-- Step 2: เลือกวันและเวลา -->
                    <form v-else @submit.prevent="handleBookingSubmit" class="space-y-4">
                        <!-- ชื่อห้องที่เลือก -->
                        <div class="flex items-center gap-2">
                            <button type="button" @click="selectedRoom = null; availableTimes = []"
                                v-if="selectedZone?.rooms?.length > 1"
                                class="flex items-center gap-1 text-xs text-blue-700 hover:underline">
                                <i class="fa-solid fa-chevron-left"></i> เปลี่ยนห้อง
                            </button>
                            <div class="text-xs font-bold text-slate-700">
                                <i class="mr-1 fa-solid fa-door-open text-slate-400"></i>
                                {{ selectedRoom?.title }}
                            </div>
                        </div>

                        <!-- ประเภทการจอง -->
                        <div v-if="selectedRoom?.confirm_type === 'manual'"
                            class="flex items-start gap-2 px-3 py-2.5 bg-amber-50 border border-amber-200 rounded-lg text-[11px] text-amber-800">
                            <i class="fa-solid fa-clock-rotate-left mt-0.5 shrink-0"></i>
                            <span><span class="font-bold">จองได้เฉพาะวันนี้</span> — เจ้าหน้าที่จะอนุมัติเมื่อคุณมาถึง</span>
                        </div>
                        <div v-else-if="selectedRoom?.confirm_type === 'auto'"
                            class="flex items-start gap-2 px-3 py-2.5 bg-emerald-50 border border-emerald-200 rounded-lg text-[11px] text-emerald-800">
                            <i class="fa-solid fa-circle-check mt-0.5 shrink-0"></i>
                            <span><span class="font-bold">ยืนยันทันที</span> — จองแล้วใช้ได้เลย ตัดโควต้าทันที</span>
                        </div>

                        <!-- อุปกรณ์ในห้อง -->
                        <div v-if="selectedRoom?.tools?.length">
                            <label class="block mb-1.5 text-xs font-bold text-slate-700">
                                <i class="fa-solid fa-toolbox mr-1.5 text-slate-400"></i>อุปกรณ์ในห้อง
                            </label>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="rt in selectedRoom.tools"
                                    :key="rt.id"
                                    class="text-[11px] bg-blue-50 text-blue-700 border border-blue-200 px-2 py-1 rounded-full flex items-center gap-1 font-medium"
                                >
                                    <i v-if="rt.tool?.icon" :class="`fa-solid ${rt.tool.icon} text-[10px]`"></i>
                                    {{ rt.tool?.name }}<template v-if="rt.quantity > 1"> ×{{ rt.quantity }}</template>
                                </span>
                            </div>
                        </div>

                        <!-- กริด Time Slot จาก DB -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-xs font-bold text-slate-700">
                                    เลือกช่วงเวลา
                                    <span v-if="usedHoursToday > 0"
                                        class="ml-1.5 font-normal text-amber-600">
                                        (ใช้ไป {{ usedHoursToday }}/{{ totalQuota }} ชม. ในโซนนี้ — เหลือ {{ quota }} ชม.)
                                    </span>
                                    <span v-else class="ml-1.5 font-normal text-slate-400">
                                        (สูงสุด {{ totalQuota }} ชม./วัน ในโซนนี้)
                                    </span>
                                </label>
                                <div class="flex items-center gap-3 text-[10px] text-slate-500">
                                    <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 bg-blue-600 rounded"></span>เลือก</span>
                                    <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-slate-200"></span>ไม่พร้อม</span>
                                    <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 bg-red-200 rounded"></span>ไม่ว่าง</span>
                                </div>
                            </div>

                            <!-- วันหยุดนักขัตฤกษ์ -->
                            <div v-if="props.todayIsHoliday"
                                class="py-6 text-center border border-red-200 border-dashed rounded-lg bg-red-50">
                                <i class="mb-1 text-lg text-red-400 fa-solid fa-calendar-xmark"></i>
                                <div class="text-xs font-bold text-red-600">งดให้บริการเนื่องในวันหยุดนักขัตฤกษ์</div>
                                <div class="text-[11px] text-red-400 mt-0.5">ไม่สามารถจองใช้บริการในวันนี้ได้</div>
                            </div>

                            <!-- โควต้าหมด -->
                            <div v-else-if="quota <= 0"
                                class="py-6 text-center border border-dashed rounded-lg border-amber-200 bg-amber-50">
                                <i class="mb-1 text-base fa-solid fa-circle-exclamation text-amber-400"></i>
                                <div class="text-xs font-bold text-amber-700">โควต้าการจองหมดแล้ว</div>
                                <div class="text-[11px] text-amber-500 mt-0.5">คุณใช้ครบ {{ totalQuota }} ชม./วัน ในโซนนี้แล้ว</div>
                            </div>

                            <!-- Loading -->
                            <div v-else-if="isFetchingSlots" class="py-6 text-xs text-center text-slate-400">
                                <i class="mr-1 fa-solid fa-spinner fa-spin"></i> กำลังโหลดช่วงเวลา...
                            </div>

                            <!-- ไม่มีข้อมูล -->
                            <div v-else-if="!availableTimes.length"
                                class="py-6 text-xs text-center border border-dashed rounded-lg text-slate-400 border-slate-200">
                                <i class="mr-1 fa-solid fa-calendar-xmark"></i> ไม่มีช่วงเวลาให้บริการในวันนี้
                            </div>

                            <!-- Slot Grid -->
                            <div v-else-if="availableTimes.length" class="grid grid-cols-3 gap-1.5 sm:grid-cols-4">
                                <button
                                    v-for="time in availableTimes"
                                    :key="time.id"
                                    type="button"
                                    @click="selectSlot(time.id)"
                                    :disabled="getSlotState(time.id) === 'booked'"
                                    :class="{
                                        'bg-blue-600 border-blue-700 text-white font-bold shadow-sm':          getSlotState(time.id) === 'selected',
                                        'bg-red-50 border-red-200 text-red-400 cursor-not-allowed line-through': getSlotState(time.id) === 'booked',
                                        'bg-slate-100 border-slate-200 text-slate-300 cursor-default':          getSlotState(time.id) === 'dim',
                                        'bg-white border-slate-200 text-slate-600 hover:border-blue-400 hover:bg-blue-50': getSlotState(time.id) === 'available',
                                    }"
                                    class="px-1 py-2.5 text-[11px] border rounded-lg text-center transition-all leading-tight font-medium"
                                >
                                    {{ time.start }}–{{ time.end }}
                                </button>
                            </div>

                        </div>

                        <!-- สรุปช่วงเวลาที่เลือก -->
                        <div v-if="bookingSummary" class="flex items-center gap-3 p-3.5 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-900">
                            <i class="text-base text-blue-500 fa-solid fa-clock shrink-0"></i>
                            <div class="flex-1">
                                <div class="font-bold">{{ bookingSummary.start }} – {{ bookingSummary.end }} น.</div>
                                <div class="text-blue-500 mt-0.5">รวม {{ bookingSummary.hours }} ชั่วโมง (จากโควต้า {{ quota }} ชม.)</div>
                            </div>
                        </div>
                        <div v-else class="p-3 text-center text-[11px] text-slate-400 border border-dashed border-slate-200 rounded-lg">
                            <i class="mr-1 fa-regular fa-hand-pointer"></i>
                            แตะ slot เพื่อเลือก — เลือกได้สูงสุด {{ quota }} ชม. ต่อเนื่องกัน
                        </div>

                        <!-- Checkbox ยอมรับเงื่อนไข -->
                        <label class="flex items-start gap-2 pt-1 cursor-pointer">
                            <input v-model="bookingForm.terms" type="checkbox" required
                                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 mt-0.5" />
                            <span class="text-[11px] text-slate-500 leading-normal">{{ t("bookTerms") }}</span>
                        </label>

                        <!-- ปุ่มยืนยัน -->
                        <button
                            type="submit"
                            :disabled="!bookingSummary || props.todayIsHoliday || isSubmitting"
                            :class="(!bookingSummary || props.todayIsHoliday || isSubmitting) ? 'bg-slate-300 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700 cursor-pointer'"
                            class="w-full text-white font-bold py-2.5 rounded-lg text-xs shadow-md transition-all flex items-center justify-center gap-1.5"
                        >
                            <i :class="isSubmitting ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-circle-check'"></i>
                            <span>{{ isSubmitting ? 'กำลังจอง...' : t("btnConfirmComplete") }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        </Transition>

        <!-- 7. Modal แชร์ลิงก์เชิญเพื่อน (Join Share Modal) -->
        <Transition name="fade">
        <div v-if="modals.joinShare"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="w-full max-w-md overflow-hidden bg-white border shadow-2xl rounded-2xl border-slate-200">
                <div class="flex items-center justify-between p-5 text-white bg-blue-900">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-user-plus text-amber-400"></i>
                        <h3 class="text-sm font-bold font-prompt">แชร์ลิงก์ให้เพื่อนเข้าร่วม</h3>
                    </div>
                    <button @click="closeModal('joinShare')" class="transition-colors text-slate-300 hover:text-white">
                        <i class="text-lg fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <!-- Progress -->
                    <div class="p-4 border border-blue-200 bg-blue-50 rounded-xl">
                        <div class="flex items-center justify-between mb-2 text-xs">
                            <span class="font-semibold text-blue-900">สมาชิกในกลุ่ม</span>
                            <span class="font-bold text-blue-900">{{ joinCapacity.current }} / {{ joinCapacity.need }} คน</span>
                        </div>
                        <div class="w-full h-2 overflow-hidden bg-blue-200 rounded-full">
                            <div class="h-full bg-blue-600 rounded-full"
                                :style="{ width: Math.min(100, (joinCapacity.current / joinCapacity.need) * 100) + '%' }">
                            </div>
                        </div>
                        <p class="text-[11px] text-blue-600 mt-1.5">
                            ต้องการอีก {{ joinCapacity.need - joinCapacity.current }} คน เพื่อส่งให้เจ้าหน้าที่ยืนยัน
                        </p>
                    </div>

                    <!-- Link -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">ลิงก์เชิญเพื่อน</label>
                        <div class="flex gap-2">
                            <input
                                :value="joinUrl"
                                readonly
                                class="flex-1 px-3 py-2 text-xs truncate border rounded-lg border-slate-200 bg-slate-50 text-slate-600 focus:outline-none"
                            />
                            <button
                                @click="copyJoinUrl"
                                class="shrink-0 bg-blue-900 hover:bg-blue-950 text-white text-xs font-bold px-3 py-2 rounded-lg transition-all flex items-center gap-1.5"
                            >
                                <i class="fa-solid fa-copy"></i>
                                คัดลอก
                            </button>
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-400 text-center">
                        <i class="mr-1 fa-solid fa-clock"></i>
                        ลิงก์มีอายุ 15 นาที — หากครบกำหนดแล้วยังไม่ครบกลุ่ม ระบบจะยกเลิกอัตโนมัติ
                    </p>

                    <button
                        @click="closeModal('joinShare')"
                        class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-2.5 rounded-xl text-xs transition-all"
                    >
                        รับทราบ
                    </button>
                </div>
            </div>
        </div>
        </Transition>

        <!-- 8. แจ้งเตือน (Toast Notification) -->
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
                    <h4 class="text-sm font-bold text-slate-900 font-prompt">{{ toast.title }}</h4>
                    <p class="text-xs text-slate-500 mt-0.5">{{ toast.desc }}</p>
                </div>
                <button
                    @click="hideToast"
                    class="transition-colors text-slate-400 hover:text-slate-600"
                >
                    <i class="text-sm fa-solid fa-xmark"></i>
                </button>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
/* คุณสามารถเพิ่ม CSS Scoped เพิ่มเติมได้ที่นี่หากต้องการ */
.font-prompt {
    font-family: "Anuphan", sans-serif;
}
</style>
