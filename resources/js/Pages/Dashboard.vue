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
    MagnifyingGlassIcon, FunnelIcon, Squares2X2Icon,
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
            // 'FAIL' is a backend grade code; show it as 'Retry' in the UI
            // without changing the underlying data key used for color lookup.
            label: grade === 'FAIL' ? 'Grade Retry' : `Grade ${grade}`,
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

// ─── Extra derivatives for the denser dashboard layout ───
// All computed from existing props — no backend changes required.

// Top 5 schools by pass rate (super-admin only). Sorted descending.
const topSchools = computed(() =>
    (props.schoolWiseComparison || [])
        .slice()
        .sort((a, b) => (b.pass_percentage || 0) - (a.pass_percentage || 0))
        .slice(0, 5)
)
// Bottom 5 schools — the ones that need attention.
const attentionSchools = computed(() =>
    (props.schoolWiseComparison || [])
        .slice()
        .filter(s => (s.students_count || 0) > 0)   // ignore empty shells
        .sort((a, b) => (a.pass_percentage || 0) - (b.pass_percentage || 0))
        .slice(0, 5)
)
// Aggregate student count per class from the section roster — powers the
// "Students by Class" donut. Keys sorted by count descending.
const classStudentCounts = computed(() => {
    const rows = props.sectionRoster?.rows || []
    const map = new Map()
    for (const r of rows) {
        const cls = String(r.class || '—')
        map.set(cls, (map.get(cls) || 0) + (r.count || 0))
    }
    return Array.from(map.entries())
        .sort(([, a], [, b]) => b - a)
        .map(([label, value]) => ({ label, value }))
})
const totalStudentsFromRoster = computed(() =>
    classStudentCounts.value.reduce((sum, r) => sum + r.value, 0)
)
// Section fill-rate: how many sections have students vs are empty.
const sectionFillStats = computed(() => {
    const rows = props.sectionRoster?.rows || []
    const total = rows.length
    const empty = rows.filter(r => (r.count || 0) === 0).length
    const filled = total - empty
    return { total, filled, empty, fillPct: total > 0 ? Math.round((filled / total) * 100) : 0 }
})
// Palette for the class-donut segments — DonutChart expects named color
// KEYS (not hex codes), which it maps to the app's canonical hex values.
const donutPalette = ['primary', 'sky', 'violet', 'amber', 'rose', 'emerald']
// Show top 6 classes individually; roll the rest into an "Other" bucket
// so the legend doesn't stretch the card by 15+ rows.
const CLASS_DONUT_TOP_N = 6
const classDonutSegments = computed(() => {
    const rows = classStudentCounts.value
    if (rows.length <= CLASS_DONUT_TOP_N + 1) {
        return rows.map((c, i) => ({
            label: c.label,
            value: c.value,
            color: donutPalette[i % donutPalette.length],
        }))
    }
    const top = rows.slice(0, CLASS_DONUT_TOP_N)
    const rest = rows.slice(CLASS_DONUT_TOP_N)
    const otherTotal = rest.reduce((s, r) => s + r.value, 0)
    return [
        ...top.map((c, i) => ({ label: c.label, value: c.value, color: donutPalette[i % donutPalette.length] })),
        { label: `Other (${rest.length} classes)`, value: otherTotal, color: 'sky' },
    ]
})

// ─── Compact KPI strip — 6 dense micro-metrics ───
// All derived from existing stats/schoolWiseComparison — no BE change.
const kpiTiles = computed(() => {
    const totalStudents = props.stats?.totalStudents ?? 0
    const totalTeachers = props.stats?.totalTeachers ?? 0
    const totalClasses  = props.stats?.totalClasses  ?? 0
    const totalSchools  = props.stats?.totalSchools  ?? 0
    const activeExams   = props.stats?.activeExams   ?? 0

    // Teacher:Student ratio (e.g. "1:34")
    const tsRatio = totalTeachers > 0
        ? `1:${Math.round(totalStudents / totalTeachers)}`
        : '—'

    // Average school size (students per school)
    const avgSchoolSize = totalSchools > 0
        ? Math.round(totalStudents / totalSchools)
        : 0

    // Average class size — from section roster if available, else derived.
    const avgClassSize = totalClasses > 0
        ? Math.round(totalStudents / Math.max(1, totalClasses))
        : 0

    return [
        { label: 'T : S Ratio',    value: tsRatio,             icon: UserGroupIcon,     color: 'primary' },
        { label: 'Avg School Size', value: avgSchoolSize,      icon: BuildingOfficeIcon, color: 'sky' },
        { label: 'Avg Class Size',  value: avgClassSize,       icon: AcademicCapIcon,   color: 'violet' },
        { label: 'Active Exams',    value: activeExams,        icon: ClipboardDocumentListIcon, color: 'amber' },
        { label: 'Empty Sections',  value: sectionFillStats.value.empty, icon: NoSymbolIcon, color: 'rose' },
        { label: 'Total Classes',   value: totalClasses,       icon: RectangleStackIcon, color: 'emerald' },
    ]
})

