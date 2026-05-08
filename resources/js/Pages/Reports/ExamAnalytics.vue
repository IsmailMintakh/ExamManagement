<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
    ChartBarIcon, ArrowLeftIcon, AcademicCapIcon, BookOpenIcon,
    BuildingOffice2Icon, TrophyIcon, CheckCircleIcon, XCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: Object,
    headline: { type: Object, default: () => ({}) },
    bySchool: { type: Array, default: () => [] },
    byClass: { type: Array, default: () => [] },
    bySubject: { type: Array, default: () => [] },
    gradeDistribution: { type: Object, default: () => ({}) },
    topPerformers: { type: Array, default: () => [] },
    isSuperAdmin: Boolean,
})

const passColor = pct => pct >= 80 ? 'text-emerald-600' : pct >= 50 ? 'text-amber-600' : 'text-rose-600'
const bgPassColor = pct => pct >= 80 ? 'bg-emerald-500' : pct >= 50 ? 'bg-amber-500' : 'bg-rose-500'

// Sort grade distribution: A1, A, B+, B, C+, C, D, F (typical Pakistani board order)
const gradeOrder = ['A1', 'A+', 'A', 'B+', 'B', 'C+', 'C', 'D', 'E', 'F', 'FAIL', null, '—']
const sortedGrades = computed(() => {
    const entries = Object.entries(props.gradeDistribution || {})
    return entries.sort(([a], [b]) => {
        const aIdx = gradeOrder.indexOf(a)
        const bIdx = gradeOrder.indexOf(b)
        if (aIdx === -1 && bIdx === -1) return a.localeCompare(b)
        if (aIdx === -1) return 1
        if (bIdx === -1) return -1
        return aIdx - bIdx
    })
})

const totalGraded = computed(() =>
    Object.values(props.gradeDistribution || {}).reduce((a, b) => a + b, 0)
)
</script>

