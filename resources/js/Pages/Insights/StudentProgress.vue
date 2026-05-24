<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import StatCard from '@/Components/StatCard.vue'
import { Head, router, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    ChartBarIcon, TrophyIcon, ArrowTrendingUpIcon, ArrowTrendingDownIcon,
    UserCircleIcon, MagnifyingGlassIcon, BookOpenIcon, CalendarIcon,
    BuildingLibraryIcon, FunnelIcon, ChevronDownIcon, XMarkIcon, ArrowRightIcon,
} from '@heroicons/vue/24/outline'
import { watch } from 'vue'
import { formatDate, formatNumber } from '@/Utils/format'

const props = defineProps({
    student: { type: Object, default: null },
    students: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    isSuperAdmin: { type: Boolean, default: false },
    trend: { type: Array, default: () => [] },
    subjectTrend: { type: Array, default: () => [] },
    summary: { type: Object, default: null },
})

// ─── Picker state — search + cascading filters (school → class → section) ───
const search = ref('')
const schoolId = ref('')
const classId = ref('')
const sectionId = ref('')

// Cascade: child filter options narrow when parent is set
const visibleClasses = computed(() => {
    if (!schoolId.value) return props.classes
    return props.classes.filter(c => Number(c.school_id) === Number(schoolId.value))
})
const visibleSections = computed(() => {
    if (!classId.value) return props.sections
    return props.sections.filter(s => Number(s.school_class_id) === Number(classId.value))
})

// Reset child selections when parent changes (a class from school A doesn't
// belong to school B once you switch schools).
watch(schoolId, () => { classId.value = ''; sectionId.value = '' })
watch(classId, () => { sectionId.value = '' })

const activeFilterCount = computed(() =>
    [schoolId.value, classId.value, sectionId.value].filter(Boolean).length
)
const filtersOpen = ref(false)

function clearFilters() {
    schoolId.value = ''
    classId.value = ''
    sectionId.value = ''
}

// Apply search + all picker filters in one pass
const filteredStudents = computed(() => {
    const q = search.value.trim().toLowerCase()
    return props.students.filter((s) => {
        if (q && !(
            (s.name || '').toLowerCase().includes(q) ||
            (s.admission_no || '').toLowerCase().includes(q) ||
            (s.roll_no || '').toString().toLowerCase().includes(q)
        )) return false
        if (schoolId.value && Number(s.school_id) !== Number(schoolId.value)) return false
        if (classId.value && Number(s.school_class_id) !== Number(classId.value)) return false
        if (sectionId.value && Number(s.section_id) !== Number(sectionId.value)) return false
        return true
    })
})

function pickStudent(s) {
    router.get(route('insights.student-progress'), { student_id: s.id }, { preserveState: false })
}

function changeStudent() {
    router.get(route('insights.student-progress'))
}

function pctColor(p) {
    if (p >= 80) return 'bg-success'
    if (p >= 60) return 'bg-info'
    if (p >= 40) return 'bg-warning'
    return 'bg-error'
}

function pctTextColor(p) {
    if (p >= 80) return 'text-success'
    if (p >= 60) return 'text-info'
    if (p >= 40) return 'text-warning'
    return 'text-error'
}

function gradeBadge(grade) {
    if (!grade) return 'badge-ghost'
    const g = String(grade).toUpperCase()
    if (g.startsWith('A')) return 'badge-success'
    if (g.startsWith('B')) return 'badge-info'
    if (g.startsWith('C')) return 'badge-warning'
    return 'badge-error'
}

const maxPct = computed(() => {
    const m = Math.max(...props.trend.map((t) => Number(t.percentage) || 0), 1)
    return m < 10 ? 100 : 100
})
</script>

