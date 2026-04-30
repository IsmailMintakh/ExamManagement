<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    ArchiveBoxIcon,
    CalendarIcon,
    ClipboardDocumentListIcon,
    UserGroupIcon,
    CheckCircleIcon,
    ArrowRightIcon,
} from '@heroicons/vue/24/outline'

defineProps({
    sessions: { type: Array, default: () => [] },
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
    <Head title="Academic Year Archive" />
    <AppLayout :breadcrumbs="[{ label: 'Archive' }]">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <ArchiveBoxIcon class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Academic Year Archive</h1>
                        <p class="text-sm text-base-content/60">View historical data from past academic sessions</p>
                    </div>
                </div>
            </div>

            <!-- Sessions Grid -->
            <div v-if="sessions?.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="session in sessions"
                    :key="session.id"
                    class="card bg-base-100 shadow-md border border-base-200 hover:shadow-lg transition-shadow"
                >
                    <div class="card-body p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="text-lg font-bold truncate">{{ session.name }}</h2>
                                <p class="mt-1 flex items-center gap-1.5 text-xs text-base-content/55">
                                    <CalendarIcon class="h-3.5 w-3.5" />
                                    {{ formatDate(session.start_date) }} — {{ formatDate(session.end_date) }}
                                </p>
                            </div>
                            <span
                                v-if="session.is_current"
                                class="badge badge-success badge-sm gap-1 shrink-0"
                            >
                                <CheckCircleIcon class="h-3 w-3" /> Current
                            </span>
                            <span
                                v-else-if="session.is_archived"
                                class="badge badge-neutral badge-sm gap-1 shrink-0"
                            >
                                <ArchiveBoxIcon class="h-3 w-3" /> Archived
                            </span>
                            <span
                                v-else
                                class="badge badge-ghost badge-sm shrink-0"
                            >
                                Upcoming
                            </span>
                        </div>

                        <!-- Stats -->
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <div class="rounded-lg bg-base-200/50 px-3 py-2.5">
                                <div class="flex items-center gap-1.5 text-xs text-base-content/55">
                                    <ClipboardDocumentListIcon class="h-3.5 w-3.5" />
                                    Exams
                                </div>
                                <p class="mt-1 text-xl font-bold tracking-tight">{{ session.exams_count ?? 0 }}</p>
                            </div>
                            <div class="rounded-lg bg-base-200/50 px-3 py-2.5">
                                <div class="flex items-center gap-1.5 text-xs text-base-content/55">
                                    <UserGroupIcon class="h-3.5 w-3.5" />
                                    Students
                                </div>
                                <p class="mt-1 text-xl font-bold tracking-tight">{{ session.students_count ?? 0 }}</p>
                            </div>
                        </div>

                        <div class="card-actions mt-4 justify-end">
                            <Link
                                :href="route('archive.show', session.id)"
                                class="btn btn-primary btn-sm gap-1.5"
                            >
                                View Details
                                <ArrowRightIcon class="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <EmptyState
                v-else
                title="No academic sessions found"
                description="Once academic sessions are added you will see them here."
            />
        </div>
    </AppLayout>
</template>
