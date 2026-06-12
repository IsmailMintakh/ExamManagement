<script setup>
/**
 * Dashboard — refactored to a two-column "real product" layout.
 *
 * Top zone: gradient hero card with greeting + featured pass-rate stat
 * Action pill: collapsible "X items need attention" surface
 * Insight banner: auto-generated single-line summary
 * Stats row: 4 compact tiles with deltas + sparklines
 * Main grid (lg+): 2-column
 *   ── Left (8/12): primary chart + secondary chart
 *   ── Right (4/12): activity feed + mini calendar
 * Compact lists: recent exams (1-line rows), then role-specific
 * Welcome state: friendly first-run if all stats are zero
 */
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import {
    AcademicCapIcon, BuildingOfficeIcon, UserGroupIcon,
    ClipboardDocumentListIcon, ChartBarIcon, ArrowTrendingUpIcon,
    CalendarDaysIcon, DocumentTextIcon, BookOpenIcon, ClockIcon,
    ArrowRightIcon, BoltIcon, TrophyIcon, ExclamationCircleIcon,
    PlusIcon, PencilSquareIcon, EnvelopeIcon, CheckBadgeIcon,
    ChevronRightIcon, RectangleStackIcon, ChevronDownIcon,
    BellAlertIcon, ArrowsRightLeftIcon, FaceSmileIcon, NoSymbolIcon,
    SparklesIcon, LightBulbIcon, FireIcon,
    MagnifyingGlassIcon, FunnelIcon,
} from '@heroicons/vue/24/outline'

import PageHeader from '@/Components/PageHeader.vue'
import StatTileDelta from '@/Components/dashboard/StatTileDelta.vue'
import ActivityFeed from '@/Components/dashboard/ActivityFeed.vue'
import CalendarWidget from '@/Components/dashboard/CalendarWidget.vue'
import ActionPill from '@/Components/dashboard/ActionPill.vue'
import BarChartHorizontal from '@/Components/charts/BarChartHorizontal.vue'
import DonutChart from '@/Components/charts/DonutChart.vue'
import TrendLineChart from '@/Components/charts/TrendLineChart.vue'

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    role: { type: String, default: '' },
    currentSession: { type: Object, default: null },
    recentExams: { type: Array, default: () => [] },
    recentStudents: { type: Array, default: () => [] },
    sectionRoster: { type: Object, default: () => ({ rows: [], empty_sections: 0, total_sections: 0 }) },
    schoolWiseComparison: { type: Array, default: () => [] },
    classWisePerformance: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
    pendingExams: { type: Array, default: () => [] },
    needsAttention: { type: Array, default: () => [] },
    setupStatus: { type: Object, default: null },
    todaysClasses: { type: Object, default: null },
    charts: { type: Object, default: () => ({ passFail: [], gradeDist: {}, sessionTrend: [] }) },
    hero: { type: Object, default: () => ({ value: 0, delta: null, sparkline: [], sample_size: 0 }) },
    insight: { type: String, default: null },
    activity: { type: Array, default: () => [] },
    calendar: { type: Object, default: () => ({ markers: [] }) },
})

const page = usePage()
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

const roleLabels = {
    'super-admin':     'District Drawing Officer',
    'school-admin':    'Principal',
    'class-teacher':   'Class Teacher',
    'subject-teacher': 'Subject Teacher',
}

// Role-aware tone: shifts the page-header gradient & accent color so the
// dashboard feels different per role at a glance. Same tokens as PageHeader
// supports — keeps the rest of the codebase consistent.
const roleTone = computed(() => ({
    'super-admin':     'primary',
    'school-admin':    'sky',
    'class-teacher':   'emerald',
    'subject-teacher': 'violet',
}[props.role] || 'primary'))

// ─── Today's Classes timeline (teachers only, kept from prior version) ───
const nowTick = ref(Date.now())
let _tick = null
onMounted(() => { _tick = setInterval(() => { nowTick.value = Date.now() }, 30000) })
onBeforeUnmount(() => { if (_tick) clearInterval(_tick) })

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

// ─── Attention items (used by the ActionPill) ───
const attentionItems = computed(() => {
    const items = (props.needsAttention || []).map(item => ({
        key: item.key,
        severity: item.severity || 'info',
        title: item.title,
        description: item.description,
        action_label: item.action_label,
        action_url: item.action_url,
        count: item.count,
    }))
    if (props.role === 'super-admin' && contactMessageCount.value > 0) {
        items.push({
            key: 'contact_messages',
            severity: 'info',
            title: `${contactMessageCount.value} new contact message${contactMessageCount.value === 1 ? '' : 's'}`,
            description: 'From visitors who used the public website contact form.',
            action_label: 'View Inbox',
            action_url: '/website/contact-messages',
            count: contactMessageCount.value,
        })
    }
    return items
})