<template>
    <Head title="Student Progress" />
    <AppLayout :breadcrumbs="[{ label: 'Insights', href: '/insights/student-progress' }, { label: 'Student Progress' }]">
        <div class="space-y-6">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight">Student Progress</h1>
                    <p class="text-sm text-base-content/55 mt-0.5">Year-over-year performance across all exams</p>
                </div>
                <button v-if="student" class="btn btn-ghost btn-sm gap-1.5" @click="changeStudent">
                    <UserCircleIcon class="h-4 w-4" /> Change Student
                </button>
            </div>

            <!-- ════════ STUDENT PICKER ════════ -->
            <section v-if="!student" class="surface overflow-hidden">
                <header class="surface-header">
                    <div class="relative flex-1 max-w-md">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-base-content/40" />
                        <input v-model="search" type="text"
                            placeholder="Search by name, admission no, roll…"
                            class="input input-bordered input-sm w-full pl-9 text-sm" />
                    </div>
                    <button type="button" @click="filtersOpen = !filtersOpen"
                        class="btn btn-sm gap-1.5"
                        :class="filtersOpen ? 'btn-primary' : 'btn-outline'">
                        <FunnelIcon class="w-4 h-4" /> Filters
                        <span v-if="activeFilterCount > 0" class="badge badge-sm badge-warning text-warning-content tabular-nums">{{ activeFilterCount }}</span>
                        <ChevronDownIcon class="w-3.5 h-3.5 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" />
                    </button>
                </header>

                <Transition name="filter-panel">
                    <div v-if="filtersOpen" class="border-b border-base-200 bg-base-200/30 px-5 sm:px-6 py-4 space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div v-if="isSuperAdmin">
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/60 mb-1.5 flex items-center gap-1">
                                    <BuildingLibraryIcon class="w-3 h-3" /> School
                                </label>
                                <SearchableSelect v-model="schoolId" size="sm"
                                    :options="[{ value: '', label: 'All schools' }, ...(schools || []).map(s => ({ value: s.id, label: s.name }))]"
                                    placeholder="All schools" />
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/60 mb-1.5 block">Class</label>
                                <SearchableSelect v-model="classId" size="sm"
                                    :options="[{ value: '', label: 'All classes' }, ...(visibleClasses || []).map(c => ({ value: c.id, label: c.name }))]"
                                    placeholder="All classes" />
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/60 mb-1.5 block">
                                    Section
                                    <span v-if="!classId" class="text-base-content/40 normal-case font-medium">· pick a class first</span>
                                </label>
                                <SearchableSelect v-model="sectionId" size="sm"
                                    :options="[{ value: '', label: 'All sections' }, ...(visibleSections || []).map(s => ({ value: s.id, label: s.name }))]"
                                    placeholder="All sections"
                                    :disabled="!visibleSections.length" />
                            </div>
                        </div>
                        <div v-if="activeFilterCount > 0" class="flex items-center justify-between gap-2 pt-2 border-t border-base-200">
                            <span class="text-xs text-base-content/55">
                                <span class="font-bold text-base-content">{{ activeFilterCount }}</span>
                                filter{{ activeFilterCount === 1 ? '' : 's' }} applied
                                · {{ filteredStudents.length }} student{{ filteredStudents.length === 1 ? '' : 's' }} match
                            </span>
                            <button type="button" @click="clearFilters" class="btn btn-ghost btn-xs gap-1 text-base-content/65">
                                <XMarkIcon class="w-3.5 h-3.5" /> Clear all
                            </button>
                        </div>
                    </div>
                </Transition>

                <!-- Result count strip -->
                <div class="px-5 sm:px-6 py-3 border-b border-base-200 flex items-center justify-between text-xs text-base-content/55">
                    <span>
                        <span class="font-bold text-base-content tabular-nums">{{ filteredStudents.length }}</span>
                        of {{ students.length }} students
                        <span v-if="search">matching "{{ search }}"</span>
                    </span>
                    <span v-if="students.length >= 500" class="text-warning font-semibold">
                        Showing first 500 — narrow with filters for older entries
                    </span>
                </div>

                <div v-if="!filteredStudents.length" class="py-16 text-center">
                    <UserCircleIcon class="w-12 h-12 text-base-content/20 mx-auto mb-2" />
                    <p class="text-sm font-medium text-base-content/55">No students match your filters</p>
                    <p class="text-xs text-base-content/40 mt-1">Try a different search term or clear the filters.</p>
                </div>

                <!-- Picker table — sticky header so headers don't scroll away -->
                <div v-else class="table-sticky-wrap" style="--table-max-h: 60vh;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th class="hidden sm:table-cell">Adm. No</th>
                                <th class="hidden md:table-cell">Roll</th>
                                <th v-if="isSuperAdmin" class="hidden lg:table-cell">School</th>
                                <th>Class / Section</th>
                                <th class="text-right"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in filteredStudents" :key="s.id"
                                class="cursor-pointer"
                                @click="pickStudent(s)">
                                <td>
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-500 to-emerald-700 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">
                                            {{ s.name?.charAt(0)?.toUpperCase() || '?' }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-sm truncate">{{ s.name }}</div>
                                            <div class="text-[10px] text-base-content/55 sm:hidden mt-0.5 truncate">
                                                {{ s.admission_no }} · {{ s.class_name }}<span v-if="s.section_name">-{{ s.section_name }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden sm:table-cell"><span class="font-mono text-xs text-base-content/75">{{ s.admission_no || '—' }}</span></td>
                                <td class="hidden md:table-cell text-[13px] text-base-content/75 tabular-nums">{{ s.roll_no || '—' }}</td>
                                <td v-if="isSuperAdmin" class="hidden lg:table-cell text-[13px] text-base-content/75 truncate max-w-[180px]" :title="s.school_name">
                                    <div class="inline-flex items-center gap-1.5">
                                        <BuildingLibraryIcon class="w-3.5 h-3.5 text-base-content/40 flex-shrink-0" />
                                        <span class="truncate">{{ s.school_name || '—' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-outline badge-sm font-bold">{{ s.class_name || '—' }}</span>
                                    <span v-if="s.section_name" class="badge badge-ghost badge-sm ml-1">{{ s.section_name }}</span>
                                </td>
                                <td class="text-right">
                                    <ArrowRightIcon class="w-4 h-4 text-base-content/30" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Student Info -->
            <div v-if="student" class="card-section">
                <div class="card-content flex items-center gap-4">
                    <div class="avatar-initial h-14 w-14">
                        {{ student.name?.charAt(0)?.toUpperCase() || '?' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-bold">{{ student.name }}</h2>
                        <p class="text-xs text-base-content/50">
                            <span v-if="student.admission_no">Adm: {{ student.admission_no }} &middot; </span>
                            <span v-if="student.roll_no">Roll: {{ student.roll_no }} &middot; </span>
                            <span v-if="student.school_name">{{ student.school_name }} &middot; </span>
                            {{ student.class_name }}<span v-if="student.section_name"> - {{ student.section_name }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div v-if="student && summary" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard title="Total Exams" :value="summary.total_exams" subtitle="Across all sessions" color="primary">
                    <template #icon><CalendarIcon class="h-5 w-5" /></template>
                </StatCard>
                <StatCard title="Avg Percentage" :value="`${summary.avg_percentage}%`" subtitle="Across all exams" color="info">
                    <template #icon><ChartBarIcon class="h-5 w-5" /></template>
                </StatCard>
                <StatCard
                    title="Best Score"
                    :value="summary.best_exam ? `${Number(summary.best_exam.percentage).toFixed(2)}%` : '-'"
                    :subtitle="summary.best_exam ? summary.best_exam.exam_name : ''"
                    color="success"
                >
                    <template #icon><TrophyIcon class="h-5 w-5" /></template>
                </StatCard>
                <StatCard
                    title="Improvement"
                    :value="`${summary.improvement_delta >= 0 ? '+' : ''}${summary.improvement_delta}%`"
                    subtitle="From first to latest"
                    :color="summary.improvement_delta >= 0 ? 'success' : 'error'"
                >
                    <template #icon>
                        <ArrowTrendingUpIcon v-if="summary.improvement_delta >= 0" class="h-5 w-5" />
                        <ArrowTrendingDownIcon v-else class="h-5 w-5" />
                    </template>
                </StatCard>
            </div>

            <!-- Main Trend Chart -->
            <div v-if="student && trend.length" class="card-section">
                <div class="card-content space-y-4">
                    <div class="flex items-center gap-3 border-b border-base-200 pb-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-info/10 text-info">
                            <ArrowTrendingUpIcon class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold">Exam Trend</h3>
                            <p class="text-xs text-base-content/50">Percentage across all exams (oldest to latest)</p>
                        </div>
                    </div>

                    <div class="flex h-48 items-end gap-2">
                        <div
                            v-for="(t, i) in trend"
                            :key="i"
                            class="group flex flex-1 flex-col items-center gap-1.5"
                        >
                            <span class="text-2xs font-bold opacity-0 transition group-hover:opacity-100" :class="pctTextColor(t.percentage)">
                                {{ Number(t.percentage).toFixed(1) }}%
                            </span>
                            <div
                                class="w-full rounded-t-lg transition-all"
                                :class="pctColor(t.percentage)"
                                :style="{ height: `${Math.max((t.percentage / maxPct) * 100, 2)}%`, minHeight: '4px' }"
                                :title="`${t.exam_name}: ${Number(t.percentage).toFixed(2)}%`"
                            />
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <div v-for="(t, i) in trend" :key="i" class="flex flex-1 flex-col items-center gap-0.5 text-center">
                            <span class="truncate text-2xs font-medium text-base-content/60" :title="t.exam_name">{{ t.exam_name }}</span>
                            <span class="truncate text-[10px] text-base-content/40">{{ t.session_name }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subject Trend Cards -->
            <div v-if="student && subjectTrend.length" class="card-section">
                <div class="card-content space-y-4">
                    <div class="flex items-center gap-3 border-b border-base-200 pb-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-secondary/10 text-secondary">
                            <BookOpenIcon class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold">Subject Trends</h3>
                            <p class="text-xs text-base-content/50">Performance by subject over time</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="st in subjectTrend"
                            :key="st.subject_id"
                            class="rounded-xl border border-base-200 bg-base-100 p-4"
                        >
                            <p class="truncate text-sm font-semibold">{{ st.subject_name }}</p>
                            <p class="text-xs text-base-content/50">{{ st.dataPoints.length }} data points</p>

                            <div class="mt-3 flex h-20 items-end gap-1">
                                <div
                                    v-for="(d, i) in st.dataPoints"
                                    :key="i"
                                    class="group flex flex-1 flex-col items-center"
                                    :title="`${d.exam}: ${Number(d.percentage).toFixed(2)}%`"
                                >
                                    <div
                                        class="w-full rounded-t"
                                        :class="pctColor(d.percentage)"
                                        :style="{ height: `${Math.max(Number(d.percentage), 2)}%`, minHeight: '4px' }"
                                    />
                                </div>
                            </div>
                            <div class="mt-2 flex justify-between text-2xs text-base-content/40">
                                <span>{{ st.dataPoints[0]?.exam || '' }}</span>
                                <span>{{ st.dataPoints[st.dataPoints.length - 1]?.exam || '' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Breakdown Table -->
            <div v-if="student && trend.length" class="card-section">
                <div class="card-content space-y-4">
                    <div class="flex items-center gap-3 border-b border-base-200 pb-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-accent/10 text-accent">
                            <ChartBarIcon class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold">Exam-by-Exam Breakdown</h3>
                            <p class="text-xs text-base-content/50">Detailed results history</p>
                        </div>
                    </div>

                    <div class="table-sticky-wrap" style="--table-max-h: 60vh;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Session</th>
                                    <th>Exam</th>
                                    <th>Date</th>
                                    <th class="text-right">Obtained</th>
                                    <th class="text-right">%</th>
                                    <th>Grade</th>
                                    <th class="text-right">Position</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(t, i) in trend" :key="i">
                                    <td class="text-[12px] text-base-content/75">{{ t.session_name }}</td>
                                    <td class="text-[13px] font-bold">{{ t.exam_name }}</td>
                                    <td class="text-[12px] text-base-content/65 whitespace-nowrap tabular-nums">{{ formatDate(t.exam_date) || '—' }}</td>
                                    <td class="text-right text-[12px] font-mono tabular-nums">{{ formatNumber(t.obtained_marks, { decimals: 0 }) }} / {{ formatNumber(t.total_marks, { decimals: 0 }) }}</td>
                                    <td class="text-right text-[13px] font-bold tabular-nums" :class="pctTextColor(t.percentage)">
                                        {{ Number(t.percentage).toFixed(2) }}%
                                    </td>
                                    <td><span class="badge badge-sm" :class="gradeBadge(t.grade)">{{ t.grade || '—' }}</span></td>
                                    <td class="text-right text-[12px] font-mono tabular-nums">{{ t.position || '—' }}</td>
                                    <td>
                                        <span class="badge badge-sm" :class="t.is_passed ? 'badge-success' : 'badge-error'">
                                            {{ t.is_passed ? 'Passed' : 'Failed' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div v-if="student && !trend.length" class="card-section">
                <div class="card-content py-16 text-center">
                    <p class="text-sm text-base-content/50">No results available for this student yet.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.filter-panel-enter-active,
.filter-panel-leave-active {
    transition: opacity 0.2s ease, max-height 0.25s ease;
    overflow: hidden;
}
.filter-panel-enter-from,
.filter-panel-leave-to {
    opacity: 0;
    max-height: 0;
}
.filter-panel-enter-to,
.filter-panel-leave-from {
    opacity: 1;
    max-height: 400px;
}
</style>
