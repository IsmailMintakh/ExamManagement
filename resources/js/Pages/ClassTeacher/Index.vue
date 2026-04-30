<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    UserGroupIcon, ClipboardDocumentCheckIcon, ChartBarIcon, TrophyIcon,
    AcademicCapIcon, CheckCircleIcon, XCircleIcon, ClockIcon,
    PencilSquareIcon, PlusIcon, DocumentTextIcon, UsersIcon,
    ExclamationTriangleIcon, EnvelopeIcon, ArrowRightIcon,
    InformationCircleIcon, ChevronDownIcon,
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
})

// UI state
const tab = ref('overview')
const tabs = [
    { key: 'overview', label: 'Overview', icon: AcademicCapIcon },
    { key: 'students', label: 'Students', icon: UserGroupIcon },
    { key: 'marks', label: 'Marks Status', icon: ClipboardDocumentCheckIcon },
    { key: 'results', label: 'Results', icon: ChartBarIcon },
    { key: 'team', label: 'Section Team', icon: UsersIcon },
]

function switchSection(sectionId) {
    router.get(route('class-teacher.index'), { section: sectionId }, { preserveState: false })
}

// Marks status helpers
function statusBadge(status) {
    return {
        submitted: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
        draft: 'bg-amber-100 text-amber-700 ring-amber-200',
        pending: 'bg-slate-100 text-slate-600 ring-slate-200',
    }[status] || 'bg-slate-100 text-slate-600 ring-slate-200'
}
function statusLabel(status) {
    return { submitted: 'Submitted', draft: 'In Progress', pending: 'Not Started' }[status] || 'Unknown'
}
function statusIcon(status) {
    return { submitted: CheckCircleIcon, draft: ClockIcon, pending: XCircleIcon }[status] || XCircleIcon
}

// Aggregate: pending vs submitted across all exams
const marksSummary = computed(() => {
    const all = props.marksStatus.flatMap(e => e.subjects)
    return {
        total: all.length,
        submitted: all.filter(s => s.status === 'submitted').length,
        inProgress: all.filter(s => s.status === 'draft').length,
        pending: all.filter(s => s.status === 'pending' || (!s.status)).length,
    }
})

// Initials for avatar
function initials(name) {
    if (!name) return '?'
    return name.split(' ').filter(Boolean).map(n => n[0]).slice(0, 2).join('').toUpperCase()
}
function avatarColor(name) {
    const colors = ['bg-emerald-500','bg-sky-500','bg-amber-500','bg-rose-500','bg-violet-500','bg-teal-500','bg-indigo-500']
    let hash = 0
    for (let i = 0; i < (name || '').length; i++) hash = (hash * 31 + name.charCodeAt(i)) | 0
    return colors[Math.abs(hash) % colors.length]
}
function gradeColor(g) {
    if (!g) return 'bg-slate-100 text-slate-600'
    if (g.startsWith('A')) return 'bg-emerald-100 text-emerald-700'
    if (g.startsWith('B')) return 'bg-sky-100 text-sky-700'
    if (g.startsWith('C')) return 'bg-amber-100 text-amber-700'
    if (g === 'D' || g === 'E') return 'bg-orange-100 text-orange-700'
    return 'bg-rose-100 text-rose-700'
}
</script>