// ─── Quick actions (now in the page header, not a section) ───
const quickActions = computed(() => {
    const all = [
        { perm: 'exams.create',    label: 'New Exam',     href: '/exams/create',     icon: ClipboardDocumentListIcon },
        { perm: 'students.create', label: 'Add Student',  href: '/students/create',  icon: UserGroupIcon },
        { perm: 'marks.enter',     label: 'Enter Marks',  href: '/marks',            icon: DocumentTextIcon },
        { perm: 'questions.create',label: 'Add Question', href: '/questions/create', icon: BookOpenIcon },
    ]
    return all.filter(a => hasPerm(a.perm)).slice(0, 3)
})

// ─── Stat tiles (with delta) ───
const statTiles = computed(() => {
    const r = props.role
    const heroDelta = props.hero?.delta ?? null
    if (r === 'super-admin') return [
        { label: 'Pass Rate',  value: (props.stats?.passRate ?? 0) + '%', icon: ArrowTrendingUpIcon, color: 'primary',
          delta: heroDelta, deltaSuffix: '%', spark: props.hero?.sparkline || [] },
        { label: 'Schools',    value: props.stats?.totalSchools ?? 0,     icon: BuildingOfficeIcon, color: 'sky',     delta: null },
        { label: 'Students',   value: props.stats?.totalStudents ?? 0,    icon: UserGroupIcon,      color: 'emerald', delta: null },
        { label: 'Teachers',   value: props.stats?.totalTeachers ?? 0,    icon: AcademicCapIcon,    color: 'violet',  delta: null },
    ]
    if (r === 'school-admin') return [
        { label: 'Pass Rate',  value: (props.stats?.passRate ?? 0) + '%', icon: ArrowTrendingUpIcon, color: 'primary',
          delta: heroDelta, deltaSuffix: '%', spark: props.hero?.sparkline || [] },
        { label: 'Students',   value: props.stats?.totalStudents ?? 0,    icon: UserGroupIcon,      color: 'emerald', delta: null },
        { label: 'Classes',    value: props.stats?.totalClasses ?? 0,     icon: AcademicCapIcon,    color: 'sky',     delta: null },
        { label: 'Active Exams', value: props.stats?.activeExams ?? 0,    icon: ClipboardDocumentListIcon, color: 'amber', delta: null },
    ]
    if (r === 'class-teacher') return [
        { label: 'My Sections',   value: props.stats?.totalSections ?? 0,     icon: RectangleStackIcon, color: 'primary' },
        { label: 'Students',      value: props.stats?.totalStudents ?? 0,     icon: UserGroupIcon,      color: 'emerald' },
        { label: 'Pending Marks', value: props.stats?.pendingMarksEntry ?? 0, icon: ClockIcon,          color: 'amber' },
    ]
    if (r === 'subject-teacher') return [
        { label: 'Subjects',      value: props.stats?.assignedSubjects ?? 0,  icon: BookOpenIcon,       color: 'primary' },
        { label: 'Sections',      value: props.stats?.assignedSections ?? 0,  icon: RectangleStackIcon, color: 'emerald' },
        { label: 'Pending Marks', value: props.stats?.pendingMarksEntry ?? 0, icon: ClockIcon,          color: 'amber' },
    ]
    return []
})

// ─── Welcome state — show only if literally everything is empty ───
const isFreshStart = computed(() => {
    const s = props.stats || {}
    const sums = [s.totalSchools, s.totalStudents, s.totalClasses, s.totalTeachers, s.totalSections, s.assignedSubjects]
        .filter(v => v !== undefined && v !== null)
    if (!sums.length) return false
    return sums.every(v => Number(v) === 0)
})

