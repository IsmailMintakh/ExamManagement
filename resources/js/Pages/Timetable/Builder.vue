<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    PencilSquareIcon, ArrowLeftIcon, EyeIcon, CheckIcon,
    XCircleIcon, BoltIcon, CursorArrowRaysIcon, InformationCircleIcon,
    TrashIcon, Square2StackIcon, LockClosedIcon, LockOpenIcon,
    DocumentDuplicateIcon, ArrowsRightLeftIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    section: Object,
    slots: { type: Array, default: () => [] },
    entries: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    teachers: { type: Array, default: () => [] },
    copyCandidates: { type: Array, default: () => [] },
})

// ─── Lock controls ───
function lockSection() {
    if (!confirm('Lock this section\'s timetable? Edits will be blocked until unlocked.')) return
    router.post(route('timetable.section.lock', props.section.id), {}, { preserveScroll: true })
}
function unlockSection() {
    if (!confirm('Unlock to allow edits?')) return
    router.post(route('timetable.section.unlock', props.section.id), {}, { preserveScroll: true })
}

// ─── Copy from another section ───
const copyFromOpen = ref(false)
const copyFromId = ref('')
function submitCopy() {
    if (!copyFromId.value) return
    if (!confirm('Copy will replace ALL existing entries in this section. Teachers will be cleared (you reassign them after). Continue?')) return
    router.post(route('timetable.section.copy', { target: props.section.id, source: copyFromId.value }), {}, {
        preserveScroll: true,
        onSuccess: () => { copyFromOpen.value = false; copyFromId.value = '' },
    })
}

const DAYS = [
    { code: 'mon', label: 'Mon' },
    { code: 'tue', label: 'Tue' },
    { code: 'wed', label: 'Wed' },
    { code: 'thu', label: 'Thu' },
    { code: 'fri', label: 'Fri' },
    { code: 'sat', label: 'Sat' },
]

// Build the cell map: keyed by `${day}|${slotId}` for O(1) lookup.
const cellMap = ref({})

function initCells() {
    const map = {}
    for (const slot of props.slots) {
        const days = slot.weekdays || ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']
        for (const day of days) {
            const key = `${day}|${slot.id}`
            map[key] = {
                weekday: day,
                time_slot_id: slot.id,
                subject_id: '',
                teacher_id: '',
                room: '',
            }
        }
    }
    for (const e of props.entries) {
        const key = `${e.weekday}|${e.time_slot_id}`
        if (map[key]) {
            map[key].subject_id = e.subject_id || ''
            map[key].teacher_id = e.teacher_id || ''
            map[key].room = e.room || ''
        }
    }
    cellMap.value = map
}
initCells()

function isPeriodSlot(slot) { return slot.type === 'period' }

const subjectsById = computed(() => Object.fromEntries(props.subjects.map(s => [s.id, s])))
const teachersById = computed(() => Object.fromEntries(props.teachers.map(t => [t.id, t])))

function cell(day, slotId) {
    return cellMap.value[`${day}|${slotId}`]
}
function slotApplies(slot, day) {
    const days = slot.weekdays || ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']
    return days.includes(day)
}

// ─────────── QUICK-FILL MODE ───────────
// Pick a subject+teacher, then click cells to drop them in.
const quickMode = ref(true)
const qSubjectId = ref('')
const qTeacherId = ref('')

function applyQuick(day, slotId) {
    if (!qSubjectId.value && !qTeacherId.value) return
    const c = cell(day, slotId)
    if (!c) return
    if (qSubjectId.value) c.subject_id = qSubjectId.value
    if (qTeacherId.value) c.teacher_id = qTeacherId.value
}

// Fill an entire row (one slot, all applicable days) with the current pick.
function fillRow(slot) {
    if (!isPeriodSlot(slot)) return
    if (!qSubjectId.value && !qTeacherId.value) return
    const days = slot.weekdays || DAYS.map(d => d.code)
    for (const d of days) applyQuick(d, slot.id)
}

