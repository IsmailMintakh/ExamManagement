<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
    TrophyIcon, ChartBarIcon, BuildingOfficeIcon, UserGroupIcon,
    AcademicCapIcon, BookOpenIcon, ArrowTrendingUpIcon, ClipboardDocumentListIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    overview: { type: Object, default: () => ({}) },
    schoolLeaderboard: { type: Array, default: () => [] },
    subjectPerformance: { type: Array, default: () => [] },
    classPerformance: { type: Array, default: () => [] },
    recentTrend: { type: Array, default: () => [] },
    topPerformers: { type: Array, default: () => [] },
    isSuperAdmin: { type: Boolean, default: false },
    currentSession: { type: Object, default: null },
})

const trendMax = computed(() => {
    const max = Math.max(...props.recentTrend.map((t) => t.pass_rate || 0), 1)
    return max < 10 ? 100 : max
})

function trophyClass(rank) {
    if (rank === 0) return 'text-yellow-500'
    if (rank === 1) return 'text-slate-400'
    if (rank === 2) return 'text-amber-700'
    return 'text-base-content/30'
}

function rankBadgeClass(rank) {
    if (rank === 0) return 'bg-yellow-500/15 text-yellow-600 ring-yellow-500/20'
    if (rank === 1) return 'bg-slate-400/15 text-slate-500 ring-slate-400/20'
    if (rank === 2) return 'bg-amber-700/15 text-amber-700 ring-amber-700/20'
    return 'bg-base-200 text-base-content/60 ring-base-300'
}

function passRateBarColor(rate) {
    if (rate >= 80) return 'bg-success'
    if (rate >= 60) return 'bg-info'
    if (rate >= 40) return 'bg-warning'
    return 'bg-error'
}

function passRateTextColor(rate) {
    if (rate >= 80) return 'text-success'
    if (rate >= 60) return 'text-info'
    if (rate >= 40) return 'text-warning'
    return 'text-error'
}

function gradeBadgeClass(grade) {
    if (!grade) return 'badge-ghost'
    const g = String(grade).toUpperCase()
    if (g.startsWith('A')) return 'badge-success'
    if (g.startsWith('B')) return 'badge-info'
    if (g.startsWith('C')) return 'badge-warning'
    return 'badge-error'
}
</script>

