<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import TimetableSubnav from '@/Components/timetable/TimetableSubnav.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    ChartBarIcon, UsersIcon, ScaleIcon,
    ExclamationTriangleIcon, AdjustmentsHorizontalIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    school: Object,
    allSchools: { type: Array, default: () => [] },
    currentSchoolId: Number,
    type: String,
    from: String,
    to: String,
    data: { type: Object, default: () => ({ rows: [], summary: {} }) },
})

const activeType = ref(props.type || 'load')
const fromInput = ref(props.from)
const toInput = ref(props.to)

const TABS = [
    { key: 'load', label: 'Teacher load', icon: 'UsersIcon', sub: 'Periods/week + covers given/received' },
    { key: 'fairness', label: 'Adjustment fairness', icon: 'ScaleIcon', sub: 'Cumulative adjustments per teacher in date window' },
    { key: 'coverage', label: 'Coverage gaps', icon: 'ExclamationTriangleIcon', sub: 'Sections with the most uncovered periods' },
]

function applyType(key) {
    activeType.value = key
    router.get(route('timetable.reports'), {
        school_id: props.school?.id,
        type: key,
        from: fromInput.value,
        to: toInput.value,
    }, { preserveScroll: true })
}
function applyDates() {
    router.get(route('timetable.reports'), {
        school_id: props.school?.id,
        type: activeType.value,
        from: fromInput.value,
        to: toInput.value,
    }, { preserveScroll: true })
}
function switchSchool(id) {
    router.get(route('timetable.reports'), {
        school_id: id,
        type: activeType.value,
    }, { preserveState: false })
}

// Color helper for the load report — 8+ periods/week = light, 25+ = heavy.
function loadColor(n) {
    if (n >= 30) return 'bg-rose-500/15 text-rose-700 dark:text-rose-300'
    if (n >= 20) return 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
    if (n >= 10) return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
    return 'bg-base-200 text-base-content/55'
}
function gapColor(pct) {
    if (pct >= 30) return 'bg-rose-500/15 text-rose-700 dark:text-rose-300'
    if (pct >= 10) return 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
    return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
}
</script>

