<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { usePermissions } from '@/Composables/usePermissions'
import { formatDate, formatNumber } from '@/Utils/format'
import {
    PencilSquareIcon, AcademicCapIcon, ChartBarIcon, ClipboardDocumentListIcon,
    UserIcon, DocumentTextIcon, ArrowsRightLeftIcon, TrophyIcon, IdentificationIcon,
    PhoneIcon, MapPinIcon, CakeIcon, HeartIcon, CalendarDaysIcon, CheckCircleIcon,
    XCircleIcon, FireIcon, SparklesIcon, ArrowRightIcon, BuildingLibraryIcon,
    ArrowDownTrayIcon, BookOpenIcon, ClockIcon, EnvelopeIcon, ChevronDownIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    student: Object,
    marks: { type: Array, default: () => [] },
    results: { type: Array, default: () => [] },
    trend: { type: Array, default: () => [] },
    transfers: { type: Array, default: () => [] },
    certificates: { type: Array, default: () => [] },
    timeline: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
})

const { can } = usePermissions()

const tab = ref('overview')
const tabs = [
    { key: 'overview', label: 'Overview', icon: SparklesIcon },
    { key: 'timeline', label: 'Timeline', icon: ClockIcon },
    { key: 'results', label: 'Results', icon: ChartBarIcon },
    { key: 'marks', label: 'Marks Detail', icon: ClipboardDocumentListIcon },
    { key: 'certificates', label: 'Certificates', icon: TrophyIcon },
    { key: 'personal', label: 'Personal Info', icon: UserIcon },
]

// Helpers
function initials(name) {
    if (!name) return '?'
    return name.split(' ').filter(Boolean).map(n => n[0]).slice(0, 2).join('').toUpperCase()
}
function avatarGradient(name) {
    const grads = [
        'from-emerald-500 to-emerald-700',
        'from-sky-500 to-indigo-700',
        'from-amber-500 to-orange-700',
        'from-rose-500 to-pink-700',
        'from-violet-500 to-purple-700',
        'from-teal-500 to-emerald-700',
    ]
    let hash = 0
    for (let i = 0; i < (name || '').length; i++) hash = (hash * 31 + name.charCodeAt(i)) | 0
    return grads[Math.abs(hash) % grads.length]
}
function gradeBadge(g) {
    if (!g) return 'bg-slate-100 text-slate-600 ring-slate-200'
    if (g.startsWith('A')) return 'bg-emerald-100 text-emerald-700 ring-emerald-200'
    if (g.startsWith('B')) return 'bg-sky-100 text-sky-700 ring-sky-200'
    if (g.startsWith('C')) return 'bg-amber-100 text-amber-700 ring-amber-200'
    if (g === 'D' || g === 'E') return 'bg-orange-100 text-orange-700 ring-orange-200'
    return 'bg-rose-100 text-rose-700 ring-rose-200'
}
// Use the project's formatDate util — shows "04 May 2026" with em-dash fallback.
function fmtDate(d) {
    return formatDate(d) || '—'
}

// Sparkline path generator
const sparklinePath = computed(() => {
    if (!props.trend.length) return ''
    const w = 220, h = 50, padding = 4
    const xs = props.trend.length === 1
        ? [w / 2]
        : props.trend.map((_, i) => padding + i * ((w - 2 * padding) / (props.trend.length - 1)))
    const ys = props.trend.map(p => h - padding - (p.percentage / 100) * (h - 2 * padding))
    return xs.map((x, i) => `${i === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${ys[i].toFixed(1)}`).join(' ')
})
const sparklineLastX = computed(() => {
    if (!props.trend.length) return 0
    const w = 220, padding = 4
    return props.trend.length === 1 ? w / 2 : padding + (props.trend.length - 1) * ((w - 2 * padding) / (props.trend.length - 1))
})
const sparklineLastY = computed(() => {
    if (!props.trend.length) return 0
    const h = 50, padding = 4
    const last = props.trend[props.trend.length - 1].percentage
    return h - padding - (last / 100) * (h - 2 * padding)
})

