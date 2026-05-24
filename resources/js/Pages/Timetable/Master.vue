<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import PageHeader from '@/Components/PageHeader.vue'
import TimetableSubnav from '@/Components/timetable/TimetableSubnav.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    Squares2X2Icon, PrinterIcon, CalendarDaysIcon,
    AdjustmentsHorizontalIcon, EyeIcon, MagnifyingGlassIcon, ChevronDownIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    school: Object,
    slots: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    entries: { type: Object, default: () => ({}) },
    allSchools: { type: Array, default: () => [] },
    currentSchoolId: Number,
    stageBlocks: { type: Array, default: () => [] },
})

// ─── Stage scope ───
// One tab per stage that actually has sections. Each tab swaps the matrix
// to that stage's bell schedule (4 periods for Pre-Primary, 8 for higher
// stages, etc.) instead of cramming every stage's slots into one mega-grid.
const activeStage = ref(props.stageBlocks?.[0]?.key ?? '')
const activeBlock = computed(() =>
    props.stageBlocks.find(b => b.key === activeStage.value) || props.stageBlocks[0] || null
)
const activeSlots = computed(() => activeBlock.value?.slots || props.slots)
const activeSections = computed(() => activeBlock.value?.sections || props.sections)

const DAYS = [
    { code: 'mon', label: 'Monday' },
    { code: 'tue', label: 'Tuesday' },
    { code: 'wed', label: 'Wednesday' },
    { code: 'thu', label: 'Thursday' },
    { code: 'fri', label: 'Friday' },
    { code: 'sat', label: 'Saturday' },
]

// Default to today's weekday, falling back to Monday on Sundays.
function todayCode() {
    const map = ['sun','mon','tue','wed','thu','fri','sat']
    const c = map[new Date().getDay()]
    return c === 'sun' ? 'mon' : c
}

const activeDay = ref(todayCode())
const filterClass = ref('') // school_class_id filter — empty = all classes
const teacherFilter = ref('')
const subjectFilter = ref('')
const search = ref('')
const heatMap = ref(false)

function entry(day, slotId, sectionId) {
    return props.entries[`${day}|${slotId}|${sectionId}`]
}
function slotApplies(slot, day) {
    const days = slot.weekdays || ['mon','tue','wed','thu','fri','sat']
    return days.includes(day)
}
function isPeriod(s) { return s.type === 'period' }

// Class filter options derived from the ACTIVE stage's sections.
const classOptions = computed(() => {
    const seen = new Map()
    for (const s of activeSections.value) {
        if (!seen.has(s.school_class_id)) {
            seen.set(s.school_class_id, { id: s.school_class_id, name: s.class_name })
        }
    }
    return [...seen.values()]
})

// Filter visible sections by class filter + search (matching class+section labels),
// scoped to the active stage.
const visibleSections = computed(() => {
    return activeSections.value.filter(s => {
        if (filterClass.value && String(s.school_class_id) !== String(filterClass.value)) return false
        if (search.value) {
            const q = search.value.toLowerCase()
            const label = `${s.class_name} ${s.name}`.toLowerCase()
            if (!label.includes(q)) return false
        }
        return true
    })
})

// Apply teacher / subject filters by *highlighting* matching cells rather
// than hiding them — clearer at-a-glance "where is Mr. Khan teaching today?"
function cellMatchesFilters(e) {
    if (teacherFilter.value && e?.teacher?.id !== Number(teacherFilter.value)) return false
    if (subjectFilter.value && e?.subject?.id !== Number(subjectFilter.value)) return false
    return true
}
function cellHighlighted(e) {
    if (!teacherFilter.value && !subjectFilter.value) return false
    if (!e) return false
    return cellMatchesFilters(e)
}

// Distinct teachers + subjects for the filter dropdowns.
const distinctTeachers = computed(() => {
    const map = new Map()
    Object.values(props.entries).forEach(e => {
        if (e.teacher?.id && !map.has(e.teacher.id)) {
            map.set(e.teacher.id, { id: e.teacher.id, name: e.teacher.name })
        }
    })
    return [...map.values()].sort((a, b) => a.name.localeCompare(b.name))
})
const distinctSubjects = computed(() => {
    const map = new Map()
    Object.values(props.entries).forEach(e => {
        if (e.subject?.id && !map.has(e.subject.id)) {
            map.set(e.subject.id, { id: e.subject.id, name: e.subject.name })
        }
    })
    return [...map.values()].sort((a, b) => a.name.localeCompare(b.name))
})

