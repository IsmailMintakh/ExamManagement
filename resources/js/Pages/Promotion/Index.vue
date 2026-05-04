<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
    ArrowPathIcon,
    InformationCircleIcon,
    UserGroupIcon,
    CheckCircleIcon,
    ClockIcon,
    AcademicCapIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    classes: { type: Array, default: () => [] },
    currentSession: { type: Object, default: null },
})

const totalStudents = computed(() => props.classes.reduce((acc, c) => acc + (c.total_students || 0), 0))
const totalPromoted = computed(() => props.classes.reduce((acc, c) => acc + (c.promoted_count || 0), 0))
const totalPending = computed(() => props.classes.reduce((acc, c) => acc + (c.pending_count || 0), 0))
</script>

<template>
    <Head title="Student Promotion" />
    <AppLayout :breadcrumbs="[{ label: 'Students', href: '/students' }, { label: 'Promotion' }]">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <ArrowPathIcon class="h-6 w-6 text-primary" />
                        Student Promotion
                    </h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        Manage year-end promotions, retentions, and graduations for each class.
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
                    <p class="font-semibold">Year-End Promotion Workflow</p>
                    <p class="text-xs opacity-80 mt-0.5">
                        Select a class to review students. For each student you can <strong>Promote</strong> to the next class,
                        <strong>Retain</strong> them in the current class, or mark them as <strong>Graduated</strong>.
                        Make sure all results are finalized before processing promotions.
                    </p>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="surface !p-4 flex flex-row items-center gap-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/15 text-primary">
                        <UserGroupIcon class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="section-eyebrow">Total Students</p>
                        <p class="text-2xl font-extrabold tabular-nums">{{ totalStudents }}</p>
                    </div>
                </div>
                <div class="surface !p-4 flex flex-row items-center gap-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-success/15 text-success">
                        <CheckCircleIcon class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="section-eyebrow">Processed</p>
                        <p class="text-2xl font-extrabold tabular-nums">{{ totalPromoted }}</p>
                    </div>
                </div>
                <div class="surface !p-4 flex flex-row items-center gap-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-warning/15 text-warning">
                        <ClockIcon class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="section-eyebrow">Pending</p>
                        <p class="text-2xl font-extrabold tabular-nums">{{ totalPending }}</p>
                    </div>
                </div>
            </div>

            <!-- Class Cards Grid -->
            <div v-if="classes.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="cls in classes"
                    :key="cls.id"
                    class="surface surface-hover !p-5"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/15 text-primary">
                                <AcademicCapIcon class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-base font-bold leading-tight">{{ cls.name }}</h3>
                                <p v-if="cls.school_name" class="text-[11px] text-base-content/55 mt-0.5">
                                    {{ cls.school_name }}
                                </p>
                            </div>
                        </div>
                        <span v-if="cls.pending_count === 0 && cls.total_students > 0" class="badge badge-success badge-sm">Done</span>
                        <span v-else-if="cls.promoted_count > 0" class="badge badge-warning badge-sm">In Progress</span>
                        <span v-else class="badge badge-ghost badge-sm">Pending</span>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2 rounded-lg border border-base-200 bg-base-200/40 p-3">
                        <div class="text-center">
                            <p class="section-eyebrow">Total</p>
                            <p class="text-lg font-extrabold tabular-nums">{{ cls.total_students }}</p>
                        </div>
                        <div class="text-center border-x border-base-300/40">
                            <p class="section-eyebrow text-success/70">Promoted</p>
                            <p class="text-lg font-extrabold text-success tabular-nums">{{ cls.promoted_count }}</p>
                        </div>
                        <div class="text-center">
                            <p class="section-eyebrow text-warning/80">Pending</p>
                            <p class="text-lg font-extrabold text-warning tabular-nums">{{ cls.pending_count }}</p>
                        </div>
                    </div>

                    <Link
                        :href="route('promotion.show', cls.id)"
                        class="btn btn-primary btn-sm w-full gap-2 mt-4"
                        :class="{ 'btn-disabled opacity-50': cls.total_students === 0 }"
                    >
                        <ArrowPathIcon class="h-4 w-4" />
                        Manage Promotion
                    </Link>
                </div>
            </div>

            <EmptyState
                v-else
                title="No classes available"
                description="There are no classes set up yet. Add classes and students before running promotions."
            />
        </div>
    </AppLayout>
</template>