// Fill an entire column (one day, all period slots).
function fillColumn(dayCode) {
    if (!qSubjectId.value && !qTeacherId.value) return
    for (const slot of props.slots) {
        if (!isPeriodSlot(slot)) continue
        if (!slotApplies(slot, dayCode)) continue
        applyQuick(dayCode, slot.id)
    }
}

function clearCell(day, slotId) {
    const c = cell(day, slotId)
    if (c) { c.subject_id = ''; c.teacher_id = ''; c.room = '' }
}

function clearAll() {
    if (!confirm('Clear ALL cells? This wipes every subject/teacher assignment in the grid (you still need to Save to make it permanent).')) return
    for (const k in cellMap.value) {
        cellMap.value[k].subject_id = ''
        cellMap.value[k].teacher_id = ''
        cellMap.value[k].room = ''
    }
}

// ─────────── CONFLICTS ───────────
const conflicts = computed(() => {
    const seen = {}
    const conflictKeys = new Set()
    for (const key in cellMap.value) {
        const c = cellMap.value[key]
        if (!c.teacher_id) continue
        const sig = `${c.weekday}|${c.time_slot_id}|${c.teacher_id}`
        if (seen[sig]) {
            conflictKeys.add(seen[sig])
            conflictKeys.add(key)
        } else {
            seen[sig] = key
        }
    }
    return conflictKeys
})

// ─────────── SAVE ───────────
const form = useForm({ entries: [] })

function save() {
    const out = []
    for (const slot of props.slots) {
        if (!isPeriodSlot(slot)) continue
        for (const day of (slot.weekdays || DAYS.map(d => d.code))) {
            const c = cell(day, slot.id)
            if (!c) continue
            out.push({
                weekday: c.weekday,
                time_slot_id: c.time_slot_id,
                subject_id: c.subject_id || null,
                teacher_id: c.teacher_id || null,
                room: c.room || null,
            })
        }
    }
    form.entries = out
    form.post(route('timetable.builder.save', props.section.id), {
        preserveScroll: true,
    })
}

// Simple progress: how many editable period cells have a teacher.
const progress = computed(() => {
    let total = 0, filled = 0
    for (const slot of props.slots) {
        if (!isPeriodSlot(slot)) continue
        const days = slot.weekdays || DAYS.map(d => d.code)
        for (const d of days) {
            total++
            const c = cell(d, slot.id)
            if (c?.teacher_id) filled++
        }
    }
    return { total, filled, pct: total ? Math.round((filled / total) * 100) : 0 }
})

const showHelp = ref(false)
</script>

