<script setup>
import { usePage, router } from "@inertiajs/vue3";
import { ref, computed, onMounted } from "vue";

const props = defineProps({
    locations: Array,
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
const selectedRoomName = ref("");

const timeSlots = [
    { id: 0, start: "08:00", end: "09:00", label: "08-09" },
    { id: 1, start: "09:00", end: "10:00", label: "09-10" },
    { id: 2, start: "10:00", end: "11:00", label: "10-11" },
    { id: 3, start: "11:00", end: "12:00", label: "11-12" },
    { id: 4, start: "12:00", end: "13:00", label: "12-13" },
    { id: 5, start: "13:00", end: "14:00", label: "13-14" },
    { id: 6, start: "14:00", end: "15:00", label: "14-15" },
    { id: 7, start: "15:00", end: "16:00", label: "15-16" },
    { id: 8, start: "16:00", end: "17:00", label: "16-17" },
    { id: 9, start: "17:00", end: "18:00", label: "17-18" },
    { id: 10, start: "18:00", end: "19:00", label: "18-19" },
    { id: 11, start: "19:00", end: "20:00", label: "19-20" },
    { id: 12, start: "20:00", end: "21:00", label: "20-21" },
];

const bookingForm = ref({
    date: "",
    selectedSlots: [],
    terms: false,
});

// Mock: slots booked by others for this room/date (จาก API จริง)
const bookedSlotIds = ref([2, 7]);

// Mock: ชั่วโมงที่ user จองไปแล้ววันนี้ (จาก API จริง)
const quotaUsedToday = ref(1);

const quotaRemaining = computed(
    () => 3 - quotaUsedToday.value - bookingForm.value.selectedSlots.length,
);

const initiateBooking = (roomName) => {
    selectedRoomName.value = roomName;
    const today = new Date().toISOString().split("T")[0];
    bookingForm.value.date = today;
    bookingForm.value.selectedSlots = [];
    bookingForm.value.terms = false;
    openModal("booking");
};

const minDate = computed(() => new Date().toISOString().split("T")[0]);

const getSlotState = (slotId) => {
    if (bookedSlotIds.value.includes(slotId)) return "booked";
    if (bookingForm.value.selectedSlots.includes(slotId)) return "selected";
    return "available";
};

const toggleSlot = (slotId) => {
    if (bookedSlotIds.value.includes(slotId)) return;

    const selected = [...bookingForm.value.selectedSlots].sort((a, b) => a - b);

    if (selected.includes(slotId)) {
        // คลิก slot ที่เลือกอยู่: ถ้าเป็นปลาย trim ออก ถ้าตรงกลาง reset
        if (slotId === selected[0]) {
            bookingForm.value.selectedSlots = selected.slice(1);
        } else if (slotId === selected[selected.length - 1]) {
            bookingForm.value.selectedSlots = selected.slice(0, -1);
        } else {
            bookingForm.value.selectedSlots = [];
        }
        return;
    }

    if (selected.length === 0) {
        bookingForm.value.selectedSlots = [slotId];
        return;
    }

    const first = selected[0];
    const last = selected[selected.length - 1];
    let newSelected;

    if (slotId === last + 1) {
        newSelected = [...selected, slotId];
    } else if (slotId === first - 1) {
        newSelected = [slotId, ...selected];
    } else {
        // ไม่ต่อเนื่อง: reset ไปเริ่มใหม่
        bookingForm.value.selectedSlots = [slotId];
        return;
    }

    if (newSelected.length + quotaUsedToday.value > 3) {
        showToast(
            currentLang.value === "th" ? "โควต้าวันนี้เต็มแล้ว" : "Daily Quota Reached",
            currentLang.value === "th"
                ? `คุณจองได้อีกเพียง ${3 - quotaUsedToday.value - selected.length} ชั่วโมงในวันนี้`
                : `You can only book ${3 - quotaUsedToday.value - selected.length} more hour(s) today.`,
            true,
        );
        return;
    }

    bookingForm.value.selectedSlots = newSelected;
};

const bookingSummary = computed(() => {
    const selected = [...bookingForm.value.selectedSlots].sort((a, b) => a - b);
    if (selected.length === 0) return null;
    return {
        start: timeSlots[selected[0]].start,
        end: timeSlots[selected[selected.length - 1]].end,
        hours: selected.length,
    };
});

const handleBookingSubmit = () => {
    if (!bookingSummary.value) {
        showToast(
            currentLang.value === "th" ? "กรุณาเลือกช่วงเวลา" : "Please Select Time Slots",
            currentLang.value === "th"
                ? "คุณต้องเลือกอย่างน้อย 1 ช่วงเวลาก่อนยืนยันการจอง"
                : "Please select at least one time slot.",
            true,
        );
        return;
    }

    closeModal("booking");

    showToast(
        currentLang.value === "th" ? "ทำการจองสำเร็จ!" : "Reservation Complete!",
        currentLang.value === "th"
            ? `จอง ${selectedRoomName.value} วันที่ ${bookingForm.value.date} เวลา ${bookingSummary.value.start}–${bookingSummary.value.end} น. (${bookingSummary.value.hours} ชม.) สำเร็จ!`
            : `Reserved ${selectedRoomName.value} on ${bookingForm.value.date} from ${bookingSummary.value.start} to ${bookingSummary.value.end} (${bookingSummary.value.hours} hr).`,
    );
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
                            href="/auth/google"
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
                    <button
                        @click="openModal('manual')"
                        class="flex items-center gap-1.5 px-3 py-2 text-slate-600 hover:text-blue-900 hover:bg-slate-100 rounded-lg transition-all font-medium"
                    >
                        <i class="text-orange-500 fa-solid fa-book-open"></i>
                        <span>{{ t("navManual") }}</span>
                    </button>
                    <button
                        @click="openModal('evaluation')"
                        class="flex items-center gap-1.5 px-3 py-2 text-slate-600 hover:text-blue-900 hover:bg-slate-100 rounded-lg transition-all font-medium"
                    >
                        <i
                            class="text-green-600 fa-solid fa-square-poll-vertical"
                        ></i>
                        <span>{{ t("navFeedback") }}</span>
                    </button>
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
            <div class="space-y-6">
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
                                @click="initiateBooking(zoneTitle(zone))"
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
                                    href="#"
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
                                <button
                                    @click="openModal('manual')"
                                    class="text-left transition-colors hover:text-amber-400"
                                >
                                    <i
                                        class="fa-solid fa-chevron-right text-[8px] mr-1"
                                    ></i>
                                    <span>{{ t("navManual") }}</span>
                                </button>
                            </li>
                            <li>
                                <button
                                    @click="openModal('evaluation')"
                                    class="text-left transition-colors hover:text-amber-400"
                                >
                                    <i
                                        class="fa-solid fa-chevron-right text-[8px] mr-1"
                                    ></i>
                                    <span>{{ t("navFeedback") }}</span>
                                </button>
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
                                href="#"
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
            <div
                class="w-full max-w-lg overflow-hidden bg-white border shadow-2xl rounded-2xl border-slate-200"
            >
                <div
                    class="flex items-center justify-between p-5 text-white bg-blue-900"
                >
                    <div class="flex items-center gap-2">
                        <i
                            class="fa-solid fa-calendar-check text-amber-400"
                        ></i>
                        <h3 class="text-sm font-bold font-prompt md:text-base">
                            {{ t("bookConfirmHeader") }}
                        </h3>
                    </div>
                    <button
                        @click="closeModal('booking')"
                        class="transition-colors text-slate-300 hover:text-white"
                    >
                        <i class="text-lg fa-solid fa-xmark"></i>
                    </button>
                </div>
                <form
                    @submit.prevent="handleBookingSubmit"
                    class="p-6 space-y-4 max-h-[80vh] overflow-y-auto"
                >
                    <!-- ชื่อห้องที่กำลังจอง -->
                    <div
                        class="p-3.5 border bg-slate-50 border-slate-200 rounded-xl"
                    >
                        <div
                            class="text-[10px] text-slate-400 font-bold uppercase tracking-wider"
                        >
                            {{ t("bookingRoomLabel") }}
                        </div>
                        <div
                            class="font-bold text-blue-900 text-sm md:text-base pt-0.5"
                        >
                            {{ selectedRoomName }}
                        </div>
                    </div>

                    <!-- แจ้งเตือนถ้ายังไม่ล็อกอิน -->
                    <div
                        v-if="!authUser"
                        class="bg-orange-50 border border-orange-200 text-orange-800 text-xs p-3.5 rounded-lg flex items-start gap-2"
                    >
                        <i
                            class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0"
                        ></i>
                        <div>
                            <span>{{ t("bookingLoginAlert") }}</span>
                            <a
                                href="/auth/google"
                                class="text-blue-900 hover:underline font-bold flex items-center gap-1 mt-1.5"
                            >
                                <i class="fa-brands fa-google"></i>
                                เข้าสู่ระบบด้วย Google
                            </a>
                        </div>
                    </div>

                    <!-- ฟอร์มจองสำหรับ user ที่ล็อกอินแล้ว -->
                    <div v-else class="space-y-4">
                        <!-- เลือกวันที่ -->
                        <div>
                            <label
                                class="block mb-1 text-xs font-bold text-slate-700"
                                >{{ t("bookDate") }}</label
                            >
                            <input
                                v-model="bookingForm.date"
                                type="date"
                                :min="minDate"
                                required
                                class="w-full text-xs p-2.5 rounded-lg border border-slate-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none transition-all"
                            />
                        </div>

                        <!-- แถบโควต้าวันนี้ -->
                        <div
                            class="p-3 border rounded-lg bg-slate-50 border-slate-200"
                        >
                            <div
                                class="flex items-center justify-between mb-2"
                            >
                                <span class="text-xs font-bold text-slate-700"
                                    ><i
                                        class="fa-solid fa-hourglass-half mr-1.5 text-slate-400"
                                    ></i
                                    >โควต้าวันนี้</span
                                >
                                <span
                                    :class="
                                        quotaUsedToday +
                                            bookingForm.selectedSlots.length >=
                                        3
                                            ? 'text-red-600'
                                            : 'text-blue-700'
                                    "
                                    class="text-xs font-bold"
                                    >{{
                                        quotaUsedToday +
                                        bookingForm.selectedSlots.length
                                    }}/3 ชั่วโมง</span
                                >
                            </div>
                            <div class="flex gap-1.5">
                                <div
                                    v-for="i in 3"
                                    :key="i"
                                    :class="
                                        i <= quotaUsedToday
                                            ? 'bg-slate-400'
                                            : i <=
                                                quotaUsedToday +
                                                    bookingForm.selectedSlots
                                                        .length
                                              ? 'bg-blue-500'
                                              : 'bg-slate-200'
                                    "
                                    class="h-2.5 flex-1 rounded-full transition-all duration-300"
                                ></div>
                            </div>
                            <p class="mt-1.5 text-[10px] text-slate-400">
                                <i class="mr-1 fa-solid fa-circle-info"></i>
                                จองได้อีก {{ quotaRemaining }} ชั่วโมงในวันนี้
                            </p>
                        </div>

                        <!-- กริด Time Slot -->
                        <div>
                            <div
                                class="flex items-center justify-between mb-2"
                            >
                                <label
                                    class="text-xs font-bold text-slate-700"
                                    >เลือกช่วงเวลา
                                    <span
                                        class="font-normal text-slate-400"
                                        >(คลิกต่อเนื่อง)</span
                                    ></label
                                >
                                <div
                                    class="flex items-center gap-3 text-[10px] text-slate-500"
                                >
                                    <span class="flex items-center gap-1">
                                        <span
                                            class="inline-block w-3 h-3 bg-blue-600 rounded"
                                        ></span>
                                        เลือก
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <span
                                            class="inline-block w-3 h-3 bg-red-200 rounded"
                                        ></span>
                                        ไม่ว่าง
                                    </span>
                                </div>
                            </div>
                            <div
                                class="grid grid-cols-4 gap-1.5 sm:grid-cols-5"
                            >
                                <button
                                    v-for="slot in timeSlots"
                                    :key="slot.id"
                                    type="button"
                                    @click="toggleSlot(slot.id)"
                                    :disabled="
                                        getSlotState(slot.id) === 'booked'
                                    "
                                    :class="{
                                        'bg-blue-600 border-blue-700 text-white font-bold shadow-sm':
                                            getSlotState(slot.id) === 'selected',
                                        'bg-red-50 border-red-200 text-red-400 cursor-not-allowed line-through':
                                            getSlotState(slot.id) === 'booked',
                                        'bg-white border-slate-200 text-slate-600 hover:border-blue-400 hover:bg-blue-50 hover:text-blue-700':
                                            getSlotState(slot.id) === 'available',
                                    }"
                                    class="px-1 py-2.5 text-[11px] border rounded-lg text-center transition-all leading-tight font-medium"
                                >
                                    {{ slot.label }}
                                </button>
                            </div>
                        </div>

                        <!-- สรุปช่วงเวลาที่เลือก -->
                        <div
                            v-if="bookingSummary"
                            class="flex items-center gap-3 p-3.5 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-900"
                        >
                            <i
                                class="text-base text-blue-500 fa-solid fa-clock shrink-0"
                            ></i>
                            <div>
                                <div class="font-bold">
                                    {{ bookingSummary.start }} –
                                    {{ bookingSummary.end }} น.
                                </div>
                                <div class="text-blue-600 mt-0.5">
                                    รวม {{ bookingSummary.hours }} ชั่วโมง
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="p-3 text-center text-[11px] text-slate-400 border border-dashed border-slate-200 rounded-lg"
                        >
                            <i class="mr-1 fa-regular fa-hand-pointer"></i>
                            ยังไม่ได้เลือกช่วงเวลา — คลิก slot ที่ต้องการ
                        </div>

                        <!-- Checkbox ยอมรับเงื่อนไข -->
                        <label
                            class="flex items-start gap-2 pt-1 cursor-pointer"
                        >
                            <input
                                v-model="bookingForm.terms"
                                type="checkbox"
                                required
                                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 mt-0.5"
                            />
                            <span
                                class="text-[11px] text-slate-500 leading-normal"
                                >{{ t("bookTerms") }}</span
                            >
                        </label>

                        <!-- ปุ่มยืนยัน -->
                        <button
                            type="submit"
                            :disabled="!bookingSummary"
                            :class="
                                bookingSummary
                                    ? 'bg-emerald-600 hover:bg-emerald-700 cursor-pointer'
                                    : 'bg-slate-300 cursor-not-allowed'
                            "
                            class="w-full text-white font-bold py-2.5 rounded-lg text-xs shadow-md transition-all flex items-center justify-center gap-1.5"
                        >
                            <i class="fa-solid fa-circle-check"></i>
                            <span>{{ t("btnConfirmComplete") }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        </Transition>

        <!-- 7. แจ้งเตือน (Toast Notification) -->
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
