<script setup>
import { usePage, router } from "@inertiajs/vue3";
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from "vue";

const props = defineProps({
    locations:      Array,
    todayIsHoliday: { type: Boolean, default: false },
    todayDate:      { type: String,  default: () => new Date().toISOString().split('T')[0] },
    roomStatusPool: { type: Object,  default: () => ({ columns: [], rooms: [] }) },
    banners:        { type: Array,   default: () => [] },
});

const appBase = window.APP_BASE ?? '';

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

// หา location ที่เป็นเจ้าของ zone (ใช้ใน modal จองห้อง — โชว์ "พื้นที่: ชื่อ location")
const zoneLocationTitle = (zone) => {
    const loc = props.locations?.find((l) => l.zones?.some((z) => z.id === zone?.id));
    return loc ? locTitle(loc) : '';
};

// แยกชื่อโซนตรงเว้นวรรคที่ 1-2 นับจากซ้าย → ส่วนกลาง (ระหว่างเว้นวรรคที่ 1 กับ 2) เป็นสีเหลือง ที่เหลือปกติ
// เช่น "Study Room" → "Study" ปกติ + "Room" เหลือง (มีเว้นวรรคเดียว เลยเป็นคำท้าย)
//      "Meeting MSU Space" → "Meeting" ปกติ + "MSU" เหลือง + "Space" ปกติ
const titleParts = (title) => {
    if (!title) return { before: '', mid: '', after: '' };
    const i1 = title.indexOf(' ');
    if (i1 === -1) return { before: title, mid: '', after: '' };
    const i2 = title.indexOf(' ', i1 + 1);
    if (i2 === -1) return { before: title.slice(0, i1), mid: title.slice(i1 + 1), after: '' };
    return { before: title.slice(0, i1), mid: title.slice(i1 + 1, i2), after: title.slice(i2 + 1) };
};
import Swal from "sweetalert2";

// --- หน้าทดลอง: "บริการยอดนิยม" — สุ่ม 6 โซนจริงจากทั้งระบบ (สุ่มครั้งเดียวตอนโหลดหน้า ไม่สุ่มซ้ำทุก re-render) ---
function shuffle(arr) {
    const a = [...arr];
    for (let i = a.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
}
const allZones = computed(() =>
    (props.locations ?? []).flatMap((loc) => (loc.zones ?? []).map((z) => ({ ...z, __loc: loc })))
);
const featuredZones = computed(() => shuffle(allZones.value).slice(0, 6));

// --- หน้าทดลอง: "สถานะห้องตอนนี้" — สุ่ม 4 ห้องจริงจาก pool ที่ backend ส่งมา (สุ่มครั้งเดียวตอนโหลดหน้า) ---
const roomStatusFeatured = computed(() => shuffle(props.roomStatusPool.rooms ?? []).slice(0, 4));
const roomStateLabel = { free: 'ว่าง', booked: 'มีคนใช้', closed: 'ปิด' };
const roomStateBadgeCls = {
    free:   'bg-emerald-500',
    booked: 'bg-amber-500',
    closed: 'bg-slate-500',
};

// --- หน้าทดลอง: แถบขั้นตอนการจอง (คงที่ ตามโฟลว์จริงของระบบ) ---
const bookingSteps = computed(() =>
    currentLang.value === 'en'
        ? [
              { title: 'Choose a Location', desc: 'Pick the building or area' },
              { title: 'Choose a Zone', desc: 'Pick the room or service' },
              { title: 'Choose a Time', desc: 'Pick an available time slot' },
              { title: 'Confirm', desc: 'Review and confirm your booking' },
          ]
        : [
              { title: 'เลือกพื้นที่', desc: 'อาคาร/พื้นที่ที่ต้องการ' },
              { title: 'เลือกโซน', desc: 'ห้องหรือบริการที่ต้องการ' },
              { title: 'เลือกเวลา', desc: 'วันและช่วงเวลาที่ว่าง' },
              { title: 'ยืนยันการจอง', desc: 'ตรวจสอบและยืนยัน' },
          ]
);

// --- Hero banner (สไลด์) ---
// มาจากที่แอดมินอัปโหลดไว้ (แท็บ "แบนเนอร์หน้าแรก") — ถ้ายังไม่มีเลย ใช้รูปเริ่มต้นสำรองไว้ก่อน
const FALLBACK_BANNERS = [
    { image: "/imgs/banner-1.png" },
    { image: "/imgs/banner.png" },
];
const bannerSlides = computed(() => props.banners?.length ? props.banners : FALLBACK_BANNERS);
const activeBanner = ref(0);
let bannerTimer = null;

const goToBanner = (i) => { activeBanner.value = i; };
const nextBanner = () => { activeBanner.value = (activeBanner.value + 1) % bannerSlides.value.length; };
const prevBanner = () => { activeBanner.value = (activeBanner.value - 1 + bannerSlides.value.length) % bannerSlides.value.length; };

const stopBannerAutoplay = () => { if (bannerTimer) clearInterval(bannerTimer); };
const startBannerAutoplay = () => {
    if (bannerSlides.value.length < 2) return; // สไลด์เดียว ไม่ต้องเลื่อนอัตโนมัติ
    stopBannerAutoplay();
    bannerTimer = setInterval(nextBanner, 5000);
};
const pauseBanner  = () => stopBannerAutoplay();
const resumeBanner = () => startBannerAutoplay();

onMounted(() => startBannerAutoplay());
onUnmounted(() => stopBannerAutoplay());

// "ขั้นตอนการจองพื้นที่" — ไฮไลต์ขั้นที่ 1 ค้างไว้เฉยๆ (ไม่มี animation)
const activeStep = 0;

// ปุ่มเด้งกลับขึ้นบนสุด — โชว์เมื่อเลื่อนลงมาระดับหนึ่ง
const showBackToTop = ref(false);
const onScroll = () => { showBackToTop.value = window.scrollY > 500; };
const scrollToTop = () => window.scrollTo({ top: 0, behavior: 'smooth' });
onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }));
onUnmounted(() => window.removeEventListener('scroll', onScroll));

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
// เริ่มต้นเลือก location แรกอัตโนมัติ (ไม่ scroll ตอนเปิดหน้าครั้งแรก) — null = หุบทั้งหมด
const activeArea  = ref(1);
const zonePanelEl = ref(null);

const switchArea = async (areaId) => {
    activeArea.value = activeArea.value === areaId ? null : areaId;
    if (activeArea.value !== null) {
        await nextTick();
        zonePanelEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
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
        router.post(`${window.APP_BASE ?? ''}/logout`);
    }
};

// --- 5. ข้อมูลการจองห้อง (Booking System) ---
const selectedZone = ref(null);
const selectedRoom = ref(null);
const availableTimes = ref([]);
const bookedTimeIds = ref([]);
const isFetchingSlots = ref(false);
const bookingWindow = ref(null);

// นอกช่วงเวลาที่เปิดให้จอง (global) — ไม่รวมกรณีฟีเจอร์ถูกปิด
const bookingClosed = computed(
    () => !!bookingWindow.value?.enabled && !bookingWindow.value?.is_open_now,
);

