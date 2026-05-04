<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed, watch, onBeforeUnmount, nextTick } from 'vue'
import {
    ArrowLeftIcon, DocumentCheckIcon, CheckCircleIcon, XCircleIcon,
    ExclamationTriangleIcon, UserGroupIcon, CloudIcon, BoltIcon,
    InformationCircleIcon, KeyIcon, ClipboardDocumentIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: Object,
    subject: Object,
    section: Object,
    schoolClass: Object,
    students: Array,
    existingMarks: Object,
    examSubject: Object,
    isSubmitted: Boolean,
})

// Coerce "30.00" → 30 (DB returns decimals as strings); leaves 33.5 as 33.5.
const totalMarks   = Number(props.examSubject?.total_marks ?? 100)
const passingMarks = Number(props.examSubject?.passing_marks ?? Math.floor(totalMarks * 0.33))

// =============== State ===============
const rows = ref(props.students.map(s => {
    const existing = props.existingMarks?.[s.id]
    return {
        student_id: s.id,
        roll_no: s.roll_no,
        name: s.name,
        father_name: s.father_name,
        marks_obtained: existing?.marks_obtained != null ? String(existing.marks_obtained) : '',
        is_absent: !!existing?.is_absent,
        remarks: existing?.remarks ?? '',
        // dirty flag — true when user has changed it since last save
        dirty: false,
        // persisted snapshot to compare against
        _snap: {
            marks_obtained: existing?.marks_obtained != null ? String(existing.marks_obtained) : '',
            is_absent: !!existing?.is_absent,
            remarks: existing?.remarks ?? '',
        },
    }
}))

const showShortcuts = ref(false)
const showReviewModal = ref(false)
const submitProcessing = ref(false)
const lastSavedAt = ref(null)
const saveStatus = ref('idle') // idle | saving | saved | error
const saveError = ref(null)
const lastSavedAgo = ref('')
let savedAgoTimer = null
let autosaveTimer = null

/** Quick +/- adjustment used by the mobile card view's stepper buttons. */
function bumpMarks(row, delta) {
    if (row.is_absent || props.isSubmitted) return
    const current = parseMarks(row.marks_obtained) ?? 0
    let next = Math.round((current + delta) * 10) / 10
    if (next < 0) next = 0
    if (next > totalMarks) next = totalMarks
    row.marks_obtained = String(next)
    markDirty(row)
}

// =============== Number parsing (handles 20.5, 20,5, "20.5 marks", etc.) ===============
function parseMarks(s) {
    if (s == null || s === '') return null
    const cleaned = String(s).trim().replace(',', '.').replace(/[^\d.\-]/g, '')
    if (cleaned === '' || cleaned === '.' || cleaned === '-') return null
    const n = Number(cleaned)
    return Number.isFinite(n) ? n : null
}

// =============== Validation ===============
function rowError(row) {
    if (props.isSubmitted) return null
    if (row.is_absent) return null
    if (row.marks_obtained === '' || row.marks_obtained == null) return null
    // Reject obvious invalid characters early (letters, multiple dots, etc.)
    if (!/^-?\d*\.?\d*$/.test(String(row.marks_obtained).trim().replace(',', '.'))) return 'Not a number'
    const v = parseMarks(row.marks_obtained)
    if (v === null) return 'Not a number'
    if (v < 0) return 'Negative'
    if (v > totalMarks) return `> ${totalMarks}`
    return null
}
const rowErrors = computed(() => rows.value.map(rowError))
const hasErrors = computed(() => rowErrors.value.some(e => e !== null))

// =============== Stats ===============
const stats = computed(() => {
    let entered = 0, absent = 0, passed = 0, failed = 0
    let scoreSum = 0, scoreCount = 0
    rows.value.forEach(r => {
        if (r.is_absent) { absent++; entered++; return }
        if (r.marks_obtained === '' || r.marks_obtained == null) return
        const v = parseMarks(r.marks_obtained)
        if (v === null) return
        entered++
        scoreSum += v
        scoreCount++
        if (v >= passingMarks) passed++
        else failed++
    })
    return {
        total: rows.value.length,
        entered,
        remaining: rows.value.length - entered,
        absent,
        passed,
        failed,
        avg: scoreCount ? (scoreSum / scoreCount).toFixed(2) : null,
        progressPct: rows.value.length ? Math.round(entered / rows.value.length * 100) : 0,
    }
})

const canSubmit = computed(() =>
    !props.isSubmitted &&
    stats.value.entered === stats.value.total &&
    !hasErrors.value
)

// =============== Autosave ===============
function markDirty(row) {
    row.dirty = (
        row.marks_obtained !== row._snap.marks_obtained ||
        row.is_absent !== row._snap.is_absent ||
        row.remarks !== row._snap.remarks
    )
    scheduleAutosave()
}

