<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    ChartBarIcon, CheckCircleIcon, XCircleIcon, TrophyIcon,
    ArrowDownTrayIcon, ArrowLeftIcon, ArrowPathIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    role: String,
    student: Object,
    result: Object,
    exam: Object,
    subjects: { type: Array, default: () => [] },
    amendments: { type: Array, default: () => [] },
})

function fmtPct(v) {
    if (v === null || v === undefined) return '-'
    return `${Number(v).toFixed(2)}%`
}
</script>

<template>
    <Head :title="`${exam?.name || 'Result'} — ${student?.name}`" />
    <AppLayout :breadcrumbs="[
        { label: role === 'parent' ? 'Family Portal' : 'My Account', href: route('portal.dashboard', { student_id: student?.id }) },
        { label: 'Results', href: route('portal.results', { student_id: student?.id }) },
        { label: exam?.name || 'Detail' },
    ]">
        <div class="space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div>
                    <Link :href="route('portal.results', { student_id: student?.id })"
                        class="btn btn-ghost btn-sm gap-1 mb-2">
                        <ArrowLeftIcon class="h-4 w-4" /> Back to results
                    </Link>
                    <h1 class="text-2xl font-bold">{{ exam?.name }}</h1>
                    <p class="text-sm text-base-content/55 mt-1">
                        {{ exam?.type || 'Exam' }}<span v-if="exam?.start_date"> · {{ exam.start_date }} — {{ exam.end_date }}</span>
                    </p>
                </div>
                <a :href="route('portal.report-card', result?.id)" target="_blank"
                    class="btn btn-primary btn-sm gap-1.5">
                    <ArrowDownTrayIcon class="h-4 w-4" /> Download Report Card
                </a>
            </div>

            <!-- Amendment banner — visible only when this result has been
                 amended after publication. Shows the latest reason + a
                 collapsible audit trail of all amendments. -->
            <div v-if="result?.last_amended_at" class="rounded-xl border-2 border-warning/30 bg-warning/5 p-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-warning/15">
                        <ArrowPathIcon class="h-5 w-5 text-warning" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold">This result was amended</p>
                        <p v-if="amendments?.[0]" class="text-xs text-base-content/70 mt-0.5">
                            <span class="font-semibold">{{ amendments[0].amended_at }}</span>
                            <span v-if="amendments[0].amended_by"> · by {{ amendments[0].amended_by }}</span>
                        </p>
                        <p v-if="amendments?.[0]?.reason" class="text-sm text-base-content/85 mt-1.5 italic">
                            "{{ amendments[0].reason }}"
                        </p>
                        <details v-if="amendments && amendments.length > 1" class="mt-3">
                            <summary class="text-xs font-semibold text-warning cursor-pointer hover:underline">
                                View full amendment history ({{ amendments.length }})
                            </summary>
                            <ul class="mt-2 space-y-2 text-xs">
                                <li v-for="a in amendments.slice(1)" :key="a.id"
                                    class="border-l-2 border-warning/30 pl-3">
                                    <p class="font-semibold">{{ a.amended_at }}<span v-if="a.amended_by"> · {{ a.amended_by }}</span></p>
                                    <p class="text-base-content/70 italic mt-0.5">"{{ a.reason }}"</p>
                                </li>
                            </ul>
                        </details>
                    </div>
                </div>
            </div>

            <!-- Header summary cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <p class="text-[10px] uppercase tracking-wide text-base-content/50">Total %</p>
                        <p class="text-2xl font-bold">{{ fmtPct(result?.percentage) }}</p>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <p class="text-[10px] uppercase tracking-wide text-base-content/50">Grade</p>
                        <p class="text-2xl font-bold">{{ result?.grade || '—' }}</p>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <p class="text-[10px] uppercase tracking-wide text-base-content/50">Position</p>
                        <p class="text-2xl font-bold flex items-center gap-1">
                            <TrophyIcon class="h-5 w-5 text-warning" />
                            {{ result?.position || '—' }}
                            <span v-if="result?.total_students" class="text-sm font-normal text-base-content/55">/ {{ result.total_students }}</span>
                        </p>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <p class="text-[10px] uppercase tracking-wide text-base-content/50">Status</p>
                        <p class="text-2xl font-bold flex items-center gap-1.5">
                            <CheckCircleIcon v-if="result?.is_passed" class="h-6 w-6 text-success" />
                            <XCircleIcon v-else class="h-6 w-6 text-error" />
                            <span :class="result?.is_passed ? 'text-success' : 'text-error'">
                                {{ result?.is_passed ? 'Passed' : 'Failed' }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Marks table -->
            <div class="card bg-base-100 shadow-md">
                <div class="card-body p-5">
                    <h2 class="text-base font-bold flex items-center gap-2 mb-3">
                        <ChartBarIcon class="h-5 w-5 text-primary" /> Subject-wise marks
                    </h2>
                    <div v-if="!subjects.length" class="text-sm text-base-content/55 py-4 text-center">
                        No subject breakdown available.
                    </div>
                    <table v-else class="table table-zebra">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th class="text-right">Obtained</th>
                                <th class="text-right">Total</th>
                                <th class="text-right">%</th>
                                <th class="text-center">Grade</th>
                                <th class="text-center">Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in subjects" :key="s.subject_id">
                                <td class="font-semibold">{{ s.subject_name }}</td>
                                <td class="text-right font-mono">{{ s.obtained_marks ?? '—' }}</td>
                                <td class="text-right font-mono">{{ s.total_marks ?? '—' }}</td>
                                <td class="text-right font-mono">{{ fmtPct(s.percentage) }}</td>
                                <td class="text-center"><span class="badge badge-sm">{{ s.grade || '—' }}</span></td>
                                <td class="text-center">
                                    <CheckCircleIcon v-if="s.is_passed" class="h-4 w-4 text-success inline" />
                                    <XCircleIcon v-else class="h-4 w-4 text-error inline" />
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-right font-mono">{{ result?.obtained_marks }}</th>
                                <th class="text-right font-mono">{{ result?.total_marks }}</th>
                                <th class="text-right font-mono">{{ fmtPct(result?.percentage) }}</th>
                                <th class="text-center">{{ result?.grade || '—' }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>

                    <p v-if="result?.remarks" class="mt-4 text-sm text-base-content/65 italic">
                        Remarks: {{ result.remarks }}
                    </p>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
