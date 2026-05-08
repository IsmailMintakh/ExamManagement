<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    UserGroupIcon, ClipboardDocumentCheckIcon, ChartBarIcon, TrophyIcon,
    AcademicCapIcon, CheckCircleIcon, XCircleIcon, ClockIcon,
    PencilSquareIcon, PlusIcon, UsersIcon, BookOpenIcon,
    ExclamationTriangleIcon, EnvelopeIcon, ArrowRightIcon,
    BellAlertIcon, MagnifyingGlassIcon, ChevronRightIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    sections: { type: Array, default: () => [] },
    activeSection: { type: Object, default: null },
    students: { type: Array, default: () => [] },
    marksStatus: { type: Array, default: () => [] },
    latestResults: { type: Array, default: () => [] },
    sectionTeam: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    currentSession: { type: Object, default: null },
    mySubjectStatus: { type: Array, default: () => [] },
    myRecentResults: { type: Array, default: () => [] },
})

function switchSection(sectionId) {
    router.get(route('class-teacher.index'), { section: sectionId }, { preserveState: false })
}

// ─── "Needs attention" digest ───
// Surface only the things the teacher actually has to act on. If nothing
// needs attention we hide the panel entirely — empty alerts add noise.
const attentionItems = computed(() => {
    const items = []

    // Subjects with no teacher assigned across any exam.
    const orphanSubjects = new Set()
    for (const exam of props.marksStatus) {
        for (const s of exam.subjects) {
            if (!s.assigned_teacher) orphanSubjects.add(`${s.subject_code}-${s.subject_name}`)
        }
    }
    if (orphanSubjects.size > 0) {
        items.push({
            id: 'orphan-teachers',
            level: 'error',
            icon: ExclamationTriangleIcon,
            title: `${orphanSubjects.size} subject(s) without a teacher`,
            body: 'Marks can\'t be entered until a subject teacher is assigned. Ask your Principal to assign one.',
        })
    }

    // Marks entry pending across the section.
    if ((props.stats.pending_marks_subjects ?? 0) > 0) {
        items.push({
            id: 'pending-marks',
            level: 'warn',
            icon: ClockIcon,
            title: `${props.stats.pending_marks_subjects} subject(s) awaiting marks`,
            body: 'Some subject teachers haven\'t finished entering marks yet — see the Marks Status section below.',
        })
    }

    // My own subjects: any I'm behind on?
    const myPending = props.mySubjectStatus.filter(s =>
        s.latest_exam && s.students_entered < s.students_total
    )
    if (myPending.length > 0) {
        items.push({
            id: 'my-marks',
            level: 'info',
            icon: PencilSquareIcon,
            title: `Enter marks for ${myPending.length} subject(s) you teach`,
            body: 'You haven\'t finished entering marks for some of your assignments. Tap below to continue.',
            action: { label: 'Enter marks', href: route('marks.index') },
        })
    }

    return items
})

const attentionLevelClasses = {
    error: {
        wrap: 'bg-rose-500/10 border-rose-500/30',
        icon: 'bg-rose-500/15 text-rose-600 dark:text-rose-400',
        title: 'text-rose-700 dark:text-rose-300',
    },
    warn: {
        wrap: 'bg-amber-500/10 border-amber-500/30',
        icon: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
        title: 'text-amber-700 dark:text-amber-300',
    },
    info: {
        wrap: 'bg-sky-500/10 border-sky-500/30',
        icon: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
        title: 'text-sky-700 dark:text-sky-300',
    },
}

// Marks summary across exams + subjects (used by stats card).
const marksSummary = computed(() => {
    const all = props.marksStatus.flatMap(e => e.subjects)
    return {
        total: all.length,
        submitted: all.filter(s => s.status === 'submitted').length,
        inProgress: all.filter(s => s.status === 'draft').length,
        pending: all.filter(s => s.status === 'pending' || (!s.status)).length,
    }
})

// Student search (client-side filter).
const studentSearch = ref('')
const filteredStudents = computed(() => {
    if (!studentSearch.value.trim()) return props.students
    const q = studentSearch.value.trim().toLowerCase()
    return props.students.filter(s =>
        (s.name || '').toLowerCase().includes(q) ||
        (s.admission_no || '').toLowerCase().includes(q) ||
        (s.father_name || '').toLowerCase().includes(q) ||
        (s.roll_no || '').toString().includes(q)
    )
})