function scheduleAutosave() {
    if (props.isSubmitted) return
    clearTimeout(autosaveTimer)
    autosaveTimer = setTimeout(autosave, 2500)
}

async function autosave() {
    if (props.isSubmitted) return

    const dirtyRows = rows.value.filter(r => r.dirty && !rowError(r))
    if (dirtyRows.length === 0) return

    saveStatus.value = 'saving'
    saveError.value = null

    try {
        const res = await window.axios.post(route('marks.autosave', props.exam.id), {
            subject_id: props.subject.id,
            section_id: props.section.id,
            marks: dirtyRows.map(r => ({
                student_id: r.student_id,
                marks_obtained: r.is_absent ? null : parseMarks(r.marks_obtained),
                is_absent: r.is_absent,
                remarks: r.remarks || null,
            })),
        })

        // Mark as snapped
        dirtyRows.forEach(r => {
            r._snap = {
                marks_obtained: r.marks_obtained,
                is_absent: r.is_absent,
                remarks: r.remarks,
            }
            r.dirty = false
        })

        lastSavedAt.value = new Date(res.data?.saved_at || Date.now())
        saveStatus.value = 'saved'
        updateSavedAgoLabel()
    } catch (e) {
        saveStatus.value = 'error'
        saveError.value = e.response?.data?.error || 'Could not autosave. Try again.'
    }
}

function updateSavedAgoLabel() {
    if (!lastSavedAt.value) { lastSavedAgo.value = ''; return }
    const seconds = Math.max(0, Math.floor((Date.now() - lastSavedAt.value.getTime()) / 1000))
    if (seconds < 5) lastSavedAgo.value = 'just now'
    else if (seconds < 60) lastSavedAgo.value = `${seconds}s ago`
    else if (seconds < 3600) lastSavedAgo.value = `${Math.floor(seconds / 60)}m ago`
    else lastSavedAgo.value = lastSavedAt.value.toLocaleTimeString()
}

savedAgoTimer = setInterval(updateSavedAgoLabel, 5000)

onBeforeUnmount(() => {
    clearInterval(savedAgoTimer)
    clearTimeout(autosaveTimer)
})

// =============== Keyboard Navigation ===============
function focusCell(rowIdx, col = 'marks') {
    nextTick(() => {
        const el = document.querySelector(`[data-cell="${col}-${rowIdx}"]`)
        if (el) { el.focus(); if (el.select) el.select() }
    })
}
function onMarksKeydown(e, idx) {
    if (e.key === 'Enter' || e.key === 'ArrowDown') {
        e.preventDefault()
        focusCell(Math.min(idx + 1, rows.value.length - 1))
    } else if (e.key === 'ArrowUp') {
        e.preventDefault()
        focusCell(Math.max(idx - 1, 0))
    }
}

// =============== Paste from Excel ===============
function onMarksPaste(e, startIdx) {
    const text = (e.clipboardData || window.clipboardData)?.getData('text')
    if (!text) return
    // Detect a multi-line paste (column from Excel)
    const lines = text.split(/\r?\n/).map(s => s.trim()).filter((s, i, arr) => i < arr.length - 1 || s !== '')
    if (lines.length <= 1) return // single value, let browser handle
    e.preventDefault()
    let applied = 0
    for (let i = 0; i < lines.length; i++) {
        const r = rows.value[startIdx + i]
        if (!r) break
        const val = lines[i]
        if (val.toLowerCase() === 'ab' || val.toLowerCase() === 'absent') {
            r.is_absent = true
            r.marks_obtained = ''
        } else {
            // Preserve decimals exactly as written (20.5 stays 20.5, not 20.50000001)
            const n = parseMarks(val)
            if (n !== null) {
                r.is_absent = false
                r.marks_obtained = String(n)
            }
        }
        markDirty(r)
        applied++
    }
    autosave()
}

function toggleAbsent(row) {
    if (row.is_absent) {
        row.marks_obtained = ''
        row.remarks = ''
    }
    markDirty(row)
}

function clearRow(row) {
    row.marks_obtained = ''
    row.is_absent = false
    row.remarks = ''
    markDirty(row)
}

function rowPercentage(row) {
    if (row.is_absent) return null
    if (row.marks_obtained === '' || row.marks_obtained == null) return null
    const v = parseMarks(row.marks_obtained)
    if (v === null) return null
    const pct = (v / totalMarks) * 100
    // Show 1 decimal only when needed (so 78 stays "78%", but 78.5 shows "78.5%")
    return Number.isInteger(pct) ? pct : Math.round(pct * 10) / 10
}

