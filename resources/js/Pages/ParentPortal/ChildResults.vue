<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
    UserCircleIcon, AcademicCapIcon, BuildingOfficeIcon,
    ChartBarIcon, ArrowDownTrayIcon, CheckCircleIcon,
    XCircleIcon, TrophyIcon, EyeIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    student: Object,
    results: Array,
    sessions: Array,
})

const grouped = computed(() => {
    const out = {}
    for (const r of props.results || []) {
        const key = r.session_name || 'Unknown Session'
        if (!out[key]) out[key] = []
        out[key].push(r)
    }
    return out
})

function fmtPct(v) {
    if (v === null || v === undefined) return '-'
    return `${Number(v).toFixed(2)}%`
}

function openReportCard(examId) {
    window.open(route('reports.report-card', [examId, props.student.id]), '_blank')
}
</script>

<template>
    <Head :title="`Results - ${student?.name}`" />
    <AppLayout :breadcrumbs="[
        { label: 'My Children', href: route('parent-portal.dashboard') },
        { label: student?.name }
    ]">
        <div class="space-y-6">
            <!-- Child header -->
            <div class="card bg-gradient-to-br from-primary to-secondary text-white shadow-lg">
                <div class="card-body p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white/20 ring-4 ring-white/30">
                            <img v-if="student?.photo_url" :src="student.photo_url" :alt="student.name" class="h-full w-full object-cover" />
                            <UserCircleIcon v-else class="h-12 w-12 text-white/90" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm uppercase tracking-wider text-white/70">Student</p>
                            <h1 class="text-2xl font-bold sm:text-3xl">{{ student?.name }}</h1>
                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-white/85">
                                <span>Adm No: <strong>{{ student?.admission_no }}</strong></span>
                                <span v-if="student?.roll_no">Roll: <strong>{{ student.roll_no }}</strong></span>
                                <span v-if="student?.school" class="inline-flex items-center gap-1">
                                    <BuildingOfficeIcon class="h-3.5 w-3.5" />
                                    {{ student.school.name }}
                                </span>
                                <span v-if="student?.class" class="inline-flex items-center gap-1">
                                    <AcademicCapIcon class="h-3.5 w-3.5" />
                                    {{ student.class.name }}<template v-if="student.section"> - {{ student.section.name }}</template>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- No results -->
            <div v-if="!results?.length" class="card bg-base-100 shadow-md">
                <div class="card-body p-8 text-center">
                    <ChartBarIcon class="h-12 w-12 text-base-content/20 mx-auto mb-3" />
                    <p class="text-base-content/55">No results published yet for {{ student?.name }}.</p>
                </div>
            </div>

            <!-- Results grouped by session -->
            <div v-for="(group, sessionName) in grouped" :key="sessionName" class="space-y-3">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-base-content/60">
                        {{ sessionName }}
                    </h2>
                    <span class="badge badge-ghost badge-sm">{{ group.length }} exam{{ group.length !== 1 ? 's' : '' }}</span>
                </div>

                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-0">
                        <div class="overflow-x-auto">
                            <table class="table table-zebra">
                                <thead>
                                    <tr>
                                        <th>Exam</th>
                                        <th class="text-right">Marks</th>
                                        <th class="text-right">%</th>
                                        <th class="text-center">Grade</th>
                                        <th class="text-center">Position</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="r in group" :key="r.id">
                                        <td>
                                            <p class="font-semibold">{{ r.exam_name }}</p>
                                            <p class="text-xs text-base-content/55">{{ r.exam_type }}</p>
                                        </td>
                                        <td class="text-right">{{ r.obtained_marks }}/{{ r.total_marks }}</td>
                                        <td class="text-right font-semibold text-primary">{{ fmtPct(r.percentage) }}</td>
                                        <td class="text-center font-semibold">{{ r.grade || '-' }}</td>
                                        <td class="text-center">
                                            <span v-if="r.position" class="inline-flex items-center gap-1">
                                                <TrophyIcon class="h-3.5 w-3.5 text-warning" />
                                                #{{ r.position }}
                                            </span>
                                            <span v-else class="text-base-content/40">-</span>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge badge-sm gap-1"
                                                :class="r.is_passed ? 'badge-success' : 'badge-error'"
                                            >
                                                <CheckCircleIcon v-if="r.is_passed" class="h-3 w-3" />
                                                <XCircleIcon v-else class="h-3 w-3" />
                                                {{ r.is_passed ? 'Pass' : 'Fail' }}
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <button
                                                @click="openReportCard(r.exam_id)"
                                                class="btn btn-ghost btn-xs gap-1"
                                                title="Open report card PDF"
                                            >
                                                <ArrowDownTrayIcon class="h-3.5 w-3.5" />
                                                Report Card
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
