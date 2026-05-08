<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
    CalendarIcon, AcademicCapIcon, Cog6ToothIcon, UserCircleIcon,
    ArrowsRightLeftIcon, EyeIcon, PencilSquareIcon, PrinterIcon,
    ExclamationTriangleIcon, BookOpenIcon, ChevronRightIcon,
    UserGroupIcon, ClockIcon, BoltIcon, NoSymbolIcon,
    Squares2X2Icon, RectangleStackIcon, TableCellsIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    school: Object,
    classes: Array,
    hasSchedule: Boolean,
    allSchools: Array,
    currentSchoolId: Number,
    stats: { type: Object, default: () => ({}) },
})

function switchSchool(id) {
    router.get(route('timetable.index'), { school_id: id }, { preserveState: false })
}

// % of section's period cells that have a teacher.
function completionPct(sec) {
    const total = props.stats?.total_period_slots || 0
    // approximate — treat all period slots × 6 days = 6 × total as ceiling.
    // The actual ceiling depends on per-slot weekdays, but this gives a decent
    // visual signal. Display capped at 100.
    if (!total) return 0
    return Math.min(100, Math.round((sec.entries_count / (total * 6)) * 100))
}
function completionLabel(sec) {
    if (!sec.entries_count) return 'Empty'
    if (completionPct(sec) >= 80) return 'Complete'
    if (completionPct(sec) >= 30) return 'In progress'
    return 'Started'
}
function completionColor(sec) {
    if (!sec.entries_count) return 'bg-base-200 text-base-content/45'
    if (completionPct(sec) >= 80) return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
    if (completionPct(sec) >= 30) return 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
    return 'bg-sky-500/15 text-sky-700 dark:text-sky-300'
}

const totalSectionsWithSchedule = computed(() =>
    props.classes.flatMap(c => c.sections).filter(s => s.entries_count > 0).length
)
</script>

