<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
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
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: Object,
    rows: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
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
                    <select v-model="classFilter" class="select select-bordered select-sm w-48">
                        <option value="">All Classes</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
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
    </AppLayout>
</template>
