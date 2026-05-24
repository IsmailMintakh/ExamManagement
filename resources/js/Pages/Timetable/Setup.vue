<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import TimetableSubnav from '@/Components/timetable/TimetableSubnav.vue'
import { Head, useForm, router, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
    Cog6ToothIcon, PlusIcon, TrashIcon, ClockIcon, CalendarIcon,
    BookOpenIcon, CheckIcon, SparklesIcon, SunIcon, MoonIcon,
    AcademicCapIcon, InformationCircleIcon,
} from '@heroicons/vue/24/outline'
import { confirmAction } from '@/lib/swal'

const props = defineProps({
    school: Object,
    slots: { type: Array, default: () => [] },
    stages: { type: Object, default: () => ({}) },
    selectedStage: { type: String, default: null },
    countsPerStage: { type: Object, default: () => ({}) },
})

// ─── Stage tab strip ───
// The first tab is "All classes (default)" → edits the school-wide slots
// (stage IS NULL). Each other tab edits that stage's own bell schedule.
const tabs = computed(() => [
    { key: '', label: 'All classes (default)', count: props.countsPerStage[''] || props.countsPerStage[null] || 0 },
    ...Object.entries(props.stages).map(([key, label]) => ({
        key, label, count: props.countsPerStage[key] || 0,
    })),
])

const activeStage = computed(() => props.selectedStage || '')

function switchTab(stage) {
    const params = { school_id: props.school?.id }
    if (stage) params.stage = stage
    router.get(route('timetable.setup'), params, { preserveScroll: false })
}

// ─── Bell schedule presets ───
const PRESETS = [
    { key: 'winter', label: 'Winter', sub: '08:00–13:30, 7 periods', icon: 'CalendarIcon' },
    { key: 'summer', label: 'Summer', sub: '07:30–12:50, 8 periods', icon: 'SunIcon' },
    { key: 'ramadan', label: 'Ramadan', sub: '08:00–11:45, 6 short periods', icon: 'MoonIcon' },
]
async function applyPreset(key) {
    const ok = await confirmAction({
        title: `Apply the "${key}" preset?`,
        text: `Replaces the current bell schedule for ${activeStage.value ? props.stages[activeStage.value] : 'all classes (default)'}. You can tweak any slot afterwards.`,
        confirmText: 'Yes, apply preset',
        danger: true,
    })
    if (!ok) return
    router.post(route('timetable.setup.preset'),
        { preset: key, stage: activeStage.value || null },
        { preserveScroll: true })
}

const ALL_DAYS = [
    { code: 'mon', label: 'Mon' },
    { code: 'tue', label: 'Tue' },
    { code: 'wed', label: 'Wed' },
    { code: 'thu', label: 'Thu' },
    { code: 'fri', label: 'Fri' },
    { code: 'sat', label: 'Sat' },
]