const bookingForm = ref({
    date:            "",
    selectedTimeIds: [],
    terms:           false,
});
const usedHoursToday  = ref(0);
const dailyQuota      = ref(3);
const globalUsedHours = ref(0);
const globalDailyQuota = ref(3);

// โซนที่ห้องเยอะ → แสดงเป็น grid ปุ่มเล็กแทนการ์ดเต็ม
const COMPACT_ROOM_THRESHOLD = 6;
const useCompactRoomPicker = computed(
    () => (selectedZone.value?.rooms?.length ?? 0) > COMPACT_ROOM_THRESHOLD,
);
// ถ้าทุกห้องในโซนตั้งค่าเหมือนกัน → ยกไปแสดงระดับโซน 1 ครั้ง (ใช้ตอน compact)
const zoneUniform = (key) => {
    const rooms = selectedZone.value?.rooms ?? [];
    if (!rooms.length) return null;
    const first = rooms[0][key];
    return rooms.every((r) => r[key] === first) ? first : null;
};
const zoneUniformConfirmType   = computed(() => zoneUniform('confirm_type'));
const zoneUniformAccessControl = computed(() => zoneUniform('access_control'));

const initiateBooking = (zone) => {
    selectedZone.value = zone;
    selectedRoom.value = zone.rooms?.length === 1 ? zone.rooms[0] : null;
    bookingForm.value  = { date: props.todayDate, selectedTimeIds: [], terms: false };
    availableTimes.value = [];
    bookedTimeIds.value  = [];
    usedHoursToday.value  = 0;
    globalUsedHours.value = 0;
    bookingWindow.value   = null;
    openModal("booking");
};

const fetchSlots = async () => {
    if (!selectedRoom.value || !bookingForm.value.date) return;
    isFetchingSlots.value = true;
    try {
        const res = await fetch(`${window.APP_BASE ?? ''}/rooms/${selectedRoom.value.id}/slots?date=${bookingForm.value.date}`);
        if (!res.ok) {
            const text = await res.text();
            console.error('Slots API error', res.status, text);
            availableTimes.value = [];
            return;
        }
        const data = await res.json();
        availableTimes.value              = data.times ?? [];
        bookedTimeIds.value               = data.booked_ids;
        usedHoursToday.value              = data.used_hours  ?? 0;
        dailyQuota.value                  = data.daily_quota ?? 3;
        globalUsedHours.value             = data.global_used_hours  ?? 0;
        globalDailyQuota.value            = data.global_daily_quota ?? 3;
        bookingWindow.value               = data.booking_window ?? null;
        bookingForm.value.selectedTimeIds = [];
    } catch (e) {
        console.error('fetchSlots failed:', e);
        availableTimes.value = [];
    } finally {
        isFetchingSlots.value = false;
    }
};

watch(selectedRoom, () => fetchSlots());

