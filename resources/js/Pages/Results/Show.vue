<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    PrinterIcon, DocumentArrowDownIcon,
    UserGroupIcon, CheckCircleIcon,
    XCircleIcon, AcademicCapIcon,
    ChartBarIcon, IdentificationIcon,
    DocumentDuplicateIcon,
} from '@heroicons/vue/24/outline'

defineProps({
    exam: Object,
    section: Object,
    schoolClass: Object,
    results: Object,
    summary: Object,
    subjects: Array,
})

function printResultSheet(examId, classId) {
    window.open(route('reports.result-sheet', [examId, classId]), '_blank')
}

function exportResults(examId) {
    window.open(route('reports.export', ['results', examId]), '_blank')
}

function openReportCard(examId, studentId) {
    window.open(route('reports.report-card', [examId, studentId]), '_blank')
}

function downloadAllMarkSheets(examId, sectionId) {
    window.open(route('reports.section-mark-sheets', [examId, sectionId]), '_blank')
}
</script>

<template>
    <Head :title="`Results - ${schoolClass?.name} ${section?.name}`" />
    <AppLayout :breadcrumbs="[
        { label: 'Results', href: route('results.index') },
        { label: exam?.name, href: route('results.generate', exam?.id) },
        { label: `${schoolClass?.name} - ${section?.name}` }
    ]">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold">{{ exam?.name }}</h1>
                    <p class="text-sm text-base-content/60 mt-0.5">
                        {{ schoolClass?.name }} - {{ section?.name }} &middot; Result Sheet
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button @click="downloadAllMarkSheets(exam?.id, section?.id)" class="btn btn-primary btn-sm gap-1.5">
                        <IdentificationIcon class="w-4 h-4" /> All Mark Sheets
                    </button>
                    <button @click="printResultSheet(exam?.id, schoolClass?.id)" class="btn btn-outline btn-sm gap-1.5">
                        <PrinterIcon class="w-4 h-4" /> Result Sheet
                    </button>
                    <button @click="exportResults(exam?.id)" class="btn btn-outline btn-sm gap-1.5">
                        <DocumentArrowDownIcon class="w-4 h-4" /> Export CSV
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-4" v-if="summary">
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                <UserGroupIcon class="w-4 h-4 text-primary" />
                            </div>
                            <div>
                                <p class="text-[11px] text-base-content/50 uppercase tracking-wide">Total</p>
                                <p class="text-2xl font-bold leading-tight">{{ summary.total }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-success/10">
                                <CheckCircleIcon class="w-4 h-4 text-success" />
                            </div>
                            <div>
                                <p class="text-[11px] text-base-content/50 uppercase tracking-wide">Passed</p>
                                <p class="text-2xl font-bold leading-tight text-success">{{ summary.passed }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-error/10">
                                <XCircleIcon class="w-4 h-4 text-error" />
                            </div>
                            <div>
                                <p class="text-[11px] text-base-content/50 uppercase tracking-wide">Failed</p>
                                <p class="text-2xl font-bold leading-tight text-error">{{ summary.failed }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-warning/10">
                                <AcademicCapIcon class="w-4 h-4 text-warning" />
                            </div>
                            <div>
                                <p class="text-[11px] text-base-content/50 uppercase tracking-wide">Pass %</p>
                                <p class="text-2xl font-bold leading-tight">{{ summary.passPercentage }}%</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-info/10">
                                <ChartBarIcon class="w-4 h-4 text-info" />
                            </div>
                            <div>
                                <p class="text-[11px] text-base-content/50 uppercase tracking-wide">Average</p>
                                <p class="text-2xl font-bold leading-tight">{{ summary.avgPercentage }}%</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Table -->
            <div class="card bg-base-100 shadow-md">
                <div class="card-body p-0">
                    <div class="overflow-x-auto">
                        <table class="table table-sm table-pin-rows">
                            <thead>
                                <tr>
                                    <th class="bg-base-200 text-center w-12">Pos</th>
                                    <th class="bg-base-200 w-16">Roll</th>
                                    <th class="bg-base-200 min-w-[140px]">Student Name</th>
                                    <th class="bg-base-200 min-w-[120px]">Father Name</th>
                                    <th v-for="subj in subjects" :key="subj.id" class="bg-base-200 text-center min-w-[60px]">
                                        <div class="text-xs font-bold">{{ subj.code || subj.name?.substring(0, 4) }}</div>
                                        <div class="text-[10px] text-base-content/40 font-normal">({{ subj.total }})</div>
                                    </th>
                                    <th class="bg-base-200 text-center min-w-[70px]">Total</th>
                                    <th class="bg-base-200 text-center w-16">%</th>
                                    <th class="bg-base-200 text-center w-16">Grade</th>
                                    <th class="bg-base-200 text-center w-20">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="result in results?.data" :key="result.id"
                                    :class="{ 'bg-error/5': !result.is_passed }">
                                    <td class="text-center font-bold text-base-content/70">{{ result.position }}</td>
                                    <td class="text-sm">{{ result.student?.roll_no }}</td>
                                    <td>
                                        <button
                                            @click="openReportCard(exam?.id, result.student?.id)"
                                            class="link link-primary link-hover font-medium text-sm text-left">
                                            {{ result.student?.name }}
                                        </button>
                                    </td>
                                    <td class="text-sm text-base-content/60">{{ result.student?.father_name || '-' }}</td>
                                    <td v-for="subj in subjects" :key="subj.id" class="text-center text-sm">
                                        <template v-if="result.subject_results?.[subj.id]">
                                            <span v-if="result.subject_results[subj.id].is_absent"
                                                  class="badge badge-ghost badge-xs">AB</span>
                                            <span v-else
                                                  :class="{ 'text-error font-bold': result.subject_results[subj.id].failed }">
                                                {{ result.subject_results[subj.id].obtained }}
                                            </span>
                                        </template>
                                        <span v-else class="text-base-content/20">-</span>
                                    </td>
                                    <td class="text-center font-bold text-sm">
                                        {{ result.obtained_marks }}<span class="text-base-content/40 font-normal">/{{ result.total_marks }}</span>
                                    </td>
                                    <td class="text-center font-bold text-sm">{{ result.percentage }}%</td>
                                    <td class="text-center">
                                        <span class="badge badge-outline badge-sm">{{ result.grade || '-' }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span :class="['badge badge-sm', result.is_passed ? 'badge-success' : 'badge-error']">
                                            {{ result.is_passed ? 'PASS' : 'FAIL' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!results?.data?.length">
                                    <td :colspan="5 + (subjects?.length || 0) + 4" class="text-center py-8 text-base-content/50">
                                        No results found for this section.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-base-200" v-if="results?.links?.length > 3">
                        <Pagination :links="results.links" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
