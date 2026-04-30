<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    ArchiveBoxIcon,
    InformationCircleIcon,
    UserGroupIcon,
    ClipboardDocumentListIcon,
    ChartBarIcon,
    CalendarIcon,
    EyeIcon,
    DocumentTextIcon,
} from '@heroicons/vue/24/outline'

defineProps({
    session: { type: Object, required: true },
    exams: { type: Array, default: () => [] },
    stats: { type: Object, required: true },
})

function formatDate(d) {
    if (!d) return '—'
    try {
        return new Date(d).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
    } catch (e) {
        return d
    }
}
</script>

<template>
    <Head :title="`Archive · ${session.name}`" />
    <AppLayout
        :breadcrumbs="[
            { label: 'Archive', href: route('archive.index') },
            { label: session.name },
        ]"
    >
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary shrink-0">
                        <ArchiveBoxIcon class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">{{ session.name }}</h1>
                        <p class="mt-1 flex items-center gap-1.5 text-sm text-base-content/55">
                            <CalendarIcon class="h-4 w-4" />
                            {{ formatDate(session.start_date) }} — {{ formatDate(session.end_date) }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span v-if="session.is_current" class="badge badge-success">Current Session</span>
                    <span v-else-if="session.is_archived" class="badge badge-neutral">Archived</span>
                    <span v-else class="badge badge-ghost">Upcoming</span>
                </div>
            </div>

            <!-- Read-only banner -->
            <div class="alert bg-info/10 border border-info/20 text-info-content">
                <InformationCircleIcon class="h-5 w-5 text-info shrink-0" />
                <div>
                    <h3 class="font-semibold text-base-content">Read-only historical data</h3>
                    <p class="text-sm text-base-content/65">
                        This view shows snapshot data from this academic session for reference purposes.
                        Records cannot be modified from the archive.
                    </p>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard title="Students" :value="stats.totalStudents" color="primary">
                    <template #icon><UserGroupIcon class="h-5 w-5" /></template>
                </StatCard>
                <StatCard title="Exams" :value="stats.totalExams" color="secondary">
                    <template #icon><ClipboardDocumentListIcon class="h-5 w-5" /></template>
                </StatCard>
                <StatCard title="Results" :value="stats.totalResults" color="accent">
                    <template #icon><ChartBarIcon class="h-5 w-5" /></template>
                </StatCard>
            </div>

            <!-- Exams List -->
            <div class="card bg-base-100 shadow-md border border-base-200">
                <div class="card-body p-0">
                    <div class="border-b border-base-200 px-5 py-4">
                        <h2 class="text-base font-bold">Exams in this Session</h2>
                        <p class="mt-0.5 text-xs text-base-content/55">All exams conducted during {{ session.name }}</p>
                    </div>

                    <div v-if="exams?.length" class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr class="text-xs uppercase tracking-wider text-base-content/55">
                                    <th>Exam</th>
                                    <th>Type</th>
                                    <th>Dates</th>
                                    <th>Results</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="exam in exams" :key="exam.id" class="hover">
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <DocumentTextIcon class="h-4 w-4 text-base-content/45" />
                                            <span class="font-medium">{{ exam.name }}</span>
                                        </div>
                                    </td>
                                    <td class="text-sm text-base-content/65">{{ exam.exam_type?.name || '—' }}</td>
                                    <td class="text-xs text-base-content/55">
                                        {{ formatDate(exam.start_date) }} — {{ formatDate(exam.end_date) }}
                                    </td>
                                    <td>
                                        <span class="badge badge-ghost badge-sm">{{ exam.results_count ?? 0 }} results</span>
                                    </td>
                                    <td class="text-right">
                                        <Link
                                            :href="route('exams.show', exam.id)"
                                            class="btn btn-ghost btn-xs gap-1"
                                        >
                                            <EyeIcon class="h-4 w-4" />
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <EmptyState
                        v-else
                        title="No exams in this session"
                        description="There are no exams recorded for this academic session."
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
