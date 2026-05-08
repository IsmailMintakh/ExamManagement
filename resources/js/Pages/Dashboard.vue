<script setup>
/**
 * Dashboard — hybrid layout, role-aware, mobile-first.
 *
 * Sections (in order):
 *   1. Slim greeting strip (1 line)
 *   2. "Needs attention" cards — only renders for roles + stats with > 0 items
 *   3. Stats grid — large tiles, role-specific
 *   4. Quick actions — 4 big icon tiles (permission-gated)
 *   5. Recent exams — card list on mobile, table on desktop
 *   6. Role section — schools (DDO), classes (Principal), sections / assignments (teachers)
 */
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
    AcademicCapIcon, BuildingOfficeIcon, UserGroupIcon,
    ClipboardDocumentListIcon, ChartBarIcon, ArrowTrendingUpIcon,
    CalendarDaysIcon, DocumentTextIcon, BookOpenIcon, ClockIcon,
    ArrowRightIcon, BoltIcon, TrophyIcon, ExclamationCircleIcon,
    PlusIcon, PencilSquareIcon, EnvelopeIcon, CheckBadgeIcon,
    ChevronRightIcon, RectangleStackIcon, ChevronDownIcon,
    BellAlertIcon, ArrowsRightLeftIcon, FaceSmileIcon, NoSymbolIcon,
} from '@heroicons/vue/24/outline'
import { ref, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    role: { type: String, default: '' },
    currentSession: { type: Object, default: null },
    recentExams: { type: Array, default: () => [] },
    schoolWiseComparison: { type: Array, default: () => [] },
    classWisePerformance: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
    pendingExams: { type: Array, default: () => [] },
    // Backend-provided list of actionable items per role: each item has
    // { key, severity (info|warning|error), title, description, action_label, action_url, count }
    needsAttention: { type: Array, default: () => [] },
    // First-run setup checklist for super-admin/school-admin: each step has
    // { key, label, count, done, action_url, action_label }. Hidden once complete.
    setupStatus: { type: Object, default: null },
    todaysClasses: { type: Object, default: null },
})

const page = usePage()
// Setup checklist starts collapsed — user can expand to see steps. Persisted
// per-browser so they don't have to keep collapsing it on every page load.
const setupOpen = ref(localStorage.getItem('dashboard-setup-open') === '1')
function toggleSetup() {
    setupOpen.value = !setupOpen.value
    localStorage.setItem('dashboard-setup-open', setupOpen.value ? '1' : '0')
}

const userName = computed(() => page.props.auth?.user?.name?.split(' ')[0] || 'there')
const userPhoto = computed(() => page.props.auth?.user?.avatar_url || null)
const roles = computed(() => page.props.auth?.user?.roles || [])
const permissions = computed(() => page.props.auth?.user?.permissions || [])
const contactMessageCount = computed(() => page.props.contactMessageCount || 0)

const hasRole = (r) => roles.value.includes(r)
const hasPerm = (p) => hasRole('super-admin') || permissions.value.includes(p)

const greeting = computed(() => {
    const h = new Date().getHours()
    if (h < 12) return 'Good morning'
    if (h < 17) return 'Good afternoon'
    return 'Good evening'
})

const todayLabel = computed(() => new Date().toLocaleDateString('en-US', {
    weekday: 'long', month: 'short', day: 'numeric',
}))

// ─── Today's Classes timeline (teachers only) ───
// Re-tick every 30s so "now/upcoming" markers update without a refresh.
const nowTick = ref(Date.now())
let _tick = null
onMounted(() => { _tick = setInterval(() => { nowTick.value = Date.now() }, 30000) })
onBeforeUnmount(() => { if (_tick) clearInterval(_tick) })

// Merged + sorted today's slots: own periods + cover periods.
const todayMerged = computed(() => {
    if (!props.todaysClasses) return []
    const own = (props.todaysClasses.periods || []).map(p => ({ ...p, kind: 'own' }))
    const covers = (props.todaysClasses.covers || []).map(p => ({ ...p, kind: 'cover' }))
    return [...own, ...covers].sort((a, b) => a.starts_at.localeCompare(b.starts_at))
})

