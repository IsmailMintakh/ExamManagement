<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    ArrowPathIcon,
    ArrowUturnLeftIcon,
    BoltIcon,
    PencilSquareIcon,
    CheckBadgeIcon,
    InformationCircleIcon,
    UserGroupIcon,
    ClockIcon,
    CheckCircleIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: { type: Object, required: true },
    rows: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({ total_failed: 0, eligible: 0, appeared: 0, passed: 0, failed: 0, threshold: 2 }),
    },
})

const detectOpen = ref(false)
const finalizeTarget = ref(null)
const isProcessing = ref(false)

function statusBadge(status) {
    switch (status) {
        case 'passed':
            return { class: 'badge-success', label: 'Passed' }
        case 'failed':
            return { class: 'badge-error', label: 'Retry' }
        case 'appeared':
            return { class: 'badge-info', label: 'Appeared' }
        case 'eligible':
            return { class: 'badge-warning', label: 'Eligible' }
        default:
            return { class: 'badge-ghost', label: status || '—' }
    }
}

const groupedByClass = computed(() => {
    const map = new Map()
    props.rows.forEach((row) => {
        const key = `${row.class_name || '-'} · ${row.section_name || '-'}`
        if (!map.has(key)) map.set(key, [])
        map.get(key).push(row)
    })
    return Array.from(map.entries()).map(([label, items]) => ({ label, items }))
})

function runDetect() {
    isProcessing.value = true
    router.post(
        route('supplementary.mark-eligible', props.exam.id),
        {},
        {
            onFinish: () => {
                isProcessing.value = false
                detectOpen.value = false
            },
        }
    )
}

function confirmFinalize(row) {
    finalizeTarget.value = row
}

function runFinalize() {
    if (!finalizeTarget.value) return
    isProcessing.value = true
    router.post(
        route('supplementary.finalize', finalizeTarget.value.id),
        {},
        {
            onFinish: () => {
                isProcessing.value = false
                finalizeTarget.value = null
            },
        }
    )
}
</script>