// Defaults seed when there are no slots yet for the active scope.
function defaultSeed(stage) {
    // ECD / Pre-Primary: exactly 4 short periods + 1 break, finishes ~10:40.
    if (stage === 'pre_primary') {
        return [
            { name: 'Period 1', type: 'period', starts_at: '08:00', ends_at: '08:40', weekdays: ['mon','tue','wed','thu','fri','sat'] },
            { name: 'Period 2', type: 'period', starts_at: '08:40', ends_at: '09:20', weekdays: ['mon','tue','wed','thu','fri','sat'] },
            { name: 'Break',    type: 'break',  starts_at: '09:20', ends_at: '09:40', weekdays: ['mon','tue','wed','thu','fri','sat'] },
            { name: 'Period 3', type: 'period', starts_at: '09:40', ends_at: '10:20', weekdays: ['mon','tue','wed','thu','fri','sat'] },
            { name: 'Period 4', type: 'period', starts_at: '10:20', ends_at: '11:00', weekdays: ['mon','tue','wed','thu','fri','sat'] },
        ]
    }
    // Primary: ~6 periods.
    if (stage === 'primary') {
        return [
            { name: 'Period 1', type: 'period', starts_at: '08:00', ends_at: '08:40', weekdays: ['mon','tue','wed','thu','fri','sat'] },
            { name: 'Period 2', type: 'period', starts_at: '08:40', ends_at: '09:20', weekdays: ['mon','tue','wed','thu','fri','sat'] },
            { name: 'Period 3', type: 'period', starts_at: '09:20', ends_at: '10:00', weekdays: ['mon','tue','wed','thu','fri','sat'] },
            { name: 'Break',    type: 'break',  starts_at: '10:00', ends_at: '10:20', weekdays: ['mon','tue','wed','thu','fri','sat'] },
            { name: 'Period 4', type: 'period', starts_at: '10:20', ends_at: '11:00', weekdays: ['mon','tue','wed','thu','fri','sat'] },
            { name: 'Period 5', type: 'period', starts_at: '11:00', ends_at: '11:40', weekdays: ['mon','tue','wed','thu','sat'] },
            { name: 'Period 6', type: 'period', starts_at: '11:40', ends_at: '12:20', weekdays: ['mon','tue','wed','thu','sat'] },
        ]
    }
    // Middle / Secondary / Higher Secondary: 7–8 periods, full day.
    return [
        { name: 'Period 1', type: 'period', starts_at: '08:00', ends_at: '08:40', weekdays: ['mon','tue','wed','thu','fri','sat'] },
        { name: 'Period 2', type: 'period', starts_at: '08:40', ends_at: '09:20', weekdays: ['mon','tue','wed','thu','fri','sat'] },
        { name: 'Period 3', type: 'period', starts_at: '09:20', ends_at: '10:00', weekdays: ['mon','tue','wed','thu','fri','sat'] },
        { name: 'Break',    type: 'break',  starts_at: '10:00', ends_at: '10:20', weekdays: ['mon','tue','wed','thu','fri','sat'] },
        { name: 'Period 4', type: 'period', starts_at: '10:20', ends_at: '11:00', weekdays: ['mon','tue','wed','thu','fri','sat'] },
        { name: 'Period 5', type: 'period', starts_at: '11:00', ends_at: '11:40', weekdays: ['mon','tue','wed','thu','sat'] },
        { name: 'Lunch',    type: 'lunch',  starts_at: '11:40', ends_at: '12:10', weekdays: ['mon','tue','wed','thu','sat'] },
        { name: 'Period 6', type: 'period', starts_at: '12:10', ends_at: '12:50', weekdays: ['mon','tue','wed','thu','sat'] },
        { name: 'Period 7', type: 'period', starts_at: '12:50', ends_at: '13:30', weekdays: ['mon','tue','wed','thu','sat'] },
    ]
}

const form = useForm({
    stage: activeStage.value || null,
    slots: props.slots.length
        ? props.slots.map(s => ({
            id: s.id,
            name: s.name,
            type: s.type,
            starts_at: s.starts_at?.slice(0, 5),
            ends_at: s.ends_at?.slice(0, 5),
            weekdays: s.weekdays || ['mon','tue','wed','thu','fri','sat'],
        }))
        : defaultSeed(activeStage.value),
})

function addSlot() {
    const last = form.slots[form.slots.length - 1]
    const startTime = last?.ends_at || '08:00'
    form.slots.push({
        name: `Period ${form.slots.length + 1}`,
        type: 'period',
        starts_at: startTime,
        ends_at: addMinutes(startTime, 40),
        weekdays: ['mon','tue','wed','thu','fri','sat'],
    })
}

function removeSlot(i) { form.slots.splice(i, 1) }

function toggleDay(slot, day) {
    const i = slot.weekdays.indexOf(day)
    if (i > -1) slot.weekdays.splice(i, 1)
    else slot.weekdays.push(day)
}

function addMinutes(time, mins) {
    const [h, m] = time.split(':').map(Number)
    const total = h * 60 + m + mins
    const hh = String(Math.floor(total / 60) % 24).padStart(2, '0')
    const mm = String(total % 60).padStart(2, '0')
    return `${hh}:${mm}`
}

function submit() {
    form.post(route('timetable.setup.save', { school_id: props.school.id }),
        { preserveScroll: true })
}

const typeOptions = [
    { value: 'period', label: 'Period', icon: BookOpenIcon, color: 'text-emerald-600 dark:text-emerald-400' },
    { value: 'break', label: 'Break', icon: ClockIcon, color: 'text-amber-600 dark:text-amber-400' },
    { value: 'lunch', label: 'Lunch', icon: ClockIcon, color: 'text-orange-600 dark:text-orange-400' },
    { value: 'assembly', label: 'Assembly', icon: CalendarIcon, color: 'text-sky-600 dark:text-sky-400' },
]
function typeColor(type) {
    return typeOptions.find(t => t.value === type)?.color || ''
}