const totalQuota    = computed(() => dailyQuota.value);
const zoneRemaining = computed(() => Math.max(0, totalQuota.value - usedHoursToday.value));
const globalRemaining = computed(() => Math.max(0, globalDailyQuota.value - globalUsedHours.value));
// เพดานที่ใช้จริง = ค่าน้อยกว่าระหว่างโควตาเฉพาะโซน กับโควตารวมทุกโซน
const quota = computed(() => Math.min(zoneRemaining.value, globalRemaining.value));

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

    if (bookingClosed.value) {
        showToast(
            currentLang.value === 'th' ? 'ไม่สามารถจองได้' : 'Booking Closed',
            bookingWindow.value?.message ?? 'ขณะนี้อยู่นอกเวลาทำการจอง',
            true,
        );
        return;
    }

    isSubmitting.value = true;
    try {
        const res = await fetch(`${window.APP_BASE ?? ''}/bookings`, {
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
        <!-- ส่วนหัวของเว็บไซต์ (Header — ตามต้นแบบ: โลโก้ตัวหนังสือ + nav + ปุ่มเหลือง) -->
        <header
            id="top"
            class="sticky top-0 z-40 bg-white border-b shadow-sm border-slate-100"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 mx-auto max-w-7xl"
            >
                <!-- โลโก้ -->
                <div class="flex items-center">
                    <img src="/imgs/logo.png" alt="MSU Library — Academic Resource Center" class="w-auto h-9" />
                </div>

                <!-- เมนูหลัก -->
                <nav class="flex flex-wrap items-center gap-1 text-sm text-slate-600 group">
                    <!-- หน้าแรก = หน้าปัจจุบัน (bold+เส้นส้มค้างไว้) แต่พอ hover ไปเมนูอื่นในแถวเดียวกันให้หลบก่อน แล้วกลับมาเมื่อเมาส์ออกจากแถบเมนู -->
                    <a href="#top"
                        class="px-3 py-2 font-bold text-slate-900 transition-all border-b-4 border-amber-400 rounded-t-lg group-hover:font-normal group-hover:text-slate-600 group-hover:border-transparent hover:!font-bold hover:!text-slate-900 hover:!border-amber-400">หน้าแรก</a>
                    <button @click="openModal('rules')"
                        class="flex items-center gap-1.5 px-3 py-2 border-b-4 border-transparent rounded-t-lg transition-all hover:font-bold hover:text-slate-900 hover:border-amber-400">
                        <span>{{ t("navRules") }}</span>
                    </button>
                    <a :href="`${appBase}/pdf/tools.pdf`" target="_blank" rel="noopener noreferrer"
                        class="flex items-center gap-1.5 px-3 py-2 border-b-4 border-transparent rounded-t-lg transition-all hover:font-bold hover:text-slate-900 hover:border-amber-400">
                        <span>{{ t("navManual") }}</span>
                    </a>
                    <a href="https://docs.google.com/forms/d/e/1FAIpQLSfG97U9yb9PcTXM3ORInGrNUfqQi3TYbxcsj7Y320h8QEEs7w/viewform?usp=dialog"
                        target="_blank" rel="noopener noreferrer"
                        class="flex items-center gap-1.5 px-3 py-2 border-b-4 border-transparent rounded-t-lg transition-all hover:font-bold hover:text-slate-900 hover:border-amber-400">
                        <span>{{ t("navFeedback") }}</span>
                    </a>
                </nav>

                <!-- ภาษา / เข้าสู่ระบบ -->
                <div class="flex items-center gap-2">
                    <div class="items-center hidden overflow-hidden text-[10px] font-bold border rounded-lg sm:flex border-slate-200">
                        <button
                            @click="changeLanguage('th')"
                            :class="currentLang === 'th' ? 'bg-slate-900 text-white' : 'text-slate-400 hover:bg-slate-50'"
                            class="px-2 py-1.5 transition-all"
                        >TH</button>
                        <button
                            @click="changeLanguage('en')"
                            :class="currentLang === 'en' ? 'bg-slate-900 text-white' : 'text-slate-400 hover:bg-slate-50'"
                            class="px-2 py-1.5 transition-all"
                        >EN</button>
                    </div>

                    <a v-if="!authUser" :href="`${appBase}/auth/google`"
                        class="bg-amber-400 hover:bg-amber-500 text-slate-900 font-bold px-4 py-2 rounded-lg text-xs flex items-center gap-1.5 transition-all">
                        <i class="fa-brands fa-google"></i>
                        <span>{{ t("login") }}</span>
                    </a>
                    <div v-else class="flex items-center gap-2">
                        <span class="hidden text-xs font-bold text-slate-700 sm:inline">
                            <i class="mr-1 fa-solid fa-circle-user text-amber-500"></i>{{ authUser.name }}
                        </span>
                        <a :href="`${appBase}/my-bookings`"
                            class="bg-amber-400 hover:bg-amber-500 text-slate-900 px-3 py-2 rounded-lg text-[11px] font-bold flex items-center gap-1 transition-all">
                            <i class="fa-solid fa-calendar-check"></i>
                            <span>การจองของฉัน</span>
                        </a>
                        <button
                            @click="handleLogout"
                            class="bg-slate-100 hover:bg-red-50 hover:text-red-600 text-slate-500 px-3 py-2 rounded-lg text-[11px] font-bold transition-all"
                        >{{ t("logout") }}</button>
                    </div>
                </div>
            </div>
        </header>

        <!-- ฮีโร่ (สไลด์) -->
        <section class="relative w-full overflow-hidden bg-slate-950 aspect-[1920/400]"
            @mouseenter="pauseBanner" @mouseleave="resumeBanner">
            <div v-for="(slide, i) in bannerSlides" :key="slide.image"
                class="absolute inset-0 transition-opacity duration-700 ease-in-out bg-center bg-no-repeat bg-contain"
                :class="i === activeBanner ? 'opacity-100' : 'opacity-0'"
                :style="{ backgroundImage: `url('${slide.image}')` }"
            ></div>

            <template v-if="bannerSlides.length > 1">
                <button @click="prevBanner" aria-label="สไลด์ก่อนหน้า"
                    class="absolute z-20 flex items-center justify-center w-8 h-8 transition-colors -translate-y-1/2 rounded-full left-3 top-1/2 bg-black/30 hover:bg-black/50">
                    <i class="text-xs text-white fa-solid fa-chevron-left"></i>
                </button>
                <button @click="nextBanner" aria-label="สไลด์ถัดไป"
                    class="absolute z-20 flex items-center justify-center w-8 h-8 transition-colors -translate-y-1/2 rounded-full right-3 top-1/2 bg-black/30 hover:bg-black/50">
                    <i class="text-xs text-white fa-solid fa-chevron-right"></i>
                </button>
                <div class="absolute z-20 flex items-center gap-1.5 -translate-x-1/2 bottom-3 left-1/2">
                    <button v-for="(slide, i) in bannerSlides" :key="`dot-${i}`" @click="goToBanner(i)"
                        :aria-label="`ไปที่สไลด์ ${i + 1}`"
                        class="h-1.5 rounded-full transition-all"
                        :class="i === activeBanner ? 'w-6 bg-white' : 'w-1.5 bg-white/50 hover:bg-white/70'"
                    ></button>
                </div>
            </template>
        </section>

        <!-- แถบประกาศสำคัญ (เลื่อนวิ่งเป็น loop แบบป้ายข่าว) -->
        <div class="overflow-hidden border-b bg-amber-50 border-amber-100">
            <div class="flex items-center py-2.5 marquee-track w-max">
                <div class="flex items-center shrink-0 gap-x-6 pr-6 text-xs text-slate-900 whitespace-nowrap">
                    <span class="font-bold shrink-0">
                        <i class="mr-1 fa-solid fa-bullhorn"></i>{{ t("quickStatTitle") }}
                    </span>
                    <span>{{ t("ann1") }}</span>
                    <span class="text-amber-300">•</span>
                    <span>{{ t("ann2") }}</span>
                    <span class="text-amber-300">•</span>
                    <span>{{ t("ann3") }}</span>
                </div>
                <div class="flex items-center shrink-0 gap-x-6 pr-6 text-xs text-slate-900 whitespace-nowrap" aria-hidden="true">
                    <span class="font-bold shrink-0">
                        <i class="mr-1 fa-solid fa-bullhorn"></i>{{ t("quickStatTitle") }}
                    </span>
                    <span>{{ t("ann1") }}</span>
                    <span class="text-amber-300">•</span>
                    <span>{{ t("ann2") }}</span>
                    <span class="text-amber-300">•</span>
                    <span>{{ t("ann3") }}</span>
                </div>
            </div>
        </div>

        <!-- ส่วนเนื้อหาหลัก -->
        <main class="flex-grow w-full px-4 py-10 mx-auto max-w-[1400px]">

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

            <!-- ═══ ขั้นตอนการจองพื้นที่ ═══ -->
            <section class="p-5 mb-8 bg-white border shadow-sm rounded-2xl border-slate-200">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center">
                    <div class="shrink-0 lg:w-48">
                        <h2 class="flex items-center gap-2 text-lg font-extrabold text-slate-900 font-prompt">
                            <span class="w-1.5 h-5 rounded-full bg-amber-400"></span>ขั้นตอนการจองพื้นที่
                        </h2>
                        <p class="mt-1 text-sm text-slate-400">จองง่าย ใช้เวลาไม่กี่ขั้นตอน</p>
                    </div>

                    <!-- จอเล็ก: กริด 2 คอลัมน์ ไม่มีเส้นเชื่อม -->
                    <div class="grid flex-1 grid-cols-2 gap-4 lg:hidden">
                        <div v-for="(step, i) in bookingSteps" :key="i" class="flex items-start gap-3">
                            <span class="flex items-center justify-center text-sm font-extrabold transition-colors duration-500 rounded-full w-9 h-9 shrink-0 text-slate-900"
                                :class="i === activeStep ? 'bg-amber-400' : 'bg-slate-100 border-2 border-slate-200'"
                            >{{ i + 1 }}</span>
                            <div class="min-w-0 text-left">
                                <div class="text-xs font-bold text-slate-800">{{ step.title }}</div>
                                <div class="text-[11px] text-slate-400 mt-0.5">{{ step.desc }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- จอใหญ่: แถวเดียว มีเส้นประเชื่อมระหว่างขั้นตอน -->
                    <div class="flex-1 hidden lg:flex lg:items-start">
                        <template v-for="(step, i) in bookingSteps" :key="`d-${i}`">
                            <div class="flex items-start flex-1 min-w-0 gap-3 px-1">
                                <span class="flex items-center justify-center text-sm font-extrabold rounded-full w-9 h-9 shrink-0 text-slate-900"
                                    :class="i === 0 ? 'bg-amber-400' : 'bg-slate-100 border-2 border-slate-200'"
                                >{{ i + 1 }}</span>
                                <div class="min-w-0 text-left">
                                    <div class="text-xs font-bold text-slate-800">{{ step.title }}</div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">{{ step.desc }}</div>
                                </div>
                            </div>
                            <div v-if="i < bookingSteps.length - 1"
                                class="flex-1 min-w-[12px] border-t-2 border-dashed border-amber-200 mt-[18px]"></div>
                        </template>
                    </div>
                </div>
            </section>

            <!-- ═══ เลือกพื้นที่บริการ (3 location จริง) ═══ -->
            <section id="locations" class="mb-8">
                <h2 class="flex items-center gap-2 mb-4 text-xl font-extrabold text-slate-900 font-prompt">
                    <span class="w-1.5 h-6 rounded-full bg-amber-400"></span>
                    เลือกพื้นที่บริการ
                </h2>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <button
                        v-for="(loc, i) in locations" :key="loc.id"
                        @click="switchArea(i + 1)"
                        class="relative overflow-hidden text-left transition-all duration-300 bg-white border-2 shadow-sm group rounded-2xl hover:shadow-lg"
                        :class="[
                            activeArea === i + 1 ? 'border-amber-400' : 'border-transparent hover:border-amber-400',
                            activeArea !== null && activeArea !== i + 1 ? 'opacity-100 grayscale' : '',
                        ]"
                    >
                        <div class="h-40 overflow-hidden bg-slate-100">
                            <img v-if="loc.pic" :src="`/imgs/locations/${loc.pic}`" :alt="locTitle(loc)"
                                class="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105" />
                        </div>
                        <div class="flex items-start justify-between gap-2 p-4">
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-slate-900 font-prompt">{{ locTitle(loc) }}</h3>
                                <p class="mt-1 text-xs text-slate-500 line-clamp-2">{{ loc.detail }}</p>
                            </div>
                            <span class="flex items-center justify-center w-8 h-8 text-sm transition-colors rounded-full shrink-0 bg-amber-400 text-slate-900 group-hover:bg-amber-500">
                                <i class="fa-solid" :class="activeArea === i + 1 ? 'fa-chevron-up' : 'fa-arrow-right'"></i>
                            </span>
                        </div>
                    </button>
                </div>

                <!-- ═══ โซนของ location ที่เลือก (ขยายแทรกในหน้า ไม่ใช่ modal ไม่ล้างส่วนอื่น) ═══ -->
                <!-- ref อยู่ที่ wrapper คงที่ (ไม่ถูกถอด/สร้างใหม่ตาม :key) กัน scrollIntoView ชี้ element ที่ยังไม่ mount ตอน mode=out-in -->
                <div ref="zonePanelEl" class="scroll-mt-24">
                <Transition name="fade" mode="out-in">
                    <div v-if="currentLocation" :key="activeArea" class="mt-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-bold text-slate-900 font-prompt">
                                พื้นที่บริการใน{{ locTitle(currentLocation) }}
                            </h3>
                            <button @click="activeArea = null" class="text-xs font-bold transition-colors text-slate-400 hover:text-slate-600">
                                <i class="mr-1 fa-solid fa-xmark"></i>ปิด
                            </button>
                        </div>

                        <div v-if="currentLocation.status !== '0'"
                            class="flex items-center gap-3 p-4 border border-red-200 bg-red-50 rounded-2xl">
                            <i class="text-xl text-red-500 fa-solid fa-circle-xmark"></i>
                            <div class="text-sm font-bold text-red-700">พื้นที่นี้ไม่พร้อมให้บริการในขณะนี้</div>
                        </div>

                        <div v-else class="grid grid-cols-2 gap-3 sm:gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            <button
                                v-for="zone in currentZones" :key="zone.id"
                                @click="zone.status === '0' && initiateBooking(zone)"
                                :disabled="zone.status !== '0'"
                                class="relative overflow-hidden text-left transition-all shadow-sm h-52 bg-slate-900 group rounded-2xl disabled:cursor-not-allowed"
                            >
                                <img v-if="zone.pic" :src="`/imgs/zones/${zone.pic}`" :alt="zoneTitle(zone)"
                                    class="absolute inset-0 object-cover w-full h-full transition-transform duration-300 group-hover:scale-105"
                                    :class="zone.status !== '0' ? 'grayscale opacity-40' : 'opacity-90'" />
                                <div v-else class="absolute inset-0 flex items-center justify-center bg-slate-800">
                                    <i class="text-4xl text-slate-500 fa-solid fa-image"></i>
                                </div>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/10 to-transparent"></div>

                                <div class="absolute inset-x-0 bottom-0 p-4 text-white">
                                    <h3 class="text-xl font-extrabold font-prompt">
                                        {{ titleParts(zoneTitle(zone)).before }}
                                        <span class="text-amber-400">{{ titleParts(zoneTitle(zone)).mid }}</span>
                                        {{ titleParts(zoneTitle(zone)).after }}
                                    </h3>
                                    <p class="flex items-center gap-1.5 mt-0.5 text-[11px] text-slate-200 line-clamp-1">
                                        <i v-if="zone.icon" :class="`fa-solid ${zone.icon}`" class="text-xl"></i>
                                        {{ zoneDetail(zone) || 'พื้นที่ให้บริการ' }}
                                    </p>
                                </div>

                                <span v-if="zone.status !== '0'"
                                    class="absolute px-2 py-1 text-[10px] font-bold text-white bg-red-600 rounded-full top-3 left-3">
                                    ปิดให้บริการ
                                </span>
                                <span v-else
                                    class="absolute flex items-center justify-center text-sm font-bold transition-colors rounded-full text-slate-900 bottom-4 right-4 w-9 h-9 bg-amber-400 group-hover:bg-amber-300">
                                    <i class="fa-solid fa-arrow-right"></i>
                                </span>
                            </button>
                        </div>
                    </div>
                </Transition>
                </div>
            </section>           

            <!-- ═══ บริการยอดนิยม (สุ่ม 6 โซนจริงจากทั้งระบบ) ═══ -->
            <section class="mb-8">
                <h2 class="flex items-center gap-2 mb-4 text-lg font-extrabold text-slate-900 font-prompt">
                    <span class="w-1.5 h-6 rounded-full bg-amber-400"></span>บริการยอดนิยม
                </h2>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                    <button
                        v-for="zone in featuredZones" :key="zone.id"
                        @click="zone.status === '0' && initiateBooking(zone)"
                        class="overflow-hidden text-left transition-all bg-white border shadow-sm group rounded-2xl border-slate-200 hover:shadow-md"
                    >
                        <div class="h-24 overflow-hidden bg-slate-100">
                            <img v-if="zone.pic" :src="`/imgs/zones/${zone.pic}`" :alt="zoneTitle(zone)"
                                class="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105" />
                        </div>
                        <div class="p-3">
                            <h4 class="text-xs font-bold truncate text-slate-900">
                                {{ titleParts(zoneTitle(zone)).before }}
                                <span class="text-amber-500">{{ titleParts(zoneTitle(zone)).mid }}</span>
                                {{ titleParts(zoneTitle(zone)).after }}
                            </h4>
                            <p class="flex items-center gap-1 text-[10px] text-slate-400 truncate mt-0.5">
                                <i v-if="zone.icon" :class="`fa-solid ${zone.icon}`"></i>
                                {{ zone.__loc?.title }}
                            </p>
                            <span class="flex items-center justify-center w-full gap-1 mt-2 text-xs font-bold text-slate-900 bg-amber-400 group-hover:bg-amber-500 px-2 py-1.5 rounded-lg transition-colors">
                                จองเลย <i class="fa-solid fa-arrow-right text-[8px]"></i>
                            </span>
                        </div>
                    </button>
                </div>
            </section>

            <!-- ═══ สถานะห้องตอนนี้ (สุ่ม 4 ห้องจริงจากทั้งระบบ — ทดลอง เอาออกได้ถ้าไม่ชอบ) ═══ -->
            <section class="mb-8">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <h2 class="flex items-center gap-2 text-lg font-extrabold text-slate-900 font-prompt">
                        <span class="w-1.5 h-6 rounded-full bg-amber-400"></span>สถานะห้องตอนนี้
                    </h2>
                    <div class="flex items-center gap-4 text-[11px] text-slate-500">
                        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>ว่าง</span>
                        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>มีคนใช้</span>
                        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>ปิด</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div v-for="(r, i) in roomStatusFeatured" :key="i"
                        class="overflow-hidden bg-white border shadow-sm rounded-2xl border-slate-200">
                        <div class="relative h-24 overflow-hidden bg-slate-100">
                            <img v-if="r.zone_pic" :src="`/imgs/zones/${r.zone_pic}`" :alt="r.room_title"
                                class="object-cover w-full h-full" />
                            <span class="absolute flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-white rounded-full top-2 right-2"
                                :class="roomStateBadgeCls[r.state]">
                                <span class="w-1.5 h-1.5 bg-white rounded-full"></span>{{ roomStateLabel[r.state] }}
                            </span>
                        </div>
                        <div class="p-3">
                            <h4 class="text-xs font-bold truncate text-slate-900">{{ r.room_title }}</h4>
                            <p class="text-[10px] text-slate-400 truncate mt-0.5">{{ r.loc_title }} · {{ r.zone_title }}</p>
                        </div>
                    </div>
                </div>
                <p class="mt-2 text-[11px] text-slate-400">* สุ่มแสดง 4 ห้องจากทั้งระบบ — สถานะ ณ เวลาปัจจุบัน ({{ todayDate }})</p>

                <!-- แบบตาราง (ห้องเดียวกับการ์ดด้านบน) — ไว้เทียบเลือกรูปแบบ นำเสนอผู้บริหาร -->
                <p class="mt-6 mb-2 text-xs font-bold text-slate-500">แบบตาราง (ห้องเดียวกับด้านบน — ดูภาพรวมทั้งวัน)</p>
                <div class="p-4 overflow-x-auto bg-white border shadow-sm rounded-2xl border-slate-200">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr>
                                <th class="p-2 font-bold text-left text-slate-500">ห้อง / เวลา</th>
                                <th v-for="col in roomStatusPool.columns" :key="col" class="p-2 font-bold text-center text-slate-500">{{ col }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(r, i) in roomStatusFeatured" :key="i" class="border-t border-slate-100">
                                <td class="p-2 font-bold whitespace-nowrap text-slate-800">
                                    {{ r.room_title }}
                                    <div class="text-[10px] font-normal text-slate-400">{{ r.loc_title }} · {{ r.zone_title }}</div>
                                </td>
                                <td v-for="(state, j) in r.cells" :key="j" class="p-2 text-center">
                                    <span class="inline-block w-2.5 h-2.5 rounded-full"
                                        :class="state === 'booked' ? 'bg-amber-500' : state === 'closed' ? 'bg-slate-300' : 'bg-emerald-500'"></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>



        <!-- FOOTER ข้อมูลการติดต่อ -->
        <footer class="mt-auto text-white bg-black">
            <div class="px-4 py-10 mx-auto max-w-[1600px]">
                <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 md:grid-cols-4">
                    <!-- คอลัมน์ที่ 1: โลโก้ -->
                    <div class="flex items-start gap-3 border-r-2 border-slate-800">
                        <img src="/imgs/footer.png" alt="MSU Library" class="w-auto h-12 shrink-0" />
                        <div class="leading-snug">
                            <div class="text-sm font-extrabold tracking-tight font-prompt">MSU LIBRARY</div>
                            <div class="text-xs text-slate-400">Academic Resource Center</div>
                            <div class="text-xs text-slate-400">Mahasarakham University</div>
                        </div>
                    </div>

                    <!-- คอลัมน์ที่ 2: แท็กไลน์ -->
                    <div class="border-r-2 border-slate-800">
                        <p class="leading-snug text-md font-prompt">
                            พื้นที่แห่งการเรียนรู้<br />เพื่ออนาคตที่มากกว่า
                        </p>
                        <div class="w-10 h-0.5 bg-amber-400 my-2.5"></div>
                        <p class="text-[11px] tracking-[0.15em] text-slate-400 uppercase">More than a Library</p>
                    </div>

                    <!-- คอลัมน์ที่ 3: ติดต่อเรา -->
                    <div class="border-r-2 border-slate-800">
                        <h5 class="mb-3 text-sm font-bold tracking-wider uppercase text-slate-300 font-prompt">
                            ติดต่อเรา
                        </h5>
                        <ul class="space-y-2.5 text-xs text-slate-400">
                            <li class="flex items-start gap-2">
                                <i class="mt-0.5 fa-solid fa-location-dot text-white"></i>
                                <span>สำนักวิทยบริการ มหาวิทยาลัยมหาสารคาม<br />ต.ขามเรียง อ.กันทรวิชัย จ.มหาสารคาม 44150</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <i class="text-white fa-solid fa-phone"></i>
                                <span>0-4375-4322-40 ต่อ 2491, 2405</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <i class="text-white fa-solid fa-envelope"></i>
                                <span>library@msu.ac.th</span>
                            </li>
                        </ul>
                    </div>

                    <!-- คอลัมน์ที่ 4: ติดตามเรา -->
                    <div class="flex flex-col justify-between">
                        <div>
                            <h5 class="mb-3 text-sm font-bold tracking-wider uppercase text-slate-300 font-prompt">
                                ติดตามเรา
                            </h5>
                            <div class="grid grid-cols-4 gap-2.5 w-fit">
                                <a href="https://www.facebook.com/librarymsu" target="_blank" rel="noopener noreferrer" aria-label="Facebook"
                                    class="flex items-center justify-center transition-colors bg-white rounded-full w-9 h-9 hover:bg-amber-400 text-slate-900">
                                    <i class="fa-brands fa-facebook-f"></i>
                                </a>
                                <a href="https://m.me/librarymsu" target="_blank" rel="noopener noreferrer" aria-label="LINE"
                                    class="flex items-center justify-center transition-colors bg-white rounded-full w-9 h-9 hover:bg-amber-400 text-slate-900">
                                    <i class="fa-solid fa-comment"></i>
                                </a>
                                <a href="https://library.msu.ac.th" target="_blank" rel="noopener noreferrer" aria-label="เว็บไซต์"
                                    class="flex items-center justify-center transition-colors bg-white rounded-full w-9 h-9 hover:bg-amber-400 text-slate-900">
                                    <i class="fa-solid fa-globe"></i>
                                </a>
                                <a href="https://www.instagram.com/library_msu/" target="_blank" rel="noopener noreferrer" aria-label="Instagram"
                                    class="flex items-center justify-center transition-colors bg-white rounded-full w-9 h-9 hover:bg-amber-400 text-slate-900">
                                    <i class="fa-brands fa-instagram"></i>
                                </a>
                                <a href="mailto:library@msu.ac.th" target="_blank" rel="noopener noreferrer" aria-label="อีเมล"
                                    class="flex items-center justify-center transition-colors bg-white rounded-full w-9 h-9 hover:bg-amber-400 text-slate-900">
                                    <i class="fa-solid fa-envelope"></i>
                                </a>
                                <a href="https://www.youtube.com/@arecmsuchannel6833" target="_blank" rel="noopener noreferrer" aria-label="YouTube"
                                    class="flex items-center justify-center transition-colors bg-white rounded-full w-9 h-9 hover:bg-amber-400 text-slate-900">
                                    <i class="fa-brands fa-youtube"></i>
                                </a>
                                <a href="https://www.tiktok.com/@msu_library" target="_blank" rel="noopener noreferrer" aria-label="TikTok"
                                    class="flex items-center justify-center transition-colors bg-white rounded-full w-9 h-9 hover:bg-amber-400 text-slate-900">
                                    <i class="fa-brands fa-tiktok"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ลิขสิทธิ์ @2026 -->
                <div
                    class="flex flex-col items-center justify-between gap-2 pt-6 mt-8 text-xs border-t border-slate-800 sm:flex-row text-slate-500"
                >
                    <p>&copy; 2026 สำนักวิทยบริการ มหาวิทยาลัยมหาสารคาม. สงวนลิขสิทธิ์ทั้งหมด.</p>
                    <p class="text-[10px]">{{ t("footerDeveloped") }}</p>
                </div>
            </div>
        </footer>

        <!-- ================= MODALS SECTION ================= -->

        <!-- 1. คุกกี้ ยอมรับนโยบายความเป็นส่วนตัว (Cookie Consent Bar) -->
        <Transition name="fade">
        <div
            v-if="showCookieConsent"
            class="fixed inset-x-0 bottom-0 z-50 p-4 text-white border-t shadow-2xl bg-amber-400 backdrop-blur-md border-amber-300"
        >
            <div
                class="flex flex-col items-center justify-between gap-4 mx-auto max-w-7xl md:flex-row"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="bg-slate-900 text-amber-400 p-2 rounded-lg mt-0.5"
                    >
                        <i class="text-lg fa-solid fa-cookie-bite"></i>
                    </div>
                    <div>
                        <h4
                            class="text-sm font-bold text-slate-900 font-prompt"
                        >
                            {{ t("cookieTitle") }}
                        </h4>
                        <p
                            class="max-w-4xl mt-1 text-xs leading-relaxed text-slate-800"
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
                        class="w-1/2 px-4 py-2 text-xs font-semibold transition-all border rounded-lg border-slate-700 hover:bg-white hover:border-white text-slate-900 md:w-auto"
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
                <div class="flex items-center justify-between p-5 text-slate-900 bg-amber-400">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-calendar-check text-slate-900"></i>
                        <h3 class="text-sm font-bold font-prompt md:text-base">
                            {{ selectedRoom ? t("bookConfirmHeader") : "เลือกห้องที่ต้องการจอง" }}
                        </h3>
                    </div>
                    <button @click="closeModal('booking')" class="transition-colors text-slate-900 hover:text-white">
                        <i class="text-lg fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                    <!-- Zone name badge + ชุดอุปกรณ์มาตรฐานของโซน -->
                    <div class="p-3 border bg-slate-50 border-slate-200 rounded-xl">
                        <div class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">พื้นที่ • {{ selectedZone ? zoneLocationTitle(selectedZone) : '' }}</div>
                        <div class="font-bold text-slate-900 text-sm pt-0.5">{{ selectedZone ? zoneTitle(selectedZone) : '' }}</div>
                        <div v-if="selectedZone?.equipment?.length" class="pt-2 mt-2 border-t border-slate-200">
                            <div class="text-[10px] text-slate-400 font-semibold mb-1">ชุดอุปกรณ์ภายในโซน</div>
                            <div class="flex flex-wrap gap-1">
                                <span v-for="rt in selectedZone.equipment" :key="rt.tool_id"
                                    class="text-[10px] bg-white text-slate-600 border border-slate-200 px-1.5 py-0.5 rounded-full flex items-center gap-0.5">
                                    <i v-if="rt.icon" :class="`fa-solid ${rt.icon} text-[9px]`"></i>
                                    {{ rt.name }}<template v-if="rt.quantity > 1"> ×{{ rt.quantity }}</template>
                                </span>
                            </div>
                        </div>
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

                    <!-- โซน scan-only: ห้ามจองผ่านเว็บ ต้องสแกน QR ที่ตัวอุปกรณ์เท่านั้น -->
                    <div v-if="selectedZone?.scan_only === '1'"
                        class="flex flex-col items-center gap-3 p-6 text-center border bg-amber-50 border-amber-200 rounded-xl">
                        <i class="text-3xl text-amber-500 fa-solid fa-qrcode"></i>
                        <div>
                            <div class="text-sm font-bold text-amber-900">ต้องสแกน QR ที่ตัวอุปกรณ์เท่านั้นเพื่อทำการจอง</div>
                            <p class="mt-1 text-xs text-amber-700">โซนนี้ไม่รองรับการจองล่วงหน้าผ่านเว็บไซต์ — กรุณาไปที่จุดบริการแล้วสแกน QR code บนอุปกรณ์เพื่อจองและเช็คอินได้ทันที</p>
                        </div>
                    </div>

                    <!-- แจ้งเตือนถ้ายังไม่ล็อกอิน -->
                    <div v-else-if="!authUser" class="bg-orange-50 border border-orange-200 text-orange-800 text-xs p-3.5 rounded-lg flex items-start gap-2">
                        <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0"></i>
                        <div>
                            <span>{{ t("bookingLoginAlert") }}</span>
                            <a :href="`${appBase}/auth/google`" class="text-amber-600 hover:underline font-bold flex items-center gap-1 mt-1.5">
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
                            <span class="ml-1 font-normal text-slate-400">({{ selectedZone?.rooms?.length }} ห้อง)</span>
                        </p>

                        <!-- ── โหมด compact: โซนห้องเยอะ → grid ปุ่มเล็ก ── -->
                        <template v-if="useCompactRoomPicker">
                            <!-- badge ระดับโซน (ถ้าทุกห้องเหมือนกัน) -->
                            <div v-if="zoneUniformConfirmType || zoneUniformAccessControl" class="flex flex-wrap gap-1 pb-1">
                                <span v-if="zoneUniformConfirmType === 'auto'"
                                    class="text-[10px] font-semibold px-2 py-0.5 rounded-full border bg-emerald-100 text-emerald-700 border-emerald-200">
                                    <i class="fa-solid fa-circle-check mr-0.5"></i>ยืนยันทันที
                                </span>
                                <span v-else-if="zoneUniformConfirmType === 'manual'"
                                    class="text-[10px] font-semibold px-2 py-0.5 rounded-full border bg-amber-100 text-amber-700 border-amber-200">
                                    <i class="fa-solid fa-users mr-0.5"></i>ต้องครบกลุ่ม
                                </span>
                                <span v-if="zoneUniformAccessControl === '1'"
                                    class="text-[10px] font-semibold px-2 py-0.5 rounded-full border bg-amber-100 text-amber-700 border-amber-200">
                                    <i class="fa-solid fa-qrcode mr-0.5"></i>แสกน QR Code เพื่อเข้าใช้บริการ
                                </span>
                                <span v-else-if="zoneUniformAccessControl === '0'"
                                    class="text-[10px] font-semibold px-2 py-0.5 rounded-full border bg-slate-100 text-slate-600 border-slate-300">
                                    <i class="fa-solid fa-bell-concierge mr-0.5"></i>ติดต่อเจ้าหน้าที่ก่อนเข้าใช้บริการ
                                </span>
                            </div>

                            <div class="grid grid-cols-3 gap-1.5 sm:grid-cols-4">
                                <button
                                    v-for="room in selectedZone?.rooms"
                                    :key="room.id"
                                    type="button"
                                    :disabled="room.status === '1'"
                                    @click="room.status !== '1' && (selectedRoom = room)"
                                    :class="room.status === '1'
                                        ? 'bg-slate-100 border-slate-200 text-slate-400 cursor-not-allowed'
                                        : 'bg-white border-slate-200 text-slate-700 hover:border-amber-400 hover:bg-amber-50 cursor-pointer'"
                                    class="border rounded-lg px-1.5 py-2 text-[11px] font-semibold text-center transition-all leading-tight flex flex-col items-center gap-0.5"
                                >
                                    <span class="w-full truncate">{{ room.title }}</span>
                                    <span v-if="room.status === '1'" class="text-[9px] font-normal">ปิด</span>
                                    <span v-else class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                </button>
                            </div>
                            <p class="text-[10px] text-slate-400 pt-1">
                                <i class="mr-1 fa-regular fa-hand-pointer"></i>แตะเลือกห้อง แล้วดูรายละเอียด + เวลาที่ว่างในขั้นถัดไป
                            </p>
                        </template>

                        <!-- ── โหมดปกติ: การ์ดเต็ม ── -->
                        <template v-else>
                            <button
                                v-for="room in selectedZone?.rooms"
                                :key="room.id"
                                @click="room.status !== '1' && (selectedRoom = room)"
                                :disabled="room.status === '1'"
                                :class="room.status === '1'
                                    ? 'opacity-60 cursor-not-allowed border-slate-200 bg-slate-50'
                                    : 'hover:border-amber-400 hover:bg-amber-50 cursor-pointer'"
                                class="w-full text-left p-3.5 border border-slate-200 rounded-xl transition-all"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="text-sm font-bold text-slate-900">{{ room.title }}</div>
                                        <div v-if="room.detail" class="text-xs text-slate-500 mt-0.5">{{ room.detail }}</div>
                                    </div>
                                    <div class="flex flex-wrap justify-end gap-1 shrink-0 max-w-[55%]">
                                        <span v-if="room.status === '1'"
                                            class="text-[10px] font-semibold px-2 py-0.5 rounded-full border whitespace-nowrap bg-red-100 text-red-700 border-red-200">
                                            <i class="fa-solid fa-circle-xmark mr-0.5"></i>ไม่ว่าง
                                        </span>
                                        <template v-else>
                                            <span v-if="room.confirm_type === 'auto'"
                                                class="text-[10px] font-semibold px-2 py-0.5 rounded-full border whitespace-nowrap bg-emerald-100 text-emerald-700 border-emerald-200">
                                                <i class="fa-solid fa-circle-check mr-0.5"></i>ยืนยันทันที
                                            </span>
                                            <span v-else
                                                class="text-[10px] font-semibold px-2 py-0.5 rounded-full border whitespace-nowrap bg-amber-100 text-amber-700 border-amber-200">
                                                <i class="fa-solid fa-users mr-0.5"></i>ต้องครบกลุ่ม
                                            </span>
                                            <span v-if="room.access_control === '1'"
                                                class="text-[10px] font-semibold px-2 py-0.5 rounded-full border bg-amber-100 text-amber-700 border-amber-200">
                                                <i class="fa-solid fa-qrcode mr-0.5"></i>แสกน QR Code เพื่อเข้าใช้บริการ
                                            </span>
                                            <span v-else
                                                class="text-[10px] font-semibold px-2 py-0.5 rounded-full border bg-slate-100 text-slate-600 border-slate-300">
                                                <i class="fa-solid fa-bell-concierge mr-0.5"></i>ติดต่อเจ้าหน้าที่ก่อนเข้าใช้บริการ
                                            </span>
                                        </template>
                                    </div>
                                </div>
                                <div v-if="room.equipment?.length" class="mt-1.5 flex flex-wrap gap-1">
                                    <span
                                        v-for="rt in room.equipment"
                                        :key="rt.tool_id"
                                        class="text-[10px] bg-slate-100 text-slate-600 border border-slate-200 px-1.5 py-0.5 rounded-full flex items-center gap-0.5"
                                    >
                                        <i v-if="rt.icon" :class="`fa-solid ${rt.icon} text-[9px]`"></i>
                                        {{ rt.name }}<template v-if="rt.quantity > 1"> ×{{ rt.quantity }}</template>
                                    </span>
                                </div>
                            </button>
                        </template>
                    </div>

                    <!-- Step 2: เลือกวันและเวลา -->
                    <form v-else @submit.prevent="handleBookingSubmit" class="space-y-4">
                        <!-- ชื่อห้องที่เลือก -->
                        <div class="flex items-center gap-2">
                            <button type="button" @click="selectedRoom = null; availableTimes = []"
                                v-if="selectedZone?.rooms?.length > 1"
                                class="flex items-center gap-1 text-xs text-amber-600 hover:underline">
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
                        <div v-if="selectedRoom?.equipment?.length">
                            <label class="block mb-1.5 text-xs font-bold text-slate-700">
                                <i class="fa-solid fa-toolbox mr-1.5 text-slate-400"></i>อุปกรณ์ในห้อง
                            </label>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="rt in selectedRoom.equipment"
                                    :key="rt.tool_id"
                                    class="text-[11px] bg-amber-50 text-amber-700 border border-amber-200 px-2 py-1 rounded-full flex items-center gap-1 font-medium"
                                >
                                    <i v-if="rt.icon" :class="`fa-solid ${rt.icon} text-[10px]`"></i>
                                    {{ rt.name }}<template v-if="rt.quantity > 1"> ×{{ rt.quantity }}</template>
                                </span>
                            </div>
                        </div>

                        <!-- นอกเวลาทำการจอง -->
                        <div v-if="bookingClosed"
                            class="flex items-start gap-2.5 p-3.5 bg-orange-50 border border-orange-200 rounded-xl text-orange-800">
                            <i class="fa-solid fa-clock mt-0.5 shrink-0"></i>
                            <div class="text-xs leading-relaxed">
                                <div class="font-bold">ขณะนี้อยู่นอกเวลาทำการจอง</div>
                                <div class="mt-0.5">
                                    ระบบเปิดให้จองเวลา {{ bookingWindow.open }} – {{ bookingWindow.close }} น.
                                    (เวลาระบบตอนนี้ {{ bookingWindow.server_time }} น.)
                                </div>
                            </div>
                        </div>

                        <!-- กริด Time Slot จาก DB -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-xs font-bold text-slate-700">
                                    เลือกช่วงเวลา
                                    <span v-if="usedHoursToday > 0 || globalUsedHours > 0"
                                        class="ml-1.5 font-normal text-amber-600">
                                        (ใช้ไป {{ usedHoursToday }}/{{ totalQuota }} ชม. ในโซนนี้ · รวมวันนี้ {{ globalUsedHours }}/{{ globalDailyQuota }} ชม. — เหลือ {{ quota }} ชม.)
                                    </span>
                                    <span v-else class="ml-1.5 font-normal text-slate-400">
                                        (สูงสุด {{ Math.min(totalQuota, globalDailyQuota) }} ชม./วัน)
                                    </span>
                                </label>
                                <div class="flex items-center gap-3 text-[10px] text-slate-500">
                                    <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-slate-900"></span>เลือก</span>
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
                                <div class="text-[11px] text-amber-500 mt-0.5">
                                    {{ zoneRemaining <= 0
                                        ? `คุณใช้ครบ ${totalQuota} ชม./วัน ในโซนนี้แล้ว`
                                        : `คุณใช้ครบโควตารวมทุกโซนแล้ว (${globalDailyQuota} ชม./วัน)` }}
                                </div>
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
                                        'bg-slate-900 border-slate-900 text-white font-bold shadow-sm':          getSlotState(time.id) === 'selected',
                                        'bg-red-50 border-red-200 text-red-400 cursor-not-allowed line-through': getSlotState(time.id) === 'booked',
                                        'bg-slate-100 border-slate-200 text-slate-300 cursor-default':          getSlotState(time.id) === 'dim',
                                        'bg-white border-slate-200 text-slate-600 hover:border-amber-400 hover:bg-amber-50': getSlotState(time.id) === 'available',
                                    }"
                                    class="px-1 py-2.5 text-[11px] border rounded-lg text-center transition-all leading-tight font-medium"
                                >
                                    {{ time.start }}–{{ time.end }}
                                </button>
                            </div>

                        </div>

                        <!-- สรุปช่วงเวลาที่เลือก -->
                        <div v-if="bookingSummary" class="flex items-center gap-3 p-3.5 bg-amber-50 border border-amber-200 rounded-xl text-xs text-slate-900">
                            <i class="text-base text-amber-500 fa-solid fa-clock shrink-0"></i>
                            <div class="flex-1">
                                <div class="font-bold">{{ bookingSummary.start }} – {{ bookingSummary.end }} น.</div>
                                <div class="text-amber-500 mt-0.5">รวม {{ bookingSummary.hours }} ชั่วโมง (จากโควต้า {{ quota }} ชม.)</div>
                            </div>
                        </div>
                        <div v-else class="p-3 text-center text-[11px] text-slate-400 border border-dashed border-slate-200 rounded-lg">
                            <i class="mr-1 fa-regular fa-hand-pointer"></i>
                            แตะ slot เพื่อเลือก — เลือกได้สูงสุด {{ quota }} ชม. ต่อเนื่องกัน
                        </div>

                        <!-- Checkbox ยอมรับเงื่อนไข -->
                        <label class="flex items-start gap-2 pt-1 cursor-pointer">
                            <input v-model="bookingForm.terms" type="checkbox" required
                                class="rounded border-slate-300 text-slate-900 focus:ring-amber-400 mt-0.5" />
                            <span class="text-[11px] text-slate-500 leading-normal">{{ t("bookTerms") }}</span>
                        </label>

                        <!-- ปุ่มยืนยัน -->
                        <button
                            type="submit"
                            :disabled="!bookingSummary || props.todayIsHoliday || bookingClosed || isSubmitting"
                            :class="(!bookingSummary || props.todayIsHoliday || bookingClosed || isSubmitting) ? 'bg-slate-300 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700 cursor-pointer'"
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
                <div class="flex items-center justify-between p-5 text-slate-900 bg-amber-400">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-user-plus text-slate-900"></i>
                        <h3 class="text-sm font-bold font-prompt">แชร์ลิงก์ให้เพื่อนเข้าร่วม</h3>
                    </div>
                    <button @click="closeModal('joinShare')" class="transition-colors text-slate-700 hover:text-slate-900">
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

        <!-- ปุ่มเด้งกลับขึ้นบนสุด -->
        <Transition name="fade">
            <button
                v-if="showBackToTop"
                @click="scrollToTop"
                aria-label="กลับขึ้นบนสุด"
                class="fixed z-40 flex items-center justify-center text-white transition-all rounded-full shadow-lg bottom-6 right-6 w-11 h-11 bg-slate-900 hover:bg-amber-400 hover:text-slate-900"
            >
                <i class="fa-solid fa-arrow-up"></i>
            </button>
        </Transition>
    </div>
</template>

<style scoped>
/* คุณสามารถเพิ่ม CSS Scoped เพิ่มเติมได้ที่นี่หากต้องการ */
.font-prompt {
    font-family: "Anuphan", sans-serif;
}

/* ลูกศรคู่กระพริบชี้ลง ลอยเหนือแท็บ — สื่อว่าแท็บพื้นที่กดเลือกได้ */
@keyframes tabHint {
    0%, 100% { transform: translate(-50%, 0);   opacity: 0.4; }
    50%      { transform: translate(-50%, 5px); opacity: 1;   }
}
.tab-hint {
    animation: tabHint 1.4s ease-in-out infinite;
    /* สีเดียวกับ gradient พื้นหลังตอนเลือกแท็บ (rose-400 → fuchsia-500 → indigo-500) */
    color: #d946ef;
    background: linear-gradient(90deg, #fb7185, #d946ef, #6366f1);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}
@media (prefers-reduced-motion: reduce) {
    .tab-hint { animation: none; opacity: 0.6; transform: translate(-50%, 0); }
}

/* แถบประกาศสำคัญ — เลื่อนวิ่งซ้ายเป็น loop ไม่มีที่สิ้นสุด (เนื้อหาซ้ำ 2 ชุดต่อกัน เลื่อน -50% แล้ววนกลับ) */
@keyframes marqueeScroll {
    from { transform: translateX(0); }
    to   { transform: translateX(-50%); }
}
.marquee-track {
    animation: marqueeScroll 22s linear infinite;
}
.marquee-track:hover {
    animation-play-state: paused;
}
@media (prefers-reduced-motion: reduce) {
    .marquee-track { animation: none; }
    .marquee-track > *:nth-child(2) { display: none; }
}
</style>
