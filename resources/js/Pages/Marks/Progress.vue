<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    ClipboardDocumentCheckIcon, CheckCircleIcon, ClockIcon,
    XCircleIcon, UserIcon, AcademicCapIcon, ChartBarIcon,
    PencilSquareIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exams: { type: Array, default: () => [] },
    exam: { type: Object, default: null },
    cells: { type: Array, default: () => [] },
    teachers: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ total: 0, submitted: 0, in_progress: 0, not_started: 0, pct: 0 }) },
    filters: { type: Object, default: () => ({}) },
})

const examId = ref(props.filters?.exam_id ? Number(props.filters.exam_id) : props.exam?.id ?? '')
const classFilter = ref(props.filters?.school_class_id ? Number(props.filters.school_class_id) : '')
const view = ref('teachers') // 'teachers' | 'cells'

// Status filter: 'all' | 'pending' | 'completed'.
//   pending   = at least one cell still not_started or in_progress
//   completed = every cell submitted or verified
const statusFilter = ref('pending')

// Teacher filter — picks one teacher out of the list. Client-side filter
// because the teacher list is already on the page.
const teacherFilter = ref('')

function reload() {
    router.get(route('marks.progress'), {
        exam_id: examId.value || undefined,
        school_class_id: classFilter.value || undefined,
    }, { preserveState: true, preserveScroll: true })
}

const examOptions = computed(() => props.exams.map(e => ({
    value: e.id, label: e.name, sublabel: e.status,
})))
const classOptions = computed(() => [
    { value: '', label: 'All classes' },
    ...props.classes.map(c => ({ value: c.id, label: c.name })),
])

// Teacher dropdown — every distinct teacher in the loaded rollup. Adds an
// "All teachers" sentinel + an "Unassigned" bucket when relevant.
const teacherOptions = computed(() => {
    const opts = [{ value: '', label: 'All teachers' }]
    for (const t of props.teachers) {
        opts.push({
            value: t.teacher_id ?? `__name__${t.teacher}`,
            label: t.teacher,
            sublabel: `${t.submitted + t.verified}/${t.total} done`,
        })
    }
    return opts
})

// Helper: does this teacher count as "completed"? Every cell submitted or verified.
const isCompleted = (t) => t.total > 0 && (t.submitted + t.verified) === t.total
const isPending   = (t) => t.not_started + t.in_progress > 0

const pill = (s) => ({
    not_started: 'bg-rose-500/15 text-rose-700 dark:text-rose-300 ring-rose-500/30',
    in_progress: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 ring-amber-500/30',
    submitted: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-500/30',
    verified: 'bg-sky-500/15 text-sky-700 dark:text-sky-300 ring-sky-500/30',
}[s] || 'bg-base-200 ring-base-300')

const pillLabel = (s) => ({
    not_started: 'Not started',
    in_progress: 'In progress',
    submitted: 'Submitted',
    verified: 'Verified',
}[s] || s)

const visibleTeachers = computed(() => {
    let rows = props.teachers
    if (teacherFilter.value !== '') {
        rows = rows.filter(t => String(t.teacher_id ?? `__name__${t.teacher}`) === String(teacherFilter.value))
    }
    if (statusFilter.value === 'pending')   rows = rows.filter(isPending)
    if (statusFilter.value === 'completed') rows = rows.filter(isCompleted)
    return rows
})

const visibleCells = computed(() => {
    let rows = props.cells
    if (teacherFilter.value !== '') {
        rows = rows.filter(c => {
            const k = c.teacher_id ?? `__name__${c.teacher || '— Unassigned —'}`
            return String(k) === String(teacherFilter.value)
        })
    }
    if (statusFilter.value === 'pending')   rows = rows.filter(c => c.status === 'not_started' || c.status === 'in_progress')
    if (statusFilter.value === 'completed') rows = rows.filter(c => c.status === 'submitted' || c.status === 'verified')
    return rows
})

// Summary counts for the status pill row (after teacher filter applied).
const teacherSummary = computed(() => {
    const all = props.teachers.filter(t =>
        teacherFilter.value === ''
        || String(t.teacher_id ?? `__name__${t.teacher}`) === String(teacherFilter.value))
    return {
        total: all.length,
        completed: all.filter(isCompleted).length,
        pending: all.filter(isPending).length,
    }
})

function entryLink(c) {
    if (!props.exam?.id || !c.subject_id || !c.section_id) return null
    return route('marks.entry', [props.exam.id, c.subject_id, c.section_id])
}
</script>