// ─── All Schools quick table ───
// Sorted by pass rate desc; caps at 10 rows so the card stays compact.
// Includes the per-school teacher count + student:teacher ratio, so the
// principal / DDO can spot understaffed schools at a glance.
const schoolsTableRows = computed(() =>
    (props.schoolWiseComparison || [])
        .slice()
        .sort((a, b) => (b.pass_percentage || 0) - (a.pass_percentage || 0))
        .slice(0, 10)
        .map(s => {
            const students = s.students_count || 0
            const teachers = s.teachers_count || 0
            // Ratio expressed as students-per-teacher (higher = more strained).
            // Guard the divide so schools with 0 teachers show "—" not "Infinity".
            const ratioNum = teachers > 0 ? Math.round(students / teachers) : null
            return {
                name: s.name || '—',
                students,
                teachers,
                ratio: ratioNum !== null ? `1:${ratioNum}` : '—',
                ratioNum,
                // Stress bucket for the ratio pill colour.
                // ≤25 comfortable, 26-40 stretched, >40 critical.
                ratioTone:
                    ratioNum === null   ? 'rose'
                  : ratioNum <= 25      ? 'emerald'
                  : ratioNum <= 40      ? 'amber'
                  :                       'rose',
                passPct: Math.round(s.pass_percentage || 0),
                tone:
                    (s.pass_percentage || 0) >= 75 ? 'emerald'
                  : (s.pass_percentage || 0) >= 50 ? 'amber'
                  :                                  'rose',
            }
        })
)