// ─── Visual helpers ───
function statusBadge(status) {
    return {
        submitted: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-500/30',
        draft: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 ring-amber-500/30',
        pending: 'bg-base-content/10 text-base-content/65 ring-base-content/15',
    }[status] || 'bg-base-content/10 text-base-content/65 ring-base-content/15'
}
function statusLabel(status) {
    return { submitted: 'Submitted', draft: 'In Progress', pending: 'Not Started' }[status] || 'Unknown'
}
function statusIcon(status) {
    return { submitted: CheckCircleIcon, draft: ClockIcon, pending: XCircleIcon }[status] || XCircleIcon
}

function initials(name) {
    if (!name) return '?'
    return name.split(' ').filter(Boolean).map(n => n[0]).slice(0, 2).join('').toUpperCase()
}
function avatarColor(name) {
    const colors = [
        'from-emerald-500 to-teal-600',
        'from-sky-500 to-blue-600',
        'from-amber-500 to-orange-600',
        'from-rose-500 to-pink-600',
        'from-violet-500 to-purple-600',
        'from-teal-500 to-cyan-600',
        'from-indigo-500 to-blue-700',
    ]
    let hash = 0
    for (let i = 0; i < (name || '').length; i++) hash = (hash * 31 + name.charCodeAt(i)) | 0
    return colors[Math.abs(hash) % colors.length]
}
function gradeColor(g) {
    if (!g) return 'bg-base-content/10 text-base-content/65'
    if (g.startsWith('A')) return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
    if (g.startsWith('B')) return 'bg-sky-500/15 text-sky-700 dark:text-sky-300'
    if (g.startsWith('C')) return 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
    if (g === 'D' || g === 'E') return 'bg-orange-500/15 text-orange-700 dark:text-orange-300'
    return 'bg-rose-500/15 text-rose-700 dark:text-rose-300'
}
function entryPct(entered, total) {
    if (!total) return 0
    return Math.min(100, Math.round((entered / total) * 100))
}
function progressBarClass(entered, total) {
    if (total > 0 && entered === total) return 'bg-gradient-to-r from-emerald-500 to-teal-500'
    if (entered > 0) return 'bg-gradient-to-r from-amber-500 to-orange-500'
    return 'bg-rose-400'
}

// Rendering caps so the page doesn't drown the user — full lists are
// reachable via dedicated pages.
const STUDENTS_PREVIEW = 8
const RESULTS_PREVIEW = 6
const studentsPreview = computed(() => filteredStudents.value.slice(0, STUDENTS_PREVIEW))
const resultsPreview = computed(() => props.latestResults.slice(0, RESULTS_PREVIEW))
</script>

