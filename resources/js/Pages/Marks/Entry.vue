<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { queueSnapshot, getSnapshot, deleteSnapshot, drainAll } from '@/lib/offlineMarksQueue'
import {
    ArrowLeftIcon, DocumentCheckIcon, CheckCircleIcon, XCircleIcon,
    ExclamationTriangleIcon, UserGroupIcon, CloudIcon, BoltIcon,
    InformationCircleIcon, KeyIcon, ClipboardDocumentIcon,
    MagnifyingGlassIcon, ChatBubbleLeftEllipsisIcon, ChevronDownIcon,
    PencilSquareIcon, ArrowPathIcon, PaperAirplaneIcon,
    ArchiveBoxIcon, ClockIcon,
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
    // Has the admin granted post-submission edit access for this (subject,
    // section)? When true and isSubmitted is also true, an "Edit Marks"
    // button appears that flips the form into editMode.
    canEditAfterSubmit: { type: Boolean, default: false },
    // Soft-deleted Mark count for this exact (exam, subject, section).
    // When > 0, the recovery banner appears so the teacher / admin can
    // one-click restore the marks that were dropped by remove-subject /
    // remove-class. Always 0 in normal operation.
    deletedMarksCount: { type: Number, default: 0 },
    // Set on the FIRST page load after we auto-un-trashed Mark rows that
    // were dropped by an earlier remove-subject / remove-class action.
    // Drives the green "We restored N marks" ribbon so the user knows
    // their previously-submitted data came back automatically — no clicks.
    autoRestoredCount: { type: Number, default: 0 },
})

const restoringMarks = ref(false)
function restoreDeletedMarks() {
    if (!props.deletedMarksCount) return
    if (!confirm(`Restore ${props.deletedMarksCount} previously-submitted mark(s) for this subject?\n\nMarks where a newer entry already exists will be skipped — the newest entry always wins.`)) return
    restoringMarks.value = true
    router.post(
        route('marks.restore', [props.exam.id, props.subject.id, props.section.id]),
        {},
        {
            preserveScroll: true,
            onFinish: () => { restoringMarks.value = false },
        }
    )
}

// editMode: user-controlled flag — flips on when the teacher clicks
// "Edit Marks" on a submitted section that the admin has unlocked. While
// editMode is true, all the `editLocked` gates behave like fresh entry.
const editMode = ref(false)
// One gate used everywhere the form was previously checking `isSubmitted`.
// Locked when:
//   - the marks are submitted AND
//   - the user has NOT yet clicked Edit Marks
// So: fresh entry = unlocked; submitted-no-permission = locked; submitted-
// with-permission-and-edit-clicked = unlocked; admin always = unlocked
// when they hit Edit.
const editLocked = computed(() => props.isSubmitted && !editMode.value)
const hasUnsavedEdits = computed(() => editMode.value && rows.value.some(r => r.dirty))

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

// ─── Search + filter (works on both mobile cards and desktop table) ───
const search = ref('')
const filterMode = ref('all') // all | empty | errors | absent
const expandedRemarks = ref({}) // student_id → bool, controls remarks expander on mobile cards

function rowMatchesFilter(row, idx) {
    if (search.value.trim()) {
        const q = search.value.toLowerCase()
        const haystack = `${row.name} ${row.roll_no || ''} ${row.father_name || ''}`.toLowerCase()
        if (!haystack.includes(q)) return false
    }
    if (filterMode.value === 'empty') {
        return !row.is_absent && (row.marks_obtained === '' || row.marks_obtained == null)
    }
    if (filterMode.value === 'errors') return !!rowErrors.value[idx]
    if (filterMode.value === 'absent') return row.is_absent
    return true
}

const visibleRows = computed(() =>
    rows.value
        .map((row, idx) => ({ row, idx }))
        .filter(({ row, idx }) => rowMatchesFilter(row, idx))
)

const filterCounts = computed(() => ({
    all: rows.value.length,
    empty: rows.value.filter(r => !r.is_absent && (r.marks_obtained === '' || r.marks_obtained == null)).length,
    errors: Object.keys(rowErrors.value).filter(k => rowErrors.value[k]).length,
    absent: rows.value.filter(r => r.is_absent).length,
}))
const submitProcessing = ref(false)
const lastSavedAt = ref(null)
const saveStatus = ref('idle') // idle | saving | retrying | saved | error | queued
const retryAttempt = ref(0)
const MAX_RETRIES = 3
// Exponential backoff between retries — 500ms → 1.5s → 4s.
const RETRY_BACKOFF_MS = [500, 1500, 4000]
const saveError = ref(null)
const lastSavedAgo = ref('')
let savedAgoTimer = null
let autosaveTimer = null

// ─── Connectivity + offline queue ───
const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true)
const hasQueuedSnapshot = ref(false)
const lastQueuedAt = ref(null)
const syncInProgress = ref(false)