const scopeLabel = computed(() => activeStage.value
    ? `Bell schedule for ${props.stages[activeStage.value]}`
    : 'School-wide default bell schedule')

// True when this stage has no rows saved yet — the form is showing the
// suggested seed. We surface this prominently so the user knows they
// need to click Save for it to actually take effect.
const stageHasNoSavedSlots = computed(() =>
    !!activeStage.value && (props.countsPerStage[activeStage.value] || 0) === 0
)
const periodCount = computed(() => form.slots.filter(s => s.type === 'period').length)
</script>

<template>
    <Head title="Bell Schedule" />
    <AppLayout :breadcrumbs="[
        { label: 'Timetable', href: route('timetable.index') },
        { label: 'Bell Schedule' },
    ]">
        <div class="space-y-3 max-w-5xl mx-auto">

            <PageHeader title="Bell schedule"
                :subtitle="`${school?.name || 'the school'} — define each stage's daily rhythm`"
                :icon="Cog6ToothIcon" tone="violet">
                <template #actions>
                    <button @click="submit" :disabled="form.processing"
                        class="btn btn-primary btn-sm rounded-lg gap-2">
                        <CheckIcon class="w-4 h-4" />
                        {{ form.processing ? 'Saving…' : 'Save schedule' }}
                    </button>
                </template>
            </PageHeader>

            <TimetableSubnav :school-id="school?.id" />

            <!-- Stage tabs — one bell schedule per stage -->
            <section class="rounded-xl border border-base-300 bg-base-100 p-2 overflow-x-auto">
                <div class="flex items-center gap-1 min-w-max">
                    <button v-for="t in tabs" :key="t.key"
                        @click="switchTab(t.key)"
                        type="button"
                        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                        :class="activeStage === t.key
                            ? 'bg-violet-500/15 text-violet-700 dark:text-violet-300 ring-1 ring-violet-500/30'
                            : 'text-base-content/60 hover:bg-base-200 hover:text-base-content'">
                        <AcademicCapIcon class="h-3.5 w-3.5" />
                        {{ t.label }}
                        <span v-if="t.count" class="badge badge-xs badge-ghost tabular-nums">{{ t.count }}</span>
                    </button>
                </div>
            </section>

            <!-- Scope hint -->
            <div class="rounded-xl border border-base-300 bg-base-100 p-3 flex items-start gap-2 text-xs text-base-content/70">
                <InformationCircleIcon class="w-4 h-4 shrink-0 text-info mt-0.5" />
                <span>
                    Editing: <strong>{{ scopeLabel }}</strong>
                    · <strong>{{ periodCount }}</strong> period{{ periodCount === 1 ? '' : 's' }}
                    + {{ form.slots.length - periodCount }} break/lunch.
                    {{ activeStage
                        ? 'Classes in this stage will use this schedule once saved. If left empty, they fall back to the school-wide default.'
                        : 'Used by any class whose stage doesn\'t have its own schedule.' }}
                </span>
            </div>

            <!-- Big warning when this stage has no saved bell schedule yet -->
            <div v-if="stageHasNoSavedSlots"
                class="rounded-xl border border-warning/40 bg-warning/5 p-3 sm:p-4 flex items-start gap-3">
                <SparklesIcon class="w-5 h-5 shrink-0 text-warning mt-0.5" />
                <div class="flex-1">
                    <p class="text-sm font-bold">
                        {{ stages[activeStage] }} doesn't have its own bell schedule yet.
                    </p>
                    <p class="text-xs text-base-content/70 mt-1">
                        Right now, classes in this stage are using the school-wide default schedule
                        (which may be longer than you want). The form below is pre-filled with a
                        suggested {{ periodCount }}-period schedule — adjust as needed and click
                        <strong>Save schedule</strong> to lock it in. After that, every section in
                        {{ stages[activeStage] }} will follow it automatically.
                    </p>
                </div>
                <button @click="submit" :disabled="form.processing"
                    class="btn btn-warning btn-sm gap-1.5 shrink-0">
                    <CheckIcon class="w-4 h-4" />
                    Save now
                </button>
            </div>

            <!-- Bell-schedule presets -->
            <section class="rounded-xl border border-violet-500/25 bg-violet-500/5 p-3">
                <div class="flex items-center gap-2 mb-2">
                    <SparklesIcon class="w-4 h-4 text-violet-600 dark:text-violet-400" />
                    <h3 class="text-xs font-bold uppercase tracking-wider">Quick-start preset</h3>
                    <span class="text-[11px] text-base-content/55">Replaces the schedule for this stage — tweak afterwards.</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                    <button v-for="p in PRESETS" :key="p.key"
                        @click="applyPreset(p.key)"
                        type="button"
                        class="rounded-xl bg-base-100 ring-1 ring-base-300 hover:ring-violet-500/40 hover:bg-violet-500/5 p-3 text-left transition-colors">
                        <div class="flex items-center gap-2 mb-1">
                            <CalendarIcon v-if="p.key === 'winter'" class="w-4 h-4 text-sky-600" />
                            <SunIcon v-else-if="p.key === 'summer'" class="w-4 h-4 text-amber-600" />
                            <MoonIcon v-else class="w-4 h-4 text-emerald-600" />
                            <span class="font-bold text-sm">{{ p.label }}</span>
                        </div>
                        <p class="text-[11px] text-base-content/55">{{ p.sub }}</p>
                    </button>
                </div>
            </section>

            <!-- Slots editor -->
            <section class="rounded-xl border border-base-300 bg-base-100 overflow-hidden">
                <header class="px-4 py-2 border-b border-base-300 bg-base-200/40 flex items-center justify-between">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-base-content/55">Periods &amp; breaks</h2>
                    <button @click="addSlot" class="btn btn-ghost btn-xs rounded-lg gap-1.5">
                        <PlusIcon class="w-3.5 h-3.5" /> Add slot
                    </button>
                </header>

                <div class="divide-y divide-base-300">
                    <div v-for="(slot, i) in form.slots" :key="i"
                        class="px-4 py-2 flex flex-col gap-2 sm:flex-row sm:items-center hover:bg-base-200/30 transition-colors">
                        <div class="flex items-center gap-3 sm:w-7 shrink-0">
                            <span class="font-mono text-xs font-bold text-base-content/40 tabular-nums w-6 text-right">{{ i + 1 }}.</span>
                        </div>

                        <div class="flex-1 min-w-0 grid grid-cols-2 gap-2">
                            <input v-model="slot.name" type="text" placeholder="Slot name"
                                class="input input-bordered input-sm rounded-lg text-sm font-semibold" />
                            <select v-model="slot.type" class="select select-bordered select-sm rounded-lg text-sm">
                                <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <input v-model="slot.starts_at" type="time"
                                class="input input-bordered input-sm rounded-lg w-24 font-mono text-xs" />
                            <span class="text-base-content/40 text-xs">→</span>
                            <input v-model="slot.ends_at" type="time"
                                class="input input-bordered input-sm rounded-lg w-24 font-mono text-xs" />
                        </div>

                        <div class="flex items-center gap-1 shrink-0 flex-wrap">
                            <button v-for="d in ALL_DAYS" :key="d.code"
                                type="button"
                                @click="toggleDay(slot, d.code)"
                                class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider transition-colors"
                                :class="slot.weekdays.includes(d.code)
                                    ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-500/30'
                                    : 'bg-base-200 text-base-content/40 ring-1 ring-base-300 hover:bg-base-300'"
                                :title="slot.weekdays.includes(d.code) ? `${d.label} active — click to disable` : `${d.label} disabled — click to enable`">
                                {{ d.label }}
                            </button>
                        </div>

                        <button @click="removeSlot(i)" class="btn btn-ghost btn-sm btn-square rounded-lg text-rose-500 shrink-0">
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <footer class="px-5 py-3 border-t border-base-300 bg-base-200/30 text-xs text-base-content/55">
                    💡 <strong>Tip:</strong> For Friday half-days, uncheck <strong>FRI</strong> on the late-period rows. Those slots won't render on Friday in the timetable.
                </footer>
            </section>

            <p v-if="form.errors.slots" class="text-xs text-error">{{ form.errors.slots }}</p>
        </div>
    </AppLayout>
</template>