function nowMinutes() {
    const d = new Date(nowTick.value)
    return d.getHours() * 60 + d.getMinutes()
}
function timeToMin(t) {
    if (!t) return 0
    const [h, m] = t.split(':').map(Number)
    return h * 60 + m
}
function periodState(p) {
    const start = timeToMin(p.starts_at)
    const end = timeToMin(p.ends_at)
    const n = nowMinutes()
    if (n < start - 5) return 'upcoming'
    if (n >= start - 5 && n < start) return 'starting-soon'
    if (n >= start && n <= end) return 'ongoing'
    return 'past'
}

const roleLabels = {
    'super-admin':     'District Drawing Officer',
    'school-admin':    'Principal',
    'class-teacher':   'Class Teacher',
    'subject-teacher': 'Subject Teacher',
}

// ─── "Needs attention" — server-provided actionable items ───
// Backend (DashboardController::needsAttentionFor) computes these per role:
// pending result reviews, marks not yet submitted, results returned for
// correction, drafts with no subjects, etc. We map severity → icon + color
// here so the Vue side stays presentation-only.
const severityIcon = {
    error:   ExclamationCircleIcon,
    warning: PencilSquareIcon,
    info:    ClipboardDocumentListIcon,
}
const severityColor = {
    error:   'rose',
    warning: 'amber',
    info:    'sky',
}
const attentionItems = computed(() => {
    const items = (props.needsAttention || []).map(item => ({
        icon: severityIcon[item.severity] || ClipboardDocumentListIcon,
        color: severityColor[item.severity] || 'sky',
        title: item.title,
        desc: item.description,
        actionLabel: item.action_label,
        href: item.action_url,
        count: item.count,
    }))

    // DDO-only extras that don't live in the backend payload (contact form
    // messages live on a different model, no need to refactor right now).
    if (props.role === 'super-admin' && contactMessageCount.value > 0) {
        items.push({
            icon: EnvelopeIcon, color: 'sky',
            title: `${contactMessageCount.value} new contact message${contactMessageCount.value === 1 ? '' : 's'}`,
            desc: 'From visitors who used the public website contact form.',
            actionLabel: 'View Inbox',
            href: '/website/contact-messages',
        })
    }

    return items
})

// ─── Status pill colours ───
const statusBadge = (status) => ({
    draft:        'badge-ghost',
    published:    'badge-info',
    marks_entry:  'badge-warning',
    completed:    'badge-success',
}[status] || 'badge-ghost')

const statusLabel = (s) => s?.replace(/_/g, ' ') || '—'

// ─── Quick-action tiles (filter by permission) ───
const quickActions = computed(() => {
    const all = [
        { perm: 'exams.create',    label: 'New Exam',     desc: 'Set up an exam',     href: '/exams/create',     icon: ClipboardDocumentListIcon, color: 'primary' },
        { perm: 'students.create', label: 'Add Student',  desc: 'Enroll a student',   href: '/students/create',  icon: UserGroupIcon,             color: 'emerald' },
        { perm: 'marks.enter',     label: 'Enter Marks',  desc: 'Submit your marks',  href: '/marks',            icon: DocumentTextIcon,          color: 'amber' },
        { perm: 'reports.view',    label: 'Reports',      desc: 'PDF result cards',   href: '/reports',          icon: ChartBarIcon,              color: 'sky' },
        { perm: 'questions.create',label: 'Add Question', desc: 'Question bank',      href: '/questions/create', icon: BookOpenIcon,              color: 'violet' },
        { perm: 'scheduling.view', label: 'Scheduling',   desc: 'Date sheets, rooms', href: '/scheduling',       icon: CalendarDaysIcon,          color: 'rose' },
    ]
    return all.filter(a => hasPerm(a.perm)).slice(0, 4)
})

