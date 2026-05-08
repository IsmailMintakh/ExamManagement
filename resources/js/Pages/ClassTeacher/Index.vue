<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    UserGroupIcon, ClipboardDocumentCheckIcon, ChartBarIcon, TrophyIcon,
    AcademicCapIcon, CheckCircleIcon, XCircleIcon, ClockIcon,
    PencilSquareIcon, PlusIcon, UsersIcon, BookOpenIcon,
    ExclamationTriangleIcon, EnvelopeIcon, ArrowRightIcon,
    InformationCircleIcon, SparklesIcon,
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

const tab = ref('overview')
const tabs = computed(() => {
    const base = [
        { key: 'overview', label: 'Overview', icon: AcademicCapIcon },
        { key: 'students', label: 'Students', icon: UserGroupIcon, count: props.students.length },
        { key: 'marks', label: 'Marks Status', icon: ClipboardDocumentCheckIcon },
        { key: 'results', label: 'Class Results', icon: ChartBarIcon, count: props.latestResults.length },
        { key: 'team', label: 'Section Team', icon: UsersIcon, count: props.sectionTeam.length },
    ]
    // "My Subjects" tab only appears if the user actually teaches anything
    // — class teachers without subject-teaching assignments don't need it.
    if (props.mySubjectStatus.length > 0) {
        base.splice(4, 0, {
            key: 'my-subjects', label: 'My Subjects', icon: BookOpenIcon, count: props.mySubjectStatus.length,
        })
    }
    return base
})

function switchSection(sectionId) {
    router.get(route('class-teacher.index'), { section: sectionId }, { preserveState: false })
}