// ─── Per-school teacher:student ratio bars ───
// Horizontal bar per school, colour-coded by stress. Sorted by ratio
// desc so the most-strained schools show at the top.
const schoolRatioBars = computed(() =>
    (props.schoolWiseComparison || [])
        .slice()
        .filter(s => (s.teachers_count || 0) > 0 && (s.students_count || 0) > 0)
        .map(s => {
            const ratioNum = Math.round((s.students_count || 0) / (s.teachers_count || 1))
            return {
                name: s.name || '—',
                ratioNum,
                students: s.students_count || 0,
                teachers: s.teachers_count || 0,
                tone: ratioNum <= 25 ? 'emerald' : ratioNum <= 40 ? 'amber' : 'rose',
            }
        })
        .sort((a, b) => b.ratioNum - a.ratioNum)
)
// Max ratio in the set — used to normalise bar widths so the worst school
// hits 100% and everyone else is proportional to that.
const maxRatio = computed(() =>
    schoolRatioBars.value.reduce((m, s) => Math.max(m, s.ratioNum), 1)
)

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

            <!-- ════════ WELCOME (first-run only) ════════ -->
            <div v-if="isFreshStart"
                 class="relative overflow-hidden rounded-3xl p-6 sm:p-10 text-center border border-primary/20
                        bg-gradient-to-br from-primary/[0.12] via-primary/[0.05] to-transparent">
                <div class="absolute -top-16 -right-16 w-48 h-48 rounded-full bg-primary/15 blur-3xl"></div>
                <div class="absolute -bottom-20 -left-20 w-56 h-56 rounded-full bg-emerald-500/10 blur-3xl"></div>
                <div class="relative">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-primary to-teal-600
                                text-primary-content flex items-center justify-center shadow-xl shadow-primary/30 mb-4">
                        <SparklesIcon class="w-8 h-8" />
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Welcome, {{ userName }}!</h2>
                    <p class="text-sm sm:text-base text-base-content/65 mt-2 max-w-md mx-auto">
                        Let's get you set up. Your dashboard will fill with charts and insights once you add some data.
                    </p>
                    <div class="mt-5 flex items-center justify-center gap-2 flex-wrap">
                        <Link v-if="hasPerm('schools.create')" href="/schools/create" class="btn btn-primary rounded-xl gap-1.5 shadow-md shadow-primary/25">
                            <BuildingOfficeIcon class="w-4 h-4" /> Add school
                        </Link>
                        <Link v-if="hasPerm('students.create')" href="/students/create" class="btn btn-outline rounded-xl gap-1.5">
                            <UserGroupIcon class="w-4 h-4" /> Add student
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ════════ HERO GREETING ════════
                 Bigger, more welcoming than the plain PageHeader. Combines
                 greeting, role/session/date context, and the top 3 quick
                 actions into one card that OWNS the mobile viewport top. -->
            <section
                class="relative overflow-hidden rounded-3xl border p-5 sm:p-7"
                :class="{
                    'border-primary/20 bg-gradient-to-br from-primary/[0.10] via-primary/[0.03] to-transparent': roleTone === 'primary',
                    'border-emerald-500/20 bg-gradient-to-br from-emerald-500/[0.10] via-emerald-500/[0.03] to-transparent': roleTone === 'emerald',
                    'border-sky-500/20 bg-gradient-to-br from-sky-500/[0.10] via-sky-500/[0.03] to-transparent': roleTone === 'sky',
                    'border-violet-500/20 bg-gradient-to-br from-violet-500/[0.10] via-violet-500/[0.03] to-transparent': roleTone === 'violet',
                }">
                <!-- Soft blurred accent — cheap depth cue, no images needed. -->
                <div class="pointer-events-none absolute -top-24 -right-24 w-64 h-64 rounded-full blur-3xl opacity-60"
                     :class="{
                        'bg-primary/20': roleTone === 'primary',
                        'bg-emerald-500/20': roleTone === 'emerald',
                        'bg-sky-500/20': roleTone === 'sky',
                        'bg-violet-500/20': roleTone === 'violet',
                     }"></div>

                <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3 sm:gap-4 min-w-0">
                        <div class="relative shrink-0">
                            <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl overflow-hidden ring-2 ring-white/70 dark:ring-white/10 shadow-lg"
                                 :class="{
                                    'shadow-primary/25': roleTone === 'primary',
                                    'shadow-emerald-500/25': roleTone === 'emerald',
                                    'shadow-sky-500/25': roleTone === 'sky',
                                    'shadow-violet-500/25': roleTone === 'violet',
                                 }">
                                <img v-if="userPhoto" :src="userPhoto" :alt="userName"
                                     class="w-full h-full object-cover" />
                                <div v-else
                                    class="w-full h-full flex items-center justify-center font-extrabold text-lg sm:text-xl text-white"
                                    :class="{
                                        'bg-gradient-to-br from-primary to-teal-600': roleTone === 'primary',
                                        'bg-gradient-to-br from-emerald-500 to-teal-600': roleTone === 'emerald',
                                        'bg-gradient-to-br from-sky-500 to-indigo-600': roleTone === 'sky',
                                        'bg-gradient-to-br from-violet-500 to-fuchsia-600': roleTone === 'violet',
                                    }">
                                    {{ (userName?.[0] || '?').toUpperCase() }}
                                </div>
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] uppercase tracking-[0.2em] font-bold text-base-content/55">
                                {{ todayLabel }}
                            </p>
                            <h1 class="text-xl sm:text-3xl font-extrabold tracking-tight leading-tight truncate">
                                {{ greeting }},
                                <span class="bg-gradient-to-r bg-clip-text text-transparent"
                                      :class="{
                                        'from-primary to-teal-600': roleTone === 'primary',
                                        'from-emerald-500 to-teal-600': roleTone === 'emerald',
                                        'from-sky-500 to-indigo-600': roleTone === 'sky',
                                        'from-violet-500 to-fuchsia-600': roleTone === 'violet',
                                      }">{{ userName }}</span>
                            </h1>
                            <p class="text-[13px] sm:text-sm text-base-content/60 mt-0.5 truncate">
                                {{ roleLabels[role] || role }}
                                <span v-if="currentSession?.name" class="text-base-content/40 mx-1.5">·</span>
                                <span v-if="currentSession?.name">{{ currentSession.name }}</span>
                            </p>
                        </div>
                    </div>

                    <!-- Quick actions — right-aligned on desktop, wrap on mobile. -->
                    <div v-if="quickActions.length" class="flex flex-wrap items-center gap-2 sm:flex-shrink-0">
                        <Link v-for="a in quickActions" :key="a.href" :href="a.href"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-base-100/80 backdrop-blur
                                   border border-base-300/50 shadow-sm hover:shadow-md hover:-translate-y-0.5
                                   text-xs sm:text-sm font-semibold transition-all">
                            <component :is="a.icon" class="w-4 h-4 opacity-70" />
                            {{ a.label }}
                        </Link>
                    </div>
                </div>
            </section>

            <!-- ════════ INSIGHT BANNER (auto-summary) ════════ -->
            <div v-if="insight"
                 class="rounded-2xl border border-primary/20 bg-gradient-to-r from-primary/[0.08] via-primary/[0.04] to-transparent
                        px-4 py-3 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-primary/15 text-primary flex items-center justify-center shrink-0">
                    <LightBulbIcon class="w-4 h-4" />
                </div>
                <p class="text-[13px] sm:text-sm text-base-content/85 font-medium flex-1">{{ insight }}</p>
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

            <!-- ════════ KPI STRIP — 6 compact micro-metrics ════════
                 Very dense — 2 cols on phones, 3 on tablets, 6 on desktop.
                 Small type, big numbers, colour-coded icon strip on the left. -->
            <div v-if="role === 'super-admin' || role === 'school-admin'"
                 class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 sm:gap-3">
                <div v-for="k in kpiTiles" :key="k.label"
                     class="relative rounded-xl bg-base-100 border border-base-300/70 shadow-sm p-3 flex items-center gap-3
                            hover:shadow-md transition-shadow overflow-hidden">
                    <span class="w-1 rounded-full self-stretch"
                          :class="{
                            'bg-gradient-to-b from-primary to-teal-600': k.color === 'primary',
                            'bg-gradient-to-b from-sky-500 to-indigo-600': k.color === 'sky',
                            'bg-gradient-to-b from-violet-500 to-fuchsia-600': k.color === 'violet',
                            'bg-gradient-to-b from-amber-500 to-orange-600': k.color === 'amber',
                            'bg-gradient-to-b from-rose-500 to-pink-600': k.color === 'rose',
                            'bg-gradient-to-b from-emerald-500 to-teal-600': k.color === 'emerald',
                          }"></span>
                    <component :is="k.icon" class="w-4 h-4 shrink-0"
                        :class="{
                            'text-primary': k.color === 'primary',
                            'text-sky-500': k.color === 'sky',
                            'text-violet-500': k.color === 'violet',
                            'text-amber-500': k.color === 'amber',
                            'text-rose-500': k.color === 'rose',
                            'text-emerald-500': k.color === 'emerald',
                        }" />
                    <div class="min-w-0 flex-1">
                        <p class="text-base sm:text-lg font-extrabold tabular-nums leading-none truncate">{{ k.value }}</p>
                        <p class="text-[10px] uppercase tracking-wider font-semibold text-base-content/55 mt-1 truncate">
                            {{ k.label }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- ════════ DENSE INSIGHTS — 2×2 grid ════════
                 Row 1: Top Schools · Needs Attention
                 Row 2: Section Fill Rate · Students by Class
                 Everything derived from the props the page already receives
                 — no backend change. -->
            <div v-if="role === 'super-admin' || role === 'school-admin'"
                 class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 items-start">

                <!-- 1. Top Schools ── mini-leaderboard -->
                <article v-if="topSchools.length"
                         class="rounded-2xl bg-gradient-to-br from-emerald-500/[0.06] via-base-100 to-base-100
                                border border-emerald-500/15 shadow-sm hover:shadow-md transition-shadow p-4 sm:p-5">
                    <header class="flex items-center gap-2.5 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 text-white
                                    flex items-center justify-center shadow-sm shadow-emerald-500/25 shrink-0">
                            <TrophyIcon class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-emerald-600/80 dark:text-emerald-400/80">Leaderboard</p>
                            <h3 class="text-sm font-bold">Top Schools</h3>
                        </div>
                    </header>
                    <ol class="space-y-1.5">
                        <li v-for="(s, i) in topSchools" :key="s.name || i"
                            class="flex items-center gap-2 text-xs">
                            <span class="w-5 h-5 rounded-md text-[10px] font-bold flex items-center justify-center shrink-0"
                                  :class="i === 0 ? 'bg-amber-500 text-white'
                                        : i === 1 ? 'bg-slate-400 text-white'
                                        : i === 2 ? 'bg-amber-700 text-white'
                                        : 'bg-base-200 text-base-content/60'">{{ i + 1 }}</span>
                            <span class="flex-1 truncate font-medium">{{ s.name || '—' }}</span>
                            <span class="font-bold tabular-nums text-emerald-700 dark:text-emerald-300 shrink-0">
                                {{ Math.round(s.pass_percentage || 0) }}%
                            </span>
                        </li>
                    </ol>
                </article>

                <!-- 2. Schools Needing Attention ── bottom performers -->
                <article v-if="attentionSchools.length"
                         class="rounded-2xl bg-gradient-to-br from-rose-500/[0.06] via-base-100 to-base-100
                                border border-rose-500/15 shadow-sm hover:shadow-md transition-shadow p-4 sm:p-5">
                    <header class="flex items-center gap-2.5 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-rose-500 to-pink-600 text-white
                                    flex items-center justify-center shadow-sm shadow-rose-500/25 shrink-0">
                            <ExclamationCircleIcon class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-rose-600/80 dark:text-rose-400/80">Needs Attention</p>
                            <h3 class="text-sm font-bold">Lowest Pass Rate</h3>
                        </div>
                    </header>
                    <ol class="space-y-1.5">
                        <li v-for="(s, i) in attentionSchools" :key="s.name || i"
                            class="flex items-center gap-2 text-xs">
                            <span class="w-5 h-5 rounded-md text-[10px] font-bold flex items-center justify-center shrink-0
                                         bg-rose-500/15 text-rose-700 dark:text-rose-300">{{ i + 1 }}</span>
                            <span class="flex-1 truncate font-medium">{{ s.name || '—' }}</span>
                            <span class="font-bold tabular-nums shrink-0"
                                  :class="(s.pass_percentage || 0) < 50 ? 'text-rose-700 dark:text-rose-300' : 'text-amber-700 dark:text-amber-300'">
                                {{ Math.round(s.pass_percentage || 0) }}%
                            </span>
                        </li>
                    </ol>
                </article>

                <!-- 3. Section Fill Rate ── donut + counters -->
                <article v-if="sectionFillStats.total > 0"
                         class="rounded-2xl bg-gradient-to-br from-sky-500/[0.06] via-base-100 to-base-100
                                border border-sky-500/15 shadow-sm hover:shadow-md transition-shadow p-4 sm:p-5">
                    <header class="flex items-center gap-2.5 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-sky-500 to-indigo-600 text-white
                                    flex items-center justify-center shadow-sm shadow-sky-500/25 shrink-0">
                            <Squares2X2Icon class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-sky-600/80 dark:text-sky-400/80">Coverage</p>
                            <h3 class="text-sm font-bold">Section Fill Rate</h3>
                        </div>
                    </header>
                    <div class="flex items-center gap-4">
                        <!-- Progress ring -->
                        <div class="relative w-20 h-20 shrink-0">
                            <svg viewBox="0 0 44 44" class="w-full h-full -rotate-90">
                                <circle cx="22" cy="22" r="18" fill="none" stroke="currentColor" stroke-width="4"
                                        class="text-base-200" />
                                <circle cx="22" cy="22" r="18" fill="none" stroke-width="4"
                                        stroke="oklch(var(--p))" stroke-linecap="round"
                                        :stroke-dasharray="`${(sectionFillStats.fillPct / 100) * 113.1} 113.1`" />
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center flex-col">
                                <span class="text-lg font-extrabold tabular-nums leading-none">{{ sectionFillStats.fillPct }}%</span>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0 space-y-1 text-xs">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-base-content/60">Filled</span>
                                <span class="font-bold tabular-nums text-emerald-700 dark:text-emerald-300">{{ sectionFillStats.filled }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-base-content/60">Empty</span>
                                <span class="font-bold tabular-nums text-rose-700 dark:text-rose-300">{{ sectionFillStats.empty }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2 pt-1 border-t border-base-200">
                                <span class="text-base-content/60">Total</span>
                                <span class="font-bold tabular-nums">{{ sectionFillStats.total }}</span>
                            </div>
                        </div>
                    </div>
                </article>

                <!-- 4. Students by Class ── donut -->
                <article v-if="classDonutSegments.length"
                         class="rounded-2xl bg-gradient-to-br from-violet-500/[0.06] via-base-100 to-base-100
                                border border-violet-500/15 shadow-sm hover:shadow-md transition-shadow p-4 sm:p-5">
                    <header class="flex items-center gap-2.5 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white
                                    flex items-center justify-center shadow-sm shadow-violet-500/25 shrink-0">
                            <RectangleStackIcon class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-violet-600/80 dark:text-violet-400/80">Distribution</p>
                            <h3 class="text-sm font-bold">Students by Class</h3>
                        </div>
                    </header>
                    <DonutChart :segments="classDonutSegments"
                                center-label="Total"
                                :center-value="totalStudentsFromRoster"
                                :size="130" :stroke="16" />
                </article>
            </div>

            <!-- ════════ SCHOOLS TABLE + FAST REPORTS ════════
                 Two panels side-by-side on lg. Left: sortable-looking
                 mini-table of every school. Right: one-click report
                 shortcuts + jump-to-common-actions. -->
            <div v-if="role === 'super-admin' || role === 'school-admin'"
                 class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4 items-start">

                <!-- LEFT (2 of 3 cols) — All Schools table -->
                <section v-if="schoolsTableRows.length"
                         class="lg:col-span-2 rounded-2xl bg-base-100 border border-base-300/70 shadow-sm
                                hover:shadow-md transition-shadow overflow-hidden">
                    <header class="px-5 py-3.5 border-b border-base-200 flex items-center justify-between gap-3 flex-wrap">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary to-teal-600 text-primary-content
                                        flex items-center justify-center shadow-sm shadow-primary/25 shrink-0">
                                <BuildingOfficeIcon class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-primary/70">Overview</p>
                                <h2 class="text-sm font-bold">All Schools · Pass Rate</h2>
                            </div>
                        </div>
                        <Link href="/analytics"
                              class="text-[11px] text-base-content/55 hover:text-primary font-semibold whitespace-nowrap
                                     inline-flex items-center gap-0.5 group">
                            Full analytics
                            <span class="transition-transform group-hover:translate-x-0.5">→</span>
                        </Link>
                    </header>

                    <!-- Desktop: proper table. Mobile: stacked cards (below) -->
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider font-bold text-base-content/55">
                                <tr>
                                    <th class="text-left px-5 py-2.5">#</th>
                                    <th class="text-left px-2 py-2.5">School</th>
                                    <th class="text-right px-2 py-2.5">Students</th>
                                    <th class="text-right px-2 py-2.5">Teachers</th>
                                    <th class="text-right px-2 py-2.5">T:S</th>
                                    <th class="text-right px-2 py-2.5">Pass Rate</th>
                                    <th class="text-left px-5 py-2.5 w-24">Trend</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-base-200">
                                <tr v-for="(s, i) in schoolsTableRows" :key="s.name"
                                    class="hover:bg-base-200/30 transition-colors">
                                    <td class="px-5 py-2.5 text-base-content/50 tabular-nums">{{ i + 1 }}</td>
                                    <td class="px-2 py-2.5 font-semibold truncate max-w-[180px]">{{ s.name }}</td>
                                    <td class="px-2 py-2.5 text-right tabular-nums font-medium">{{ s.students }}</td>
                                    <td class="px-2 py-2.5 text-right tabular-nums text-base-content/70">{{ s.teachers }}</td>
                                    <td class="px-2 py-2.5 text-right tabular-nums">
                                        <span class="inline-block px-1.5 py-0.5 rounded font-bold text-[10.5px]"
                                              :class="{
                                                'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300': s.ratioTone === 'emerald',
                                                'bg-amber-500/15 text-amber-700 dark:text-amber-300': s.ratioTone === 'amber',
                                                'bg-rose-500/15 text-rose-700 dark:text-rose-300': s.ratioTone === 'rose',
                                              }">{{ s.ratio }}</span>
                                    </td>
                                    <td class="px-2 py-2.5 text-right tabular-nums">
                                        <span class="inline-block px-2 py-0.5 rounded-md font-bold text-[11px]"
                                              :class="{
                                                'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300': s.tone === 'emerald',
                                                'bg-amber-500/15 text-amber-700 dark:text-amber-300': s.tone === 'amber',
                                                'bg-rose-500/15 text-rose-700 dark:text-rose-300': s.tone === 'rose',
                                              }">{{ s.passPct }}%</span>
                                    </td>
                                    <td class="px-5 py-2.5">
                                        <div class="h-1.5 rounded-full bg-base-200 overflow-hidden">
                                            <div class="h-full rounded-full"
                                                 :class="{
                                                    'bg-gradient-to-r from-emerald-500 to-teal-500': s.tone === 'emerald',
                                                    'bg-gradient-to-r from-amber-500 to-orange-500': s.tone === 'amber',
                                                    'bg-gradient-to-r from-rose-500 to-pink-500': s.tone === 'rose',
                                                 }"
                                                 :style="{ width: s.passPct + '%' }"></div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile: stacked cards (now show teachers + ratio too) -->
                    <div class="sm:hidden divide-y divide-base-200">
                        <div v-for="(s, i) in schoolsTableRows" :key="'m-' + s.name"
                             class="px-4 py-2.5">
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-base-content/50 tabular-nums shrink-0 w-4">{{ i + 1 }}</span>
                                <p class="text-sm font-semibold truncate flex-1">{{ s.name }}</p>
                                <span class="px-2 py-0.5 rounded-md text-[11px] font-bold tabular-nums shrink-0"
                                      :class="{
                                        'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300': s.tone === 'emerald',
                                        'bg-amber-500/15 text-amber-700 dark:text-amber-300': s.tone === 'amber',
                                        'bg-rose-500/15 text-rose-700 dark:text-rose-300': s.tone === 'rose',
                                      }">{{ s.passPct }}%</span>
                            </div>
                            <div class="flex items-center gap-3 mt-1 pl-7 text-[11px] text-base-content/60">
                                <span><b class="text-base-content">{{ s.students }}</b> students</span>
                                <span><b class="text-base-content">{{ s.teachers }}</b> teachers</span>
                                <span class="font-bold tabular-nums"
                                      :class="{
                                        'text-emerald-700 dark:text-emerald-300': s.ratioTone === 'emerald',
                                        'text-amber-700 dark:text-amber-300': s.ratioTone === 'amber',
                                        'text-rose-700 dark:text-rose-300': s.ratioTone === 'rose',
                                      }">{{ s.ratio }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- RIGHT (1 of 3 cols) — Fast Reports -->
                <section class="rounded-2xl bg-gradient-to-br from-primary/[0.06] via-base-100 to-base-100
                                border border-primary/15 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                    <header class="px-5 py-3.5 border-b border-base-200 flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary to-teal-600 text-primary-content
                                    flex items-center justify-center shadow-sm shadow-primary/25 shrink-0">
                            <BoltIcon class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-primary/70">Shortcuts</p>
                            <h2 class="text-sm font-bold">Fast Reports</h2>
                        </div>
                    </header>
                    <div class="p-3 grid grid-cols-2 gap-2">
                        <Link href="/analytics"
                              class="flex flex-col items-start gap-1 rounded-xl bg-base-100 border border-base-300 p-3
                                     hover:border-primary/40 hover:-translate-y-0.5 hover:shadow-sm transition-all group">
                            <ChartBarIcon class="w-4 h-4 text-primary" />
                            <span class="text-xs font-semibold group-hover:text-primary transition-colors">Analytics</span>
                            <span class="text-[10px] text-base-content/50">Full breakdown</span>
                        </Link>
                        <Link href="/results"
                              class="flex flex-col items-start gap-1 rounded-xl bg-base-100 border border-base-300 p-3
                                     hover:border-emerald-500/40 hover:-translate-y-0.5 hover:shadow-sm transition-all group">
                            <CheckBadgeIcon class="w-4 h-4 text-emerald-600" />
                            <span class="text-xs font-semibold group-hover:text-emerald-600 transition-colors">Results</span>
                            <span class="text-[10px] text-base-content/50">Per exam</span>
                        </Link>
                        <Link href="/exams"
                              class="flex flex-col items-start gap-1 rounded-xl bg-base-100 border border-base-300 p-3
                                     hover:border-amber-500/40 hover:-translate-y-0.5 hover:shadow-sm transition-all group">
                            <ClipboardDocumentListIcon class="w-4 h-4 text-amber-600" />
                            <span class="text-xs font-semibold group-hover:text-amber-600 transition-colors">Exams</span>
                            <span class="text-[10px] text-base-content/50">Manage</span>
                        </Link>
                        <Link href="/students"
                              class="flex flex-col items-start gap-1 rounded-xl bg-base-100 border border-base-300 p-3
                                     hover:border-sky-500/40 hover:-translate-y-0.5 hover:shadow-sm transition-all group">
                            <UserGroupIcon class="w-4 h-4 text-sky-600" />
                            <span class="text-xs font-semibold group-hover:text-sky-600 transition-colors">Students</span>
                            <span class="text-[10px] text-base-content/50">Roster</span>
                        </Link>
                        <Link href="/schools"
                              class="flex flex-col items-start gap-1 rounded-xl bg-base-100 border border-base-300 p-3
                                     hover:border-violet-500/40 hover:-translate-y-0.5 hover:shadow-sm transition-all group">
                            <BuildingOfficeIcon class="w-4 h-4 text-violet-600" />
                            <span class="text-xs font-semibold group-hover:text-violet-600 transition-colors">Schools</span>
                            <span class="text-[10px] text-base-content/50">All schools</span>
                        </Link>
                        <Link href="/certificates"
                              class="flex flex-col items-start gap-1 rounded-xl bg-base-100 border border-base-300 p-3
                                     hover:border-rose-500/40 hover:-translate-y-0.5 hover:shadow-sm transition-all group">
                            <DocumentTextIcon class="w-4 h-4 text-rose-600" />
                            <span class="text-xs font-semibold group-hover:text-rose-600 transition-colors">Certificates</span>
                            <span class="text-[10px] text-base-content/50">Generate</span>
                        </Link>
                    </div>
                </section>
            </div>

            <!-- ════════ SCHOOL-WISE TEACHER : STUDENT RATIO ════════
                 Horizontal bar per school, sorted worst → best (highest
                 ratio = most-strained staff). Colour-coded: green ≤25,
                 amber 26-40, red > 40 students per teacher.
                 Sits alone on its own row so it can breathe on wide
                 screens and stays readable on phones. -->
            <section v-if="role === 'super-admin' && schoolRatioBars.length"
                     class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                <header class="px-5 py-3.5 border-b border-base-200 flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white
                                    flex items-center justify-center shadow-sm shadow-violet-500/25 shrink-0">
                            <UserGroupIcon class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-violet-600/80 dark:text-violet-400/80">Staffing</p>
                            <h2 class="text-sm font-bold">Student : Teacher Ratio — Per School</h2>
                        </div>
                    </div>
                    <!-- Colour-key legend so the tones aren't a mystery. -->
                    <div class="flex items-center gap-3 text-[10px] font-semibold text-base-content/60 whitespace-nowrap">
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>≤ 25</span>
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span>26-40</span>
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-rose-500"></span>&gt; 40</span>
                    </div>
                </header>
                <div class="p-4 sm:p-5 space-y-2.5">
                    <div v-for="s in schoolRatioBars" :key="s.name"
                         class="grid grid-cols-[minmax(0,1fr)_auto] sm:grid-cols-[minmax(0,220px)_1fr_auto] gap-3 items-center">
                        <!-- School name + sub-meta -->
                        <div class="min-w-0">
                            <p class="text-xs sm:text-sm font-semibold truncate">{{ s.name }}</p>
                            <p class="text-[10.5px] text-base-content/55 mt-0.5">
                                <b class="text-base-content/75">{{ s.students }}</b> students
                                &middot;
                                <b class="text-base-content/75">{{ s.teachers }}</b> teachers
                            </p>
                        </div>
                        <!-- Bar — hidden on mobile, shown from sm up so the row stays clean -->
                        <div class="hidden sm:block h-6 rounded-lg bg-base-200 overflow-hidden">
                            <div class="h-full rounded-lg flex items-center justify-end px-2 transition-all"
                                 :class="{
                                    'bg-gradient-to-r from-emerald-500 to-teal-500': s.tone === 'emerald',
                                    'bg-gradient-to-r from-amber-500 to-orange-500': s.tone === 'amber',
                                    'bg-gradient-to-r from-rose-500 to-pink-500': s.tone === 'rose',
                                 }"
                                 :style="{ width: Math.max(8, Math.round((s.ratioNum / maxRatio) * 100)) + '%' }">
                                <span class="text-[10px] font-bold text-white/90 tabular-nums drop-shadow">
                                    {{ s.ratioNum }}
                                </span>
                            </div>
                        </div>
                        <!-- Ratio pill (always visible) -->
                        <span class="px-2 py-0.5 rounded-md font-bold text-[11px] tabular-nums shrink-0"
                              :class="{
                                'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300': s.tone === 'emerald',
                                'bg-amber-500/15 text-amber-700 dark:text-amber-300': s.tone === 'amber',
                                'bg-rose-500/15 text-rose-700 dark:text-rose-300': s.tone === 'rose',
                              }">1:{{ s.ratioNum }}</span>
                    </div>
                </div>
            </section>

            <!-- ════════ STUDENTS BY CLASS / SECTION — surfaced near top (actionable) ════════ -->
            <section v-if="(role === 'super-admin' || role === 'school-admin') && sectionRoster?.rows?.length"
                class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                <header class="px-5 py-3.5 border-b border-base-200 flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-sky-500 to-indigo-600 text-white
                                    flex items-center justify-center shadow-sm shadow-sky-500/25 shrink-0">
                            <UserGroupIcon class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-sm font-bold truncate leading-tight">Students by class &amp; section</h2>
                            <p class="text-[10.5px] text-base-content/55 leading-tight mt-0.5">
                                Showing <b class="text-base-content/85">{{ filteredRoster.length }}</b>
                                of {{ sectionRoster.rows.length }}
                            </p>
                        </div>
                    </div>
                    <span v-if="sectionRoster.empty_sections"
                          class="text-[10px] font-bold uppercase tracking-wider whitespace-nowrap px-2 py-1 rounded-md
                                 bg-rose-500/12 text-rose-700 dark:text-rose-300 ring-1 ring-rose-500/20">
                        {{ sectionRoster.empty_sections }} of {{ sectionRoster.total_sections }} empty
                    </span>
                </header>

                <!-- Search + empty-only filter — turns this widget from a wall
                     of names into an actionable triage list. -->
                <div class="px-4 py-3 border-b border-base-200 bg-base-200/25 flex items-center gap-2 flex-wrap">
                    <div class="flex-1 min-w-[180px] flex items-center gap-2 px-3 py-2 rounded-xl bg-base-100
                                border border-base-300 focus-within:border-primary/60 focus-within:ring-2 focus-within:ring-primary/15
                                transition-shadow">
                        <MagnifyingGlassIcon class="w-4 h-4 text-base-content/40 shrink-0" />
                        <input v-model="rosterSearch" type="text"
                            placeholder="Search class, section, teacher…"
                            class="bg-transparent outline-none flex-1 text-xs min-w-0" />
                    </div>
                    <button type="button" @click="rosterEmptyOnly = !rosterEmptyOnly"
                        class="text-[11px] font-semibold px-3 py-2 rounded-xl border transition-all flex items-center gap-1.5 whitespace-nowrap"
                        :class="rosterEmptyOnly
                            ? 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30 shadow-sm shadow-rose-500/10'
                            : 'bg-base-100 text-base-content/65 border-base-300 hover:border-rose-500/40 hover:text-rose-700 hover:-translate-y-0.5'">
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
            <div v-if="showCharts && totalResultsCharted > 0" class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-5">
                <!-- LEFT (8 cols on lg) — primary charts -->
                <div class="lg:col-span-8 space-y-4 sm:space-y-5">
                    <!-- Trend chart — hero card with tinted icon tile + subtle top-gradient -->
                    <article class="relative rounded-2xl bg-gradient-to-br from-primary/[0.04] via-base-100 to-base-100
                                    border border-base-300/70 shadow-sm hover:shadow-md transition-shadow p-5 sm:p-6 overflow-hidden">
                        <div class="pointer-events-none absolute -top-16 -right-16 w-40 h-40 rounded-full bg-primary/10 blur-3xl"></div>
                        <header class="relative flex items-start justify-between mb-4 gap-3 flex-wrap">
                            <div class="flex items-start gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-teal-600 text-primary-content
                                            flex items-center justify-center shadow-md shadow-primary/25 shrink-0">
                                    <ArrowTrendingUpIcon class="w-5 h-5" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-primary/70">Trend</p>
                                    <h3 class="text-sm sm:text-base font-bold mt-0.5 truncate">
                                        Pass Rate Over Last {{ charts.sessionTrend?.length || 0 }} Sessions
                                    </h3>
                                </div>
                            </div>
                        </header>
                        <TrendLineChart :points="charts.sessionTrend || []" color="primary" :height="180" unit="%" />
                    </article>

                    <!-- Comparison bars -->
                    <article v-if="compareBars.length"
                             class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm hover:shadow-md transition-shadow p-5 sm:p-6">
                        <header class="flex items-start justify-between mb-4 gap-3 flex-wrap">
                            <div class="flex items-start gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-500 to-indigo-600 text-white
                                            flex items-center justify-center shadow-md shadow-sky-500/25 shrink-0">
                                    <ChartBarIcon class="w-5 h-5" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-sky-600/80 dark:text-sky-400/80">Compare</p>
                                    <h3 class="text-sm sm:text-base font-bold mt-0.5 truncate">{{ compareTitle }}</h3>
                                </div>
                            </div>
                            <span class="text-[11px] font-semibold text-base-content/55 whitespace-nowrap px-2 py-1
                                         rounded-lg bg-base-200/60">Pass rate</span>
                        </header>
                        <BarChartHorizontal :rows="compareBars" :max="100" unit="%" />
                    </article>
                </div>

                <!-- RIGHT (4 cols on lg) — feed + calendar + breakdown -->
                <div class="lg:col-span-4 space-y-4 sm:space-y-5">
                    <!-- Pass/Fail donut — centrepiece card with donut + label -->
                    <article class="rounded-2xl bg-gradient-to-br from-emerald-500/[0.06] via-base-100 to-base-100
                                    border border-emerald-500/15 shadow-sm p-5 sm:p-6">
                        <header class="flex items-center gap-2.5 mb-3">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 text-white
                                        flex items-center justify-center shadow-sm shadow-emerald-500/25 shrink-0">
                                <CheckBadgeIcon class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-emerald-600/80 dark:text-emerald-400/80">Breakdown</p>
                                <h3 class="text-sm font-bold">Pass / Retry</h3>
                            </div>
                        </header>
                        <DonutChart :segments="charts.passFail" center-label="Pass" :center-value="overallPassRate + '%'"
                            :size="140" :stroke="20" />
                    </article>

                    <!-- Grade distribution -->
                    <article v-if="gradeRows.length"
                             class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm p-5 sm:p-6">
                        <header class="flex items-center gap-2.5 mb-3">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-orange-600 text-white
                                        flex items-center justify-center shadow-sm shadow-amber-500/25 shrink-0">
                                <TrophyIcon class="w-4 h-4" />
                            </div>
                            <h3 class="text-sm font-bold">Grade Distribution</h3>
                        </header>
                        <BarChartHorizontal :rows="gradeRows" />
                    </article>

                    <!-- Calendar -->
                    <article v-if="calendar?.month_label"
                             class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm p-5 sm:p-6">
                        <CalendarWidget
                            :month="calendar.month"
                            :month-label="calendar.month_label"
                            :today="calendar.today"
                            :markers="calendar.markers" />
                    </article>

                    <!-- Activity feed -->
                    <article class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm overflow-hidden">
                        <header class="px-5 py-3.5 border-b border-base-200 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-rose-500 to-pink-600 text-white
                                            flex items-center justify-center shadow-sm shadow-rose-500/25 shrink-0">
                                    <FireIcon class="w-4 h-4" />
                                </div>
                                <h3 class="text-sm font-bold">Activity</h3>
                            </div>
                            <Link href="/notifications"
                                  class="text-[11px] text-base-content/55 hover:text-primary font-semibold whitespace-nowrap
                                         inline-flex items-center gap-0.5 group">
                                View all
                                <span class="transition-transform group-hover:translate-x-0.5">→</span>
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
            <section v-if="recentExams?.length"
                     class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                <header class="px-5 py-3.5 border-b border-base-200 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-orange-600 text-white
                                    flex items-center justify-center shadow-sm shadow-amber-500/25 shrink-0">
                            <ClipboardDocumentListIcon class="w-4 h-4" />
                        </div>
                        <h2 class="text-sm font-bold">Recent Exams</h2>
                    </div>
                    <Link href="/exams"
                          class="text-[11px] text-base-content/55 hover:text-primary font-semibold whitespace-nowrap
                                 inline-flex items-center gap-0.5 group">
                        View all
                        <span class="transition-transform group-hover:translate-x-0.5">→</span>
                    </Link>
                </header>
                <div class="divide-y divide-base-200">
                    <Link v-for="exam in recentExams" :key="exam.id" :href="`/exams/${exam.id}`"
                        class="flex items-center gap-3 px-5 py-3 hover:bg-base-200/40 active:bg-base-200/60 transition-colors group">
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm truncate group-hover:text-primary transition-colors">{{ exam.name }}</p>
                            <p class="text-[11px] text-base-content/55 truncate mt-0.5">
                                {{ exam.type }}
                                <span v-if="exam.start_date"> · {{ exam.start_date }}</span>
                            </p>
                        </div>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider whitespace-nowrap"
                            :class="statusBadge(exam.status)">
                            {{ statusLabel(exam.status) }}
                        </span>
                        <ChevronRightIcon class="w-3.5 h-3.5 text-base-content/30 shrink-0 group-hover:text-primary
                                                  group-hover:translate-x-0.5 transition-all" />
                    </Link>
                </div>
            </section>

            <!-- ════════ RECENTLY ADDED STUDENTS — who added them ════════ -->
            <section v-if="(role === 'super-admin' || role === 'school-admin') && recentStudents?.length"
                class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                <header class="px-5 py-3.5 border-b border-base-200 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 text-white
                                    flex items-center justify-center shadow-sm shadow-emerald-500/25 shrink-0">
                            <UserGroupIcon class="w-4 h-4" />
                        </div>
                        <h2 class="text-sm font-bold">Recently added students</h2>
                    </div>
                    <Link href="/activity-log?subject_type=App\Models\Student"
                        class="text-[11px] text-base-content/55 hover:text-primary font-semibold whitespace-nowrap
                               inline-flex items-center gap-0.5 group">
                        Full history
                        <span class="transition-transform group-hover:translate-x-0.5">→</span>
                    </Link>
                </header>
                <div class="divide-y divide-base-200">
                    <Link v-for="s in recentStudents" :key="s.id" :href="`/students/${s.id}`"
                        class="flex items-center gap-3 px-5 py-3 hover:bg-base-200/40 active:bg-base-200/60 transition-colors group">
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm truncate group-hover:text-primary transition-colors">
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
