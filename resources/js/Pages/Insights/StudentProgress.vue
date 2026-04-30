<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import { Head, router, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    ChartBarIcon, TrophyIcon, ArrowTrendingUpIcon, ArrowTrendingDownIcon,
    UserCircleIcon, MagnifyingGlassIcon, BookOpenIcon, CalendarIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    student: { type: Object, default: null },
    students: { type: Array, default: () => [] },
    trend: { type: Array, default: () => [] },
    subjectTrend: { type: Array, default: () => [] },
    summary: { type: Object, default: null },
})

const search = ref('')
const filteredStudents = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return props.students
    return props.students.filter((s) =>
        (s.name || '').toLowerCase().includes(q) ||
        (s.admission_no || '').toLowerCase().includes(q) ||
        (s.class_name || '').toLowerCase().includes(q),
    )
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
            <div class="page-header">
                <div>
                    <h1 class="page-title">Student Progress</h1>
                    <p class="page-subtitle">Year-over-year performance across all exams</p>
                </div>
                <button v-if="student" class="btn btn-ghost btn-sm" @click="changeStudent">
                    <UserCircleIcon class="h-4 w-4" />
                    Change Student
                </button>
            </div>

            <!-- Student Picker -->
            <div v-if="!student" class="card-section">
                <div class="card-content space-y-4">
                    <div class="flex items-center gap-3 border-b border-base-200 pb-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <MagnifyingGlassIcon class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold">Pick a Student</h3>
                            <p class="text-xs text-base-content/50">Search from the list below</p>
                        </div>
                    </div>

                    <div class="relative">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-base-content/40" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search by name, admission no, class..."
                            class="input input-bordered w-full pl-9"
                        />
                    </div>

                    <div v-if="!filteredStudents.length" class="py-10 text-center text-sm text-base-content/40">
                        No students found.
                    </div>
                    <div v-else class="max-h-[500px] overflow-y-auto">
                        <ul class="space-y-1.5">
                            <li v-for="s in filteredStudents" :key="s.id">
                                <button
                                    class="flex w-full items-center gap-3 rounded-xl border border-base-200 bg-base-100 px-4 py-3 text-left transition-all hover:border-primary/40 hover:bg-primary/5"
                                    @click="pickStudent(s)"
                                >
                                    <div class="avatar-initial h-10 w-10 text-xs">
                                        {{ s.name?.charAt(0)?.toUpperCase() || '?' }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold">{{ s.name }}</p>
                                        <p class="text-xs text-base-content/50">
                                            <span v-if="s.admission_no">{{ s.admission_no }} &middot; </span>
                                            <span v-if="s.school_name">{{ s.school_name }} &middot; </span>
                                            {{ s.class_name }}<span v-if="s.section_name"> - {{ s.section_name }}</span>
                                        </p>
                                    </div>
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

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

                    <div class="overflow-x-auto">
                        <table class="table-clean">
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
                                    <td class="text-xs">{{ t.session_name }}</td>
                                    <td class="text-xs font-semibold">{{ t.exam_name }}</td>
                                    <td class="text-xs text-base-content/60">{{ t.exam_date || '-' }}</td>
                                    <td class="text-right text-xs">{{ Number(t.obtained_marks).toFixed(0) }} / {{ Number(t.total_marks).toFixed(0) }}</td>
                                    <td class="text-right text-xs font-bold" :class="pctTextColor(t.percentage)">
                                        {{ Number(t.percentage).toFixed(2) }}%
                                    </td>
                                    <td><span class="badge badge-sm" :class="gradeBadge(t.grade)">{{ t.grade || '-' }}</span></td>
                                    <td class="text-right text-xs">{{ t.position || '-' }}</td>
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
