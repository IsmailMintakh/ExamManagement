<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    ArrowDownTrayIcon, CheckCircleIcon, XCircleIcon,
    TrophyIcon, ChartBarIcon, AcademicCapIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    result: Object,
    exam: Object,
    subjects: Array,
    student: Object,
    // ECD–5 students see an extra term-trail row under each subject and an
    // Overall Assessment row at the bottom. Higher classes get null for
    // both and the @if guards skip them.
    isPrimary: { type: Boolean, default: false },
    assessment: { type: Object, default: null },
})

function fmtPct(v) {
    if (v === null || v === undefined) return '-'
    return `${Number(v).toFixed(2)}%`
}

function downloadReportCard() {
    if (!props.exam?.id || !props.student?.id) return
    window.open(route('reports.report-card', [props.exam.id, props.student.id]), '_blank')
}
</script>

<template>
    <Head :title="`Result - ${exam?.name}`" />
    <AppLayout :breadcrumbs="[
        { label: 'My Results', href: route('student-portal.results') },
        { label: exam?.name || 'Result' }
    ]">
        <div class="space-y-6">
            <!-- Exam Header -->
            <div class="rounded-2xl bg-gradient-to-br from-primary to-secondary p-6 text-white shadow-lg">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm uppercase tracking-wider text-white/70">{{ exam?.type }}</p>
                        <h1 class="text-2xl font-bold sm:text-3xl">{{ exam?.name }}</h1>
                        <p class="text-sm text-white/85 mt-1">
                            <template v-if="exam?.start_date">{{ exam.start_date }} &mdash; {{ exam.end_date }}</template>
                            <template v-else-if="result?.session?.name">{{ result.session.name }}</template>
                        </p>
                    </div>
                    <button @click="downloadReportCard" class="btn btn-sm bg-white/20 hover:bg-white/30 border-white/30 text-white gap-2">
                        <ArrowDownTrayIcon class="h-4 w-4" />
                        Download Report Card
                    </button>
                </div>
            </div>

            <!-- Summary Card -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <p class="text-2xs uppercase text-base-content/50">Total</p>
                        <p class="text-xl font-bold">{{ result?.total_marks }}</p>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <p class="text-2xs uppercase text-base-content/50">Obtained</p>
                        <p class="text-xl font-bold text-primary">{{ result?.obtained_marks }}</p>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <p class="text-2xs uppercase text-base-content/50">Percentage</p>
                        <p class="text-xl font-bold">{{ fmtPct(result?.percentage) }}</p>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <p class="text-2xs uppercase text-base-content/50">Grade</p>
                        <p class="text-xl font-bold">{{ result?.grade || '-' }}</p>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <p class="text-2xs uppercase text-base-content/50 flex items-center gap-1">
                            <TrophyIcon class="h-3 w-3" /> Position
                        </p>
                        <p class="text-xl font-bold">{{ result?.position ? '#' + result.position : '-' }}</p>
                        <p v-if="result?.total_students" class="text-2xs text-base-content/40">of {{ result.total_students }}</p>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <p class="text-2xs uppercase text-base-content/50">Status</p>
                        <span
                            class="badge gap-1 mt-1"
                            :class="result?.is_passed ? 'badge-success' : 'badge-error'"
                        >
                            <CheckCircleIcon v-if="result?.is_passed" class="h-3 w-3" />
                            <XCircleIcon v-else class="h-3 w-3" />
                            {{ result?.is_passed ? 'Pass' : 'Retry' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Subjects Table -->
            <div class="card bg-base-100 shadow-md">
                <div class="card-body p-0">
                    <div class="px-5 py-4 border-b border-base-200 flex items-center gap-2">
                        <AcademicCapIcon class="h-5 w-5 text-primary" />
                        <h2 class="text-base font-bold">Subject-wise Breakdown</h2>
                    </div>
                    <div v-if="subjects?.length" class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-right">Obtained</th>
                                    <th class="text-right">%</th>
                                    <th class="text-center">Grade</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="(s, i) in subjects" :key="i">
                                <tr>
                                    <td class="font-medium">{{ s.subject_name }}</td>
                                    <td class="text-right">{{ s.total_marks ?? '-' }}</td>
                                    <td class="text-right font-semibold">{{ s.obtained_marks ?? '-' }}</td>
                                    <td class="text-right">{{ s.percentage !== null && s.percentage !== undefined ? fmtPct(s.percentage) : '-' }}</td>
                                    <td class="text-center">{{ s.grade || '-' }}</td>
                                    <td class="text-center">
                                        <span
                                            v-if="s.is_passed !== null && s.is_passed !== undefined"
                                            class="badge badge-sm"
                                            :class="s.is_passed ? 'badge-success' : 'badge-error'"
                                        >
                                            {{ s.is_passed ? 'Pass' : 'Retry' }}
                                        </span>
                                        <span v-else class="text-base-content/40">-</span>
                                    </td>
                                </tr>
                                <tr v-if="isPrimary && s.primary_breakdown" class="bg-base-200/40">
                                    <td colspan="6" class="text-[11px] text-base-content/65 py-1.5 px-3">
                                        <span class="font-bold text-base-content/45 uppercase tracking-wider text-[10px] mr-2">Term trail:</span>
                                        1st <strong>{{ s.primary_breakdown.first?.obtained ?? '—' }}</strong>/{{ s.primary_breakdown.first?.total ?? '—' }}
                                        ·
                                        2nd <strong>{{ s.primary_breakdown.second?.obtained ?? '—' }}</strong>/{{ s.primary_breakdown.second?.total ?? '—' }}
                                        ·
                                        Final <strong>{{ s.primary_breakdown.final?.obtained ?? '—' }}</strong>/{{ s.primary_breakdown.final?.total ?? '—' }}
                                    </td>
                                </tr>
                                </template>
                                <!-- Primary Assessment row. -->
                                <tr v-if="isPrimary && assessment" class="bg-emerald-500/10">
                                    <td class="font-semibold text-emerald-700 dark:text-emerald-300">
                                        Overall Assessment
                                        <span class="block text-[10px] text-base-content/55 font-normal">conduct · participation · attendance</span>
                                    </td>
                                    <td class="text-right">{{ assessment.total }}</td>
                                    <td class="text-right font-semibold">{{ assessment.obtained }}</td>
                                    <td class="text-right">{{ fmtPct(assessment.total > 0 ? (assessment.obtained / assessment.total * 100) : 0) }}</td>
                                    <td class="text-center text-base-content/40">—</td>
                                    <td class="text-center">
                                        <span class="badge badge-sm" :class="assessment.passed ? 'badge-success' : 'badge-error'">
                                            {{ assessment.passed ? 'Pass' : 'Retry' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="p-8 text-center text-sm text-base-content/55">
                        Subject-wise breakdown not available.
                    </div>
                </div>
            </div>

            <div v-if="result?.remarks" class="card bg-base-100 shadow-md">
                <div class="card-body p-5">
                    <h3 class="text-sm font-bold mb-2">Remarks</h3>
                    <p class="text-sm text-base-content/75">{{ result.remarks }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