<template>
    <Head :title="`Exam Analytics — ${exam.name}`" />
    <AppLayout :breadcrumbs="[
        { label: 'Reports', href: route('reports.index') },
        { label: 'Exam Analytics' },
    ]">
        <div class="space-y-5 max-w-6xl mx-auto">

            <!-- Header -->
            <div>
                <Link :href="route('reports.index')" class="btn btn-ghost btn-sm gap-1 mb-2 -ml-2">
                    <ArrowLeftIcon class="w-4 h-4" /> Back to reports
                </Link>
                <h1 class="text-2xl font-extrabold tracking-tight flex items-center gap-2">
                    <ChartBarIcon class="w-6 h-6 text-violet-600 dark:text-violet-400" />
                    {{ exam.name }}
                </h1>
                <p class="text-sm text-base-content/55 mt-1">
                    {{ exam.type }} · {{ exam.session }} ·
                    <span class="capitalize">{{ exam.status }}</span>
                </p>
            </div>

            <!-- ════════ HEADLINE ════════ -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Students</p>
                    <p class="text-2xl font-extrabold tabular-nums">{{ headline.total || 0 }}</p>
                </div>
                <div class="rounded-2xl border-2 border-emerald-500/30 bg-emerald-500/5 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-emerald-700 dark:text-emerald-400">Passed</p>
                    <p class="text-2xl font-extrabold tabular-nums text-emerald-600 dark:text-emerald-400">
                        {{ headline.passed || 0 }}
                    </p>
                </div>
                <div class="rounded-2xl border-2 border-rose-500/30 bg-rose-500/5 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-rose-700 dark:text-rose-400">Failed</p>
                    <p class="text-2xl font-extrabold tabular-nums text-rose-600 dark:text-rose-400">
                        {{ headline.failed || 0 }}
                    </p>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Pass rate</p>
                    <p class="text-2xl font-extrabold tabular-nums" :class="passColor(headline.pass_rate)">
                        {{ headline.pass_rate || 0 }}%
                    </p>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Average</p>
                    <p class="text-2xl font-extrabold tabular-nums">{{ headline.avg_percentage || 0 }}%</p>
                </div>
            </div>

            <!-- ════════ DDO COMPARISON (super-admin only) ════════ -->
            <section v-if="isSuperAdmin && bySchool.length"
                class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <header class="px-5 py-3 border-b border-base-300 flex items-center gap-2">
                    <BuildingOffice2Icon class="w-4 h-4 text-base-content/55" />
                    <h2 class="text-sm font-bold">School Comparison</h2>
                    <span class="text-xs text-base-content/45">· ranked by pass rate</span>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider text-base-content/55">
                            <tr>
                                <th class="text-left px-3 py-2.5 font-bold">#</th>
                                <th class="text-left px-3 py-2.5 font-bold">School</th>
                                <th class="text-center px-3 py-2.5 font-bold">Students</th>
                                <th class="text-center px-3 py-2.5 font-bold">Pass / Fail</th>
                                <th class="text-center px-3 py-2.5 font-bold">Pass rate</th>
                                <th class="text-center px-3 py-2.5 font-bold">Average</th>
                                <th class="text-center px-3 py-2.5 font-bold">Top</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-300">
                            <tr v-for="(s, idx) in bySchool" :key="s.school_id" class="hover:bg-base-200/30">
                                <td class="px-3 py-2.5 text-xs font-bold tabular-nums w-8">{{ idx + 1 }}</td>
                                <td class="px-3 py-2.5 text-xs font-bold">{{ s.school_name }}</td>
                                <td class="px-3 py-2.5 text-center text-xs tabular-nums">{{ s.students }}</td>
                                <td class="px-3 py-2.5 text-center text-xs">
                                    <span class="text-emerald-600 font-bold">{{ s.passed }}</span> /
                                    <span class="text-rose-600 font-bold">{{ s.failed }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <div class="inline-flex items-center gap-2 w-full max-w-[120px]">
                                        <div class="flex-1 h-1.5 bg-base-200 rounded-full overflow-hidden">
                                            <div class="h-full transition-all" :class="bgPassColor(s.pass_rate)"
                                                :style="{ width: s.pass_rate + '%' }"></div>
                                        </div>
                                        <span class="text-xs font-bold tabular-nums" :class="passColor(s.pass_rate)">
                                            {{ s.pass_rate }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs tabular-nums">{{ s.avg_percentage }}%</td>
                                <td class="px-3 py-2.5 text-center text-xs tabular-nums text-violet-700 dark:text-violet-300">
                                    {{ s.top_score }}%
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ════════ BY CLASS ════════ -->
            <section v-if="byClass.length" class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <header class="px-5 py-3 border-b border-base-300 flex items-center gap-2">
                    <AcademicCapIcon class="w-4 h-4 text-base-content/55" />
                    <h2 class="text-sm font-bold">Per Class</h2>
                </header>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 p-4">
                    <div v-for="c in byClass" :key="c.class_id"
                        class="rounded-xl border border-base-300 bg-base-200/30 p-3">
                        <div class="flex items-center justify-between mb-2">
                            <p class="font-bold text-sm">{{ c.class_name }}</p>
                            <span class="text-[10px] text-base-content/55">{{ c.students }} students</span>
                        </div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="text-xs text-base-content/65">Pass rate</span>
                            <span class="text-xs font-bold tabular-nums ml-auto" :class="passColor(c.pass_rate)">
                                {{ c.pass_rate }}%
                            </span>
                        </div>
                        <div class="h-1.5 bg-base-200 rounded-full overflow-hidden mb-2">
                            <div class="h-full transition-all" :class="bgPassColor(c.pass_rate)"
                                :style="{ width: c.pass_rate + '%' }"></div>
                        </div>
                        <div class="flex items-center text-[11px] text-base-content/55">
                            <span><CheckCircleIcon class="w-3 h-3 inline mr-0.5 text-emerald-500" />{{ c.passed }} passed</span>
                            <span class="ml-auto">avg {{ c.avg_percentage }}%</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ════════ BY SUBJECT ════════ -->
            <section v-if="bySubject.length" class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <header class="px-5 py-3 border-b border-base-300 flex items-center gap-2">
                    <BookOpenIcon class="w-4 h-4 text-base-content/55" />
                    <h2 class="text-sm font-bold">Per Subject</h2>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider text-base-content/55">
                            <tr>
                                <th class="text-left px-3 py-2.5 font-bold">Subject</th>
                                <th class="text-center px-3 py-2.5 font-bold">Attempted</th>
                                <th class="text-center px-3 py-2.5 font-bold">Absent</th>
                                <th class="text-center px-3 py-2.5 font-bold">Passed</th>
                                <th class="text-center px-3 py-2.5 font-bold">Pass rate</th>
                                <th class="text-center px-3 py-2.5 font-bold">Average</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-300">
                            <tr v-for="s in bySubject" :key="s.subject_id" class="hover:bg-base-200/30">
                                <td class="px-3 py-2.5 text-xs font-bold">{{ s.subject_name }}</td>
                                <td class="px-3 py-2.5 text-center text-xs tabular-nums">{{ s.attempted }}</td>
                                <td class="px-3 py-2.5 text-center text-xs tabular-nums text-base-content/55">{{ s.absent }}</td>
                                <td class="px-3 py-2.5 text-center text-xs tabular-nums text-emerald-600">{{ s.passed }}</td>
                                <td class="px-3 py-2.5 text-center text-xs font-bold tabular-nums" :class="passColor(s.pass_rate)">
                                    {{ s.pass_rate }}%
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs tabular-nums">{{ s.avg_percentage }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ════════ GRADE DISTRIBUTION + TOP PERFORMERS ════════ -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                <!-- Grade distribution -->
                <section class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                    <header class="px-5 py-3 border-b border-base-300 flex items-center gap-2">
                        <ChartBarIcon class="w-4 h-4 text-base-content/55" />
                        <h2 class="text-sm font-bold">Grade Distribution</h2>
                    </header>
                    <div class="p-4 space-y-2">
                        <div v-for="[grade, count] in sortedGrades" :key="grade" class="flex items-center gap-3 text-sm">
                            <span class="font-bold w-12 tabular-nums">{{ grade || '—' }}</span>
                            <div class="flex-1 h-3 bg-base-200 rounded-full overflow-hidden">
                                <div class="h-full bg-violet-500"
                                    :style="{ width: ((count / totalGraded) * 100).toFixed(1) + '%' }"></div>
                            </div>
                            <span class="text-xs tabular-nums w-16 text-right">
                                {{ count }} ({{ ((count / totalGraded) * 100).toFixed(1) }}%)
                            </span>
                        </div>
                        <p v-if="!sortedGrades.length" class="text-center text-sm text-base-content/55 py-6">
                            No graded results yet.
                        </p>
                    </div>
                </section>

                <!-- Top performers -->
                <section class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                    <header class="px-5 py-3 border-b border-base-300 flex items-center gap-2">
                        <TrophyIcon class="w-4 h-4 text-amber-500" />
                        <h2 class="text-sm font-bold">Top Performers</h2>
                    </header>
                    <div class="divide-y divide-base-300">
                        <div v-for="(p, idx) in topPerformers" :key="idx"
                            class="px-4 py-2.5 flex items-center gap-3">
                            <span class="w-7 h-7 rounded-full bg-amber-500/15 text-amber-700 dark:text-amber-400 flex items-center justify-center font-bold text-xs tabular-nums shrink-0">
                                {{ idx + 1 }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-sm truncate">{{ p.student_name }}</p>
                                <p class="text-[11px] text-base-content/55 truncate">
                                    Roll {{ p.roll_no }} · {{ p.class_name }} {{ p.section_name }}
                                    <span v-if="isSuperAdmin && p.school_name"> · {{ p.school_name }}</span>
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold text-violet-700 dark:text-violet-300 tabular-nums">{{ p.percentage }}%</p>
                                <p class="text-[10px] text-base-content/55">{{ p.grade }}</p>
                            </div>
                        </div>
                        <div v-if="!topPerformers.length" class="px-4 py-8 text-center text-sm text-base-content/55">
                            No passing results yet.
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
