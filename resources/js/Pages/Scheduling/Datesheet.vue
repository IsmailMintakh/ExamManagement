<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import SchedulingSubnav from '@/Components/scheduling/SchedulingSubnav.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    CalendarDaysIcon,
    DocumentArrowDownIcon,
    CheckIcon,
    ArrowLeftIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    BoltIcon,
    XMarkIcon,
    InformationCircleIcon,
} from '@heroicons/vue/24/outline'
import { confirmAction } from '@/lib/swal'

const props = defineProps({
    exam: Object,
    rows: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
    autoModeDefault: { type: String, default: 'terminal' }, // 'terminal' | 'period_based'
})

const classFilter = ref('')

const form = useForm({
    schedules: props.rows.map(r => ({
        subject_id: r.subject_id,
        school_class_id: r.school_class_id,
        exam_date: r.exam_date || '',
        start_time: r.start_time || '',
        end_time: r.end_time || '',
        instructions: r.instructions || '',
        _subject_name: r.subject_name,
        _class_name: r.class_name,
        _subject_code: r.subject_code,
        _total_marks: r.total_marks,
    })),
})

const filtered = computed(() => {
    if (!classFilter.value) return form.schedules
    return form.schedules.filter(s => s.school_class_id === Number(classFilter.value))
})

function computeDuration(row) {
    if (!row.exam_date || !row.start_time || !row.end_time) return null
    const s = new Date(`${row.exam_date}T${row.start_time}`)
    const e = new Date(`${row.exam_date}T${row.end_time}`)
    if (isNaN(s) || isNaN(e)) return null
    const mins = Math.round((e - s) / 60000)
    return mins > 0 ? mins : null
}

// ─── Live conflict detection (mirrors the backend check) ───
// Two papers in the same class can't share a time window on the same date.
// We compute conflicts client-side so the user sees them as they type, instead
// of only after submitting.
const conflictMap = computed(() => {
    const map = new Map() // index → conflicting subject name
    const rows = form.schedules
    for (let i = 0; i < rows.length; i++) {
        const a = rows[i]
        if (!a.exam_date || !a.start_time || !a.end_time) continue
        const aStart = new Date(`${a.exam_date}T${a.start_time}`).getTime()
        const aEnd = new Date(`${a.exam_date}T${a.end_time}`).getTime()
        if (!aStart || !aEnd || aEnd <= aStart) continue
        for (let j = i + 1; j < rows.length; j++) {
            const b = rows[j]
            if (!b.exam_date || !b.start_time || !b.end_time) continue
            if (Number(a.school_class_id) !== Number(b.school_class_id)) continue
            if (a.exam_date !== b.exam_date) continue
            const bStart = new Date(`${b.exam_date}T${b.start_time}`).getTime()
            const bEnd = new Date(`${b.exam_date}T${b.end_time}`).getTime()
            if (aStart < bEnd && bStart < aEnd) {
                if (!map.has(i)) map.set(i, [])
                if (!map.has(j)) map.set(j, [])
                map.get(i).push(b._subject_name)
                map.get(j).push(a._subject_name)
            }
        }
    }
    return map
})

function rowConflict(row) {
    const idx = form.schedules.indexOf(row)
    return conflictMap.value.get(idx) || null
}

const totalConflicts = computed(() => conflictMap.value.size)

function save() {
    if (totalConflicts.value > 0) {
        // Submit anyway — backend will reject and show field-level errors.
        // Frontend won't block in case the user wants to see the server message.
    }
    form.post(route('scheduling.store-schedule', props.exam.id), {
        preserveScroll: true,
    })
}

function openPdf() {
    window.open(route('scheduling.datesheet-pdf', props.exam.id), '_blank')
}

// ─── Auto-generate modal ───
const autoOpen = ref(false)
const autoSubmitting = ref(false)
const auto = ref({
    mode: props.autoModeDefault || 'terminal',
    start_date: props.exam?.start_date || new Date().toISOString().slice(0, 10),
    end_date: props.exam?.end_date || '',
    default_start_time: '09:00',
    default_duration_minutes: 120,
    off_days: [0], // Sunday off
    holiday_input: '',
    holidays: [],
    overwrite_existing: false,
})

