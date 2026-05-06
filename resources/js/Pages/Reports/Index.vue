<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormSelect from '@/Components/FormSelect.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    TrophyIcon, DocumentTextIcon, ClipboardDocumentListIcon,
    ChartBarIcon, ArrowDownTrayIcon, ExclamationTriangleIcon,
    MagnifyingGlassIcon, DocumentChartBarIcon, TableCellsIcon,
    UserGroupIcon, IdentificationIcon, PrinterIcon, EyeIcon,
    AcademicCapIcon, BookOpenIcon, ListBulletIcon,
    DocumentDuplicateIcon, SparklesIcon, Squares2X2Icon,
    ArrowTopRightOnSquareIcon, CheckCircleIcon, ClockIcon,
    PencilSquareIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exams: Array,
    classes: Array,
})

const search = ref('')
const activeCategory = ref('all')

// Report catalog — each report is a defined item with metadata
const reports = computed(() => [
    {
        id: 'award-list',
        title: 'Award / Merit List',
        description: 'Top-performing students ranked by percentage. Perfect for ceremony announcements.',
        category: 'students',
        categoryLabel: 'Student Reports',
        icon: TrophyIcon,
        color: 'warning',
        format: 'PDF',
        inputs: [{ key: 'exam', label: 'Exam', type: 'exam', required: true }],
        route: 'reports.award-list',
        routeFormat: (values) => [values.exam],
    },
    {
        id: 'result-sheet',
        title: 'Class Result Sheet',
        description: 'Complete class-wise result sheet with all marks, grades, and ranks in tabular format.',
        category: 'classes',
        categoryLabel: 'Class Reports',
        icon: TableCellsIcon,
        color: 'primary',
        format: 'PDF',
        inputs: [
            { key: 'exam', label: 'Exam', type: 'exam', required: true },
            { key: 'class', label: 'Class', type: 'class', required: true },
        ],
        route: 'reports.result-sheet',
        routeFormat: (values) => ({ exam: values.exam, schoolClass: values.class }),
    },
    {
        id: 'attendance-sheet',
        title: 'Exam Attendance Sheet',
        description: 'Per-subject sign-in sheet — students sign next to their roll/name as they enter the exam room. One page per subject per section.',
        category: 'classes',
        categoryLabel: 'Class Reports',
        icon: PencilSquareIcon,
        color: 'accent',
        format: 'PDF',
        inputs: [
            { key: 'exam', label: 'Exam', type: 'exam', required: true },
            { key: 'class', label: 'Class', type: 'class', required: true },
        ],
        route: 'reports.attendance-sheet',
        routeFormat: (values) => ({ exam: values.exam, schoolClass: values.class }),
    },
    {
        id: 'report-card',
        title: 'Student Report Card',
        description: 'Individual, government-format report card for each student with full marks breakdown.',
        category: 'students',
        categoryLabel: 'Student Reports',
        icon: IdentificationIcon,
        color: 'success',
        format: 'PDF',
        inputs: [],
        howTo: [
            'Go to Results → View any exam',
            'Open the section result sheet',
            'Click a student\'s name to download their report card',
        ],
    },
    {
        id: 'export-results',
        title: 'Results Export',
        description: 'Download complete exam results as CSV for Excel analysis, backup, or external use.',
        category: 'exports',
        categoryLabel: 'Data Exports',
        icon: DocumentDuplicateIcon,
        color: 'info',
        format: 'CSV',
        inputs: [{ key: 'exam', label: 'Exam', type: 'exam', required: true }],
        route: 'reports.export',
        routeFormat: (values) => ({ type: 'results', exam: values.exam }),
    },
    {
        id: 'export-marks',
        title: 'Raw Marks Export',
        description: 'Export all entered marks per subject per student. Useful for audit trails.',
        category: 'exports',
        categoryLabel: 'Data Exports',
        icon: Squares2X2Icon,
        color: 'secondary',
        format: 'CSV',
        inputs: [{ key: 'exam', label: 'Exam', type: 'exam', required: true }],
        route: 'reports.export',
        routeFormat: (values) => ({ type: 'marks', exam: values.exam }),
    },
    {
        id: 'export-award',
        title: 'Merit List Export',
        description: 'Download the merit list in spreadsheet format for announcements or certificates.',
        category: 'exports',
        categoryLabel: 'Data Exports',
        icon: SparklesIcon,
        color: 'accent',
        format: 'CSV',
        inputs: [{ key: 'exam', label: 'Exam', type: 'exam', required: true }],
        route: 'reports.export',
        routeFormat: (values) => ({ type: 'award-list', exam: values.exam }),
    },
])

const categories = [
    { key: 'all', label: 'All Reports', icon: Squares2X2Icon, count: computed(() => reports.value.length) },
    { key: 'students', label: 'Student Reports', icon: UserGroupIcon, count: computed(() => reports.value.filter(r => r.category === 'students').length) },
    { key: 'classes', label: 'Class Reports', icon: AcademicCapIcon, count: computed(() => reports.value.filter(r => r.category === 'classes').length) },
    { key: 'exports', label: 'Data Exports', icon: ArrowDownTrayIcon, count: computed(() => reports.value.filter(r => r.category === 'exports').length) },
]