<template>
    <Head title="My Class" />
    <AppLayout :breadcrumbs="[{ label: 'My Class' }]">
        <div class="space-y-6 max-w-[1500px] mx-auto">
            <!-- ==================== HEADER ==================== -->
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs text-base-content/55 font-semibold uppercase tracking-wider mb-1.5">
                        <AcademicCapIcon class="w-3.5 h-3.5" />
                        <span>Class Teacher Dashboard</span>
                    </div>
                    <h1 class="text-3xl font-extrabold tracking-tight flex items-center gap-3">
                        {{ activeSection?.class_name }} <span class="text-base-content/30">·</span>
                        <span class="text-primary">Section {{ activeSection?.name }}</span>
                    </h1>
                    <p class="text-sm text-base-content/60 mt-1.5">
                        {{ activeSection?.school_name }}
                        <span v-if="currentSession"> · Session {{ currentSession.name }}</span>
                    </p>
                </div>

                <!-- Section selector (only shown if user is class teacher of multiple sections) -->
                <div v-if="sections.length > 1" class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-base-content/55 uppercase tracking-wider">Switch Class</span>
                    <select @change="switchSection($event.target.value)" :value="activeSection?.id"
                        class="select select-bordered select-sm rounded-xl min-w-[200px]">
                        <option v-for="s in sections" :key="s.id" :value="s.id">
                            {{ s.class_name }} · Section {{ s.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- ==================== KPI STRIP ==================== -->
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="rounded-2xl border border-base-200 bg-base-100 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                            <UserGroupIcon class="w-5 h-5" />
                        </div>
                        <span class="text-[10px] uppercase tracking-wider font-bold text-base-content/40">Students</span>
                    </div>
                    <div class="mt-3 text-3xl font-extrabold tabular-nums">{{ stats.student_count ?? 0 }}</div>
                    <p class="text-xs text-base-content/55 mt-1">Capacity: {{ activeSection?.capacity || '—' }}</p>
                </div>

                <div class="rounded-2xl border border-base-200 bg-base-100 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center">
                            <ClipboardDocumentCheckIcon class="w-5 h-5" />
                        </div>
                        <span class="text-[10px] uppercase tracking-wider font-bold text-base-content/40">Pending Marks</span>
                    </div>
                    <div class="mt-3 text-3xl font-extrabold tabular-nums text-amber-700">{{ stats.pending_marks_subjects ?? 0 }}</div>
                    <p class="text-xs text-base-content/55 mt-1">Subjects not yet submitted</p>
                </div>

                <div class="rounded-2xl border border-base-200 bg-base-100 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center">
                            <ChartBarIcon class="w-5 h-5" />
                        </div>
                        <span class="text-[10px] uppercase tracking-wider font-bold text-base-content/40">Pass Rate</span>
                    </div>
                    <div class="mt-3 text-3xl font-extrabold tabular-nums text-emerald-700">
                        {{ stats.avg_pass_rate !== null ? `${stats.avg_pass_rate}%` : '—' }}
                    </div>
                    <p class="text-xs text-base-content/55 mt-1">Across finalized results</p>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-base-100 p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center">
                            <TrophyIcon class="w-5 h-5" />
                        </div>
                        <span class="text-[10px] uppercase tracking-wider font-bold text-amber-700">Top Performer</span>
                    </div>
                    <div class="mt-3 text-base font-bold leading-tight truncate" :title="stats.top_performer?.name">
                        {{ stats.top_performer?.name || '—' }}
                    </div>
                    <p v-if="stats.top_performer" class="text-xs text-base-content/55 mt-1">
                        <span class="font-bold text-emerald-600">{{ stats.top_performer.percentage }}%</span>
                    </p>
                </div>
            </div>

            <!-- ==================== TABS ==================== -->
            <div class="border-b border-base-200 flex gap-1 overflow-x-auto">
                <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
                    class="inline-flex items-center gap-2 px-4 py-3 text-sm font-semibold border-b-2 transition-colors whitespace-nowrap"
                    :class="tab === t.key
                        ? 'border-primary text-primary'
                        : 'border-transparent text-base-content/60 hover:text-base-content hover:border-base-300'">
                    <component :is="t.icon" class="w-4 h-4" />
                    {{ t.label }}
                </button>
            </div>

            <!-- ==================== OVERVIEW TAB ==================== -->
            <div v-if="tab === 'overview'" class="space-y-5">
                <!-- Marks submission summary cards -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-5">
                        <CheckCircleIcon class="w-6 h-6 text-emerald-600 mb-3" />
                        <div class="text-2xl font-extrabold text-emerald-700 tabular-nums">{{ marksSummary.submitted }}</div>
                        <p class="text-xs font-semibold text-emerald-800 mt-0.5">Submitted</p>
                        <p class="text-xs text-base-content/55 mt-1">Out of {{ marksSummary.total }} subject-exam slots</p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50/40 p-5">
                        <ClockIcon class="w-6 h-6 text-amber-600 mb-3" />
                        <div class="text-2xl font-extrabold text-amber-700 tabular-nums">{{ marksSummary.inProgress }}</div>
                        <p class="text-xs font-semibold text-amber-800 mt-0.5">In Progress</p>
                        <p class="text-xs text-base-content/55 mt-1">Marks entered but not finalized</p>
                    </div>
                    <div class="rounded-2xl border border-rose-200 bg-rose-50/40 p-5">
                        <XCircleIcon class="w-6 h-6 text-rose-600 mb-3" />
                        <div class="text-2xl font-extrabold text-rose-700 tabular-nums">{{ marksSummary.pending }}</div>
                        <p class="text-xs font-semibold text-rose-800 mt-0.5">Not Started</p>
                        <p class="text-xs text-base-content/55 mt-1">No marks entered yet</p>
                    </div>
                </div>

                <!-- Quick links -->
                <div class="rounded-2xl border border-base-200 bg-base-100 p-6">
                    <h3 class="text-sm font-bold mb-4">Quick actions</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <button @click="tab = 'students'" class="group text-left p-4 rounded-xl border border-base-200 hover:border-primary/40 hover:bg-primary/5 transition-all">
                            <UserGroupIcon class="w-6 h-6 text-primary mb-2" />
                            <div class="text-sm font-semibold">Students</div>
                            <div class="text-xs text-base-content/55">View &amp; manage roster</div>
                        </button>
                        <button @click="tab = 'marks'" class="group text-left p-4 rounded-xl border border-base-200 hover:border-amber-300 hover:bg-amber-50 transition-all">
                            <ClipboardDocumentCheckIcon class="w-6 h-6 text-amber-600 mb-2" />
                            <div class="text-sm font-semibold">Marks Status</div>
                            <div class="text-xs text-base-content/55">Track subject submissions</div>
                        </button>
                        <button @click="tab = 'results'" class="group text-left p-4 rounded-xl border border-base-200 hover:border-emerald-300 hover:bg-emerald-50 transition-all">
                            <ChartBarIcon class="w-6 h-6 text-emerald-600 mb-2" />
                            <div class="text-sm font-semibold">Results</div>
                            <div class="text-xs text-base-content/55">Latest exam performance</div>
                        </button>
                        <Link :href="route('marks.index')" class="group text-left p-4 rounded-xl border border-base-200 hover:border-sky-300 hover:bg-sky-50 transition-all block">
                            <PencilSquareIcon class="w-6 h-6 text-sky-600 mb-2" />
                            <div class="text-sm font-semibold">Enter Marks</div>
                            <div class="text-xs text-base-content/55">For subjects I teach</div>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ==================== STUDENTS TAB ==================== -->
            <div v-if="tab === 'students'" class="space-y-4">
                <div class="rounded-2xl border border-base-200 bg-base-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-base-200 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold">Students ({{ students.length }})</h3>
                            <p class="text-xs text-base-content/55 mt-0.5">Roster of students in your class</p>
                        </div>
                        <Link :href="route('students.create')" class="btn btn-primary btn-sm rounded-xl gap-1.5">
                            <PlusIcon class="w-4 h-4" /> Add Student
                        </Link>
                    </div>
                    <div v-if="!students.length" class="p-10 text-center">
                        <UserGroupIcon class="w-10 h-10 text-base-content/30 mx-auto mb-3" />
                        <p class="text-sm font-medium text-base-content/70">No students in this section yet</p>
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-base-200/40 text-[11px] uppercase tracking-wider text-base-content/55">
                                <tr>
                                    <th class="text-left px-3 sm:px-6 py-3 font-bold">Roll</th>
                                    <th class="text-left px-3 py-3 font-bold">Student</th>
                                    <th class="text-left px-3 py-3 font-bold hidden sm:table-cell">Admission No.</th>
                                    <th class="text-left px-3 py-3 font-bold hidden md:table-cell">Father's Name</th>
                                    <th class="text-left px-3 py-3 font-bold hidden lg:table-cell">Guardian Phone</th>
                                    <th class="text-right px-3 sm:px-6 py-3 font-bold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-base-200">
                                <tr v-for="s in students" :key="s.id" class="hover:bg-base-200/30 transition-colors">
                                    <td class="px-3 sm:px-6 py-3 font-mono text-xs font-semibold text-base-content/60">{{ s.roll_no || '—' }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-2 sm:gap-3">
                                            <div class="w-8 h-8 rounded-full text-white font-bold text-[10px] flex items-center justify-center flex-shrink-0" :class="avatarColor(s.name)">
                                                {{ initials(s.name) }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-medium truncate">{{ s.name }}</div>
                                                <div class="text-[10px] text-base-content/50 sm:hidden font-mono truncate">{{ s.admission_no }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-xs font-mono text-base-content/60 hidden sm:table-cell">{{ s.admission_no }}</td>
                                    <td class="px-3 py-3 text-sm hidden md:table-cell">{{ s.father_name || '—' }}</td>
                                    <td class="px-3 py-3 text-sm hidden lg:table-cell">{{ s.guardian_phone || '—' }}</td>
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

            <!-- ==================== MARKS STATUS TAB ==================== -->
            <div v-if="tab === 'marks'" class="space-y-5">
                <div class="rounded-xl bg-sky-50 border border-sky-200 p-4 flex items-start gap-3">
                    <InformationCircleIcon class="w-5 h-5 text-sky-600 flex-shrink-0 mt-0.5" />
                    <div class="text-sm text-sky-900">
                        <p class="font-semibold">As class teacher, you can see who has submitted marks for your section.</p>
                        <p class="text-xs text-sky-800 mt-1">Each subject has an assigned subject teacher responsible for entering marks. Follow up with them for any delays.</p>
                    </div>
                </div>

                <div v-for="exam in marksStatus" :key="exam.exam_id"
                    class="rounded-2xl border border-base-200 bg-base-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-base-200 bg-base-200/30 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold">{{ exam.exam_name }}</h3>
                            <p class="text-xs text-base-content/55 mt-0.5">{{ exam.exam_type }} · Starts {{ exam.start_date }}</p>
                        </div>
                        <span class="badge badge-sm capitalize">{{ exam.status?.replace('_', ' ') }}</span>
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
                                        <div class="font-medium text-sm">{{ subj.subject_name }}</div>
                                        <div class="text-[11px] text-base-content/50 font-mono">{{ subj.subject_code }} · {{ subj.total_marks }} marks</div>
                                        <!-- Mobile-only inline teacher info -->
                                        <div class="md:hidden mt-1.5">
                                            <span v-if="subj.assigned_teacher" class="text-[11px] text-base-content/65 inline-flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>{{ subj.assigned_teacher }}
                                            </span>
                                            <span v-else class="text-[11px] text-rose-600 inline-flex items-center gap-1">
                                                <ExclamationTriangleIcon class="w-3 h-3" /> No teacher
                                            </span>
                                            <span class="text-[11px] text-base-content/55 sm:hidden ml-2">· {{ subj.students_entered }}/{{ subj.students_total }}</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5 hidden md:table-cell">
                                        <div v-if="subj.assigned_teacher" class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full text-white font-bold text-[9px] flex items-center justify-center flex-shrink-0" :class="avatarColor(subj.assigned_teacher)">
                                                {{ initials(subj.assigned_teacher) }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-sm font-medium truncate">{{ subj.assigned_teacher }}</div>
                                                <div class="text-[11px] text-base-content/50 flex items-center gap-1 truncate">
                                                    <EnvelopeIcon class="w-3 h-3 flex-shrink-0" />
                                                    <span class="truncate">{{ subj.assigned_teacher_email }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <span v-else class="text-xs text-rose-600 font-medium inline-flex items-center gap-1">
                                            <ExclamationTriangleIcon class="w-3.5 h-3.5" /> No teacher assigned
                                        </span>
                                    </td>
                                    <td class="px-3 py-3.5 text-right hidden sm:table-cell">
                                        <span class="text-xs font-mono tabular-nums">{{ subj.students_entered }}/{{ subj.students_total }}</span>
                                        <div class="h-1 bg-base-200 rounded-full overflow-hidden mt-1 w-20 ml-auto">
                                            <div class="h-full rounded-full"
                                                :class="subj.students_entered === subj.students_total ? 'bg-emerald-500' : subj.students_entered > 0 ? 'bg-amber-500' : 'bg-rose-400'"
                                                :style="`width: ${subj.students_total ? (subj.students_entered / subj.students_total * 100) : 0}%`"></div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5">
                                        <span class="inline-flex items-center gap-1 sm:gap-1.5 px-2 sm:px-2.5 py-1 rounded-full text-[10px] sm:text-[11px] font-semibold ring-1 whitespace-nowrap"
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

                <div v-if="!marksStatus.length" class="rounded-2xl border border-base-200 bg-base-100 p-10 text-center">
                    <ClipboardDocumentCheckIcon class="w-10 h-10 text-base-content/30 mx-auto mb-3" />
                    <p class="text-sm font-medium text-base-content/70">No active exams for this section</p>
                    <p class="text-xs text-base-content/50 mt-1">Marks entry status will appear here once an exam is published.</p>
                </div>
            </div>

            <!-- ==================== RESULTS TAB ==================== -->
            <div v-if="tab === 'results'" class="space-y-4">
                <div class="rounded-2xl border border-base-200 bg-base-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-base-200">
                        <h3 class="text-sm font-bold">Latest Results · {{ latestResults.length }}</h3>
                        <p class="text-xs text-base-content/55 mt-0.5">Most recent finalized exam results for your class</p>
                    </div>
                    <div v-if="!latestResults.length" class="p-10 text-center">
                        <ChartBarIcon class="w-10 h-10 text-base-content/30 mx-auto mb-3" />
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
                                        <span v-if="r.position" class="inline-flex w-7 h-7 rounded-full items-center justify-center text-xs font-bold"
                                            :class="r.position === 1 ? 'bg-amber-500 text-white' : r.position === 2 ? 'bg-slate-200 text-slate-700' : r.position === 3 ? 'bg-amber-700 text-white' : 'bg-base-200 text-base-content/60'">
                                            {{ r.position }}
                                        </span>
                                        <span v-else class="text-base-content/30">—</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="font-medium text-sm">{{ r.student_name }}</div>
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
                                        <span v-if="r.is_passed" class="inline-flex items-center gap-1 text-emerald-700 text-xs font-semibold">
                                            <CheckCircleIcon class="w-3.5 h-3.5" /> <span class="hidden sm:inline">Passed</span>
                                        </span>
                                        <span v-else class="inline-flex items-center gap-1 text-rose-600 text-xs font-semibold">
                                            <XCircleIcon class="w-3.5 h-3.5" /> <span class="hidden sm:inline">Failed</span>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ==================== SECTION TEAM TAB ==================== -->
            <div v-if="tab === 'team'" class="space-y-4">
                <div class="rounded-xl bg-sky-50 border border-sky-200 p-4 flex items-start gap-3">
                    <InformationCircleIcon class="w-5 h-5 text-sky-600 flex-shrink-0 mt-0.5" />
                    <p class="text-sm text-sky-900">
                        These are all the subject teachers who work with your section. They handle their own marks entry for their subjects. You can reach out to coordinate on any subject.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="t in sectionTeam" :key="t.teacher_email + t.subject_code"
                        class="rounded-2xl border border-base-200 bg-base-100 p-5 hover:shadow-md transition-all">
                        <div class="flex items-start gap-3">
                            <div class="w-11 h-11 rounded-full text-white font-bold text-sm flex items-center justify-center flex-shrink-0" :class="avatarColor(t.teacher_name)">
                                {{ initials(t.teacher_name) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-sm truncate">{{ t.teacher_name }}</h4>
                                <p class="text-xs text-primary font-semibold mt-0.5">{{ t.subject_name }} <span class="text-base-content/40 font-mono">({{ t.subject_code }})</span></p>
                                <p class="text-[11px] text-base-content/50 mt-1.5 flex items-center gap-1 truncate">
                                    <EnvelopeIcon class="w-3 h-3 flex-shrink-0" /> {{ t.teacher_email }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!sectionTeam.length" class="rounded-2xl border border-base-200 bg-base-100 p-10 text-center">
                    <UsersIcon class="w-10 h-10 text-base-content/30 mx-auto mb-3" />
                    <p class="text-sm font-medium text-base-content/70">No subject teachers assigned yet</p>
                    <p class="text-xs text-base-content/50 mt-1">Your Principal will assign subject teachers to this section.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