const WEEKDAY_LABELS = [
    { value: 0, short: 'Sun' },
    { value: 1, short: 'Mon' },
    { value: 2, short: 'Tue' },
    { value: 3, short: 'Wed' },
    { value: 4, short: 'Thu' },
    { value: 5, short: 'Fri' },
    { value: 6, short: 'Sat' },
]

function toggleOffDay(d) {
    const i = auto.value.off_days.indexOf(d)
    if (i > -1) auto.value.off_days.splice(i, 1)
    else auto.value.off_days.push(d)
}

function addHoliday() {
    const v = auto.value.holiday_input
    if (v && !auto.value.holidays.includes(v)) auto.value.holidays.push(v)
    auto.value.holiday_input = ''
}
function removeHoliday(d) {
    auto.value.holidays = auto.value.holidays.filter(x => x !== d)
}

async function submitAuto() {
    if (form.schedules.some(s => s.exam_date) && !auto.value.overwrite_existing) {
        const ok = await confirmAction({
            title: 'Keep existing dates?',
            text: 'Some papers already have a date. Auto-gen will fill only the empty rows. Tick "Overwrite existing" first if you want to rebuild from scratch.',
            confirmText: 'Continue (fill empty only)',
        })
        if (!ok) return
    }
    autoSubmitting.value = true
    router.post(route('scheduling.datesheet-auto', props.exam.id), {
        mode: auto.value.mode,
        start_date: auto.value.start_date || null,
        end_date: auto.value.end_date || null,
        default_start_time: auto.value.default_start_time || null,
        default_duration_minutes: auto.value.default_duration_minutes || 120,
        off_days: auto.value.off_days,
        holidays: auto.value.holidays,
        overwrite_existing: auto.value.overwrite_existing,
    }, {
        preserveScroll: true,
        onFinish: () => { autoSubmitting.value = false; autoOpen.value = false },
    })
}

// ─── Bulk-apply time from one row to others ───
// Common case: every paper in this exam runs 09:00–12:00. User fills the
// first row, hits "Apply to all" and we copy to all OTHER rows. Default
// behavior is to skip rows that already have a time set, so the user doesn't
// accidentally clobber rows they've already customised. The "overwrite all"
// variant clobbers everything for the rare reschedule case.
function applyTimeToAll(sourceRow, { overwrite = false } = {}) {
    if (!sourceRow.start_time || !sourceRow.end_time) return
    let changed = 0
    for (const r of form.schedules) {
        if (r === sourceRow) continue
        if (!overwrite && r.start_time && r.end_time) continue
        r.start_time = sourceRow.start_time
        r.end_time = sourceRow.end_time
        changed++
    }
    return changed
}

// Same idea for the date — user fills the date for one paper, applies to all
// (useful for monthly tests that all happen on the same day).
function applyDateToAll(sourceRow, { overwrite = false } = {}) {
    if (!sourceRow.exam_date) return
    let changed = 0
    for (const r of form.schedules) {
        if (r === sourceRow) continue
        if (!overwrite && r.exam_date) continue
        r.exam_date = sourceRow.exam_date
        changed++
    }
    return changed
}

// Same again for instructions — common case is a single instruction line that
// applies to every paper ("Bring own stationery, calculators not allowed").
function applyInstructionsToAll(sourceRow, { overwrite = false } = {}) {
    if (!sourceRow.instructions) return
    let changed = 0
    for (const r of form.schedules) {
        if (r === sourceRow) continue
        if (!overwrite && r.instructions) continue
        r.instructions = sourceRow.instructions
        changed++
    }
    return changed
}

// "Did at least one OTHER row already get the same time?" — used to hide the
// button once the user has already applied (no point showing "Apply to all"
// when there's nothing left to change).
function isFirstWithTime(row) {
    if (!row.start_time || !row.end_time) return false
    const idx = form.schedules.indexOf(row)
    for (let i = 0; i < idx; i++) {
        if (form.schedules[i].start_time && form.schedules[i].end_time) return false
    }
    return true
}
function isFirstWithDate(row) {
    if (!row.exam_date) return false
    const idx = form.schedules.indexOf(row)
    for (let i = 0; i < idx; i++) {
        if (form.schedules[i].exam_date) return false
    }
    return true
}
function isFirstWithInstructions(row) {
    if (!row.instructions) return false
    const idx = form.schedules.indexOf(row)
    for (let i = 0; i < idx; i++) {
        if (form.schedules[i].instructions) return false
    }
    return true
}
</script>

