<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import FormSelect from '@/Components/FormSelect.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    TrophyIcon, DocumentTextIcon, ClipboardDocumentListIcon,
    ChartBarIcon, ArrowDownTrayIcon, ExclamationTriangleIcon,
    TableCellsIcon, UserGroupIcon, IdentificationIcon, PrinterIcon,
    AcademicCapIcon, DocumentDuplicateIcon, SparklesIcon, Squares2X2Icon,
    ArrowTopRightOnSquareIcon, PencilSquareIcon, DocumentChartBarIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exams: Array,
    classes: Array,
    sections: { type: Array, default: () => [] },
    students: { type: Array, default: () => [] },
    teachers: { type: Array, default: () => [] },
})

// ─── Report catalog (unchanged data; only the UX around it changes) ───
const reports = [
    { id: 'award-list', title: 'Award / Merit List', group: 'Student', icon: TrophyIcon, format: 'PDF',
      description: 'Top students ranked by percentage — for ceremony announcements.',
      route: 'reports.award-list', extra: [], routeFormat: (v) => [v.exam] },
    { id: 'report-card', title: 'Student Report Card', group: 'Student', icon: IdentificationIcon, format: 'PDF',
      description: 'Government-format card for one student. Open it from a section result sheet.',
      goTo: 'results.index', goToLabel: 'Open Results' },
    { id: 'progress-booklet', title: 'Student Progress Booklet', group: 'Student', icon: DocumentTextIcon, format: 'PDF',
      description: 'Multi-page PDF for one student: profile, all exams, subject trend.',
      route: 'reports.progress-booklet', extra: [{ key: 'student', label: 'Student', type: 'student' }],
      routeFormat: (v) => [v.student], examOptional: true },
    { id: 'result-sheet', title: 'Class Result Sheet', group: 'Class', icon: TableCellsIcon, format: 'PDF',
      description: 'Full class result with all marks, grades and ranks.',
      route: 'reports.result-sheet', extra: [{ key: 'class', label: 'Class', type: 'class' }],
      routeFormat: (v) => ({ exam: v.exam, schoolClass: v.class }) },
    { id: 'attendance-sheet', title: 'Exam Attendance Sheet', group: 'Class', icon: PencilSquareIcon, format: 'PDF',
      description: 'Per-subject sign-in sheet — one page per subject per section.',
      route: 'reports.attendance-sheet', extra: [{ key: 'class', label: 'Class', type: 'class' }],
      routeFormat: (v) => ({ exam: v.exam, schoolClass: v.class }) },
    { id: 'progress-booklet-bulk', title: 'Section Progress Booklets', group: 'Class', icon: DocumentDuplicateIcon, format: 'PDF',
      description: 'One PDF with a progress page per student — whole-class PTM run.',
      route: 'reports.progress-booklet-bulk', extra: [{ key: 'section', label: 'Section', type: 'section' }],
      routeFormat: (v) => [v.section], examOptional: true },
    { id: 'exam-analytics', title: 'Exam Analytics', group: 'Analytics', icon: ChartBarIcon, format: 'WEB',
      description: 'Pass rate by class & subject, grade distribution, top performers.',
      route: 'reports.exam-analytics', extra: [], routeFormat: (v) => [v.exam], self: true },
    { id: 'teacher-report-card', title: "Teacher's Report Card", group: 'Analytics', icon: AcademicCapIcon, format: 'WEB',
      description: 'One teacher across all sections: marks status, pass rate, average.',
      route: 'reports.teacher-report-card', extra: [{ key: 'user', label: 'Teacher', type: 'teacher' }],
      routeFormat: (v) => [v.user], self: true, examOptional: true },
    { id: 'export-results', title: 'Results Export (CSV)', group: 'Export', icon: DocumentDuplicateIcon, format: 'CSV',
      description: 'All exam results as CSV for Excel / backup.',
      route: 'reports.export', extra: [], routeFormat: (v) => ({ type: 'results', exam: v.exam }) },
    { id: 'export-marks', title: 'Raw Marks Export (CSV)', group: 'Export', icon: Squares2X2Icon, format: 'CSV',
      description: 'Every entered mark per subject per student — audit trail.',
      route: 'reports.export', extra: [], routeFormat: (v) => ({ type: 'marks', exam: v.exam }) },
    { id: 'export-award', title: 'Merit List Export (CSV)', group: 'Export', icon: SparklesIcon, format: 'CSV',
      description: 'Merit list as a spreadsheet for announcements.',
      route: 'reports.export', extra: [], routeFormat: (v) => ({ type: 'award-list', exam: v.exam }) },
]

