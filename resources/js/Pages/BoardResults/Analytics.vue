<script setup>
/**
 * Board-result analytics — charts + position holders + fail/supply lists.
 * All numbers come pre-summarised from the controller so this page is
 * pure presentation.
 */
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import DonutChart from '@/Components/charts/DonutChart.vue'
import BarChartHorizontal from '@/Components/charts/BarChartHorizontal.vue'
import TrendLineChart from '@/Components/charts/TrendLineChart.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref, onMounted } from 'vue'
import axios from 'axios'
import {
    AcademicCapIcon, ArrowLeftIcon, TrophyIcon, ChartBarIcon,
    CheckBadgeIcon, ExclamationCircleIcon, ArrowPathIcon,
    ArrowTrendingUpIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    results: { type: Array, default: () => [] },
})

// Pass / Supply / Fail donut.
const passFailSegments = computed(() => [
    { label: 'Passed',   value: props.stats.passed ?? 0, color: 'emerald' },
    { label: 'Supply',   value: props.stats.supply ?? 0, color: 'sky' },
    { label: 'Failed',   value: props.stats.failed ?? 0, color: 'rose' },
].filter(s => s.value > 0))

// Grade histogram — bar chart rows.
const gradeBars = computed(() => {
    const colours = { A1: 'emerald', A: 'emerald', B: 'sky', C: 'amber', D: 'amber', E: 'rose', F: 'rose' }
    return Object.entries(props.stats.grades ?? {})
        .map(([grade, count]) => ({
            label: `Grade ${grade}`,
            value: count,
            color: colours[grade] || 'primary',
        }))
        .filter(r => r.value > 0)
})

// Division bar chart.
const divisionBars = computed(() => {
    const rows = Object.entries(props.stats.divisions ?? {}).map(([d, c]) => ({
        label: d,
        value: c,
        color: d === '1st' ? 'emerald' : d === '2nd' ? 'sky' : d === '3rd' ? 'amber' : 'rose',
    }))
    return rows.filter(r => r.value > 0)
})

// Subject-wise: pass % per subject (sorted desc).
const subjectPassBars = computed(() =>
    [...(props.stats.subject_stats ?? [])]
        .sort((a, b) => (b.pass_percent || 0) - (a.pass_percent || 0))
        .map(s => ({
            label: s.name,
            sub: `${s.passed}/${s.appeared} passed · avg ${s.average_percent}%`,
            value: s.pass_percent,
            color: s.pass_percent >= 80 ? 'emerald' : s.pass_percent >= 50 ? 'amber' : 'rose',
        }))
)

// ─── Subject-wise grade distribution helpers ───
// Fixed column order matches the FBISE grade scale used server-side.
const gradeCols = ['A1', 'A', 'B', 'C', 'D', 'E', 'F']
// Bar segment colour (for the stacked bar); cell tint (for the table cell background).
function gradeBarColor(g) {
    return {
        'A1': 'bg-emerald-500',
        'A':  'bg-emerald-400',
        'B':  'bg-sky-500',
        'C':  'bg-amber-500',
        'D':  'bg-orange-500',
        'E':  'bg-rose-400',
        'F':  'bg-rose-600',
    }[g] || 'bg-base-300'
}
function gradeCellColor(g) {
    return {
        'A1': 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        'A':  'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        'B':  'bg-sky-500/10 text-sky-700 dark:text-sky-300',
        'C':  'bg-amber-500/10 text-amber-700 dark:text-amber-300',
        'D':  'bg-orange-500/10 text-orange-700 dark:text-orange-300',
        'E':  'bg-rose-400/10 text-rose-700 dark:text-rose-300',
        'F':  'bg-rose-600/10 text-rose-700 dark:text-rose-300',
    }[g] || 'bg-base-200'
}
// Percentage of the segmented bar this grade should occupy for a subject row.
function segPct(subject, grade) {
    const total = subject.appeared || 0
    if (total === 0) return 0
    const c = subject.grade_counts?.[grade] || 0
    return Math.round((c / total) * 100 * 10) / 10
}

// ─── Year-over-year comparison ───
// Fetches after mount so the initial page render is fast; empty state
// shown until the request completes.
const yoyRows = ref([])
const yoyLoading = ref(true)
onMounted(async () => {
    try {
        const { data } = await axios.get(route('board-results.year-over-year', props.exam.id))
        yoyRows.value = data.rows || []
    } catch (_) { yoyRows.value = [] } finally { yoyLoading.value = false }
})
// TrendLineChart wants a numeric array in chronological order.
const yoyTrendPoints = computed(() => yoyRows.value.map(r => r.pass_percentage))