<template>
    <Head title="Marks Entry Progress" />
    <AppLayout :breadcrumbs="[{ label: 'Marks', href: route('marks.index') }, { label: 'Progress' }]">
        <div class="space-y-4 max-w-6xl mx-auto">

            <!-- Header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight flex items-center gap-2">
                        <ClipboardDocumentCheckIcon class="w-6 h-6 text-primary" />
                        Marks Entry Progress
                    </h1>
                    <p class="text-sm text-base-content/55 mt-1">
                        See who has finished entering marks and who is still pending — for timely result submission.
                    </p>
                </div>
                <Link :href="route('marks.index')" class="btn btn-ghost btn-sm">
                    Back to Marks
                </Link>
            </div>

            <!-- Filter strip -->
            <div class="rounded-2xl border border-base-300 bg-base-100 p-3 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 block mb-1.5">Exam</label>
                        <SearchableSelect v-model="examId" :options="examOptions" @change="reload"
                            placeholder="Pick an exam…" size="sm" />
                    </div>
                    <div>
                        <label class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 block mb-1.5">Class</label>
                        <SearchableSelect v-model="classFilter" :options="classOptions" @change="reload"
                            placeholder="All classes" size="sm" />
                    </div>
                    <div>
                        <label class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 block mb-1.5">
                            Teacher
                            <span class="font-medium normal-case text-base-content/40">
                                · {{ teacherSummary.completed }}/{{ teacherSummary.total }} fully done
                            </span>
                        </label>
                        <SearchableSelect v-model="teacherFilter" :options="teacherOptions"
                            placeholder="All teachers" size="sm" :clearable="true" />
                    </div>
                </div>

                <!-- Status pill row — All / Pending / Completed -->
                <div class="flex items-center gap-1 rounded-xl border border-base-300 bg-base-200/40 p-1 text-xs w-full sm:w-auto sm:inline-flex">
                    <button @click="statusFilter = 'all'"
                        class="rounded-lg px-3 py-1.5 font-bold transition-colors"
                        :class="statusFilter === 'all' ? 'bg-base-100 shadow-sm' : 'text-base-content/55 hover:text-base-content'">
                        All <span class="ml-1 text-base-content/45 font-mono">{{ teacherSummary.total }}</span>
                    </button>
                    <button @click="statusFilter = 'pending'"
                        class="rounded-lg px-3 py-1.5 font-bold transition-colors"
                        :class="statusFilter === 'pending' ? 'bg-amber-500 text-white' : 'text-base-content/55 hover:text-base-content'">
                        Pending <span class="ml-1 font-mono"
                            :class="statusFilter === 'pending' ? 'text-white/80' : 'text-base-content/45'">{{ teacherSummary.pending }}</span>
                    </button>
                    <button @click="statusFilter = 'completed'"
                        class="rounded-lg px-3 py-1.5 font-bold transition-colors"
                        :class="statusFilter === 'completed' ? 'bg-emerald-500 text-white' : 'text-base-content/55 hover:text-base-content'">
                        Completed <span class="ml-1 font-mono"
                            :class="statusFilter === 'completed' ? 'text-white/80' : 'text-base-content/45'">{{ teacherSummary.completed }}</span>
                    </button>
                </div>
            </div>

            <template v-if="exam">
                <!-- Stat strip -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/5 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-wider font-bold text-emerald-700 dark:text-emerald-300">Submitted</p>
                        <p class="text-2xl font-extrabold tabular-nums">{{ stats.submitted }}</p>
                    </div>
                    <div class="rounded-xl border border-amber-500/30 bg-amber-500/5 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-wider font-bold text-amber-700 dark:text-amber-300">In progress</p>
                        <p class="text-2xl font-extrabold tabular-nums">{{ stats.in_progress }}</p>
                    </div>
                    <div class="rounded-xl border border-rose-500/30 bg-rose-500/5 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-wider font-bold text-rose-700 dark:text-rose-300">Not started</p>
                        <p class="text-2xl font-extrabold tabular-nums">{{ stats.not_started }}</p>
                    </div>
                    <div class="rounded-xl border border-base-300 bg-base-100 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Overall</p>
                        <p class="text-2xl font-extrabold tabular-nums">{{ stats.pct }}%</p>
                        <div class="h-1.5 bg-base-200 rounded-full mt-1 overflow-hidden">
                            <div class="h-full bg-emerald-500 rounded-full transition-all"
                                :style="{ width: stats.pct + '%' }"></div>
                        </div>
                    </div>
                </div>

                <!-- View toggle -->
                <div class="flex gap-1 rounded-xl border border-base-300 bg-base-100 p-1">
                    <button @click="view = 'teachers'"
                        class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors"
                        :class="view === 'teachers' ? 'bg-primary text-primary-content' : 'text-base-content/55 hover:bg-base-200'">
                        <UserIcon class="inline w-3.5 h-3.5 mr-1" />
                        By teacher
                    </button>
                    <button @click="view = 'cells'"
                        class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-colors"
                        :class="view === 'cells' ? 'bg-primary text-primary-content' : 'text-base-content/55 hover:bg-base-200'">
                        <AcademicCapIcon class="inline w-3.5 h-3.5 mr-1" />
                        By class / paper
                    </button>
                </div>

                <!-- BY TEACHER -->
                <section v-if="view === 'teachers'" class="space-y-3">
                    <div v-if="!visibleTeachers.length"
                        class="rounded-2xl border border-base-300 bg-base-100 p-6 text-center text-sm text-base-content/55">
                        <template v-if="statusFilter === 'pending'">Every teacher has submitted — well done.</template>
                        <template v-else-if="statusFilter === 'completed'">No teachers have finished yet.</template>
                        <template v-else>No teachers to show.</template>
                    </div>
                    <div v-for="t in visibleTeachers" :key="t.teacher_id || t.teacher"
                        class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                        <header class="px-4 py-3 border-b border-base-300 flex items-center gap-3 bg-base-200/30">
                            <div class="w-9 h-9 rounded-full bg-base-300 flex items-center justify-center shrink-0">
                                <UserIcon class="w-4 h-4 text-base-content/55" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-sm truncate">{{ t.teacher }}</p>
                                <p class="text-[11px] text-base-content/55 tabular-nums">
                                    <span class="font-bold text-emerald-700 dark:text-emerald-300">{{ t.submitted + t.verified }}</span>
                                    <span class="text-base-content/45"> / {{ t.total }} done</span>
                                    <span v-if="t.in_progress" class="ml-2 text-amber-700 dark:text-amber-300 font-semibold">· {{ t.in_progress }} in progress</span>
                                    <span v-if="t.not_started" class="ml-2 text-rose-700 dark:text-rose-300 font-semibold">· {{ t.not_started }} not started</span>
                                </p>
                            </div>
                            <div class="w-24 shrink-0">
                                <div class="h-1.5 bg-base-200 rounded-full overflow-hidden">
                                    <div class="h-full transition-all"
                                        :class="t.submitted + t.verified === t.total ? 'bg-emerald-500' : 'bg-amber-500'"
                                        :style="{ width: ((t.submitted + t.verified) / Math.max(t.total, 1) * 100) + '%' }"></div>
                                </div>
                            </div>
                        </header>
                        <div class="divide-y divide-base-300">
                            <div v-for="c in t.cells" :key="c.subject_id + '-' + c.section_id"
                                class="px-4 py-2 flex items-center gap-3 text-sm hover:bg-base-200/30">
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold truncate">{{ c.subject }} <span class="text-base-content/45 font-mono text-xs">{{ c.subject_code }}</span></p>
                                    <p class="text-[11px] text-base-content/55">{{ c.class }} · Section {{ c.section }} · {{ c.students }} student(s) entered</p>
                                </div>
                                <span v-if="c.submitted_at" class="text-[11px] text-base-content/55 hidden sm:block">{{ c.submitted_at }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold ring-1 capitalize"
                                    :class="pill(c.status)">
                                    {{ pillLabel(c.status) }}
                                </span>
                                <a v-if="entryLink(c)" :href="entryLink(c)"
                                    class="btn btn-ghost btn-xs btn-square text-primary" title="Open marks entry">
                                    <PencilSquareIcon class="w-4 h-4" />
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- BY CELL (flat table) -->
                <section v-else class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                    <div v-if="!visibleCells.length" class="p-6 text-center text-sm text-base-content/55">
                        <template v-if="statusFilter === 'pending'">Nothing pending. Every paper has been submitted.</template>
                        <template v-else-if="statusFilter === 'completed'">No completed papers yet.</template>
                        <template v-else>No papers in this exam yet.</template>
                    </div>
                    <table v-else class="w-full text-sm">
                        <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider text-base-content/55">
                            <tr>
                                <th class="text-left px-3 py-2 font-bold">Class · Section</th>
                                <th class="text-left px-3 py-2 font-bold">Subject</th>
                                <th class="text-left px-3 py-2 font-bold">Teacher</th>
                                <th class="text-right px-3 py-2 font-bold">Entered</th>
                                <th class="text-center px-3 py-2 font-bold">Status</th>
                                <th class="text-right px-3 py-2 font-bold">Submitted</th>
                                <th class="text-right px-3 py-2 font-bold">Open</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-300">
                            <tr v-for="c in visibleCells" :key="c.subject_id + '-' + c.section_id" class="hover:bg-base-200/30">
                                <td class="px-3 py-2">{{ c.class }} · {{ c.section }}</td>
                                <td class="px-3 py-2 font-semibold">{{ c.subject }}</td>
                                <td class="px-3 py-2 text-base-content/70">{{ c.teacher || '— Unassigned —' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ c.students }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold ring-1 capitalize"
                                        :class="pill(c.status)">{{ pillLabel(c.status) }}</span>
                                </td>
                                <td class="px-3 py-2 text-right text-[11px] text-base-content/55">{{ c.submitted_at || '—' }}</td>
                                <td class="px-3 py-2 text-right">
                                    <a v-if="entryLink(c)" :href="entryLink(c)"
                                        class="btn btn-ghost btn-xs btn-square text-primary" title="Open marks entry">
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </template>
            <div v-else class="rounded-2xl border border-base-300 bg-base-100 p-8 text-center">
                <ChartBarIcon class="w-10 h-10 mx-auto text-base-content/30 mb-2" />
                <p class="text-sm text-base-content/55">Pick an exam above to see marks-entry progress.</p>
            </div>
        </div>
    </AppLayout>
</template>
