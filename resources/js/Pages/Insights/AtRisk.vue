<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    ExclamationTriangleIcon, FireIcon, BellAlertIcon,
    ArrowRightIcon, ChartBarIcon, ShieldExclamationIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    flagged: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({ total: 0, high: 0, medium: 0, low: 0 }) },
    currentSession: { type: Object, default: null },
})

const activeFilter = ref('all')

const filters = [
    { key: 'all', label: 'All' },
    { key: 'consistently_failing', label: 'Consistently Retry' },
    { key: 'declining', label: 'Declining' },
    { key: 'below_class_avg', label: 'Below Class Avg' },
    { key: 'multiple_subject_failures', label: 'Multiple Retries' },
]

const filtered = computed(() => {
    if (activeFilter.value === 'all') return props.flagged
    return props.flagged.filter((f) => (f.reasons || []).some((r) => r.category === activeFilter.value))
})

function severityBadge(s) {
    if (s === 'high') return 'bg-error/10 text-error ring-error/20'
    if (s === 'medium') return 'bg-warning/10 text-warning ring-warning/20'
    return 'bg-info/10 text-info ring-info/20'
}

function severityIcon(s) {
    if (s === 'high') return FireIcon
    if (s === 'medium') return ShieldExclamationIcon
    return BellAlertIcon
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
</script>

<template>
    <Head title="At-Risk Students" />
    <AppLayout :breadcrumbs="[{ label: 'Insights' }, { label: 'At-Risk Students' }]">
        <div class="space-y-6">
            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">At-Risk Students</h1>
                    <p class="page-subtitle">Smart alerts — students who need intervention</p>
                </div>
                <div v-if="currentSession" class="hidden items-center gap-2 rounded-xl border border-base-200 bg-base-100 px-3 py-2 text-xs font-medium sm:flex">
                    <span class="text-base-content/40">Session</span>
                    <span class="font-semibold">{{ currentSession.name }}</span>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard title="Total Flagged" :value="counts.total" subtitle="Students needing help" color="primary">
                    <template #icon><ExclamationTriangleIcon class="h-5 w-5" /></template>
                </StatCard>
                <StatCard title="High Priority" :value="counts.high" subtitle="Immediate action" color="error">
                    <template #icon><FireIcon class="h-5 w-5" /></template>
                </StatCard>
                <StatCard title="Medium" :value="counts.medium" subtitle="Monitor closely" color="warning">
                    <template #icon><ShieldExclamationIcon class="h-5 w-5" /></template>
                </StatCard>
                <StatCard title="Low" :value="counts.low" subtitle="Keep an eye on" color="info">
                    <template #icon><BellAlertIcon class="h-5 w-5" /></template>
                </StatCard>
            </div>

            <!-- Filter Tabs -->
            <div class="card-section">
                <div class="card-content">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="f in filters"
                            :key="f.key"
                            class="rounded-lg border px-3 py-1.5 text-xs font-medium transition-all"
                            :class="activeFilter === f.key
                                ? 'border-primary bg-primary text-primary-content'
                                : 'border-base-200 bg-base-100 text-base-content/70 hover:bg-base-200'"
                            @click="activeFilter = f.key"
                        >
                            {{ f.label }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Flagged List -->
            <div v-if="!filtered.length" class="card-section">
                <div class="card-content py-16 text-center">
                    <CheckCircleIcon class="mx-auto h-12 w-12 text-success/60" />
                    <p class="mt-4 text-sm text-base-content/50">No students matching this category. Great work!</p>
                </div>
            </div>

            <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div
                    v-for="f in filtered"
                    :key="f.student_id"
                    class="card-section"
                >
                    <div class="card-content space-y-4">
                        <!-- Header: avatar + name + severity -->
                        <div class="flex items-start gap-3">
                            <div class="avatar-initial h-12 w-12 text-sm">
                                {{ f.student_name?.charAt(0)?.toUpperCase() || '?' }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold">{{ f.student_name }}</p>
                                <p class="text-xs text-base-content/50">
                                    <span v-if="f.admission_no">{{ f.admission_no }} &middot; </span>
                                    <span v-if="f.school_name">{{ f.school_name }} &middot; </span>
                                    {{ f.class_name }}<span v-if="f.section_name"> - {{ f.section_name }}</span>
                                </p>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-2xs font-bold uppercase tracking-wider ring-1" :class="severityBadge(f.severity)">
                                <component :is="severityIcon(f.severity)" class="h-3 w-3" />
                                {{ f.severity }}
                            </span>
                        </div>

                        <!-- Reasons -->
                        <div class="space-y-2">
                            <div
                                v-for="(r, i) in f.reasons"
                                :key="i"
                                class="rounded-lg border border-base-200 bg-base-100 p-3"
                            >
                                <p class="text-xs font-semibold">{{ r.label }}</p>
                                <p class="mt-1 text-2xs text-base-content/60">{{ r.detail }}</p>
                                <p class="mt-2 rounded bg-primary/5 px-2 py-1.5 text-2xs text-primary">
                                    <span class="font-bold">Suggested action:</span> {{ r.suggested_action }}
                                </p>
                            </div>
                        </div>

                        <!-- Last 3 exams mini bars -->
                        <div v-if="f.last_three_exams?.length">
                            <p class="mb-2 text-2xs font-bold uppercase tracking-wider text-base-content/40">Last exams</p>
                            <div class="space-y-1.5">
                                <div v-for="(ex, i) in f.last_three_exams" :key="i" class="flex items-center gap-2">
                                    <span class="w-28 truncate text-2xs text-base-content/60">{{ ex.exam_name }}</span>
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-base-200">
                                        <div
                                            class="h-full rounded-full"
                                            :class="pctColor(ex.percentage)"
                                            :style="{ width: `${Math.min(ex.percentage, 100)}%` }"
                                        />
                                    </div>
                                    <span class="w-12 text-right text-2xs font-bold" :class="pctTextColor(ex.percentage)">
                                        {{ Number(ex.percentage).toFixed(1) }}%
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Footer: class avg + link -->
                        <div class="flex items-center justify-between border-t border-base-200 pt-3">
                            <div class="text-2xs text-base-content/50">
                                Latest: <span class="font-bold" :class="pctTextColor(f.last_exam_percentage)">{{ f.last_exam_percentage }}%</span>
                                <span v-if="f.class_average !== null">
                                    &middot; Class avg: <span class="font-bold">{{ f.class_average }}%</span>
                                </span>
                            </div>
                            <Link
                                :href="route('insights.student-progress', { student_id: f.student_id })"
                                class="inline-flex items-center gap-1 rounded-lg bg-primary/10 px-2.5 py-1.5 text-2xs font-semibold text-primary transition-all hover:bg-primary hover:text-primary-content"
                            >
                                View Progress
                                <ArrowRightIcon class="h-3 w-3" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