<template>
    <Head title="My Class" />
    <AppLayout :breadcrumbs="[{ label: 'My Class' }]">
        <div class="space-y-5 max-w-[1500px] mx-auto">

            <!-- ════════════ HERO ════════════ -->
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-800 text-white shadow-xl">
                <div class="absolute -top-24 -right-24 w-72 h-72 bg-amber-400/20 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-24 -left-24 w-72 h-72 bg-emerald-400/20 rounded-full blur-3xl"></div>
                <div class="absolute inset-0 opacity-[0.04]" style="background-image: radial-gradient(white 1px, transparent 1px); background-size: 24px 24px;"></div>

                <div class="relative px-6 sm:px-8 py-7 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                    <div class="flex items-start gap-4">
                        <div class="hidden sm:flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/20">
                            <AcademicCapIcon class="w-7 h-7 text-amber-300" />
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-[0.25em] font-bold text-amber-300/90 mb-1.5">
                                Class Teacher Dashboard
                            </p>
                            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight leading-tight">
                                {{ activeSection?.class_name }}
                                <span class="text-amber-300/85">— Section {{ activeSection?.name }}</span>
                            </h1>
                            <p class="text-sm text-emerald-50/85 mt-2">
                                {{ activeSection?.school_name }}
                                <span v-if="currentSession" class="opacity-70">· Session {{ currentSession.name }}</span>
                            </p>
                        </div>
                    </div>

                    <div v-if="sections.length > 1" class="flex items-center gap-2.5 sm:flex-shrink-0">
                        <span class="text-[11px] uppercase tracking-wider font-bold text-emerald-100/80 hidden sm:inline">Switch</span>
                        <select
                            @change="switchSection($event.target.value)"
                            :value="activeSection?.id"
                            class="bg-white/10 backdrop-blur border border-white/25 text-white text-sm font-semibold rounded-xl px-3.5 py-2.5 min-w-[220px] focus:outline-none focus:ring-2 focus:ring-amber-400/60 cursor-pointer">
                            <option v-for="s in sections" :key="s.id" :value="s.id" class="text-slate-800">
                                {{ s.class_name }} — Section {{ s.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ════════════ COMPACT KPI ROW ════════════ -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center shadow-md shadow-emerald-500/15 shrink-0">
                        <UserGroupIcon class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Students</p>
                        <p class="text-2xl font-extrabold tabular-nums leading-tight">{{ stats.student_count ?? 0 }}</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white flex items-center justify-center shadow-md shadow-amber-500/15 shrink-0">
                        <ClipboardDocumentCheckIcon class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Pending Marks</p>
                        <p class="text-2xl font-extrabold tabular-nums leading-tight text-amber-600 dark:text-amber-400">{{ stats.pending_marks_subjects ?? 0 }}</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 text-white flex items-center justify-center shadow-md shadow-sky-500/15 shrink-0">
                        <ChartBarIcon class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Pass Rate</p>
                        <p class="text-2xl font-extrabold tabular-nums leading-tight text-sky-600 dark:text-sky-400">
                            {{ stats.avg_pass_rate !== null ? `${stats.avg_pass_rate}%` : '—' }}
                        </p>
                    </div>
                </div>
                <div class="rounded-2xl border border-amber-500/40 bg-amber-500/5 px-4 py-3.5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-yellow-600 text-white flex items-center justify-center shadow-md shadow-amber-500/20 shrink-0">
                        <TrophyIcon class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wider font-bold text-amber-600 dark:text-amber-400">Top Performer</p>
                        <p class="text-sm font-bold leading-tight truncate" :title="stats.top_performer?.name">
                            {{ stats.top_performer?.name || '—' }}
                            <span v-if="stats.top_performer" class="font-bold text-emerald-600 dark:text-emerald-400">· {{ stats.top_performer.percentage }}%</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- ════════════ NEEDS ATTENTION ════════════
                 Only renders when there's actually something to flag. Each
                 row is colour-coded by severity (rose/amber/sky). -->
            <div v-if="attentionItems.length" class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <div class="px-5 sm:px-6 py-3.5 border-b border-base-300 flex items-center gap-2">
                    <BellAlertIcon class="w-4 h-4 text-amber-500" />
                    <h2 class="text-sm font-bold uppercase tracking-wider">Needs your attention</h2>
                    <span class="text-xs text-base-content/55 ml-auto">{{ attentionItems.length }} item{{ attentionItems.length === 1 ? '' : 's' }}</span>
                </div>
                <div class="divide-y divide-base-300">
                    <div v-for="item in attentionItems" :key="item.id"
                        class="px-5 sm:px-6 py-4 flex items-start gap-3"
                        :class="attentionLevelClasses[item.level].wrap">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                            :class="attentionLevelClasses[item.level].icon">
                            <component :is="item.icon" class="w-5 h-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold" :class="attentionLevelClasses[item.level].title">{{ item.title }}</p>
                            <p class="text-xs text-base-content/65 mt-0.5">{{ item.body }}</p>
                        </div>
                        <Link v-if="item.action" :href="item.action.href" class="btn btn-sm btn-primary rounded-xl shrink-0 gap-1">
                            {{ item.action.label }} <ArrowRightIcon class="w-3.5 h-3.5" />
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ════════════ TWO-COLUMN GRID (results + my subjects) ════════════
                 The two information panels users glance at most often. On
                 mobile they stack; on desktop side-by-side. -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <!-- ─── Recent results ─── -->
                <section class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                    <header class="px-5 sm:px-6 py-3.5 border-b border-base-300 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                                <ChartBarIcon class="w-4 h-4" />
                            </div>
                            <h2 class="text-sm font-bold">Recent class results</h2>
                            <span class="text-xs text-base-content/45">· {{ latestResults.length }}</span>
                        </div>
                    </header>
                    <div v-if="!resultsPreview.length" class="p-8 text-center">
                        <ChartBarIcon class="w-9 h-9 mx-auto text-base-content/20 mb-2" />
                        <p class="text-xs text-base-content/55">No results finalized yet.</p>
                    </div>
                    <ul v-else class="divide-y divide-base-300">
                        <li v-for="r in resultsPreview" :key="r.id" class="px-5 sm:px-6 py-3 flex items-center gap-3 hover:bg-base-200/40 transition-colors">
                            <span v-if="r.position && r.position <= 3"
                                class="inline-flex w-7 h-7 rounded-full items-center justify-center text-xs font-bold shadow-sm shrink-0"
                                :class="r.position === 1 ? 'bg-gradient-to-br from-amber-400 to-yellow-500 text-white' : r.position === 2 ? 'bg-gradient-to-br from-slate-300 to-slate-400 text-white' : 'bg-gradient-to-br from-amber-700 to-orange-700 text-white'">
                                {{ r.position }}
                            </span>
                            <span v-else class="inline-flex w-7 h-7 rounded-full items-center justify-center text-[10px] font-mono font-bold bg-base-200 text-base-content/55 shrink-0">
                                {{ r.position || '—' }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold truncate">{{ r.student_name }}</p>
                                <p class="text-[11px] text-base-content/55 font-mono">Roll {{ r.roll_no }} · {{ r.exam_name }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold tabular-nums">{{ Number(r.percentage).toFixed(1) }}%</p>
                                <span class="inline-flex items-center px-1.5 py-0 rounded text-[10px] font-bold" :class="gradeColor(r.grade)">{{ r.grade || '—' }}</span>
                            </div>
                            <CheckCircleIcon v-if="r.is_passed" class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                            <XCircleIcon v-else class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0" />
                        </li>
                    </ul>
                    <footer v-if="latestResults.length > RESULTS_PREVIEW" class="px-5 sm:px-6 py-2.5 border-t border-base-300 bg-base-200/30">
                        <p class="text-xs text-base-content/55 text-center">
                            Showing {{ RESULTS_PREVIEW }} of {{ latestResults.length }} — full list below
                        </p>
                    </footer>
                </section>

                <!-- ─── My teaching ─── -->
                <section class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                    <header class="px-5 sm:px-6 py-3.5 border-b border-base-300 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-violet-500/15 text-violet-600 dark:text-violet-400 flex items-center justify-center">
                            <BookOpenIcon class="w-4 h-4" />
                        </div>
                        <h2 class="text-sm font-bold">My teaching</h2>
                        <span class="text-xs text-base-content/45">· {{ mySubjectStatus.length }} subject{{ mySubjectStatus.length === 1 ? '' : 's' }}</span>
                        <Link :href="route('marks.index')" class="ml-auto text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-1">
                            Enter marks <ArrowRightIcon class="w-3 h-3" />
                        </Link>
                    </header>
                    <div v-if="!mySubjectStatus.length" class="p-8 text-center">
                        <BookOpenIcon class="w-9 h-9 mx-auto text-base-content/20 mb-2" />
                        <p class="text-xs text-base-content/55">You're not assigned to teach any subjects yet.</p>
                    </div>
                    <ul v-else class="divide-y divide-base-300">
                        <li v-for="a in mySubjectStatus" :key="`${a.subject_id}-${a.section_id}`" class="px-5 sm:px-6 py-3.5">
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold truncate">{{ a.subject_name }}</p>
                                    <p class="text-[11px] text-base-content/55">
                                        <span class="font-mono">{{ a.subject_code }}</span> ·
                                        {{ a.class_name }} — Section {{ a.section_name }}
                                    </p>
                                </div>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold ring-1 whitespace-nowrap"
                                    :class="statusBadge(a.submission_status)">
                                    <component :is="statusIcon(a.submission_status)" class="w-3 h-3" />
                                    {{ statusLabel(a.submission_status) }}
                                </span>
                            </div>
                            <div v-if="a.latest_exam">
                                <div class="flex items-center justify-between text-[11px] text-base-content/65 mb-1">
                                    <span class="truncate mr-2">{{ a.latest_exam.name }}</span>
                                    <span class="font-mono font-bold tabular-nums shrink-0">{{ a.students_entered }}/{{ a.students_total }}</span>
                                </div>
                                <div class="h-1.5 bg-base-200 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all" :class="progressBarClass(a.students_entered, a.students_total)"
                                        :style="`width: ${entryPct(a.students_entered, a.students_total)}%`"></div>
                                </div>
                            </div>
                            <p v-else class="text-[11px] text-base-content/45 italic">No exam yet.</p>
                        </li>
                    </ul>
                </section>
            </div>

            <!-- ════════════ STUDENTS ════════════ -->
            <section class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <header class="px-5 sm:px-6 py-3.5 border-b border-base-300 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <UserGroupIcon class="w-4 h-4" />
                    </div>
                    <h2 class="text-sm font-bold">Students</h2>
                    <span class="text-xs text-base-content/45">· {{ filteredStudents.length }} of {{ students.length }}</span>
                    <div class="flex-1"></div>
                    <div class="relative hidden sm:block">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-base-content/40" />
                        <input v-model="studentSearch" type="text" placeholder="Search by name, roll, admission #..."
                            class="w-64 rounded-lg border border-base-300 bg-base-100 pl-9 pr-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" />
                    </div>
                    <Link :href="route('students.create')" class="btn btn-primary btn-sm rounded-xl gap-1">
                        <PlusIcon class="w-3.5 h-3.5" /> Add Student
                    </Link>
                </header>
                <!-- Mobile search bar -->
                <div class="px-4 py-2 sm:hidden border-b border-base-300">
                    <div class="relative">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-base-content/40" />
                        <input v-model="studentSearch" type="text" placeholder="Search students..."
                            class="w-full rounded-lg border border-base-300 bg-base-100 pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" />
                    </div>
                </div>

                <div v-if="!students.length" class="p-12 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-base-200 mx-auto mb-3 flex items-center justify-center">
                        <UserGroupIcon class="w-7 h-7 text-base-content/30" />
                    </div>
                    <p class="text-sm font-medium text-base-content/70">No students in this section yet</p>
                </div>
                <div v-else-if="!filteredStudents.length" class="p-10 text-center">
                    <p class="text-xs text-base-content/55">No students match "{{ studentSearch }}".</p>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider text-base-content/55">
                            <tr>
                                <th class="text-left px-4 sm:px-6 py-2.5 font-bold w-14">Roll</th>
                                <th class="text-left px-3 py-2.5 font-bold">Name</th>
                                <th class="text-left px-3 py-2.5 font-bold hidden sm:table-cell">Admission</th>
                                <th class="text-left px-3 py-2.5 font-bold hidden md:table-cell">Father</th>
                                <th class="text-left px-3 py-2.5 font-bold hidden lg:table-cell">Phone</th>
                                <th class="text-right px-4 sm:px-6 py-2.5 font-bold w-24"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-300">
                            <tr v-for="s in studentsPreview" :key="s.id" class="hover:bg-base-200/30 transition-colors">
                                <td class="px-4 sm:px-6 py-2.5 font-mono text-xs font-bold text-base-content/55">{{ s.roll_no || '—' }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-full text-white font-bold text-[10px] flex items-center justify-center shrink-0 bg-gradient-to-br shadow-sm" :class="avatarColor(s.name)">
                                            {{ initials(s.name) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-semibold truncate">{{ s.name }}</div>
                                            <div class="text-[10px] text-base-content/50 sm:hidden font-mono truncate">{{ s.admission_no }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-xs font-mono text-base-content/65 hidden sm:table-cell">{{ s.admission_no }}</td>
                                <td class="px-3 py-2.5 text-sm hidden md:table-cell">{{ s.father_name || '—' }}</td>
                                <td class="px-3 py-2.5 text-xs font-mono text-base-content/65 hidden lg:table-cell">{{ s.guardian_phone || '—' }}</td>
                                <td class="px-4 sm:px-6 py-2.5 text-right whitespace-nowrap">
                                    <Link :href="route('students.show', s.id)" class="btn btn-ghost btn-xs rounded-lg">View</Link>
                                    <Link :href="route('students.edit', s.id)" class="btn btn-ghost btn-xs rounded-lg btn-square ml-0.5">
                                        <PencilSquareIcon class="w-3.5 h-3.5" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <footer v-if="filteredStudents.length > STUDENTS_PREVIEW" class="px-5 sm:px-6 py-2.5 border-t border-base-300 bg-base-200/30 flex items-center justify-between">
                    <p class="text-xs text-base-content/55">
                        Showing {{ STUDENTS_PREVIEW }} of {{ filteredStudents.length }}
                    </p>
                    <Link :href="route('students.index', { section_id: activeSection?.id })" class="text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-1">
                        View all <ChevronRightIcon class="w-3 h-3" />
                    </Link>
                </footer>
            </section>

            <!-- ════════════ MARKS STATUS BY EXAM ════════════
                 Each exam gets a card; class teacher can see at-a-glance
                 which subjects are submitted vs. pending across the section. -->
            <section v-if="marksStatus.length" class="space-y-3">
                <header class="flex items-center gap-2 px-1">
                    <div class="w-8 h-8 rounded-lg bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                        <ClipboardDocumentCheckIcon class="w-4 h-4" />
                    </div>
                    <h2 class="text-sm font-bold">Marks status by exam</h2>
                    <span class="text-xs text-base-content/45">· {{ marksSummary.submitted }}/{{ marksSummary.total }} submitted</span>
                </header>

                <div v-for="exam in marksStatus" :key="exam.exam_id"
                    class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                    <div class="px-5 sm:px-6 py-3 border-b border-base-300 flex items-center justify-between">
                        <div class="min-w-0">
                            <h3 class="font-bold text-sm truncate">{{ exam.exam_name }}</h3>
                            <p class="text-[11px] text-base-content/55">{{ exam.exam_type }} · {{ exam.start_date }}</p>
                        </div>
                        <span class="badge badge-sm capitalize">{{ exam.status?.replace('_', ' ') }}</span>
                    </div>
                    <ul class="divide-y divide-base-300">
                        <li v-for="subj in exam.subjects" :key="subj.subject_id" class="px-5 sm:px-6 py-3 flex items-center gap-3 hover:bg-base-200/30 transition-colors">
                            <!-- Subject -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-baseline gap-2">
                                    <p class="text-sm font-semibold truncate">{{ subj.subject_name }}</p>
                                    <p class="text-[11px] text-base-content/45 font-mono shrink-0">{{ subj.subject_code }}</p>
                                </div>
                                <p v-if="subj.assigned_teacher" class="text-[11px] text-base-content/65 mt-0.5">
                                    👤 {{ subj.assigned_teacher }}
                                </p>
                                <p v-else class="text-[11px] text-rose-600 dark:text-rose-400 mt-0.5 inline-flex items-center gap-1">
                                    <ExclamationTriangleIcon class="w-3 h-3" /> No teacher assigned
                                </p>
                            </div>
                            <!-- Progress -->
                            <div class="hidden sm:block w-32 shrink-0">
                                <div class="flex items-center justify-between text-[10px] text-base-content/55 mb-0.5">
                                    <span>Entered</span>
                                    <span class="font-mono font-bold tabular-nums">{{ subj.students_entered }}/{{ subj.students_total }}</span>
                                </div>
                                <div class="h-1.5 bg-base-200 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all" :class="progressBarClass(subj.students_entered, subj.students_total)"
                                        :style="`width: ${entryPct(subj.students_entered, subj.students_total)}%`"></div>
                                </div>
                            </div>
                            <!-- Status -->
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold ring-1 whitespace-nowrap shrink-0"
                                :class="statusBadge(subj.status)">
                                <component :is="statusIcon(subj.status)" class="w-3 h-3" />
                                {{ statusLabel(subj.status) }}
                            </span>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- ════════════ SECTION TEAM ════════════ -->
            <section v-if="sectionTeam.length" class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <header class="px-5 sm:px-6 py-3.5 border-b border-base-300 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                        <UsersIcon class="w-4 h-4" />
                    </div>
                    <h2 class="text-sm font-bold">Section team</h2>
                    <span class="text-xs text-base-content/45">· {{ sectionTeam.length }} teacher{{ sectionTeam.length === 1 ? '' : 's' }}</span>
                </header>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 p-4 sm:p-5">
                    <div v-for="t in sectionTeam" :key="t.teacher_email + t.subject_code"
                        class="rounded-xl border border-base-300 bg-base-200/30 hover:bg-base-200/60 p-3.5 flex items-start gap-2.5 transition-colors">
                        <div class="w-10 h-10 rounded-xl text-white font-bold text-sm flex items-center justify-center flex-shrink-0 bg-gradient-to-br shadow-md" :class="avatarColor(t.teacher_name)">
                            {{ initials(t.teacher_name) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-xs truncate">{{ t.teacher_name }}</h4>
                            <p class="text-[11px] text-emerald-600 dark:text-emerald-400 font-bold truncate">{{ t.subject_name }}</p>
                            <p class="text-[10px] text-base-content/50 mt-0.5 flex items-center gap-1 truncate">
                                <EnvelopeIcon class="w-2.5 h-2.5 flex-shrink-0" /> {{ t.teacher_email }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </AppLayout>
</template>
