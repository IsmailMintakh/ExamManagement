<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    CalendarDaysIcon, ClipboardDocumentListIcon,
    BuildingOfficeIcon, MagnifyingGlassIcon,
    ArrowRightIcon, CheckCircleIcon, ClockIcon,
    DocumentTextIcon, UserGroupIcon, Cog6ToothIcon,
    PlusCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exams: Array,
    roomsCount: Number,
})

const search = ref('')

const filteredExams = computed(() => {
    if (!search.value.trim()) return props.exams || []
    const q = search.value.toLowerCase()
    return (props.exams || []).filter(e =>
        e.name?.toLowerCase().includes(q) ||
        e.exam_type?.toLowerCase().includes(q) ||
        e.session?.toLowerCase().includes(q)
    )
})

const statusBadge = (s) => ({
    draft: 'badge-ghost',
    published: 'badge-info',
    marks_entry: 'badge-warning',
    processing: 'badge-accent',
    completed: 'badge-success',
}[s] || 'badge-ghost')

const statusLabel = (s) => s?.replace(/_/g, ' ') || '—'

function progress(exam) {
    if (!exam.exam_subjects_count) return 0
    return Math.round((exam.schedules_count / exam.exam_subjects_count) * 100)
}
</script>

<template>
    <Head title="Exam Scheduling" />
    <AppLayout :breadcrumbs="[{ label: 'Exam Scheduling' }]">
        <div class="space-y-6">

            <!-- HEADER -->
            <div class="page-header">
                <div>
                    <h1 class="page-title flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-secondary shadow-lg shadow-primary/25">
                            <CalendarDaysIcon class="h-5 w-5 text-white" />
                        </div>
                        Exam Scheduling
                    </h1>
                    <p class="page-subtitle">Manage date sheets, seating plans, invigilators, and admit cards</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('scheduling.rooms')" class="btn btn-outline btn-sm gap-1.5">
                        <BuildingOfficeIcon class="w-4 h-4" /> Manage Rooms
                        <span class="badge badge-sm ml-1">{{ roomsCount }}</span>
                    </Link>
                </div>
            </div>

            <!-- FEATURE LEGEND -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="surface p-4 flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                        <DocumentTextIcon class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <p class="text-[13px] font-bold leading-tight">Date Sheet</p>
                        <p class="mt-0.5 text-[11px] text-base-content/50">Subject-wise exam schedule</p>
                    </div>
                </div>
                <div class="surface p-4 flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-secondary/10">
                        <UserGroupIcon class="h-5 w-5 text-secondary" />
                    </div>
                    <div>
                        <p class="text-[13px] font-bold leading-tight">Seating Plan</p>
                        <p class="mt-0.5 text-[11px] text-base-content/50">Room + seat assignment</p>
                    </div>
                </div>
                <div class="surface p-4 flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-accent/10">
                        <CheckCircleIcon class="h-5 w-5 text-accent" />
                    </div>
                    <div>
                        <p class="text-[13px] font-bold leading-tight">Invigilators</p>
                        <p class="mt-0.5 text-[11px] text-base-content/50">Duty assignments</p>
                    </div>
                </div>
                <div class="surface p-4 flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-success/10">
                        <ClipboardDocumentListIcon class="h-5 w-5 text-success" />
                    </div>
                    <div>
                        <p class="text-[13px] font-bold leading-tight">Admit Cards</p>
                        <p class="mt-0.5 text-[11px] text-base-content/50">Student entry passes</p>
                    </div>
                </div>
            </div>

            <!-- SEARCH -->
            <div class="surface">
                <div class="surface-body">
                    <div class="relative">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-base-content/35" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search exams by name, type, or session..."
                            class="input input-bordered w-full pl-9 text-sm"
                        />
                    </div>
                </div>
            </div>

            <!-- EXAMS LIST -->
            <div v-if="filteredExams.length" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <Link
                    v-for="exam in filteredExams"
                    :key="exam.id"
                    :href="route('scheduling.index', exam.id)"
                    class="surface group block transition-all hover:-translate-y-1 hover:shadow-elevated"
                >
                    <div class="surface-body space-y-4">
                        <!-- Exam Info -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="text-sm font-bold leading-tight">{{ exam.name }}</h3>
                                    <span class="badge badge-sm capitalize" :class="statusBadge(exam.status)">{{ statusLabel(exam.status) }}</span>
                                </div>
                                <p class="mt-1 text-xs text-base-content/55">
                                    {{ exam.exam_type }} &bull; {{ exam.session }}
                                </p>
                                <p v-if="exam.start_date" class="mt-1 text-[11px] text-base-content/45">
                                    {{ exam.start_date }} → {{ exam.end_date }}
                                </p>
                            </div>
                            <ArrowRightIcon class="h-5 w-5 shrink-0 text-base-content/30 transition-transform group-hover:translate-x-1 group-hover:text-primary" />
                        </div>

                        <!-- Progress -->
                        <div v-if="exam.exam_subjects_count > 0">
                            <div class="flex items-center justify-between mb-1.5 text-[11px]">
                                <span class="text-base-content/55">Scheduling Progress</span>
                                <span class="font-bold" :class="progress(exam) === 100 ? 'text-success' : 'text-primary'">
                                    {{ exam.schedules_count }}/{{ exam.exam_subjects_count }} ({{ progress(exam) }}%)
                                </span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-bar-fill" :class="progress(exam) === 100 ? 'bg-success' : 'bg-primary'" :style="{ width: progress(exam) + '%' }" />
                            </div>
                        </div>
                        <div v-else class="rounded-lg bg-warning/5 border border-warning/20 px-3 py-2 text-[11px] text-warning">
                            ⚠ No exam subjects added — add subjects to the exam first
                        </div>

                        <!-- Feature Stats -->
                        <div class="grid grid-cols-4 gap-2 pt-1 border-t border-base-200">
                            <div class="text-center">
                                <p class="text-lg font-extrabold text-primary leading-none">{{ exam.schedules_count }}</p>
                                <p class="text-[9px] font-semibold uppercase tracking-wider text-base-content/40 mt-1">Scheduled</p>
                            </div>
                            <div class="text-center">
                                <p class="text-lg font-extrabold text-secondary leading-none">{{ exam.seats_count }}</p>
                                <p class="text-[9px] font-semibold uppercase tracking-wider text-base-content/40 mt-1">Seats</p>
                            </div>
                            <div class="text-center">
                                <p class="text-lg font-extrabold text-accent leading-none">{{ exam.invigilators_count }}</p>
                                <p class="text-[9px] font-semibold uppercase tracking-wider text-base-content/40 mt-1">Invigilators</p>
                            </div>
                            <div class="text-center">
                                <span v-if="exam.is_fully_scheduled" class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-success/10 mx-auto">
                                    <CheckCircleIcon class="h-4 w-4 text-success" />
                                </span>
                                <span v-else class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-warning/10 mx-auto">
                                    <ClockIcon class="h-4 w-4 text-warning" />
                                </span>
                                <p class="text-[9px] font-semibold uppercase tracking-wider text-base-content/40 mt-1">{{ exam.is_fully_scheduled ? 'Ready' : 'Pending' }}</p>
                            </div>
                        </div>
                    </div>
                </Link>
            </div>

            <!-- EMPTY STATE -->
            <div v-else-if="search" class="surface">
                <EmptyState
                    title="No exams match your search"
                    description="Try a different keyword or clear the search."
                />
            </div>
            <div v-else class="surface">
                <EmptyState
                    title="No exams to schedule"
                    description="Create an exam first, then come back here to manage its date sheet, seating, and invigilators."
                    action-text="Create Exam"
                    :action-href="route('exams.create')"
                />
            </div>

            <!-- HELP NOTE -->
            <div class="rounded-2xl border border-info/15 bg-info/5 p-5 flex items-start gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-info/15">
                    <PlusCircleIcon class="h-5 w-5 text-info" />
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-bold">Getting Started with Exam Scheduling</h3>
                    <ol class="mt-2 space-y-1 text-[12.5px] text-base-content/65 list-decimal ml-5">
                        <li>First, add <Link :href="route('scheduling.rooms')" class="link link-primary font-semibold">exam rooms</Link> for your school with capacity, rows, and columns.</li>
                        <li>Click any exam above to open its scheduling hub.</li>
                        <li>Set the <strong>Date Sheet</strong> (which subject on which date/time), assign <strong>Seating</strong> per section, assign <strong>Invigilators</strong> per room.</li>
                        <li>Finally, download <strong>Admit Cards</strong> for all students with QR verification.</li>
                    </ol>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
