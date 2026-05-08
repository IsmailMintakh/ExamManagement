<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    UserCircleIcon, AcademicCapIcon,
    ChartBarIcon, ClipboardDocumentListIcon, TrophyIcon,
    CheckCircleIcon, XCircleIcon, ArrowRightIcon, CalendarIcon,
    UserGroupIcon, ClockIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    role: String,                  // 'student' | 'parent'
    students: { type: Array, default: () => [] },
    activeStudentId: Number,
    student: Object,
    currentSession: Object,
    stats: Object,
    recentResults: { type: Array, default: () => [] },
    upcoming: { type: Array, default: () => [] },
})

function fmtPct(v) {
    if (v === null || v === undefined) return '-'
    return `${Number(v).toFixed(2)}%`
}

// Switch the active child (parent only). Reloads the dashboard for the
// selected student.
function switchTo(studentId) {
    router.get(route('portal.dashboard'), { student_id: studentId }, { preserveScroll: true })
}
</script>

<template>
    <Head :title="role === 'parent' ? `${student?.name} — Family Portal` : 'My Dashboard'" />
    <AppLayout :breadcrumbs="[{ label: role === 'parent' ? 'Family Portal' : 'My Dashboard' }]">
        <div class="space-y-6">

            <!-- Child picker (only when parent has 2+ children) -->
            <div v-if="role === 'parent' && students.length > 1"
                class="card bg-base-100 shadow-sm">
                <div class="card-body p-3">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-base-content/55 mb-2 flex items-center gap-1.5">
                        <UserGroupIcon class="h-3.5 w-3.5" /> Viewing
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="child in students" :key="child.id"
                            type="button"
                            @click="switchTo(child.id)"
                            class="flex items-center gap-2 rounded-xl border-2 px-3 py-2 text-left transition-colors"
                            :class="child.id === activeStudentId
                                ? 'border-primary bg-primary/5'
                                : 'border-base-200 hover:border-primary/40 hover:bg-base-200/40'">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-base-200">
                                <img v-if="child.photo_url" :src="child.photo_url" :alt="child.name" class="h-full w-full object-cover" />
                                <UserCircleIcon v-else class="h-5 w-5 text-base-content/40" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold truncate">{{ child.name }}</p>
                                <p class="text-[10px] text-base-content/55">
                                    {{ child.class_name || '—' }}{{ child.section_name ? ' · ' + child.section_name : '' }}
                                </p>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Welcome Header -->
            <div class="rounded-2xl bg-gradient-to-br from-primary to-secondary p-6 text-white shadow-lg">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white/20 ring-4 ring-white/30">
                        <img v-if="student?.photo_url" :src="student.photo_url" :alt="student.name" class="h-full w-full object-cover" />
                        <UserCircleIcon v-else class="h-12 w-12 text-white/90" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm uppercase tracking-wider text-white/70">
                            {{ role === 'parent' ? 'Viewing' : 'Welcome back' }}
                        </p>
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
                                    <p class="text-xs uppercase tracking-wide text-base-content/50">Latest %</p>
                                    <p class="text-2xl font-bold">{{ fmtPct(stats?.latestPercentage) }}</p>
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
                                    <p class="text-2xl font-bold">{{ stats?.latestPosition || '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming papers -->
            <div v-if="upcoming.length" class="card bg-base-100 shadow-md">
                <div class="card-body p-5">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <CalendarIcon class="h-5 w-5 text-info" />
                            <h2 class="text-base font-bold">Upcoming papers</h2>
                            <span class="badge badge-sm badge-ghost">next 14 days</span>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Exam</th>
                                    <th>Date</th>
                                    <th>Day</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(u, i) in upcoming" :key="i">
                                    <td class="font-semibold">{{ u.subject || '—' }}</td>
                                    <td class="text-base-content/70">{{ u.exam_name || '—' }}</td>
                                    <td>{{ u.date || '—' }}</td>
                                    <td>{{ u.day || '—' }}</td>
                                    <td class="font-mono text-xs">{{ u.time || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent results -->
            <div class="card bg-base-100 shadow-md">
                <div class="card-body p-5">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <ChartBarIcon class="h-5 w-5 text-primary" />
                            <h2 class="text-base font-bold">Recent results</h2>
                        </div>
                        <Link :href="route('portal.results', { student_id: activeStudentId })"
                            class="text-xs text-primary font-semibold hover:underline flex items-center gap-1">
                            View all <ArrowRightIcon class="h-3 w-3" />
                        </Link>
                    </div>

                    <div v-if="!recentResults.length" class="rounded-xl border-2 border-dashed border-base-200 p-8 text-center">
                        <ClockIcon class="h-8 w-8 mx-auto text-base-content/30" />
                        <p class="mt-2 text-sm text-base-content/55">No results yet.</p>
                    </div>

                    <div v-else class="space-y-2">
                        <Link v-for="r in recentResults" :key="r.id"
                            :href="route('portal.result-detail', r.id)"
                            class="flex items-center justify-between rounded-xl border border-base-200 p-3 hover:bg-base-200/40 transition-colors">
                            <div class="min-w-0">
                                <p class="font-semibold truncate">{{ r.exam_name }}</p>
                                <p class="text-xs text-base-content/55">
                                    {{ r.exam_type || 'Exam' }}<span v-if="r.session_name"> · {{ r.session_name }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <div class="text-right">
                                    <p class="text-sm font-bold">{{ fmtPct(r.percentage) }}</p>
                                    <p class="text-[11px] text-base-content/55">
                                        Position {{ r.position || '—' }} · Grade {{ r.grade || '—' }}
                                    </p>
                                </div>
                                <CheckCircleIcon v-if="r.is_passed" class="h-5 w-5 text-success shrink-0" />
                                <XCircleIcon v-else class="h-5 w-5 text-error shrink-0" />
                            </div>
                        </Link>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