<template>
    <Head title="Analytics & Insights" />
    <AppLayout :breadcrumbs="[{ label: 'Analytics' }]">
        <div class="space-y-6">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">Analytics &amp; Insights</h1>
                    <p class="page-subtitle">Performance overview across the cluster</p>
                </div>
                <div v-if="currentSession" class="hidden items-center gap-2 rounded-xl border border-base-200 bg-base-100 px-3 py-2 text-xs font-medium sm:flex">
                    <span class="text-base-content/40">Session</span>
                    <span class="font-semibold">{{ currentSession.name }}</span>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    v-if="isSuperAdmin"
                    title="Total Schools"
                    :value="overview.totalSchools ?? 0"
                    :subtitle="`${overview.totalStudents ?? 0} students`"
                    color="primary"
                >
                    <template #icon>
                        <BuildingOfficeIcon class="h-5 w-5" />
                    </template>
                </StatCard>

                <StatCard
                    title="Total Students"
                    :value="overview.totalStudents ?? 0"
                    subtitle="Active enrollment"
                    color="secondary"
                >
                    <template #icon>
                        <UserGroupIcon class="h-5 w-5" />
                    </template>
                </StatCard>

                <StatCard
                    title="Total Exams"
                    :value="overview.totalExams ?? 0"
                    :subtitle="`${overview.totalResults ?? 0} results processed`"
                    color="accent"
                >
                    <template #icon>
                        <ClipboardDocumentListIcon class="h-5 w-5" />
                    </template>
                </StatCard>

                <StatCard
                    title="Overall Pass Rate"
                    :value="`${overview.overallPassRate ?? 0}%`"
                    :subtitle="`Avg ${overview.avgPercentage ?? 0}%`"
                    :color="overview.overallPassRate >= 60 ? 'success' : 'warning'"
                >
                    <template #icon>
                        <ArrowTrendingUpIcon class="h-5 w-5" />
                    </template>
                </StatCard>
            </div>

            <!-- Two-column: School Leaderboard + Recent Trend -->
            <div class="grid grid-cols-1 gap-6" :class="isSuperAdmin ? 'lg:grid-cols-3' : 'lg:grid-cols-1'">
                <!-- School Leaderboard (super-admin only) -->
                <div v-if="isSuperAdmin" class="card-section lg:col-span-2">
                    <div class="card-content space-y-4">
                        <div class="flex items-center gap-3 border-b border-base-200 pb-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <TrophyIcon class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold">School Leaderboard</h3>
                                <p class="text-xs text-base-content/50">Schools ranked by overall pass rate</p>
                            </div>
                        </div>

                        <div v-if="!schoolLeaderboard.length" class="py-10 text-center text-sm text-base-content/40">
                            No school data available yet.
                        </div>

                        <ol v-else class="space-y-2">
                            <li
                                v-for="(school, i) in schoolLeaderboard"
                                :key="school.id"
                                class="flex items-center gap-4 rounded-xl border border-base-200 bg-base-100 px-4 py-3 transition-colors hover:border-primary/30 hover:bg-primary/5"
                            >
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1" :class="rankBadgeClass(i)">
                                    <TrophyIcon v-if="i < 3" class="h-5 w-5" :class="trophyClass(i)" />
                                    <span v-else class="text-sm font-bold">{{ i + 1 }}</span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold">{{ school.name }}</p>
                                            <p class="text-xs text-base-content/50">
                                                <span v-if="school.code">{{ school.code }} &middot; </span>
                                                {{ school.total_students }} students &middot; {{ school.total_results }} results
                                            </p>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p class="text-base font-bold tracking-tight" :class="passRateTextColor(school.pass_rate)">
                                                {{ school.pass_rate }}%
                                            </p>
                                            <p class="text-2xs text-base-content/50">avg {{ school.avg_percentage }}%</p>
                                        </div>
                                    </div>
                                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-base-200">
                                        <div
                                            class="h-full rounded-full transition-all"
                                            :class="passRateBarColor(school.pass_rate)"
                                            :style="{ width: `${Math.min(school.pass_rate, 100)}%` }"
                                        />
                                    </div>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- Recent Trend -->
                <div class="card-section">
                    <div class="card-content space-y-4">
                        <div class="flex items-center gap-3 border-b border-base-200 pb-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-info/10 text-info">
                                <ArrowTrendingUpIcon class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold">Recent Trend</h3>
                                <p class="text-xs text-base-content/50">Pass rate across last 6 sessions</p>
                            </div>
                        </div>

                        <div v-if="!recentTrend.length" class="py-10 text-center text-sm text-base-content/40">
                            No session history yet.
                        </div>

                        <div v-else class="space-y-3">
                            <!-- Bar chart -->
                            <div class="flex h-40 items-end gap-2">
                                <div
                                    v-for="(t, i) in recentTrend"
                                    :key="i"
                                    class="group flex flex-1 flex-col items-center gap-1.5"
                                >
                                    <span class="text-2xs font-bold opacity-0 transition group-hover:opacity-100" :class="passRateTextColor(t.pass_rate)">
                                        {{ t.pass_rate }}%
                                    </span>
                                    <div
                                        class="w-full rounded-t-lg transition-all"
                                        :class="passRateBarColor(t.pass_rate)"
                                        :style="{ height: `${Math.max((t.pass_rate / trendMax) * 100, 2)}%`, minHeight: '4px' }"
                                        :title="`${t.session_name}: ${t.pass_rate}%`"
                                    />
                                </div>
                            </div>
                            <!-- Labels -->
                            <div class="flex gap-2">
                                <div
                                    v-for="(t, i) in recentTrend"
                                    :key="i"
                                    class="flex flex-1 flex-col items-center gap-0.5 text-center"
                                >
                                    <span class="truncate text-2xs font-medium text-base-content/60" :title="t.session_name">{{ t.session_name }}</span>
                                    <span class="text-[10px] text-base-content/40">{{ t.total_results }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Performers -->
            <div class="card-section">
                <div class="card-content space-y-4">
                    <div class="flex items-center gap-3 border-b border-base-200 pb-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-warning/10 text-warning">
                            <TrophyIcon class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold">Top Performers</h3>
                            <p class="text-xs text-base-content/50">Highest scoring students {{ isSuperAdmin ? 'cluster-wide' : 'in your school' }}</p>
                        </div>
                    </div>

                    <div v-if="!topPerformers.length" class="py-10 text-center text-sm text-base-content/40">
                        No results published yet.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="table-clean">
                            <thead>
                                <tr>
                                    <th class="w-12">Rank</th>
                                    <th>Student</th>
                                    <th v-if="isSuperAdmin">School</th>
                                    <th>Class</th>
                                    <th>Exam</th>
                                    <th class="text-right">%</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(s, i) in topPerformers" :key="s.id">
                                    <td>
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-xs font-bold ring-1" :class="rankBadgeClass(i)">
                                            <TrophyIcon v-if="i < 3" class="h-3.5 w-3.5" :class="trophyClass(i)" />
                                            <span v-else>{{ i + 1 }}</span>
                                        </span>
                                    </td>
                                    <td>
                                        <p class="text-sm font-semibold">{{ s.student_name }}</p>
                                        <p class="text-xs text-base-content/50">{{ s.admission_no }}</p>
                                    </td>
                                    <td v-if="isSuperAdmin" class="text-xs">{{ s.school_name }}</td>
                                    <td class="text-xs">
                                        {{ s.class_name }}<span v-if="s.section_name"> &middot; {{ s.section_name }}</span>
                                    </td>
                                    <td class="text-xs">{{ s.exam_name }}</td>
                                    <td class="text-right font-bold" :class="passRateTextColor(s.percentage)">{{ Number(s.percentage).toFixed(2) }}%</td>
                                    <td><span class="badge badge-sm" :class="gradeBadgeClass(s.grade)">{{ s.grade || '-' }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Subject + Class Performance -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Subject Performance -->
                <div class="card-section">
                    <div class="card-content space-y-4">
                        <div class="flex items-center gap-3 border-b border-base-200 pb-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-secondary/10 text-secondary">
                                <BookOpenIcon class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold">Subject Performance</h3>
                                <p class="text-xs text-base-content/50">Average percentage per subject</p>
                            </div>
                        </div>

                        <div v-if="!subjectPerformance.length" class="py-10 text-center text-sm text-base-content/40">
                            No subject data available yet.
                        </div>

                        <ul v-else class="space-y-2.5">
                            <li
                                v-for="(s, i) in subjectPerformance"
                                :key="s.id"
                                class="rounded-xl border border-base-200 bg-base-100 px-4 py-3"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-2xs font-bold text-base-content/40">#{{ i + 1 }}</span>
                                            <p class="truncate text-sm font-semibold">{{ s.name }}</p>
                                            <span v-if="s.code" class="badge badge-ghost badge-xs">{{ s.code }}</span>
                                        </div>
                                        <p class="mt-0.5 text-xs text-base-content/50">{{ s.total_entries }} entries &middot; avg marks {{ Number(s.avg_marks).toFixed(2) }}</p>
                                    </div>
                                    <p class="shrink-0 text-base font-bold tracking-tight" :class="passRateTextColor(s.avg_percentage)">
                                        {{ Number(s.avg_percentage).toFixed(2) }}%
                                    </p>
                                </div>
                                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-base-200">
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :class="passRateBarColor(s.avg_percentage)"
                                        :style="{ width: `${Math.min(Number(s.avg_percentage) || 0, 100)}%` }"
                                    />
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Class Performance -->
                <div class="card-section">
                    <div class="card-content space-y-4">
                        <div class="flex items-center gap-3 border-b border-base-200 pb-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-accent/10 text-accent">
                                <AcademicCapIcon class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold">Class Performance</h3>
                                <p class="text-xs text-base-content/50">Pass rate per class</p>
                            </div>
                        </div>

                        <div v-if="!classPerformance.length" class="py-10 text-center text-sm text-base-content/40">
                            No class data available yet.
                        </div>

                        <ul v-else class="space-y-2.5">
                            <li
                                v-for="(c, i) in classPerformance"
                                :key="c.id"
                                class="rounded-xl border border-base-200 bg-base-100 px-4 py-3"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-2xs font-bold text-base-content/40">#{{ i + 1 }}</span>
                                            <p class="truncate text-sm font-semibold">{{ c.name }}</p>
                                        </div>
                                        <p class="mt-0.5 text-xs text-base-content/50">
                                            <span v-if="isSuperAdmin && c.school_name">{{ c.school_name }} &middot; </span>
                                            {{ c.total_students }} results &middot; avg {{ c.avg_percentage }}%
                                        </p>
                                    </div>
                                    <p class="shrink-0 text-base font-bold tracking-tight" :class="passRateTextColor(c.pass_rate)">
                                        {{ c.pass_rate }}%
                                    </p>
                                </div>
                                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-base-200">
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :class="passRateBarColor(c.pass_rate)"
                                        :style="{ width: `${Math.min(c.pass_rate, 100)}%` }"
                                    />
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