// ─── Charts ───
const showCharts = computed(() => ['super-admin', 'school-admin'].includes(props.role))
const totalResultsCharted = computed(() =>
    (props.charts?.passFail || []).reduce((a, s) => a + (s.value || 0), 0)
)
const overallPassRate = computed(() => {
    const total = totalResultsCharted.value
    if (!total) return 0
    const passed = (props.charts?.passFail || []).find(s => s.label === 'Passed')?.value || 0
    return Math.round(passed / total * 100)
})
const gradeRows = computed(() => {
    const colors = { 'A1': 'emerald', 'A+': 'emerald', 'A': 'emerald', 'B+': 'sky', 'B': 'sky', 'C+': 'amber', 'C': 'amber', 'D': 'rose', 'F': 'rose', 'FAIL': 'rose' }
    const order = ['A1','A+','A','B+','B','C+','C','D','E','F','FAIL','—']
    return Object.entries(props.charts?.gradeDist || {})
        .sort(([a], [b]) => {
            const ai = order.indexOf(a), bi = order.indexOf(b)
            if (ai === -1 && bi === -1) return a.localeCompare(b)
            if (ai === -1) return 1
            if (bi === -1) return -1
            return ai - bi
        })
        .map(([grade, count]) => ({
            label: `Grade ${grade}`,
            value: count,
            color: colors[grade] || 'primary',
        }))
})
const compareBars = computed(() => {
    if (props.role === 'super-admin') {
        return (props.schoolWiseComparison || []).map(s => ({
            label: s.name,
            sub: `${s.students_count || 0} students`,
            value: s.pass_percentage || 0,
            color: (s.pass_percentage >= 80) ? 'emerald'
                 : (s.pass_percentage >= 50) ? 'amber' : 'rose',
        }))
    }
    return (props.classWisePerformance || []).map(c => ({
        label: c.name,
        sub: `${c.total_students || 0} students`,
        value: c.pass_percentage || 0,
        color: (c.pass_percentage >= 80) ? 'emerald'
             : (c.pass_percentage >= 50) ? 'amber' : 'rose',
    }))
})
const compareTitle = computed(() => props.role === 'super-admin' ? 'School Comparison' : 'Class Performance')

// ─── Section roster: search + empty-only filter ───
// The section roster is one of the most-scanned widgets on the admin
// dashboard. With 20+ sections you can't eyeball it fast, especially to find
// "which sections still need students added". A tiny search + toggle solves it
// without leaving the page.
const rosterSearch = ref('')
const rosterEmptyOnly = ref(false)
const filteredRoster = computed(() => {
    const rows = props.sectionRoster?.rows || []
    const q = rosterSearch.value.trim().toLowerCase()
    return rows.filter(r => {
        if (rosterEmptyOnly.value && (r.count || 0) > 0) return false
        if (!q) return true
        return (r.class || '').toLowerCase().includes(q)
            || (r.section || '').toLowerCase().includes(q)
            || (r.teacher || '').toLowerCase().includes(q)
    })
})

