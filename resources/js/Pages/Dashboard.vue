<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

interface AdminUser { name: string; email: string; avatar: string | null; google_id: string }
interface Booking { id: number; studentName: string; studentId: string; studentType: string; zone: string; roomName: string; date: string; timeSlot: string; status: 'pending' | 'approved' | 'rejected' }
interface Room { id: number; name: string; zone: string; active: boolean }
interface Member { id: number; name: string; email: string; code: string; type: string; faculty: string; totalBookings: number; lastBooking: string }
interface Holiday { id: number; date: string; name: string; type: 'national' | 'library' }
interface ServiceDay { day: string; isOpen: boolean; openTime: string; closeTime: string }
interface Feedback { id: number; studentInfo: string; stars: number; comment: string }
interface ToastState { show: boolean; title: string; desc: string; isError: boolean }
type TabId = 'overview' | 'bookings' | 'members' | 'rooms' | 'holidays' | 'service_hours' | 'reports' | 'manual'

// Auth
const page = usePage()
const admin = computed<AdminUser | null>(() => (page.props as any).auth?.admin ?? null)
const adminInitials = computed(() => {
  if (!admin.value?.name) return 'AD'
  return admin.value.name.split(' ').map((n: string) => n[0]).join('').substring(0, 2).toUpperCase()
})

// Mobile-aware sidebar
const isMobile = ref(false)
const isSidebarCollapsed = ref(false)
const updateMobile = () => {
  isMobile.value = window.innerWidth < 768
  if (isMobile.value) isSidebarCollapsed.value = true
}
onMounted(() => { updateMobile(); window.addEventListener('resize', updateMobile) })
onUnmounted(() => window.removeEventListener('resize', updateMobile))

const currentTab = ref<TabId>('overview')

// Mock data
const bookings = ref<Booking[]>([
  { id: 1, studentName: 'นายอนุชิต วงศ์สุวรรณ', studentId: '64010912140', studentType: 'นิสิต ป.ตรี', zone: 'โซนอาคารหลัก', roomName: 'โต๊ะรวมกลุ่มเล็ก (Zone B)', date: '27 พฤษภาคม 2569', timeSlot: '13:00 – 15:00 น.', status: 'pending' },
  { id: 2, studentName: 'นส. ธัญญาพร ประเสริฐสังข์', studentId: '65011244512', studentType: 'นิสิต ป.ตรี', zone: 'Co-Working Space', roomName: 'มุมคอมพิวเตอร์กราฟิก Mac Suite', date: '27 พฤษภาคม 2569', timeSlot: '09:00 – 11:00 น.', status: 'pending' },
  { id: 3, studentName: 'นายธนาธิป สุวรรณสิงห์', studentId: '63011311025', studentType: 'บัณฑิตศึกษา', zone: 'ห้องศึกษากลุ่ม', roomName: 'ห้องศึกษากลุ่มขนาดใหญ่ (Group Room L)', date: '28 พฤษภาคม 2569', timeSlot: '14:00 – 17:00 น.', status: 'pending' },
])

const rooms = ref<Room[]>([
  { id: 1, name: 'คอกศึกษาเดี่ยว (Booth 1-10)', zone: 'โซนอาคารหลัก (ชั้น 1)', active: true },
  { id: 2, name: 'โต๊ะรวมกลุ่มเล็ก (Zone B)', zone: 'โซนอาคารหลัก (ชั้น 1)', active: true },
  { id: 3, name: 'ห้องสัมมนาวิชาการ (Zone C)', zone: 'โซนอาคารหลัก (ชั้น 1)', active: true },
  { id: 4, name: 'พื้นที่แลกเปลี่ยนไอเดีย', zone: 'โซน Co-Working Space', active: true },
  { id: 5, name: 'มุมคอมพิวเตอร์กราฟิก Mac Suite', zone: 'โซน Co-Working Space', active: true },
  { id: 6, name: 'พื้นที่พักผ่อน Relax & Read', zone: 'โซน Co-Working Space', active: false },
  { id: 7, name: 'ห้องศึกษากลุ่มย่อย Room M', zone: 'โซนห้องศึกษากลุ่ม (ชั้น 3-4)', active: true },
  { id: 8, name: 'ห้องศึกษากลุ่มใหญ่ Room L', zone: 'โซนห้องศึกษากลุ่ม (ชั้น 3-4)', active: true },
  { id: 9, name: 'ห้อง Mini Theater', zone: 'โซนห้องศึกษากลุ่ม (ชั้น 3-4)', active: false },
])

const members = ref<Member[]>([
  { id: 1, name: 'นายอนุชิต วงศ์สุวรรณ', email: 'anuchit.w@msu.ac.th', code: '64010912140', type: 'นิสิต ป.ตรี', faculty: 'คณะวิทยาการสารสนเทศ', totalBookings: 12, lastBooking: '27 พ.ค. 2569' },
  { id: 2, name: 'นส. ธัญญาพร ประเสริฐสังข์', email: 'thanyaporn.p@msu.ac.th', code: '65011244512', type: 'นิสิต ป.ตรี', faculty: 'คณะมนุษยศาสตร์ฯ', totalBookings: 8, lastBooking: '27 พ.ค. 2569' },
  { id: 3, name: 'นายธนาธิป สุวรรณสิงห์', email: 'thanathip.s@msu.ac.th', code: '63011311025', type: 'บัณฑิตศึกษา', faculty: 'คณะวิทยาศาสตร์', totalBookings: 25, lastBooking: '26 พ.ค. 2569' },
  { id: 4, name: 'นส. พิมพ์ชนก รักเรียน', email: 'pimchanok.r@msu.ac.th', code: '66010811034', type: 'นิสิต ป.ตรี', faculty: 'คณะศึกษาศาสตร์', totalBookings: 3, lastBooking: '24 พ.ค. 2569' },
  { id: 5, name: 'ผศ.ดร. วรากร ทองดี', email: 'warakorn.t@msu.ac.th', code: 'STF-20145', type: 'บุคลากร/อาจารย์', faculty: 'คณะวิศวกรรมศาสตร์', totalBookings: 6, lastBooking: '25 พ.ค. 2569' },
])

const holidays = ref<Holiday[]>([
  { id: 1, date: '5 มิถุนายน 2569', name: 'วันเฉลิมพระชนมพรรษา สมเด็จพระราชินี', type: 'national' },
  { id: 2, date: '28 กรกฎาคม 2569', name: 'วันเฉลิมพระชนมพรรษา รัชกาลที่ 10', type: 'national' },
  { id: 3, date: '12 สิงหาคม 2569', name: 'วันแม่แห่งชาติ', type: 'national' },
  { id: 4, date: '15–16 สิงหาคม 2569', name: 'ปิดปรับปรุงระบบประจำปี', type: 'library' },
  { id: 5, date: '23 ตุลาคม 2569', name: 'วันปิยมหาราช', type: 'national' },
  { id: 6, date: '5 ธันวาคม 2569', name: 'วันพ่อแห่งชาติ', type: 'national' },
])