// Heatmap: per-teacher load across the active day. Used to color cells when
// heatMap mode is on — light = teaches few periods, dark = stretched thin.
const teacherLoadToday = computed(() => {
    if (!heatMap.value) return {}
    const counts = {}
    for (const slot of props.slots) {
        if (!isPeriod(slot)) continue
        if (!slotApplies(slot, activeDay.value)) continue
        for (const sec of props.sections) {
            const e = entry(activeDay.value, slot.id, sec.id)
            if (e?.teacher_id) {
                counts[e.teacher_id] = (counts[e.teacher_id] || 0) + 1
            }
        }
    }
    return counts
})
function heatColor(e) {
    if (!heatMap.value || !e?.teacher_id) return ''
    const count = teacherLoadToday.value[e.teacher_id] || 0
    if (count >= 6) return 'bg-rose-500/40 ring-rose-500/60'
    if (count >= 4) return 'bg-amber-500/30 ring-amber-500/60'
    if (count >= 2) return 'bg-emerald-500/20 ring-emerald-500/40'
    return 'bg-sky-500/10 ring-sky-500/30'
}

// Per-day stats for the badge on the day pills (scoped to active stage).
function statsForDay(dayCode) {
    let filled = 0, total = 0
    for (const slot of activeSlots.value) {
        if (!isPeriod(slot)) continue
        if (!slotApplies(slot, dayCode)) continue
        for (const sec of activeSections.value) {
            total++
            const e = entry(dayCode, slot.id, sec.id)
            if (e?.teacher_id) filled++
        }
    }
    return { filled, total, pct: total ? Math.round((filled / total) * 100) : 0 }
}

// Detect within-day teacher conflicts (same teacher in two sections at the
// same slot). Returns Set of "<teacher_id>|<slotId>" keys with conflicts.
// Conflict detection runs across the WHOLE school (not just the active
// stage) because a teacher could be double-booked across stages too.
const conflictsToday = computed(() => {
    const seen = {}
    const conflicts = new Set()
    for (const slot of props.slots) {
        if (!isPeriod(slot)) continue
        if (!slotApplies(slot, activeDay.value)) continue
        for (const sec of props.sections) {
            const e = entry(activeDay.value, slot.id, sec.id)
            if (!e?.teacher_id) continue
            const key = `${e.teacher_id}|${slot.id}`
            if (seen[key]) {
                conflicts.add(seen[key])
                conflicts.add(`${slot.id}|${sec.id}`)
            } else {
                seen[key] = `${slot.id}|${sec.id}`
            }
        }
    }
    return conflicts
})

function clearFilters() {
    filterClass.value = ''
    teacherFilter.value = ''
    subjectFilter.value = ''
    search.value = ''
}

function switchSchool(id) {
    router.get(route('timetable.master'), { school_id: id }, { preserveState: false })
}
</script>