// ─── Status helpers ───
const statusBadge = (status) => ({
    draft:        'bg-base-200 text-base-content/65',
    published:    'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    marks_entry:  'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    completed:    'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
}[status] || 'bg-base-200')
const statusLabel = (s) => s?.replace(/_/g, ' ') || '—'
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="[{ label: 'Dashboard' }]">
        <div class="space-y-4 sm:space-y-5 max-w-7xl mx-auto">

            <!-- ════════ WELCOME (first-run mode only) ════════ -->
            <div v-if="isFreshStart" class="rounded-2xl p-6 sm:p-8 text-center"
                 style="background: linear-gradient(135deg, oklch(var(--p) / 0.08), oklch(var(--p) / 0.02));
                        border: 1px solid oklch(var(--p) / 0.2);">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-primary text-primary-content flex items-center justify-center shadow-lg shadow-primary/30 mb-3">
                    <SparklesIcon class="w-7 h-7" />
                </div>
                <h2 class="text-xl sm:text-2xl font-extrabold tracking-tight">Welcome, {{ userName }}!</h2>
                <p class="text-sm text-base-content/65 mt-1.5 max-w-md mx-auto">
                    Let's get you set up. The dashboard will fill with charts and insights once you add some data.
                </p>
                <div class="mt-4 flex items-center justify-center gap-2 flex-wrap">
                    <Link v-if="hasPerm('schools.create')" href="/schools/create" class="btn btn-primary btn-sm rounded-xl gap-1.5">
                        <BuildingOfficeIcon class="w-4 h-4" /> Add school
                    </Link>
                    <Link v-if="hasPerm('students.create')" href="/students/create" class="btn btn-outline btn-sm rounded-xl gap-1.5">
                        <UserGroupIcon class="w-4 h-4" /> Add student
                    </Link>
                    <Link href="/dashboard" v-if="setupStatus" class="btn btn-ghost btn-sm rounded-xl">
                        Setup checklist →
                    </Link>
                </div>
            </div>

            <!-- ════════ HEADER (compact, professional) ════════ -->
            <!-- Tone shifts per role so a class teacher's dashboard reads
                 visually distinct from a principal's at a single glance. -->
            <PageHeader
                :title="`${greeting}, ${userName}`"
                :subtitle="`${roleLabels[role] || role} · ${currentSession?.name || 'No active session'} · ${todayLabel}`"
                :icon="AcademicCapIcon" :tone="roleTone" />

            <!-- ════════ INSIGHT BANNER (auto-summary) ════════ -->
            <div v-if="insight" class="rounded-2xl bg-gradient-to-r from-primary/[0.08] to-emerald-500/[0.06] border border-primary/15 px-4 py-3 flex items-center gap-3">
                <LightBulbIcon class="w-5 h-5 text-primary shrink-0" />
                <p class="text-sm text-base-content/85 font-medium flex-1">{{ insight }}</p>
            </div>

            <!-- ════════ ACTION PILL (setup + attention, demoted) ════════ -->
            <ActionPill :setup-status="setupStatus" :attention-items="attentionItems" />

            <!-- ════════ TODAY'S TEACHING SCHEDULE (teachers only) ════════ -->
            <section v-if="todaysClasses && (role === 'class-teacher' || role === 'subject-teacher')"
                class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden shadow-sm">
                <header class="px-4 sm:px-5 py-3 border-b border-base-200 flex items-center justify-between gap-2 flex-wrap">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-7 h-7 rounded-lg bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                            <ClockIcon class="w-4 h-4" />
                        </div>
                        <h2 class="font-bold text-sm">Today's Schedule</h2>
                        <span class="text-[11px] text-base-content/55">
                            · {{ todayMerged.length }} period{{ todayMerged.length === 1 ? '' : 's' }}
                            <span v-if="todaysClasses.covers?.length" class="text-amber-600 dark:text-amber-400 font-bold">
                                + {{ todaysClasses.covers.length }} cover{{ todaysClasses.covers.length === 1 ? '' : 's' }}
                            </span>
                        </span>
                    </div>
                    <Link :href="route('timetable.teacher', $page.props.auth.user.id)"
                        class="btn btn-ghost btn-xs rounded-lg gap-1">
                        <CalendarDaysIcon class="w-3.5 h-3.5" /> My Timetable
                    </Link>
                </header>

                <div v-if="todaysClasses.is_off_day" class="px-5 py-6 text-center">
                    <FaceSmileIcon class="w-9 h-9 text-emerald-500 mx-auto mb-1.5" />
                    <p class="font-bold text-sm">No classes today — enjoy your day off!</p>
                </div>

                <div v-else-if="todaysClasses.absent" class="px-4 py-3 bg-rose-500/10 border-b border-rose-500/20 flex items-start gap-3">
                    <NoSymbolIcon class="w-5 h-5 text-rose-600 dark:text-rose-400 mt-0.5 shrink-0" />
                    <div class="text-sm">
                        <p class="font-bold text-rose-900 dark:text-rose-200">You're marked absent today</p>
                        <p class="text-xs text-rose-700 dark:text-rose-300/80 mt-0.5">Cover periods you've been assigned (if any) appear below.</p>
                    </div>
                </div>

                <div v-if="!todaysClasses.is_off_day && !todayMerged.length" class="px-5 py-6 text-center">
                    <CalendarDaysIcon class="w-9 h-9 text-base-content/25 mx-auto mb-1.5" />
                    <p class="font-bold text-sm">No classes scheduled today</p>
                </div>

                <div v-else-if="!todaysClasses.is_off_day" class="overflow-x-auto p-3">
                    <div class="flex gap-2 min-w-min">
                        <div v-for="(p, idx) in todayMerged" :key="idx"
                            class="rounded-xl p-3 ring-2 transition-colors min-w-[170px] flex-shrink-0"
                            :class="[
                                periodState(p) === 'ongoing' ? 'ring-emerald-500/60 bg-emerald-500/10' :
                                periodState(p) === 'starting-soon' ? 'ring-amber-500/60 bg-amber-500/10 animate-pulse' :
                                periodState(p) === 'past' ? 'ring-base-200 bg-base-200/40 opacity-60' :
                                p.kind === 'cover' ? 'ring-amber-500/40 bg-amber-500/5' :
                                'ring-base-200 bg-base-100',
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
                        </div>
                    </div>
                </div>
            </section>

            <!-- ════════ STATS ROW ════════ -->
            <section v-if="statTiles.length" class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3">
                <StatTileDelta v-for="tile in statTiles" :key="tile.label"
                    :label="tile.label"
                    :value="tile.value"
                    :icon="tile.icon"
                    :color="tile.color"
                    :delta="tile.delta"
                    :delta-suffix="tile.deltaSuffix || '%'"
                    :spark="tile.spark || []" />
            </section>

            <!-- ════════ STUDENTS BY CLASS / SECTION — surfaced near top (actionable) ════════ -->
            <section v-if="(role === 'super-admin' || role === 'school-admin') && sectionRoster?.rows?.length"
                class="rounded-2xl bg-base-100 border border-base-300 shadow-sm overflow-hidden">
                <header class="px-4 py-3 border-b border-base-200 flex items-center justify-between gap-2 flex-wrap">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-7 h-7 rounded-lg bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                            <UserGroupIcon class="w-4 h-4" />
                        </div>
                        <h2 class="text-sm font-bold truncate">Students by class &amp; section</h2>
                        <span class="text-[11px] text-base-content/55 whitespace-nowrap">
                            · {{ filteredRoster.length }} / {{ sectionRoster.rows.length }}
                        </span>
                    </div>
                    <span v-if="sectionRoster.empty_sections" class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold whitespace-nowrap">
                        {{ sectionRoster.empty_sections }} of {{ sectionRoster.total_sections }} empty
                    </span>
                </header>

                <!-- Search + empty-only filter — turns this widget from a wall
                     of names into an actionable triage list. -->
                <div class="px-3 py-2.5 border-b border-base-200 bg-base-200/30 flex items-center gap-2 flex-wrap">
                    <div class="flex-1 min-w-[180px] flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-base-100 border border-base-300 focus-within:border-primary/50">
                        <MagnifyingGlassIcon class="w-4 h-4 text-base-content/40 shrink-0" />
                        <input v-model="rosterSearch" type="text"
                            placeholder="Search class, section, teacher…"
                            class="bg-transparent outline-none flex-1 text-xs min-w-0" />
                    </div>
                    <button type="button" @click="rosterEmptyOnly = !rosterEmptyOnly"
                        class="text-[11px] font-semibold px-2.5 py-1.5 rounded-lg border transition-colors flex items-center gap-1.5 whitespace-nowrap"
                        :class="rosterEmptyOnly
                            ? 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30'
                            : 'bg-base-100 text-base-content/65 border-base-300 hover:border-rose-500/30 hover:text-rose-700'">
                        <FunnelIcon class="w-3.5 h-3.5" />
                        Empty only
                    </button>
                </div>

                <div v-if="filteredRoster.length" class="max-h-96 overflow-y-auto grid grid-cols-1 sm:grid-cols-2">
                    <div v-for="r in filteredRoster" :key="r.id"
                        class="flex items-center gap-3 px-4 py-2.5 border-b border-base-200 last:border-b-0 sm:[&:nth-last-child(-n+2)]:border-b-0"
                        :class="r.count === 0 ? 'bg-rose-500/[0.04]' : ''">
                        <!-- Gradient class-pill, matches the visual language
                             used in MyClass/MySubjects pages for consistency. -->
                        <div class="w-9 h-9 rounded-xl shrink-0 flex items-center justify-center text-white font-bold text-[11px] shadow-md"
                             :class="r.count === 0
                                ? 'bg-gradient-to-br from-rose-500 to-pink-600 shadow-rose-500/15'
                                : 'bg-gradient-to-br from-sky-500 to-indigo-600 shadow-sky-500/15'">
                            {{ (r.class || '?').toString().substring(0, 2).toUpperCase() }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm truncate">
                                Class {{ r.class }} <span class="text-base-content/55">— {{ r.section }}</span>
                            </p>
                            <p class="text-[11px] text-base-content/55 truncate">
                                <span class="text-base-content/45">CT:</span>
                                <span class="font-medium text-base-content/75">{{ r.teacher || '—' }}</span>
                            </p>
                        </div>
                        <span v-if="r.count > 0"
                            class="px-2.5 py-1 rounded-lg text-xs font-bold tabular-nums shrink-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300">
                            {{ r.count }}
                        </span>
                        <span v-else
                            class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider shrink-0 bg-rose-500/15 text-rose-700 dark:text-rose-300 whitespace-nowrap">
                            Empty
                        </span>
                    </div>
                </div>
                <div v-else class="px-5 py-8 text-center">
                    <MagnifyingGlassIcon class="w-8 h-8 text-base-content/25 mx-auto mb-1.5" />
                    <p class="text-xs text-base-content/55">
                        No sections match your filters.
                        <button type="button" @click="rosterSearch = ''; rosterEmptyOnly = false" class="text-primary font-semibold ml-1">Clear</button>
                    </p>
                </div>
            </section>

            <!-- ════════ MAIN GRID — 2-column on lg+ ════════ -->
            <div v-if="showCharts && totalResultsCharted > 0" class="grid grid-cols-1 lg:grid-cols-12 gap-3 sm:gap-4">
                <!-- LEFT (8 cols on lg) — primary charts -->
                <div class="lg:col-span-8 space-y-3 sm:space-y-4">
                    <!-- Trend chart -->
                    <article class="rounded-xl bg-base-100 border border-base-300 shadow-sm p-4 sm:p-5">
                        <header class="flex items-center justify-between mb-3 gap-2 flex-wrap">
                            <div>
                                <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-base-content/45">Trend</p>
                                <h3 class="text-sm sm:text-base font-bold mt-0.5">Pass Rate Over Last {{ charts.sessionTrend?.length || 0 }} Sessions</h3>
                            </div>
                            <ArrowTrendingUpIcon class="w-5 h-5 text-primary opacity-60" />
                        </header>
                        <TrendLineChart :points="charts.sessionTrend || []" color="primary" :height="180" unit="%" />
                    </article>

                    <!-- Comparison bars -->
                    <article v-if="compareBars.length" class="rounded-xl bg-base-100 border border-base-300 shadow-sm p-4 sm:p-5">
                        <header class="flex items-center justify-between mb-3 gap-2 flex-wrap">
                            <div>
                                <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-base-content/45">Compare</p>
                                <h3 class="text-sm sm:text-base font-bold mt-0.5">{{ compareTitle }}</h3>
                            </div>
                            <span class="text-[11px] text-base-content/55">Pass rate</span>
                        </header>
                        <BarChartHorizontal :rows="compareBars" :max="100" unit="%" />
                    </article>
                </div>

                <!-- RIGHT (4 cols on lg) — feed + calendar + breakdown -->
                <div class="lg:col-span-4 space-y-3 sm:space-y-4">
                    <!-- Pass/Fail donut + grade dist (compact) -->
                    <article class="rounded-xl bg-base-100 border border-base-300 shadow-sm p-4 sm:p-5">
                        <header class="mb-3">
                            <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-base-content/45">Breakdown</p>
                            <h3 class="text-sm font-bold mt-0.5">Pass / Fail</h3>
                        </header>
                        <DonutChart :segments="charts.passFail" center-label="Pass" :center-value="overallPassRate + '%'"
                            :size="140" :stroke="20" />
                    </article>

                    <!-- Grade distribution -->
                    <article v-if="gradeRows.length" class="rounded-xl bg-base-100 border border-base-300 shadow-sm p-4 sm:p-5">
                        <header class="mb-3 flex items-center gap-2">
                            <TrophyIcon class="w-4 h-4 text-amber-500" />
                            <h3 class="text-sm font-bold">Grade Distribution</h3>
                        </header>
                        <BarChartHorizontal :rows="gradeRows" />
                    </article>

                    <!-- Calendar -->
                    <article v-if="calendar?.month_label" class="rounded-xl bg-base-100 border border-base-300 shadow-sm p-4 sm:p-5">
                        <CalendarWidget
                            :month="calendar.month"
                            :month-label="calendar.month_label"
                            :today="calendar.today"
                            :markers="calendar.markers" />
                    </article>

                    <!-- Activity feed -->
                    <article class="rounded-2xl bg-base-100 border border-base-300 shadow-sm overflow-hidden">
                        <header class="px-4 py-3 border-b border-base-200 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-7 h-7 rounded-lg bg-rose-500/15 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                                    <FireIcon class="w-4 h-4" />
                                </div>
                                <h3 class="text-sm font-bold">Activity</h3>
                            </div>
                            <Link href="/notifications" class="text-[11px] text-base-content/55 hover:text-primary font-medium whitespace-nowrap">
                                View all →
                            </Link>
                        </header>
                        <ActivityFeed :items="activity" />
                    </article>
                </div>
            </div>

            <!-- Empty state for admins with no chart data yet -->
            <div v-else-if="showCharts && !isFreshStart" class="rounded-2xl bg-base-100 border border-dashed border-base-300 p-6 text-center">
                <ChartBarIcon class="w-10 h-10 text-base-content/30 mx-auto mb-2" />
                <p class="font-bold text-sm">Performance charts will appear after the first exam result is published</p>
                <p class="text-xs text-base-content/55 mt-1">Pass-rate trend, grade distribution, and comparison.</p>
            </div>

            <!-- Teachers: 2-column main with calendar + activity on right -->
            <div v-else-if="role === 'class-teacher' || role === 'subject-teacher'"
                class="grid grid-cols-1 lg:grid-cols-12 gap-3 sm:gap-4">
                <div class="lg:col-span-8 space-y-3 sm:space-y-4">
                    <!-- Class teacher: my sections -->
                    <section v-if="role === 'class-teacher' && sections?.length"
                        class="rounded-2xl bg-base-100 border border-base-300 shadow-sm overflow-hidden">
                        <header class="px-4 py-3 border-b border-base-200 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-7 h-7 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                    <RectangleStackIcon class="w-4 h-4" />
                                </div>
                                <h2 class="text-sm font-bold">My Sections</h2>
                                <span class="text-[11px] text-base-content/55">· {{ sections.length }}</span>
                            </div>
                            <Link href="/my-class" class="text-[11px] text-base-content/55 hover:text-primary font-medium whitespace-nowrap">
                                Open hub →
                            </Link>
                        </header>
                        <div class="divide-y divide-base-200">
                            <Link v-for="s in sections" :key="s.id" :href="`/my-class?section=${s.id}`"
                                class="flex items-center gap-3 px-4 py-3 hover:bg-base-200/40 transition-colors">
                                <div class="w-9 h-9 rounded-xl shrink-0 bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center font-bold text-[11px] shadow-md shadow-emerald-500/15">
                                    {{ (s.class_name || '?').substring(0, 2).toUpperCase() }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-sm truncate">{{ s.class_name }} — {{ s.name }}</p>
                                    <p class="text-[11px] text-base-content/55">{{ s.students_count }} students</p>
                                </div>
                                <ArrowRightIcon class="w-4 h-4 text-base-content/40 shrink-0" />
                            </Link>
                        </div>
                    </section>

                    <!-- Subject teacher: assignments -->
                    <section v-if="role === 'subject-teacher' && assignments?.length"
                        class="rounded-2xl bg-base-100 border border-base-300 shadow-sm overflow-hidden">
                        <header class="px-4 py-3 border-b border-base-200 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-7 h-7 rounded-lg bg-violet-500/15 text-violet-600 dark:text-violet-400 flex items-center justify-center shrink-0">
                                    <BookOpenIcon class="w-4 h-4" />
                                </div>
                                <h2 class="text-sm font-bold">My Assignments</h2>
                                <span class="text-[11px] text-base-content/55">· {{ assignments.length }}</span>
                            </div>
                            <Link href="/my-subjects" class="text-[11px] text-base-content/55 hover:text-primary font-medium whitespace-nowrap">
                                View all →
                            </Link>
                        </header>
                        <div class="divide-y divide-base-200">
                            <Link v-for="a in assignments" :key="a.id"
                                :href="`/marks?subject=${a.subject_id}&section=${a.section_id}`"
                                class="flex items-center gap-3 px-4 py-3 hover:bg-base-200/40 transition-colors">
                                <div class="w-9 h-9 rounded-xl shrink-0 bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white flex items-center justify-center text-[10px] font-bold shadow-md shadow-violet-500/15">
                                    {{ (a.subject_code || a.subject_name || '?').substring(0, 2).toUpperCase() }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-sm truncate">{{ a.subject_name }}</p>
                                    <p class="text-[11px] text-base-content/55 truncate">{{ a.class_name }} · {{ a.section_name }}</p>
                                </div>
                                <ArrowRightIcon class="w-4 h-4 text-base-content/40 shrink-0" />
                            </Link>
                        </div>
                    </section>
                </div>

                <div class="lg:col-span-4 space-y-3 sm:space-y-4">
                    <article v-if="calendar?.month_label" class="rounded-xl bg-base-100 border border-base-300 shadow-sm p-4 sm:p-5">
                        <CalendarWidget
                            :month="calendar.month"
                            :month-label="calendar.month_label"
                            :today="calendar.today"
                            :markers="calendar.markers" />
                    </article>

                    <article class="rounded-xl bg-base-100 border border-base-300 shadow-sm overflow-hidden">
                        <header class="px-4 py-3 border-b border-base-200 flex items-center gap-2">
                            <FireIcon class="w-4 h-4 text-rose-500" />
                            <h3 class="text-sm font-bold">Activity</h3>
                        </header>
                        <ActivityFeed :items="activity" />
                    </article>
                </div>
            </div>

            <!-- ════════ SECONDARY PANELS — tidy 2-column grid ════════ -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4 items-start">

            <!-- ════════ RECENT EXAMS — compact 1-line rows ════════ -->
            <section v-if="recentExams?.length" class="rounded-2xl bg-base-100 border border-base-300 shadow-sm overflow-hidden">
                <header class="px-4 py-3 border-b border-base-200 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-7 h-7 rounded-lg bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <ClipboardDocumentListIcon class="w-4 h-4" />
                        </div>
                        <h2 class="text-sm font-bold">Recent Exams</h2>
                    </div>
                    <Link href="/exams" class="text-[11px] text-base-content/55 hover:text-primary font-medium whitespace-nowrap">
                        View all →
                    </Link>
                </header>
                <div class="divide-y divide-base-200">
                    <Link v-for="exam in recentExams" :key="exam.id" :href="`/exams/${exam.id}`"
                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-base-200/40 transition-colors">
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm truncate">{{ exam.name }}</p>
                            <p class="text-[11px] text-base-content/55 truncate">
                                {{ exam.type }}
                                <span v-if="exam.start_date"> · {{ exam.start_date }}</span>
                            </p>
                        </div>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider whitespace-nowrap"
                            :class="statusBadge(exam.status)">
                            {{ statusLabel(exam.status) }}
                        </span>
                        <ChevronRightIcon class="w-3.5 h-3.5 text-base-content/40 shrink-0" />
                    </Link>
                </div>
            </section>

            <!-- ════════ RECENTLY ADDED STUDENTS — who added them ════════ -->
            <section v-if="(role === 'super-admin' || role === 'school-admin') && recentStudents?.length"
                class="rounded-2xl bg-base-100 border border-base-300 shadow-sm overflow-hidden">
                <header class="px-4 py-3 border-b border-base-200 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-7 h-7 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                            <UserGroupIcon class="w-4 h-4" />
                        </div>
                        <h2 class="text-sm font-bold">Recently added students</h2>
                    </div>
                    <Link href="/activity-log?subject_type=App\Models\Student"
                        class="text-[11px] text-base-content/55 hover:text-primary font-medium whitespace-nowrap">
                        Full history →
                    </Link>
                </header>
                <div class="divide-y divide-base-200">
                    <Link v-for="s in recentStudents" :key="s.id" :href="`/students/${s.id}`"
                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-base-200/40 transition-colors">
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm truncate">
                                {{ s.name }}
                                <span class="font-mono text-[11px] text-base-content/45">#{{ s.admission_no }}</span>
                            </p>
                            <p class="text-[11px] text-base-content/55 truncate">
                                {{ s.class }} · added by <span class="font-semibold text-base-content/75">{{ s.added_by }}</span>
                                <span v-if="s.when"> · {{ s.when }}</span>
                            </p>
                        </div>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider whitespace-nowrap"
                            :class="s.status === 'active' ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-amber-500/15 text-amber-700 dark:text-amber-300'">
                            {{ s.status }}
                        </span>
                        <ChevronRightIcon class="w-3.5 h-3.5 text-base-content/40 shrink-0" />
                    </Link>
                </div>
            </section>


            </div><!-- /secondary panels grid -->

            <!-- ════════ QUICK CTA STRIP (bottom, replaces big quick-action tiles) ════════ -->
            <section v-if="quickActions.length" class="flex items-center gap-2 flex-wrap pt-1 pb-1">
                <span class="text-[11px] uppercase tracking-wider font-bold text-base-content/45 mr-1">Quick:</span>
                <Link v-for="a in quickActions" :key="a.href" :href="a.href"
                    class="btn btn-ghost btn-sm rounded-xl gap-1.5 ring-1 ring-base-200 hover:ring-primary/30 hover:bg-primary/5">
                    <component :is="a.icon" class="w-3.5 h-3.5" />
                    <span>{{ a.label }}</span>
                </Link>
            </section>
        </div>
    </AppLayout>
</template>