/** Quick +/- adjustment used by the mobile card view's stepper buttons. */
function bumpMarks(row, delta) {
    if (row.is_absent || editLocked.value) return
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
    if (editLocked.value) return null
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

// Count of Mark rows currently in "draft" state for this paper —
// derived directly from the payload the controller sent. Non-zero
// means the teacher has typed values (autosave persisted them) but
// hasn't clicked Submit yet, so results still show AB for those
// students. The header shows a "Submit drafts (N)" button when
// this is > 0.
const draftCount = computed(() => {
    const map = props.existingMarks || {}
    return Object.values(map).filter(m => m?.status === 'draft').length
})

// ─────── Marks Backup / Snapshot recovery ───────
// Every destructive/mutating action on Mark rows (submit, edit, force-
// submit, remove-subject, remove-class) writes a full-paper snapshot to
// the mark_snapshots table BEFORE mutating. This modal lets the teacher
// or admin browse those snapshots and roll back if marks disappear.
const showSnapshotModal = ref(false)
const snapshotsLoading = ref(false)
const snapshots = ref([])
const restoringSnapshotId = ref(null)

async function openSnapshotModal() {
    showSnapshotModal.value = true
    snapshotsLoading.value = true
    try {
        const res = await window.axios.get(
            route('marks.snapshots.list', [props.exam.id, props.subject.id, props.section.id])
        )
        snapshots.value = res.data?.snapshots || []
    } catch (e) {
        snapshots.value = []
    } finally {
        snapshotsLoading.value = false
    }
}

function restoreSnapshot(snapshotId) {
    if (restoringSnapshotId.value) return
    if (!confirm('Restore this snapshot? A backup of the current state is taken automatically first, so this action is reversible.')) return
    restoringSnapshotId.value = snapshotId
    router.post(
        route('marks.snapshots.restore', [props.exam.id, props.subject.id, props.section.id, snapshotId]),
        {},
        {
            // Only close on success — otherwise the modal would vanish
            // on 403/422 and the teacher would think "nothing happened".
            onSuccess: () => {
                showSnapshotModal.value = false
                // Full window reload so `existingMarks` re-hydrates from the
                // restored DB rows. Inertia's preserveState=true (default on
                // POST-redirect) can leave stale row.marks_obtained values
                // that are hidden by the restored ones under the hood; a
                // hard reload guarantees the grid reflects the restore.
                window.location.reload()
            },
            onError: () => {
                alert('Restore did not complete. Check that you still have access to this paper, or try again.')
            },
            onFinish: () => {
                restoringSnapshotId.value = null
            },
        }
    )
}

function triggerLabel(trigger) {
    const labels = {
        pre_store: 'Before edit save',
        pre_submit: 'Before submit',
        post_submit: 'Finalized submission ✓',
        pre_submit_drafts: 'Before submit-drafts',
        pre_remove_subject: 'Before subject removed',
        pre_remove_class: 'Before class removed',
        pre_restore: 'Before earlier restore',
        pre_admin_delete: 'Before admin delete',
        manual: 'Manual snapshot',
    }
    return labels[trigger] || trigger
}

const submittingDrafts = ref(false)
function submitDrafts() {
    if (submittingDrafts.value || !draftCount.value) return
    if (!confirm(`Submit ${draftCount.value} draft mark(s) for this paper? Results will recalculate automatically.`)) return
    submittingDrafts.value = true
    router.post(
        route('marks.submit-drafts', [props.exam.id, props.subject.id, props.section.id]),
        {},
        {
            preserveScroll: true,
            onFinish: () => { submittingDrafts.value = false },
        }
    )
}

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

/**
 * Autosave: try to POST, fall back to IndexedDB on network error.
 * The queued snapshot replaces any prior queued one (newest wins) and
 * gets drained on reconnect or page reload.
 */
async function autosave() {
    if (props.isSubmitted) return

    const dirtyRows = rows.value.filter(r => r.dirty && !rowError(r))
    if (dirtyRows.length === 0) return

    const payload = {
        subject_id: props.subject.id,
        section_id: props.section.id,
        marks: dirtyRows.map(r => ({
            student_id: r.student_id,
            marks_obtained: r.is_absent ? null : parseMarks(r.marks_obtained),
            is_absent: r.is_absent,
            remarks: r.remarks || null,
        })),
    }

    saveStatus.value = 'saving'
    saveError.value = null

    // Hard-offline shortcut — don't even attempt the request, queue immediately.
    if (!navigator.onLine) {
        await queuePayloadOffline(payload, dirtyRows)
        return
    }

    // Retry policy —
    //   4xx: user-fixable (validation, permission). Surface immediately.
    //   5xx: server hiccup (DB lock, unique-constraint race, timeout).
    //        Retry with exponential backoff — the same payload will
    //        usually succeed on the next attempt now that saves use
    //        withTrashed. After MAX_RETRIES, queue offline as durable
    //        fallback so no marks are ever lost.
    //   network (no response): treat like 5xx — retry, then queue.
    for (let attempt = 0; attempt <= MAX_RETRIES; attempt++) {
        retryAttempt.value = attempt
        if (attempt > 0) saveStatus.value = 'retrying'

        try {
            const res = await window.axios.post(route('marks.autosave', props.exam.id), payload)

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
            retryAttempt.value = 0
            updateSavedAgoLabel()

            if (hasQueuedSnapshot.value) {
                try {
                    await deleteSnapshot({
                        examId: props.exam.id,
                        subjectId: props.subject.id,
                        sectionId: props.section.id,
                    })
                    hasQueuedSnapshot.value = false
                } catch (e) { /* best-effort */ }
            }
            return
        } catch (e) {
            const status = e.response?.status
            const isValidation = status >= 400 && status < 500
            // Client-side validation / permission — retry won't help.
            if (isValidation) {
                saveStatus.value = 'error'
                saveError.value = e.response?.data?.error || 'Could not autosave. Try again.'
                retryAttempt.value = 0
                return
            }
            // Retryable — 5xx / no response. Back off and try again.
            if (attempt < MAX_RETRIES) {
                await new Promise(r => setTimeout(r, RETRY_BACKOFF_MS[attempt]))
                continue
            }
            // Exhausted retries → durable fallback (offline queue).
            await queuePayloadOffline(payload, dirtyRows)
            retryAttempt.value = 0
            return
        }
    }
}

async function queuePayloadOffline(payload, dirtyRows) {
    try {
        await queueSnapshot({
            examId: props.exam.id,
            subjectId: props.subject.id,
            sectionId: props.section.id,
            payload,
        })
        hasQueuedSnapshot.value = true
        lastQueuedAt.value = new Date()
        saveStatus.value = 'queued'
        // Don't clear dirty — we want the next online autosave to re-send fresh.
        // But snapshot is on disk so even a refresh won't lose data.
    } catch (e) {
        saveStatus.value = 'error'
        saveError.value = 'Could not queue offline. Storage full?'
    }
}

/** Manual or auto sync of any queued snapshots. */
async function syncQueued(showToast = false) {
    if (syncInProgress.value || !navigator.onLine) return
    syncInProgress.value = true
    try {
        const result = await drainAll((examId) => route('marks.autosave', examId))
        if (result.sent > 0) {
            hasQueuedSnapshot.value = false
            lastSavedAt.value = new Date()
            saveStatus.value = 'saved'
            updateSavedAgoLabel()
        }
    } finally {
        syncInProgress.value = false
    }
}

// React to connectivity changes.
function handleOnline() {
    isOnline.value = true
    syncQueued()
}
function handleOffline() {
    isOnline.value = false
    saveStatus.value = 'queued'
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

onMounted(async () => {
    // Check if a snapshot is sitting in IDB for this scope (e.g. page closed
    // before reconnect). If so, mark the badge active and try to flush.
    try {
        const snap = await getSnapshot({
            examId: props.exam.id,
            subjectId: props.subject.id,
            sectionId: props.section.id,
        })
        if (snap) {
            hasQueuedSnapshot.value = true
            lastQueuedAt.value = new Date(snap.queued_at)
            if (navigator.onLine) syncQueued()
        }
    } catch (e) { /* IDB unavailable — fall through */ }

    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)
})

onBeforeUnmount(() => {
    clearInterval(savedAgoTimer)
    clearTimeout(autosaveTimer)
    window.removeEventListener('online', handleOnline)
    window.removeEventListener('offline', handleOffline)
})

// =============== Keyboard Navigation ===============
function focusCell(rowIdx, col = 'marks') {
    nextTick(() => {
        const el = document.querySelector(`[data-cell="${col}-${rowIdx}"]`)
        if (el) { el.focus(); if (el.select) el.select() }
    })
}
function onMarksKeydown(e, idx) {
    const row = rows.value[idx]

    // Enter / ArrowDown → next student's marks input (existing behavior)
    if (e.key === 'Enter' || e.key === 'ArrowDown') {
        e.preventDefault()
        focusCell(Math.min(idx + 1, rows.value.length - 1))
        return
    }

    // ArrowUp → previous student's marks input
    if (e.key === 'ArrowUp') {
        e.preventDefault()
        focusCell(Math.max(idx - 1, 0))
        return
    }

    // Tab / Shift+Tab → jump straight to next/previous student's marks input
    // (skipping the absent checkbox and remarks inputs in between). Power users
    // can still Shift+Tab to checkbox by pressing Tab from there.
    if (e.key === 'Tab' && !e.ctrlKey && !e.metaKey && !e.altKey) {
        e.preventDefault()
        focusCell(e.shiftKey ? Math.max(idx - 1, 0) : Math.min(idx + 1, rows.value.length - 1))
        return
    }

    // Space → toggle Absent for this row. If currently absent, untoggling clears
    // it back to empty and the cursor stays here so the teacher can type a number.
    // Only fires when the field is empty (so it doesn't kill normal typing).
    if (e.key === ' ' && !row.marks_obtained && !editLocked.value) {
        e.preventDefault()
        row.is_absent = !row.is_absent
        toggleAbsent(row)
        return
    }

    // Esc → clear this row and move focus away. Quick "I made a mistake" reset.
    if (e.key === 'Escape') {
        e.preventDefault()
        clearRow(row)
        e.target.blur()
        return
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

// postWithRetry — Inertia router.post wrapper with exponential backoff.
// 4xx surfaces immediately (validation/permission — retry can't fix it);
// 5xx / network retries up to MAX_RETRIES with the same backoff schedule
// as autosave() so the user sees consistent behavior across paths.
function postWithRetry(url, payload, options = {}) {
    let attempt = 0
    const attemptPost = () => {
        router.post(url, payload, {
            ...options,
            onError: errors => {
                const status = (typeof window !== 'undefined' && window.__lastInertiaStatus) || 0
                const isValidation = errors && Object.keys(errors).length > 0 && status >= 400 && status < 500
                if (isValidation || attempt >= MAX_RETRIES) {
                    options.onError?.(errors)
                    options.onFinish?.()
                    return
                }
                attempt++
                setTimeout(attemptPost, RETRY_BACKOFF_MS[Math.min(attempt - 1, RETRY_BACKOFF_MS.length - 1)])
            },
        })
    }
    attemptPost()
}

function submitMarks() {
    submitProcessing.value = true
    postWithRetry(route('marks.submit', [props.exam.id, props.subject.id, props.section.id]), {}, {
        onSuccess: () => { showReviewModal.value = false },
        onFinish: () => { submitProcessing.value = false },
    })
}

// ─── Post-submission edit workflow ───
// Confirm dialog + save → POST to marks.store with the dirty rows. The
// controller detects the existing 'submitted' status and triggers a
// result recalc, then redirects with a success flash.
const showPostEditConfirm = ref(false)
const postEditSaving = ref(false)

function cancelEdit() {
    // Revert any unsaved row changes back to the persisted snapshot so
    // closing edit mode behaves like "undo, don't apply".
    for (const r of rows.value) {
        r.marks_obtained = r._snap.marks_obtained
        r.is_absent = r._snap.is_absent
        r.remarks = r._snap.remarks
        r.dirty = false
    }
    editMode.value = false
}

function requestPostEditSave() {
    if (!hasUnsavedEdits.value || hasErrors.value) return
    showPostEditConfirm.value = true
}

function savePostSubmitEdits() {
    if (postEditSaving.value) return
    postEditSaving.value = true
    const dirtyRows = rows.value.filter(r => r.dirty && !rowError(r))
    const payload = {
        subject_id: props.subject.id,
        section_id: props.section.id,
        school_class_id: props.schoolClass?.id,
        marks: dirtyRows.map(r => ({
            student_id: r.student_id,
            marks_obtained: r.is_absent ? null : parseMarks(r.marks_obtained),
            is_absent: r.is_absent,
            remarks: r.remarks || null,
        })),
    }
    postWithRetry(route('marks.store', props.exam.id), payload, {
        preserveScroll: true,
        onSuccess: () => {
            showPostEditConfirm.value = false
            editMode.value = false
            // Re-anchor each row's snapshot so the cleared state matches DB.
            for (const r of rows.value) {
                r._snap = {
                    marks_obtained: r.marks_obtained,
                    is_absent: r.is_absent,
                    remarks: r.remarks,
                }
                r.dirty = false
            }
        },
        onFinish: () => { postEditSaving.value = false },
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
                        <!-- Submit drafts button — only visible when there are unsubmitted
                             (draft) Mark rows for this paper. One-click way for teachers to
                             flip drafts to submitted without needing every student filled,
                             then cascades to result regeneration server-side. -->
                        <button v-if="draftCount > 0"
                            type="button"
                            @click="submitDrafts"
                            :disabled="submittingDrafts"
                            :title="`${draftCount} draft mark${draftCount === 1 ? '' : 's'} not yet submitted — click to submit them and refresh results.`"
                            class="flex-1 lg:flex-none px-3 lg:px-4 py-1.5 lg:py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-center lg:min-w-[92px] transition-colors disabled:opacity-60 disabled:cursor-not-allowed shadow-sm">
                            <div class="flex items-center justify-center gap-1.5">
                                <span v-if="submittingDrafts" class="loading loading-spinner loading-xs"></span>
                                <PaperAirplaneIcon v-else class="w-3.5 h-3.5" />
                                <div>
                                    <div class="text-base sm:text-lg font-extrabold tabular-nums leading-tight">{{ draftCount }}</div>
                                    <div class="text-[9px] uppercase tracking-widest font-semibold opacity-90">Submit drafts</div>
                                </div>
                            </div>
                        </button>
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
                        <!-- Marks Backup / History — always visible so
                             teachers can see and restore snapshots even if
                             the current page is empty (that's the whole
                             point: "marks disappeared" recovery). -->
                        <button type="button" @click="openSnapshotModal"
                            title="View marks backup history — restore any earlier snapshot if marks were lost."
                            class="flex-1 lg:flex-none px-2.5 lg:px-3 py-1.5 lg:py-2 rounded-xl bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-500/25 text-center lg:min-w-[76px] transition-colors">
                            <div class="flex items-center justify-center gap-1">
                                <ArchiveBoxIcon class="w-4 h-4" />
                                <div>
                                    <div class="text-[9px] uppercase tracking-widest font-semibold leading-tight">Marks</div>
                                    <div class="text-[9px] uppercase tracking-widest font-semibold leading-tight">Backup</div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ═══════════ AUTO-RESTORE SUCCESS RIBBON ═══════════
                 Shown ONLY on the page load where the controller actually
                 un-trashed Mark rows for this paper. Tells the user their
                 previously-submitted marks have come back automatically.
                 Disappears on the next reload (autoRestoredCount becomes 0). -->
            <div v-if="autoRestoredCount > 0"
                 class="rounded-2xl border border-emerald-500/40 bg-emerald-500/10 p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center flex-shrink-0">
                    <ArrowPathIcon class="w-5 h-5" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-emerald-900 dark:text-emerald-100">
                        {{ autoRestoredCount }} previously-submitted mark{{ autoRestoredCount === 1 ? '' : 's' }} restored automatically
                    </div>
                    <div class="text-xs text-emerald-800/80 dark:text-emerald-200/80 leading-relaxed mt-0.5">
                        The marks were soft-deleted by an earlier admin action and have been brought back. They appear in the grid below — no re-entry needed.
                    </div>
                </div>
            </div>

            <!-- ═══════════ DELETED-MARKS RECOVERY BANNER ═══════════
                 Surfaces when this (exam, subject, section) has soft-deleted
                 Mark rows in the database — usually the result of an admin
                 clicking remove-subject / remove-class earlier. The marks
                 aren't lost; one click brings them back. Renders above the
                 normal submitted/editing banners because recovery is the
                 most urgent thing the user needs to act on. -->
            <div v-if="deletedMarksCount > 0"
                 class="rounded-2xl border-2 border-rose-500/40 bg-rose-500/10 p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-500 text-white flex items-center justify-center flex-shrink-0">
                    <ExclamationTriangleIcon class="w-5 h-5" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-rose-900 dark:text-rose-100">
                        {{ deletedMarksCount }} previously-submitted mark{{ deletedMarksCount === 1 ? '' : 's' }} {{ deletedMarksCount === 1 ? 'is' : 'are' }} hidden
                    </div>
                    <div class="text-xs text-rose-800/80 dark:text-rose-200/80 leading-relaxed mt-0.5">
                        The marks are still in the database — soft-deleted by an earlier admin action.
                        Click <b>Restore marks</b> to bring them back. Existing fresh entries (if any) won't be overwritten — the newest entry always wins.
                    </div>
                </div>
                <button type="button" @click="restoreDeletedMarks"
                        :disabled="restoringMarks"
                        class="btn btn-error btn-sm gap-1.5 rounded-xl shrink-0">
                    <ArrowPathIcon class="w-4 h-4" :class="restoringMarks ? 'animate-spin' : ''" />
                    {{ restoringMarks ? 'Restoring…' : `Restore ${deletedMarksCount} mark${deletedMarksCount === 1 ? '' : 's'}` }}
                </button>
            </div>

            <!-- ═══════════ SUBMITTED BANNER ═══════════ -->
            <!-- Three states:
                 1. submitted + no edit permission → green lock banner
                 2. submitted + permission + NOT yet in edit mode → invite to edit
                 3. submitted + permission + IN edit mode → amber "you're editing" banner -->
            <div v-if="isSubmitted && !canEditAfterSubmit"
                 class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center flex-shrink-0">
                    <CheckCircleIcon class="w-5 h-5" />
                </div>
                <div>
                    <div class="font-bold text-emerald-900 dark:text-emerald-100">Marks Submitted</div>
                    <div class="text-xs text-emerald-800/75 dark:text-emerald-200/75">
                        These marks are locked. Ask your administrator to enable post-submission edits for this exam to change them.
                    </div>
                </div>
            </div>

            <div v-else-if="isSubmitted && canEditAfterSubmit && !editMode"
                 class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center flex-shrink-0">
                    <CheckCircleIcon class="w-5 h-5" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-emerald-900 dark:text-emerald-100">Marks Submitted</div>
                    <div class="text-xs text-emerald-800/75 dark:text-emerald-200/75">
                        Your administrator has enabled post-submission edits. Click <b>Edit Marks</b> to revise — results will recalculate automatically after you save.
                    </div>
                </div>
                <button type="button" @click="editMode = true"
                        class="btn btn-warning btn-sm gap-1.5 rounded-xl shrink-0">
                    <PencilSquareIcon class="w-4 h-4" /> Edit Marks
                </button>
            </div>

            <div v-else-if="isSubmitted && canEditAfterSubmit && editMode"
                 class="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center flex-shrink-0">
                    <PencilSquareIcon class="w-5 h-5" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-amber-900 dark:text-amber-100">Editing submitted marks</div>
                    <div class="text-xs text-amber-800/80 dark:text-amber-200/80">
                        Changes won't apply until you click <b>Save changes</b> below.
                        Result percentages, grades and pass/retry will recalculate automatically.
                    </div>
                </div>
                <button type="button" @click="cancelEdit"
                        class="btn btn-ghost btn-sm gap-1.5 rounded-xl shrink-0">
                    <XCircleIcon class="w-4 h-4" /> Cancel
                </button>
            </div>

            <!-- ═══════════ STATUS STRIP ═══════════ -->
            <div v-if="!editLocked" class="rounded-2xl border border-base-200 bg-base-100 p-4">
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

                    <div class="flex items-center gap-3 flex-wrap">
                        <!-- Online / offline pill (always visible while saving features matter) -->
                        <span v-if="!isOnline"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-rose-500/15 text-rose-700 dark:text-rose-300 text-[10px] font-bold uppercase tracking-wider">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                            Offline
                        </span>

                        <!-- Save indicator -->
                        <div v-if="saveStatus === 'saving'" class="text-xs text-base-content/55 flex items-center gap-1.5">
                            <span class="loading loading-spinner loading-xs"></span>
                            Saving...
                        </div>
                        <div v-else-if="saveStatus === 'retrying'" class="text-xs text-amber-700 dark:text-amber-300 flex items-center gap-1.5 font-medium">
                            <span class="loading loading-spinner loading-xs"></span>
                            Retrying save (attempt {{ retryAttempt }} of {{ MAX_RETRIES }})...
                        </div>
                        <div v-else-if="saveStatus === 'queued' || hasQueuedSnapshot" class="text-xs text-amber-700 dark:text-amber-300 flex items-center gap-1.5 font-medium">
                            <CloudIcon class="w-3.5 h-3.5" />
                            <span>Saved locally — will sync when online</span>
                            <button v-if="isOnline" @click="syncQueued" :disabled="syncInProgress"
                                class="underline ml-1">{{ syncInProgress ? 'Syncing…' : 'Sync now' }}</button>
                        </div>
                        <div v-else-if="saveStatus === 'saved'" class="text-xs text-emerald-700 flex items-center gap-1.5 font-medium">
                            <CheckCircleIcon class="w-3.5 h-3.5" />
                            Draft saved {{ lastSavedAgo }}
                        </div>
                        <div v-else-if="saveStatus === 'error'" class="text-xs text-rose-700 flex items-center gap-1.5 font-medium" :title="saveError">
                            <ExclamationTriangleIcon class="w-3.5 h-3.5" />
                            {{ saveError || "Save didn't go through" }} <button @click="autosave" class="underline">Retry</button>
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

            <!-- ═══════════ SEARCH + FILTER (works on both mobile + desktop) ═══════════ -->
            <div v-if="rows.length" class="rounded-2xl border border-base-200 bg-base-100 p-2.5 sm:p-3 space-y-2">
                <div class="relative">
                    <MagnifyingGlassIcon class="w-4 h-4 text-base-content/40 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input v-model="search" type="text"
                        placeholder="Search by name or roll number…"
                        class="input input-bordered input-sm w-full pl-9 text-sm" />
                </div>
                <div class="flex items-center gap-1.5 flex-wrap">
                    <button v-for="f in [
                        { k: 'all', label: 'All', count: filterCounts.all },
                        { k: 'empty', label: 'Empty', count: filterCounts.empty },
                        { k: 'errors', label: 'Errors', count: filterCounts.errors },
                        { k: 'absent', label: 'Absent', count: filterCounts.absent },
                    ]" :key="f.k"
                        @click="filterMode = f.k"
                        class="px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider transition-colors flex items-center gap-1"
                        :class="filterMode === f.k
                            ? (f.k === 'errors' ? 'bg-rose-500 text-white'
                               : f.k === 'empty' ? 'bg-amber-500 text-white'
                               : f.k === 'absent' ? 'bg-orange-500 text-white'
                               : 'bg-primary text-primary-content')
                            : 'bg-base-200 text-base-content/65 hover:bg-base-300'">
                        <span>{{ f.label }}</span>
                        <span class="font-mono tabular-nums opacity-80">{{ f.count }}</span>
                    </button>
                    <button v-if="search || filterMode !== 'all'"
                        @click="search = ''; filterMode = 'all'"
                        class="ml-auto text-[11px] text-base-content/55 hover:text-base-content underline">
                        Clear
                    </button>
                </div>
                <p v-if="visibleRows.length !== rows.length" class="text-[11px] text-base-content/55">
                    Showing {{ visibleRows.length }} of {{ rows.length }} students
                </p>
            </div>

            <!-- ═══════════ MOBILE CARD VIEW (xs–sm) ═══════════
                 Touch-friendly per-student cards. Replaces the table on phones.
                 Layout: 2 rows. Top row = identity + status. Bottom row = stepper input full-width.
                 Percentage + absent shown as inline pills below.
            -->
            <div class="sm:hidden space-y-2.5 max-w-full">
                <div v-for="{ row, idx } in visibleRows" :key="`m-${row.student_id}`"
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
                            :disabled="row.is_absent || editLocked"
                            class="w-11 h-12 shrink-0 rounded-xl bg-base-200 text-xl font-bold text-base-content/75 active:scale-95 transition-transform disabled:opacity-30 disabled:active:scale-100"
                            aria-label="Decrease">−</button>

                        <input
                            v-model="row.marks_obtained"
                            @input="markDirty(row)"
                            :disabled="row.is_absent || editLocked"
                            type="text"
                            inputmode="decimal"
                            :placeholder="row.is_absent ? 'AB' : 'Marks'"
                            class="input input-bordered flex-1 min-w-0 h-12 text-center text-xl font-bold tabular-nums px-1"
                            :class="{ 'input-error': rowErrors[idx], 'opacity-40 placeholder-amber-700': row.is_absent }"
                        />

                        <button type="button"
                            @click="bumpMarks(row, 1)"
                            :disabled="row.is_absent || editLocked"
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
                            <input type="checkbox" v-model="row.is_absent" @change="toggleAbsent(row)" :disabled="editLocked"
                                   class="checkbox checkbox-warning checkbox-xs" />
                            <span class="text-[10px] font-bold uppercase tracking-wider">Absent</span>
                        </label>
                    </div>

                    <div v-if="rowErrors[idx]" class="mx-3 mb-3 px-2.5 py-1.5 rounded-lg bg-rose-500/15 text-rose-700 dark:text-rose-300 text-[11px] font-medium">
                        {{ rowErrors[idx] }} — enter 0 to {{ totalMarks }}
                    </div>

                    <!-- Remarks expander (mobile-only — desktop has its own column) -->
                    <div v-if="!isSubmitted" class="border-t border-base-200/70">
                        <button type="button"
                            @click="expandedRemarks[row.student_id] = !expandedRemarks[row.student_id]"
                            class="w-full px-3 py-2 flex items-center gap-1.5 text-[11px] text-base-content/55 hover:bg-base-200/50 transition-colors">
                            <ChatBubbleLeftEllipsisIcon class="w-3.5 h-3.5" />
                            <span class="font-medium">{{ row.remarks ? 'Edit remark' : 'Add remark' }}</span>
                            <span v-if="row.remarks" class="ml-1 text-base-content/45 truncate max-w-[55%]">— {{ row.remarks }}</span>
                            <ChevronDownIcon class="w-3.5 h-3.5 ml-auto transition-transform"
                                :class="expandedRemarks[row.student_id] ? 'rotate-180' : ''" />
                        </button>
                        <div v-if="expandedRemarks[row.student_id]" class="px-3 pb-3 pt-1">
                            <input v-model="row.remarks" @input="markDirty(row)"
                                :disabled="row.is_absent"
                                type="text"
                                placeholder="e.g. excellent improvement, needs attention"
                                class="input input-bordered input-sm w-full text-xs" />
                        </div>
                    </div>
                </div>

                <div v-if="!rows.length" class="rounded-2xl border border-base-200 px-4 py-12 text-center text-sm text-base-content/55">
                    <UserGroupIcon class="w-10 h-10 text-base-content/30 mx-auto mb-2" />
                    No students in this section.
                </div>
                <div v-else-if="!visibleRows.length" class="rounded-2xl border border-base-200 px-4 py-8 text-center text-sm text-base-content/55">
                    <MagnifyingGlassIcon class="w-8 h-8 text-base-content/30 mx-auto mb-1.5" />
                    No students match the current filter.
                </div>
            </div>

            <!-- Keyboard shortcut hints — discoverable, only on desktop -->
            <div v-if="!isSubmitted" class="hidden sm:flex items-center gap-3 px-4 py-2 rounded-xl bg-base-200/40 border border-base-200 text-[11px] text-base-content/65">
                <span class="font-bold uppercase tracking-wider text-base-content/45">Shortcuts:</span>
                <span><kbd class="kbd kbd-xs">Tab</kbd> next</span>
                <span><kbd class="kbd kbd-xs">⇧</kbd>+<kbd class="kbd kbd-xs">Tab</kbd> previous</span>
                <span><kbd class="kbd kbd-xs">↵</kbd> next</span>
                <span><kbd class="kbd kbd-xs">Space</kbd> toggle absent</span>
                <span><kbd class="kbd kbd-xs">Esc</kbd> clear &amp; cancel</span>
                <span class="ml-auto text-base-content/45">Paste a column from Excel anywhere</span>
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
                            <tr v-for="{ row, idx } in visibleRows" :key="row.student_id"
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
                                        :disabled="row.is_absent || editLocked"
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
                                        :disabled="editLocked"
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
                                        :disabled="row.is_absent || editLocked"
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

            <!-- Post-submission EDIT action bar — shown only when the teacher
                 has clicked Edit Marks on a submitted section. Stays visible
                 until they Save or Cancel. Confirmation modal handles save. -->
            <div v-if="isSubmitted && editMode && rows.length"
                class="sticky bottom-4 rounded-2xl border border-amber-500/40 bg-amber-50/95 dark:bg-amber-950/40 backdrop-blur-xl shadow-xl p-3 z-10">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-2 text-xs text-amber-900 dark:text-amber-200">
                        <PencilSquareIcon class="w-4 h-4 shrink-0" />
                        <span v-if="hasUnsavedEdits">
                            You've changed {{ rows.filter(r => r.dirty).length }} student{{ rows.filter(r => r.dirty).length === 1 ? '' : 's' }} — click <b>Save changes</b> to apply.
                        </span>
                        <span v-else>Make your edits below, then save.</span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button @click="cancelEdit" class="btn btn-ghost btn-sm rounded-xl gap-1.5">
                            <XCircleIcon class="w-4 h-4" /> Cancel
                        </button>
                        <button @click="requestPostEditSave"
                            :disabled="!hasUnsavedEdits || hasErrors"
                            class="btn btn-warning btn-sm rounded-xl gap-1.5">
                            <DocumentCheckIcon class="w-4 h-4" /> Save changes
                        </button>
                    </div>
                </div>
                <div v-if="hasErrors" class="mt-2 px-2 py-1 rounded-md bg-rose-500/15 text-rose-700 dark:text-rose-300 text-[11px] font-medium flex items-center gap-1.5">
                    <ExclamationTriangleIcon class="w-3.5 h-3.5 shrink-0" />
                    Fix highlighted rows before saving
                </div>
            </div>

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
                                <div class="text-[10px] uppercase tracking-wider text-rose-700 font-semibold">Retry</div>
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

        <!-- ═══════════ POST-SUBMISSION EDIT CONFIRMATION ═══════════
             Last-chance dialog before applying changes to already-submitted
             marks. The controller does the actual cascade into Result rows. -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0" enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="showPostEditConfirm"
                class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4"
                @click.self="!postEditSaving && (showPostEditConfirm = false)">
                <div class="bg-base-100 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
                    <div class="px-5 py-4 border-b border-base-200 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white flex items-center justify-center shadow-md">
                            <ExclamationTriangleIcon class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-bold text-base">Save edits to submitted marks?</h3>
                            <p class="text-[11px] text-base-content/55">This action recalculates results.</p>
                        </div>
                    </div>
                    <div class="px-5 py-4 space-y-2 text-sm">
                        <p>
                            You're editing
                            <b class="text-base-content">{{ rows.filter(r => r.dirty).length }}</b>
                            already-submitted student mark{{ rows.filter(r => r.dirty).length === 1 ? '' : 's' }}.
                        </p>
                        <p class="text-base-content/65 text-xs leading-relaxed">
                            On save: the new marks are recorded against the existing submission,
                            and the system automatically recalculates total marks, percentages, grades,
                            pass/retry and merit positions for every affected student. Updated
                            values flow straight into result cards and result sheets.
                        </p>
                    </div>
                    <div class="px-5 py-3 border-t border-base-200 bg-base-200/40 flex items-center justify-end gap-2">
                        <button @click="showPostEditConfirm = false" :disabled="postEditSaving"
                            class="btn btn-ghost btn-sm rounded-xl">Cancel</button>
                        <button @click="savePostSubmitEdits" :disabled="postEditSaving"
                            class="btn btn-warning btn-sm rounded-xl gap-1.5">
                            <ArrowPathIcon v-if="postEditSaving" class="w-4 h-4 animate-spin" />
                            <DocumentCheckIcon v-else class="w-4 h-4" />
                            {{ postEditSaving ? 'Saving…' : 'Save & recalculate' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- ═══════════ MARKS BACKUP / SNAPSHOTS MODAL ═══════════
             Lists every snapshot ever taken for this (exam, subject,
             section). Each row shows what triggered the snapshot, who
             took it, when, and how many students it covered. Clicking
             Restore rolls the paper back to that snapshot — a fresh
             pre_restore snapshot is auto-taken first so the restore
             itself is undoable.  -->
        <Transition name="modal">
            <div v-if="showSnapshotModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                @click.self="showSnapshotModal = false">
                <div class="bg-base-100 rounded-2xl shadow-2xl max-w-3xl w-full max-h-[85vh] flex flex-col">
                    <div class="p-5 border-b border-base-200 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/15 text-indigo-600 dark:text-indigo-300 flex items-center justify-center flex-shrink-0">
                            <ArchiveBoxIcon class="w-5 h-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-lg font-extrabold">Marks Backup History</div>
                            <div class="text-xs text-base-content/65">
                                {{ subject?.name }} · {{ schoolClass?.name }} {{ section?.name }}
                                — every save, submit and destructive admin action is captured here.
                            </div>
                        </div>
                        <button @click="showSnapshotModal = false" class="btn btn-ghost btn-sm">Close</button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-4">
                        <div v-if="snapshotsLoading" class="p-8 text-center text-sm text-base-content/55">
                            <span class="loading loading-spinner loading-md"></span>
                            <div class="mt-2">Loading snapshots…</div>
                        </div>
                        <div v-else-if="!snapshots.length" class="p-8 text-center text-sm text-base-content/55">
                            <ArchiveBoxIcon class="w-10 h-10 mx-auto text-base-content/25 mb-3" />
                            <div class="font-medium mb-1">No snapshots yet.</div>
                            <div class="text-xs">Snapshots are created automatically the first time marks are saved or an admin touches this paper.</div>
                        </div>
                        <ul v-else class="space-y-2">
                            <li v-for="snap in snapshots" :key="snap.id"
                                class="rounded-xl border border-base-200 hover:border-indigo-500/40 hover:bg-indigo-500/5 transition-colors p-3">
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-base-200 flex items-center justify-center shrink-0">
                                        <ClockIcon class="w-4 h-4 text-base-content/55" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold text-sm">{{ triggerLabel(snap.trigger) }}</span>
                                            <span class="text-[10px] uppercase tracking-wider font-bold text-base-content/45">
                                                {{ snap.student_count }} student{{ snap.student_count === 1 ? '' : 's' }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-base-content/65 mt-0.5">
                                            {{ snap.taken_at_human }}
                                            <span v-if="snap.taken_by"> · by {{ snap.taken_by }}</span>
                                        </div>
                                        <div v-if="snap.notes" class="text-[11px] text-base-content/55 mt-1 italic">
                                            {{ snap.notes }}
                                        </div>
                                    </div>
                                    <button type="button"
                                        @click="restoreSnapshot(snap.id)"
                                        :disabled="restoringSnapshotId !== null"
                                        class="btn btn-sm btn-primary shrink-0">
                                        <span v-if="restoringSnapshotId === snap.id" class="loading loading-spinner loading-xs"></span>
                                        <ArrowPathIcon v-else class="w-3.5 h-3.5" />
                                        Restore
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="p-4 border-t border-base-200 text-[11px] text-base-content/55 bg-base-200/30 rounded-b-2xl">
                        <b>How this works:</b> the system keeps the last 20 snapshots per paper. Restoring any one of them
                        overwrites the current marks — but a fresh "pre-restore" snapshot is captured first, so the
                        restore itself is undoable.
                    </div>
                </div>
            </div>
        </Transition>
    </AppLayout>
</template>