const GROUPS = ['Student', 'Class', 'Analytics', 'Export']
const GROUP_META = {
    Student:   { icon: UserGroupIcon,             dot: 'bg-emerald-500', chip: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', label: 'Student reports' },
    Class:     { icon: AcademicCapIcon,           dot: 'bg-sky-500',     chip: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',         label: 'Class reports' },
    Analytics: { icon: ChartBarIcon,              dot: 'bg-violet-500',  chip: 'bg-violet-500/10 text-violet-600 dark:text-violet-400', label: 'Analytics' },
    Export:    { icon: ArrowDownTrayIcon,         dot: 'bg-amber-500',   chip: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',   label: 'Data exports' },
}

// ─── Shared context: pick the exam ONCE ───
const examId = ref(props.exams?.length === 1 ? props.exams[0].id : null)
const extra = ref({})       // per-report extra picks (class/section/student/teacher)
function extraVal(id) {
    if (!extra.value[id]) extra.value[id] = {}
    return extra.value[id]
}

const selectedExam = computed(() => props.exams?.find(e => e.id === examId.value) || null)

function optionsFor(type) {
    const src = type === 'class' ? props.classes
        : type === 'section' ? props.sections
        : type === 'student' ? props.students
        : type === 'teacher' ? props.teachers : []
    return (src || []).map(o => ({ value: o.id, label: o.name }))
}

function needsExam(r) {
    return !r.goTo && !r.examOptional
}
function ready(r) {
    if (r.goTo) return true
    if (needsExam(r) && !examId.value) return false
    return (r.extra || []).every(e => extraVal(r.id)[e.key])
}
function run(r) {
    if (r.goTo) return
    const values = { exam: examId.value, ...extraVal(r.id) }
    const url = route(r.route, r.routeFormat(values))
    r.self ? (window.location.href = url) : window.open(url, '_blank')
}

const search = ref('')
const visible = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return reports
    return reports.filter(r => r.title.toLowerCase().includes(q) || r.description.toLowerCase().includes(q))
})
function groupReports(g) {
    return visible.value.filter(r => r.group === g)
}
</script>

<template>
    <Head title="Reports" />
    <AppLayout :breadcrumbs="[{ label: 'Reports' }]">
        <div class="space-y-4 max-w-5xl mx-auto">

            <PageHeader title="Reports &amp; exports"
                subtitle="Pick an exam once, then generate any report with one click"
                :icon="DocumentChartBarIcon" tone="primary" />

            <!-- No exams -->
            <div v-if="!exams?.length"
                class="rounded-xl border border-amber-500/30 bg-amber-500/5 p-5 flex items-center gap-4">
                <ExclamationTriangleIcon class="w-8 h-8 text-amber-500 shrink-0" />
                <div class="flex-1">
                    <p class="font-bold text-sm">No exams with results yet</p>
                    <p class="text-xs text-base-content/60">Reports need an exam with marks/results first.</p>
                </div>
                <Link :href="route('results.index')" class="btn btn-warning btn-sm rounded-lg">Go to Results</Link>
            </div>

            <template v-else>
                <!-- ═══ Toolbar — exam (shared) + search, one refined bar ═══ -->
                <div class="rounded-xl border border-base-300 bg-base-100 shadow-sm p-4">
                    <div class="flex flex-col lg:flex-row lg:items-end gap-4">
                        <div class="flex-1 min-w-0">
                            <label class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-base-content/55 mb-1.5">
                                <span class="w-4 h-4 rounded-full bg-primary text-primary-content grid place-items-center text-[10px] font-bold">1</span>
                                Exam
                            </label>
                            <FormSelect v-model="examId"
                                :options="exams.map(e => ({ value: e.id, label: e.name }))"
                                placeholder="Select an exam…" size="sm" />
                        </div>
                        <div class="lg:w-72">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-base-content/55 mb-1.5">Search</label>
                            <input v-model="search" type="text" placeholder="Filter reports…"
                                class="input input-bordered input-sm w-full rounded-lg text-sm" />
                        </div>
                        <div class="pb-1.5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-semibold whitespace-nowrap"
                                :class="selectedExam
                                    ? 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300'
                                    : 'bg-base-200 text-base-content/55'">
                                <span class="w-1.5 h-1.5 rounded-full" :class="selectedExam ? 'bg-emerald-500' : 'bg-base-content/30'"></span>
                                {{ selectedExam ? 'Ready' : 'Pick an exam' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ═══ Report groups ═══ -->
                <div v-for="g in GROUPS" :key="g" v-show="groupReports(g).length"
                    class="rounded-xl border border-base-300 bg-base-100 shadow-sm overflow-hidden">
                    <header class="flex items-center gap-2 px-4 py-2.5 border-b border-base-300 bg-base-200/30">
                        <span class="w-1.5 h-1.5 rounded-full" :class="GROUP_META[g].dot"></span>
                        <h2 class="text-[11px] font-bold uppercase tracking-wider text-base-content/60">{{ GROUP_META[g].label }}</h2>
                        <span class="text-[11px] text-base-content/40 tabular-nums">{{ groupReports(g).length }}</span>
                    </header>
                    <div class="divide-y divide-base-300">
                        <div v-for="r in groupReports(g)" :key="r.id"
                            class="flex flex-col sm:flex-row sm:items-center gap-3 px-4 py-3.5 hover:bg-base-200/30 transition-colors">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" :class="GROUP_META[g].chip">
                                <component :is="r.icon" class="w-5 h-5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="font-bold text-sm leading-tight">{{ r.title }}</p>
                                    <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded"
                                        :class="r.format === 'PDF' ? 'bg-rose-500/12 text-rose-600 dark:text-rose-400'
                                            : r.format === 'CSV' ? 'bg-emerald-500/12 text-emerald-600 dark:text-emerald-400'
                                            : 'bg-sky-500/12 text-sky-600 dark:text-sky-400'">{{ r.format }}</span>
                                </div>
                                <p class="text-[12px] text-base-content/55 mt-0.5">{{ r.description }}</p>
                            </div>

                            <!-- extra input (only when the report needs more than the exam) -->
                            <div v-if="r.extra && r.extra.length" class="w-full sm:w-48 shrink-0">
                                <FormSelect v-for="e in r.extra" :key="e.key"
                                    v-model="extraVal(r.id)[e.key]"
                                    :options="optionsFor(e.type)"
                                    :placeholder="e.label" size="sm" />
                            </div>

                            <!-- action -->
                            <Link v-if="r.goTo" :href="route(r.goTo)"
                                class="btn btn-outline btn-sm rounded-lg gap-1.5 shrink-0 w-full sm:w-auto sm:min-w-[8.5rem]">
                                {{ r.goToLabel }} <ArrowTopRightOnSquareIcon class="w-3.5 h-3.5" />
                            </Link>
                            <button v-else @click="run(r)" :disabled="!ready(r)"
                                class="btn btn-primary btn-sm rounded-lg gap-1.5 shrink-0 w-full sm:w-auto sm:min-w-[8.5rem] disabled:opacity-45 disabled:cursor-not-allowed"
                                :title="!ready(r) ? (needsExam(r) && !examId ? 'Choose an exam first' : 'Fill the field first') : ''">
                                <component :is="r.format === 'CSV' ? ArrowDownTrayIcon : PrinterIcon" class="w-4 h-4" />
                                {{ r.format === 'WEB' ? 'Open' : r.format === 'CSV' ? 'Download' : 'Generate' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="!visible.length" class="rounded-xl border border-base-300 bg-base-100 shadow-sm p-10 text-center">
                    <p class="text-sm text-base-content/55">No reports match “{{ search }}”.</p>
                    <button @click="search = ''" class="btn btn-ghost btn-sm mt-3 rounded-lg">Clear search</button>
                </div>

                <p class="text-[11px] text-base-content/40 text-center pb-2">
                    PDFs open in a new tab to print or save · CSV opens in Excel · generate after results are finalized for accuracy.
                </p>
            </template>
        </div>
    </AppLayout>
</template>