<template>
    <Head :title="`Build · ${section.class_name} ${section.name}`" />
    <AppLayout :breadcrumbs="[
        { label: 'Timetable', href: route('timetable.index') },
        { label: `${section.class_name} · ${section.name}` },
    ]">
        <div class="space-y-4 max-w-[1500px] mx-auto">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                <div>
                    <Link :href="route('timetable.index')" class="btn btn-ghost btn-sm gap-1 mb-2 -ml-2">
                        <ArrowLeftIcon class="w-4 h-4" /> Back
                    </Link>
                    <h1 class="text-2xl font-extrabold tracking-tight flex items-center gap-2">
                        <PencilSquareIcon class="w-6 h-6 text-violet-600 dark:text-violet-400" />
                        Build timetable
                    </h1>
                    <p class="text-sm text-base-content/55 mt-1">
                        <strong>{{ section.class_name }}</strong> · Section {{ section.name }} · {{ section.school_name }}
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <button v-if="copyCandidates.length && !section.timetable_locked"
                        @click="copyFromOpen = true"
                        class="btn btn-ghost btn-sm rounded-xl gap-1.5">
                        <DocumentDuplicateIcon class="w-4 h-4" /> Copy from…
                    </button>
                    <button v-if="!section.timetable_locked" @click="lockSection"
                        class="btn btn-ghost btn-sm rounded-xl gap-1.5"
                        title="Lock to prevent further edits">
                        <LockClosedIcon class="w-4 h-4" /> Lock
                    </button>
                    <button v-else @click="unlockSection"
                        class="btn btn-warning btn-sm rounded-xl gap-1.5">
                        <LockOpenIcon class="w-4 h-4" /> Unlock to edit
                    </button>
                    <Link :href="route('timetable.section', section.id)" class="btn btn-ghost btn-sm rounded-xl gap-1.5">
                        <EyeIcon class="w-4 h-4" /> View
                    </Link>
                    <button @click="save"
                        :disabled="form.processing || conflicts.size > 0 || section.timetable_locked"
                        class="btn btn-primary rounded-xl gap-2">
                        <CheckIcon class="w-4 h-4" />
                        {{ form.processing ? 'Saving…' : 'Save timetable' }}
                    </button>
                </div>
            </div>

            <!-- LOCK STATE BANNER -->
            <div v-if="section.timetable_locked"
                class="rounded-2xl border-2 border-amber-500/40 bg-amber-500/10 p-4 flex items-start gap-3">
                <LockClosedIcon class="w-5 h-5 text-amber-700 dark:text-amber-300 mt-0.5 shrink-0" />
                <div class="flex-1 text-sm">
                    <p class="font-bold text-amber-900 dark:text-amber-200">Timetable locked</p>
                    <p class="text-xs text-amber-800 dark:text-amber-300/80 mt-0.5">
                        Locked
                        <span v-if="section.timetable_locked_at">on <strong>{{ section.timetable_locked_at }}</strong></span>
                        <span v-if="section.timetable_locked_by_name"> by <strong>{{ section.timetable_locked_by_name }}</strong></span>.
                        Edits are blocked until you unlock above.
                    </p>
                </div>
            </div>

            <!-- COPY-FROM MODAL -->
            <div v-if="copyFromOpen" class="modal modal-open">
                <div class="modal-box max-w-md">
                    <div class="flex items-center gap-2 mb-2">
                        <DocumentDuplicateIcon class="w-5 h-5 text-violet-600 dark:text-violet-400" />
                        <h3 class="text-base font-bold">Copy timetable from another section</h3>
                    </div>
                    <p class="text-xs text-base-content/65 mb-4">
                        Cells (subjects, rooms) are copied. Teachers are cleared so you can reassign per cell — the same teacher in two sections at the same time would conflict.
                    </p>
                    <select v-model="copyFromId" class="select select-bordered select-sm rounded-lg w-full text-sm mb-3">
                        <option value="">— Select source section —</option>
                        <option v-for="c in copyCandidates" :key="c.id" :value="c.id">
                            {{ c.class_name }} — {{ c.name }}
                        </option>
                    </select>
                    <div class="modal-action">
                        <button @click="copyFromOpen = false" class="btn btn-ghost btn-sm">Cancel</button>
                        <button @click="submitCopy" :disabled="!copyFromId"
                            class="btn btn-primary btn-sm gap-1.5">
                            <DocumentDuplicateIcon class="w-4 h-4" /> Copy
                        </button>
                    </div>
                </div>
                <div class="modal-backdrop" @click="copyFromOpen = false"></div>
            </div>

            <!-- HOW IT WORKS HELPER -->
            <div class="rounded-2xl border-2 border-violet-500/30 bg-violet-500/5 p-4">
                <button @click="showHelp = !showHelp" class="w-full flex items-center justify-between text-left">
                    <div class="flex items-center gap-2">
                        <InformationCircleIcon class="w-5 h-5 text-violet-600 dark:text-violet-400" />
                        <span class="font-bold text-sm">How does this work?</span>
                    </div>
                    <span class="text-xs text-violet-600 dark:text-violet-400 font-bold">{{ showHelp ? 'Hide' : 'Show' }}</span>
                </button>
                <div v-if="showHelp" class="mt-3 space-y-2 text-sm text-base-content/75">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="bg-base-100 rounded-xl p-3 ring-1 ring-base-300">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="w-6 h-6 rounded-full bg-violet-500 text-white text-xs font-bold grid place-items-center">1</span>
                                <strong class="text-sm">Pick a subject + teacher</strong>
                            </div>
                            <p class="text-xs text-base-content/60">Use the green toolbar below the grid title. Choose what you want to drop into cells.</p>
                        </div>
                        <div class="bg-base-100 rounded-xl p-3 ring-1 ring-base-300">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="w-6 h-6 rounded-full bg-violet-500 text-white text-xs font-bold grid place-items-center">2</span>
                                <strong class="text-sm">Click cells to fill them</strong>
                            </div>
                            <p class="text-xs text-base-content/60">In Quick Fill mode, just click any cell to drop your selection in. Click <strong>"Fill row"</strong> to apply across all days for one period.</p>
                        </div>
                        <div class="bg-base-100 rounded-xl p-3 ring-1 ring-base-300">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="w-6 h-6 rounded-full bg-violet-500 text-white text-xs font-bold grid place-items-center">3</span>
                                <strong class="text-sm">Save when done</strong>
                            </div>
                            <p class="text-xs text-base-content/60">Hit <strong>Save timetable</strong>. The system blocks teacher conflicts (one teacher in two classes at the same time).</p>
                        </div>
                    </div>
                    <div class="text-xs text-base-content/55 pt-1">
                        <strong>Tip:</strong> Switch off Quick Fill mode if you'd rather edit each cell with dropdowns. Breaks &amp; lunch are read-only.
                    </div>
                </div>
            </div>

            <!-- ONBOARDING WHEN EMPTY -->
            <div v-if="!subjects.length || !teachers.length" class="rounded-2xl border-2 border-amber-500/30 bg-amber-500/10 p-4">
                <div class="flex items-start gap-3">
                    <XCircleIcon class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5" />
                    <div class="text-sm">
                        <p class="font-bold mb-1">You need some setup first</p>
                        <ul class="list-disc list-inside text-xs text-base-content/70 space-y-0.5">
                            <li v-if="!subjects.length">No subjects assigned to this class yet — go to <Link href="/classes" class="text-violet-600 underline">Classes</Link> and add subjects to <strong>{{ section.class_name }}</strong>.</li>
                            <li v-if="!teachers.length">No teachers in this school — go to <Link href="/users" class="text-violet-600 underline">Users</Link> and create some class-teacher / subject-teacher accounts.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- QUICK-FILL TOOLBAR -->
            <div class="rounded-2xl bg-gradient-to-r from-emerald-500/10 to-teal-500/10 border-2 border-emerald-500/30 p-4">
                <div class="flex flex-col md:flex-row md:items-center gap-3">
                    <div class="flex items-center gap-2 shrink-0">
                        <BoltIcon class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        <span class="text-sm font-bold">Quick Fill</span>
                        <label class="ml-2 text-xs flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" v-model="quickMode" class="toggle toggle-success toggle-xs" />
                            <span :class="quickMode ? 'text-emerald-700 dark:text-emerald-300 font-bold' : 'text-base-content/55'">{{ quickMode ? 'ON' : 'OFF' }}</span>
                        </label>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-2 flex-1">
                        <select v-model="qSubjectId" class="select select-bordered select-sm rounded-lg flex-1 text-sm">
                            <option value="">— Pick subject —</option>
                            <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <select v-model="qTeacherId" class="select select-bordered select-sm rounded-lg flex-1 text-sm">
                            <option value="">— Pick teacher —</option>
                            <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                        <button @click="qSubjectId = ''; qTeacherId = ''" class="btn btn-ghost btn-sm rounded-lg gap-1" title="Clear selection">
                            <TrashIcon class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <button @click="clearAll" class="btn btn-ghost btn-xs rounded-lg gap-1 text-rose-600">
                            <TrashIcon class="w-3 h-3" /> Clear all
                        </button>
                    </div>
                </div>
                <p v-if="quickMode" class="text-[11px] text-emerald-700 dark:text-emerald-300 mt-2 flex items-center gap-1">
                    <CursorArrowRaysIcon class="w-3.5 h-3.5" />
                    {{ qSubjectId || qTeacherId ? 'Now click any cell or "Fill row" / "Fill day" buttons to apply.' : 'Pick a subject and/or teacher first, then click cells.' }}
                </p>
            </div>

            <!-- PROGRESS -->
            <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3 flex items-center gap-3">
                <Square2StackIcon class="w-4 h-4 text-base-content/55" />
                <div class="flex-1">
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="font-bold">Period coverage</span>
                        <span class="text-base-content/55">{{ progress.filled }} of {{ progress.total }} cells filled — {{ progress.pct }}%</span>
                    </div>
                    <div class="h-1.5 bg-base-200 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full transition-all" :style="{ width: progress.pct + '%' }"></div>
                    </div>
                </div>
            </div>

            <!-- Conflict notice -->
            <div v-if="conflicts.size" class="rounded-2xl bg-rose-500/10 border-2 border-rose-500/30 p-4 flex items-start gap-3">
                <XCircleIcon class="w-5 h-5 text-rose-600 dark:text-rose-400 mt-0.5 shrink-0" />
                <div class="text-sm text-rose-900 dark:text-rose-200">
                    <p class="font-bold">Teacher conflict — fix before saving</p>
                    <p class="text-xs mt-0.5">A teacher is assigned to two cells in the same period. Highlighted cells (rose ring) must be fixed first.</p>
                </div>
            </div>

            <p v-if="form.errors.entries" class="text-xs text-error">{{ form.errors.entries }}</p>

            <!-- Grid -->
            <div class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-base-200/50">
                                <th class="text-left px-3 py-3 font-bold text-[10px] uppercase tracking-wider text-base-content/55 sticky left-0 bg-base-200/50 z-10 min-w-[140px]">
                                    Slot
                                </th>
                                <th v-for="d in DAYS" :key="d.code" class="text-center px-2 py-3 font-bold text-[10px] uppercase tracking-wider text-base-content/55 min-w-[160px]">
                                    <div>{{ d.label }}</div>
                                    <button v-if="quickMode && (qSubjectId || qTeacherId)"
                                        @click="fillColumn(d.code)"
                                        type="button"
                                        class="mt-1 text-[9px] font-bold text-emerald-700 dark:text-emerald-300 hover:underline normal-case tracking-normal"
                                        title="Apply to whole day">
                                        ↓ Fill day
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-300">
                            <tr v-for="slot in slots" :key="slot.id" :class="!isPeriodSlot(slot) ? 'bg-amber-500/5' : ''">
                                <td class="px-3 py-2 sticky left-0 z-10"
                                    :class="!isPeriodSlot(slot) ? 'bg-amber-500/10' : 'bg-base-100'">
                                    <p class="font-bold text-xs">{{ slot.name }}</p>
                                    <p class="text-[10px] text-base-content/55 font-mono">
                                        {{ slot.starts_at?.slice(0,5) }}–{{ slot.ends_at?.slice(0,5) }}
                                    </p>
                                    <span v-if="slot.type !== 'period'" class="text-[9px] uppercase tracking-wider font-bold text-amber-700 dark:text-amber-300">{{ slot.type }}</span>
                                    <button v-else-if="quickMode && (qSubjectId || qTeacherId)"
                                        @click="fillRow(slot)"
                                        type="button"
                                        class="block mt-1 text-[9px] font-bold text-emerald-700 dark:text-emerald-300 hover:underline"
                                        title="Apply to all days for this slot">
                                        → Fill row
                                    </button>
                                </td>
                                <td v-for="d in DAYS" :key="d.code" class="px-1.5 py-1.5 align-top">
                                    <!-- Slot doesn't run on this day -->
                                    <div v-if="!slotApplies(slot, d.code)"
                                        class="rounded-md bg-base-200/50 text-center py-3 text-[10px] text-base-content/35 italic">
                                        no class
                                    </div>
                                    <!-- Break / Lunch / Assembly -->
                                    <div v-else-if="!isPeriodSlot(slot)"
                                        class="rounded-md bg-amber-500/10 text-center py-3 text-[10px] uppercase tracking-wider font-bold text-amber-700 dark:text-amber-300">
                                        {{ slot.type }}
                                    </div>
                                    <!-- QUICK-FILL CELL: click to drop selection in -->
                                    <div v-else-if="quickMode"
                                        @click="applyQuick(d.code, slot.id)"
                                        class="rounded-lg p-2 ring-1 transition-all cursor-pointer min-h-[64px] flex flex-col justify-center"
                                        :class="conflicts.has(`${d.code}|${slot.id}`)
                                            ? 'ring-rose-500/50 bg-rose-500/10'
                                            : (cell(d.code, slot.id)?.teacher_id
                                                ? 'ring-emerald-500/40 bg-emerald-500/10 hover:bg-emerald-500/15'
                                                : 'ring-base-300 bg-base-200/30 hover:bg-emerald-500/10 hover:ring-emerald-500/30')"
                                        :title="(qSubjectId || qTeacherId) ? 'Click to apply selection' : 'Pick subject/teacher first'">
                                        <template v-if="cell(d.code, slot.id)?.subject_id || cell(d.code, slot.id)?.teacher_id">
                                            <p class="font-bold text-[11px] truncate">
                                                {{ subjectsById[cell(d.code, slot.id).subject_id]?.name || '— pick subject —' }}
                                            </p>
                                            <p class="text-[10px] text-base-content/65 truncate">
                                                {{ teachersById[cell(d.code, slot.id).teacher_id]?.name || '— pick teacher —' }}
                                            </p>
                                            <button @click.stop="clearCell(d.code, slot.id)" type="button"
                                                class="text-[9px] uppercase tracking-wider font-bold text-base-content/40 hover:text-rose-600 mt-1">
                                                clear
                                            </button>
                                        </template>
                                        <template v-else>
                                            <p class="text-[10px] text-base-content/35 text-center italic">click to fill</p>
                                        </template>
                                    </div>
                                    <!-- DROPDOWN MODE: edit per cell -->
                                    <div v-else
                                        class="rounded-lg p-1.5 space-y-1 ring-1 transition-colors"
                                        :class="conflicts.has(`${d.code}|${slot.id}`)
                                            ? 'ring-rose-500/50 bg-rose-500/10'
                                            : (cell(d.code, slot.id)?.teacher_id ? 'ring-emerald-500/30 bg-emerald-500/5' : 'ring-base-300 bg-base-200/30')">
                                        <select v-model="cell(d.code, slot.id).subject_id"
                                            class="select select-bordered select-xs w-full text-[11px] rounded-md">
                                            <option value="">— Subject —</option>
                                            <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                                        </select>
                                        <select v-model="cell(d.code, slot.id).teacher_id"
                                            class="select select-bordered select-xs w-full text-[11px] rounded-md">
                                            <option value="">— Teacher —</option>
                                            <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                                        </select>
                                        <button v-if="cell(d.code, slot.id)?.teacher_id || cell(d.code, slot.id)?.subject_id"
                                            type="button"
                                            @click="clearCell(d.code, slot.id)"
                                            class="text-[9px] uppercase tracking-wider font-bold text-base-content/45 hover:text-rose-600 transition-colors w-full">
                                            clear
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="text-xs text-base-content/55 text-center pb-4">
                Empty cells = "no class scheduled". Server validates teacher conflicts across all sections before saving.
            </p>
        </div>
    </AppLayout>
</template>
