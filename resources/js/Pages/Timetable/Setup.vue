<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    Cog6ToothIcon, PlusIcon, TrashIcon, ClockIcon, CalendarIcon,
    ArrowLeftIcon, BookOpenIcon, CheckIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    school: Object,
    slots: { type: Array, default: () => [] },
})

const ALL_DAYS = [
    { code: 'mon', label: 'Mon' },
    { code: 'tue', label: 'Tue' },
    { code: 'wed', label: 'Wed' },
    { code: 'thu', label: 'Thu' },
    { code: 'fri', label: 'Fri' },
    { code: 'sat', label: 'Sat' },
]

// Build initial form rows from props.slots, or seed defaults if empty.
function defaultSeed() {
    return [
        { name: 'Period 1',  type: 'period',  starts_at: '08:00', ends_at: '08:40', weekdays: ['mon','tue','wed','thu','fri','sat'] },
        { name: 'Period 2',  type: 'period',  starts_at: '08:40', ends_at: '09:20', weekdays: ['mon','tue','wed','thu','fri','sat'] },
        { name: 'Period 3',  type: 'period',  starts_at: '09:20', ends_at: '10:00', weekdays: ['mon','tue','wed','thu','fri','sat'] },
        { name: 'Break',     type: 'break',   starts_at: '10:00', ends_at: '10:20', weekdays: ['mon','tue','wed','thu','fri','sat'] },
        { name: 'Period 4',  type: 'period',  starts_at: '10:20', ends_at: '11:00', weekdays: ['mon','tue','wed','thu','fri','sat'] },
        { name: 'Period 5',  type: 'period',  starts_at: '11:00', ends_at: '11:40', weekdays: ['mon','tue','wed','thu','sat'] },   // skip Friday
        { name: 'Lunch',     type: 'lunch',   starts_at: '11:40', ends_at: '12:10', weekdays: ['mon','tue','wed','thu','sat'] },
        { name: 'Period 6',  type: 'period',  starts_at: '12:10', ends_at: '12:50', weekdays: ['mon','tue','wed','thu','sat'] },
        { name: 'Period 7',  type: 'period',  starts_at: '12:50', ends_at: '13:30', weekdays: ['mon','tue','wed','thu','sat'] },
    ]
}

const form = useForm({
    slots: props.slots.length
        ? props.slots.map(s => ({
            id: s.id,
            name: s.name,
            type: s.type,
            starts_at: s.starts_at?.slice(0, 5),
            ends_at: s.ends_at?.slice(0, 5),
            weekdays: s.weekdays || ['mon','tue','wed','thu','fri','sat'],
        }))
        : defaultSeed(),
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

function removeSlot(i) {
    form.slots.splice(i, 1)
}

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
    form.post(route('timetable.setup.save', { school_id: props.school.id }), {
        preserveScroll: true,
    })
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
</script>

<template>
    <Head title="Bell Schedule" />
    <AppLayout :breadcrumbs="[
        { label: 'Timetable', href: route('timetable.index') },
        { label: 'Bell Schedule' },
    ]">
        <div class="space-y-5 max-w-5xl mx-auto">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                <div>
                    <Link :href="route('timetable.index', { school_id: school?.id })"
                        class="btn btn-ghost btn-sm gap-1 mb-2 -ml-2">
                        <ArrowLeftIcon class="w-4 h-4" /> Back
                    </Link>
                    <h1 class="text-2xl font-extrabold tracking-tight flex items-center gap-2">
                        <Cog6ToothIcon class="w-6 h-6 text-violet-600 dark:text-violet-400" />
                        Bell Schedule
                    </h1>
                    <p class="text-sm text-base-content/55 mt-1">
                        Define {{ school?.name }}'s daily periods. Uncheck Friday on a row to make it a half-day slot.
                    </p>
                </div>
                <button @click="submit" :disabled="form.processing"
                    class="btn btn-primary rounded-xl gap-2">
                    <CheckIcon class="w-4 h-4" />
                    {{ form.processing ? 'Saving…' : 'Save schedule' }}
                </button>
            </div>

            <!-- Slots editor -->
            <section class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <header class="px-5 py-3 border-b border-base-300 flex items-center justify-between">
                    <h2 class="text-sm font-bold">Periods &amp; breaks</h2>
                    <button @click="addSlot" class="btn btn-ghost btn-sm rounded-lg gap-1.5">
                        <PlusIcon class="w-4 h-4" /> Add slot
                    </button>
                </header>

                <div class="divide-y divide-base-300">
                    <div v-for="(slot, i) in form.slots" :key="i"
                        class="px-4 sm:px-5 py-4 flex flex-col gap-3 sm:flex-row sm:items-center hover:bg-base-200/30 transition-colors">
                        <!-- Order number -->
                        <div class="flex items-center gap-3 sm:w-7 shrink-0">
                            <span class="font-mono text-xs font-bold text-base-content/40 tabular-nums w-6 text-right">{{ i + 1 }}.</span>
                        </div>

                        <!-- Name + type -->
                        <div class="flex-1 min-w-0 grid grid-cols-2 gap-2">
                            <input v-model="slot.name" type="text" placeholder="Slot name"
                                class="input input-bordered input-sm rounded-lg text-sm font-semibold" />
                            <select v-model="slot.type" class="select select-bordered select-sm rounded-lg text-sm">
                                <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>

                        <!-- Time range -->
                        <div class="flex items-center gap-2 shrink-0">
                            <input v-model="slot.starts_at" type="time"
                                class="input input-bordered input-sm rounded-lg w-24 font-mono text-xs" />
                            <span class="text-base-content/40 text-xs">→</span>
                            <input v-model="slot.ends_at" type="time"
                                class="input input-bordered input-sm rounded-lg w-24 font-mono text-xs" />
                        </div>

                        <!-- Day toggles -->
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
                    💡 <strong>Tip:</strong> For Friday half-days, uncheck <strong>FRI</strong> on the late-period rows. Those slots will simply not render on Friday in the timetable grid.
                </footer>
            </section>

            <p v-if="form.errors.slots" class="text-xs text-error">{{ form.errors.slots }}</p>
        </div>
    </AppLayout>
</template>
