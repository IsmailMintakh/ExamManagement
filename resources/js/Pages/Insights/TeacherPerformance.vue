<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    AcademicCapIcon, TrophyIcon, ChartBarIcon, UserGroupIcon,
    ArrowTrendingUpIcon, ArrowTrendingDownIcon, MagnifyingGlassIcon,
    ExclamationTriangleIcon, SparklesIcon, InformationCircleIcon,
    StarIcon, ChevronUpDownIcon, ChevronUpIcon, ChevronDownIcon,
    AdjustmentsHorizontalIcon, FunnelIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    teachers: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    currentSession: { type: Object, default: null },
})

// ============= Filters & Sort =============
const search = ref('')
const subjectFilter = ref('')
const classFilter = ref('')
const tierFilter = ref('all')
const sortKey = ref('pass_rate')
const sortDir = ref('desc')

// Distinct subjects + classes for filter dropdowns
const subjects = computed(() => [...new Set(props.teachers.map(t => t.subject_name).filter(Boolean))].sort())
const classes = computed(() => [...new Set(props.teachers.map(t => t.class_name).filter(Boolean))].sort())

// Performance tier: excellent (>=80), good (60-79), needs-work (40-59), struggling (<40)
function tier(passRate) {
    if (passRate >= 80) return 'excellent'
    if (passRate >= 60) return 'good'
    if (passRate >= 40) return 'needs-work'
    return 'struggling'
}

const tierMeta = {
    excellent:    { label: 'Excellent',     color: 'emerald', desc: '≥ 80% pass rate' },
    good:         { label: 'Good',          color: 'sky',     desc: '60–79% pass rate' },
    'needs-work': { label: 'Needs Support', color: 'amber',   desc: '40–59% pass rate' },
    struggling:   { label: 'Struggling',    color: 'rose',    desc: '< 40% pass rate' },
}

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase()
    return props.teachers.filter(t => {
        if (q && !((t.teacher_name || '').toLowerCase().includes(q) ||
                   (t.subject_name || '').toLowerCase().includes(q) ||
                   (t.class_name || '').toLowerCase().includes(q))) return false
        if (subjectFilter.value && t.subject_name !== subjectFilter.value) return false
        if (classFilter.value && t.class_name !== classFilter.value) return false
        if (tierFilter.value !== 'all' && tier(t.pass_rate) !== tierFilter.value) return false
        return true
    })
})

const sorted = computed(() => {
    const dir = sortDir.value === 'asc' ? 1 : -1
    return [...filtered.value].sort((a, b) => {
        const av = a[sortKey.value] ?? 0
        const bv = b[sortKey.value] ?? 0
        if (typeof av === 'string') return av.localeCompare(bv) * dir
        return (av - bv) * dir
    })
})

function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortKey.value = key
        sortDir.value = 'desc'
    }
}

function clearFilters() {
    search.value = ''
    subjectFilter.value = ''
    classFilter.value = ''
    tierFilter.value = 'all'
}

// ============= Derived insights =============
const validRows = computed(() => props.teachers.filter(t => t.total_marked > 0))

const topFive = computed(() =>
    [...validRows.value].sort((a, b) => b.pass_rate - a.pass_rate).slice(0, 5)
)
const needsAttention = computed(() =>
    [...validRows.value].filter(t => t.pass_rate < 60).sort((a, b) => a.pass_rate - b.pass_rate).slice(0, 5)
)

const distribution = computed(() => {
    const buckets = { excellent: 0, good: 0, 'needs-work': 0, struggling: 0 }
    validRows.value.forEach(t => buckets[tier(t.pass_rate)]++)
    const total = validRows.value.length || 1
    return Object.entries(buckets).map(([key, count]) => ({
        key, count,
        label: tierMeta[key].label,
        color: tierMeta[key].color,
        desc: tierMeta[key].desc,
        pct: Math.round(count / total * 100),
    }))
})