<template>
    <Head title="Master Timetable" />
    <AppLayout :breadcrumbs="[
        { label: 'Timetable', href: route('timetable.index') },
        { label: 'Master view' },
    ]">
        <div class="space-y-3 max-w-[1900px] mx-auto">

            <PageHeader title="Master grid"
                :subtitle="`Every class & section side-by-side · ${school?.name || ''} · ${sections.length} section${sections.length === 1 ? '' : 's'}`"
                :icon="Squares2X2Icon" tone="violet">
                <template #actions>
                    <div v-if="allSchools.length" class="min-w-[200px]">
                        <SearchableSelect :model-value="currentSchoolId" size="sm"
                            :options="allSchools.map(s => ({ value: s.id, label: s.name }))"
                            placeholder="Select school"
                            @change="(v) => switchSchool(v)" />
                    </div>
                    <a :href="route('timetable.routine.pdf', { school_id: school?.id })" target="_blank"
                        class="btn btn-primary btn-sm rounded-lg gap-1.5">
                        <PrinterIcon class="w-4 h-4" /> Wall-chart
                    </a>
                    <a :href="route('timetable.master.pdf', { school_id: school?.id })" target="_blank"
                        class="btn btn-outline btn-sm rounded-lg gap-1.5">
                        <PrinterIcon class="w-4 h-4" /> A3
                    </a>
                    <a :href="route('timetable.school.pdf', { school_id: school?.id })" target="_blank"
                        class="btn btn-ghost btn-sm rounded-lg gap-1.5">
                        <PrinterIcon class="w-4 h-4" /> Booklet
                    </a>
                </template>
            </PageHeader>

            <TimetableSubnav :school-id="school?.id" />

            <!-- Stage tabs — each stage has its own bell schedule (Pre-Primary 4,
                 Primary 6, Middle/High 7-8) so the matrix shows only that
                 stage's slots instead of one P1-P17 mega-grid. -->
            <div v-if="stageBlocks?.length > 1"
                class="flex items-center gap-1 overflow-x-auto rounded-2xl border border-base-300 bg-base-100 p-1">
                <button v-for="b in stageBlocks" :key="b.key"
                    @click="activeStage = b.key"
                    type="button"
                    class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                    :class="activeStage === b.key
                        ? 'bg-violet-500/15 text-violet-700 dark:text-violet-300 ring-1 ring-violet-500/30'
                        : 'text-base-content/60 hover:bg-base-200 hover:text-base-content'">
                    {{ b.label }}
                    <span class="badge badge-xs badge-ghost tabular-nums">
                        {{ b.sections.length }} sec · {{ b.slots.filter(s => s.type === 'period').length }} periods
                    </span>
                </button>
            </div>

            <!-- Day pills -->
            <div class="flex gap-1.5 overflow-x-auto pb-1">
                <button v-for="d in DAYS" :key="d.code"
                    @click="activeDay = d.code"
                    class="px-4 py-2.5 rounded-xl text-sm font-bold ring-2 transition-colors whitespace-nowrap flex items-center gap-2"
                    :class="activeDay === d.code
                        ? 'ring-violet-500 bg-violet-500/10 text-violet-700 dark:text-violet-300'
                        : 'ring-base-300 bg-base-100 hover:bg-base-200/50'">
                    <span>{{ d.label }}</span>
                    <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-base-200/70">
                        {{ statsForDay(d.code).filled }}/{{ statsForDay(d.code).total }}
                    </span>
                </button>
            </div>

            <!-- Filters strip -->
            <div class="rounded-2xl border border-base-300 bg-base-100 p-3">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1.5 text-base-content/55 shrink-0">
                        <AdjustmentsHorizontalIcon class="w-4 h-4" />
                        <span class="text-[11px] uppercase tracking-wider font-bold">Filter</span>
                    </div>
                    <div class="w-40">
                        <SearchableSelect v-model="filterClass" size="xs"
                            :options="[{ value: '', label: 'All classes' }, ...classOptions.map(c => ({ value: c.id, label: c.name }))]"
                            placeholder="All classes" />
                    </div>
                    <div class="w-52">
                        <SearchableSelect v-model="teacherFilter" size="xs"
                            :options="[{ value: '', label: 'All teachers (highlight one)' }, ...distinctTeachers.map(t => ({ value: t.id, label: t.name }))]"
                            placeholder="All teachers" />
                    </div>
                    <div class="w-52">
                        <SearchableSelect v-model="subjectFilter" size="xs"
                            :options="[{ value: '', label: 'All subjects (highlight one)' }, ...distinctSubjects.map(s => ({ value: s.id, label: s.name }))]"
                            placeholder="All subjects" />
                    </div>
                    <div class="relative flex-1 min-w-[160px]">
                        <MagnifyingGlassIcon class="w-3.5 h-3.5 text-base-content/40 absolute left-2 top-1/2 -translate-y-1/2" />
                        <input v-model="search" type="text" placeholder="Search class/section…"
                            class="input input-bordered input-xs rounded-lg pl-7 w-full text-xs" />
                    </div>
                    <label class="flex items-center gap-1.5 text-xs cursor-pointer ml-auto">
                        <input type="checkbox" v-model="heatMap" class="toggle toggle-error toggle-xs" />
                        <span :class="heatMap ? 'font-bold text-rose-700 dark:text-rose-300' : 'text-base-content/55'">
                            Heat-map
                        </span>
                    </label>
                    <button v-if="filterClass || teacherFilter || subjectFilter || search"
                        @click="clearFilters"
                        class="btn btn-ghost btn-xs rounded-lg gap-1">
                        <XCircleIcon class="w-3.5 h-3.5" /> Clear
                    </button>
                </div>
                <p v-if="heatMap" class="text-[11px] text-base-content/55 mt-2">
                    <span class="inline-block w-2.5 h-2.5 rounded bg-sky-500/40 mr-1"></span>1 period
                    <span class="inline-block w-2.5 h-2.5 rounded bg-emerald-500/40 ml-2 mr-1"></span>2-3
                    <span class="inline-block w-2.5 h-2.5 rounded bg-amber-500/40 ml-2 mr-1"></span>4-5
                    <span class="inline-block w-2.5 h-2.5 rounded bg-rose-500/60 ml-2 mr-1"></span>6+ (overloaded)
                </p>
            </div>

            <!-- Conflict warning -->
            <div v-if="conflictsToday.size" class="rounded-2xl border-2 border-rose-500/40 bg-rose-500/10 p-3 text-sm flex items-start gap-2">
                <XCircleIcon class="w-5 h-5 text-rose-600 mt-0.5 shrink-0" />
                <div>
                    <p class="font-bold text-rose-900 dark:text-rose-200">Teacher conflicts detected on {{ DAYS.find(d => d.code === activeDay)?.label }}</p>
                    <p class="text-xs text-rose-700 dark:text-rose-300/80 mt-0.5">A teacher is assigned to two sections at the same time. Conflicting cells are outlined in rose.</p>
                </div>
            </div>

            <!-- ════════════ MASTER GRID ════════════ -->
            <div v-if="visibleSections.length" class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <div class="overflow-auto" style="max-height: 80vh;">
                    <table class="text-xs border-collapse" style="min-width: 100%;">
                        <thead>
                            <tr>
                                <th class="text-left px-2 py-2.5 font-bold text-[10px] uppercase tracking-wider text-base-content/55 sticky left-0 top-0 bg-base-200 z-30 border-b border-r border-base-300 min-w-[120px]">
                                    Slot
                                </th>
                                <th v-for="sec in visibleSections" :key="sec.id"
                                    class="text-center px-1.5 py-2 font-bold sticky top-0 bg-base-200 z-20 border-b border-r border-base-300 min-w-[120px]">
                                    <p class="text-[10px] uppercase tracking-wider text-violet-700 dark:text-violet-300">{{ sec.class_name }}</p>
                                    <p class="text-[11px] text-base-content/65 normal-case tracking-normal">Sec {{ sec.name }}</p>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="slot in activeSlots" :key="slot.id"
                                :class="!isPeriod(slot) ? 'bg-amber-500/5' : ''">
                                <td class="px-2 py-1.5 sticky left-0 z-10 border-b border-r border-base-300 align-top"
                                    :class="!isPeriod(slot) ? 'bg-amber-500/10' : 'bg-base-100'">
                                    <p class="font-bold text-xs leading-tight">{{ slot.name }}</p>
                                    <p class="text-[9px] text-base-content/55 font-mono">{{ slot.starts_at?.slice(0,5) }}–{{ slot.ends_at?.slice(0,5) }}</p>
                                    <span v-if="!isPeriod(slot)" class="text-[9px] uppercase tracking-wider font-bold text-amber-700 dark:text-amber-300">{{ slot.type }}</span>
                                </td>
                                <td v-for="sec in visibleSections" :key="sec.id"
                                    class="border-b border-r border-base-300 align-top p-0.5"
                                    :class="!slotApplies(slot, activeDay) ? 'bg-base-200/30' : ''">
                                    <!-- Slot doesn't apply -->
                                    <div v-if="!slotApplies(slot, activeDay)"
                                        class="text-center py-2 text-[9px] text-base-content/35 italic">
                                        no class
                                    </div>
                                    <!-- Break / lunch -->
                                    <div v-else-if="!isPeriod(slot)"
                                        class="text-center py-2 text-[9px] uppercase tracking-wider font-bold text-amber-700 dark:text-amber-300">
                                        {{ slot.type }}
                                    </div>
                                    <!-- Period cell -->
                                    <div v-else
                                        class="rounded-md p-1.5 text-center min-h-[44px] flex flex-col justify-center transition-colors"
                                        :class="[
                                            conflictsToday.has(`${slot.id}|${sec.id}`)
                                                ? 'ring-2 ring-rose-500 bg-rose-500/10'
                                                : '',
                                            cellHighlighted(entry(activeDay, slot.id, sec.id))
                                                ? 'ring-2 ring-violet-500 bg-violet-500/15 shadow'
                                                : '',
                                            (teacherFilter || subjectFilter) && !cellHighlighted(entry(activeDay, slot.id, sec.id))
                                                ? 'opacity-30'
                                                : '',
                                            heatMap && entry(activeDay, slot.id, sec.id)
                                                ? 'ring-1 ' + heatColor(entry(activeDay, slot.id, sec.id))
                                                : ''
                                        ]">
                                        <template v-if="entry(activeDay, slot.id, sec.id)">
                                            <p class="font-bold text-[11px] truncate leading-tight">
                                                {{ entry(activeDay, slot.id, sec.id).subject?.name || '—' }}
                                            </p>
                                            <p class="text-[9px] text-base-content/55 truncate leading-tight">
                                                {{ entry(activeDay, slot.id, sec.id).teacher?.name || 'No teacher' }}
                                            </p>
                                        </template>
                                        <template v-else>
                                            <span class="text-[9px] text-base-content/30 italic">—</span>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-else class="rounded-2xl border border-base-300 bg-base-100 p-10 text-center">
                <CalendarDaysIcon class="w-10 h-10 text-base-content/30 mx-auto mb-2" />
                <p class="font-bold text-sm">No sections match your filter</p>
                <p class="text-xs text-base-content/55 mt-0.5">Try clearing the search or class filter.</p>
            </div>

            <p class="text-[11px] text-base-content/45 text-center pb-4">
                Highlight a teacher or subject above to spotlight where they appear today.
                Conflicting cells (same teacher, same period) are outlined rose.
            </p>
        </div>
    </AppLayout>
</template>
