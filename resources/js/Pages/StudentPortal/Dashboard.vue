<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    UserCircleIcon, AcademicCapIcon, BuildingOfficeIcon,
    ChartBarIcon, ClipboardDocumentListIcon, TrophyIcon,
    CheckCircleIcon, XCircleIcon, ArrowRightIcon, CalendarIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    student: Object,
    currentSession: Object,
    academicHistory: Array,
    stats: Object,
    recentResults: Array,
})

function fmtPct(v) {
    if (v === null || v === undefined) return '-'
    return `${Number(v).toFixed(2)}%`
}
</script>

<template>
    <Head title="My Dashboard" />
    <AppLayout :breadcrumbs="[{ label: 'My Dashboard' }]">
        <div class="space-y-6">
            <!-- Welcome Header -->
            <div class="rounded-2xl bg-gradient-to-br from-primary to-secondary p-6 text-white shadow-lg">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white/20 ring-4 ring-white/30">
                        <img v-if="student?.photo_url" :src="student.photo_url" :alt="student.name" class="h-full w-full object-cover" />
                        <UserCircleIcon v-else class="h-12 w-12 text-white/90" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm uppercase tracking-wider text-white/70">Welcome back</p>
                        <h1 class="text-2xl font-bold sm:text-3xl">{{ student?.name }}</h1>
                        <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-white/85">
                            <span>Admission No: <strong>{{ student?.admission_no }}</strong></span>
                            <span v-if="student?.roll_no">Roll No: <strong>{{ student?.roll_no }}</strong></span>
                            <span v-if="student?.class">Class: <strong>{{ student.class.name }}{{ student.section ? ' - ' + student.section.name : '' }}</strong></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Academic Info + Quick Stats -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <!-- Academic Info -->
                <div class="card bg-base-100 shadow-md lg:col-span-1">
                    <div class="card-body p-5">
                        <div class="flex items-center gap-2 mb-3">
                            <AcademicCapIcon class="h-5 w-5 text-primary" />
                            <h2 class="text-base font-bold">Academic Info</h2>
                        </div>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-base-content/55">School</dt>
                                <dd class="font-medium text-right">{{ student?.school?.name || '-' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-base-content/55">Class</dt>
                                <dd class="font-medium text-right">{{ student?.class?.name || '-' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-base-content/55">Section</dt>
                                <dd class="font-medium text-right">{{ student?.section?.name || '-' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-base-content/55">Current Session</dt>
                                <dd class="font-medium text-right">{{ currentSession?.name || student?.session?.name || '-' }}</dd>
                            </div>
                            <div v-if="student?.father_name" class="flex justify-between gap-3">
                                <dt class="text-base-content/55">Father</dt>
                                <dd class="font-medium text-right">{{ student.father_name }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:col-span-2">
                    <div class="card bg-base-100 shadow-md">
                        <div class="card-body p-5">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                    <ClipboardDocumentListIcon class="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-base-content/50">Exams</p>
                                    <p class="text-2xl font-bold">{{ stats?.totalExams ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card bg-base-100 shadow-md">
                        <div class="card-body p-5">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-success/10">
                                    <ChartBarIcon class="h-5 w-5 text-success" />
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-base-content/50">Latest Grade</p>
                                    <p class="text-2xl font-bold">{{ stats?.latestGrade || '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card bg-base-100 shadow-md">
                        <div class="card-body p-5">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10">
                                    <TrophyIcon class="h-5 w-5 text-warning" />
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-base-content/50">Latest Position</p>
                                    <p class="text-2xl font-bold">{{ stats?.latestPosition ? '#' + stats.latestPosition : '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Results -->
            <div class="card bg-base-100 shadow-md">
                <div class="card-body p-5">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <ChartBarIcon class="h-5 w-5 text-primary" />
                            <h2 class="text-base font-bold">Recent Results</h2>
                        </div>
                        <Link :href="route('student-portal.results')" class="btn btn-ghost btn-xs gap-1">
                            View All <ArrowRightIcon class="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <div v-if="recentResults?.length" class="space-y-2">
                        <Link
                            v-for="r in recentResults"
                            :key="r.id"
                            :href="route('student-portal.result-detail', r.id)"
                            class="flex items-center gap-4 rounded-lg border border-base-200 p-3 transition hover:border-primary/40 hover:bg-base-200/40"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                                :class="r.is_passed ? 'bg-success/10 text-success' : 'bg-error/10 text-error'">
                                <CheckCircleIcon v-if="r.is_passed" class="h-5 w-5" />
                                <XCircleIcon v-else class="h-5 w-5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold truncate">{{ r.exam_name }}</p>
                                <p class="text-xs text-base-content/55 truncate">{{ r.exam_type }} &middot; {{ r.session_name }}</p>
                            </div>
                            <div class="text-right hidden sm:block">
                                <p class="text-sm font-bold">{{ fmtPct(r.percentage) }}</p>
                                <p class="text-xs text-base-content/55">Grade {{ r.grade || '-' }}</p>
                            </div>
                            <ArrowRightIcon class="h-4 w-4 text-base-content/30" />
                        </Link>
                    </div>
                    <div v-else class="py-8 text-center text-sm text-base-content/55">
                        No results yet. Once your exams are graded they will appear here.
                    </div>
                </div>
            </div>

            <!-- Academic History -->
            <div v-if="academicHistory?.length" class="card bg-base-100 shadow-md">
                <div class="card-body p-5">
                    <div class="flex items-center gap-2 mb-3">
                        <CalendarIcon class="h-5 w-5 text-primary" />
                        <h2 class="text-base font-bold">Academic History</h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="s in academicHistory"
                            :key="s.id"
                            class="badge badge-outline gap-1"
                        >
                            <CalendarIcon class="h-3 w-3" />
                            {{ s.name }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