function statusBadge(status) {
    // Opacity tints work in both light and dark themes — base solid colors
    // would blow out one mode or the other.
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

const marksSummary = computed(() => {
    const all = props.marksStatus.flatMap(e => e.subjects)
    return {
        total: all.length,
        submitted: all.filter(s => s.status === 'submitted').length,
        inProgress: all.filter(s => s.status === 'draft').length,
        pending: all.filter(s => s.status === 'pending' || (!s.status)).length,
    }
})

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

// Progress percentage helper for the linear marks-entry bars on the
// "My Subjects" cards.
function entryPct(entered, total) {
    if (!total) return 0
    return Math.min(100, Math.round((entered / total) * 100))
}
</script>

<template>
    <Head title="My Class" />
    <AppLayout :breadcrumbs="[{ label: 'My Class' }]">
        <div class="space-y-6 max-w-[1500px] mx-auto">

            <!-- ════════════ HERO ════════════
                 Premium gradient header card. Class info big and clear,
                 section switcher tucked top-right where users expect it. -->
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-800 text-white shadow-xl">
                <!-- Decorative blobs -->
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

            <!-- ════════════ KPI STRIP ════════════
                 Card backgrounds: theme-aware bg-base-100 (white in light, dark
                 panel in dark). Color accents come ONLY from the icon tile +
                 numeric text — never from card background gradients which
                 looked terrible in dark mode. -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="group rounded-2xl border border-base-300 bg-base-100 p-5 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <div class="flex items-start justify-between mb-3">
                        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center shadow-md shadow-emerald-500/20">
                            <UserGroupIcon class="w-5 h-5" />
                        </div>
                        <span class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Students</span>
                    </div>
                    <div class="text-3xl font-extrabold tabular-nums">{{ stats.student_count ?? 0 }}</div>
                    <p class="text-xs text-base-content/60 mt-1">Active in this section</p>
                </div>

                <div class="group rounded-2xl border border-base-300 bg-base-100 p-5 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <div class="flex items-start justify-between mb-3">
                        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white flex items-center justify-center shadow-md shadow-amber-500/20">
                            <ClipboardDocumentCheckIcon class="w-5 h-5" />
                        </div>
                        <span class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Pending</span>
                    </div>
                    <div class="text-3xl font-extrabold tabular-nums text-amber-600 dark:text-amber-400">{{ stats.pending_marks_subjects ?? 0 }}</div>
                    <p class="text-xs text-base-content/60 mt-1">Subject marks awaiting submission</p>
                </div>

                <div class="group rounded-2xl border border-base-300 bg-base-100 p-5 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <div class="flex items-start justify-between mb-3">
                        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 text-white flex items-center justify-center shadow-md shadow-sky-500/20">
                            <ChartBarIcon class="w-5 h-5" />
                        </div>
                        <span class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Pass Rate</span>
                    </div>
                    <div class="text-3xl font-extrabold tabular-nums text-sky-600 dark:text-sky-400">
                        {{ stats.avg_pass_rate !== null ? `${stats.avg_pass_rate}%` : '—' }}
                    </div>
                    <p class="text-xs text-base-content/60 mt-1">Across finalized exam results</p>
                </div>

                <!-- Top performer: subtle amber tint via opacity (works in both
                     light + dark) instead of the previous from-amber-50 wash
                     which rendered as pale cream on dark backgrounds. -->
                <div class="group rounded-2xl border-2 border-amber-500/40 bg-amber-500/5 p-5 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <div class="flex items-start justify-between mb-3">
                        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-amber-500 to-yellow-600 text-white flex items-center justify-center shadow-md shadow-amber-500/30">
                            <TrophyIcon class="w-5 h-5" />
                        </div>
                        <span class="text-[10px] uppercase tracking-wider font-bold text-amber-600 dark:text-amber-400">Top Performer</span>
                    </div>
                    <div class="text-base font-bold leading-tight truncate" :title="stats.top_performer?.name">
                        {{ stats.top_performer?.name || '—' }}
                    </div>
                    <p v-if="stats.top_performer" class="text-xs text-base-content/65 mt-1">
                        <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ stats.top_performer.percentage }}%</span> overall
                    </p>
                    <p v-else class="text-xs text-base-content/55 mt-1">No results yet</p>
                </div>
            </div>

            <!-- ════════════ TABS ════════════
                 Pill-style tabs, compact, icon + label + optional count. -->
            <div class="rounded-2xl border border-base-200 bg-base-100 p-1.5 flex gap-1 overflow-x-auto">
                <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-xl transition-all whitespace-nowrap"
                    :class="tab === t.key
                        ? 'bg-gradient-to-br from-emerald-600 to-teal-700 text-white shadow-md shadow-emerald-500/20'
                        : 'text-base-content/60 hover:text-base-content hover:bg-base-200/50'">
                    <component :is="t.icon" class="w-4 h-4" />
                    {{ t.label }}
                    <span v-if="t.count !== undefined" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                        :class="tab === t.key ? 'bg-white/20' : 'bg-base-200 text-base-content/55'">
                        {{ t.count }}
                    </span>
                </button>
            </div>

            <!-- ════════════ OVERVIEW TAB ════════════ -->
            <div v-if="tab === 'overview'" class="space-y-5">
                <!-- Summary cards: opacity-based color washes survive dark
                     mode without the pale-cream blowout the from-{color}-50
                     gradients caused. -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center shadow-sm">
                                <CheckCircleIcon class="w-5 h-5" />
                            </div>
                            <div class="flex-1">
                                <div class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 tabular-nums leading-none">{{ marksSummary.submitted }}</div>
                                <p class="text-xs font-bold uppercase tracking-wider text-emerald-600/85 dark:text-emerald-400/85 mt-1">Submitted</p>
                            </div>
                        </div>
                        <p class="text-xs text-base-content/65 mt-3">Out of {{ marksSummary.total }} subject-exam slots</p>
                    </div>
                    <div class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shadow-sm">
                                <ClockIcon class="w-5 h-5" />
                            </div>
                            <div class="flex-1">
                                <div class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 tabular-nums leading-none">{{ marksSummary.inProgress }}</div>
                                <p class="text-xs font-bold uppercase tracking-wider text-amber-600/85 dark:text-amber-400/85 mt-1">In Progress</p>
                            </div>
                        </div>
                        <p class="text-xs text-base-content/65 mt-3">Marks entered, not finalized</p>
                    </div>
                    <div class="rounded-2xl border border-rose-500/30 bg-rose-500/5 p-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-rose-500 text-white flex items-center justify-center shadow-sm">
                                <XCircleIcon class="w-5 h-5" />
                            </div>
                            <div class="flex-1">
                                <div class="text-3xl font-extrabold text-rose-600 dark:text-rose-400 tabular-nums leading-none">{{ marksSummary.pending }}</div>
                                <p class="text-xs font-bold uppercase tracking-wider text-rose-600/85 dark:text-rose-400/85 mt-1">Not Started</p>
                            </div>
                        </div>
                        <p class="text-xs text-base-content/65 mt-3">No marks entered yet</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-base-300 bg-base-100 p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <SparklesIcon class="w-4 h-4 text-amber-500" />
                        <h3 class="text-sm font-bold uppercase tracking-wider">Quick actions</h3>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <button @click="tab = 'students'" class="group text-left p-4 rounded-xl border border-base-300 hover:border-emerald-500 hover:bg-emerald-500/10 hover:-translate-y-0.5 transition-all">
                            <UserGroupIcon class="w-7 h-7 text-emerald-600 dark:text-emerald-400 mb-2.5 group-hover:scale-110 transition-transform" />
                            <div class="text-sm font-bold">Students</div>
                            <div class="text-[11px] text-base-content/65 mt-0.5">View &amp; manage roster</div>
                        </button>
                        <button @click="tab = 'marks'" class="group text-left p-4 rounded-xl border border-base-300 hover:border-amber-500 hover:bg-amber-500/10 hover:-translate-y-0.5 transition-all">
                            <ClipboardDocumentCheckIcon class="w-7 h-7 text-amber-600 dark:text-amber-400 mb-2.5 group-hover:scale-110 transition-transform" />
                            <div class="text-sm font-bold">Marks Status</div>
                            <div class="text-[11px] text-base-content/65 mt-0.5">Track submissions</div>
                        </button>
                        <button @click="tab = 'results'" class="group text-left p-4 rounded-xl border border-base-300 hover:border-sky-500 hover:bg-sky-500/10 hover:-translate-y-0.5 transition-all">
                            <ChartBarIcon class="w-7 h-7 text-sky-600 dark:text-sky-400 mb-2.5 group-hover:scale-110 transition-transform" />
                            <div class="text-sm font-bold">Class Results</div>
                            <div class="text-[11px] text-base-content/65 mt-0.5">Latest exam performance</div>
                        </button>
                        <Link :href="route('marks.index')" class="group text-left p-4 rounded-xl border border-base-300 hover:border-violet-500 hover:bg-violet-500/10 hover:-translate-y-0.5 transition-all block">
                            <PencilSquareIcon class="w-7 h-7 text-violet-600 dark:text-violet-400 mb-2.5 group-hover:scale-110 transition-transform" />
                            <div class="text-sm font-bold">Enter Marks</div>
                            <div class="text-[11px] text-base-content/65 mt-0.5">For subjects I teach</div>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ════════════ STUDENTS TAB ════════════ -->
            <div v-if="tab === 'students'" class="space-y-4">
                <div class="rounded-2xl border border-base-200 bg-base-100 overflow-hidden shadow-sm">
                    <div class="px-6 py-4 border-b border-base-200 flex items-center justify-between bg-base-200/30">
                        <div>
                            <h3 class="text-sm font-bold flex items-center gap-1.5">
                                <UserGroupIcon class="w-4 h-4 text-emerald-600" />
                                Students <span class="text-base-content/40 font-normal">· {{ students.length }}</span>
                            </h3>
                            <p class="text-xs text-base-content/55 mt-0.5">Roster of students in your class</p>
                        </div>
                        <Link :href="route('students.create')" class="btn btn-primary btn-sm rounded-xl gap-1.5">
                            <PlusIcon class="w-4 h-4" /> Add Student
                        </Link>
                    </div>
                    <div v-if="!students.length" class="p-12 text-center">
                        <div class="w-14 h-14 rounded-2xl bg-base-200 mx-auto mb-3 flex items-center justify-center">
                            <UserGroupIcon class="w-7 h-7 text-base-content/30" />
                        </div>
                        <p class="text-sm font-medium text-base-content/70">No students in this section yet</p>
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-base-200/40 text-[11px] uppercase tracking-wider text-base-content/55">
                                <tr>
                                    <th class="text-left px-3 sm:px-6 py-3 font-bold">Roll</th>
                                    <th class="text-left px-3 py-3 font-bold">Student</th>
                                    <th class="text-left px-3 py-3 font-bold hidden sm:table-cell">Admission #</th>
                                    <th class="text-left px-3 py-3 font-bold hidden md:table-cell">Father's Name</th>
                                    <th class="text-left px-3 py-3 font-bold hidden lg:table-cell">Phone</th>
                                    <th class="text-right px-3 sm:px-6 py-3 font-bold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-base-200">
                                <tr v-for="s in students" :key="s.id" class="hover:bg-base-200/30 transition-colors">
                                    <td class="px-3 sm:px-6 py-3 font-mono text-xs font-bold text-base-content/55">{{ s.roll_no || '—' }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full text-white font-bold text-[10px] flex items-center justify-center flex-shrink-0 bg-gradient-to-br shadow-sm" :class="avatarColor(s.name)">
                                                {{ initials(s.name) }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-semibold truncate">{{ s.name }}</div>
                                                <div class="text-[10px] text-base-content/50 sm:hidden font-mono truncate">{{ s.admission_no }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-xs font-mono text-base-content/60 hidden sm:table-cell">{{ s.admission_no }}</td>
                                    <td class="px-3 py-3 text-sm hidden md:table-cell">{{ s.father_name || '—' }}</td>
                                    <td class="px-3 py-3 text-sm hidden lg:table-cell font-mono text-xs text-base-content/65">{{ s.guardian_phone || '—' }}</td>
                                    <td class="px-3 sm:px-6 py-3 text-right whitespace-nowrap">
                                        <Link :href="route('students.show', s.id)" class="btn btn-ghost btn-xs rounded-lg">View</Link>
                                        <Link :href="route('students.edit', s.id)" class="btn btn-ghost btn-xs rounded-lg ml-1">
                                            <PencilSquareIcon class="w-3.5 h-3.5" />
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ════════════ MARKS STATUS TAB ════════════ -->
            <div v-if="tab === 'marks'" class="space-y-5">
                <div class="rounded-2xl bg-sky-500/10 border border-sky-500/30 p-4 flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-sky-500/20 text-sky-600 dark:text-sky-400 flex items-center justify-center flex-shrink-0">
                        <InformationCircleIcon class="w-5 h-5" />
                    </div>
                    <div class="text-sm text-sky-900 dark:text-sky-200">
                        <p class="font-bold">As class teacher, you see who has submitted marks for your section.</p>
                        <p class="text-xs text-sky-800/85 dark:text-sky-300/85 mt-1">Each subject has an assigned subject teacher responsible for entering marks. Follow up with them on any delays.</p>
                    </div>
                </div>

                <div v-for="exam in marksStatus" :key="exam.exam_id"
                    class="rounded-2xl border border-base-200 bg-base-100 overflow-hidden shadow-sm">
                    <div class="px-6 py-4 border-b border-base-200 bg-gradient-to-r from-base-200/40 to-transparent flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-sm">{{ exam.exam_name }}</h3>
                            <p class="text-xs text-base-content/55 mt-0.5">{{ exam.exam_type }} · Starts {{ exam.start_date }}</p>
                        </div>
                        <span class="badge badge-sm capitalize bg-base-200">{{ exam.status?.replace('_', ' ') }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-base-200/20 text-[11px] uppercase tracking-wider text-base-content/55">
                                <tr>
                                    <th class="text-left px-3 sm:px-6 py-3 font-bold">Subject</th>
                                    <th class="text-left px-3 py-3 font-bold hidden md:table-cell">Assigned Teacher</th>
                                    <th class="text-right px-3 py-3 font-bold hidden sm:table-cell">Entered</th>
                                    <th class="text-left px-3 py-3 font-bold">Status</th>
                                    <th class="text-right px-3 sm:px-6 py-3 font-bold hidden lg:table-cell">Submitted</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-base-200">
                                <tr v-for="subj in exam.subjects" :key="subj.subject_id" class="hover:bg-base-200/30 transition-colors">
                                    <td class="px-3 sm:px-6 py-3.5">
                                        <div class="font-semibold text-sm">{{ subj.subject_name }}</div>
                                        <div class="text-[11px] text-base-content/50 font-mono">{{ subj.subject_code }} · {{ subj.total_marks }} marks</div>
                                    </td>
                                    <td class="px-3 py-3.5 hidden md:table-cell">
                                        <div v-if="subj.assigned_teacher" class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full text-white font-bold text-[9px] flex items-center justify-center flex-shrink-0 bg-gradient-to-br" :class="avatarColor(subj.assigned_teacher)">
                                                {{ initials(subj.assigned_teacher) }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold truncate">{{ subj.assigned_teacher }}</div>
                                                <div class="text-[11px] text-base-content/50 flex items-center gap-1 truncate">
                                                    <EnvelopeIcon class="w-3 h-3 flex-shrink-0" />
                                                    <span class="truncate">{{ subj.assigned_teacher_email }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <span v-else class="text-xs text-rose-600 dark:text-rose-400 font-medium inline-flex items-center gap-1">
                                            <ExclamationTriangleIcon class="w-3.5 h-3.5" /> No teacher assigned
                                        </span>
                                    </td>
                                    <td class="px-3 py-3.5 text-right hidden sm:table-cell">
                                        <span class="text-xs font-mono tabular-nums">{{ subj.students_entered }}/{{ subj.students_total }}</span>
                                        <div class="h-1.5 bg-base-200 rounded-full overflow-hidden mt-1.5 w-24 ml-auto">
                                            <div class="h-full rounded-full transition-all"
                                                :class="subj.students_entered === subj.students_total ? 'bg-gradient-to-r from-emerald-500 to-teal-500' : subj.students_entered > 0 ? 'bg-gradient-to-r from-amber-500 to-orange-500' : 'bg-rose-300'"
                                                :style="`width: ${entryPct(subj.students_entered, subj.students_total)}%`"></div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold ring-1 whitespace-nowrap"
                                            :class="statusBadge(subj.status)">
                                            <component :is="statusIcon(subj.status)" class="w-3 h-3" />
                                            {{ statusLabel(subj.status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3.5 text-right text-xs text-base-content/55 hidden lg:table-cell">
                                        <span v-if="subj.submitted_at">{{ new Date(subj.submitted_at).toLocaleDateString() }}</span>
                                        <span v-else class="text-base-content/35">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="!marksStatus.length" class="rounded-2xl border border-base-200 bg-base-100 p-12 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-base-200 mx-auto mb-3 flex items-center justify-center">
                        <ClipboardDocumentCheckIcon class="w-7 h-7 text-base-content/30" />
                    </div>
                    <p class="text-sm font-medium text-base-content/70">No active exams for this section</p>
                    <p class="text-xs text-base-content/50 mt-1">Marks entry status will appear here once an exam is published.</p>
                </div>
            </div>

            <!-- ════════════ MY SUBJECTS TAB ════════════
                 Class teachers who also teach as subject teachers see their
                 own assignments here, separate from the section-wide marks
                 status (which shows OTHER teachers' work). -->
            <div v-if="tab === 'my-subjects'" class="space-y-5">
                <div class="rounded-2xl bg-violet-500/10 border border-violet-500/30 p-4 flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-violet-500/20 text-violet-600 dark:text-violet-400 flex items-center justify-center flex-shrink-0">
                        <BookOpenIcon class="w-5 h-5" />
                    </div>
                    <div class="text-sm text-violet-900 dark:text-violet-200">
                        <p class="font-bold">Subjects you personally teach</p>
                        <p class="text-xs text-violet-800/85 dark:text-violet-300/85 mt-1">Across your class teacher section AND any other section. Use this to track your OWN marks-entry progress and your students' results.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="a in mySubjectStatus" :key="`${a.subject_id}-${a.section_id}`"
                        class="rounded-2xl border border-base-200 bg-base-100 p-5 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                        <!-- Header -->
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div>
                                <h4 class="font-bold text-base">{{ a.subject_name }}</h4>
                                <p class="text-[11px] text-base-content/50 font-mono mt-0.5">{{ a.subject_code }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold ring-1 whitespace-nowrap"
                                :class="statusBadge(a.submission_status)">
                                <component :is="statusIcon(a.submission_status)" class="w-3 h-3" />
                                {{ statusLabel(a.submission_status) }}
                            </span>
                        </div>
                        <!-- Class + section pill -->
                        <div class="inline-flex items-center gap-1.5 text-xs font-semibold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-500/30 rounded-md px-2 py-1">
                            <AcademicCapIcon class="w-3.5 h-3.5" />
                            {{ a.class_name }} · Section {{ a.section_name }}
                        </div>

                        <!-- Latest exam marks-entry progress -->
                        <div class="mt-4 pt-4 border-t border-base-200">
                            <div v-if="a.latest_exam">
                                <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/45 mb-1.5">Latest Exam</p>
                                <p class="text-sm font-semibold truncate">{{ a.latest_exam.name }}</p>
                                <div class="flex items-center justify-between text-xs text-base-content/65 mt-2 mb-1">
                                    <span>Entered</span>
                                    <span class="font-mono font-bold tabular-nums">{{ a.students_entered }} / {{ a.students_total }}</span>
                                </div>
                                <div class="h-2 bg-base-200 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all"
                                        :class="a.students_entered === a.students_total && a.students_total > 0 ? 'bg-gradient-to-r from-emerald-500 to-teal-500' : a.students_entered > 0 ? 'bg-gradient-to-r from-amber-500 to-orange-500' : 'bg-rose-300'"
                                        :style="`width: ${entryPct(a.students_entered, a.students_total)}%`"></div>
                                </div>
                            </div>
                            <div v-else class="text-xs text-base-content/45 italic">
                                No exam scheduled yet for this subject.
                            </div>
                        </div>

                        <Link :href="route('marks.index')"
                            class="mt-4 block text-center text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 hover:underline">
                            Enter / review marks →
                        </Link>
                    </div>
                </div>

                <!-- Recent results across MY subjects -->
                <div v-if="myRecentResults.length" class="rounded-2xl border border-base-200 bg-base-100 overflow-hidden shadow-sm">
                    <div class="px-6 py-4 border-b border-base-200 bg-base-200/30">
                        <h3 class="text-sm font-bold flex items-center gap-1.5">
                            <ChartBarIcon class="w-4 h-4 text-violet-600" />
                            Recent results in subjects you teach
                            <span class="text-base-content/40 font-normal">· {{ myRecentResults.length }}</span>
                        </h3>
                        <p class="text-xs text-base-content/55 mt-0.5">Subject-wise marks for students in classes where you teach</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-base-200/20 text-[11px] uppercase tracking-wider text-base-content/55">
                                <tr>
                                    <th class="text-left px-3 sm:px-6 py-3 font-bold">Student</th>
                                    <th class="text-left px-3 py-3 font-bold hidden md:table-cell">Class · Section</th>
                                    <th class="text-left px-3 py-3 font-bold hidden lg:table-cell">Exam</th>
                                    <th class="text-left px-3 py-3 font-bold">My Subjects</th>
                                    <th class="text-right px-3 sm:px-6 py-3 font-bold">Overall</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-base-200">
                                <tr v-for="r in myRecentResults" :key="r.id" class="hover:bg-base-200/30 transition-colors">
                                    <td class="px-3 sm:px-6 py-3.5">
                                        <div class="font-semibold text-sm">{{ r.student_name }}</div>
                                        <div class="text-[11px] text-base-content/50 font-mono">Roll {{ r.roll_no }}</div>
                                    </td>
                                    <td class="px-3 py-3.5 text-xs text-base-content/65 hidden md:table-cell">{{ r.class_name }} · {{ r.section_name }}</td>
                                    <td class="px-3 py-3.5 text-xs text-base-content/65 hidden lg:table-cell">{{ r.exam_name }}</td>
                                    <td class="px-3 py-3.5">
                                        <div class="flex flex-wrap gap-1.5">
                                            <span v-for="sm in r.subject_marks" :key="sm.subject_id"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-mono ring-1"
                                                :class="sm.is_passed ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-500/30' : 'bg-rose-500/15 text-rose-700 dark:text-rose-300 ring-rose-500/30'">
                                                <span class="font-semibold not-italic">{{ sm.subject_name }}:</span>
                                                {{ sm.obtained_marks }}/{{ sm.total_marks }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3.5 text-right">
                                        <span class="font-bold tabular-nums">{{ Number(r.overall_percentage).toFixed(1) }}%</span>
                                        <div class="mt-0.5">
                                            <span class="inline-flex items-center px-1.5 py-0 rounded text-[10px] font-bold" :class="gradeColor(r.overall_grade)">{{ r.overall_grade || '—' }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ════════════ CLASS RESULTS TAB ════════════ -->
            <div v-if="tab === 'results'" class="space-y-4">
                <div class="rounded-2xl border border-base-200 bg-base-100 overflow-hidden shadow-sm">
                    <div class="px-6 py-4 border-b border-base-200 bg-base-200/30">
                        <h3 class="text-sm font-bold flex items-center gap-1.5">
                            <ChartBarIcon class="w-4 h-4 text-sky-600" />
                            Latest Results <span class="text-base-content/40 font-normal">· {{ latestResults.length }}</span>
                        </h3>
                        <p class="text-xs text-base-content/55 mt-0.5">Most recent finalized exam results for your class</p>
                    </div>
                    <div v-if="!latestResults.length" class="p-12 text-center">
                        <div class="w-14 h-14 rounded-2xl bg-base-200 mx-auto mb-3 flex items-center justify-center">
                            <ChartBarIcon class="w-7 h-7 text-base-content/30" />
                        </div>
                        <p class="text-sm font-medium text-base-content/70">No results finalized yet</p>
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-base-200/40 text-[11px] uppercase tracking-wider text-base-content/55">
                                <tr>
                                    <th class="text-left px-3 sm:px-6 py-3 font-bold">Pos</th>
                                    <th class="text-left px-3 py-3 font-bold">Student</th>
                                    <th class="text-left px-3 py-3 font-bold hidden md:table-cell">Exam</th>
                                    <th class="text-right px-3 py-3 font-bold hidden sm:table-cell">Marks</th>
                                    <th class="text-right px-3 py-3 font-bold">%</th>
                                    <th class="text-center px-3 py-3 font-bold hidden sm:table-cell">Grade</th>
                                    <th class="text-right px-3 sm:px-6 py-3 font-bold">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-base-200">
                                <tr v-for="r in latestResults" :key="r.id" class="hover:bg-base-200/30 transition-colors">
                                    <td class="px-3 sm:px-6 py-3">
                                        <span v-if="r.position" class="inline-flex w-7 h-7 rounded-full items-center justify-center text-xs font-bold shadow-sm"
                                            :class="r.position === 1 ? 'bg-gradient-to-br from-amber-400 to-yellow-500 text-white' : r.position === 2 ? 'bg-gradient-to-br from-slate-300 to-slate-400 text-white' : r.position === 3 ? 'bg-gradient-to-br from-amber-700 to-orange-700 text-white' : 'bg-base-200 text-base-content/60'">
                                            {{ r.position }}
                                        </span>
                                        <span v-else class="text-base-content/30">—</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="font-semibold text-sm">{{ r.student_name }}</div>
                                        <div class="text-[11px] text-base-content/50 font-mono">Roll {{ r.roll_no }}</div>
                                        <div class="text-[10px] text-base-content/55 mt-0.5 md:hidden">{{ r.exam_name }}</div>
                                    </td>
                                    <td class="px-3 py-3 text-xs hidden md:table-cell">{{ r.exam_name }}</td>
                                    <td class="px-3 py-3 text-right font-mono text-xs hidden sm:table-cell">{{ Number(r.obtained_marks).toFixed(0) }}/{{ Number(r.total_marks).toFixed(0) }}</td>
                                    <td class="px-3 py-3 text-right">
                                        <span class="font-bold tabular-nums">{{ Number(r.percentage).toFixed(1) }}%</span>
                                        <div class="text-[10px] sm:hidden mt-0.5">
                                            <span class="inline-flex items-center px-1.5 py-0 rounded text-[10px] font-bold" :class="gradeColor(r.grade)">{{ r.grade || '—' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-center hidden sm:table-cell">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold" :class="gradeColor(r.grade)">{{ r.grade || '—' }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 text-right">
                                        <span v-if="r.is_passed" class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 text-xs font-bold">
                                            <CheckCircleIcon class="w-3.5 h-3.5" /> <span class="hidden sm:inline">Passed</span>
                                        </span>
                                        <span v-else class="inline-flex items-center gap-1 text-rose-600 dark:text-rose-400 text-xs font-bold">
                                            <XCircleIcon class="w-3.5 h-3.5" /> <span class="hidden sm:inline">Failed</span>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ════════════ SECTION TEAM TAB ════════════ -->
            <div v-if="tab === 'team'" class="space-y-4">
                <div class="rounded-2xl bg-sky-500/10 border border-sky-500/30 p-4 flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-sky-500/20 text-sky-600 dark:text-sky-400 flex items-center justify-center flex-shrink-0">
                        <UsersIcon class="w-5 h-5" />
                    </div>
                    <p class="text-sm text-sky-900 dark:text-sky-200">
                        These are all the subject teachers who work with your section. They handle their own marks entry. Reach out to coordinate on any subject.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="t in sectionTeam" :key="t.teacher_email + t.subject_code"
                        class="group rounded-2xl border border-base-200 bg-base-100 p-5 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                        <div class="flex items-start gap-3">
                            <div class="w-12 h-12 rounded-2xl text-white font-bold text-base flex items-center justify-center flex-shrink-0 bg-gradient-to-br shadow-md" :class="avatarColor(t.teacher_name)">
                                {{ initials(t.teacher_name) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-sm truncate">{{ t.teacher_name }}</h4>
                                <p class="text-xs text-emerald-600 dark:text-emerald-400 font-bold mt-0.5">{{ t.subject_name }} <span class="text-base-content/45 font-mono font-normal">({{ t.subject_code }})</span></p>
                                <p class="text-[11px] text-base-content/50 mt-2 flex items-center gap-1 truncate">
                                    <EnvelopeIcon class="w-3 h-3 flex-shrink-0" /> {{ t.teacher_email }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!sectionTeam.length" class="rounded-2xl border border-base-200 bg-base-100 p-12 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-base-200 mx-auto mb-3 flex items-center justify-center">
                        <UsersIcon class="w-7 h-7 text-base-content/30" />
                    </div>
                    <p class="text-sm font-medium text-base-content/70">No subject teachers assigned yet</p>
                    <p class="text-xs text-base-content/50 mt-1">Your Principal will assign subject teachers to this section.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