<template>
    <Head :title="`Supplementary - ${exam.name}`" />
    <AppLayout
        :breadcrumbs="[
            { label: 'Supplementary', href: '/supplementary' },
            { label: exam.name },
        ]"
    >
        <div class="space-y-5">
            <!-- Header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <ArrowPathIcon class="h-6 w-6 text-primary" />
                        {{ exam.name }}
                    </h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        {{ exam.exam_type }}<span v-if="exam.session"> · {{ exam.session }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('supplementary.index')" class="btn btn-ghost btn-sm gap-2">
                        <ArrowUturnLeftIcon class="h-4 w-4" />
                        Back
                    </Link>
                    <button
                        type="button"
                        class="btn btn-secondary btn-sm gap-2"
                        :disabled="isProcessing"
                        @click="detectOpen = true"
                    >
                        <BoltIcon class="h-4 w-4" />
                        Auto-detect Eligible Students
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                <div class="card bg-base-100 border border-base-200 shadow-sm">
                    <div class="card-body p-3 flex flex-row items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-error/10 text-error">
                            <XCircleIcon class="h-4 w-4" />
                        </div>
                        <div>
                            <p class="text-2xs uppercase tracking-wider text-base-content/40">Total Retry</p>
                            <p class="text-lg font-bold">{{ stats.total_failed }}</p>
                        </div>
                    </div>
                </div>
                <div class="card bg-base-100 border border-base-200 shadow-sm">
                    <div class="card-body p-3 flex flex-row items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-warning/10 text-warning">
                            <UserGroupIcon class="h-4 w-4" />
                        </div>
                        <div>
                            <p class="text-2xs uppercase tracking-wider text-base-content/40">Eligible</p>
                            <p class="text-lg font-bold">{{ stats.eligible }}</p>
                        </div>
                    </div>
                </div>
                <div class="card bg-base-100 border border-base-200 shadow-sm">
                    <div class="card-body p-3 flex flex-row items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-info/10 text-info">
                            <ClockIcon class="h-4 w-4" />
                        </div>
                        <div>
                            <p class="text-2xs uppercase tracking-wider text-base-content/40">Appeared</p>
                            <p class="text-lg font-bold">{{ stats.appeared }}</p>
                        </div>
                    </div>
                </div>
                <div class="card bg-base-100 border border-base-200 shadow-sm">
                    <div class="card-body p-3 flex flex-row items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-success/10 text-success">
                            <CheckCircleIcon class="h-4 w-4" />
                        </div>
                        <div>
                            <p class="text-2xs uppercase tracking-wider text-base-content/40">Passed</p>
                            <p class="text-lg font-bold">{{ stats.passed }}</p>
                        </div>
                    </div>
                </div>
                <div class="card bg-base-100 border border-base-200 shadow-sm">
                    <div class="card-body p-3 flex flex-row items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-error/10 text-error">
                            <XCircleIcon class="h-4 w-4" />
                        </div>
                        <div>
                            <p class="text-2xs uppercase tracking-wider text-base-content/40">Retry (Supp.)</p>
                            <p class="text-lg font-bold">{{ stats.failed }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!rows.length" class="alert alert-info">
                <InformationCircleIcon class="h-5 w-5 shrink-0" />
                <div class="text-sm">
                    <p class="font-semibold">No eligible students yet</p>
                    <p class="text-xs opacity-80 mt-0.5">
                        Click "Auto-detect Eligible Students" to flag students who need to retry up to {{ stats.threshold }} subject(s).
                    </p>
                </div>
            </div>

            <!-- Students Table grouped by Class · Section -->
            <div v-for="group in groupedByClass" :key="group.label" class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body p-0">
                    <div class="px-4 py-3 border-b border-base-200 bg-base-200/40">
                        <h3 class="text-sm font-semibold">{{ group.label }}</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th class="w-12">#</th>
                                    <th>Roll No</th>
                                    <th>Student</th>
                                    <th>Retry Subjects</th>
                                    <th class="w-32">Status</th>
                                    <th class="w-44 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, i) in group.items" :key="row.id">
                                    <td>{{ i + 1 }}</td>
                                    <td class="text-sm">{{ row.roll_no || '-' }}</td>
                                    <td class="text-sm font-medium">{{ row.student_name }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            <span
                                                v-for="sub in row.failed_subjects"
                                                :key="sub.id"
                                                class="badge badge-outline badge-error badge-sm"
                                            >
                                                {{ sub.code || sub.name }}
                                            </span>
                                            <span v-if="!row.failed_subjects.length" class="text-xs text-base-content/40">—</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-sm" :class="statusBadge(row.supplementary_status).class">
                                            {{ statusBadge(row.supplementary_status).label }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <Link
                                            v-if="['eligible', 'appeared'].includes(row.supplementary_status)"
                                            :href="route('supplementary.entry', row.id)"
                                            class="btn btn-xs btn-outline btn-primary gap-1"
                                        >
                                            <PencilSquareIcon class="h-3.5 w-3.5" />
                                            Enter Marks
                                        </Link>
                                        <button
                                            v-if="row.supplementary_status === 'appeared'"
                                            type="button"
                                            class="btn btn-xs btn-success ml-1 gap-1"
                                            :disabled="isProcessing"
                                            @click="confirmFinalize(row)"
                                        >
                                            <CheckBadgeIcon class="h-3.5 w-3.5" />
                                            Finalize
                                        </button>
                                        <span
                                            v-if="['passed', 'failed'].includes(row.supplementary_status)"
                                            class="text-xs text-base-content/50"
                                        >
                                            Finalized
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <EmptyState
                v-if="rows.length === 0"
                title="Nothing to show yet"
                description="Run auto-detect to flag retry-eligible students within threshold."
            />
        </div>

        <ConfirmDialog
            :show="detectOpen"
            title="Auto-detect Eligible Students"
            :message="`This will scan all retry-eligible results in this exam and mark students as supplementary-eligible if they need to retry up to ${stats.threshold} subject(s). Continue?`"
            confirm-text="Yes, Detect"
            type="warning"
            @confirm="runDetect"
            @cancel="detectOpen = false"
        />

        <ConfirmDialog
            :show="!!finalizeTarget"
            title="Finalize Supplementary Result"
            :message="finalizeTarget
                ? `Finalize result for ${finalizeTarget.student_name}? The better of the original and supplementary marks will be used to recalculate pass/retry.`
                : ''"
            confirm-text="Yes, Finalize"
            type="warning"
            @confirm="runFinalize"
            @cancel="finalizeTarget = null"
        />
    </AppLayout>
</template>