<template>
    <Head :title="`Date Sheet — ${exam.name}`" />
    <AppLayout
        :breadcrumbs="[
            { label: 'Exams', href: route('exams.index') },
            { label: exam.name, href: route('exams.show', exam.id) },
            { label: 'Scheduling', href: route('scheduling.index', exam.id) },
            { label: 'Date Sheet' },
        ]"
    >
        <div class="space-y-4 max-w-5xl mx-auto">
            <SchedulingSubnav :exam-id="exam.id" />
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <CalendarDaysIcon class="h-6 w-6 text-primary" />
                        Date Sheet
                    </h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        Set exam dates, start/end times and instructions. Duration is computed automatically.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('scheduling.index', exam.id)" class="btn btn-ghost btn-sm gap-1.5">
                        <ArrowLeftIcon class="h-4 w-4" /> Back
                    </Link>
                    <button @click="autoOpen = true" class="btn btn-accent btn-sm gap-1.5">
                        <BoltIcon class="h-4 w-4" /> Auto-generate
                    </button>
                    <button @click="openPdf" class="btn btn-outline btn-sm gap-1.5">
                        <DocumentArrowDownIcon class="h-4 w-4" /> Generate PDF
                    </button>
                    <button @click="save" :disabled="form.processing" class="btn btn-primary btn-sm gap-1.5">
                        <CheckIcon class="h-4 w-4" /> {{ form.processing ? 'Saving…' : 'Save Schedule' }}
                    </button>
                </div>
            </div>

            <!-- Conflict alert — only when client-side detection finds overlaps -->
            <div v-if="totalConflicts > 0"
                class="rounded-xl border border-error/30 bg-error/10 px-4 py-3 flex items-start gap-3">
                <ExclamationTriangleIcon class="h-5 w-5 text-error flex-shrink-0 mt-0.5" />
                <div class="flex-1 text-sm">
                    <p class="font-bold text-error">
                        {{ totalConflicts }} schedule conflict{{ totalConflicts === 1 ? '' : 's' }} detected
                    </p>
                    <p class="text-[12px] text-error/85 mt-0.5">
                        The same class can't sit two papers at the same time. Conflicting rows are highlighted below — adjust the date or time before saving.
                    </p>
                </div>
            </div>

            <div class="card-section">
                <div class="card-header flex items-center justify-between">
                    <h3>Subject Schedule ({{ filtered.length }})</h3>
                    <div class="w-48">
                        <SearchableSelect v-model="classFilter" size="sm"
                            :options="[{ value: '', label: 'All Classes' }, ...classes.map(c => ({ value: c.id, label: c.name }))]"
                            placeholder="All Classes" />
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table v-if="filtered.length" class="table table-sm">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Subject</th>
                                <th style="width:10%">Marks</th>
                                <th style="width:14%">Date</th>
                                <th style="width:11%">Start</th>
                                <th style="width:11%">End</th>
                                <th style="width:10%">Duration</th>
                                <th>Instructions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in filtered" :key="row.subject_id + '-' + row.school_class_id"
                                :class="rowConflict(row) ? 'bg-error/5' : ''">
                                <td class="text-sm">{{ row._class_name }}</td>
                                <td>
                                    <div class="flex items-center gap-1.5">
                                        <ExclamationTriangleIcon v-if="rowConflict(row)"
                                            class="h-4 w-4 text-error flex-shrink-0"
                                            :title="`Overlaps with: ${rowConflict(row).join(', ')}`" />
                                        <div>
                                            <div class="font-medium">{{ row._subject_name }}</div>
                                            <div v-if="row._subject_code" class="text-2xs text-base-content/50">{{ row._subject_code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge badge-sm badge-ghost">{{ row._total_marks }}</span></td>
                                <td>
                                    <input v-model="row.exam_date" type="date"
                                        :min="exam.start_date || undefined"
                                        :max="exam.end_date || undefined"
                                        class="input input-bordered input-xs w-full"
                                        :class="rowConflict(row) ? 'input-error' : ''" />
                                    <button v-if="isFirstWithDate(row)" type="button"
                                        @click="applyDateToAll(row)"
                                        class="text-[10px] text-primary font-semibold hover:underline mt-0.5"
                                        title="Copy this date to all rows that don't have a date yet">
                                        ↓ Apply to all
                                    </button>
                                </td>
                                <td>
                                    <input v-model="row.start_time" type="time"
                                        class="input input-bordered input-xs w-full"
                                        :class="rowConflict(row) ? 'input-error' : ''" />
                                </td>
                                <td>
                                    <input v-model="row.end_time" type="time"
                                        :min="row.start_time || undefined"
                                        class="input input-bordered input-xs w-full"
                                        :class="rowConflict(row) ? 'input-error' : ''" />
                                    <button v-if="isFirstWithTime(row)" type="button"
                                        @click="applyTimeToAll(row)"
                                        class="text-[10px] text-primary font-semibold hover:underline mt-0.5"
                                        title="Copy this time to all rows that don't have one yet">
                                        ↓ Apply to all
                                    </button>
                                </td>
                                <td class="text-xs">
                                    <span v-if="computeDuration(row)" class="inline-flex items-center gap-1 text-base-content/60">
                                        <ClockIcon class="h-3 w-3" />
                                        {{ computeDuration(row) }} min
                                    </span>
                                    <span v-else class="text-base-content/30">—</span>
                                </td>
                                <td>
                                    <input v-model="row.instructions" type="text" placeholder="Optional"
                                        class="input input-bordered input-xs w-full" />
                                    <button v-if="isFirstWithInstructions(row)" type="button"
                                        @click="applyInstructionsToAll(row)"
                                        class="text-[10px] text-primary font-semibold hover:underline mt-0.5"
                                        title="Copy this instruction to all rows that don't have one yet">
                                        ↓ Apply to all
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <EmptyState v-else title="No subjects to schedule" description="Add subjects to this exam first." />
                </div>
            </div>
        </div>

        <!-- ═══════════ AUTO-GENERATE MODAL ═══════════ -->
        <div v-if="autoOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-base-content/40 p-4"
            @click.self="autoOpen = false">
            <div class="bg-base-100 rounded-2xl shadow-2xl border border-base-300 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <header class="px-5 py-4 border-b border-base-300 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-accent/15 text-accent flex items-center justify-center">
                        <BoltIcon class="w-5 h-5" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-base font-bold">Auto-generate date sheet</h3>
                        <p class="text-xs text-base-content/55">
                            Pick a strategy and the system fills every paper's date + time, skipping Sundays and holidays.
                        </p>
                    </div>
                    <button @click="autoOpen = false" class="btn btn-ghost btn-sm btn-square">
                        <XMarkIcon class="w-4 h-4" />
                    </button>
                </header>

                <div class="p-5 space-y-4">
                    <!-- Mode -->
                    <div>
                        <p class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 mb-2">Strategy</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <button type="button" @click="auto.mode = 'terminal'"
                                class="rounded-xl p-3 text-left ring-2 transition-colors"
                                :class="auto.mode === 'terminal' ? 'ring-primary bg-primary/5' : 'ring-base-300 hover:bg-base-200/40'">
                                <p class="font-bold text-sm">Terminal (one paper per class per day)</p>
                                <p class="text-[11px] text-base-content/55 mt-1">
                                    Spread papers across consecutive working days. Each class advances one subject per day.
                                    Use for First/Mid/Annual/Send-up Exams.
                                </p>
                            </button>
                            <button type="button" @click="auto.mode = 'period_based'"
                                class="rounded-xl p-3 text-left ring-2 transition-colors"
                                :class="auto.mode === 'period_based' ? 'ring-primary bg-primary/5' : 'ring-base-300 hover:bg-base-200/40'">
                                <p class="font-bold text-sm">Period-based (test in class period)</p>
                                <p class="text-[11px] text-base-content/55 mt-1">
                                    Schedule every paper on one day, each at the period that subject is normally taught.
                                    Use for Monthly / Unit Tests.
                                </p>
                            </button>
                        </div>
                    </div>

                    <!-- Dates -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11px] uppercase tracking-wider font-bold text-base-content/55">Start date</label>
                            <input v-model="auto.start_date" type="date"
                                class="input input-bordered input-sm w-full mt-1 font-mono" />
                        </div>
                        <div v-if="auto.mode === 'terminal'">
                            <label class="text-[11px] uppercase tracking-wider font-bold text-base-content/55">
                                End date <span class="font-medium normal-case text-base-content/40">· optional</span>
                            </label>
                            <input v-model="auto.end_date" type="date" :min="auto.start_date || undefined"
                                class="input input-bordered input-sm w-full mt-1 font-mono" />
                        </div>
                    </div>

                    <!-- Default time + duration (terminal mode only) -->
                    <div v-if="auto.mode === 'terminal'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11px] uppercase tracking-wider font-bold text-base-content/55">Default start time</label>
                            <input v-model="auto.default_start_time" type="time"
                                class="input input-bordered input-sm w-full mt-1 font-mono" />
                        </div>
                        <div>
                            <label class="text-[11px] uppercase tracking-wider font-bold text-base-content/55">Duration (minutes)</label>
                            <input v-model.number="auto.default_duration_minutes" type="number" min="15" max="300"
                                class="input input-bordered input-sm w-full mt-1 font-mono" />
                        </div>
                    </div>

                    <div v-if="auto.mode === 'period_based'"
                        class="rounded-lg border border-info/40 bg-info/5 p-3 flex items-start gap-2 text-xs text-base-content/70">
                        <InformationCircleIcon class="w-4 h-4 shrink-0 text-info mt-0.5" />
                        <span>
                            Each paper will run at the period when that subject is normally taught (read from the timetable).
                            If a subject isn't on the timetable yet, we fall back to <strong>{{ auto.default_start_time }}</strong> for
                            <strong>{{ auto.default_duration_minutes }} min</strong>.
                        </span>
                    </div>

                    <!-- Off days -->
                    <div>
                        <p class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 mb-2">
                            Off days <span class="font-medium normal-case text-base-content/40">· no papers on these</span>
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            <button v-for="d in WEEKDAY_LABELS" :key="d.value"
                                type="button" @click="toggleOffDay(d.value)"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold ring-1 transition-colors"
                                :class="auto.off_days.includes(d.value)
                                    ? 'bg-rose-500/15 text-rose-700 dark:text-rose-300 ring-rose-500/30'
                                    : 'bg-base-200/40 text-base-content/60 ring-base-300 hover:bg-base-200'">
                                {{ d.short }}
                            </button>
                        </div>
                    </div>

                    <!-- Holidays -->
                    <div>
                        <p class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 mb-2">
                            Holidays <span class="font-medium normal-case text-base-content/40">· also skipped</span>
                        </p>
                        <div class="flex gap-2">
                            <input v-model="auto.holiday_input" type="date"
                                class="input input-bordered input-sm flex-1 font-mono"
                                @keyup.enter="addHoliday" />
                            <button type="button" @click="addHoliday" class="btn btn-outline btn-sm">Add</button>
                        </div>
                        <div v-if="auto.holidays.length" class="flex flex-wrap gap-1.5 mt-2">
                            <span v-for="d in auto.holidays" :key="d"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-rose-500/15 text-rose-700 dark:text-rose-300 text-[11px] font-semibold">
                                {{ d }}
                                <button type="button" @click="removeHoliday(d)" class="hover:text-rose-900">
                                    <XMarkIcon class="w-3 h-3" />
                                </button>
                            </span>
                        </div>
                    </div>

                    <!-- Overwrite -->
                    <label class="flex items-start gap-2.5 p-3 rounded-xl border-2 transition-colors cursor-pointer"
                        :class="auto.overwrite_existing ? 'border-rose-500 bg-rose-500/5' : 'border-base-200 hover:bg-base-200/40'">
                        <input type="checkbox" v-model="auto.overwrite_existing"
                            class="checkbox checkbox-error checkbox-sm mt-0.5" />
                        <div class="text-xs">
                            <p class="font-bold">Overwrite existing schedule rows</p>
                            <p class="text-base-content/55 mt-0.5">
                                When OFF, auto-gen only fills papers that don't have a date yet — hand-set rows stay.
                                When ON, every row is rebuilt from scratch.
                            </p>
                        </div>
                    </label>
                </div>

                <footer class="px-5 py-4 border-t border-base-300 flex items-center justify-end gap-2">
                    <button type="button" @click="autoOpen = false" class="btn btn-ghost btn-sm">Cancel</button>
                    <button type="button" @click="submitAuto" :disabled="autoSubmitting"
                        class="btn btn-accent btn-sm gap-1.5">
                        <BoltIcon class="w-4 h-4" />
                        {{ autoSubmitting ? 'Generating…' : 'Auto-generate' }}
                    </button>
                </footer>
            </div>
        </div>
    </AppLayout>
</template>