// Tile color presets — gradient surfaces with brand-accent identities.
// Each color has its own personality so the dashboard isn't monochrome.
// Opacity-based so the same class string adapts to light + dark themes.
const tileColors = {
    primary: { iconBg: 'bg-teal-500/15',    iconText: 'text-teal-600 dark:text-teal-400',       hover: 'hover:border-teal-500/40' },
    emerald: { iconBg: 'bg-emerald-500/15', iconText: 'text-emerald-600 dark:text-emerald-400', hover: 'hover:border-emerald-500/40' },
    amber:   { iconBg: 'bg-amber-500/15',   iconText: 'text-amber-600 dark:text-amber-400',     hover: 'hover:border-amber-500/40' },
    sky:     { iconBg: 'bg-sky-500/15',     iconText: 'text-sky-600 dark:text-sky-400',         hover: 'hover:border-sky-500/40' },
    violet:  { iconBg: 'bg-violet-500/15',  iconText: 'text-violet-600 dark:text-violet-400',   hover: 'hover:border-violet-500/40' },
    rose:    { iconBg: 'bg-rose-500/15',    iconText: 'text-rose-600 dark:text-rose-400',       hover: 'hover:border-rose-500/40' },
}

// Attention banners — translucent fills so dark mode shows tinted-dark
// surfaces, not blinding light. Text uses semantic shades that have
// proper contrast against either background.
const attentionColors = {
    amber: 'bg-amber-500/10 border-amber-500/30 text-amber-900 dark:text-amber-100',
    sky:   'bg-sky-500/10   border-sky-500/30   text-sky-900   dark:text-sky-100',
    rose:  'bg-rose-500/10  border-rose-500/30  text-rose-900  dark:text-rose-100',
}
const attentionIconBg = {
    amber: 'bg-amber-500/20 text-amber-700 dark:text-amber-300',
    sky:   'bg-sky-500/20   text-sky-700   dark:text-sky-300',
    rose:  'bg-rose-500/20  text-rose-700  dark:text-rose-300',
}