<template>
    <Head title="Timetable Reports" />
    <AppLayout :breadcrumbs="[
        { label: 'Timetable', href: route('timetable.index') },
        { label: 'Reports' },
    ]">
        <div class="space-y-3 max-w-7xl mx-auto">

            <PageHeader title="Timetable reports"
                :subtitle="`Teacher load, adjustment fairness & coverage gaps · ${school?.name || ''}`"
                :icon="ChartBarIcon" tone="violet">
                <template #actions>
                    <select v-if="allSchools.length"
                        @change="switchSchool($event.target.value)" :value="currentSchoolId"
                        class="select select-bordered select-sm rounded-lg text-sm">
                        <option v-for="s in allSchools" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </template>
            </PageHeader>

            <TimetableSubnav :school-id="school?.id" />

            <!-- Report tabs -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <button v-for="t in TABS" :key="t.key"
                    @click="applyType(t.key)"
                    type="button"
                    class="rounded-2xl p-4 text-left ring-2 transition-colors"
                    :class="activeType === t.key
                        ? 'ring-violet-500 bg-violet-500/10'
                        : 'ring-base-300 bg-base-100 hover:bg-base-200/40'">
                    <div class="flex items-center gap-2 mb-1">
                        <UsersIcon v-if="t.key === 'load'" class="w-5 h-5 text-violet-600 dark:text-violet-400" />
                        <ScaleIcon v-else-if="t.key === 'fairness'" class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        <ExclamationTriangleIcon v-else class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                        <span class="font-bold text-sm">{{ t.label }}</span>
                    </div>
                    <p class="text-[11px] text-base-content/55">{{ t.sub }}</p>
                </button>
            </div>

            <!-- Date filter (only for fairness + coverage) -->
            <div v-if="activeType !== 'load'"
                class="rounded-2xl border border-base-300 bg-base-100 p-3 flex items-center gap-2 flex-wrap">
                <AdjustmentsHorizontalIcon class="w-4 h-4 text-base-content/55" />
                <span class="text-[11px] uppercase tracking-wider font-bold text-base-content/55">Date window</span>
                <input v-model="fromInput" type="date" class="input input-bordered input-xs rounded-lg text-xs font-mono">
                <span class="text-base-content/45">→</span>
                <input v-model="toInput" type="date" class="input input-bordered input-xs rounded-lg text-xs font-mono">
                <button @click="applyDates" class="btn btn-primary btn-xs rounded-lg">Apply</button>
            </div>

            <!-- ─── TEACHER LOAD ─── -->
            <section v-if="activeType === 'load'" class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <header class="px-5 py-3 border-b border-base-300 flex items-center gap-3">
                    <h2 class="text-sm font-bold">Teacher Load — Current Routine</h2>
                    <div class="ml-auto flex gap-2 text-[11px]">
                        <span class="text-base-content/55">{{ data.summary?.total_teachers || 0 }} teachers</span>
                        <span class="text-base-content/55">avg {{ data.summary?.avg_periods || 0 }} p/wk</span>
                        <span class="text-base-content/55">max {{ data.summary?.max_periods || 0 }} p/wk</span>
                    </div>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider text-base-content/55">
                            <tr>
                                <th class="text-left px-3 py-2.5 font-bold">Teacher</th>
                                <th class="text-center px-3 py-2.5 font-bold">Periods/week</th>
                                <th class="text-center px-3 py-2.5 font-bold">Classes</th>
                                <th class="text-center px-3 py-2.5 font-bold">Subjects</th>
                                <th class="text-center px-3 py-2.5 font-bold">Covers given</th>
                                <th class="text-center px-3 py-2.5 font-bold">Covers received</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-300">
                            <tr v-for="r in data.rows" :key="r.teacher_id" class="hover:bg-base-200/30">
                                <td class="px-3 py-2.5">
                                    <p class="font-bold text-xs">{{ r.teacher_name }}</p>
                                    <p class="text-[10px] text-base-content/55 truncate">{{ r.email }}</p>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <span class="inline-block px-2.5 py-0.5 rounded-md text-xs font-bold tabular-nums" :class="loadColor(r.periods_per_week)">
                                        {{ r.periods_per_week }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs tabular-nums">{{ r.distinct_classes }}</td>
                                <td class="px-3 py-2.5 text-center text-xs tabular-nums">{{ r.distinct_subjects }}</td>
                                <td class="px-3 py-2.5 text-center text-xs font-bold tabular-nums" :class="r.covers_given >= 5 ? 'text-amber-600' : ''">
                                    {{ r.covers_given }}
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs tabular-nums">{{ r.covers_received }}</td>
                            </tr>
                            <tr v-if="!data.rows.length">
                                <td colspan="6" class="text-center py-10 text-sm text-base-content/55">No teachers found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ─── FAIRNESS ─── -->
            <section v-if="activeType === 'fairness'" class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <header class="px-5 py-3 border-b border-base-300 flex items-center gap-3 flex-wrap">
                    <h2 class="text-sm font-bold">Adjustment Fairness</h2>
                    <div class="flex gap-3 ml-auto text-[11px] text-base-content/55">
                        <span>{{ data.summary?.total_covers || 0 }} total covers</span>
                        <span>{{ data.summary?.distinct_substitutes || 0 }} substitutes</span>
                        <span>{{ data.summary?.days_with_subs || 0 }} days</span>
                    </div>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider text-base-content/55">
                            <tr>
                                <th class="text-left px-3 py-2.5 font-bold">Teacher</th>
                                <th class="text-center px-3 py-2.5 font-bold">Given</th>
                                <th class="text-center px-3 py-2.5 font-bold">Received</th>
                                <th class="text-center px-3 py-2.5 font-bold">Net (G − R)</th>
                                <th class="text-left px-3 py-2.5 font-bold">Last given</th>
                                <th class="text-left px-3 py-2.5 font-bold">Last received</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-300">
                            <tr v-for="r in data.rows" :key="r.teacher_id" class="hover:bg-base-200/30">
                                <td class="px-3 py-2.5 font-bold text-xs">{{ r.teacher_name }}</td>
                                <td class="px-3 py-2.5 text-center text-sm font-bold tabular-nums"
                                    :class="r.covers_given >= 5 ? 'text-amber-700 dark:text-amber-300' : ''">
                                    {{ r.covers_given }}
                                </td>
                                <td class="px-3 py-2.5 text-center text-sm tabular-nums">{{ r.covers_received }}</td>
                                <td class="px-3 py-2.5 text-center text-sm tabular-nums"
                                    :class="r.net_load > 2 ? 'text-rose-600 font-bold' : (r.net_load < -2 ? 'text-emerald-600 font-bold' : '')">
                                    {{ r.net_load > 0 ? '+' : '' }}{{ r.net_load }}
                                </td>
                                <td class="px-3 py-2.5 text-xs text-base-content/55 font-mono">{{ r.last_cover_given_on || '—' }}</td>
                                <td class="px-3 py-2.5 text-xs text-base-content/55 font-mono">{{ r.last_cover_received_on || '—' }}</td>
                            </tr>
                            <tr v-if="!data.rows.length">
                                <td colspan="6" class="text-center py-10 text-sm text-base-content/55">No class adjustments in this window.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ─── COVERAGE GAPS ─── -->
            <section v-if="activeType === 'coverage'" class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <header class="px-5 py-3 border-b border-base-300 flex items-center gap-3">
                    <h2 class="text-sm font-bold">Class Coverage Gaps</h2>
                    <div class="ml-auto text-[11px] text-base-content/55">
                        <span class="text-rose-600 font-bold">{{ data.summary?.gap_periods || 0 }}</span> uncovered of
                        {{ data.summary?.total_periods_needing_cover || 0 }} total
                    </div>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider text-base-content/55">
                            <tr>
                                <th class="text-left px-3 py-2.5 font-bold">Class · Section</th>
                                <th class="text-center px-3 py-2.5 font-bold">Need cover</th>
                                <th class="text-center px-3 py-2.5 font-bold">Confirmed</th>
                                <th class="text-center px-3 py-2.5 font-bold">Suggested</th>
                                <th class="text-center px-3 py-2.5 font-bold">Declined</th>
                                <th class="text-center px-3 py-2.5 font-bold">Gap %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-300">
                            <tr v-for="r in data.rows" :key="r.section_id" class="hover:bg-base-200/30">
                                <td class="px-3 py-2.5 font-bold text-xs">{{ r.class_name }} · {{ r.section_name }}</td>
                                <td class="px-3 py-2.5 text-center text-sm tabular-nums">{{ r.total_periods_needing_cover }}</td>
                                <td class="px-3 py-2.5 text-center text-sm text-emerald-600 tabular-nums">{{ r.covers_confirmed }}</td>
                                <td class="px-3 py-2.5 text-center text-sm text-amber-600 tabular-nums">{{ r.covers_suggested }}</td>
                                <td class="px-3 py-2.5 text-center text-sm text-rose-600 tabular-nums">{{ r.covers_declined }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    <span class="inline-block px-2.5 py-0.5 rounded-md text-xs font-bold tabular-nums" :class="gapColor(r.gap_pct)">
                                        {{ r.gap_pct }}%
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!data.rows.length">
                                <td colspan="6" class="text-center py-10 text-sm text-base-content/55">No coverage data in this window.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