const serviceHours = ref<ServiceDay[]>([
  { day: 'จันทร์', isOpen: true, openTime: '08:00', closeTime: '21:00' },
  { day: 'อังคาร', isOpen: true, openTime: '08:00', closeTime: '21:00' },
  { day: 'พุธ', isOpen: true, openTime: '08:00', closeTime: '21:00' },
  { day: 'พฤหัสบดี', isOpen: true, openTime: '08:00', closeTime: '21:00' },
  { day: 'ศุกร์', isOpen: true, openTime: '08:00', closeTime: '20:00' },
  { day: 'เสาร์', isOpen: true, openTime: '09:00', closeTime: '17:00' },
  { day: 'อาทิตย์', isOpen: false, openTime: '09:00', closeTime: '17:00' },
])

const feedbacks = ref<Feedback[]>([
  { id: 1, studentInfo: 'นิสิต คณะวิทยาการสารสนเทศ • 640109xxxx', stars: 5, comment: 'ระบบจองออนไลน์ช่วยให้จองโต๊ะได้สะดวกมากค่ะ ขอบคุณมากๆ ค่ะ' },
  { id: 2, studentInfo: 'นิสิต คณะมนุษยศาสตร์ฯ • 650112xxxx', stars: 4, comment: 'อยากให้มีแจ้งเตือน Line Notify ก่อนถึงเวลาจองสัก 15 นาทีค่ะ' },
])

const manualOpenSections = ref<number[]>([0])
const toggleManualSection = (idx: number) => {
  if (manualOpenSections.value.includes(idx)) {
    manualOpenSections.value = manualOpenSections.value.filter(i => i !== idx)
  } else {
    manualOpenSections.value.push(idx)
  }
}

const toast = ref<ToastState>({ show: false, title: '', desc: '', isError: false })
let toastTimeout: ReturnType<typeof setTimeout> | null = null

// Computed stats
const pendingCount = computed(() => bookings.value.filter(b => b.status === 'pending').length)
const baseApproved = ref(24)
const approvedCount = computed(() => baseApproved.value + bookings.value.filter(b => b.status === 'approved').length)
const activeRooms = computed(() => rooms.value.filter(r => r.active).length)

const groupedRooms = computed(() => {
  const zones = ['โซนอาคารหลัก (ชั้น 1)', 'โซน Co-Working Space', 'โซนห้องศึกษากลุ่ม (ชั้น 3-4)']
  return zones.map(z => ({ name: z, items: rooms.value.filter(r => r.zone === z) }))
})

const memberInitials = (name: string) =>
  name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()

// Actions
const toggleSidebar = () => { isSidebarCollapsed.value = !isSidebarCollapsed.value }
const switchTab = (id: TabId) => {
  currentTab.value = id
  if (isMobile.value) isSidebarCollapsed.value = true
}

const updateBookingStatus = (id: number, action: 'approve' | 'reject') => {
  const b = bookings.value.find(b => b.id === id)
  if (!b) return
  b.status = action === 'approve' ? 'approved' : 'rejected'
  showToast(
    action === 'approve' ? 'อนุมัติคิวสำเร็จ' : 'ปฏิเสธเรียบร้อย',
    action === 'approve' ? 'ส่งอีเมลยืนยันสิทธิ์แก่ผู้จองแล้ว' : 'แจ้งผู้จองทราบเรียบร้อยแล้ว',
    action !== 'approve'
  )
}

const toggleRoom = (id: number) => {
  const r = rooms.value.find(r => r.id === id)
  if (!r) return
  r.active = !r.active
  showToast(r.active ? 'เปิดใช้งานแล้ว' : 'ปิดให้บริการชั่วคราว', r.active ? 'ห้องพร้อมแสดงบนเว็บหลัก' : 'ห้องนี้ซ่อนจากหน้าจองแล้ว', !r.active)
}

const toggleServiceDay = (idx: number) => {
  serviceHours.value[idx].isOpen = !serviceHours.value[idx].isOpen
  const d = serviceHours.value[idx]
  showToast(d.isOpen ? `เปิดบริการวัน${d.day}` : `ปิดบริการวัน${d.day}`, d.isOpen ? 'อัปเดตตารางเรียบร้อย' : 'วันนี้ไม่มีการให้บริการ', !d.isOpen)
}

const deleteHoliday = (id: number) => {
  holidays.value = holidays.value.filter(h => h.id !== id)
  showToast('ลบวันหยุดแล้ว', 'อัปเดตตารางวันหยุดเรียบร้อย', false)
}

const showToast = (title: string, desc: string, isError = false) => {
  toast.value = { show: true, title, desc, isError }
  if (toastTimeout) clearTimeout(toastTimeout)
  toastTimeout = setTimeout(() => hideToast(), 5000)
}
const hideToast = () => { toast.value.show = false }

const logoutAdmin = async () => {
  const result = await Swal.fire({
    title: 'ออกจากระบบ?',
    text: 'คุณต้องการออกจากระบบหลังบ้านใช่หรือไม่',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#1e3a5f',
    cancelButtonColor: '#94a3b8',
    confirmButtonText: 'ใช่, ออกจากระบบ',
    cancelButtonText: 'ยกเลิก',
    reverseButtons: true,
  })
  if (result.isConfirmed) router.post('/logout')
}
</script>