// =============== Submit ===============
async function openReview() {
    // Force any pending edits to save first
    clearTimeout(autosaveTimer)
    await autosave()
    showReviewModal.value = true
}

function submitMarks() {
    submitProcessing.value = true
    router.post(route('marks.submit', [props.exam.id, props.subject.id, props.section.id]), {}, {
        onSuccess: () => { showReviewModal.value = false },
        onFinish: () => { submitProcessing.value = false },
    })
}

// =============== Below-passing students preview (for review modal) ===============
const failedStudents = computed(() =>
    rows.value
        .filter(r => {
            if (r.is_absent || r.marks_obtained === '' || r.marks_obtained == null) return false
            const v = parseMarks(r.marks_obtained)
            return v !== null && v < passingMarks
        })
        .map(r => ({ name: r.name, marks: r.marks_obtained }))
)
const absentStudents = computed(() =>
    rows.value.filter(r => r.is_absent).map(r => r.name)
)
</script>

<template>
    <Head :title="`Marks · ${subject?.name} · ${schoolClass?.name} ${section?.name}`" />
    <AppLayout :breadcrumbs="[
        { label: 'Marks Entry', href: route('marks.index') },
        { label: `${subject?.name} · ${schoolClass?.name} ${section?.name}` }
    ]">
        <div class="space-y-3 sm:space-y-4 max-w-[1500px] mx-auto">
            <!-- ═══════════ HEADER ═══════════ -->
            <div class="rounded-2xl border border-base-200 bg-base-100 p-3.5 sm:p-5">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 sm:gap-4">
                    <div class="min-w-0">
                        <div class="text-[10px] sm:text-[11px] uppercase tracking-wider font-semibold text-base-content/55 mb-0.5 truncate">
                            Marks Entry · {{ exam?.name }}
                        </div>
                        <h1 class="text-lg sm:text-2xl font-extrabold tracking-tight truncate">
                            {{ subject?.name }}
                            <span class="text-base-content/30 mx-1">·</span>
                            <span class="text-primary">{{ schoolClass?.name }} {{ section?.name }}</span>
                        </h1>
                    </div>
                    <div class="flex items-stretch gap-2">
                        <div class="flex-1 lg:flex-none px-2.5 lg:px-3 py-1.5 lg:py-2 rounded-xl bg-primary/10 text-center lg:min-w-[64px]">
                            <div class="text-base sm:text-lg font-extrabold text-primary tabular-nums leading-tight">{{ totalMarks }}</div>
                            <div class="text-[9px] uppercase tracking-widest text-base-content/55 font-semibold">Total</div>
                        </div>
                        <div class="flex-1 lg:flex-none px-2.5 lg:px-3 py-1.5 lg:py-2 rounded-xl bg-amber-500/15 text-center lg:min-w-[64px]">
                            <div class="text-base sm:text-lg font-extrabold text-amber-700 dark:text-amber-300 tabular-nums leading-tight">{{ passingMarks }}</div>
                            <div class="text-[9px] uppercase tracking-widest text-base-content/55 font-semibold">Pass</div>
                        </div>
                        <div class="flex-1 lg:flex-none px-2.5 lg:px-3 py-1.5 lg:py-2 rounded-xl bg-base-200 text-center lg:min-w-[64px]">
                            <div class="text-base sm:text-lg font-extrabold tabular-nums leading-tight">{{ stats.total }}</div>
                            <div class="text-[9px] uppercase tracking-widest text-base-content/55 font-semibold">Students</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════ SUBMITTED BANNER ═══════════ -->
            <div v-if="isSubmitted" class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center flex-shrink-0">
                    <CheckCircleIcon class="w-5 h-5" />
                </div>
                <div>
                    <div class="font-bold text-emerald-900 dark:text-emerald-100">Marks Submitted</div>
                    <div class="text-xs text-emerald-800/75 dark:text-emerald-200/75">These marks are locked and can no longer be edited.</div>
                </div>
            </div>

            <!-- ═══════════ STATUS STRIP ═══════════ -->
            <div v-if="!isSubmitted" class="rounded-2xl border border-base-200 bg-base-100 p-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2.5">
                            <div class="relative w-10 h-10">
                                <svg viewBox="0 0 36 36" class="w-10 h-10 -rotate-90">
                                    <circle cx="18" cy="18" r="14" fill="none" stroke="currentColor" stroke-width="3" class="text-base-200" />
                                    <circle cx="18" cy="18" r="14" fill="none" stroke="currentColor" stroke-width="3"
                                        :stroke-dasharray="`${stats.progressPct * 0.88} 88`" class="text-primary transition-all duration-300" stroke-linecap="round" />
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center text-[10px] font-bold tabular-nums text-primary">{{ stats.progressPct }}%</div>
                            </div>
                            <div>
                                <div class="text-sm font-bold tabular-nums">{{ stats.entered }} / {{ stats.total }}</div>
                                <div class="text-[11px] text-base-content/55">
                                    <template v-if="stats.remaining">{{ stats.remaining }} remaining</template>
                                    <template v-else>All entered</template>
                                </div>
                            </div>
                        </div>

                        <div v-if="stats.avg" class="hidden md:block pl-4 border-l border-base-200">
                            <div class="text-sm font-bold tabular-nums">Avg {{ stats.avg }}</div>
                            <div class="text-[11px] text-base-content/55">
                                {{ stats.passed }} pass · {{ stats.failed }} fail · {{ stats.absent }} absent
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <!-- Save indicator -->
                        <div v-if="saveStatus === 'saving'" class="text-xs text-base-content/55 flex items-center gap-1.5">
                            <span class="loading loading-spinner loading-xs"></span>
                            Saving...
                        </div>
                        <div v-else-if="saveStatus === 'saved'" class="text-xs text-emerald-700 flex items-center gap-1.5 font-medium">
                            <CheckCircleIcon class="w-3.5 h-3.5" />
                            Draft saved {{ lastSavedAgo }}
                        </div>
                        <div v-else-if="saveStatus === 'error'" class="text-xs text-rose-700 flex items-center gap-1.5 font-medium" :title="saveError">
                            <ExclamationTriangleIcon class="w-3.5 h-3.5" />
                            {{ saveError || 'Save failed' }} <button @click="autosave" class="underline">Retry</button>
                        </div>
                        <div v-else class="text-xs text-base-content/45 flex items-center gap-1.5">
                            <CloudIcon class="w-3.5 h-3.5" />
                            Autosave on
                        </div>

                        <button @click="showShortcuts = !showShortcuts"
                            class="hidden sm:inline-flex btn btn-ghost btn-xs rounded-lg gap-1">
                            <KeyIcon class="w-3.5 h-3.5" /> Shortcuts
                        </button>
                    </div>
                </div>

                <!-- Shortcuts popout -->
                <div v-if="showShortcuts" class="mt-3 pt-3 border-t border-base-200 grid grid-cols-2 md:grid-cols-4 gap-2 text-[11px]">
                    <div class="flex items-center gap-1.5"><kbd class="kbd kbd-xs">Tab</kbd><span class="text-base-content/65">Next student</span></div>
                    <div class="flex items-center gap-1.5"><kbd class="kbd kbd-xs">Enter</kbd><span class="text-base-content/65">Next student</span></div>
                    <div class="flex items-center gap-1.5"><kbd class="kbd kbd-xs">↑</kbd><kbd class="kbd kbd-xs">↓</kbd><span class="text-base-content/65">Move up/down</span></div>
                    <div class="flex items-center gap-1.5"><kbd class="kbd kbd-xs">Ctrl+V</kbd><span class="text-base-content/65">Paste column</span></div>
                </div>
            </div>

            <!-- ═══════════ HELP HINT (desktop only — keyboard-focused tips) ═══════════ -->
            <div v-if="!isSubmitted" class="hidden sm:flex rounded-xl bg-sky-500/10 border border-sky-500/30 p-3 items-start gap-2.5 text-xs text-sky-900 dark:text-sky-100">
                <InformationCircleIcon class="w-4 h-4 text-sky-600 flex-shrink-0 mt-0.5" />
                <div>
                    <span class="font-semibold">Tip:</span>
                    Click any marks cell, then press <kbd class="kbd kbd-xs">Tab</kbd> to jump to the next student. Type <b>"ab"</b> or check the absent box to mark a student absent.
                    To paste from Excel, copy a column of marks then click the first cell and press <kbd class="kbd kbd-xs">Ctrl+V</kbd>.
                </div>
            </div>

            <!-- ═══════════ MOBILE CARD VIEW (xs–sm) ═══════════
                 Touch-friendly per-student cards. Replaces the table on phones.
                 Layout: 2 rows. Top row = identity + status. Bottom row = stepper input full-width.
                 Percentage + absent shown as inline pills below.
            -->
            <div class="sm:hidden space-y-2.5 max-w-full">
                <div v-for="(row, idx) in rows" :key="`m-${row.student_id}`"
                     class="rounded-2xl border bg-base-100 overflow-hidden transition-colors"
                     :class="[
                         row.is_absent ? 'border-amber-300/70'
                       : rowErrors[idx] ? 'border-rose-400'
                       : (row.marks_obtained !== '' && row.marks_obtained != null)
                            ? ((parseMarks(row.marks_obtained) ?? -1) >= passingMarks
                                ? 'border-emerald-300/70'
                                : 'border-rose-300/70')
                       : 'border-base-200',
                     ]">
                    <!-- Identity row -->
                    <div class="flex items-center gap-2.5 px-3 pt-3 pb-2 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-base-200 flex items-center justify-center text-[11px] font-bold text-base-content/70 shrink-0 tabular-nums">
                            {{ idx + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-[15px] leading-tight truncate">{{ row.name }}</div>
                            <div class="text-[11px] text-base-content/55 mt-0.5 truncate">
                                <span class="font-mono font-semibold">#{{ row.roll_no || '—' }}</span>
                                <span v-if="row.father_name"> · {{ row.father_name }}</span>
                            </div>
                        </div>
                        <!-- Pass / Fail / Absent / Error pill -->
                        <span v-if="row.is_absent"
                              class="px-2 py-1 rounded-md bg-amber-500/20 text-amber-800 dark:text-amber-200 text-[10px] font-bold uppercase tracking-wider shrink-0">Absent</span>
                        <ExclamationTriangleIcon v-else-if="rowErrors[idx]" class="w-5 h-5 text-rose-600 shrink-0" />
                        <CheckCircleIcon v-else-if="row.marks_obtained !== '' && row.marks_obtained != null && (parseMarks(row.marks_obtained) ?? -1) >= passingMarks"
                            class="w-5 h-5 text-emerald-600 shrink-0" />
                        <XCircleIcon v-else-if="row.marks_obtained !== '' && row.marks_obtained != null"
                            class="w-5 h-5 text-rose-500 shrink-0" />
                    </div>

                    <!-- Stepper row — full width, equal-flex children -->
                    <div class="px-3 pb-3 flex items-stretch gap-2 min-w-0">
                        <button type="button"
                            @click="bumpMarks(row, -1)"
                            :disabled="row.is_absent || isSubmitted"
                            class="w-11 h-12 shrink-0 rounded-xl bg-base-200 text-xl font-bold text-base-content/75 active:scale-95 transition-transform disabled:opacity-30 disabled:active:scale-100"
                            aria-label="Decrease">−</button>

                        <input
                            v-model="row.marks_obtained"
                            @input="markDirty(row)"
                            :disabled="row.is_absent || isSubmitted"
                            type="text"
                            inputmode="decimal"
                            :placeholder="row.is_absent ? 'AB' : 'Marks'"
                            class="input input-bordered flex-1 min-w-0 h-12 text-center text-xl font-bold tabular-nums px-1"
                            :class="{ 'input-error': rowErrors[idx], 'opacity-40 placeholder-amber-700': row.is_absent }"
                        />

                        <button type="button"
                            @click="bumpMarks(row, 1)"
                            :disabled="row.is_absent || isSubmitted"
                            class="w-11 h-12 shrink-0 rounded-xl bg-base-200 text-xl font-bold text-base-content/75 active:scale-95 transition-transform disabled:opacity-30 disabled:active:scale-100"
                            aria-label="Increase">+</button>
                    </div>

                    <!-- Inline metrics row -->
                    <div class="px-3 pb-3 flex items-center gap-2 text-[11px] min-w-0">
                        <span class="text-base-content/55 shrink-0">out of <span class="font-bold tabular-nums text-base-content/85">{{ totalMarks }}</span></span>
                        <span v-if="rowPercentage(row) !== null"
                              class="px-2 py-0.5 rounded-md font-bold tabular-nums shrink-0"
                              :class="rowPercentage(row) >= (passingMarks / totalMarks * 100)
                                  ? 'bg-emerald-500/20 text-emerald-800 dark:text-emerald-200'
                                  : 'bg-rose-500/20 text-rose-700 dark:text-rose-300'">
                            {{ rowPercentage(row) }}%
                        </span>
                        <div class="flex-1"></div>
                        <label class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg cursor-pointer active:bg-base-200 shrink-0"
                               :class="row.is_absent ? 'bg-amber-500/15 text-amber-800 dark:text-amber-200' : 'text-base-content/70'">
                            <input type="checkbox" v-model="row.is_absent" @change="toggleAbsent(row)" :disabled="isSubmitted"
                                   class="checkbox checkbox-warning checkbox-xs" />
                            <span class="text-[10px] font-bold uppercase tracking-wider">Absent</span>
                        </label>
                    </div>

                    <div v-if="rowErrors[idx]" class="mx-3 mb-3 px-2.5 py-1.5 rounded-lg bg-rose-500/15 text-rose-700 dark:text-rose-300 text-[11px] font-medium">
                        {{ rowErrors[idx] }} — enter 0 to {{ totalMarks }}
                    </div>
                </div>

                <div v-if="!rows.length" class="rounded-2xl border border-base-200 px-4 py-12 text-center text-sm text-base-content/55">
                    <UserGroupIcon class="w-10 h-10 text-base-content/30 mx-auto mb-2" />
                    No students in this section.
                </div>
            </div>

            <!-- ═══════════ DESKTOP TABLE (sm+) ═══════════ -->
            <div class="hidden sm:block rounded-2xl border border-base-200 bg-base-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-base-200/50 text-[11px] uppercase tracking-wider text-base-content/55 sticky top-0">
                            <tr>
                                <th class="text-left px-3 py-3 font-bold w-10 hidden sm:table-cell">#</th>
                                <th class="text-left px-3 py-3 font-bold w-16">Roll</th>
                                <th class="text-left px-3 py-3 font-bold">Student</th>
                                <th class="text-center px-3 py-3 font-bold w-28 sm:w-32">Marks <span class="text-base-content/40 normal-case font-normal hidden sm:inline">/ {{ totalMarks }}</span></th>
                                <th class="text-center px-3 py-3 font-bold w-14 hidden sm:table-cell">%</th>
                                <th class="text-center px-3 py-3 font-bold w-14 sm:w-20">AB</th>
                                <th class="text-center px-3 py-3 font-bold w-12 hidden md:table-cell">Status</th>
                                <th class="text-left px-3 py-3 font-bold w-48 hidden lg:table-cell">Remarks</th>
                                <th class="text-right px-2 py-3 font-bold w-10 hidden md:table-cell"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-200">
                            <tr v-for="(row, idx) in rows" :key="row.student_id"
                                class="transition-colors"
                                :class="{
                                    'bg-base-200/30': row.is_absent,
                                    'bg-rose-500/10': rowErrors[idx],
                                    'hover:bg-base-200/40': !row.is_absent && !rowErrors[idx],
                                }">
                                <td class="px-3 py-2.5 text-base-content/40 text-xs tabular-nums hidden sm:table-cell">{{ idx + 1 }}</td>
                                <td class="px-3 py-2.5 font-mono text-xs font-semibold text-base-content/65">{{ row.roll_no || '—' }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="font-medium leading-tight text-sm sm:text-[14px]">{{ row.name }}</div>
                                    <div class="text-[10.5px] text-base-content/50 hidden sm:block">{{ row.father_name }}</div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <input
                                        :data-cell="`marks-${idx}`"
                                        v-model="row.marks_obtained"
                                        @input="markDirty(row)"
                                        @keydown="onMarksKeydown($event, idx)"
                                        @paste="onMarksPaste($event, idx)"
                                        :disabled="row.is_absent || isSubmitted"
                                        type="text"
                                        inputmode="decimal"
                                        :placeholder="row.is_absent ? 'AB' : '—'"
                                        class="input input-sm w-full text-center font-mono font-bold tabular-nums focus:bg-primary/5"
                                        :class="{
                                            'input-bordered': !rowErrors[idx],
                                            'input-error': rowErrors[idx],
                                            'opacity-40': row.is_absent,
                                        }"
                                    />
                                    <div v-if="rowErrors[idx]" class="text-[10px] text-rose-600 font-medium text-center mt-0.5">{{ rowErrors[idx] }}</div>
                                </td>
                                <td class="px-3 py-2.5 text-center hidden sm:table-cell">
                                    <span v-if="rowPercentage(row) !== null"
                                        class="text-xs font-bold tabular-nums"
                                        :class="rowPercentage(row) >= (passingMarks / totalMarks * 100) ? 'text-emerald-700' : 'text-rose-600'">
                                        {{ rowPercentage(row) }}%
                                    </span>
                                    <span v-else class="text-base-content/30 text-xs">—</span>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <input
                                        type="checkbox"
                                        v-model="row.is_absent"
                                        @change="toggleAbsent(row)"
                                        :disabled="isSubmitted"
                                        class="checkbox checkbox-sm" />
                                </td>
                                <td class="px-3 py-2.5 text-center hidden md:table-cell">
                                    <template v-if="row.is_absent">
                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-md bg-amber-100 text-amber-700 text-[10px] font-bold uppercase tracking-wider">AB</span>
                                    </template>
                                    <template v-else-if="row.marks_obtained === '' || row.marks_obtained == null">
                                        <span class="text-base-content/30 text-xs">—</span>
                                    </template>
                                    <template v-else-if="rowErrors[idx]">
                                        <ExclamationTriangleIcon class="w-4 h-4 text-rose-600 mx-auto" />
                                    </template>
                                    <template v-else-if="(parseMarks(row.marks_obtained) ?? -1) >= passingMarks">
                                        <CheckCircleIcon class="w-4 h-4 text-emerald-600 mx-auto" />
                                    </template>
                                    <template v-else>
                                        <XCircleIcon class="w-4 h-4 text-rose-500 mx-auto" />
                                    </template>
                                </td>
                                <td class="px-3 py-2.5 hidden lg:table-cell">
                                    <input
                                        v-model="row.remarks"
                                        @input="markDirty(row)"
                                        :disabled="row.is_absent || isSubmitted"
                                        type="text"
                                        placeholder="—"
                                        class="input input-bordered input-sm w-full text-xs" />
                                </td>
                                <td class="px-2 py-2.5 text-right hidden md:table-cell">
                                    <button v-if="!isSubmitted && (row.marks_obtained || row.is_absent)" @click="clearRow(row)"
                                        class="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-rose-600" title="Clear row">
                                        <XCircleIcon class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="9" class="px-4 py-12 text-center text-sm text-base-content/55">
                                    <UserGroupIcon class="w-10 h-10 text-base-content/30 mx-auto mb-2" />
                                    No students in this section.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ═══════════ STICKY ACTION BAR (mobile + desktop variants) ═══════════ -->

            <!-- MOBILE: compact dock that floats above the bottom nav -->
            <div v-if="!isSubmitted && rows.length"
                class="sm:hidden sticky z-10 -mx-1 rounded-2xl border border-base-200 bg-base-100/95 backdrop-blur-xl shadow-xl p-2.5"
                style="bottom: calc(76px + env(safe-area-inset-bottom));">
                <!-- Progress -->
                <div class="flex items-center gap-2 mb-2 px-1">
                    <div class="flex-1 h-1.5 bg-base-200 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-full transition-all duration-300"
                             :style="`width: ${stats.total ? (stats.entered / stats.total * 100) : 0}%`"></div>
                    </div>
                    <span class="text-[10px] font-bold text-base-content/60 tabular-nums shrink-0">
                        {{ stats.entered }}/{{ stats.total }}
                    </span>
                </div>
                <!-- Action row: icon-button save + big primary submit -->
                <div class="flex items-stretch gap-2">
                    <button @click="autosave"
                        class="shrink-0 w-12 h-11 rounded-xl bg-base-200 active:scale-95 transition-transform flex items-center justify-center"
                        aria-label="Save draft">
                        <CloudIcon class="w-5 h-5 text-base-content/65" />
                    </button>
                    <button @click="openReview"
                        :disabled="!canSubmit"
                        class="flex-1 h-11 rounded-xl font-bold text-sm gap-2 inline-flex items-center justify-center text-white transition-all active:scale-[0.98]"
                        :class="canSubmit ? 'bg-gradient-to-br from-primary to-primary/80 shadow-md shadow-primary/30' : 'bg-base-300 text-base-content/40 cursor-not-allowed'">
                        <DocumentCheckIcon class="w-4 h-4" />
                        <span>{{ hasErrors ? 'Fix Errors' : (stats.remaining ? 'Submit' : 'Submit All') }}</span>
                    </button>
                </div>
                <!-- Inline error hint -->
                <div v-if="hasErrors" class="mt-2 px-2 py-1 rounded-md bg-rose-500/10 text-rose-700 dark:text-rose-300 text-[10.5px] font-medium flex items-center gap-1.5">
                    <ExclamationTriangleIcon class="w-3.5 h-3.5 shrink-0" />
                    Fix highlighted rows before submitting
                </div>
            </div>

            <!-- DESKTOP: original wider layout -->
            <div v-if="!isSubmitted && rows.length"
                class="hidden sm:block sticky bottom-4 rounded-2xl border border-base-200 bg-base-100/95 backdrop-blur-xl shadow-xl p-4 z-10">
                <div class="flex items-center justify-between gap-3">
                    <Link :href="route('marks.index')" class="btn btn-ghost btn-sm gap-1.5 rounded-xl">
                        <ArrowLeftIcon class="w-4 h-4" /> Back to Marks Entry
                    </Link>
                    <div class="flex items-center gap-3">
                        <div v-if="hasErrors" class="text-xs text-rose-600 font-medium flex items-center gap-1.5">
                            <ExclamationTriangleIcon class="w-4 h-4" /> Fix errors first
                        </div>
                        <button @click="autosave" class="btn btn-outline btn-sm rounded-xl gap-1.5">
                            <CloudIcon class="w-4 h-4" /> Save Draft
                        </button>
                        <button @click="openReview" :disabled="!canSubmit" class="btn btn-primary btn-sm rounded-xl gap-1.5">
                            <DocumentCheckIcon class="w-4 h-4" /> Review &amp; Submit
                        </button>
                    </div>
                </div>
            </div>

            <Link v-if="isSubmitted" :href="route('marks.index')" class="btn btn-ghost btn-sm gap-1.5 rounded-xl">
                <ArrowLeftIcon class="w-4 h-4" /> Back to Marks Entry
            </Link>
        </div>

        <!-- ═══════════ REVIEW & SUBMIT MODAL ═══════════ -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0" enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="showReviewModal" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4"
                @click.self="!submitProcessing && (showReviewModal = false)">
                <div class="bg-base-100 rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-hidden flex flex-col">
                    <div class="px-6 py-5 border-b border-base-200">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                                <DocumentCheckIcon class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="text-lg font-extrabold tracking-tight">Review &amp; Submit</h3>
                                <p class="text-xs text-base-content/60 mt-0.5">{{ subject?.name }} · {{ schoolClass?.name }} {{ section?.name }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-5 overflow-y-auto flex-1 space-y-5">
                        <!-- Summary tiles -->
                        <div class="grid grid-cols-4 gap-2.5">
                            <div class="rounded-xl bg-base-200 p-3 text-center">
                                <div class="text-xl font-extrabold tabular-nums">{{ stats.total }}</div>
                                <div class="text-[10px] uppercase tracking-wider text-base-content/55 font-semibold">Total</div>
                            </div>
                            <div class="rounded-xl bg-emerald-100 p-3 text-center">
                                <div class="text-xl font-extrabold text-emerald-700 tabular-nums">{{ stats.passed }}</div>
                                <div class="text-[10px] uppercase tracking-wider text-emerald-700 font-semibold">Passed</div>
                            </div>
                            <div class="rounded-xl bg-rose-100 p-3 text-center">
                                <div class="text-xl font-extrabold text-rose-700 tabular-nums">{{ stats.failed }}</div>
                                <div class="text-[10px] uppercase tracking-wider text-rose-700 font-semibold">Failed</div>
                            </div>
                            <div class="rounded-xl bg-amber-100 p-3 text-center">
                                <div class="text-xl font-extrabold text-amber-700 tabular-nums">{{ stats.absent }}</div>
                                <div class="text-[10px] uppercase tracking-wider text-amber-700 font-semibold">Absent</div>
                            </div>
                        </div>

                        <div v-if="stats.avg" class="rounded-xl bg-sky-50 border border-sky-100 p-3 text-sm">
                            <span class="text-base-content/60">Class average:</span>
                            <span class="font-extrabold text-sky-700 ml-1.5 tabular-nums">{{ stats.avg }} / {{ totalMarks }}</span>
                        </div>

                        <!-- Below-passing list -->
                        <div v-if="failedStudents.length">
                            <div class="text-[11px] uppercase tracking-wider font-bold text-rose-600 mb-2 flex items-center gap-1.5">
                                <XCircleIcon class="w-3.5 h-3.5" /> Below passing ({{ failedStudents.length }})
                            </div>
                            <div class="rounded-xl bg-rose-500/10 border border-rose-500/20 p-3 max-h-32 overflow-y-auto">
                                <ul class="space-y-1 text-xs">
                                    <li v-for="(s, i) in failedStudents" :key="i" class="flex justify-between">
                                        <span>{{ s.name }}</span>
                                        <span class="font-mono font-bold">{{ s.marks }} / {{ totalMarks }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Absent list -->
                        <div v-if="absentStudents.length">
                            <div class="text-[11px] uppercase tracking-wider font-bold text-amber-700 mb-2 flex items-center gap-1.5">
                                <ExclamationTriangleIcon class="w-3.5 h-3.5" /> Marked absent ({{ absentStudents.length }})
                            </div>
                            <div class="rounded-xl bg-amber-500/10 border border-amber-500/20 p-3 max-h-32 overflow-y-auto">
                                <ul class="space-y-1 text-xs">
                                    <li v-for="(n, i) in absentStudents" :key="i">{{ n }}</li>
                                </ul>
                            </div>
                        </div>

                        <div class="rounded-xl bg-base-200/60 border border-base-300 p-3 text-xs text-base-content/70 flex items-start gap-2">
                            <InformationCircleIcon class="w-4 h-4 flex-shrink-0 mt-0.5 text-base-content/55" />
                            <span>Once submitted, marks are <b>locked</b> and can no longer be edited. Make sure everything looks right above.</span>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-base-200 flex items-center justify-end gap-2 bg-base-200/30">
                        <button @click="showReviewModal = false" :disabled="submitProcessing"
                            class="btn btn-ghost btn-sm rounded-xl">
                            Keep Editing
                        </button>
                        <button @click="submitMarks" :disabled="submitProcessing"
                            class="btn btn-primary btn-sm rounded-xl gap-1.5">
                            <BoltIcon v-if="!submitProcessing" class="w-4 h-4" />
                            <span v-else class="loading loading-spinner loading-xs"></span>
                            {{ submitProcessing ? 'Submitting...' : 'Submit Final Marks' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AppLayout>
</template>