// Helpers
function initials(name) {
    if (!name) return '?'
    return name.split(' ').filter(Boolean).map(n => n[0]).slice(0, 2).join('').toUpperCase()
}
function avatarColor(name) {
    const colors = ['bg-emerald-500', 'bg-sky-500', 'bg-amber-500', 'bg-rose-500', 'bg-violet-500', 'bg-teal-500', 'bg-indigo-500', 'bg-orange-500']
    let hash = 0
    for (let i = 0; i < (name || '').length; i++) hash = (hash * 31 + name.charCodeAt(i)) | 0
    return colors[Math.abs(hash) % colors.length]
}
function tierBadgeClass(t) {
    const c = tierMeta[t]?.color
    return {
        emerald: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
        sky: 'bg-sky-100 text-sky-700 ring-sky-200',
        amber: 'bg-amber-100 text-amber-700 ring-amber-200',
        rose: 'bg-rose-100 text-rose-700 ring-rose-200',
    }[c]
}
function passColor(p) {
    if (p >= 80) return 'bg-emerald-500'
    if (p >= 60) return 'bg-sky-500'
    if (p >= 40) return 'bg-amber-500'
    return 'bg-rose-500'
}
function deltaPhrase(d) {
    if (d === null || d === undefined) return { text: 'No comparison available', tone: 'muted' }
    if (d > 5) return { text: `${d > 0 ? '+' : ''}${d}% above school average`, tone: 'positive' }
    if (d > 0) return { text: `${d > 0 ? '+' : ''}${d}% above school average`, tone: 'positive-mild' }
    if (d < -5) return { text: `${Math.abs(d)}% below school average`, tone: 'negative' }
    if (d < 0) return { text: `${Math.abs(d)}% below school average`, tone: 'negative-mild' }
    return { text: 'On par with school average', tone: 'neutral' }
}
function deltaToneClass(tone) {
    return {
        positive: 'text-emerald-700 bg-emerald-50 ring-emerald-200',
        'positive-mild': 'text-emerald-600 bg-emerald-50 ring-emerald-100',
        neutral: 'text-slate-600 bg-slate-50 ring-slate-200',
        'negative-mild': 'text-amber-600 bg-amber-50 ring-amber-100',
        negative: 'text-rose-700 bg-rose-50 ring-rose-200',
        muted: 'text-slate-400 bg-slate-50 ring-slate-200',
    }[tone]
}
</script>