<template>
  <div class="flex min-h-screen bg-slate-100 text-slate-800 font-anuphan">

    <!-- Overlay backdrop on mobile when sidebar open -->
    <div
      v-if="isMobile && !isSidebarCollapsed"
      class="fixed inset-0 z-20 bg-black/40 backdrop-blur-sm"
      @click="isSidebarCollapsed = true"
    ></div>

    <!-- ===== SIDEBAR ===== -->
    <aside
      :class="[
        isSidebarCollapsed ? (isMobile ? 'w-0 overflow-hidden' : 'w-20') : 'w-64',
        isMobile ? 'fixed inset-y-0 left-0 z-30' : 'relative'
      ]"
      class="flex flex-col justify-between transition-all duration-300 ease-in-out border-r shadow-xl bg-slate-900 text-slate-300 border-slate-800 shrink-0"
    >
      <!-- Logo header -->
      <div>
        <!-- Amber identity stripe at top -->
        <div class="h-[3px] bg-gradient-to-r from-amber-500 via-amber-400 to-amber-600"></div>
        <div class="flex items-center justify-between p-4 border-b bg-slate-950 border-slate-800">
          <div class="flex items-center gap-2 overflow-hidden">
            <div class="bg-amber-500 text-slate-950 font-bold p-2.5 rounded-lg text-sm shrink-0 shadow-lg">MSU</div>
            <div v-show="!isSidebarCollapsed" class="transition-opacity duration-200">
              <span class="block text-xs font-bold leading-tight tracking-wider text-white">ADMIN BACKEND</span>
              <span class="text-[9px] text-amber-400 font-medium">MSU Library Portal</span>
            </div>
          </div>
        </div>

        <!-- Nav -->
        <nav class="p-3 space-y-0.5 overflow-y-auto" style="max-height: calc(100vh - 160px)">

          <!-- Section: ระบบหลัก -->
          <p v-show="!isSidebarCollapsed" class="px-3 pt-3 pb-1 text-[9px] font-bold uppercase tracking-widest text-slate-500">ระบบหลัก</p>
          <button @click="switchTab('overview')" :class="currentTab === 'overview' ? 'nav-active text-white' : 'hover:bg-slate-800 hover:text-white'" class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl">
            <i class="w-4 text-sm text-center fa-solid fa-chart-line" :class="currentTab==='overview'?'text-amber-400':''"></i>
            <span v-show="!isSidebarCollapsed">ภาพรวมระบบ</span>
          </button>

          <!-- Section: การจัดการ -->
          <p v-show="!isSidebarCollapsed" class="px-3 pt-4 pb-1 text-[9px] font-bold uppercase tracking-widest text-slate-500">การจัดการ</p>
          <button @click="switchTab('bookings')" :class="currentTab === 'bookings' ? 'nav-active text-white' : 'hover:bg-slate-800 hover:text-white'" class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl">
            <i class="w-4 text-sm text-center fa-solid fa-calendar-check" :class="currentTab==='bookings'?'text-amber-400':''"></i>
            <span v-show="!isSidebarCollapsed">จัดการคำขอจอง</span>
            <span v-if="pendingCount > 0" :class="isSidebarCollapsed ? 'absolute top-1.5 right-1.5' : 'ml-auto'" class="bg-amber-500 text-slate-950 px-1.5 py-0.5 rounded-full text-[9px] font-extrabold">{{ pendingCount }}</span>
          </button>
          <button @click="switchTab('members')" :class="currentTab === 'members' ? 'nav-active text-white' : 'hover:bg-slate-800 hover:text-white'" class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl">
            <i class="w-4 text-sm text-center fa-solid fa-id-card" :class="currentTab==='members'?'text-amber-400':''"></i>
            <span v-show="!isSidebarCollapsed">จัดการสมาชิก</span>
          </button>

          <!-- Section: ตั้งค่าระบบ -->
          <p v-show="!isSidebarCollapsed" class="px-3 pt-4 pb-1 text-[9px] font-bold uppercase tracking-widest text-slate-500">ตั้งค่าระบบ</p>
          <button @click="switchTab('rooms')" :class="currentTab === 'rooms' ? 'nav-active text-white' : 'hover:bg-slate-800 hover:text-white'" class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl">
            <i class="w-4 text-sm text-center fa-solid fa-building-columns" :class="currentTab==='rooms'?'text-amber-400':''"></i>
            <span v-show="!isSidebarCollapsed">จัดการพื้นที่ห้องบริการ</span>
          </button>
          <button @click="switchTab('holidays')" :class="currentTab === 'holidays' ? 'nav-active text-white' : 'hover:bg-slate-800 hover:text-white'" class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl">
            <i class="w-4 text-sm text-center fa-solid fa-calendar-xmark" :class="currentTab==='holidays'?'text-amber-400':''"></i>
            <span v-show="!isSidebarCollapsed">วันหยุด & ปิดให้บริการ</span>
          </button>
          <button @click="switchTab('service_hours')" :class="currentTab === 'service_hours' ? 'nav-active text-white' : 'hover:bg-slate-800 hover:text-white'" class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl">
            <i class="w-4 text-sm text-center fa-solid fa-business-time" :class="currentTab==='service_hours'?'text-amber-400':''"></i>
            <span v-show="!isSidebarCollapsed">เวลาให้บริการ</span>
          </button>

          <!-- Section: รายงาน -->
          <p v-show="!isSidebarCollapsed" class="px-3 pt-4 pb-1 text-[9px] font-bold uppercase tracking-widest text-slate-500">รายงาน & ข้อมูล</p>
          <button @click="switchTab('reports')" :class="currentTab === 'reports' ? 'nav-active text-white' : 'hover:bg-slate-800 hover:text-white'" class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl">
            <i class="w-4 text-sm text-center fa-solid fa-star-half-stroke" :class="currentTab==='reports'?'text-amber-400':''"></i>
            <span v-show="!isSidebarCollapsed">ผลประเมิน & รายงาน</span>
          </button>
          <button @click="switchTab('manual')" :class="currentTab === 'manual' ? 'nav-active text-white' : 'hover:bg-slate-800 hover:text-white'" class="relative flex items-center w-full gap-3 px-4 py-3 text-xs font-semibold transition-all rounded-xl">
            <i class="w-4 text-sm text-center fa-solid fa-book-open" :class="currentTab==='manual'?'text-amber-400':''"></i>
            <span v-show="!isSidebarCollapsed">คู่มือการใช้งาน</span>
          </button>
        </nav>
      </div>

      <!-- Sidebar footer: admin info -->
      <div class="p-3 border-t border-slate-800 bg-slate-950/40">
        <div class="flex items-center gap-3 p-2 rounded-xl bg-slate-900/65">
          <img v-if="admin?.avatar" :src="admin.avatar" :alt="admin.name" class="object-cover rounded-full shadow w-9 h-9 shrink-0 ring-2 ring-amber-500/50" />
          <div v-else class="flex items-center justify-center text-xs font-bold text-white rounded-full shadow w-9 h-9 bg-gradient-to-tr from-blue-600 to-amber-500 shrink-0">{{ adminInitials }}</div>
          <div v-show="!isSidebarCollapsed" class="overflow-hidden">
            <h5 class="font-bold text-[11px] text-white leading-tight truncate">{{ admin?.name ?? 'ผู้ดูแลระบบ' }}</h5>
            <p class="text-[9px] text-slate-500">ผู้ดูแลระบบห้องสมุด มมส</p>
          </div>
          <button @click="logoutAdmin" v-show="!isSidebarCollapsed" class="p-1 ml-auto transition-colors text-slate-500 hover:text-red-400" title="ออกจากระบบ">
            <i class="text-xs fa-solid fa-power-off"></i>
          </button>
        </div>
      </div>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="flex flex-col flex-grow min-w-0">

      <!-- Header -->
      <header class="sticky top-0 z-20 flex items-center justify-between px-4 py-3 bg-white border-b-2 shadow-sm border-amber-200 md:px-6">
        <div class="flex items-center gap-3">
          <button @click="toggleSidebar" class="flex items-center justify-center w-10 h-10 transition-all border shadow-inner rounded-xl hover:bg-slate-100 text-slate-600 active:scale-95 border-slate-200">
            <i class="fa-solid" :class="isSidebarCollapsed ? 'fa-bars' : 'fa-bars-staggered'"></i>
          </button>         
        </div>
        <div class="flex items-center gap-2 md:gap-3">
          <div class="flex items-center gap-2 pl-2 border-l border-slate-200 md:pl-3">
            <img v-if="admin?.avatar" :src="admin.avatar" :alt="admin.name" class="object-cover rounded-full shadow-sm w-9 h-9 ring-2 ring-blue-900/20" />
            <div v-else class="flex items-center justify-center text-xs font-bold text-white rounded-full shadow-sm w-9 h-9 bg-gradient-to-tr from-blue-700 to-indigo-500">{{ adminInitials }}</div>
            <div class="hidden md:block">
              <p class="text-xs font-bold leading-tight text-slate-900">{{ admin?.name ?? 'ผู้ดูแลระบบ' }}</p>
              <p class="text-[10px] text-slate-400 leading-tight">{{ admin?.email ?? '' }}</p>
            </div>
            <button @click="logoutAdmin" class="hidden ml-1 text-xs transition-colors text-slate-400 hover:text-red-500 md:block" title="ออกจากระบบ">
              <i class="fa-solid fa-right-from-bracket"></i>
            </button>
          </div>
        </div>
      </header>

      <!-- Tab Content -->
      <main class="flex-grow p-4 space-y-5 overflow-y-auto md:p-6">

        <!-- ===== TAB: ภาพรวมระบบ ===== -->
        <div v-show="currentTab === 'overview'" class="space-y-5">

          <!-- Welcome banner -->
          <div class="relative p-6 overflow-hidden text-white shadow-lg bg-gradient-to-r from-blue-900 to-indigo-900 rounded-2xl">
            <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:12px_12px]"></div>
            <div class="relative z-10 flex flex-col justify-between gap-4 md:flex-row md:items-center">
              <div>
                <h2 class="text-lg font-bold md:text-xl text-amber-400">ยินดีต้อนรับกลับมา, {{ admin?.name ?? 'แอดมิน' }}!</h2>
                <p class="max-w-xl mt-1 text-xs text-slate-200">ภาพรวมการจองพื้นที่ทั้งหมด — โซนศึกษาหลัก, Co-working Space และห้องศึกษากลุ่ม วันนี้</p>
              </div>
              <span class="bg-blue-950/80 border border-blue-700 text-green-400 font-bold px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 shrink-0 w-fit">
                <i class="fa-solid fa-circle text-[8px] animate-pulse"></i> เชื่อมต่อฐานข้อมูลปกติ
              </span>
            </div>
          </div>

          <!-- Primary KPI cards -->
          <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="flex items-center justify-between p-5 bg-white border border-l-4 shadow-sm rounded-2xl border-slate-200 border-l-amber-500">
              <div class="space-y-1">
                <p class="text-xs font-semibold text-slate-400">รออนุมัติ</p>
                <h3 class="text-2xl font-bold text-slate-900">{{ pendingCount }}</h3>
                <span class="text-[10px] bg-amber-50 text-amber-600 px-2 py-0.5 rounded border border-amber-200 font-bold"><i class="mr-1 fa-solid fa-clock"></i>รอดำเนินการ</span>
              </div>
              <div class="flex items-center justify-center text-lg w-11 h-11 bg-amber-500/10 text-amber-600 rounded-xl"><i class="fa-solid fa-user-clock"></i></div>
            </div>
            <div class="flex items-center justify-between p-5 bg-white border border-l-4 shadow-sm rounded-2xl border-slate-200 border-l-green-500">
              <div class="space-y-1">
                <p class="text-xs font-semibold text-slate-400">อนุมัติวันนี้</p>
                <h3 class="text-2xl font-bold text-slate-900">{{ approvedCount }}</h3>
                <span class="text-[10px] bg-green-50 text-green-600 px-2 py-0.5 rounded border border-green-200 font-bold"><i class="mr-1 fa-solid fa-arrow-trend-up"></i>+12% เมื่อวาน</span>
              </div>
              <div class="flex items-center justify-center text-lg text-green-600 w-11 h-11 bg-green-500/10 rounded-xl"><i class="fa-solid fa-calendar-check"></i></div>
            </div>
            <div class="flex items-center justify-between p-5 bg-white border border-l-4 shadow-sm rounded-2xl border-slate-200 border-l-red-400">
              <div class="space-y-1">
                <p class="text-xs font-semibold text-slate-400">ยกเลิกอัตโนมัติ</p>
                <h3 class="text-2xl font-bold text-slate-900">2</h3>
                <span class="text-[10px] bg-red-50 text-red-600 px-2 py-0.5 rounded border border-red-200 font-bold"><i class="mr-1 fa-solid fa-ban"></i>ไม่เช็คอินวันนี้</span>
              </div>
              <div class="flex items-center justify-center text-lg text-red-500 w-11 h-11 bg-red-500/10 rounded-xl"><i class="fa-solid fa-calendar-xmark"></i></div>
            </div>
            <div class="flex items-center justify-between p-5 bg-white border border-l-4 shadow-sm rounded-2xl border-slate-200 border-l-blue-600">
              <div class="space-y-1">
                <p class="text-xs font-semibold text-slate-400">ห้องเปิดบริการ</p>
                <h3 class="text-2xl font-bold text-slate-900">{{ activeRooms }}<span class="text-base text-slate-400">/{{ rooms.length }}</span></h3>
                <span class="text-[10px] bg-blue-50 text-blue-600 px-2 py-0.5 rounded border border-blue-200 font-bold"><i class="mr-1 fa-solid fa-door-open"></i>พร้อมให้จอง</span>
              </div>
              <div class="flex items-center justify-center text-lg text-blue-600 w-11 h-11 bg-blue-500/10 rounded-xl"><i class="fa-solid fa-building-columns"></i></div>
            </div>
          </div>

          <!-- Secondary insight cards -->
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="flex items-center gap-4 p-4 bg-white border shadow-sm rounded-2xl border-slate-200">
              <div class="flex items-center justify-center w-10 h-10 text-teal-600 bg-teal-500/10 rounded-xl shrink-0"><i class="fa-solid fa-user-check"></i></div>
              <div>
                <p class="text-[11px] text-slate-400 font-semibold">อัตราเช็คอินสำเร็จ</p>
                <p class="text-lg font-bold text-teal-700">95.6%</p>
                <p class="text-[10px] text-slate-400">เฉลี่ยสัปดาห์นี้</p>
              </div>
            </div>
            <div class="flex items-center gap-4 p-4 bg-white border shadow-sm rounded-2xl border-slate-200">
              <div class="flex items-center justify-center w-10 h-10 text-purple-600 bg-purple-500/10 rounded-xl shrink-0"><i class="fa-solid fa-users"></i></div>
              <div>
                <p class="text-[11px] text-slate-400 font-semibold">สมาชิกทั้งหมด</p>
                <p class="text-lg font-bold text-purple-700">{{ members.length }} <span class="text-xs font-normal text-slate-400">ราย</span></p>
                <p class="text-[10px] text-slate-400">ใช้งานแล้วอย่างน้อย 1 ครั้ง</p>
              </div>
            </div>
            <div class="flex items-center gap-4 p-4 bg-white border shadow-sm rounded-2xl border-slate-200">
              <div class="flex items-center justify-center w-10 h-10 text-orange-600 bg-orange-500/10 rounded-xl shrink-0"><i class="fa-solid fa-fire-flame-curved"></i></div>
              <div>
                <p class="text-[11px] text-slate-400 font-semibold">ช่วงเวลาพีควันนี้</p>
                <p class="text-lg font-bold text-orange-600">13:00–15:00</p>
                <p class="text-[10px] text-slate-400">ยอดจองสูงสุดของวัน</p>
              </div>
            </div>
          </div>

          <!-- Charts -->
          <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
            <div class="p-6 space-y-4 bg-white border shadow-sm lg:col-span-8 rounded-2xl border-slate-200">
              <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h4 class="flex items-center gap-2 text-sm font-bold text-slate-900"><i class="text-blue-900 fa-solid fa-chart-column"></i> สถิติการจองแยกโซน (สัปดาห์นี้)</h4>
                <span class="text-[11px] text-slate-400">หน่วย: ครั้ง</span>
              </div>
              <div class="pt-2 space-y-4">
                <div class="space-y-1.5">
                  <div class="flex justify-between text-xs"><span class="font-medium text-slate-700">อาคารวิทยบริการ A (โซนหลัก)</span><span class="font-bold text-blue-900">142 ครั้ง</span></div>
                  <div class="w-full h-3 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-blue-700 to-blue-950" style="width:68%"></div></div>
                </div>
                <div class="space-y-1.5">
                  <div class="flex justify-between text-xs"><span class="font-medium text-slate-700">อาคารวิทยบริการ B (Co-Working Space)</span><span class="font-bold text-orange-500">210 ครั้ง</span></div>
                  <div class="w-full h-3 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-orange-400 to-amber-600" style="width:100%"></div></div>
                </div>
                <div class="space-y-1.5">
                  <div class="flex justify-between text-xs"><span class="font-medium text-slate-700">MSU SPACE (ห้องศึกษากลุ่ม)</span><span class="font-bold text-green-600">95 ครั้ง</span></div>
                  <div class="w-full h-3 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-green-400 to-emerald-600" style="width:45%"></div></div>
                </div>
              </div>
              <div class="pt-3 border-t border-slate-100">
                <p class="text-[11px] text-slate-400"><i class="mr-1 text-blue-400 fa-solid fa-circle-info"></i>รวมทั้งสัปดาห์: <strong class="text-slate-700">447 ครั้ง</strong> — เพิ่มขึ้น 8.3% จากสัปดาห์ก่อน</p>
              </div>
            </div>
            <div class="flex flex-col justify-between p-6 bg-white border shadow-sm lg:col-span-4 rounded-2xl border-slate-200">
              <div>
                <h4 class="flex items-center gap-2 pb-3 text-sm font-bold border-b text-slate-900 border-slate-100"><i class="text-orange-500 fa-solid fa-chart-pie"></i> สัดส่วนประเภทผู้ใช้</h4>
                <div class="flex justify-center py-4">
                  <svg class="transform -rotate-90 w-28 h-28">
                    <circle cx="56" cy="56" r="44" fill="transparent" stroke="#f1f5f9" stroke-width="12" />
                    <circle cx="56" cy="56" r="44" fill="transparent" stroke="#1e40af" stroke-width="12" stroke-dasharray="276.46" stroke-dashoffset="55.29" />
                    <circle cx="56" cy="56" r="44" fill="transparent" stroke="#f97316" stroke-width="12" stroke-dasharray="276.46" stroke-dashoffset="220.78" />
                  </svg>
                </div>
              </div>
              <div class="pt-2 space-y-2 text-xs border-t border-slate-50">
                <div class="flex items-center justify-between"><div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded bg-blue-900"></span><span class="text-slate-600">นิสิต ป.ตรี</span></div><span class="font-bold text-slate-700">80%</span></div>
                <div class="flex items-center justify-between"><div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded bg-orange-500"></span><span class="text-slate-600">ป.โท–เอก / บุคลากร</span></div><span class="font-bold text-slate-700">20%</span></div>
              </div>
            </div>
          </div>
        </div>

        <!-- ===== TAB: จัดการคำขอจอง ===== -->
        <div v-show="currentTab === 'bookings'" class="space-y-5">
          <div class="overflow-hidden bg-white border shadow-sm rounded-2xl border-slate-200">
            <div class="flex flex-col items-start justify-between gap-3 p-5 border-b border-slate-200 sm:flex-row sm:items-center">
              <div>
                <h3 class="text-base font-bold text-slate-900">คิวคำขอจองล่าสุด</h3>
                <p class="mt-0.5 text-xs text-slate-500">ตรวจสอบ ประเมินสิทธิ์ และดำเนินการอนุมัติหรือปฏิเสธได้ทันที</p>
              </div>
              <span class="text-xs bg-amber-50 border border-amber-200 text-amber-700 px-3 py-1.5 rounded-xl font-bold shrink-0">{{ pendingCount }} รายการรอดำเนินการ</span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left border-collapse">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-200 text-[11px] text-slate-500 font-bold uppercase tracking-wider">
                    <th class="p-4">ผู้ยื่นคำขอ</th>
                    <th class="p-4">พื้นที่ / ห้อง</th>
                    <th class="p-4">วันและเวลา</th>
                    <th class="p-4 text-center">สถานะ</th>
                    <th class="p-4 text-right">การจัดการ</th>
                  </tr>
                </thead>
                <tbody class="text-xs divide-y divide-slate-100 text-slate-600">
                  <tr v-for="item in bookings" :key="item.id" class="transition-colors hover:bg-slate-50/70">
                    <td class="p-4"><div class="font-bold text-slate-900">{{ item.studentName }}</div><div class="text-[10px] text-slate-400">{{ item.studentType }} • {{ item.studentId }}</div></td>
                    <td class="p-4">
                      <span :class="{'bg-blue-50 text-blue-900 border-blue-200': item.zone.includes('หลัก'),'bg-orange-50 text-orange-900 border-orange-200': item.zone.includes('Co-Working'),'bg-green-50 text-green-900 border-green-200': item.zone.includes('กลุ่ม')}" class="font-bold px-2 py-0.5 rounded text-[10px] border">{{ item.zone }}</span>
                      <div class="mt-1 font-semibold text-slate-800">{{ item.roomName }}</div>
                    </td>
                    <td class="p-4"><div>{{ item.date }}</div><div class="font-bold text-slate-900 mt-0.5"><i class="mr-1 fa-solid fa-clock text-slate-400"></i>{{ item.timeSlot }}</div></td>
                    <td class="p-4 text-center">
                      <span v-if="item.status==='pending'" class="bg-amber-100 text-amber-800 font-bold px-2.5 py-1 rounded-full text-[10px] inline-flex items-center gap-1 border border-amber-200 animate-pulse"><i class="fa-solid fa-circle-pause text-[7px]"></i> รอดำเนินการ</span>
                      <span v-else-if="item.status==='approved'" class="bg-green-100 text-green-800 font-bold px-2.5 py-1 rounded-full text-[10px] inline-flex items-center gap-1 border border-green-200"><i class="fa-solid fa-circle-check text-[7px]"></i> อนุมัติสำเร็จ</span>
                      <span v-else class="bg-red-100 text-red-800 font-bold px-2.5 py-1 rounded-full text-[10px] inline-flex items-center gap-1 border border-red-200"><i class="fa-solid fa-circle-xmark text-[7px]"></i> ปฏิเสธแล้ว</span>
                    </td>
                    <td class="p-4 text-right">
                      <div v-if="item.status==='pending'" class="flex justify-end gap-1.5">
                        <button @click="updateBookingStatus(item.id,'approve')" class="bg-green-600 hover:bg-green-700 text-white font-bold px-3 py-1.5 rounded-lg text-[10px] transition-all flex items-center gap-1"><i class="fa-solid fa-check"></i> อนุมัติ</button>
                        <button @click="updateBookingStatus(item.id,'reject')" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold px-3 py-1.5 rounded-lg text-[10px] border border-red-200 transition-all">ปฏิเสธ</button>
                      </div>
                      <span v-else class="text-[10px] text-slate-400">ดำเนินการแล้ว</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- ===== TAB: จัดการสมาชิก ===== -->
        <div v-show="currentTab === 'members'" class="space-y-5">
          <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="p-4 text-center bg-white border shadow-sm rounded-2xl border-slate-200"><p class="text-xs font-semibold text-slate-400">สมาชิกทั้งหมด</p><p class="mt-1 text-2xl font-bold text-blue-900">{{ members.length }}</p></div>
            <div class="p-4 text-center bg-white border shadow-sm rounded-2xl border-slate-200"><p class="text-xs font-semibold text-slate-400">นิสิต ป.ตรี</p><p class="mt-1 text-2xl font-bold text-blue-700">{{ members.filter(m=>m.type==='นิสิต ป.ตรี').length }}</p></div>
            <div class="p-4 text-center bg-white border shadow-sm rounded-2xl border-slate-200"><p class="text-xs font-semibold text-slate-400">บัณฑิตศึกษา</p><p class="mt-1 text-2xl font-bold text-indigo-700">{{ members.filter(m=>m.type==='บัณฑิตศึกษา').length }}</p></div>
            <div class="p-4 text-center bg-white border shadow-sm rounded-2xl border-slate-200"><p class="text-xs font-semibold text-slate-400">บุคลากร/อาจารย์</p><p class="mt-1 text-2xl font-bold text-amber-600">{{ members.filter(m=>m.type==='บุคลากร/อาจารย์').length }}</p></div>
          </div>
          <div class="overflow-hidden bg-white border shadow-sm rounded-2xl border-slate-200">
            <div class="flex items-center justify-between p-5 border-b border-slate-200">
              <h3 class="text-sm font-bold text-slate-900">รายชื่อสมาชิกทั้งหมด</h3>
              <div class="relative hidden sm:block">
                <i class="absolute text-xs -translate-y-1/2 left-3 top-1/2 fa-solid fa-magnifying-glass text-slate-400"></i>
                <input type="text" placeholder="ค้นหาชื่อหรือรหัส..." class="pl-8 pr-3 py-1.5 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200 w-48" />
              </div>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-left">
                <thead><tr class="bg-slate-50 border-b border-slate-200 text-[11px] text-slate-500 font-bold uppercase tracking-wider"><th class="p-4">สมาชิก</th><th class="p-4">รหัส</th><th class="hidden p-4 md:table-cell">คณะ</th><th class="p-4">ประเภท</th><th class="p-4 text-center">จองทั้งหมด</th><th class="hidden p-4 sm:table-cell">จองล่าสุด</th></tr></thead>
                <tbody class="text-xs divide-y divide-slate-100 text-slate-600">
                  <tr v-for="m in members" :key="m.id" class="transition-colors hover:bg-slate-50/70">
                    <td class="p-4">
                      <div class="flex items-center gap-2.5">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full text-[10px] font-bold text-white bg-gradient-to-tr from-blue-600 to-indigo-500 shrink-0">{{ memberInitials(m.name) }}</div>
                        <div><div class="font-bold text-slate-900">{{ m.name }}</div><div class="text-[10px] text-slate-400">{{ m.email }}</div></div>
                      </div>
                    </td>
                    <td class="p-4 font-mono text-slate-600">{{ m.code }}</td>
                    <td class="hidden p-4 md:table-cell text-slate-500">{{ m.faculty }}</td>
                    <td class="p-4">
                      <span :class="{'bg-blue-50 text-blue-800 border-blue-200': m.type==='นิสิต ป.ตรี','bg-indigo-50 text-indigo-800 border-indigo-200': m.type==='บัณฑิตศึกษา','bg-amber-50 text-amber-800 border-amber-200': m.type==='บุคลากร/อาจารย์'}" class="text-[10px] px-2 py-0.5 rounded border font-semibold">{{ m.type }}</span>
                    </td>
                    <td class="p-4 font-bold text-center text-slate-900">{{ m.totalBookings }}</td>
                    <td class="hidden p-4 sm:table-cell text-slate-400">{{ m.lastBooking }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- ===== TAB: จัดการพื้นที่ ===== -->
        <div v-show="currentTab === 'rooms'" class="space-y-5">
          <div class="p-5 text-white bg-gradient-to-r from-blue-900 to-indigo-900 rounded-2xl">
            <h3 class="text-base font-bold">จัดการพื้นที่ห้องบริการ (Location / Zone / Room)</h3>
            <p class="mt-1 text-xs text-slate-200">เปิดหรือปิดปรับปรุงชั่วคราวแต่ละพื้นที่ย่อย การเปลี่ยนแปลงมีผลทันทีบนหน้าจองของผู้ใช้</p>
          </div>
          <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
            <div v-for="group in groupedRooms" :key="group.name" class="p-5 space-y-4 bg-white border shadow-sm rounded-2xl border-slate-200">
              <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h4 class="text-sm font-bold text-slate-900">{{ group.name }}</h4>
                <span class="text-[10px] font-bold" :class="group.items.some(r=>r.active)?'text-green-600':'text-red-500'">
                  <i class="fa-solid fa-circle text-[8px] mr-1"></i>{{ group.items.filter(r=>r.active).length }}/{{ group.items.length }} เปิด
                </span>
              </div>
              <div class="space-y-3">
                <div v-for="room in group.items" :key="room.id" class="flex items-center justify-between gap-2 text-xs">
                  <span class="leading-tight text-slate-700">{{ room.name }}</span>
                  <button @click="toggleRoom(room.id)" :class="room.active ? 'bg-green-600 hover:bg-green-700' : 'bg-slate-400 hover:bg-slate-500'" class="text-white text-[10px] px-2.5 py-1 rounded font-bold transition-colors shrink-0 min-w-[72px]">
                    {{ room.active ? 'เปิดอยู่' : 'ปิดอยู่' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ===== TAB: วันหยุด & ปิดให้บริการ ===== -->
        <div v-show="currentTab === 'holidays'" class="space-y-5">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="flex items-center gap-3 p-4 bg-white border shadow-sm rounded-2xl border-slate-200">
              <div class="flex items-center justify-center w-10 h-10 text-red-500 rounded-xl bg-red-50 shrink-0"><i class="fa-solid fa-calendar-xmark"></i></div>
              <div><p class="text-[11px] text-slate-400 font-semibold">วันหยุดในระบบ</p><p class="text-xl font-bold text-slate-900">{{ holidays.length }} วัน</p></div>
            </div>
            <div class="flex items-center gap-3 p-4 bg-white border shadow-sm rounded-2xl border-slate-200">
              <div class="flex items-center justify-center w-10 h-10 text-blue-600 rounded-xl bg-blue-50 shrink-0"><i class="fa-solid fa-flag"></i></div>
              <div><p class="text-[11px] text-slate-400 font-semibold">วันหยุดราชการ</p><p class="text-xl font-bold text-slate-900">{{ holidays.filter(h=>h.type==='national').length }} วัน</p></div>
            </div>
            <div class="flex items-center gap-3 p-4 bg-white border shadow-sm rounded-2xl border-slate-200">
              <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-amber-50 text-amber-600 shrink-0"><i class="fa-solid fa-wrench"></i></div>
              <div><p class="text-[11px] text-slate-400 font-semibold">ปิดปรับปรุง (ห้องสมุด)</p><p class="text-xl font-bold text-slate-900">{{ holidays.filter(h=>h.type==='library').length }} ครั้ง</p></div>
            </div>
          </div>
          <div class="overflow-hidden bg-white border shadow-sm rounded-2xl border-slate-200">
            <div class="flex items-center justify-between p-5 border-b border-slate-200">
              <div>
                <h3 class="text-sm font-bold text-slate-900">ตารางวันหยุดและปิดให้บริการ</h3>
                <p class="text-xs text-slate-500 mt-0.5">ระบบจะปิดรับการจองในวันเหล่านี้โดยอัตโนมัติ</p>
              </div>
              <button class="flex items-center gap-1.5 bg-blue-900 hover:bg-blue-800 text-white text-xs font-bold px-3 py-2 rounded-xl transition-colors">
                <i class="fa-solid fa-plus"></i> เพิ่มวันหยุด
              </button>
            </div>
            <div class="divide-y divide-slate-100">
              <div v-for="h in holidays" :key="h.id" class="flex items-center justify-between px-5 py-3.5 hover:bg-slate-50/70 transition-colors">
                <div class="flex items-center gap-3">
                  <div :class="h.type==='national'?'bg-blue-100 text-blue-700':'bg-amber-100 text-amber-700'" class="flex items-center justify-center w-8 h-8 text-xs rounded-lg shrink-0">
                    <i :class="h.type==='national'?'fa-solid fa-flag':'fa-solid fa-wrench'"></i>
                  </div>
                  <div>
                    <p class="text-xs font-bold text-slate-900">{{ h.name }}</p>
                    <p class="text-[11px] text-slate-400"><i class="mr-1 fa-regular fa-calendar text-slate-300"></i>{{ h.date }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <span :class="h.type==='national'?'bg-blue-50 text-blue-700 border-blue-200':'bg-amber-50 text-amber-700 border-amber-200'" class="text-[10px] font-bold px-2 py-0.5 rounded border hidden sm:inline-block">
                    {{ h.type==='national' ? 'ราชการ' : 'ห้องสมุด' }}
                  </span>
                  <button @click="deleteHoliday(h.id)" class="p-1 transition-colors text-slate-400 hover:text-red-500" title="ลบ"><i class="text-xs fa-solid fa-trash-can"></i></button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ===== TAB: เวลาให้บริการ ===== -->
        <div v-show="currentTab === 'service_hours'" class="space-y-5">
          <div class="p-5 text-white bg-gradient-to-r from-blue-900 to-indigo-900 rounded-2xl">
            <h3 class="text-base font-bold">กำหนดเวลาเปิด-ปิดให้บริการ</h3>
            <p class="mt-1 text-xs text-slate-200">ระบบแบ่งช่วงเวลาจองเป็น 13 สล็อต ทุก 1 ชั่วโมง ตั้งแต่ 08:00–21:00 น. — สล็อตที่พ้นเวลาปิดจะถูกซ่อนโดยอัตโนมัติ</p>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="(day, idx) in serviceHours" :key="day.day" :class="day.isOpen?'border-slate-200':'border-red-100 bg-red-50/30'" class="p-4 transition-all bg-white border shadow-sm rounded-2xl">
              <div class="flex items-center justify-between mb-3">
                <h4 :class="day.isOpen?'text-slate-900':'text-slate-400'" class="text-sm font-bold">{{ day.day }}</h4>
                <button @click="toggleServiceDay(idx)" :class="day.isOpen?'bg-green-100 text-green-700 border-green-200 hover:bg-green-200':'bg-red-100 text-red-600 border-red-200 hover:bg-red-200'" class="text-[10px] font-bold px-2.5 py-1 rounded-lg border transition-colors">
                  <i :class="day.isOpen?'fa-solid fa-toggle-on':'fa-solid fa-toggle-off'" class="mr-1"></i>
                  {{ day.isOpen ? 'เปิด' : 'ปิด' }}
                </button>
              </div>
              <div v-if="day.isOpen" class="space-y-1.5">
                <div class="flex items-center gap-2 text-xs text-slate-600">
                  <i class="w-3 text-blue-400 fa-solid fa-clock"></i>
                  <span>{{ day.openTime }} – {{ day.closeTime }} น.</span>
                </div>
                <div class="flex items-center gap-2 text-[10px] text-slate-400">
                  <i class="w-3 fa-solid fa-layer-group text-slate-300"></i>
                  <span>{{ Math.round((parseInt(day.closeTime)-parseInt(day.openTime))) }} สล็อต</span>
                </div>
              </div>
              <p v-else class="text-[11px] text-slate-400 mt-1"><i class="mr-1 fa-solid fa-ban"></i>ไม่มีการให้บริการ</p>
            </div>
          </div>
          <div class="flex items-start gap-3 p-4 border bg-amber-50 border-amber-200 rounded-xl">
            <i class="fa-solid fa-circle-info text-amber-500 mt-0.5 shrink-0"></i>
            <p class="text-xs text-amber-800">การเปลี่ยนแปลงเวลาให้บริการจะมีผลกับทุกโซนและทุกห้องในระบบ ผู้ใช้จะไม่เห็นช่วงเวลาที่อยู่นอกเวลาให้บริการในหน้าจอง</p>
          </div>
        </div>

        <!-- ===== TAB: ผลประเมิน & รายงาน ===== -->
        <div v-show="currentTab === 'reports'" class="space-y-5">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="p-5 text-center bg-white border shadow-sm rounded-2xl border-slate-200"><div class="text-3xl text-amber-500">★★★★★</div><h4 class="mt-1 text-xs font-bold text-slate-400">ความพึงพอใจเฉลี่ย</h4><div class="mt-1 text-3xl font-extrabold text-blue-900">4.8 / 5.0</div></div>
            <div class="p-5 text-center bg-white border shadow-sm rounded-2xl border-slate-200"><div class="text-3xl text-blue-900"><i class="fa-solid fa-square-poll-vertical"></i></div><h4 class="mt-1 text-xs font-bold text-slate-400">ผู้เข้าประเมินสะสม</h4><div class="mt-1 text-3xl font-extrabold text-blue-900">426 ราย</div></div>
            <div class="p-5 text-center bg-white border shadow-sm rounded-2xl border-slate-200"><div class="text-3xl text-emerald-600"><i class="fa-solid fa-face-smile"></i></div><h4 class="mt-1 text-xs font-bold text-slate-400">ผู้พึงพอใจสูงมาก</h4><div class="mt-1 text-3xl font-extrabold text-emerald-600">98.2%</div></div>
          </div>
          <div class="p-6 space-y-4 bg-white border shadow-sm rounded-2xl border-slate-200">
            <h4 class="pb-3 text-sm font-bold border-b text-slate-900 border-slate-100">ความเห็นล่าสุดจากนิสิต</h4>
            <div class="space-y-3">
              <div v-for="f in feedbacks" :key="f.id" class="p-4 space-y-1 border bg-slate-50 rounded-xl border-slate-100">
                <div class="flex items-center justify-between"><span class="text-xs font-bold text-slate-800">{{ f.studentInfo }}</span><span class="text-xs text-amber-500">{{ '★'.repeat(f.stars) }}{{ '☆'.repeat(5-f.stars) }}</span></div>
                <p class="text-xs italic text-slate-500">"{{ f.comment }}"</p>
              </div>
            </div>
          </div>
        </div>

        <!-- ===== TAB: คู่มือการใช้งาน ===== -->
        <div v-show="currentTab === 'manual'" class="space-y-5">
          <div class="flex flex-wrap items-center justify-between gap-3 p-5 text-white bg-gradient-to-r from-blue-900 to-indigo-900 rounded-2xl">
            <div>
              <h3 class="text-base font-bold">คู่มือการใช้งานระบบ LibRoom</h3>
              <p class="mt-1 text-xs text-slate-200">เอกสารอ้างอิงสำหรับผู้ดูแลระบบและนิสิตผู้ใช้บริการ</p>
            </div>
            <button class="flex items-center gap-2 px-4 py-2 text-xs font-bold text-white transition-colors border bg-white/10 hover:bg-white/20 border-white/20 rounded-xl">
              <i class="text-red-300 fa-solid fa-file-pdf"></i> ดาวน์โหลด PDF
            </button>
          </div>
          <div class="space-y-3">
            <div v-for="(section, idx) in [
              { icon: 'fa-calendar-plus', title: 'ขั้นตอนการจองห้องสำหรับนิสิต', content: ['เข้าสู่ระบบด้วย Google Account มหาวิทยาลัย (@msu.ac.th)', 'เลือกโซนพื้นที่ที่ต้องการ จากนั้นเลือกห้องย่อยที่ต้องการ', 'เลือกวันที่และช่วงเวลา (สล็อตละ 1 ชั่วโมง สูงสุด 3 ชั่วโมง/วัน)', 'ยืนยันการจอง รอการอนุมัติจากเจ้าหน้าที่ภายใน 15 นาที', 'เช็คอินที่จุดบริการภายใน 15 นาทีหลังเวลาเริ่มต้น'] },
              { icon: 'fa-shield-halved', title: 'กฎระเบียบการใช้พื้นที่', content: ['ใช้เพื่อกิจกรรมทางวิชาการและการเรียนรู้เท่านั้น', 'ห้ามนำอาหารที่มีกลิ่นแรงเข้าในพื้นที่บริการ', 'รักษาความสะอาดและความเรียบร้อยทุกครั้ง', 'ห้ามส่งเสียงดังรบกวนผู้ใช้บริการท่านอื่น', 'คืนกุญแจและอุปกรณ์ให้ครบก่อนออกจากพื้นที่'] },
              { icon: 'fa-rotate-right', title: 'การเช็คอินและยกเลิกการจอง', content: ['เช็คอินได้ที่เคาน์เตอร์บริการหรือผ่าน QR Code หน้าห้อง', 'หากไม่เช็คอินภายใน 15 นาที ระบบจะยกเลิกอัตโนมัติ', 'ยกเลิกล่วงหน้าได้อย่างน้อย 30 นาทีก่อนเวลาจอง', 'การยกเลิกซ้ำเกิน 3 ครั้ง/เดือน อาจถูกระงับสิทธิ์ชั่วคราว'] },
              { icon: 'fa-circle-question', title: 'คำถามที่พบบ่อย (FAQ)', content: ['Q: จองได้กี่ชั่วโมงต่อวัน? — A: สูงสุด 3 ชั่วโมง/วัน/ห้อง', 'Q: การจองกลุ่มทำอย่างไร? — A: ผู้จองหลักสร้างการจองและแชร์ QR Code ให้สมาชิกกลุ่ม', 'Q: ลืมเช็คอินทำอย่างไร? — A: ติดต่อเจ้าหน้าที่เคาน์เตอร์ภายในเวลาให้บริการ', 'Q: จองซ้ำในเวลาเดิมได้ไหม? — A: ไม่ได้ ระบบจะตรวจสอบความซ้ำซ้อนอัตโนมัติ'] }
            ]" :key="idx" class="overflow-hidden bg-white border shadow-sm rounded-2xl border-slate-200">
              <button @click="toggleManualSection(idx)" class="flex items-center justify-between w-full p-5 text-left transition-colors hover:bg-slate-50">
                <div class="flex items-center gap-3">
                  <div class="flex items-center justify-center w-8 h-8 text-xs text-blue-700 rounded-lg bg-blue-50 shrink-0"><i :class="`fa-solid ${section.icon}`"></i></div>
                  <span class="text-sm font-bold text-slate-900">{{ section.title }}</span>
                </div>
                <i class="text-xs transition-transform fa-solid text-slate-400" :class="manualOpenSections.includes(idx)?'fa-chevron-up':'fa-chevron-down'"></i>
              </button>
              <div v-show="manualOpenSections.includes(idx)" class="px-5 pb-5">
                <ul class="space-y-2">
                  <li v-for="(item, i) in section.content" :key="i" class="flex items-start gap-2.5 text-xs text-slate-600">
                    <i class="fa-solid fa-circle-dot text-blue-300 text-[9px] mt-1 shrink-0"></i>
                    <span>{{ item }}</span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>

      </main>

      <!-- Footer -->
      <footer class="py-4 text-xs text-center border-t bg-slate-900 text-slate-400 border-slate-800">
        &copy; 2026 สำนักวิทยบริการ มหาวิทยาลัยมหาสารคาม &mdash; ระบบบริการจองพื้นที่ออนไลน์ (MSU Library System v2)
      </footer>
    </div>

    <!-- Toast -->
    <div :class="toast.show ? 'translate-x-0 opacity-100' : 'translate-x-96 opacity-0'" class="fixed z-50 flex items-start w-full max-w-sm gap-3 p-4 transition-all duration-300 bg-white border-l-4 shadow-2xl top-6 right-6 rounded-xl" :style="{ borderLeftColor: toast.isError ? '#ef4444' : '#22c55e' }">
      <div :class="toast.isError ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'" class="p-2 rounded-full mt-0.5">
        <i :class="toast.isError ? 'fa-solid fa-circle-xmark' : 'fa-solid fa-circle-check'" class="text-lg"></i>
      </div>
      <div class="flex-grow">
        <h4 class="text-sm font-bold text-slate-900">{{ toast.title }}</h4>
        <p class="text-xs text-slate-500 mt-0.5">{{ toast.desc }}</p>
      </div>
      <button @click="hideToast" class="transition-colors text-slate-400 hover:text-slate-600"><i class="text-sm fa-solid fa-xmark"></i></button>
    </div>

  </div>
</template>

<style scoped>
.font-anuphan {
  font-family: 'Anuphan', sans-serif;
}

/* Sidebar active item: amber left bar via pseudo-element */
.nav-active {
  background-color: #1e293b; /* slate-800 */
}
.nav-active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 8px;
  bottom: 8px;
  width: 3px;
  background-color: #fbbf24; /* amber-400 */
  border-radius: 0 3px 3px 0;
}
</style>