<template>
    <Head title="Timetable" />
    <AppLayout :breadcrumbs="[{ label: 'Timetable' }]">
        <div class="space-y-5 max-w-[1500px] mx-auto">

            <!-- Hero -->
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-violet-700 to-purple-800 text-white shadow-xl">
                <div class="absolute -top-24 -right-24 w-72 h-72 bg-pink-400/20 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-24 -left-24 w-72 h-72 bg-violet-400/20 rounded-full blur-3xl"></div>

                <div class="relative px-6 py-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div class="hidden sm:flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/15 backdrop-blur ring-1 ring-white/20">
                            <CalendarIcon class="w-6 h-6 text-pink-300" />
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-[0.25em] font-bold text-pink-300/90">School Timetable</p>
                            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-tight mt-1">
                                {{ school?.name || 'Pick a school' }}
                            </h1>
                            <p class="text-sm text-violet-50/85 mt-1">
                                Manage class schedules, teacher assignments &amp; daily substitutions.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <select v-if="allSchools.length"
                            @change="switchSchool($event.target.value)"
                            :value="currentSchoolId"
                            class="bg-white/10 backdrop-blur border border-white/25 text-white text-sm font-semibold rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-pink-400/60 cursor-pointer">
                            <option v-for="s in allSchools" :key="s.id" :value="s.id" class="text-slate-800">{{ s.name }}</option>
                        </select>
                        <Link v-if="school && hasSchedule" :href="route('timetable.master', { school_id: school.id })"
                            class="bg-white/15 hover:bg-white/25 backdrop-blur border border-white/25 text-white text-sm font-semibold rounded-xl px-4 py-2 inline-flex items-center gap-2 transition-colors">
                            <TableCellsIcon class="w-4 h-4" />
                            Master view
                        </Link>
                        <a v-if="school && hasSchedule" :href="route('timetable.routine.pdf', { school_id: school.id })" target="_blank"
                            class="bg-pink-500/90 hover:bg-pink-500 text-white text-sm font-bold rounded-xl px-4 py-2 inline-flex items-center gap-2 transition-colors shadow-lg ring-2 ring-pink-300/40">
                            <PrinterIcon class="w-4 h-4" />
                            Print routine (1-2 A4)
                        </a>
                        <Link :href="route('timetable.setup', { school_id: school?.id })"
                            class="bg-white/15 hover:bg-white/25 backdrop-blur border border-white/25 text-white text-sm font-semibold rounded-xl px-4 py-2 inline-flex items-center gap-2 transition-colors">
                            <Cog6ToothIcon class="w-4 h-4" />
                            Bell schedule
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="!school" class="rounded-2xl border border-base-300 bg-base-100 p-10 text-center">
                <p class="text-sm text-base-content/65">No school selected.</p>
            </div>
            <div v-else-if="!hasSchedule" class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-8 text-center">
                <ExclamationTriangleIcon class="w-10 h-10 text-amber-500 mx-auto mb-3" />
                <h3 class="font-bold mb-1">Bell schedule not set yet</h3>
                <p class="text-sm text-base-content/65 mb-4">
                    Define your school's periods and breaks before building class timetables.
                </p>
                <Link :href="route('timetable.setup', { school_id: school?.id })"
                    class="btn btn-primary btn-sm rounded-xl gap-1.5">
                    <Cog6ToothIcon class="w-4 h-4" /> Set up bell schedule
                </Link>
            </div>

            <!-- ════════ STATS ════════ -->
            <div v-if="school && hasSchedule" class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
                    <div class="flex items-center gap-2 text-base-content/55">
                        <Squares2X2Icon class="w-4 h-4" />
                        <span class="text-[10px] uppercase tracking-wider font-bold">Classes</span>
                    </div>
                    <p class="text-2xl font-extrabold tabular-nums mt-1">{{ stats.total_classes || 0 }}</p>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
                    <div class="flex items-center gap-2 text-base-content/55">
                        <RectangleStackIcon class="w-4 h-4" />
                        <span class="text-[10px] uppercase tracking-wider font-bold">Sections</span>
                    </div>
                    <p class="text-2xl font-extrabold tabular-nums mt-1">{{ stats.total_sections || 0 }}</p>
                    <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">{{ totalSectionsWithSchedule }} have entries</p>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
                    <div class="flex items-center gap-2 text-base-content/55">
                        <ClockIcon class="w-4 h-4" />
                        <span class="text-[10px] uppercase tracking-wider font-bold">Periods/day</span>
                    </div>
                    <p class="text-2xl font-extrabold tabular-nums mt-1">{{ stats.total_period_slots || 0 }}</p>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
                    <div class="flex items-center gap-2 text-base-content/55">
                        <NoSymbolIcon class="w-4 h-4 text-rose-500" />
                        <span class="text-[10px] uppercase tracking-wider font-bold">Absent today</span>
                    </div>
                    <p class="text-2xl font-extrabold tabular-nums mt-1" :class="stats.today_absences > 0 ? 'text-rose-600' : ''">{{ stats.today_absences || 0 }}</p>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
                    <div class="flex items-center gap-2 text-base-content/55">
                        <ArrowsRightLeftIcon class="w-4 h-4 text-amber-500" />
                        <span class="text-[10px] uppercase tracking-wider font-bold">Covers today</span>
                    </div>
                    <p class="text-2xl font-extrabold tabular-nums mt-1" :class="stats.today_covers > 0 ? 'text-amber-600' : ''">{{ stats.today_covers || 0 }}</p>
                </div>
            </div>

            <!-- Quick actions row -->
            <div v-if="school && hasSchedule" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                <Link :href="route('timetable.master', { school_id: school?.id })"
                    class="group rounded-2xl border-2 border-violet-500/30 bg-violet-500/5 p-4 flex items-center gap-3 hover:border-violet-500/60 hover:bg-violet-500/10 transition-colors">
                    <div class="w-11 h-11 rounded-xl bg-violet-500 text-white flex items-center justify-center shadow">
                        <TableCellsIcon class="w-5 h-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm">Master view</p>
                        <p class="text-xs text-base-content/55">All sections side-by-side, one day at a time</p>
                    </div>
                    <ChevronRightIcon class="w-4 h-4 text-base-content/40" />
                </Link>
                <Link :href="route('timetable.setup', { school_id: school?.id })"
                    class="group rounded-2xl border border-base-300 bg-base-100 p-4 flex items-center gap-3 hover:border-violet-500/40 hover:bg-violet-500/5 transition-colors">
                    <div class="w-11 h-11 rounded-xl bg-violet-500/15 text-violet-600 dark:text-violet-400 flex items-center justify-center">
                        <Cog6ToothIcon class="w-5 h-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm">Bell schedule</p>
                        <p class="text-xs text-base-content/55">Periods, breaks, Friday half-day supported</p>
                    </div>
                    <ChevronRightIcon class="w-4 h-4 text-base-content/40" />
                </Link>
                <Link :href="route('timetable.substitutions')"
                    class="group rounded-2xl border border-base-300 bg-base-100 p-4 flex items-center gap-3 hover:border-amber-500/40 hover:bg-amber-500/5 transition-colors">
                    <div class="w-11 h-11 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                        <ArrowsRightLeftIcon class="w-5 h-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm">Today's substitutions</p>
                        <p class="text-xs text-base-content/55">Mark absences &amp; auto-cover empty periods</p>
                    </div>
                    <ChevronRightIcon class="w-4 h-4 text-base-content/40" />
                </Link>
                <a :href="route('timetable.routine.pdf', { school_id: school.id })" target="_blank"
                    class="group rounded-2xl border-2 border-pink-500/40 bg-gradient-to-br from-pink-500/10 to-rose-500/10 p-4 flex items-center gap-3 hover:border-pink-500/70 hover:from-pink-500/15 transition-colors">
                    <div class="w-11 h-11 rounded-xl bg-pink-500 text-white flex items-center justify-center shadow">
                        <PrinterIcon class="w-5 h-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm">Wall-chart routine</p>
                        <p class="text-xs text-base-content/55">Whole school on 1-2 A4 sheets — pin in office</p>
                    </div>
                    <ChevronRightIcon class="w-4 h-4 text-base-content/40" />
                </a>
            </div>

            <!-- Classes + sections grid -->
            <section v-if="school && hasSchedule && classes.length" class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <header class="px-5 py-3 border-b border-base-300 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <AcademicCapIcon class="w-4 h-4" />
                    </div>
                    <h2 class="text-sm font-bold">Classes &amp; sections</h2>
                    <span class="text-xs text-base-content/45">· {{ classes.length }} class{{ classes.length === 1 ? '' : 'es' }}</span>
                </header>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 p-4">
                    <div v-for="cls in classes" :key="cls.id"
                        class="rounded-xl border border-base-300 bg-base-200/30 p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <BookOpenIcon class="w-4 h-4 text-base-content/55" />
                            <h3 class="font-bold text-sm">{{ cls.name }}</h3>
                            <span class="text-xs text-base-content/45 ml-auto">{{ cls.sections.length }} section{{ cls.sections.length === 1 ? '' : 's' }}</span>
                        </div>
                        <div v-if="!cls.sections.length" class="text-xs text-base-content/45 italic">No sections yet.</div>
                        <ul v-else class="space-y-1.5">
                            <li v-for="sec in cls.sections" :key="sec.id"
                                class="rounded-lg bg-base-100 border border-base-300 px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold flex-1">Section {{ sec.name }}</span>
                                    <span class="text-[10px] uppercase tracking-wider font-bold px-2 py-0.5 rounded-md"
                                        :class="completionColor(sec)">
                                        {{ completionLabel(sec) }}
                                    </span>
                                    <Link :href="route('timetable.section', sec.id)"
                                        class="btn btn-ghost btn-xs rounded-lg" title="View">
                                        <EyeIcon class="w-3.5 h-3.5" />
                                    </Link>
                                    <Link :href="route('timetable.builder', sec.id)"
                                        class="btn btn-ghost btn-xs rounded-lg" title="Edit">
                                        <PencilSquareIcon class="w-3.5 h-3.5" />
                                    </Link>
                                </div>
                                <div v-if="sec.entries_count > 0" class="mt-1.5 h-1 rounded-full bg-base-200 overflow-hidden">
                                    <div class="h-full transition-all duration-500"
                                        :class="completionPct(sec) >= 80 ? 'bg-emerald-500' : completionPct(sec) >= 30 ? 'bg-amber-500' : 'bg-sky-500'"
                                        :style="{ width: completionPct(sec) + '%' }"></div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