const gradePill = (g) => ({
    'A1': 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    'A':  'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    'B':  'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    'C':  'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    'D':  'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    'E':  'bg-orange-500/15 text-orange-700 dark:text-orange-300',
    'F':  'bg-rose-500/15 text-rose-700 dark:text-rose-300',
}[g] || 'bg-base-200 text-base-content/60')
</script>

<template>
    <Head :title="`Analytics · ${exam.title}`" />
    <AppLayout :breadcrumbs="[
        { label: 'Board Results', href: route('board-results.index') },
        { label: exam.title, href: route('board-results.show', exam.id) },
        { label: 'Analytics' }
    ]">
        <div class="space-y-4 sm:space-y-5 max-w-7xl mx-auto">
            <PageHeader :title="`Analytics — ${exam.title}`"
                        :subtitle="`${exam.school_class?.name} · ${exam.school?.name} · ${stats.total} students`"
                        :icon="ChartBarIcon" tone="violet">
                <template #actions>
                    <Link :href="route('board-results.show', exam.id)"
                          class="btn btn-ghost btn-sm rounded-xl gap-1.5">
                        <ArrowLeftIcon class="w-4 h-4" /> Back
                    </Link>
                </template>
            </PageHeader>

            <!-- KPI strip -->
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2 sm:gap-3">
                <div class="rounded-xl bg-base-100 border border-base-300/70 p-3 shadow-sm">
                    <p class="text-lg font-extrabold tabular-nums">{{ stats.total }}</p>
                    <p class="text-[10px] uppercase tracking-wider font-semibold text-base-content/55 mt-1">Total</p>
                </div>
                <div class="rounded-xl bg-emerald-500/5 border border-emerald-500/20 p-3 shadow-sm">
                    <p class="text-lg font-extrabold tabular-nums text-emerald-700 dark:text-emerald-300">{{ stats.passed }}</p>
                    <p class="text-[10px] uppercase tracking-wider font-semibold text-emerald-700/80 dark:text-emerald-400/80 mt-1">Passed</p>
                </div>
                <div class="rounded-xl bg-sky-500/5 border border-sky-500/20 p-3 shadow-sm">
                    <p class="text-lg font-extrabold tabular-nums text-sky-700 dark:text-sky-300">{{ stats.supply }}</p>
                    <p class="text-[10px] uppercase tracking-wider font-semibold text-sky-700/80 dark:text-sky-400/80 mt-1">Supply</p>
                </div>
                <div class="rounded-xl bg-rose-500/5 border border-rose-500/20 p-3 shadow-sm">
                    <p class="text-lg font-extrabold tabular-nums text-rose-700 dark:text-rose-300">{{ stats.failed }}</p>
                    <p class="text-[10px] uppercase tracking-wider font-semibold text-rose-700/80 dark:text-rose-400/80 mt-1">Failed</p>
                </div>
                <div class="rounded-xl bg-primary/5 border border-primary/20 p-3 shadow-sm">
                    <p class="text-lg font-extrabold tabular-nums text-primary">{{ stats.pass_percentage }}%</p>
                    <p class="text-[10px] uppercase tracking-wider font-semibold text-primary/80 mt-1">Pass Rate</p>
                </div>
                <div class="rounded-xl bg-violet-500/5 border border-violet-500/20 p-3 shadow-sm">
                    <p class="text-lg font-extrabold tabular-nums text-violet-700 dark:text-violet-300">{{ stats.top_percentage }}%</p>
                    <p class="text-[10px] uppercase tracking-wider font-semibold text-violet-700/80 dark:text-violet-400/80 mt-1">Highest</p>
                </div>
            </div>

            <!-- Charts row: pass/fail donut + grade histogram + division breakdown -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
                <article v-if="passFailSegments.length"
                         class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm p-4 sm:p-5">
                    <header class="flex items-center gap-2.5 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 text-white
                                    flex items-center justify-center shadow-sm">
                            <CheckBadgeIcon class="w-4 h-4" />
                        </div>
                        <h3 class="text-sm font-bold">Pass / Supply / Fail</h3>
                    </header>
                    <DonutChart :segments="passFailSegments"
                                center-label="Pass Rate"
                                :center-value="stats.pass_percentage + '%'"
                                :size="140" :stroke="20" />
                </article>

                <article v-if="gradeBars.length"
                         class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm p-4 sm:p-5">
                    <header class="flex items-center gap-2.5 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-orange-600 text-white
                                    flex items-center justify-center shadow-sm">
                            <TrophyIcon class="w-4 h-4" />
                        </div>
                        <h3 class="text-sm font-bold">Grade Distribution</h3>
                    </header>
                    <BarChartHorizontal :rows="gradeBars" />
                </article>

                <article v-if="divisionBars.length"
                         class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm p-4 sm:p-5">
                    <header class="flex items-center gap-2.5 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-sky-500 to-indigo-600 text-white
                                    flex items-center justify-center shadow-sm">
                            <AcademicCapIcon class="w-4 h-4" />
                        </div>
                        <h3 class="text-sm font-bold">Division Breakdown</h3>
                    </header>
                    <BarChartHorizontal :rows="divisionBars" />
                </article>
            </div>

            <!-- Subject-wise pass rate — full-width bar chart -->
            <article v-if="subjectPassBars.length"
                     class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm p-4 sm:p-5">
                <header class="flex items-center gap-2.5 mb-3">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white
                                flex items-center justify-center shadow-sm">
                        <ChartBarIcon class="w-4 h-4" />
                    </div>
                    <h3 class="text-sm font-bold">Subject-wise Pass Rate</h3>
                </header>
                <BarChartHorizontal :rows="subjectPassBars" :max="100" unit="%" />
            </article>

            <!-- ════════ SUBJECT-WISE GRADE DISTRIBUTION ═══════════════
                 Per subject, how many students fell into each FBISE grade
                 slot (A1 → F). Every row has:
                   • segmented stacked bar (visual snapshot)
                   • matrix of grade counts (exact numbers)
                   • total appeared + pass % at the end
            -->
            <article v-if="stats.subject_stats?.length"
                     class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm overflow-hidden">
                <header class="px-5 py-3.5 border-b border-base-200 flex items-center justify-between gap-2 flex-wrap">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-orange-600 text-white
                                    flex items-center justify-center shadow-sm">
                            <TrophyIcon class="w-4 h-4" />
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-amber-600/80 dark:text-amber-400/80">Breakdown</p>
                            <h3 class="text-sm font-bold">Subject-wise Grade Distribution</h3>
                        </div>
                    </div>
                    <!-- Grade legend -->
                    <div class="flex items-center gap-2 text-[10px] font-semibold text-base-content/60">
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-emerald-500"></span>A1</span>
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-emerald-400"></span>A</span>
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-sky-500"></span>B</span>
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-amber-500"></span>C</span>
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-orange-500"></span>D</span>
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-rose-400"></span>E</span>
                        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-rose-600"></span>F</span>
                    </div>
                </header>

                <!-- Desktop: table with segmented bar + grade columns -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider font-bold text-base-content/55">
                            <tr>
                                <th class="text-left px-5 py-2.5 w-48">Subject</th>
                                <th class="text-left px-2 py-2.5">Grade Split</th>
                                <th v-for="g in gradeCols" :key="'h-' + g" class="text-center px-2 py-2.5 w-10">{{ g }}</th>
                                <th class="text-right px-2 py-2.5 w-16">Appeared</th>
                                <th class="text-right px-5 py-2.5 w-16">Pass %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-200">
                            <tr v-for="s in stats.subject_stats" :key="'sub-' + s.subject_id"
                                class="hover:bg-base-200/30 transition-colors">
                                <td class="px-5 py-2.5 font-semibold">{{ s.name }}</td>
                                <td class="px-2 py-2.5">
                                    <!-- Segmented stacked bar — one coloured slice per grade -->
                                    <div class="flex h-3 rounded overflow-hidden bg-base-200 min-w-[120px]">
                                        <div v-for="g in gradeCols" :key="'bar-' + s.subject_id + '-' + g"
                                             v-show="segPct(s, g) > 0"
                                             :class="gradeBarColor(g)"
                                             :style="{ width: segPct(s, g) + '%' }"
                                             :title="g + ': ' + (s.grade_counts?.[g] || 0)"></div>
                                    </div>
                                </td>
                                <td v-for="g in gradeCols" :key="'c-' + s.subject_id + '-' + g"
                                    class="text-center px-2 py-2.5 tabular-nums text-xs"
                                    :class="[
                                        (s.grade_counts?.[g] || 0) === 0 ? 'text-base-content/25' : 'font-semibold',
                                        gradeCellColor(g),
                                    ]">
                                    {{ s.grade_counts?.[g] || 0 }}
                                </td>
                                <td class="px-2 py-2.5 text-right tabular-nums font-medium">{{ s.appeared }}</td>
                                <td class="px-5 py-2.5 text-right tabular-nums">
                                    <span class="inline-block px-2 py-0.5 rounded-md font-bold text-[11px]"
                                          :class="s.pass_percent >= 75 ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                                : s.pass_percent >= 50 ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                                                :                         'bg-rose-500/15 text-rose-700 dark:text-rose-300'">
                                        {{ s.pass_percent }}%
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile: stacked cards -->
                <div class="md:hidden divide-y divide-base-200">
                    <div v-for="s in stats.subject_stats" :key="'m-' + s.subject_id" class="px-4 py-3">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <p class="text-sm font-semibold truncate flex-1">{{ s.name }}</p>
                            <span class="text-[10px] text-base-content/55">{{ s.appeared }} appeared</span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold tabular-nums"
                                  :class="s.pass_percent >= 75 ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                        : s.pass_percent >= 50 ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                                        :                         'bg-rose-500/15 text-rose-700 dark:text-rose-300'">
                                {{ s.pass_percent }}%
                            </span>
                        </div>
                        <div class="flex h-2.5 rounded overflow-hidden bg-base-200 mb-2">
                            <div v-for="g in gradeCols" :key="'mbar-' + s.subject_id + '-' + g"
                                 v-show="segPct(s, g) > 0"
                                 :class="gradeBarColor(g)"
                                 :style="{ width: segPct(s, g) + '%' }"></div>
                        </div>
                        <div class="grid grid-cols-7 gap-1">
                            <div v-for="g in gradeCols" :key="'mc-' + s.subject_id + '-' + g"
                                 class="rounded text-center py-1"
                                 :class="[
                                     (s.grade_counts?.[g] || 0) === 0 ? 'bg-base-200/50 text-base-content/40' : gradeCellColor(g),
                                 ]">
                                <p class="text-[9px] uppercase font-bold">{{ g }}</p>
                                <p class="text-xs font-bold tabular-nums">{{ s.grade_counts?.[g] || 0 }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </article>

            <!-- Top 10 + Fail + Supply — three side-by-side lists -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
                <!-- Top 10 -->
                <section class="rounded-2xl bg-gradient-to-br from-emerald-500/[0.06] via-base-100 to-base-100
                                border border-emerald-500/15 shadow-sm overflow-hidden">
                    <header class="px-4 py-3 border-b border-base-200 flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 text-white
                                    flex items-center justify-center shadow-sm">
                            <TrophyIcon class="w-4 h-4" />
                        </div>
                        <h3 class="text-sm font-bold">Position Holders (Top 10)</h3>
                    </header>
                    <div v-if="stats.top_10?.length" class="divide-y divide-base-200">
                        <div v-for="(r, i) in stats.top_10" :key="'top-' + r.id"
                             class="flex items-center gap-3 px-4 py-2">
                            <span class="w-6 h-6 rounded-md text-[10px] font-bold flex items-center justify-center shrink-0"
                                  :class="i === 0 ? 'bg-amber-500 text-white'
                                        : i === 1 ? 'bg-slate-400 text-white'
                                        : i === 2 ? 'bg-amber-700 text-white'
                                        : 'bg-base-200 text-base-content/60'">
                                {{ i + 1 }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold truncate">{{ r.student.name }}</p>
                                <p class="text-[10px] text-base-content/55">Roll {{ r.student.roll_no || '—' }}</p>
                            </div>
                            <span :class="['px-1.5 py-0.5 rounded text-[10px] font-bold', gradePill(r.grade)]">
                                {{ r.grade }}
                            </span>
                            <span class="text-xs font-bold tabular-nums text-emerald-700 dark:text-emerald-300 shrink-0">
                                {{ Number(r.percentage).toFixed(1) }}%
                            </span>
                        </div>
                    </div>
                    <div v-else class="px-4 py-6 text-center text-xs text-base-content/55">No entries yet.</div>
                </section>

                <!-- Supply -->
                <section class="rounded-2xl bg-gradient-to-br from-sky-500/[0.06] via-base-100 to-base-100
                                border border-sky-500/15 shadow-sm overflow-hidden">
                    <header class="px-4 py-3 border-b border-base-200 flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-sky-500 to-indigo-600 text-white
                                    flex items-center justify-center shadow-sm">
                            <ArrowPathIcon class="w-4 h-4" />
                        </div>
                        <h3 class="text-sm font-bold">Supplementary ({{ stats.supply_list?.length ?? 0 }})</h3>
                    </header>
                    <div v-if="stats.supply_list?.length" class="divide-y divide-base-200 max-h-80 overflow-y-auto">
                        <div v-for="r in stats.supply_list" :key="'sup-' + r.id"
                             class="flex items-center gap-3 px-4 py-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold truncate">{{ r.student.name }}</p>
                                <p class="text-[10px] text-base-content/55">Roll {{ r.student.roll_no || '—' }}</p>
                            </div>
                            <span class="text-xs font-bold tabular-nums text-sky-700 dark:text-sky-300 shrink-0">
                                {{ Number(r.percentage).toFixed(1) }}%
                            </span>
                        </div>
                    </div>
                    <div v-else class="px-4 py-6 text-center text-xs text-base-content/55">
                        None — everyone passed or fully failed.
                    </div>
                </section>

                <!-- Fail -->
                <section class="rounded-2xl bg-gradient-to-br from-rose-500/[0.06] via-base-100 to-base-100
                                border border-rose-500/15 shadow-sm overflow-hidden">
                    <header class="px-4 py-3 border-b border-base-200 flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-rose-500 to-pink-600 text-white
                                    flex items-center justify-center shadow-sm">
                            <ExclamationCircleIcon class="w-4 h-4" />
                        </div>
                        <h3 class="text-sm font-bold">Failed ({{ stats.failed_list?.length ?? 0 }})</h3>
                    </header>
                    <div v-if="stats.failed_list?.length" class="divide-y divide-base-200 max-h-80 overflow-y-auto">
                        <div v-for="r in stats.failed_list" :key="'fail-' + r.id"
                             class="flex items-center gap-3 px-4 py-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold truncate">{{ r.student.name }}</p>
                                <p class="text-[10px] text-base-content/55">Roll {{ r.student.roll_no || '—' }}</p>
                            </div>
                            <span class="text-xs font-bold tabular-nums text-rose-700 dark:text-rose-300 shrink-0">
                                {{ Number(r.percentage).toFixed(1) }}%
                            </span>
                        </div>
                    </div>
                    <div v-else class="px-4 py-6 text-center text-xs text-base-content/55">
                        Nobody fully failed. 🎉
                    </div>
                </section>
            </div>

            <!-- ════════ YEAR-OVER-YEAR ═══════════════════════════════
                 Same-school + same-class + same-level exams across sessions
                 (ordered by announcement date). Trend chart on the left,
                 detail rows on the right with the current exam highlighted. -->
            <article class="rounded-2xl bg-gradient-to-br from-primary/[0.04] via-base-100 to-base-100
                            border border-primary/15 shadow-sm p-4 sm:p-5">
                <header class="flex items-center gap-2.5 mb-3">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary to-teal-600 text-primary-content
                                flex items-center justify-center shadow-sm">
                        <ArrowTrendingUpIcon class="w-4 h-4" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-primary/70">History</p>
                        <h3 class="text-sm font-bold">Year-over-Year — Class {{ exam.school_class?.name }} / {{ exam.level }}</h3>
                    </div>
                </header>

                <div v-if="yoyLoading" class="text-center py-8">
                    <span class="loading loading-spinner loading-sm"></span>
                </div>

                <div v-else-if="yoyRows.length < 2" class="text-xs text-base-content/60 py-4 text-center">
                    Only one exam on record for this class + level so far — the trend line will populate as future
                    boards are announced.
                </div>

                <div v-else class="grid grid-cols-1 lg:grid-cols-5 gap-4">
                    <!-- Trend line — pass % over time -->
                    <div class="lg:col-span-3">
                        <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/50 mb-2">
                            Pass Rate Trend ({{ yoyRows.length }} sessions)
                        </p>
                        <TrendLineChart :points="yoyTrendPoints" color="primary" :height="180" unit="%" />
                    </div>

                    <!-- Detail rows -->
                    <div class="lg:col-span-2 space-y-1.5">
                        <div v-for="r in yoyRows" :key="r.id"
                             class="rounded-lg border p-2.5 text-xs flex items-center gap-3"
                             :class="r.is_current
                                 ? 'border-primary/40 bg-primary/[0.06]'
                                 : 'border-base-300 bg-base-100'">
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold truncate">
                                    {{ r.title }}
                                    <span v-if="r.is_current"
                                          class="ml-1 text-[9px] uppercase tracking-wider font-bold px-1 py-0.5 rounded bg-primary text-primary-content">
                                        Current
                                    </span>
                                </p>
                                <p class="text-[10px] text-base-content/55">
                                    {{ r.session }} · {{ r.total }} students
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-bold tabular-nums text-primary">{{ r.pass_percentage }}%</p>
                                <p class="text-[10px] text-base-content/55 tabular-nums">avg {{ r.average_percent }}%</p>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </AppLayout>
</template>
