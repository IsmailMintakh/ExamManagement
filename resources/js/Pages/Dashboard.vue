<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
    AcademicCapIcon, BuildingOfficeIcon, UserGroupIcon,
    ClipboardDocumentListIcon, ChartBarIcon, CheckCircleIcon,
    ArrowTrendingUpIcon, CalendarDaysIcon, DocumentTextIcon,
    BookOpenIcon, ClockIcon, PlusCircleIcon, TableCellsIcon,
    ArrowRightIcon, SparklesIcon, BoltIcon, FireIcon,
    TrophyIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    stats: Object,
    role: String,
    currentSession: Object,
    recentExams: Array,
    schoolWiseComparison: Array,
    classWisePerformance: Array,
    sections: Array,
    assignments: Array,
    pendingExams: Array,
})

const page = usePage()
const userName = computed(() => page.props.auth?.user?.name?.split(' ')[0] || 'User')
const roles = computed(() => page.props.auth?.user?.roles || [])
const permissions = computed(() => page.props.auth?.user?.permissions || [])
const hasRole = (r) => roles.value.includes(r)
const hasPerm = (p) => hasRole('super-admin') || permissions.value.includes(p)

const greeting = computed(() => {
    const h = new Date().getHours()
    if (h < 12) return 'Good morning'
    if (h < 17) return 'Good afternoon'
    return 'Good evening'
})