// Timeline event styling
const eventMeta = {
    exam_result:  { icon: ChartBarIcon,     color: 'sky',     label: 'Exam Result' },
    certificate:  { icon: TrophyIcon,       color: 'amber',   label: 'Certificate' },
    transfer:     { icon: ArrowsRightLeftIcon, color: 'violet', label: 'Transfer' },
    admission:    { icon: AcademicCapIcon,  color: 'emerald', label: 'Admission' },
}
function eventColorClasses(c) {
    return {
        sky: 'bg-sky-100 text-sky-700 ring-sky-200',
        amber: 'bg-amber-100 text-amber-700 ring-amber-200',
        violet: 'bg-violet-100 text-violet-700 ring-violet-200',
        emerald: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
    }[c]
}
</script>

<template>
    <Head :title="student?.name" />
    <AppLayout :breadcrumbs="[{ label: 'Students', href: route('students.index') }, { label: student?.name }]">
        <div class="space-y-6 max-w-[1500px] mx-auto">
            <!-- ═══════════ HEADER ═══════════ -->
            <div class="relative surface !rounded-3xl overflow-hidden">
                <!-- Cover gradient -->
                <div class="h-32 bg-gradient-to-r" :class="avatarGradient(student?.name)"></div>

                <div class="px-6 pb-6 -mt-16 lg:-mt-12">
                    <div class="flex flex-col lg:flex-row lg:items-end gap-5">
                        <!-- Avatar -->
                        <div class="relative w-32 h-32 rounded-3xl ring-4 ring-base-100 shadow-xl bg-gradient-to-br flex items-center justify-center text-white text-4xl font-black overflow-hidden flex-shrink-0"
                            :class="avatarGradient(student?.name)">
                            <img v-if="student?.photo_url" :src="student.photo_url" class="w-full h-full object-cover" :alt="student.name" />
                            <span v-else>{{ initials(student?.name) }}</span>
                            <span class="absolute -bottom-1 -right-1 w-7 h-7 rounded-full ring-4 ring-base-100"
                                :class="student?.status === 'active' ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                        </div>

                        <!-- Identity -->
                        <div class="flex-1 lg:mb-2 min-w-0">
                            <h1 class="text-2xl lg:text-3xl font-extrabold tracking-tight">{{ student?.name }}</h1>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-base-content/65">
                                <span class="inline-flex items-center gap-1">
                                    <IdentificationIcon class="w-4 h-4" />
                                    <span class="font-mono">{{ student?.admission_no }}</span>
                                </span>
                                <span v-if="student?.roll_no" class="inline-flex items-center gap-1">
                                    <span class="text-base-content/40">Roll</span>
                                    <span class="font-bold">#{{ student.roll_no }}</span>
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <BuildingLibraryIcon class="w-4 h-4" />
                                    {{ student?.school_class?.name }} · Sec {{ student?.section?.name }}
                                </span>
                                <span v-if="student?.school?.name" class="inline-flex items-center gap-1">
                                    <MapPinIcon class="w-4 h-4" />
                                    {{ student.school.name }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold ring-1"
                                    :class="student?.status === 'active' ? 'bg-emerald-100 text-emerald-700 ring-emerald-200' : 'bg-amber-100 text-amber-700 ring-amber-200'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="student?.status === 'active' ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'"></span>
                                    {{ (student?.status || 'unknown').toUpperCase() }}
                                </span>
                                <span v-if="student?.academic_session?.name" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-base-200 text-base-content/70 text-[11px] font-semibold ring-1 ring-base-300">
                                    <CalendarDaysIcon class="w-3 h-3" /> Session {{ student.academic_session.name }}
                                </span>
                                <span v-if="student?.gender" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-base-200 text-base-content/70 text-[11px] font-semibold capitalize ring-1 ring-base-300">
                                    {{ student.gender }}
                                </span>
                                <span v-if="stats?.currentStreak >= 2" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-orange-100 text-orange-700 text-[11px] font-bold ring-1 ring-orange-200">
                                    <FireIcon class="w-3 h-3" /> {{ stats.currentStreak }}-Exam Pass Streak
                                </span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-wrap items-center gap-2 lg:mb-2">
                            <a v-if="student?.id" :href="route('students.id-card', student.id)" target="_blank"
                                class="btn btn-ghost btn-sm rounded-xl gap-1.5">
                                <IdentificationIcon class="w-4 h-4" /> ID Card
                            </a>
                            <Link v-if="can('transfers.create')" :href="route('transfers.create', student?.id)"
                                class="btn btn-ghost btn-sm rounded-xl gap-1.5">
                                <ArrowsRightLeftIcon class="w-4 h-4" /> Transfer
                            </Link>
                            <Link v-if="can('students.edit')" :href="route('students.edit', student?.id)"
                                class="btn btn-primary btn-sm rounded-xl gap-1.5">
                                <PencilSquareIcon class="w-4 h-4" /> Edit Profile
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════ KPI STRIP ═══════════ -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
                <div class="surface p-5">
                    <ClipboardDocumentListIcon class="w-5 h-5 text-primary mb-2" />
                    <div class="text-2xl font-extrabold tabular-nums">{{ stats.examsTaken }}</div>
                    <p class="text-xs text-base-content/55 mt-0.5">Exams Taken</p>
                    <p class="text-[10px] text-emerald-600 font-semibold mt-1">{{ stats.examsPassed }} passed</p>
                </div>

                <div class="surface p-5 lg:col-span-2">
                    <div class="flex items-start justify-between">
                        <div>
                            <ChartBarIcon class="w-5 h-5 text-sky-600 mb-2" />
                            <div class="text-2xl font-extrabold tabular-nums">{{ stats.avgScore }}%</div>
                            <p class="text-xs text-base-content/55 mt-0.5">Average Score</p>
                            <p v-if="stats.bestPercentage" class="text-[10px] text-amber-600 font-semibold mt-1">Best {{ stats.bestPercentage }}%</p>
                        </div>
                        <!-- Sparkline -->
                        <div v-if="trend.length > 1" class="hidden sm:block">
                            <svg width="220" height="50" class="overflow-visible">
                                <defs>
                                    <linearGradient id="sparkfill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="rgb(2 132 199)" stop-opacity="0.25" />
                                        <stop offset="100%" stop-color="rgb(2 132 199)" stop-opacity="0" />
                                    </linearGradient>
                                </defs>
                                <path :d="sparklinePath + ` L ${sparklineLastX} 50 L 4 50 Z`" fill="url(#sparkfill)" />
                                <path :d="sparklinePath" fill="none" stroke="rgb(2 132 199)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <circle :cx="sparklineLastX" :cy="sparklineLastY" r="3.5" fill="rgb(2 132 199)" stroke="white" stroke-width="2" />
                            </svg>
                            <p class="text-[10px] text-base-content/45 text-right mt-1">% trend over time</p>
                        </div>
                    </div>
                </div>

                <div class="surface p-5">
                    <TrophyIcon class="w-5 h-5 text-amber-600 mb-2" />
                    <div class="text-2xl font-extrabold tabular-nums">
                        <template v-if="stats.bestRank">#{{ stats.bestRank }}</template>
                        <template v-else>—</template>
                    </div>
                    <p class="text-xs text-base-content/55 mt-0.5">Best Rank</p>
                    <p class="text-[10px] text-base-content/45 mt-1">In any exam</p>
                </div>

                <div class="surface p-5">
                    <SparklesIcon class="w-5 h-5 text-violet-600 mb-2" />
                    <div class="text-2xl font-extrabold tabular-nums">{{ stats.certificatesEarned }}</div>
                    <p class="text-xs text-base-content/55 mt-0.5">Certificates</p>
                    <p class="text-[10px] text-base-content/45 mt-1">Earned</p>
                </div>
            </div>

            <!-- ═══════════ TABS ═══════════ -->
            <div class="border-b border-base-200 flex gap-1 overflow-x-auto">
                <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
                    class="inline-flex items-center gap-2 px-4 py-3 text-sm font-semibold border-b-2 transition-colors whitespace-nowrap"
                    :class="tab === t.key
                        ? 'border-primary text-primary'
                        : 'border-transparent text-base-content/55 hover:text-base-content hover:border-base-300'">
                    <component :is="t.icon" class="w-4 h-4" />
                    {{ t.label }}
                </button>
            </div>

            <!-- ═══════════ OVERVIEW TAB ═══════════ -->
            <div v-if="tab === 'overview'" class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <!-- Recent results (left, 2 cols) -->
                <div class="lg:col-span-2 space-y-5">
                    <div class="surface overflow-hidden">
                        <div class="px-5 py-4 border-b border-base-200 flex items-center justify-between">
                            <h3 class="text-sm font-bold flex items-center gap-2">
                                <ChartBarIcon class="w-4 h-4 text-sky-600" />
                                Recent Exam Results
                            </h3>
                            <button @click="tab = 'results'" class="text-xs text-primary font-semibold hover:underline inline-flex items-center gap-1">
                                See all <ArrowRightIcon class="w-3 h-3" />
                            </button>
                        </div>
                        <div v-if="!results.length" class="p-10 text-center text-sm text-base-content/55">
                            <ChartBarIcon class="w-10 h-10 mx-auto text-base-content/30 mb-2" />
                            No exam results yet.
                        </div>
                        <div v-else class="divide-y divide-base-200">
                            <div v-for="r in results.slice(0, 4)" :key="r.id" class="p-4 hover:bg-base-200/30 transition-colors flex items-center gap-4">
                                <div class="w-11 h-11 rounded-xl flex items-center justify-center text-base font-bold flex-shrink-0"
                                    :class="r.is_passed ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
                                    <component :is="r.is_passed ? CheckCircleIcon : XCircleIcon" class="w-5 h-5" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-sm truncate">{{ r.exam_name }}</div>
                                    <div class="text-[11px] text-base-content/55">{{ fmtDate(r.exam_date) }} · {{ r.exam_type }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-extrabold tabular-nums">{{ r.percentage }}%</div>
                                    <div class="text-[11px] text-base-content/55">
                                        {{ Number(r.obtained_marks).toFixed(0) }}/{{ Number(r.total_marks).toFixed(0) }}
                                    </div>
                                </div>
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-bold ring-1" :class="gradeBadge(r.grade)">{{ r.grade || '—' }}</span>
                                <span v-if="r.position" class="hidden sm:inline-flex w-9 h-9 rounded-full items-center justify-center text-xs font-bold flex-shrink-0"
                                    :class="r.position === 1 ? 'bg-amber-500 text-white' : r.position === 2 ? 'bg-slate-300 text-slate-700' : r.position === 3 ? 'bg-amber-700 text-white' : 'bg-base-200 text-base-content/60'">
                                    #{{ r.position }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Recent certificates -->
                    <div v-if="certificates.length" class="surface overflow-hidden">
                        <div class="px-5 py-4 border-b border-base-200 flex items-center justify-between">
                            <h3 class="text-sm font-bold flex items-center gap-2">
                                <TrophyIcon class="w-4 h-4 text-amber-600" />
                                Certificates Earned
                            </h3>
                            <button @click="tab = 'certificates'" class="text-xs text-primary font-semibold hover:underline">See all</button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-5">
                            <div v-for="c in certificates.slice(0, 4)" :key="c.id"
                                class="flex items-center gap-3 p-3 rounded-xl border border-base-200 hover:border-amber-300 transition-colors">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 text-white flex items-center justify-center flex-shrink-0">
                                    <TrophyIcon class="w-5 h-5" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-sm truncate">{{ c.template_name }}</div>
                                    <div class="text-[10px] text-base-content/55 font-mono">{{ c.number }}</div>
                                </div>
                                <a :href="route('certificates.download', c.id)" target="_blank" class="btn btn-ghost btn-xs">
                                    <ArrowDownTrayIcon class="w-3.5 h-3.5" />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick info (right) -->
                <div class="space-y-5">
                    <div class="surface p-5">
                        <h3 class="text-sm font-bold flex items-center gap-2 mb-4">
                            <UserIcon class="w-4 h-4 text-primary" />
                            Quick Info
                        </h3>
                        <dl class="space-y-3 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-base-content/55 text-xs uppercase tracking-wider font-semibold">Father</dt>
                                <dd class="font-semibold text-right">{{ student?.father_name || '—' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-base-content/55 text-xs uppercase tracking-wider font-semibold">Phone</dt>
                                <dd class="font-mono font-semibold text-right">{{ student?.guardian_phone || '—' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-base-content/55 text-xs uppercase tracking-wider font-semibold">Date of Birth</dt>
                                <dd class="font-semibold text-right">{{ fmtDate(student?.date_of_birth) }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-base-content/55 text-xs uppercase tracking-wider font-semibold">Blood Group</dt>
                                <dd class="font-semibold text-right inline-flex items-center gap-1">
                                    <HeartIcon v-if="student?.blood_group" class="w-3.5 h-3.5 text-rose-500" />
                                    {{ student?.blood_group || '—' }}
                                </dd>
                            </div>
                        </dl>
                        <button @click="tab = 'personal'" class="mt-4 text-xs text-primary font-semibold hover:underline inline-flex items-center gap-1">
                            View full personal info <ArrowRightIcon class="w-3 h-3" />
                        </button>
                    </div>

                    <!-- Performance summary -->
                    <div v-if="results.length" class="rounded-2xl border border-base-200 bg-gradient-to-br from-base-100 to-emerald-50/30 p-5">
                        <h3 class="text-sm font-bold flex items-center gap-2 mb-4">
                            <SparklesIcon class="w-4 h-4 text-emerald-600" />
                            Performance Summary
                        </h3>
                        <div class="space-y-3">
                            <div>
                                <div class="flex justify-between text-xs mb-1.5">
                                    <span class="text-base-content/65">Overall pass rate</span>
                                    <span class="font-bold">{{ stats.examsTaken ? Math.round(stats.examsPassed / stats.examsTaken * 100) : 0 }}%</span>
                                </div>
                                <div class="h-1.5 bg-base-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-emerald-500 rounded-full" :style="`width: ${stats.examsTaken ? (stats.examsPassed / stats.examsTaken * 100) : 0}%`"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs mb-1.5">
                                    <span class="text-base-content/65">Average score</span>
                                    <span class="font-bold">{{ stats.avgScore }}%</span>
                                </div>
                                <div class="h-1.5 bg-base-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-sky-500 rounded-full" :style="`width: ${stats.avgScore}%`"></div>
                                </div>
                            </div>
                            <div v-if="stats.bestPercentage">
                                <div class="flex justify-between text-xs mb-1.5">
                                    <span class="text-base-content/65">Personal best</span>
                                    <span class="font-bold">{{ stats.bestPercentage }}%</span>
                                </div>
                                <div class="h-1.5 bg-base-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-amber-500 rounded-full" :style="`width: ${stats.bestPercentage}%`"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════ TIMELINE TAB ═══════════ -->
            <div v-if="tab === 'timeline'" class="surface p-6 lg:p-8">
                <div v-if="!timeline.length" class="text-center py-10 text-sm text-base-content/55">
                    <ClockIcon class="w-10 h-10 mx-auto text-base-content/30 mb-2" />
                    No academic history yet.
                </div>
                <div v-else class="relative">
                    <div class="absolute left-[18px] top-2 bottom-2 w-px bg-gradient-to-b from-base-200 via-base-300 to-base-200"></div>
                    <ol class="space-y-6">
                        <li v-for="(e, i) in timeline" :key="i" class="relative pl-12">
                            <div class="absolute left-0 top-0 w-9 h-9 rounded-full ring-4 ring-base-100 flex items-center justify-center"
                                :class="eventColorClasses(eventMeta[e.type]?.color)">
                                <component :is="eventMeta[e.type]?.icon || ClockIcon" class="w-4 h-4" />
                            </div>
                            <div class="rounded-xl border border-base-200 bg-base-100 p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <span class="text-[10px] uppercase tracking-wider font-bold" :class="`text-${eventMeta[e.type]?.color}-700`">
                                            {{ eventMeta[e.type]?.label || e.type }}
                                        </span>
                                        <h4 class="font-bold text-sm mt-1">{{ e.title }}</h4>

                                        <!-- Type-specific meta -->
                                        <div v-if="e.type === 'exam_result'" class="mt-2 flex flex-wrap items-center gap-3 text-xs">
                                            <span class="font-mono text-base-content/65">{{ Number(e.meta.obtained_marks).toFixed(0) }}/{{ Number(e.meta.total_marks).toFixed(0) }}</span>
                                            <span class="font-bold tabular-nums">{{ e.meta.percentage }}%</span>
                                            <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold ring-1" :class="gradeBadge(e.meta.grade)">{{ e.meta.grade || '—' }}</span>
                                            <span v-if="e.meta.position" class="text-amber-700 font-semibold">Position #{{ e.meta.position }}</span>
                                            <span class="inline-flex items-center gap-1 font-semibold" :class="e.meta.is_passed ? 'text-emerald-700' : 'text-rose-700'">
                                                <component :is="e.meta.is_passed ? CheckCircleIcon : XCircleIcon" class="w-3 h-3" />
                                                {{ e.meta.is_passed ? 'Passed' : 'Failed' }}
                                            </span>
                                        </div>
                                        <div v-else-if="e.type === 'certificate'" class="mt-2 text-xs text-base-content/65">
                                            <span class="font-mono">{{ e.meta.number }}</span>
                                            <span v-if="e.meta.exam_name"> · {{ e.meta.exam_name }}</span>
                                        </div>
                                        <div v-else-if="e.type === 'transfer'" class="mt-2 text-xs text-base-content/65">
                                            Status: <span class="font-semibold capitalize">{{ e.meta.status }}</span>
                                            <span v-if="e.meta.reason"> · {{ e.meta.reason }}</span>
                                        </div>
                                        <div v-else-if="e.type === 'admission'" class="mt-2 text-xs text-base-content/65">
                                            {{ e.meta.class }} · Section {{ e.meta.section }}
                                        </div>
                                    </div>
                                    <span class="text-[11px] text-base-content/50 font-medium whitespace-nowrap">{{ fmtDate(e.date) }}</span>
                                </div>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>

            <!-- ═══════════ RESULTS TAB ═══════════ -->
            <div v-if="tab === 'results'" class="surface overflow-hidden">
                <div v-if="!results.length" class="p-10 text-center text-sm text-base-content/55">No results yet.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-base-200/40 text-[11px] uppercase tracking-wider text-base-content/55">
                            <tr>
                                <th class="text-left px-6 py-3 font-bold">Exam</th>
                                <th class="text-right px-3 py-3 font-bold">Marks</th>
                                <th class="text-right px-3 py-3 font-bold">%</th>
                                <th class="text-center px-3 py-3 font-bold">Grade</th>
                                <th class="text-center px-3 py-3 font-bold">Position</th>
                                <th class="text-center px-3 py-3 font-bold">Pass / Retry</th>
                                <th class="text-right px-6 py-3 font-bold">Report Card</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-200">
                            <tr v-for="r in results" :key="r.id" class="hover:bg-base-200/30 transition-colors">
                                <td class="px-6 py-3">
                                    <div class="font-medium">{{ r.exam_name }}</div>
                                    <div class="text-[11px] text-base-content/55">{{ r.exam_type }} · {{ fmtDate(r.exam_date) }}</div>
                                </td>
                                <td class="px-3 py-3 text-right font-mono text-xs">{{ Number(r.obtained_marks).toFixed(0) }}/{{ Number(r.total_marks).toFixed(0) }}</td>
                                <td class="px-3 py-3 text-right"><span class="font-bold tabular-nums">{{ r.percentage }}%</span></td>
                                <td class="px-3 py-3 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold ring-1" :class="gradeBadge(r.grade)">{{ r.grade || '—' }}</span>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span v-if="r.position" class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold"
                                        :class="r.position === 1 ? 'bg-amber-500 text-white' : r.position === 2 ? 'bg-slate-200 text-slate-700' : r.position === 3 ? 'bg-amber-700 text-white' : 'bg-base-200 text-base-content/60'">
                                        {{ r.position }}
                                    </span>
                                    <span v-else class="text-base-content/30">—</span>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold ring-1"
                                        :class="r.is_passed ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-rose-50 text-rose-700 ring-rose-200'">
                                        <component :is="r.is_passed ? CheckCircleIcon : XCircleIcon" class="w-3 h-3" />
                                        {{ r.is_passed ? 'Pass' : 'Retry' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <Link :href="route('reports.report-card', [r.exam_id, student.id])" target="_blank" class="btn btn-ghost btn-xs gap-1 rounded-lg">
                                        <DocumentTextIcon class="w-3.5 h-3.5" /> Card
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ═══════════ MARKS DETAIL TAB ═══════════ -->
            <div v-if="tab === 'marks'" class="surface overflow-hidden">
                <div v-if="!marks.length" class="p-10 text-center text-sm text-base-content/55">No marks recorded yet.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-base-200/40 text-[11px] uppercase tracking-wider text-base-content/55">
                            <tr>
                                <th class="text-left px-6 py-3 font-bold">Exam</th>
                                <th class="text-left px-3 py-3 font-bold">Subject</th>
                                <th class="text-right px-3 py-3 font-bold">Marks</th>
                                <th class="text-right px-3 py-3 font-bold">%</th>
                                <th class="text-center px-3 py-3 font-bold">Pass</th>
                                <th class="text-right px-6 py-3 font-bold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-200">
                            <tr v-for="m in marks" :key="m.id" class="hover:bg-base-200/30 transition-colors">
                                <td class="px-6 py-3 text-sm">{{ m.exam_name }}</td>
                                <td class="px-3 py-3">
                                    <div class="font-medium">{{ m.subject_name }}</div>
                                    <div class="text-[10px] text-base-content/55 font-mono">{{ m.subject_code }}</div>
                                </td>
                                <td class="px-3 py-3 text-right font-mono">{{ m.is_absent ? 'AB' : `${m.marks_obtained}/${m.total_marks}` }}</td>
                                <td class="px-3 py-3 text-right">
                                    <span v-if="!m.is_absent" class="font-bold tabular-nums">{{ m.percentage }}%</span>
                                    <span v-else class="text-base-content/30">—</span>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <CheckCircleIcon v-if="!m.is_absent && m.percentage >= 33" class="w-4 h-4 text-emerald-600 mx-auto" />
                                    <XCircleIcon v-else class="w-4 h-4 text-rose-500 mx-auto" />
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold capitalize"
                                        :class="m.status === 'submitted' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                        {{ m.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ═══════════ CERTIFICATES TAB ═══════════ -->
            <div v-if="tab === 'certificates'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-if="!certificates.length" class="lg:col-span-3 surface p-10 text-center text-sm text-base-content/55">
                    <TrophyIcon class="w-10 h-10 mx-auto text-base-content/30 mb-2" />
                    No certificates earned yet.
                </div>
                <div v-for="c in certificates" :key="c.id"
                    class="group surface overflow-hidden hover:shadow-xl hover:-translate-y-0.5 transition-all">
                    <div class="h-24 bg-gradient-to-br from-amber-400 via-amber-500 to-orange-600 relative flex items-center justify-center">
                        <TrophyIcon class="w-12 h-12 text-white/80 group-hover:scale-110 transition-transform" />
                    </div>
                    <div class="p-5">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-amber-700">{{ c.type?.replace('_', ' ') }}</div>
                        <h4 class="font-bold mt-1">{{ c.template_name }}</h4>
                        <p class="text-[11px] text-base-content/55 mt-1 font-mono">{{ c.number }}</p>
                        <p v-if="c.exam_name" class="text-xs text-base-content/65 mt-2">{{ c.exam_name }}</p>
                        <div class="flex items-center justify-between mt-4 pt-4 border-t border-base-200">
                            <span class="text-[11px] text-base-content/55">{{ fmtDate(c.issued_at) }}</span>
                            <a :href="route('certificates.download', c.id)" target="_blank" class="btn btn-primary btn-xs gap-1 rounded-lg">
                                <ArrowDownTrayIcon class="w-3.5 h-3.5" /> Download
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════ PERSONAL INFO TAB ═══════════ -->
            <div v-if="tab === 'personal'" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="surface p-6">
                    <h3 class="text-sm font-bold flex items-center gap-2 mb-5">
                        <UserIcon class="w-4 h-4 text-primary" />
                        Personal Details
                    </h3>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-4 text-sm">
                        <div><dt class="text-[11px] uppercase tracking-wider text-base-content/55 font-semibold mb-1">Father's Name</dt><dd class="font-semibold">{{ student?.father_name || '—' }}</dd></div>
                        <div><dt class="text-[11px] uppercase tracking-wider text-base-content/55 font-semibold mb-1">Date of Birth</dt><dd class="font-semibold inline-flex items-center gap-1"><CakeIcon class="w-3.5 h-3.5 text-base-content/40" />{{ fmtDate(student?.date_of_birth) }}</dd></div>
                        <div><dt class="text-[11px] uppercase tracking-wider text-base-content/55 font-semibold mb-1">Gender</dt><dd class="font-semibold capitalize">{{ student?.gender || '—' }}</dd></div>
                        <div><dt class="text-[11px] uppercase tracking-wider text-base-content/55 font-semibold mb-1">Blood Group</dt><dd class="font-semibold inline-flex items-center gap-1"><HeartIcon v-if="student?.blood_group" class="w-3.5 h-3.5 text-rose-500" />{{ student?.blood_group || '—' }}</dd></div>
                        <div><dt class="text-[11px] uppercase tracking-wider text-base-content/55 font-semibold mb-1">Religion</dt><dd class="font-semibold">{{ student?.religion || '—' }}</dd></div>
                        <div><dt class="text-[11px] uppercase tracking-wider text-base-content/55 font-semibold mb-1">Guardian Phone</dt><dd class="font-semibold inline-flex items-center gap-1"><PhoneIcon class="w-3.5 h-3.5 text-base-content/40" />{{ student?.guardian_phone || '—' }}</dd></div>
                        <div><dt class="text-[11px] uppercase tracking-wider text-base-content/55 font-semibold mb-1">CNIC / B-Form</dt><dd class="font-semibold font-mono">{{ student?.cnic || '—' }}</dd></div>
                        <div class="col-span-2"><dt class="text-[11px] uppercase tracking-wider text-base-content/55 font-semibold mb-1">Address</dt><dd class="font-semibold inline-flex items-start gap-1"><MapPinIcon class="w-3.5 h-3.5 text-base-content/40 mt-1 flex-shrink-0" />{{ student?.address || '—' }}</dd></div>
                    </dl>
                </div>

                <div class="surface p-6">
                    <h3 class="text-sm font-bold flex items-center gap-2 mb-5">
                        <AcademicCapIcon class="w-4 h-4 text-primary" />
                        Academic &amp; Enrollment
                    </h3>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-4 text-sm">
                        <div class="col-span-2"><dt class="text-[11px] uppercase tracking-wider text-base-content/55 font-semibold mb-1">School</dt><dd class="font-semibold inline-flex items-center gap-1"><BuildingLibraryIcon class="w-3.5 h-3.5 text-base-content/40" />{{ student?.school?.name || '—' }}</dd></div>
                        <div><dt class="text-[11px] uppercase tracking-wider text-base-content/55 font-semibold mb-1">Class</dt><dd class="font-semibold">{{ student?.school_class?.name || '—' }}</dd></div>
                        <div><dt class="text-[11px] uppercase tracking-wider text-base-content/55 font-semibold mb-1">Section</dt><dd class="font-semibold">{{ student?.section?.name || '—' }}</dd></div>
                        <div><dt class="text-[11px] uppercase tracking-wider text-base-content/55 font-semibold mb-1">Roll Number</dt><dd class="font-mono font-semibold">{{ student?.roll_no || '—' }}</dd></div>
                        <div><dt class="text-[11px] uppercase tracking-wider text-base-content/55 font-semibold mb-1">Admission No.</dt><dd class="font-mono font-semibold">{{ student?.admission_no }}</dd></div>
                        <div class="col-span-2"><dt class="text-[11px] uppercase tracking-wider text-base-content/55 font-semibold mb-1">Academic Session</dt><dd class="font-semibold inline-flex items-center gap-1"><CalendarDaysIcon class="w-3.5 h-3.5 text-base-content/40" />{{ student?.academic_session?.name || '—' }}</dd></div>
                    </dl>

                    <!-- Transfer history if any -->
                    <div v-if="transfers.length" class="mt-6 pt-5 border-t border-base-200">
                        <h4 class="text-xs uppercase tracking-wider font-bold text-base-content/55 mb-3 flex items-center gap-2">
                            <ArrowsRightLeftIcon class="w-3.5 h-3.5" /> Transfer History
                        </h4>
                        <ul class="space-y-2">
                            <li v-for="t in transfers" :key="t.id" class="text-xs flex items-start gap-2">
                                <span class="text-base-content/55">{{ fmtDate(t.date) }}</span>
                                <span class="text-base-content/85">{{ t.from }} → {{ t.to }}</span>
                                <span class="ml-auto inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold capitalize"
                                    :class="t.status === 'completed' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">{{ t.status }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
