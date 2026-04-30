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
                            {{ result?.is_passed ? 'Pass' : 'Fail' }}
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
                                <tr v-for="(s, i) in subjects" :key="i">
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
                                            {{ s.is_passed ? 'Pass' : 'Fail' }}
                                        </span>
                                        <span v-else class="text-base-content/40">-</span>
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
