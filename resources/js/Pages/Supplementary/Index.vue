<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    ArrowPathIcon,
    InformationCircleIcon,
    ClipboardDocumentListIcon,
    UserGroupIcon,
    CheckCircleIcon,
    ClockIcon,
    AcademicCapIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exams: { type: Array, default: () => [] },
    totals: {
        type: Object,
        default: () => ({ pending_exams: 0, eligible_students: 0, appeared: 0, passed: 0 }),
    },
    currentSession: { type: Object, default: null },
})

function statusBadgeClass(exam) {
    if (exam.eligible_count === 0) return 'badge-ghost'
    if (exam.pending_count === 0) return 'badge-success'
    if (exam.appeared_count > 0) return 'badge-warning'
    return 'badge-info'
}

function statusLabel(exam) {
    if (exam.eligible_count === 0) return 'No eligible'
    if (exam.pending_count === 0) return 'Done'
    if (exam.appeared_count > 0) return 'In Progress'
    return 'Pending'
}
</script>

<template>
    <Head title="Supplementary Exams" />
    <AppLayout :breadcrumbs="[{ label: 'Supplementary' }]">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <ArrowPathIcon class="h-6 w-6 text-primary" />
                        Supplementary Exams
                    </h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        Manage supplementary exam eligibility, marks entry, and result finalization.
                    </p>
                </div>
                <div v-if="currentSession" class="rounded-xl border border-base-200 bg-base-100 px-4 py-2 text-xs">
                    <p class="text-2xs uppercase tracking-wider text-base-content/40">Current Session</p>
                    <p class="font-semibold">{{ currentSession.name }}</p>
                </div>
            </div>

            <!-- Info Banner -->
            <div class="alert alert-info">
                <InformationCircleIcon class="h-5 w-5 shrink-0" />
                <div class="text-sm">
                    <p class="font-semibold">Supplementary Exam Workflow</p>
                    <p class="text-xs opacity-80 mt-0.5">
                        Auto-detect students who narrowly failed, let subject teachers enter supplementary marks,
                        and finalize results to update pass/fail status using the better of the two attempts.
                    </p>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body p-4 flex flex-row items-center gap-4">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <ClipboardDocumentListIcon class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-2xs uppercase tracking-wider text-base-content/40">Pending Exams</p>
                            <p class="text-xl font-bold">{{ totals.pending_exams }}</p>
                        </div>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body p-4 flex flex-row items-center gap-4">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-warning/10 text-warning">
                            <UserGroupIcon class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-2xs uppercase tracking-wider text-base-content/40">Eligible Students</p>
                            <p class="text-xl font-bold">{{ totals.eligible_students }}</p>
                        </div>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body p-4 flex flex-row items-center gap-4">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-info/10 text-info">
                            <ClockIcon class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-2xs uppercase tracking-wider text-base-content/40">Appeared</p>
                            <p class="text-xl font-bold">{{ totals.appeared }}</p>
                        </div>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body p-4 flex flex-row items-center gap-4">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-success/10 text-success">
                            <CheckCircleIcon class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-2xs uppercase tracking-wider text-base-content/40">Passed</p>
                            <p class="text-xl font-bold">{{ totals.passed }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Exam Cards -->
            <div v-if="exams.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="exam in exams"
                    :key="exam.id"
                    class="card bg-base-100 shadow-sm border border-base-200 hover:border-primary/40 hover:shadow-md transition-all"
                >
                    <div class="card-body p-5">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <AcademicCapIcon class="h-5 w-5" />
                                </div>
                                <div>
                                    <h3 class="text-base font-bold leading-tight">{{ exam.name }}</h3>
                                    <p v-if="exam.exam_type" class="text-2xs text-base-content/50 mt-0.5">
                                        {{ exam.exam_type }}<span v-if="exam.session"> · {{ exam.session }}</span>
                                    </p>
                                </div>
                            </div>
                            <span class="badge badge-sm" :class="statusBadgeClass(exam)">{{ statusLabel(exam) }}</span>
                        </div>

                        <div class="mt-4 grid grid-cols-4 gap-2 rounded-lg border border-base-200 bg-base-200/40 p-3 text-center">
                            <div>
                                <p class="text-2xs uppercase tracking-wider text-base-content/50">Eligible</p>
                                <p class="text-lg font-bold">{{ exam.eligible_count }}</p>
                            </div>
                            <div>
                                <p class="text-2xs uppercase tracking-wider text-info/70">Appeared</p>
                                <p class="text-lg font-bold text-info">{{ exam.appeared_count }}</p>
                            </div>
                            <div>
                                <p class="text-2xs uppercase tracking-wider text-success/70">Passed</p>
                                <p class="text-lg font-bold text-success">{{ exam.passed_count }}</p>
                            </div>
                            <div>
                                <p class="text-2xs uppercase tracking-wider text-error/70">Failed</p>
                                <p class="text-lg font-bold text-error">{{ exam.failed_count }}</p>
                            </div>
                        </div>

                        <div class="card-actions mt-4">
                            <Link
                                :href="route('supplementary.show', exam.id)"
                                class="btn btn-primary btn-sm w-full gap-2"
                            >
                                <ArrowPathIcon class="h-4 w-4" />
                                Manage
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body p-8 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary mx-auto mb-4">
                        <ArrowPathIcon class="h-8 w-8" />
                    </div>
                    <h2 class="text-lg font-bold">No supplementary-eligible students yet</h2>
                    <p class="mt-2 text-sm text-base-content/60 max-w-xl mx-auto">
                        Supplementary exams give students who failed 1–2 subjects a second chance. Students become eligible once their main exam results are finalized and the Principal explicitly flags them.
                    </p>

                    <div class="mt-6 text-left max-w-xl mx-auto">
                        <p class="text-2xs uppercase tracking-wider text-base-content/50 font-semibold mb-2">How to get started</p>
                        <ol class="space-y-2 text-sm">
                            <li class="flex gap-2">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-primary-content text-xs font-bold shrink-0 mt-0.5">1</span>
                                <span>Finalize an exam's results (from the <Link :href="route('exams.index')" class="link link-primary">Exams</Link> page).</span>
                            </li>
                            <li class="flex gap-2">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-primary-content text-xs font-bold shrink-0 mt-0.5">2</span>
                                <span>Come back here and open that exam. As Principal, click <b>"Mark Eligible"</b> — students who failed 1–2 subjects will be auto-flagged.</span>
                            </li>
                            <li class="flex gap-2">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-primary-content text-xs font-bold shrink-0 mt-0.5">3</span>
                                <span>Subject teachers enter supplementary marks; Principal finalizes to update pass/fail.</span>
                            </li>
                        </ol>
                    </div>

                    <div class="mt-6">
                        <Link :href="route('exams.index')" class="btn btn-primary btn-sm gap-2">
                            <ClipboardDocumentListIcon class="h-4 w-4" />
                            Go to Exams
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