<template>
    <Head title="Teacher Performance" />
    <AppLayout :breadcrumbs="[{ label: 'Insights' }, { label: 'Teacher Performance' }]">
        <div class="space-y-6 max-w-[1500px] mx-auto">
            <!-- ============ HEADER ============ -->
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs text-base-content/55 font-semibold uppercase tracking-wider mb-2">
                        <ChartBarIcon class="w-3.5 h-3.5" />
                        <span>Insights</span>
                    </div>
                    <h1 class="text-3xl font-extrabold tracking-tight">Teacher Performance</h1>
                    <p class="text-sm text-base-content/60 mt-1.5 max-w-2xl">
                        Compare every teacher's classroom outcomes — pass rates, average scores, and how they stack up against the school baseline.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div v-if="currentSession" class="flex items-center gap-2 rounded-xl border border-base-200 bg-base-100 px-3 py-2 text-xs">
                        <span class="text-base-content/45 uppercase tracking-wider text-[10px] font-bold">Session</span>
                        <span class="font-bold">{{ currentSession.name }}</span>
                    </div>
                </div>
            </div>

            <!-- ============ HEADLINE STATS ============ -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="rounded-2xl border border-base-200 bg-gradient-to-br from-base-100 to-base-200/30 p-5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-primary/10 text-primary">
                            <UserGroupIcon class="w-5 h-5" />
                        </div>
                        <span class="text-[10px] uppercase tracking-wider font-bold text-base-content/40">Teachers</span>
                    </div>
                    <div class="mt-3 text-3xl font-extrabold tabular-nums">{{ stats.total_teachers ?? 0 }}</div>
                    <p class="text-xs text-base-content/55 mt-1">{{ stats.total_assignments ?? 0 }} subject &amp; section assignments</p>
                </div>

                <div class="rounded-2xl border border-base-200 bg-gradient-to-br from-base-100 to-base-200/30 p-5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-sky-100 text-sky-700">
                            <ChartBarIcon class="w-5 h-5" />
                        </div>
                        <span class="text-[10px] uppercase tracking-wider font-bold text-base-content/40">School Avg</span>
                    </div>
                    <div class="mt-3 text-3xl font-extrabold tabular-nums text-sky-700">{{ stats.avg_pass_rate ?? 0 }}%</div>
                    <p class="text-xs text-base-content/55 mt-1">Average pass rate across all teachers</p>
                </div>

                <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-base-100 p-5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-500 text-white">
                            <TrophyIcon class="w-5 h-5" />
                        </div>
                        <span class="text-[10px] uppercase tracking-wider font-bold text-emerald-700">Top Performer</span>
                    </div>
                    <div class="mt-3 text-base font-bold leading-tight truncate" :title="stats.top_performer?.teacher_name">
                        {{ stats.top_performer?.teacher_name || '—' }}
                    </div>
                    <p v-if="stats.top_performer" class="text-xs text-base-content/55 mt-1">
                        <span class="font-bold text-emerald-600">{{ stats.top_performer.pass_rate }}%</span> pass · {{ stats.top_performer.subject_name }}
                    </p>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-base-100 p-5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-amber-500 text-white">
                            <ExclamationTriangleIcon class="w-5 h-5" />
                        </div>
                        <span class="text-[10px] uppercase tracking-wider font-bold text-amber-700">Need Support</span>
                    </div>
                    <div class="mt-3 text-3xl font-extrabold tabular-nums text-amber-700">{{ needsAttention.length }}</div>
                    <p class="text-xs text-base-content/55 mt-1">Teachers below 60% pass rate</p>
                </div>
            </div>

            <!-- ============ DISTRIBUTION ============ -->
            <div v-if="validRows.length" class="rounded-2xl border border-base-200 bg-base-100 p-6">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-sm font-bold flex items-center gap-2">
                            <SparklesIcon class="w-4 h-4 text-primary" />
                            Performance Distribution
                        </h3>
                        <p class="text-xs text-base-content/55 mt-0.5">How teachers split across performance tiers</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <button v-for="d in distribution" :key="d.key"
                        @click="tierFilter = tierFilter === d.key ? 'all' : d.key"
                        class="text-left p-4 rounded-xl border-2 transition-all hover:shadow-md"
                        :class="tierFilter === d.key
                            ? 'border-primary bg-primary/5 shadow'
                            : 'border-base-200 hover:border-base-300'">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider"
                                :class="`text-${d.color}-700`">{{ d.label }}</span>
                            <span class="text-[10px] text-base-content/50">{{ d.desc }}</span>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-extrabold tabular-nums">{{ d.count }}</span>
                            <span class="text-sm text-base-content/55">{{ d.pct }}%</span>
                        </div>
                        <div class="h-1.5 bg-base-200 rounded-full overflow-hidden mt-2">
                            <div class="h-full rounded-full transition-all duration-700"
                                :class="{
                                    'bg-emerald-500': d.color === 'emerald',
                                    'bg-sky-500': d.color === 'sky',
                                    'bg-amber-500': d.color === 'amber',
                                    'bg-rose-500': d.color === 'rose',
                                }"
                                :style="`width: ${d.pct}%`"></div>
                        </div>
                    </button>
                </div>
                <p v-if="tierFilter !== 'all'" class="mt-3 text-xs text-base-content/60 flex items-center gap-1.5">
                    <FunnelIcon class="w-3.5 h-3.5" />
                    Filtering by <b>{{ tierMeta[tierFilter].label }}</b>.
                    <button @click="tierFilter = 'all'" class="link link-primary text-xs">Clear</button>
                </p>
            </div>

            <!-- ============ LEADERBOARDS (Top + Attention) ============ -->
            <div v-if="validRows.length" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <!-- Top performers -->
                <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50/60 to-base-100 p-6">
                    <div class="flex items-center gap-2.5 mb-5">
                        <div class="w-9 h-9 rounded-xl bg-emerald-500 text-white flex items-center justify-center">
                            <TrophyIcon class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold">Top 5 Performers</h3>
                            <p class="text-xs text-base-content/55">Highest pass rates this session</p>
                        </div>
                    </div>
                    <ol class="space-y-2">
                        <li v-for="(t, i) in topFive" :key="t.assignment_id"
                            class="flex items-center gap-3 p-3 rounded-xl bg-base-100 hover:shadow-sm transition-shadow">
                            <div class="flex items-center justify-center w-7 h-7 rounded-full font-bold text-xs flex-shrink-0"
                                :class="i === 0 ? 'bg-amber-500 text-white' : i === 1 ? 'bg-slate-300 text-slate-700' : i === 2 ? 'bg-amber-700 text-white' : 'bg-base-200 text-base-content/60'">
                                {{ i + 1 }}
                            </div>
                            <div class="w-9 h-9 rounded-full text-white font-bold text-sm flex items-center justify-center flex-shrink-0" :class="avatarColor(t.teacher_name)">
                                {{ initials(t.teacher_name) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-sm truncate">{{ t.teacher_name }}</div>
                                <div class="text-[11px] text-base-content/55 truncate">{{ t.subject_name }} · {{ t.class_name }}<span v-if="t.section_name"> {{ t.section_name }}</span></div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="font-extrabold text-emerald-700 tabular-nums">{{ t.pass_rate }}%</div>
                                <div class="text-[10px] text-base-content/50">{{ t.total_marked }} students</div>
                            </div>
                        </li>
                    </ol>
                </div>

                <!-- Needs attention -->
                <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50/60 to-base-100 p-6">
                    <div class="flex items-center gap-2.5 mb-5">
                        <div class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center">
                            <ExclamationTriangleIcon class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold">Needs Support</h3>
                            <p class="text-xs text-base-content/55">Teachers who would benefit from mentoring</p>
                        </div>
                    </div>
                    <ol v-if="needsAttention.length" class="space-y-2">
                        <li v-for="t in needsAttention" :key="t.assignment_id"
                            class="flex items-center gap-3 p-3 rounded-xl bg-base-100 hover:shadow-sm transition-shadow">
                            <div class="w-9 h-9 rounded-full text-white font-bold text-sm flex items-center justify-center flex-shrink-0" :class="avatarColor(t.teacher_name)">
                                {{ initials(t.teacher_name) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-sm truncate">{{ t.teacher_name }}</div>
                                <div class="text-[11px] text-base-content/55 truncate">{{ t.subject_name }} · {{ t.class_name }}<span v-if="t.section_name"> {{ t.section_name }}</span></div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="font-extrabold text-rose-600 tabular-nums">{{ t.pass_rate }}%</div>
                                <div class="text-[10px] text-base-content/50">{{ t.total_marked }} students</div>
                            </div>
                        </li>
                    </ol>
                    <div v-else class="text-center py-8">
                        <CheckCircleIcon class="w-10 h-10 text-emerald-500 mx-auto mb-2" />
                        <p class="text-sm font-medium text-base-content/70">No teachers need attention</p>
                        <p class="text-xs text-base-content/50 mt-1">Everyone is performing above 60% pass rate</p>
                    </div>
                </div>
            </div>

            <!-- ============ FILTER BAR ============ -->
            <div class="rounded-2xl border border-base-200 bg-base-100 p-4">
                <div class="flex flex-col lg:flex-row gap-3 items-start lg:items-center">
                    <div class="relative flex-1 w-full lg:max-w-md">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-base-content/40" />
                        <input v-model="search" type="text"
                            placeholder="Search teacher, subject, or class..."
                            class="input input-bordered input-sm w-full pl-10 rounded-xl" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <div class="w-44">
                            <SearchableSelect v-model="subjectFilter" size="sm"
                                :options="[{ value: '', label: 'All Subjects' }, ...subjects.map(s => ({ value: s, label: s }))]"
                                placeholder="All Subjects" />
                        </div>
                        <div class="w-44">
                            <SearchableSelect v-model="classFilter" size="sm"
                                :options="[{ value: '', label: 'All Classes' }, ...classes.map(c => ({ value: c, label: c }))]"
                                placeholder="All Classes" />
                        </div>
                        <select v-model="tierFilter" class="select select-bordered select-sm rounded-xl">
                            <option value="all">All Tiers</option>
                            <option value="excellent">Excellent (≥80%)</option>
                            <option value="good">Good (60–79%)</option>
                            <option value="needs-work">Needs Support (40–59%)</option>
                            <option value="struggling">Struggling (<40%)</option>
                        </select>
                        <button v-if="search || subjectFilter || classFilter || tierFilter !== 'all'"
                            @click="clearFilters"
                            class="btn btn-ghost btn-sm rounded-xl">Clear</button>
                    </div>
                </div>
            </div>

            <!-- ============ DETAIL TABLE ============ -->
            <div class="rounded-2xl border border-base-200 bg-base-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-base-200 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold flex items-center gap-2">
                            <AcademicCapIcon class="w-4 h-4" />
                            All Teachers
                            <span class="ml-1 text-xs font-medium text-base-content/55">({{ sorted.length }} of {{ teachers.length }})</span>
                        </h3>
                    </div>
                    <div class="text-[11px] text-base-content/50 hidden sm:flex items-center gap-1.5">
                        <InformationCircleIcon class="w-3.5 h-3.5" />
                        Click column headers to sort
                    </div>
                </div>

                <div v-if="!sorted.length" class="p-10 text-center">
                    <FunnelIcon class="w-10 h-10 text-base-content/30 mx-auto mb-3" />
                    <p class="text-sm font-medium text-base-content/70">No teachers match your filters</p>
                    <button @click="clearFilters" class="mt-3 text-xs link link-primary">Clear all filters</button>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-base-200/40 text-[11px] uppercase tracking-wider text-base-content/55">
                            <tr>
                                <th class="text-left px-3 sm:px-6 py-3 font-bold">Teacher</th>
                                <th class="text-left px-3 py-3 font-bold hidden md:table-cell">Subject &amp; Class</th>
                                <th class="text-right px-3 py-3 font-bold cursor-pointer hover:text-base-content hidden sm:table-cell" @click="toggleSort('total_marked')">
                                    <span class="inline-flex items-center gap-1">Students <ChevronUpDownIcon class="w-3 h-3" /></span>
                                </th>
                                <th class="text-right px-3 py-3 font-bold cursor-pointer hover:text-base-content" @click="toggleSort('avg_percentage')">
                                    <span class="inline-flex items-center gap-1">Avg % <ChevronUpDownIcon class="w-3 h-3" /></span>
                                </th>
                                <th class="text-left px-3 py-3 font-bold cursor-pointer hover:text-base-content min-w-[140px] sm:min-w-[200px]" @click="toggleSort('pass_rate')">
                                    <span class="inline-flex items-center gap-1">Pass Rate <ChevronUpDownIcon class="w-3 h-3" /></span>
                                </th>
                                <th class="text-left px-3 py-3 font-bold hidden lg:table-cell">vs School Average</th>
                                <th class="text-right px-3 sm:px-6 py-3 font-bold">Tier</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-200">
                            <tr v-for="t in sorted" :key="t.assignment_id" class="hover:bg-base-200/30 transition-colors">
                                <!-- Teacher -->
                                <td class="px-3 sm:px-6 py-3.5">
                                    <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                                        <div class="w-9 h-9 rounded-full text-white font-bold text-xs flex items-center justify-center flex-shrink-0" :class="avatarColor(t.teacher_name)">
                                            {{ initials(t.teacher_name) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-sm truncate">{{ t.teacher_name }}</div>
                                            <div class="text-[11px] text-base-content/50 truncate">{{ t.teacher_email }}</div>
                                            <!-- Mobile inline subject -->
                                            <div class="md:hidden text-[10px] text-base-content/65 mt-0.5 truncate">
                                                {{ t.subject_name }} · {{ t.class_name }}<span v-if="t.section_name"> {{ t.section_name }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <!-- Subject (hidden on mobile, shown inline above) -->
                                <td class="px-3 py-3.5 hidden md:table-cell">
                                    <div class="text-sm font-medium">{{ t.subject_name }}</div>
                                    <div class="text-[11px] text-base-content/55">{{ t.class_name }}<span v-if="t.section_name"> · Section {{ t.section_name }}</span></div>
                                </td>
                                <!-- Students -->
                                <td class="px-3 py-3.5 text-right hidden sm:table-cell">
                                    <span class="font-mono font-medium">{{ t.total_marked }}</span>
                                </td>
                                <!-- Avg % -->
                                <td class="px-3 py-3.5 text-right">
                                    <span class="font-mono font-bold tabular-nums">{{ Number(t.avg_percentage).toFixed(1) }}%</span>
                                </td>
                                <!-- Pass Rate bar -->
                                <td class="px-3 py-3.5">
                                    <div v-if="t.total_marked === 0" class="text-xs text-base-content/40">—</div>
                                    <div v-else class="flex items-center gap-1.5 sm:gap-2.5">
                                        <div class="flex-1 h-2 rounded-full bg-base-200 overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-700"
                                                :class="passColor(t.pass_rate)"
                                                :style="`width: ${Math.min(t.pass_rate, 100)}%`"></div>
                                        </div>
                                        <span class="text-xs font-bold tabular-nums w-10 sm:w-12 text-right">{{ t.pass_rate }}%</span>
                                    </div>
                                </td>
                                <!-- Delta with plain English -->
                                <td class="px-3 py-3.5 hidden lg:table-cell">
                                    <div v-if="t.total_marked === 0" class="text-xs text-base-content/40">—</div>
                                    <div v-else class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold ring-1"
                                        :class="deltaToneClass(deltaPhrase(t.delta_vs_school).tone)">
                                        <ArrowTrendingUpIcon v-if="t.delta_vs_school > 0" class="w-3 h-3" />
                                        <ArrowTrendingDownIcon v-else-if="t.delta_vs_school < 0" class="w-3 h-3" />
                                        <span>{{ deltaPhrase(t.delta_vs_school).text }}</span>
                                    </div>
                                </td>
                                <!-- Tier badge -->
                                <td class="px-3 sm:px-6 py-3.5 text-right">
                                    <span v-if="t.total_marked > 0"
                                        class="inline-flex items-center gap-1 px-2 sm:px-2.5 py-1 rounded-full text-[10px] sm:text-[11px] font-bold ring-1 whitespace-nowrap"
                                        :class="tierBadgeClass(tier(t.pass_rate))">
                                        <StarIcon v-if="tier(t.pass_rate) === 'excellent'" class="w-3 h-3" />
                                        {{ tierMeta[tier(t.pass_rate)].label }}
                                    </span>
                                    <span v-else class="text-xs text-base-content/40">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============ HELP / GLOSSARY ============ -->
            <details class="rounded-2xl border border-base-200 bg-base-100 group">
                <summary class="cursor-pointer list-none p-5 flex items-center gap-3 hover:bg-base-200/30 transition-colors rounded-2xl">
                    <InformationCircleIcon class="w-5 h-5 text-primary" />
                    <span class="text-sm font-bold flex-1">How are these numbers calculated?</span>
                    <ChevronDownIcon class="w-4 h-4 text-base-content/40 group-open:rotate-180 transition-transform" />
                </summary>
                <div class="px-5 pb-5 pt-0 space-y-3 text-sm text-base-content/75">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-3 border-t border-base-200">
                        <div>
                            <h4 class="font-bold text-base-content mb-1">Pass Rate</h4>
                            <p class="text-xs leading-relaxed">Percentage of the teacher's students who scored at or above the passing threshold (33% by default) on submitted exam marks.</p>
                        </div>
                        <div>
                            <h4 class="font-bold text-base-content mb-1">Average %</h4>
                            <p class="text-xs leading-relaxed">Mean percentage score across all students in this teacher's section/subject combination, including any grace marks awarded.</p>
                        </div>
                        <div>
                            <h4 class="font-bold text-base-content mb-1">vs School Average</h4>
                            <p class="text-xs leading-relaxed">Comparison of this teacher's average against the school-wide average for the same subject. <span class="text-emerald-700 font-semibold">Above</span> means stronger outcomes; <span class="text-rose-700 font-semibold">below</span> may suggest the class needs more support.</p>
                        </div>
                        <div>
                            <h4 class="font-bold text-base-content mb-1">Performance Tier</h4>
                            <p class="text-xs leading-relaxed">Quick label based on pass rate: <b class="text-emerald-700">Excellent</b> (≥80%), <b class="text-sky-700">Good</b> (60–79%), <b class="text-amber-700">Needs Support</b> (40–59%), <b class="text-rose-700">Struggling</b> (&lt;40%).</p>
                        </div>
                    </div>
                </div>
            </details>
        </div>
    </AppLayout>
</template>