const filteredReports = computed(() => {
    let items = reports.value
    if (activeCategory.value !== 'all') {
        items = items.filter(r => r.category === activeCategory.value)
    }
    if (search.value.trim()) {
        const q = search.value.toLowerCase()
        items = items.filter(r => r.title.toLowerCase().includes(q) || r.description.toLowerCase().includes(q))
    }
    return items
})

// Per-report input state
const reportInputs = ref({})
function getInputs(reportId) {
    if (!reportInputs.value[reportId]) reportInputs.value[reportId] = {}
    return reportInputs.value[reportId]
}
function canGenerate(report) {
    const vals = getInputs(report.id)
    return report.inputs.every(inp => !inp.required || vals[inp.key])
}

function generate(report) {
    if (!report.route) return
    const values = getInputs(report.id)
    const params = report.routeFormat(values)
    window.open(route(report.route, params), '_blank')
}

// Stats
const totalExams = computed(() => props.exams?.length || 0)
const completedExams = computed(() => props.exams?.filter(e => e.status === 'completed').length || 0)
</script>

<template>
    <Head title="Reports & Exports" />
    <AppLayout :breadcrumbs="[{ label: 'Reports' }]">
        <div class="space-y-6">

            <!-- ============ HEADER ============ -->
            <div class="page-header">
                <div>
                    <h1 class="page-title flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-secondary shadow-lg shadow-primary/25">
                            <DocumentChartBarIcon class="h-5 w-5 text-white" />
                        </div>
                        Reports & Exports
                    </h1>
                    <p class="page-subtitle">Generate official PDF reports and data exports for any exam</p>
                </div>
                <div class="hidden sm:flex items-center gap-2">
                    <Link :href="route('exams.index')" class="btn btn-ghost btn-sm gap-1.5">
                        <ClipboardDocumentListIcon class="w-4 h-4" /> Back to Exams
                    </Link>
                </div>
            </div>

            <!-- ============ NO EXAMS WARNING ============ -->
            <div v-if="!exams?.length" class="alert-banner alert-banner-warning">
                <ExclamationTriangleIcon class="w-5 h-5 shrink-0 text-warning" />
                <div class="flex-1">
                    <p class="font-semibold">No exams with results found</p>
                    <p class="mt-0.5 text-xs text-base-content/60">Generate results for an exam before creating reports. Reports require completed or in-progress exams.</p>
                </div>
                <Link :href="route('results.index')" class="btn btn-warning btn-sm">Go to Results</Link>
            </div>

            <!-- ============ QUICK STATS ============ -->
            <div v-if="exams?.length" class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <div class="stat-card">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10">
                            <DocumentTextIcon class="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-base-content/45">Reports Available</p>
                            <p class="mt-0.5 text-2xl font-extrabold text-primary">{{ reports.length }}</p>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-secondary/10">
                            <ClipboardDocumentListIcon class="h-5 w-5 text-secondary" />
                        </div>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-base-content/45">Total Exams</p>
                            <p class="mt-0.5 text-2xl font-extrabold text-secondary">{{ totalExams }}</p>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-success/10">
                            <CheckCircleIcon class="h-5 w-5 text-success" />
                        </div>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-base-content/45">Completed</p>
                            <p class="mt-0.5 text-2xl font-extrabold text-success">{{ completedExams }}</p>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-accent/10">
                            <AcademicCapIcon class="h-5 w-5 text-accent" />
                        </div>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-base-content/45">Classes</p>
                            <p class="mt-0.5 text-2xl font-extrabold text-accent">{{ classes?.length || 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ FILTER BAR ============ -->
            <div v-if="exams?.length" class="surface">
                <div class="surface-body">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <!-- Category tabs -->
                        <div class="flex flex-wrap items-center gap-1.5 rounded-xl bg-base-200/50 p-1">
                            <button
                                v-for="cat in categories"
                                :key="cat.key"
                                @click="activeCategory = cat.key"
                                class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold"
                                :class="activeCategory === cat.key
                                    ? 'bg-base-100 shadow-sm text-primary'
                                    : 'text-base-content/55 hover:text-base-content'"
                                style="transition: all 0.15s cubic-bezier(0.16, 1, 0.3, 1);"
                            >
                                <component :is="cat.icon" class="w-3.5 h-3.5" />
                                {{ cat.label }}
                                <span class="ml-0.5 rounded-md bg-base-200/70 px-1.5 py-0.5 text-[10px] font-bold">
                                    {{ cat.count.value }}
                                </span>
                            </button>
                        </div>
                        <!-- Search -->
                        <div class="relative flex-1">
                            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-base-content/35" />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search reports..."
                                class="input input-bordered w-full pl-9 text-sm"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ REPORTS GRID ============ -->
            <div v-if="exams?.length && filteredReports.length" class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="(report, idx) in filteredReports"
                    :key="report.id"
                    class="surface group relative overflow-hidden"
                    :style="`animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: ${idx * 40}ms;`"
                >
                    <!-- Format badge -->
                    <div class="absolute right-4 top-4 z-10">
                        <span class="badge badge-sm font-bold" :class="report.format === 'PDF' ? 'badge-error' : 'badge-success'">
                            {{ report.format }}
                        </span>
                    </div>

                    <div class="surface-body space-y-4">
                        <!-- Icon + title -->
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl transition-transform group-hover:scale-110"
                                :class="{
                                    'bg-warning/10': report.color === 'warning',
                                    'bg-primary/10': report.color === 'primary',
                                    'bg-secondary/10': report.color === 'secondary',
                                    'bg-accent/10': report.color === 'accent',
                                    'bg-success/10': report.color === 'success',
                                    'bg-info/10': report.color === 'info',
                                }"
                            >
                                <component
                                    :is="report.icon"
                                    class="h-6 w-6"
                                    :class="{
                                        'text-warning': report.color === 'warning',
                                        'text-primary': report.color === 'primary',
                                        'text-secondary': report.color === 'secondary',
                                        'text-accent': report.color === 'accent',
                                        'text-success': report.color === 'success',
                                        'text-info': report.color === 'info',
                                    }"
                                />
                            </div>
                            <div class="flex-1 min-w-0 pt-1">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-base-content/45">{{ report.categoryLabel }}</p>
                                <h3 class="mt-0.5 text-[15px] font-bold leading-tight">{{ report.title }}</h3>
                            </div>
                        </div>

                        <p class="text-[13px] leading-relaxed text-base-content/60">{{ report.description }}</p>

                        <!-- Inputs or how-to -->
                        <div v-if="report.inputs.length" class="space-y-2.5">
                            <div v-for="inp in report.inputs" :key="inp.key">
                                <FormSelect
                                    v-model="getInputs(report.id)[inp.key]"
                                    :label="inp.label"
                                    :options="inp.type === 'exam'
                                        ? (exams?.map(e => ({ value: e.id, label: e.name })) || [])
                                        : (classes?.map(c => ({ value: c.id, label: c.name })) || [])"
                                    :placeholder="`Select ${inp.label.toLowerCase()}`"
                                    :required="inp.required"
                                    size="sm"
                                />
                            </div>
                        </div>

                        <!-- How to for Report Card -->
                        <div v-else-if="report.howTo" class="rounded-xl border border-base-200 bg-base-200/30 p-3.5">
                            <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-base-content/50">How to generate:</p>
                            <ol class="space-y-1.5">
                                <li v-for="(step, i) in report.howTo" :key="i" class="flex gap-2 text-xs text-base-content/65">
                                    <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-primary/15 text-[9px] font-bold text-primary">{{ i + 1 }}</span>
                                    <span>{{ step }}</span>
                                </li>
                            </ol>
                        </div>

                        <!-- Action -->
                        <div v-if="report.inputs.length" class="pt-1">
                            <button
                                @click="generate(report)"
                                :disabled="!canGenerate(report)"
                                class="btn btn-primary btn-sm w-full gap-1.5"
                            >
                                <component :is="report.format === 'PDF' ? PrinterIcon : ArrowDownTrayIcon" class="w-4 h-4" />
                                {{ report.format === 'PDF' ? 'Generate PDF' : 'Download CSV' }}
                                <ArrowTopRightOnSquareIcon class="w-3 h-3 opacity-60" />
                            </button>
                        </div>
                        <Link
                            v-else
                            :href="route('results.index')"
                            class="btn btn-outline btn-sm w-full gap-1.5"
                        >
                            <EyeIcon class="w-4 h-4" /> Go to Results
                        </Link>
                    </div>
                </article>
            </div>

            <!-- ============ EMPTY SEARCH ============ -->
            <div v-else-if="exams?.length && !filteredReports.length" class="surface">
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <MagnifyingGlassIcon class="h-7 w-7 text-base-content/30" />
                    </div>
                    <h3 class="text-base font-bold">No reports match your search</h3>
                    <p class="mt-1.5 text-sm text-base-content/55">Try adjusting your filters or search query.</p>
                    <button @click="search = ''; activeCategory = 'all'" class="btn btn-ghost btn-sm mt-4">Clear filters</button>
                </div>
            </div>

            <!-- ============ HELP / TIPS ============ -->
            <div v-if="exams?.length" class="rounded-2xl border border-info/15 bg-gradient-to-br from-info/5 to-primary/5 p-5">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-info/15">
                        <SparklesIcon class="h-5 w-5 text-info" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-bold">Report Generation Tips</h3>
                        <div class="mt-2 grid grid-cols-1 gap-2 text-[12.5px] text-base-content/65 md:grid-cols-2">
                            <div class="flex items-start gap-2">
                                <span class="text-success">✓</span>
                                <span>PDFs open in a new tab — print or download from your browser.</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-success">✓</span>
                                <span>CSV exports open directly in Excel, Numbers, or Google Sheets.</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-success">✓</span>
                                <span>Generate reports only after results are finalized for accuracy.</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-success">✓</span>
                                <span>Custom letterhead templates available under Master Data.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