// ─── Stats grid (per role) ───
const statTiles = computed(() => {
    if (props.role === 'super-admin') return [
        { label: 'Schools',      value: props.stats?.totalSchools ?? 0,      icon: BuildingOfficeIcon, color: 'primary' },
        { label: 'Students',     value: props.stats?.totalStudents ?? 0,     icon: UserGroupIcon,      color: 'emerald' },
        { label: 'Teachers',     value: props.stats?.totalTeachers ?? 0,     icon: AcademicCapIcon,    color: 'sky' },
        { label: 'Pass Rate',    value: (props.stats?.passRate ?? 0) + '%',  icon: ArrowTrendingUpIcon, color: 'amber' },
    ]
    if (props.role === 'school-admin') return [
        { label: 'Students',     value: props.stats?.totalStudents ?? 0,     icon: UserGroupIcon,      color: 'primary' },
        { label: 'Classes',      value: props.stats?.totalClasses ?? 0,      icon: AcademicCapIcon,    color: 'emerald' },
        { label: 'Teachers',     value: props.stats?.totalTeachers ?? 0,     icon: BookOpenIcon,       color: 'sky' },
        { label: 'Pass Rate',    value: (props.stats?.passRate ?? 0) + '%',  icon: ArrowTrendingUpIcon, color: 'amber' },
    ]
    if (props.role === 'class-teacher') return [
        { label: 'My Sections',  value: props.stats?.totalSections ?? 0,     icon: RectangleStackIcon, color: 'primary' },
        { label: 'Students',     value: props.stats?.totalStudents ?? 0,     icon: UserGroupIcon,      color: 'emerald' },
        { label: 'Pending Marks', value: props.stats?.pendingMarksEntry ?? 0, icon: ClockIcon,          color: 'amber' },
    ]
    if (props.role === 'subject-teacher') return [
        { label: 'Subjects',     value: props.stats?.assignedSubjects ?? 0,  icon: BookOpenIcon,       color: 'primary' },
        { label: 'Sections',     value: props.stats?.assignedSections ?? 0,  icon: RectangleStackIcon, color: 'emerald' },
        { label: 'Pending Marks', value: props.stats?.pendingMarksEntry ?? 0, icon: ClockIcon,          color: 'amber' },
    ]
    return []
})
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="[{ label: 'Dashboard' }]">
        <div class="space-y-5 sm:space-y-6">

            <!-- ════════ 1. GREETING STRIP — slate hero card ════════ -->
            <div class="flex items-center gap-3 sm:gap-4 rounded-2xl p-3.5 sm:p-4 bg-base-100 border border-base-200">
                <div class="relative shrink-0">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl text-white flex items-center justify-center font-bold text-lg overflow-hidden shadow-md"
                         style="background: linear-gradient(135deg, #4b545c 0%, #2c3138 100%);">
                        <img v-if="userPhoto" :src="userPhoto" :alt="userName" class="w-full h-full object-cover" />
                        <span v-else>{{ userName.charAt(0).toUpperCase() }}</span>
                    </div>
                    <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full bg-emerald-500 ring-2 ring-base-100"></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[10px] text-base-content/55 font-bold uppercase tracking-wider">{{ todayLabel }}</p>
                    <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight truncate mt-0.5">
                        {{ greeting }}, {{ userName }}
                    </h1>
                    <p class="text-[11px] text-base-content/55 mt-0.5 truncate">
                        <span class="font-semibold text-base-content/75">{{ roleLabels[role] || role }}</span>
                        <span v-if="currentSession?.name"> · {{ currentSession.name }}</span>
                    </p>
                </div>
            </div>

            <!-- ════════ TODAY'S TEACHING SCHEDULE (teachers only) ════════ -->
            <section v-if="todaysClasses && (role === 'class-teacher' || role === 'subject-teacher')"
                class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <header class="px-4 sm:px-5 py-3 border-b border-base-300 flex items-center justify-between gap-2 flex-wrap">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-sky-500/15 text-sky-600 dark:text-sky-400 grid place-items-center shrink-0">
                            <ClockIcon class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <h2 class="font-bold text-sm truncate">Today's Schedule</h2>
                            <p class="text-[11px] text-base-content/55 truncate">
                                {{ todayMerged.length }} period{{ todayMerged.length === 1 ? '' : 's' }}
                                <span v-if="todaysClasses.covers?.length" class="text-amber-600 dark:text-amber-400 font-bold">
                                    · {{ todaysClasses.covers.length }} substitution cover{{ todaysClasses.covers.length === 1 ? '' : 's' }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <Link :href="route('timetable.teacher', $page.props.auth.user.id)"
                        class="btn btn-ghost btn-xs rounded-lg gap-1">
                        <CalendarDaysIcon class="w-3.5 h-3.5" /> My Timetable
                    </Link>
                </header>

                <!-- Off-day -->
                <div v-if="todaysClasses.is_off_day" class="px-5 py-8 text-center">
                    <FaceSmileIcon class="w-10 h-10 text-emerald-500 mx-auto mb-2" />
                    <p class="font-bold text-sm">No classes today — enjoy your day off!</p>
                    <p class="text-xs text-base-content/55 mt-0.5">Your timetable resumes tomorrow.</p>
                </div>

                <!-- Marked absent -->
                <div v-else-if="todaysClasses.absent" class="px-4 sm:px-5 py-4 bg-rose-500/10 border-b border-rose-500/20 flex items-start gap-3">
                    <NoSymbolIcon class="w-5 h-5 text-rose-600 dark:text-rose-400 mt-0.5 shrink-0" />
                    <div class="text-sm">
                        <p class="font-bold text-rose-900 dark:text-rose-200">You're marked absent today</p>
                        <p class="text-xs text-rose-700 dark:text-rose-300/80 mt-0.5">Your classes have been auto-suggested for substitution. Cover periods you've been assigned (if any) appear below.</p>
                    </div>
                </div>

                <!-- Empty state (not off-day, but no periods scheduled) -->
                <div v-if="!todaysClasses.is_off_day && !todayMerged.length" class="px-5 py-8 text-center">
                    <CalendarDaysIcon class="w-10 h-10 text-base-content/25 mx-auto mb-2" />
                    <p class="font-bold text-sm">No classes scheduled today</p>
                    <p class="text-xs text-base-content/55 mt-0.5">No timetable entries for you on {{ todaysClasses.day }}. Talk to admin if this looks wrong.</p>
                </div>

                <!-- Period strip -->
                <div v-else-if="!todaysClasses.is_off_day" class="overflow-x-auto p-3 sm:p-4">
                    <div class="flex gap-2 min-w-min">
                        <div v-for="(p, idx) in todayMerged" :key="idx"
                            class="rounded-xl p-3 ring-2 transition-colors min-w-[180px] flex-shrink-0"
                            :class="[
                                periodState(p) === 'ongoing' ? 'ring-emerald-500/60 bg-emerald-500/10' :
                                periodState(p) === 'starting-soon' ? 'ring-amber-500/60 bg-amber-500/10 animate-pulse' :
                                periodState(p) === 'past' ? 'ring-base-300 bg-base-200/40 opacity-60' :
                                p.kind === 'cover' ? 'ring-amber-500/40 bg-amber-500/5' :
                                'ring-base-300 bg-base-100',
                                p.is_cancelled ? 'opacity-40 line-through' : ''
                            ]">
                            <div class="flex items-center justify-between gap-1 mb-1.5">
                                <span class="text-[10px] font-mono font-bold tracking-tight">{{ p.starts_at }}–{{ p.ends_at }}</span>
                                <span v-if="periodState(p) === 'ongoing'"
                                    class="text-[9px] uppercase font-bold tracking-wider text-emerald-700 dark:text-emerald-300 px-1.5 py-0.5 bg-emerald-500/20 rounded">NOW</span>
                                <span v-else-if="periodState(p) === 'starting-soon'"
                                    class="text-[9px] uppercase font-bold tracking-wider text-amber-700 dark:text-amber-300 px-1.5 py-0.5 bg-amber-500/20 rounded">SOON</span>
                                <span v-else-if="p.kind === 'cover'"
                                    class="text-[9px] uppercase font-bold tracking-wider text-amber-700 dark:text-amber-300 px-1.5 py-0.5 bg-amber-500/20 rounded flex items-center gap-0.5">
                                    <ArrowsRightLeftIcon class="w-2.5 h-2.5" /> COVER
                                </span>
                            </div>
                            <p class="font-bold text-sm truncate">{{ p.subject || '—' }}</p>
                            <p class="text-[11px] text-base-content/65 truncate">{{ p.class }} · {{ p.section }}</p>
                            <p v-if="p.kind === 'cover' && p.replaces" class="text-[10px] text-amber-700 dark:text-amber-400 mt-1 truncate">
                                replaces {{ p.replaces }}
                            </p>
                            <p v-if="p.room" class="text-[10px] text-base-content/45 mt-0.5 truncate">Room {{ p.room }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ════════ SETUP CHECKLIST — compact, collapsible, hides when complete ════════ -->
            <section v-if="setupStatus && !setupStatus.is_complete"
                class="surface surface-accent-left accent-amber overflow-hidden">
                <!-- Compact header — always visible. Click to toggle the steps. -->
                <button type="button" @click="toggleSetup"
                    class="w-full flex items-center gap-3 px-4 sm:px-5 py-3 text-left hover:bg-base-200/40 transition-colors">
                    <div class="w-8 h-8 rounded-lg bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center flex-shrink-0">
                        <SparklesIcon class="w-4 h-4" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-sm">Setup Checklist</span>
                            <span class="text-[11px] font-bold tabular-nums text-amber-700 dark:text-amber-400">
                                {{ setupStatus.done_count }}/{{ setupStatus.total_count }}
                            </span>
                        </div>
                        <!-- Inline progress bar so users see status without expanding -->
                        <div class="mt-1.5 h-1 rounded-full bg-base-200 overflow-hidden">
                            <div class="h-full bg-amber-500 transition-all duration-500"
                                :style="`width: ${(setupStatus.done_count / setupStatus.total_count * 100).toFixed(0)}%`"></div>
                        </div>
                    </div>
                    <ChevronDownIcon class="w-4 h-4 text-base-content/45 transition-transform flex-shrink-0"
                        :class="setupOpen ? 'rotate-180' : ''" />
                </button>

                <!-- Steps — only render when expanded -->
                <Transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="max-h-0 opacity-0"
                    enter-to-class="max-h-[800px] opacity-100"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="max-h-[800px] opacity-100"
                    leave-to-class="max-h-0 opacity-0"
                >
                    <ol v-if="setupOpen" class="divide-y divide-base-200 border-t border-base-200 overflow-hidden">
                        <li v-for="(step, i) in setupStatus.steps" :key="step.key"
                            class="flex items-center gap-3 px-4 sm:px-5 py-2.5"
                            :class="step.done ? 'bg-emerald-500/5' : ''">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold flex-shrink-0"
                                :class="step.done ? 'bg-emerald-500 text-white' : 'bg-base-300 text-base-content/55'">
                                <CheckBadgeIcon v-if="step.done" class="w-3.5 h-3.5" />
                                <span v-else>{{ i + 1 }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-[13px]" :class="step.done ? 'text-base-content/65' : ''">
                                    {{ step.label }}
                                    <span class="text-[10px] font-normal text-base-content/45 ml-1 tabular-nums">· {{ step.count }}</span>
                                </p>
                            </div>
                            <Link v-if="!step.done" :href="step.action_url" class="btn btn-primary btn-xs gap-1">
                                <PlusIcon class="w-3 h-3" /> {{ step.action_label }}
                            </Link>
                            <Link v-else :href="step.action_url" class="text-[11px] text-base-content/45 hover:text-primary">
                                Add more
                            </Link>
                        </li>
                    </ol>
                </Transition>
            </section>

            <section v-if="attentionItems.length" class="space-y-2">
                <div class="flex items-center gap-2 px-1">
                    <ExclamationCircleIcon class="w-4 h-4 text-amber-600" />
                    <h2 class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Needs your attention</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <Link v-for="(item, i) in attentionItems" :key="i"
                          :href="item.href"
                          class="group flex items-center gap-3 rounded-2xl border p-3 sm:p-3.5 transition-all active:scale-[0.99] hover:shadow-md"
                          :class="attentionColors[item.color]">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                             :class="attentionIconBg[item.color]">
                            <component :is="item.icon" class="w-5 h-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-[13.5px] leading-tight">{{ item.title }}</p>
                            <p class="text-[11.5px] opacity-75 mt-0.5 truncate">{{ item.desc }}</p>
                        </div>
                        <ChevronRightIcon class="w-4 h-4 opacity-50 shrink-0 group-hover:translate-x-0.5 transition-transform" />
                    </Link>
                </div>
            </section>

            <!-- ════════ 3. STATS ════════ -->
            <section v-if="statTiles.length" class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3.5">
                <div v-for="tile in statTiles" :key="tile.label"
                     class="group relative rounded-2xl bg-base-100 border border-base-200 p-4 sm:p-5 shadow-card hover:shadow-card-md transition-all overflow-hidden"
                     :class="tileColors[tile.color]?.hover">
                    <div class="flex items-start justify-between gap-2">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                             :class="tileColors[tile.color]?.iconBg + ' ' + tileColors[tile.color]?.iconText">
                            <component :is="tile.icon" class="w-5 h-5" />
                        </div>
                    </div>
                    <p class="mt-4 stat-number text-base-content">{{ tile.value }}</p>
                    <p class="section-eyebrow mt-2">{{ tile.label }}</p>
                </div>
            </section>

            <!-- ════════ 4. QUICK ACTIONS ════════ -->
            <section v-if="quickActions.length" class="space-y-3">
                <div class="flex items-center gap-2 px-1">
                    <BoltIcon class="w-4 h-4 text-amber-500" />
                    <h2 class="section-eyebrow">Quick Actions</h2>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3.5">
                    <Link v-for="a in quickActions" :key="a.href"
                          :href="a.href"
                          class="group rounded-2xl bg-base-100 border border-base-200 p-4 sm:p-5 shadow-card hover:shadow-card-md transition-all active:scale-[0.98] flex flex-col gap-3"
                          :class="tileColors[a.color]?.hover">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center"
                             :class="tileColors[a.color]?.iconBg + ' ' + tileColors[a.color]?.iconText">
                            <component :is="a.icon" class="w-5 h-5" />
                        </div>
                        <div>
                            <p class="font-bold text-sm leading-tight">{{ a.label }}</p>
                            <p class="text-[11px] text-base-content/55 mt-1 leading-snug">{{ a.desc }}</p>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- ════════ 5. RECENT EXAMS ════════ -->
            <section v-if="recentExams?.length" class="space-y-2.5">
                <div class="flex items-center justify-between px-1">
                    <div class="flex items-center gap-2">
                        <ClipboardDocumentListIcon class="w-4 h-4 text-primary" />
                        <h2 class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Recent Exams</h2>
                    </div>
                    <Link v-if="hasPerm('exams.view')" :href="route('exams.index')"
                          class="text-xs font-semibold text-primary inline-flex items-center gap-1 hover:gap-1.5 transition-all">
                        View all <ArrowRightIcon class="w-3 h-3" />
                    </Link>
                </div>
                <div class="rounded-2xl bg-base-100 border border-base-200 overflow-hidden divide-y divide-base-200">
                    <Link v-for="exam in recentExams" :key="exam.id"
                          :href="route('exams.show', exam.id)"
                          class="flex items-center gap-3 p-3.5 active:bg-base-200/40 transition-colors min-w-0">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <ClipboardDocumentListIcon class="w-5 h-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-bold text-sm truncate">{{ exam.name }}</p>
                                <span :class="['badge badge-xs capitalize', statusBadge(exam.status)]">{{ statusLabel(exam.status) }}</span>
                            </div>
                            <p class="text-[11px] text-base-content/55 mt-0.5 truncate">
                                <span v-if="exam.type">{{ exam.type }}</span>
                                <span v-if="exam.start_date"> · {{ exam.start_date }} → {{ exam.end_date }}</span>
                            </p>
                        </div>
                        <ChevronRightIcon class="w-4 h-4 text-base-content/30 shrink-0" />
                    </Link>
                </div>
            </section>

            <!-- ════════ 6a. ROLE: super-admin → school comparison ════════ -->
            <section v-if="role === 'super-admin' && schoolWiseComparison?.length" class="space-y-2.5">
                <div class="flex items-center justify-between px-1">
                    <div class="flex items-center gap-2">
                        <TrophyIcon class="w-4 h-4 text-amber-500" />
                        <h2 class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">School Performance</h2>
                    </div>
                    <Link :href="route('analytics.index')" class="text-xs font-semibold text-primary inline-flex items-center gap-1 hover:gap-1.5 transition-all">
                        Full analytics <ArrowRightIcon class="w-3 h-3" />
                    </Link>
                </div>
                <div class="rounded-2xl bg-base-100 border border-base-200 divide-y divide-base-200">
                    <div v-for="(school, idx) in schoolWiseComparison" :key="school.id" class="flex items-center gap-3 p-3.5">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center text-xs font-black shrink-0"
                             :class="idx === 0 ? 'bg-amber-500/20 text-amber-700 dark:text-amber-300' : idx === 1 ? 'bg-base-300 text-base-content/70' : idx === 2 ? 'bg-orange-500/20 text-orange-700 dark:text-orange-300' : 'bg-primary/15 text-primary'">
                            #{{ idx + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-sm truncate">{{ school.name }}</p>
                            <p class="text-[11px] text-base-content/55 mt-0.5">{{ school.students_count ?? 0 }} students</p>
                        </div>
                        <div class="flex items-center gap-2 w-32 sm:w-40 shrink-0">
                            <div class="flex-1 h-1.5 rounded-full bg-base-200 overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-emerald-500 to-emerald-600 transition-all"
                                     :style="{ width: (school.pass_percentage || 0) + '%' }"></div>
                            </div>
                            <span class="text-xs font-bold tabular-nums w-10 text-right">{{ school.pass_percentage || 0 }}%</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ════════ 6b. ROLE: school-admin → class performance ════════ -->
            <section v-if="role === 'school-admin' && classWisePerformance?.length" class="space-y-2.5">
                <div class="flex items-center gap-2 px-1">
                    <AcademicCapIcon class="w-4 h-4 text-secondary" />
                    <h2 class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Class Performance</h2>
                </div>
                <div class="rounded-2xl bg-base-100 border border-base-200 divide-y divide-base-200">
                    <div v-for="cls in classWisePerformance" :key="cls.id" class="flex items-center gap-3 p-3.5">
                        <div class="w-9 h-9 rounded-xl bg-secondary/10 text-secondary flex items-center justify-center shrink-0">
                            <AcademicCapIcon class="w-5 h-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-sm truncate">{{ cls.name }}</p>
                            <p class="text-[11px] text-base-content/55 mt-0.5">{{ cls.total_students ?? 0 }} students</p>
                        </div>
                        <div class="flex items-center gap-2 w-32 sm:w-40 shrink-0">
                            <div class="flex-1 h-1.5 rounded-full bg-base-200 overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-primary to-primary/80 transition-all"
                                     :style="{ width: (cls.pass_percentage || 0) + '%' }"></div>
                            </div>
                            <span class="text-xs font-bold tabular-nums w-10 text-right">{{ cls.pass_percentage || 0 }}%</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ════════ 6c. ROLE: class-teacher → my sections ════════ -->
            <section v-if="role === 'class-teacher' && sections?.length" class="space-y-2.5">
                <div class="flex items-center justify-between px-1">
                    <div class="flex items-center gap-2">
                        <RectangleStackIcon class="w-4 h-4 text-accent" />
                        <h2 class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">My Sections</h2>
                    </div>
                    <Link href="/my-class" class="text-xs font-semibold text-primary inline-flex items-center gap-1 hover:gap-1.5 transition-all">
                        Open hub <ArrowRightIcon class="w-3 h-3" />
                    </Link>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                    <div v-for="s in sections" :key="s.id" class="rounded-2xl bg-base-100 border border-base-200 p-4 flex items-center gap-3">
                        <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-accent/20 to-accent/10 text-accent flex items-center justify-center font-black shrink-0">
                            {{ s.name?.charAt(0) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-sm truncate">{{ s.class_name }} – {{ s.name }}</p>
                            <p class="text-[11px] text-base-content/55 mt-0.5">{{ s.students_count }} students</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ════════ 6d. ROLE: subject-teacher → assignments ════════ -->
            <section v-if="role === 'subject-teacher' && assignments?.length" class="space-y-2.5">
                <div class="flex items-center gap-2 px-1">
                    <BookOpenIcon class="w-4 h-4 text-info" />
                    <h2 class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">My Subjects</h2>
                </div>
                <div class="rounded-2xl bg-base-100 border border-base-200 divide-y divide-base-200">
                    <div v-for="a in assignments" :key="a.id" class="flex items-center gap-3 p-3.5">
                        <div class="w-10 h-10 rounded-xl bg-info/10 text-info flex items-center justify-center shrink-0">
                            <BookOpenIcon class="w-5 h-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-sm truncate">{{ a.subject_name }}</p>
                            <p class="text-[11px] text-base-content/55 mt-0.5">{{ a.class_name }} – {{ a.section_name }}</p>
                        </div>
                        <Link :href="route('marks.index')" class="text-xs font-semibold text-primary inline-flex items-center gap-0.5 hover:gap-1 transition-all">
                            Marks <ArrowRightIcon class="w-3 h-3" />
                        </Link>
                    </div>
                </div>
            </section>

            <!-- ════════ EMPTY STATE — only when truly nothing ════════ -->
            <div v-if="!recentExams?.length && !attentionItems.length && statTiles.length === 0"
                 class="rounded-2xl border border-dashed border-base-300 p-10 text-center">
                <ClipboardDocumentListIcon class="w-12 h-12 mx-auto text-base-content/30" />
                <h3 class="mt-4 font-bold">Nothing to show yet</h3>
                <p class="mt-1 text-xs text-base-content/55">Once data flows in, this dashboard will fill up.</p>
            </div>
        </div>
    </AppLayout>
</template>