const todayLabel = computed(() => new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' }))

const roleLabels = {
    'super-admin': 'District Admin',
    'school-admin': 'Principal',
    'class-teacher': 'Class Teacher',
    'subject-teacher': 'Subject Teacher',
}

const statusBadge = (status) => ({
    draft: 'badge-ghost',
    published: 'badge-info',
    marks_entry: 'badge-warning',
    completed: 'badge-success',
}[status] || 'badge-ghost')

const statusLabel = (s) => s?.replace(/_/g, ' ') || '—'
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout :breadcrumbs="[{ label: 'Dashboard' }]">
        <div class="space-y-6">

            <!-- ============ HERO BANNER ============ -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-secondary p-6 text-primary-content shadow-xl shadow-primary/30 sm:p-8">
                <div class="relative z-10 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-widest opacity-75">{{ todayLabel }}</p>
                        <h1 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-[1.75rem]">
                            {{ greeting }}, {{ userName }}!
                        </h1>
                        <p class="mt-1.5 text-sm opacity-85">Here's what's happening with your exams today.</p>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-1.5 text-xs font-medium backdrop-blur-sm">
                                <CalendarDaysIcon class="h-3.5 w-3.5" />
                                {{ currentSession?.name || 'No Active Session' }}
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-lg bg-white/20 px-3 py-1.5 text-xs font-bold backdrop-blur-sm">
                                <SparklesIcon class="h-3 w-3" />
                                {{ roleLabels[role] || role }}
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link v-if="role === 'super-admin'" :href="route('exams.create')"
                            class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-semibold backdrop-blur-md transition-all hover:bg-white/25">
                            <PlusCircleIcon class="h-4 w-4" /> New Exam
                        </Link>
                        <Link :href="route('reports.index')"
                            class="inline-flex items-center gap-2 rounded-xl bg-white text-primary px-4 py-2.5 text-sm font-semibold shadow-lg transition-all hover:shadow-xl">
                            <DocumentTextIcon class="h-4 w-4" /> Reports
                        </Link>
                    </div>
                </div>
                <!-- Decorative -->
                <div class="absolute -right-10 -top-10 h-48 w-48 rounded-full bg-white/5 blur-2xl" />
                <div class="absolute -bottom-16 right-32 h-40 w-40 rounded-full bg-white/5 blur-3xl" />
                <div class="absolute -bottom-8 -right-4 h-32 w-32 rounded-full bg-white/5" />
            </div>

            <!-- ============ STATS ============ -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <template v-if="role === 'super-admin'">
                    <StatCard title="Schools" :value="stats?.totalSchools ?? 0" subtitle="Active schools" color="primary">
                        <template #icon><BuildingOfficeIcon class="h-5 w-5" /></template>
                    </StatCard>
                    <StatCard title="Students" :value="stats?.totalStudents ?? 0" subtitle="Enrolled this session" color="secondary">
                        <template #icon><UserGroupIcon class="h-5 w-5" /></template>
                    </StatCard>
                    <StatCard title="Teachers" :value="stats?.totalTeachers ?? 0" subtitle="All schools" color="accent">
                        <template #icon><AcademicCapIcon class="h-5 w-5" /></template>
                    </StatCard>
                    <StatCard title="Pass Rate" :value="(stats?.passRate ?? 0) + '%'" subtitle="Overall performance" color="success">
                        <template #icon><ArrowTrendingUpIcon class="h-5 w-5" /></template>
                    </StatCard>
                </template>

                <template v-if="role === 'school-admin'">
                    <StatCard title="Students" :value="stats?.totalStudents ?? 0" subtitle="Enrolled" color="primary">
                        <template #icon><UserGroupIcon class="h-5 w-5" /></template>
                    </StatCard>
                    <StatCard title="Classes" :value="stats?.totalClasses ?? 0" subtitle="Active" color="secondary">
                        <template #icon><AcademicCapIcon class="h-5 w-5" /></template>
                    </StatCard>
                    <StatCard title="Teachers" :value="stats?.totalTeachers ?? 0" subtitle="In your school" color="accent">
                        <template #icon><BookOpenIcon class="h-5 w-5" /></template>
                    </StatCard>
                    <StatCard title="Pass Rate" :value="(stats?.passRate ?? 0) + '%'" subtitle="School performance" color="success">
                        <template #icon><ArrowTrendingUpIcon class="h-5 w-5" /></template>
                    </StatCard>
                </template>

                <template v-if="role === 'class-teacher'">
                    <StatCard title="My Sections" :value="stats?.totalSections ?? 0" subtitle="Assigned" color="primary">
                        <template #icon><TableCellsIcon class="h-5 w-5" /></template>
                    </StatCard>
                    <StatCard title="Students" :value="stats?.totalStudents ?? 0" subtitle="In your sections" color="secondary">
                        <template #icon><UserGroupIcon class="h-5 w-5" /></template>
                    </StatCard>
                    <StatCard title="Pending Entry" :value="stats?.pendingMarksEntry ?? 0" subtitle="Marks to enter" color="warning">
                        <template #icon><ClockIcon class="h-5 w-5" /></template>
                    </StatCard>
                </template>

                <template v-if="role === 'subject-teacher'">
                    <StatCard title="Subjects" :value="stats?.assignedSubjects ?? 0" subtitle="Assigned" color="primary">
                        <template #icon><BookOpenIcon class="h-5 w-5" /></template>
                    </StatCard>
                    <StatCard title="Sections" :value="stats?.assignedSections ?? 0" subtitle="Teaching" color="secondary">
                        <template #icon><TableCellsIcon class="h-5 w-5" /></template>
                    </StatCard>
                    <StatCard title="Pending Marks" :value="stats?.pendingMarksEntry ?? 0" subtitle="Need entry" color="warning">
                        <template #icon><ClockIcon class="h-5 w-5" /></template>
                    </StatCard>
                </template>
            </div>

            <!-- ============ QUICK ACTIONS ============ -->
            <div class="surface">
                <div class="surface-header">
                    <h3 class="flex items-center gap-2"><BoltIcon class="h-4 w-4 text-warning" /> Quick Actions</h3>
                </div>
                <div class="surface-body">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        <Link v-if="hasPerm('exams.create')" :href="route('exams.create')"
                            class="group flex flex-col items-start gap-2 rounded-xl border border-base-200 p-4 transition-all hover:border-primary/40 hover:bg-primary/5 hover:shadow-md">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 transition-transform group-hover:scale-110">
                                <ClipboardDocumentListIcon class="h-5 w-5 text-primary" />
                            </div>
                            <p class="text-[13px] font-semibold leading-tight">Create Exam</p>
                            <p class="text-[11px] text-base-content/50">Set up a new exam</p>
                        </Link>
                        <Link v-if="hasPerm('students.create')" :href="route('students.create')"
                            class="group flex flex-col items-start gap-2 rounded-xl border border-base-200 p-4 transition-all hover:border-secondary/40 hover:bg-secondary/5 hover:shadow-md">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-secondary/10 transition-transform group-hover:scale-110">
                                <UserGroupIcon class="h-5 w-5 text-secondary" />
                            </div>
                            <p class="text-[13px] font-semibold leading-tight">Add Student</p>
                            <p class="text-[11px] text-base-content/50">Enroll new student</p>
                        </Link>
                        <Link v-if="hasPerm('marks.enter')" :href="route('marks.index')"
                            class="group flex flex-col items-start gap-2 rounded-xl border border-base-200 p-4 transition-all hover:border-accent/40 hover:bg-accent/5 hover:shadow-md">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-accent/10 transition-transform group-hover:scale-110">
                                <DocumentTextIcon class="h-5 w-5 text-accent" />
                            </div>
                            <p class="text-[13px] font-semibold leading-tight">Enter Marks</p>
                            <p class="text-[11px] text-base-content/50">Submit student marks</p>
                        </Link>
                        <Link :href="route('reports.index')"
                            class="group flex flex-col items-start gap-2 rounded-xl border border-base-200 p-4 transition-all hover:border-info/40 hover:bg-info/5 hover:shadow-md">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-info/10 transition-transform group-hover:scale-110">
                                <ChartBarIcon class="h-5 w-5 text-info" />
                            </div>
                            <p class="text-[13px] font-semibold leading-tight">View Reports</p>
                            <p class="text-[11px] text-base-content/50">Generate PDF reports</p>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ============ CONTENT GRID ============ -->
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <!-- Recent Exams (2 cols) -->
                <div class="surface xl:col-span-2">
                    <div class="surface-header">
                        <h3 class="flex items-center gap-2"><ClipboardDocumentListIcon class="h-4 w-4 text-primary" /> Recent Exams</h3>
                        <Link v-if="hasPerm('exams.view')" :href="route('exams.index')"
                            class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
                            View All <ArrowRightIcon class="h-3 w-3" />
                        </Link>
                    </div>
                    <div class="overflow-x-auto">
                        <table v-if="recentExams?.length" class="table">
                            <thead>
                                <tr>
                                    <th>Exam</th>
                                    <th>Type</th>
                                    <th class="hidden sm:table-cell">Schedule</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="exam in recentExams" :key="exam.id" class="hover">
                                    <td>
                                        <Link :href="route('exams.show', exam.id)" class="font-semibold hover:text-primary transition-colors">
                                            {{ exam.name }}
                                        </Link>
                                    </td>
                                    <td>
                                        <span class="badge badge-sm badge-outline">{{ exam.type }}</span>
                                    </td>
                                    <td class="hidden sm:table-cell text-xs text-base-content/55">
                                        {{ exam.start_date }} → {{ exam.end_date }}
                                    </td>
                                    <td>
                                        <span class="badge badge-sm capitalize" :class="statusBadge(exam.status)">{{ statusLabel(exam.status) }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else class="empty-state py-12">
                            <div class="empty-state-icon">
                                <ClipboardDocumentListIcon class="h-7 w-7 text-base-content/30" />
                            </div>
                            <p class="text-sm font-semibold">No exams yet</p>
                            <p class="mt-1 text-xs text-base-content/50">Create your first exam to get started.</p>
                        </div>
                    </div>
                </div>

                <!-- Session Info -->
                <div class="surface">
                    <div class="surface-header">
                        <h3 class="flex items-center gap-2"><CalendarDaysIcon class="h-4 w-4 text-secondary" /> Overview</h3>
                    </div>
                    <div class="surface-body space-y-4">
                        <div class="rounded-xl bg-gradient-to-br from-primary/5 to-secondary/5 p-4">
                            <p class="text-[10px] uppercase tracking-widest text-base-content/45">Current Session</p>
                            <p class="mt-1 text-lg font-extrabold text-gradient-primary">{{ currentSession?.name || 'None' }}</p>
                        </div>

                        <div class="space-y-3">
                            <div v-if="role === 'super-admin'" class="flex items-center justify-between text-[13px]">
                                <span class="text-base-content/55">Pending Results</span>
                                <span class="font-bold text-warning">{{ stats?.pendingResults ?? 0 }}</span>
                            </div>
                            <div v-if="role === 'school-admin'" class="flex items-center justify-between text-[13px]">
                                <span class="text-base-content/55">Total Sections</span>
                                <span class="font-bold">{{ stats?.totalSections ?? 0 }}</span>
                            </div>
                            <div v-if="role === 'school-admin'" class="flex items-center justify-between text-[13px]">
                                <span class="text-base-content/55">Active Exams</span>
                                <span class="font-bold text-info">{{ stats?.activeExams ?? 0 }}</span>
                            </div>
                            <div class="flex items-center justify-between text-[13px]">
                                <span class="text-base-content/55">Your Role</span>
                                <span class="font-bold">{{ roleLabels[role] || role }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ SCHOOL COMPARISON (Super Admin) ============ -->
            <div v-if="role === 'super-admin' && schoolWiseComparison?.length" class="surface">
                <div class="surface-header">
                    <h3 class="flex items-center gap-2"><TrophyIcon class="h-4 w-4 text-warning" /> School Performance</h3>
                    <Link :href="route('analytics.index')" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
                        Full Analytics <ArrowRightIcon class="h-3 w-3" />
                    </Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>School</th>
                                <th class="hidden sm:table-cell">Students</th>
                                <th>Pass Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(school, idx) in schoolWiseComparison" :key="school.id" class="hover">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 items-center justify-center rounded-lg text-xs font-bold"
                                            :class="idx === 0 ? 'bg-warning/15 text-warning' : idx === 1 ? 'bg-base-300 text-base-content/70' : idx === 2 ? 'bg-orange-500/15 text-orange-600' : 'bg-primary/10 text-primary'">
                                            #{{ idx + 1 }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-sm truncate">{{ school.name }}</p>
                                            <p class="text-[11px] text-base-content/45">{{ school.code }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden sm:table-cell font-semibold">{{ school.students_count ?? 0 }}</td>
                                <td>
                                    <div class="flex items-center gap-3 min-w-[180px]">
                                        <div class="progress-bar flex-1">
                                            <div class="progress-bar-fill bg-success" :style="{ width: (school.pass_percentage || 0) + '%' }" />
                                        </div>
                                        <span class="text-xs font-bold w-10 text-right">{{ school.pass_percentage || 0 }}%</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============ CLASS PERFORMANCE (School Admin) ============ -->
            <div v-if="role === 'school-admin' && classWisePerformance?.length" class="surface">
                <div class="surface-header">
                    <h3 class="flex items-center gap-2"><AcademicCapIcon class="h-4 w-4 text-secondary" /> Class Performance</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr><th>Class</th><th class="hidden sm:table-cell">Students</th><th>Pass Rate</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="cls in classWisePerformance" :key="cls.id" class="hover">
                                <td class="font-semibold">{{ cls.name }}</td>
                                <td class="hidden sm:table-cell">{{ cls.total_students ?? 0 }}</td>
                                <td>
                                    <div class="flex items-center gap-3 min-w-[180px]">
                                        <div class="progress-bar flex-1">
                                            <div class="progress-bar-fill bg-primary" :style="{ width: (cls.pass_percentage || 0) + '%' }" />
                                        </div>
                                        <span class="text-xs font-bold w-10 text-right">{{ cls.pass_percentage || 0 }}%</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============ MY SECTIONS (Class Teacher) ============ -->
            <div v-if="role === 'class-teacher' && sections?.length" class="surface">
                <div class="surface-header">
                    <h3 class="flex items-center gap-2"><TableCellsIcon class="h-4 w-4 text-accent" /> My Sections</h3>
                </div>
                <div class="surface-body">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="section in sections" :key="section.id" class="rounded-xl border border-base-200 p-4 transition-all hover:border-primary/30 hover:shadow-md">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-accent/10 text-sm font-bold text-accent">
                                    {{ section.name?.charAt(0) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-sm truncate">{{ section.class_name }} - {{ section.name }}</p>
                                    <p class="text-xs text-base-content/55">{{ section.students_count }} students</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ MY ASSIGNMENTS (Subject Teacher) ============ -->
            <div v-if="role === 'subject-teacher' && assignments?.length" class="surface">
                <div class="surface-header">
                    <h3 class="flex items-center gap-2"><BookOpenIcon class="h-4 w-4 text-info" /> My Assignments</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Subject</th><th>Class</th><th>Section</th></tr></thead>
                        <tbody>
                            <tr v-for="a in assignments" :key="a.id" class="hover">
                                <td class="font-semibold">{{ a.subject_name }}</td>
                                <td>{{ a.class_name }}</td>
                                <td>
                                    <span class="badge badge-sm badge-outline">{{ a.section_name }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
