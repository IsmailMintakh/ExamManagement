<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import PageHeader from '@/Components/PageHeader.vue'
import TimetableSubnav from '@/Components/timetable/TimetableSubnav.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    CalendarIcon, ExclamationTriangleIcon, BoltIcon, PrinterIcon,
    EyeIcon, PencilSquareIcon, Cog6ToothIcon,
} from '@heroicons/vue/24/outline'
import { confirmAction } from '@/lib/swal'

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

function completionPct(sec) {
    const total = props.stats?.total_period_slots || 0
    if (!total) return 0
    return Math.min(100, Math.round((sec.entries_count / (total * 6)) * 100))
}
function completionLabel(sec) {
    if (!sec.entries_count) return 'Empty'
    if (completionPct(sec) >= 80) return 'Complete'
    if (completionPct(sec) >= 30) return 'Partial'
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

const statChips = computed(() => [
    { label: 'Classes', value: props.stats.total_classes || 0 },
    { label: 'Sections', value: props.stats.total_sections || 0, hint: `${totalSectionsWithSchedule.value} built` },
    { label: 'Periods/day', value: props.stats.total_period_slots || 0 },
    { label: 'Absent today', value: props.stats.today_absences || 0, tone: props.stats.today_absences > 0 ? 'rose' : '' },
    { label: 'Adjustments today', value: props.stats.today_covers || 0, tone: props.stats.today_covers > 0 ? 'amber' : '' },
])

// ─── Auto-generate ───
const generating = ref(false)
const overwrite = ref(false)
async function autoGenerate() {
    const hasExisting = totalSectionsWithSchedule.value > 0
    let text = 'Auto-generate timetables for every unlocked section from the subject–teacher assignments?'
    if (hasExisting && !overwrite.value) {
        text += ` ${totalSectionsWithSchedule.value} section(s) already have a timetable and will be SKIPPED (tick "Overwrite" to rebuild them).`
    } else if (overwrite.value) {
        text += ' OVERWRITE is on — sections that already have a timetable will be wiped and rebuilt. Locked sections are never touched.'
    }
    const ok = await confirmAction({
        title: 'Auto-generate timetables?',
        text,
        confirmText: overwrite.value ? 'Yes, overwrite & rebuild' : 'Yes, generate',
        danger: overwrite.value,
    })
    if (!ok) return
    router.post(route('timetable.generate', { school_id: props.school?.id }),
        { overwrite: overwrite.value },
        { preserveScroll: true, onStart: () => { generating.value = true }, onFinish: () => { generating.value = false } },
    )
}
</script>

<template>
    <Head title="Timetable" />
    <AppLayout :breadcrumbs="[{ label: 'Timetable' }]">
        <div class="space-y-3 max-w-[1600px] mx-auto">

            <PageHeader :title="school?.name || 'Timetable'"
                :subtitle="school ? 'Schedules, teacher assignments & daily class adjustments' : 'Pick a school to begin'"
                :icon="CalendarIcon" tone="violet">
                <template #actions>
                    <div v-if="allSchools.length" class="min-w-[200px]">
                        <SearchableSelect :model-value="currentSchoolId" size="sm"
                            :options="allSchools.map(s => ({ value: s.id, label: s.name }))"
                            placeholder="Select school"
                            @change="(v) => switchSchool(v)" />
                    </div>
                    <a v-if="school && hasSchedule" :href="route('timetable.routine.pdf', { school_id: school.id })" target="_blank"
                        class="btn btn-sm rounded-lg gap-1.5 btn-outline">
                        <PrinterIcon class="w-4 h-4" /> Routine PDF
                    </a>
                    <a v-if="school && hasSchedule" :href="route('timetable.school.pdf', { school_id: school.id })" target="_blank"
                        class="btn btn-sm rounded-lg gap-1.5 btn-outline">
                        <PrinterIcon class="w-4 h-4" /> Booklet PDF
                    </a>
                </template>
            </PageHeader>

            <TimetableSubnav :school-id="school?.id" />

            <!-- Empty states -->
            <div v-if="!school" class="rounded-xl border border-base-300 bg-base-100 p-8 text-center text-sm text-base-content/60">
                No school selected.
            </div>
            <div v-else-if="!hasSchedule" class="rounded-xl border border-amber-500/30 bg-amber-500/5 p-6 flex items-center gap-4">
                <ExclamationTriangleIcon class="w-8 h-8 text-amber-500 shrink-0" />
                <div class="flex-1">
                    <p class="font-bold text-sm">Bell schedule not set up</p>
                    <p class="text-xs text-base-content/60">Define periods &amp; breaks before building class timetables.</p>
                </div>
                <Link :href="route('timetable.setup', { school_id: school?.id })" class="btn btn-primary btn-sm rounded-lg gap-1.5">
                    <Cog6ToothIcon class="w-4 h-4" /> Set up
                </Link>
            </div>

            <template v-if="school && hasSchedule">
                <!-- Dense stat strip -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                    <div v-for="s in statChips" :key="s.label"
                        class="rounded-xl border border-base-300 bg-base-100 px-3 py-2">
                        <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/50">{{ s.label }}</p>
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-xl font-extrabold tabular-nums"
                                :class="{ 'text-rose-600': s.tone === 'rose', 'text-amber-600': s.tone === 'amber' }">
                                {{ s.value }}
                            </span>
                            <span v-if="s.hint" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">{{ s.hint }}</span>
                        </div>
                    </div>
                </div>

                <!-- Auto-generate bar -->
                <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/5 px-4 py-2.5 flex flex-wrap items-center gap-3">
                    <BoltIcon class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                    <div class="flex-1 min-w-[200px]">
                        <p class="text-sm font-bold leading-tight">Auto-generate timetables</p>
                        <p class="text-[11px] text-base-content/55">From subject–teacher assignments — balanced, no double-booking.</p>
                    </div>
                    <label class="flex items-center gap-1.5 text-xs font-semibold cursor-pointer select-none">
                        <input type="checkbox" v-model="overwrite" class="checkbox checkbox-xs checkbox-error" />
                        Overwrite existing
                    </label>
                    <button type="button" @click="autoGenerate" :disabled="generating"
                        class="btn btn-sm rounded-lg gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white border-0 disabled:opacity-60">
                        <BoltIcon class="w-4 h-4" /> {{ generating ? 'Generating…' : 'Auto-generate' }}
                    </button>
                </div>

                <!-- Classes & sections — dense table -->
                <div v-if="classes.length" class="rounded-xl border border-base-300 bg-base-100 overflow-hidden">
                    <div class="px-4 py-2 border-b border-base-300 flex items-center gap-2 bg-base-200/40">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-base-content/55">Classes &amp; sections</h2>
                        <span class="text-[11px] text-base-content/45">{{ stats.total_sections || 0 }} sections · {{ totalSectionsWithSchedule }} built</span>
                    </div>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-base-300">
                            <template v-for="cls in classes" :key="cls.id">
                                <tr class="bg-base-200/30">
                                    <td colspan="3" class="px-4 py-1.5 text-xs font-bold text-base-content/70">
                                        {{ cls.name }}
                                        <span class="text-base-content/40 font-normal">· {{ cls.sections.length }} section{{ cls.sections.length === 1 ? '' : 's' }}</span>
                                    </td>
                                </tr>
                                <tr v-if="!cls.sections.length">
                                    <td colspan="3" class="px-6 py-2 text-xs text-base-content/40 italic">No sections yet.</td>
                                </tr>
                                <tr v-for="sec in cls.sections" :key="sec.id" class="hover:bg-base-200/30">
                                    <td class="px-6 py-2 w-1/2">
                                        <span class="font-semibold">Section {{ sec.name }}</span>
                                        <div class="mt-1 h-1 w-32 rounded-full bg-base-200 overflow-hidden">
                                            <div class="h-full transition-all"
                                                :class="completionPct(sec) >= 80 ? 'bg-emerald-500' : completionPct(sec) >= 30 ? 'bg-amber-500' : 'bg-sky-500'"
                                                :style="{ width: (sec.entries_count ? Math.max(completionPct(sec), 6) : 0) + '%' }"></div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="text-[10px] uppercase tracking-wider font-bold px-2 py-0.5 rounded-md"
                                            :class="completionColor(sec)">{{ completionLabel(sec) }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap">
                                        <Link :href="route('timetable.section', sec.id)" class="btn btn-ghost btn-xs rounded-lg" title="View">
                                            <EyeIcon class="w-3.5 h-3.5" />
                                        </Link>
                                        <Link :href="route('timetable.builder', sec.id)" class="btn btn-ghost btn-xs rounded-lg" title="Edit">
                                            <PencilSquareIcon class="w-3.5 h-3.5" />
                                        </Link>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
