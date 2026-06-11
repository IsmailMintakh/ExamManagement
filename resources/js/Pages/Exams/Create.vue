<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { Head, useForm, Link, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import {
    ArrowLeftIcon, ArrowRightIcon, CheckCircleIcon, ClipboardDocumentListIcon,
    BuildingOfficeIcon, AcademicCapIcon, Cog6ToothIcon, EyeIcon,
    SparklesIcon, BoltIcon, PlusIcon, TrashIcon, ExclamationTriangleIcon,
    InformationCircleIcon, XCircleIcon, CheckIcon, BookmarkIcon, BeakerIcon,
    LockClosedIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: Object,
    examTypes: Array,
    sessions: Array,
    schools: Array,
    subjects: Array,
    classes: Array,
    gradingScales: Array,
    teachers: { type: Array, default: () => [] },
    isSuperAdmin: Boolean,
    currentSchoolId: Number,
    defaultTermWeights: { type: Object, default: () => ({ first: 25, second: 25, final: 50 }) },
})

const isEdit = !!props.exam?.id

const form = useForm({
    name: props.exam?.name || '',
    exam_type_id: props.exam?.exam_type_id || '',
    academic_session_id: props.exam?.academic_session_id || '',
    grading_scale_id: props.exam?.grading_scale_id || '',
    term: props.exam?.term || '',
    combine_previous_terms: props.exam?.combine_previous_terms ?? false,
    term_weights: props.exam?.term_weights || { ...props.defaultTermWeights },
    exam_controller_id: props.exam?.exam_controller_id || '',
    start_date: props.exam?.start_date || '',
    end_date: props.exam?.end_date || '',
    description: props.exam?.description || '',
    marks_entry_deadline: props.exam?.marks_entry_deadline || '',
    total_marks: props.exam?.total_marks ?? 100,
    passing_marks: props.exam?.passing_marks ?? 33,
    passing_percentage: props.exam?.passing_percentage ?? 33,
    min_subjects_to_pass: props.exam?.min_subjects_to_pass ?? null,
    main_subjects_must_pass: props.exam?.main_subjects_must_pass ?? false,
    all_subjects_must_pass: props.exam?.all_subjects_must_pass ?? false,
    grace_marks: props.exam?.grace_marks ?? 0,
    grace_marks_max_subjects: props.exam?.grace_marks_max_subjects ?? 0,
    position_calculation: props.exam?.position_calculation || 'section',
    passing_rules: props.exam?.passing_rules || {
        fail_if_absent_in_any: false,
        mandatory_subjects: [],
        custom_conditions: [],
    },
    combination_rules: props.exam?.combination_rules || { enabled: false, source_exams: [] },
    apply_to_all_schools: props.exam?.apply_to_all_schools ?? false,
    selected_school_ids: props.exam?.schools?.map(s => s.id) || (props.currentSchoolId ? [props.currentSchoolId] : []),
    subjects: props.exam?.exam_subjects?.map(es => ({
        subject_id: es.subject_id,
        school_class_id: es.school_class_id,
        total_marks: es.total_marks,
        passing_marks: es.passing_marks,
        exam_date: es.exam_date || '',
        // Carried for the UI only: locks the row when marks have already
        // been entered for this (subject, class), so the admin can't bump
        // total_marks / passing_marks (which would silently invalidate
        // students' existing percentages) or un-tick to remove the row.
        // The controller enforces the same on the server.
        marks_count: es.marks_count || 0,
    })) || [],
})

// ════════════════════════════════════════════════════════════════
// WIZARD STATE
// ════════════════════════════════════════════════════════════════
const step = ref(1)
const steps = [
    { num: 1, key: 'basics', label: 'Basics', icon: ClipboardDocumentListIcon, desc: 'Name, dates, total marks' },
    { num: 2, key: 'scope', label: 'Scope', icon: BuildingOfficeIcon, desc: 'Schools to apply to' },
    { num: 3, key: 'subjects', label: 'Subjects', icon: AcademicCapIcon, desc: 'Map subjects to classes' },
    { num: 4, key: 'rules', label: 'Passing Rules', icon: Cog6ToothIcon, desc: 'Custom logic' },
    { num: 5, key: 'preview', label: 'Preview', icon: EyeIcon, desc: 'See sample outcomes' },
]

// Live total for the 1st/2nd/Final weights — backend rejects unless this is 100.
const termWeightSum = computed(() =>
    (Number(form.term_weights?.first) || 0)
    + (Number(form.term_weights?.second) || 0)
    + (Number(form.term_weights?.final) || 0)
)

function canAdvance(stepNum) {
    if (stepNum === 1) return form.name && form.exam_type_id && form.academic_session_id && form.start_date && form.end_date
    if (stepNum === 2) return form.apply_to_all_schools || form.selected_school_ids.length > 0
    if (stepNum === 3) return form.subjects.length > 0
    if (stepNum === 4) return true // rules are optional
    return true
}
function next() { if (canAdvance(step.value) && step.value < 5) step.value++ }
function back() { if (step.value > 1) step.value-- }
function gotoStep(n) {
    // Allow jumping back, only forward if previous steps valid
    if (n < step.value) { step.value = n; return }
    for (let i = step.value; i < n; i++) {
        if (!canAdvance(i)) return
    }
    step.value = n
}

// ════════════════════════════════════════════════════════════════
// TEMPLATES (Step 1 quick-start)
// ════════════════════════════════════════════════════════════════
const presets = [
    {
        key: 'monthly', label: 'Monthly Test', icon: BoltIcon,
        desc: '50 marks · 33% pass · No grace marks',
        config: { total_marks: 50, passing_marks: 17, passing_percentage: 33, grace_marks: 0, grace_marks_max_subjects: 0, main_subjects_must_pass: false, all_subjects_must_pass: false, position_calculation: 'section' },
    },
    {
        key: 'term', label: 'Term Exam', icon: ClipboardDocumentListIcon,
        desc: '100 marks · 33% pass · 5 grace, max 2 subjects',
        config: { total_marks: 100, passing_marks: 33, passing_percentage: 33, grace_marks: 5, grace_marks_max_subjects: 2, main_subjects_must_pass: true, all_subjects_must_pass: false, position_calculation: 'section' },
    },
    {
        key: 'annual', label: 'Annual Exam', icon: AcademicCapIcon,
        desc: '100 marks · main subjects must pass · 5 grace',
        config: { total_marks: 100, passing_marks: 33, passing_percentage: 33, grace_marks: 5, grace_marks_max_subjects: 2, main_subjects_must_pass: true, all_subjects_must_pass: false, position_calculation: 'class' },
    },
    {
        key: 'board', label: 'Board Pattern', icon: SparklesIcon,
        desc: '100 marks · all subjects must pass · no grace',
        config: { total_marks: 100, passing_marks: 33, passing_percentage: 33, grace_marks: 0, grace_marks_max_subjects: 0, main_subjects_must_pass: false, all_subjects_must_pass: true, position_calculation: 'school' },
    },
]
const activePreset = ref(null)
function applyPreset(p) {
    Object.assign(form, p.config)
    activePreset.value = p.key
}

// Auto-calc passing_marks from percentage
watch(() => [form.passing_percentage, form.total_marks], ([pct, total]) => {
    if (pct && total) form.passing_marks = Math.round((pct / 100) * total * 100) / 100
})

// ─── Auto-sum total marks from per-subject totals ───
// User feedback: the exam-level "Total Marks" should reflect what was set on
// the subject rows in Step 3, not be a separate guess. Default behaviour is to
// auto-sum and keep in sync; user can toggle override (e.g. exam awards bonus
// marks not represented in any single subject).
const totalMarksAutoSync = ref(true)
const subjectsTotalMarksSum = computed(() =>
    form.subjects.reduce((sum, s) => sum + (Number(s.total_marks) || 0), 0)
)
const subjectsPassingMarksSum = computed(() =>
    form.subjects.reduce((sum, s) => sum + (Number(s.passing_marks) || 0), 0)
)
watch(subjectsTotalMarksSum, (newSum) => {
    if (totalMarksAutoSync.value && newSum > 0) {
        form.total_marks = newSum
    }
})

// ─── Typical per-subject marks (mode of the rows) ───
// The "Configuration Summary" needs to show meaningful numbers for the user.
// 2100 total / 33% passing was confusing because:
//   - 2100 is the SUM of all subject totals (correct, but not "per subject")
//   - 33% was a stale default that never updated to the 40% the user set
// We now derive the typical per-subject total/passing values and use them
// to (a) display in the summary and (b) auto-sync passing_percentage.
function mode(values) {
    if (!values.length) return null
    const counts = new Map()
    for (const v of values) counts.set(v, (counts.get(v) || 0) + 1)
    let best = null, bestCount = 0
    for (const [v, c] of counts) {
        if (c > bestCount) { best = v; bestCount = c }
    }
    return best
}
const subjectTotalsTypical = computed(() =>
    mode(form.subjects.map(s => Number(s.total_marks) || 0).filter(v => v > 0))
)
const subjectPassingTypical = computed(() =>
    mode(form.subjects.map(s => Number(s.passing_marks) || 0).filter(v => v >= 0))
)
const subjectPassingPctTypical = computed(() => {
    if (!subjectTotalsTypical.value || subjectTotalsTypical.value <= 0) return null
    if (subjectPassingTypical.value == null) return null
    return Math.round((subjectPassingTypical.value / subjectTotalsTypical.value) * 1000) / 10
})
// True when at least one subject row uses different marks than the typical
// pair — surfaced in the summary so the user is reminded "not uniform".
const subjectsMarksUniform = computed(() => {
    if (form.subjects.length === 0) return true
    return form.subjects.every(s =>
        Number(s.total_marks) === subjectTotalsTypical.value
        && Number(s.passing_marks) === subjectPassingTypical.value
    )
})

// Keep form.passing_percentage in sync with what the subjects actually use —
// otherwise the summary keeps showing a stale 33%.
watch(subjectPassingPctTypical, (pct) => {
    if (pct != null && Math.abs((form.passing_percentage || 0) - pct) > 0.05) {
        form.passing_percentage = pct
    }
})

// ─── Per-class breakdown for the Summary panel ───
// "Sum of all totals" across every class isn't a meaningful number — each
// class only sits its own subjects. This builds one row per class with that
// class's totals, which is what teachers actually grade against.
const subjectsByClassBreakdown = computed(() => {
    const map = new Map()
    for (const row of form.subjects) {
        const cid = Number(row.school_class_id)
        if (!cid) continue
        if (!map.has(cid)) {
            const cls = (props.classes || []).find(c => Number(c.id) === cid)
            map.set(cid, {
                id: cid,
                name: cls?.name || `Class #${cid}`,
                sort_order: cls?.sort_order ?? 9999,
                subjects: 0,
                total: 0,
                passing: 0,
            })
        }
        const r = map.get(cid)
        r.subjects += 1
        r.total += Number(row.total_marks) || 0
        r.passing += Number(row.passing_marks) || 0
    }
    return [...map.values()]
        .map(r => ({
            ...r,
            percentage: r.total > 0 ? Math.round((r.passing / r.total) * 1000) / 10 : 0,
        }))
        .sort((a, b) => (a.sort_order - b.sort_order) || a.name.localeCompare(b.name))
})
// User typed in the Total Marks input → switch off auto-sync so we don't fight
// them on the next subject change.
function disableTotalMarksAutoSync() {
    totalMarksAutoSync.value = false
}
// User clicks the "Sync from subjects" hint to re-enable auto-sync.
function reEnableTotalMarksAutoSync() {
    totalMarksAutoSync.value = true
    if (subjectsTotalMarksSum.value > 0) form.total_marks = subjectsTotalMarksSum.value
}

// ════════════════════════════════════════════════════════════════
// STEP 3: BULK SUBJECT MAPPING
// ════════════════════════════════════════════════════════════════
const bulkSelectedClasses = ref([])
const bulkSelectedSubjects = ref([])
const bulkTotalMarks = ref(100)
const bulkPassingMarks = ref(33)

// Per-subject override map: subject_id → { total, passing }.
// Only filled when admin wants a subject to differ from the global default
// (e.g. Computer = 50 / 17, while every other subject keeps 100 / 33). Empty
// entry falls back to bulkTotalMarks / bulkPassingMarks.
const bulkSubjectOverrides = ref({})

function ensureOverride(subjectId) {
    if (!bulkSubjectOverrides.value[subjectId]) {
        bulkSubjectOverrides.value[subjectId] = { total: null, passing: null }
    }
    return bulkSubjectOverrides.value[subjectId]
}
function clearOverride(subjectId) {
    delete bulkSubjectOverrides.value[subjectId]
}
function overrideOf(subjectId) {
    const o = bulkSubjectOverrides.value[subjectId]
    return {
        total: (o && o.total != null && o.total !== '') ? Number(o.total) : bulkTotalMarks.value,
        passing: (o && o.passing != null && o.passing !== '') ? Number(o.passing) : bulkPassingMarks.value,
    }
}
const showSubjectOverrides = ref(false)

const availableClasses = computed(() => {
    if (form.apply_to_all_schools) return props.classes || []
    if (!form.selected_school_ids.length) return []
    return (props.classes || []).filter(c => form.selected_school_ids.includes(c.school_id))
})

// Subjects are scoped to the classes the user has selected — only subjects
// actually assigned to those classes (via the class_subjects pivot) are
// offered. Otherwise the form would let users create exam papers for subjects
// that the class doesn't even teach.
const availableSubjects = computed(() => {
    if (!bulkSelectedClasses.value.length) return []
    const allowedIds = new Set()
    for (const classId of bulkSelectedClasses.value) {
        const cls = (props.classes || []).find(c => Number(c.id) === Number(classId))
        if (cls?.subjects) {
            for (const s of cls.subjects) allowedIds.add(s.id)
        }
    }
    return (props.subjects || []).filter(s => allowedIds.has(s.id))
})

// Auto-select every available subject the moment a class is picked. This
// matches the common case ("create papers for all subjects this class teaches")
// while still letting the user un-tick a subject before applying.
watch(bulkSelectedClasses, () => {
    bulkSelectedSubjects.value = availableSubjects.value.map(s => s.id)
}, { deep: true })

function toggleAllBulkSubjects() {
    if (bulkSelectedSubjects.value.length === availableSubjects.value.length) {
        bulkSelectedSubjects.value = []
    } else {
        bulkSelectedSubjects.value = availableSubjects.value.map(s => s.id)
    }
}

function toggleAllBulkClasses() {
    if (bulkSelectedClasses.value.length === availableClasses.value.length) {
        bulkSelectedClasses.value = []
    } else {
        bulkSelectedClasses.value = availableClasses.value.map(c => c.id)
    }
}

function applyBulkSubjects() {
    if (!bulkSelectedClasses.value.length || !bulkSelectedSubjects.value.length) return
    let added = 0
    for (const classId of bulkSelectedClasses.value) {
        const cls = (props.classes || []).find(c => Number(c.id) === Number(classId))
        const classSubjectIds = new Set((cls?.subjects || []).map(s => s.id))
        for (const subjectId of bulkSelectedSubjects.value) {
            if (!classSubjectIds.has(Number(subjectId))) continue
            const exists = form.subjects.some(s =>
                Number(s.school_class_id) === Number(classId) && Number(s.subject_id) === Number(subjectId))
            if (exists) continue
            const m = overrideOf(subjectId)
            form.subjects.push({
                subject_id: subjectId,
                school_class_id: classId,
                total_marks: m.total,
                passing_marks: m.passing,
                exam_date: '',
            })
            added++
        }
    }
    if (added > 0) {
        bulkSelectedClasses.value = []
        bulkSelectedSubjects.value = []
        bulkSubjectOverrides.value = {}
        showSubjectOverrides.value = false
    }
}

// ── Bulk edit on the per-class table ──
// Tick the rows whose marks should change (or pick a whole subject / class
// at once via the helper buttons), set the marks, click Apply.
const editSelected = ref(new Set())
const editTotal = ref(null)
const editPass = ref(null)

function toggleEditRow(i) {
    if (editSelected.value.has(i)) editSelected.value.delete(i)
    else editSelected.value.add(i)
    editSelected.value = new Set(editSelected.value)
}
function selectAllRows() {
    if (editSelected.value.size === form.subjects.length) editSelected.value = new Set()
    else editSelected.value = new Set(form.subjects.map((_, i) => i))
}
function selectBySubject(subjectId) {
    const next = new Set(editSelected.value)
    form.subjects.forEach((r, i) => {
        if (Number(r.subject_id) === Number(subjectId)) next.add(i)
    })
    editSelected.value = next
}
function selectByClass(classId) {
    const next = new Set(editSelected.value)
    form.subjects.forEach((r, i) => {
        if (Number(r.school_class_id) === Number(classId)) next.add(i)
    })
    editSelected.value = next
}
function applyEditMarks() {
    if (!editSelected.value.size) return
    for (const i of editSelected.value) {
        if (editTotal.value !== null && editTotal.value !== '') form.subjects[i].total_marks = Number(editTotal.value)
        if (editPass.value !== null && editPass.value !== '') form.subjects[i].passing_marks = Number(editPass.value)
    }
    editSelected.value = new Set()
    editTotal.value = null
    editPass.value = null
}
function removeSelectedRows() {
    if (!editSelected.value.size) return
    const indices = [...editSelected.value].sort((a, b) => b - a) // delete back-to-front
    for (const i of indices) form.subjects.splice(i, 1)
    editSelected.value = new Set()
}

const distinctSubjectsInRows = computed(() => {
    const seen = new Map()
    for (const r of form.subjects) {
        if (r.subject_id && !seen.has(r.subject_id)) {
            const s = props.subjects.find(x => Number(x.id) === Number(r.subject_id))
            seen.set(r.subject_id, { id: r.subject_id, name: s?.name || `#${r.subject_id}` })
        }
    }
    return [...seen.values()]
})
const distinctClassesInRows = computed(() => {
    const seen = new Map()
    for (const r of form.subjects) {
        if (r.school_class_id && !seen.has(r.school_class_id)) {
            const c = props.classes.find(x => Number(x.id) === Number(r.school_class_id))
            seen.set(r.school_class_id, { id: r.school_class_id, name: c?.name || `#${r.school_class_id}` })
        }
    }
    return [...seen.values()]
})

// For the per-row subject dropdown — only show subjects assigned to that row's class
function subjectsForClass(classId) {
    if (!classId) return props.subjects || []
    const cls = (props.classes || []).find(c => Number(c.id) === Number(classId))
    if (!cls?.subjects) return props.subjects || []
    const allowedIds = new Set(cls.subjects.map(s => s.id))
    return (props.subjects || []).filter(s => allowedIds.has(s.id))
}

function addSubjectRow() {
    form.subjects.push({ subject_id: '', school_class_id: '', total_marks: form.total_marks || 100, passing_marks: form.passing_marks || 33, exam_date: '' })
}
function removeSubjectRow(i) { form.subjects.splice(i, 1) }
function clearAllSubjects() { form.subjects = [] }

// ─── "Add Missing Subject" quick-add modal ───
// Only available on the Edit Exam screen (props.exam exists). Append-only:
// posts to a dedicated endpoint that uses firstOrCreate semantics, so it
// physically cannot overwrite existing ExamSubject rows or disturb any Mark
// data. Designed for the "we accidentally omitted a subject during creation"
// case — admin picks a class, ticks one or more missing subjects, saves.
const showAddMissingModal = ref(false)
const addMissingClassId = ref(null)
const addMissingPicks = ref({}) // { subjectId: { ticked, total_marks, passing_marks, exam_date } }
const addMissingBusy = ref(false)

function openAddMissingModal() {
    addMissingClassId.value = null
    addMissingPicks.value = {}
    showAddMissingModal.value = true
}
function closeAddMissingModal() {
    showAddMissingModal.value = false
}

// Subjects already on this exam for the selected class — shown read-only.
const addMissingExistingForClass = computed(() => {
    if (!addMissingClassId.value) return []
    const cid = Number(addMissingClassId.value)
    return form.subjects
        .filter(s => Number(s.school_class_id) === cid)
        .map(s => ({
            subject: subjectMap.value[s.subject_id],
            total_marks: s.total_marks,
            passing_marks: s.passing_marks,
            marks_count: s.marks_count || 0,
        }))
        .filter(r => r.subject)
})

// Subjects assigned to the selected class (via class_subjects pivot) but NOT
// already on this exam — the actual "missing" candidates the admin can tick.
const addMissingCandidates = computed(() => {
    if (!addMissingClassId.value) return []
    const cls = (props.classes || []).find(c => Number(c.id) === Number(addMissingClassId.value))
    if (!cls?.subjects) return []
    const alreadyOnExam = new Set(
        form.subjects
            .filter(s => Number(s.school_class_id) === Number(addMissingClassId.value))
            .map(s => Number(s.subject_id))
    )
    return cls.subjects.filter(s => !alreadyOnExam.has(s.id))
})

function ensurePickRow(subjectId) {
    if (!addMissingPicks.value[subjectId]) {
        addMissingPicks.value[subjectId] = {
            ticked: false,
            total_marks: form.total_marks || 100,
            passing_marks: form.passing_marks || 33,
            exam_date: '',
        }
    }
    return addMissingPicks.value[subjectId]
}

const addMissingTickedCount = computed(() =>
    Object.values(addMissingPicks.value).filter(p => p.ticked).length
)

function submitAddMissing() {
    if (!props.exam?.id) return
    const payload = {
        subjects: addMissingCandidates.value
            .filter(s => addMissingPicks.value[s.id]?.ticked)
            .map(s => ({
                subject_id: s.id,
                school_class_id: Number(addMissingClassId.value),
                total_marks: Number(addMissingPicks.value[s.id].total_marks) || 100,
                passing_marks: Number(addMissingPicks.value[s.id].passing_marks) || 33,
                exam_date: addMissingPicks.value[s.id].exam_date || null,
            })),
    }
    if (!payload.subjects.length) return
    addMissingBusy.value = true
    router.post(route('exams.add-missing-subjects', props.exam.id), payload, {
        preserveScroll: true,
        onSuccess: () => {
            closeAddMissingModal()
            // Stay on the edit form — Inertia re-renders with the new exam
            // data automatically via the back() redirect from the controller.
        },
        onFinish: () => { addMissingBusy.value = false },
    })
}

// Helpers
const subjectMap = computed(() => Object.fromEntries((props.subjects || []).map(s => [s.id, s])))
const classMap = computed(() => Object.fromEntries((props.classes || []).map(c => [c.id, c])))

// Group subjects by class for cleaner display
const subjectsByClass = computed(() => {
    const map = new Map()
    for (const row of form.subjects) {
        const cls = classMap.value[row.school_class_id]
        if (!cls) continue
        if (!map.has(cls.id)) map.set(cls.id, { class: cls, rows: [] })
        map.get(cls.id).rows.push(row)
    }
    return Array.from(map.values())
})

// ════════════════════════════════════════════════════════════════
// STEP 4: RULE BUILDER
// ════════════════════════════════════════════════════════════════
function toggleMandatorySubject(subjectId) {
    const arr = form.passing_rules.mandatory_subjects ?? []
    const idx = arr.indexOf(subjectId)
    if (idx > -1) arr.splice(idx, 1)
    else arr.push(subjectId)
    form.passing_rules.mandatory_subjects = [...arr]
}

// "Subjects in use" = all unique subject IDs across the per-class mapping (Step 3)
const subjectsInUse = computed(() => {
    const ids = new Set(form.subjects.map(s => Number(s.subject_id)).filter(Boolean))
    return (props.subjects || []).filter(s => ids.has(s.id))
})

// ════════════════════════════════════════════════════════════════
// STEP 5: LIVE PREVIEW — runs the rules client-side against 3 fake students
// ════════════════════════════════════════════════════════════════
function evaluateStudent(studentMarks /* { [subjectId]: % } */) {
    // Build per-subject rows
    const subjectRows = subjectsInUse.value.map(s => {
        const pct = studentMarks[s.id] ?? 0
        const obtained = (pct / 100) * (form.total_marks || 100)
        const passing = form.passing_marks || 33
        const isAbsent = pct === null
        return {
            id: s.id,
            name: s.name,
            is_main: s.is_main,
            percentage: pct,
            obtained,
            passing,
            is_passed: !isAbsent && obtained >= passing,
            shortBy: !isAbsent && obtained < passing ? +(passing - obtained).toFixed(1) : 0,
        }
    })

    if (subjectRows.length === 0) return { passed: null, reasons: ['No subjects mapped yet'] }

    const failed = subjectRows.filter(r => !r.is_passed)
    const passed = subjectRows.filter(r => r.is_passed)
    const overallPct = subjectRows.reduce((s, r) => s + r.percentage, 0) / subjectRows.length

    // Apply grace marks if configured
    const graceMarks = form.grace_marks || 0
    const graceMax = form.grace_marks_max_subjects || 0
    let gracePromotedSubjects = []
    if (graceMarks > 0 && graceMax > 0 && failed.length > 0 && failed.length <= graceMax) {
        const eligible = failed.filter(r => r.shortBy <= graceMarks)
        if (eligible.length === failed.length) {
            gracePromotedSubjects = eligible
            failed.length = 0 // virtually pass them
        }
    }

    const reasons = []

    // Custom: all subjects must pass
    if (form.all_subjects_must_pass && failed.length > 0) {
        reasons.push(`Failed ${failed.length} subject(s); rule requires all subjects passed`)
        return { passed: false, reasons, subjectRows, overallPct, gracePromotedSubjects }
    }

    // Main subjects must pass
    if (form.main_subjects_must_pass) {
        const mainFailed = failed.filter(r => r.is_main)
        if (mainFailed.length > 0) {
            reasons.push(`Failed main subject(s): ${mainFailed.map(r => r.name).join(', ')}`)
            return { passed: false, reasons, subjectRows, overallPct, gracePromotedSubjects }
        }
    }

    // Mandatory subjects
    const mandatory = form.passing_rules?.mandatory_subjects || []
    if (mandatory.length > 0) {
        const mandatoryFailed = subjectRows.filter(r => mandatory.includes(r.id) && !r.is_passed)
        if (mandatoryFailed.length > 0) {
            reasons.push(`Failed mandatory subject(s): ${mandatoryFailed.map(r => r.name).join(', ')}`)
            return { passed: false, reasons, subjectRows, overallPct, gracePromotedSubjects }
        }
    }

    // Min subjects to pass
    if (form.min_subjects_to_pass && passed.length < form.min_subjects_to_pass) {
        reasons.push(`Passed only ${passed.length}; rule requires at least ${form.min_subjects_to_pass}`)
        return { passed: false, reasons, subjectRows, overallPct, gracePromotedSubjects }
    }

    if (failed.length > 0) {
        reasons.push(`Failed ${failed.length} subject(s) but no rule prevents promotion`)
    } else if (gracePromotedSubjects.length > 0) {
        reasons.push(`Promoted with grace marks on ${gracePromotedSubjects.length} subject(s)`)
    } else {
        reasons.push('Passed all subjects')
    }
    return { passed: true, reasons, subjectRows, overallPct, gracePromotedSubjects }
}

const sampleStudents = computed(() => {
    if (subjectsInUse.value.length === 0) return []
    const subs = subjectsInUse.value
    // Top scorer: ~92% in all
    const top = Object.fromEntries(subs.map(s => [s.id, 90 + Math.floor(Math.random() * 8)]))
    // Borderline: 33-40% in all
    const border = Object.fromEntries(subs.map(s => [s.id, 33 + Math.floor(Math.random() * 8)]))
    // Failing-one: most subjects 50%, but one (math-like or first) at 28%
    const oneFail = Object.fromEntries(subs.map(s => [s.id, 50 + Math.floor(Math.random() * 15)]))
    if (subs[0]) oneFail[subs[0].id] = 28
    return [
        { name: 'Top Scorer', sublabel: 'High performer · ~92% avg', tone: 'emerald', marks: top, eval: evaluateStudent(top) },
        { name: 'Borderline', sublabel: 'Just at the passing line', tone: 'amber', marks: border, eval: evaluateStudent(border) },
        { name: 'Failing One Subject', sublabel: 'Tests grace marks logic', tone: 'rose', marks: oneFail, eval: evaluateStudent(oneFail) },
    ]
})

// ════════════════════════════════════════════════════════════════
// SUBMIT
// ════════════════════════════════════════════════════════════════
function submit() {
    if (isEdit) {
        form.put(route('exams.update', props.exam.id))
    } else {
        form.post(route('exams.store'))
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Exam' : 'Create Exam'" />
    <AppLayout :breadcrumbs="[
        { label: 'Exams', href: route('exams.index') },
        { label: isEdit ? `Edit ${exam.name}` : 'Create Exam' },
    ]">
        <div class="max-w-5xl mx-auto space-y-5">
            <!-- ═══════════ HEADER + STEPPER (unified hero card) ═══════════ -->
            <section class="surface overflow-hidden">
                <div class="px-5 sm:px-6 pt-5 pb-4 flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <div class="text-[11px] uppercase tracking-[0.2em] font-bold text-primary/80 mb-1.5">
                            {{ isEdit ? 'Edit Exam' : 'Create Exam' }}
                        </div>
                        <h1 class="text-2xl font-extrabold tracking-tight">
                            {{ isEdit ? exam?.name : 'New Exam · Setup Wizard' }}
                        </h1>
                        <p class="text-sm text-base-content/55 mt-1">
                            Configure marks, schools, subjects and passing rules in five guided steps.
                        </p>
                    </div>
                    <Link :href="route('exams.index')" class="btn btn-ghost btn-sm gap-1.5">
                        <ArrowLeftIcon class="w-4 h-4" /> Cancel
                    </Link>
                </div>
                <ol class="grid grid-cols-5 gap-1.5 px-3 sm:px-4 pb-4 pt-1 border-t border-base-200/60">
                    <li v-for="s in steps" :key="s.num" class="relative">
                        <button @click="gotoStep(s.num)" type="button"
                            class="w-full text-left p-2.5 rounded-xl transition-all border"
                            :class="step === s.num
                                ? 'bg-primary/10 border-primary/30 ring-1 ring-primary/20'
                                : step > s.num
                                    ? 'bg-emerald-500/10 border-emerald-500/25 hover:bg-emerald-500/15 dark:bg-emerald-500/15'
                                    : 'border-transparent hover:bg-base-200/50'">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                                    :class="step === s.num
                                        ? 'bg-primary text-primary-content shadow-sm'
                                        : step > s.num
                                            ? 'bg-emerald-500 text-white shadow-sm'
                                            : 'bg-base-300 text-base-content/55'">
                                    <CheckIcon v-if="step > s.num" class="w-4 h-4" />
                                    <span v-else>{{ s.num }}</span>
                                </div>
                                <div class="hidden sm:block min-w-0">
                                    <div class="text-xs font-bold truncate">{{ s.label }}</div>
                                    <div class="text-[10px] text-base-content/45 truncate">{{ s.desc }}</div>
                                </div>
                            </div>
                        </button>
                    </li>
                </ol>
            </section>

            <form @submit.prevent="submit">

            <!-- ═══════════ STEP 1: BASICS + TEMPLATES ═══════════ -->
            <div v-show="step === 1" class="space-y-5">
                <!-- Templates -->
                <section class="surface surface-accent-left accent-amber">
                    <header class="surface-header">
                        <h3>
                            <span class="w-7 h-7 rounded-lg bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                <BookmarkIcon class="w-4 h-4" />
                            </span>
                            Quick Start · Pick a Template
                        </h3>
                        <span class="text-[10px] text-base-content/45">Optional · pre-fills sensible defaults</span>
                    </header>
                    <div class="surface-body">
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            <button v-for="p in presets" :key="p.key" type="button"
                                @click="applyPreset(p)"
                                class="text-left p-4 rounded-xl border-2 transition-all bg-base-100"
                                :class="activePreset === p.key
                                    ? 'border-primary bg-primary/5 shadow-md'
                                    : 'border-base-200 hover:border-primary/40 hover:bg-base-200/40'">
                                <div class="flex items-center gap-2 mb-2">
                                    <component :is="p.icon" class="w-5 h-5 text-primary" />
                                    <span class="font-bold text-sm">{{ p.label }}</span>
                                    <CheckIcon v-if="activePreset === p.key" class="w-4 h-4 text-primary ml-auto" />
                                </div>
                                <p class="text-[11px] text-base-content/55 leading-relaxed">{{ p.desc }}</p>
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Basics form — modern card with header + form-grid -->
                <section class="surface">
                    <header class="surface-header">
                        <h3>
                            <span class="w-7 h-7 rounded-lg bg-teal-500/15 text-teal-600 dark:text-teal-400 flex items-center justify-center">
                                <ClipboardDocumentListIcon class="w-4 h-4" />
                            </span>
                            Exam Details
                        </h3>
                    </header>
                    <div class="surface-body space-y-5">
                        <div>
                            <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Exam Name *</label>
                            <input v-model="form.name" type="text" required
                                placeholder="e.g. First Term Examination 2025-26"
                                class="input input-bordered w-full mt-1.5" />
                            <p v-if="form.errors.name" class="text-xs text-error mt-1.5">{{ form.errors.name }}</p>
                        </div>
                        <div class="form-grid">
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Exam Type *</label>
                                <div class="mt-1.5">
                                    <SearchableSelect v-model="form.exam_type_id"
                                        :options="(examTypes || []).map(t => ({ value: t.id, label: t.name }))"
                                        placeholder="Select type..." />
                                </div>
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Academic Session *</label>
                                <div class="mt-1.5">
                                    <SearchableSelect v-model="form.academic_session_id"
                                        :options="(sessions || []).map(s => ({ value: s.id, label: s.name }))"
                                        placeholder="Select session..." />
                                </div>
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Start Date *</label>
                                <input v-model="form.start_date" type="date" required
                                    class="input input-bordered w-full mt-1.5" />
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">End Date *</label>
                                <input v-model="form.end_date" type="date" required
                                    :min="form.start_date || undefined"
                                    class="input input-bordered w-full mt-1.5" />
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Marks Entry Deadline</label>
                                <input v-model="form.marks_entry_deadline" type="date"
                                    :min="form.end_date || form.start_date || undefined"
                                    class="input input-bordered w-full mt-1.5" />
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Grading Scale</label>
                                <div class="mt-1.5">
                                    <SearchableSelect v-model="form.grading_scale_id"
                                        :options="[{ value: '', label: 'Default' }, ...(gradingScales || []).map(g => ({ value: g.id, label: g.name }))]"
                                        placeholder="Default" />
                                </div>
                            </div>
                            <!-- Optional: pick a teacher to designate as Exam Controller for this exam.
                                 Their name will appear on the date sheet, admit cards, etc. as the
                                 Examination Officer. Leave blank to fall back to the school's default. -->
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">
                                    Exam Controller
                                    <span class="text-base-content/40 normal-case font-medium">· optional</span>
                                </label>
                                <div class="mt-1.5">
                                    <SearchableSelect v-model="form.exam_controller_id"
                                        :options="[{ value: '', label: '— None —' }, ...(teachers || []).map(t => ({ value: t.id, label: t.name }))]"
                                        placeholder="— None —"
                                        clearable />
                                </div>
                                <p class="text-[10px] text-base-content/45 mt-1">
                                    Pick a teacher to sign exam paperwork (date sheet, admit cards, etc.).
                                </p>
                            </div>
                        </div>

                        <div>
                            <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Description</label>
                            <textarea v-model="form.description" rows="2" placeholder="Optional notes about this exam..."
                                class="textarea textarea-bordered w-full mt-1.5"></textarea>
                        </div>

                        <!-- ─── Term + final-year combination ─── -->
                        <div class="rounded-xl border-2 border-dashed border-violet-500/30 bg-violet-500/5 p-4 space-y-3">
                            <p class="text-xs font-bold uppercase tracking-wider text-violet-700 dark:text-violet-300 flex items-center gap-1.5">
                                <ClipboardDocumentListIcon class="w-3.5 h-3.5" />
                                Term &amp; Final-Year Combination
                                <span class="text-base-content/40 normal-case font-medium">· optional</span>
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Which term?</label>
                                    <select v-model="form.term" class="select select-bordered w-full mt-1.5">
                                        <option value="">— Not part of a term cycle —</option>
                                        <option value="first">First Term</option>
                                        <option value="second">Second Term</option>
                                        <option value="final">Final Term</option>
                                    </select>
                                    <p class="text-[10px] text-base-content/45 mt-1">
                                        Tag this exam so the year-end aggregator knows where it sits.
                                    </p>
                                </div>
                                <div v-if="form.term === 'final'" class="flex items-center">
                                    <label class="flex items-start gap-2.5 p-3 rounded-xl border-2 transition-colors cursor-pointer w-full"
                                        :class="form.combine_previous_terms ? 'border-violet-500 bg-violet-500/10' : 'border-base-200 hover:bg-base-200/40'">
                                        <input type="checkbox" v-model="form.combine_previous_terms"
                                            class="checkbox checkbox-sm checkbox-primary mt-0.5" />
                                        <div class="text-xs">
                                            <p class="font-bold">Combine 1st + 2nd term into final result</p>
                                            <p class="text-base-content/55 mt-0.5">Year-end result = weighted aggregate of all three terms.</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div v-if="form.term === 'final' && form.combine_previous_terms"
                                class="rounded-lg border border-violet-500/30 bg-base-100 p-3 space-y-2">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-base-content/65 flex items-center justify-between">
                                    <span>Term weights (must total 100)</span>
                                    <span class="font-mono tabular-nums" :class="termWeightSum === 100 ? 'text-emerald-600' : 'text-rose-600'">
                                        sum = {{ termWeightSum }}
                                    </span>
                                </p>
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <label class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">1st Term %</label>
                                        <input type="number" min="0" max="100" v-model.number="form.term_weights.first"
                                            class="input input-bordered input-sm w-full mt-1" />
                                    </div>
                                    <div>
                                        <label class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">2nd Term %</label>
                                        <input type="number" min="0" max="100" v-model.number="form.term_weights.second"
                                            class="input input-bordered input-sm w-full mt-1" />
                                    </div>
                                    <div>
                                        <label class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Final %</label>
                                        <input type="number" min="0" max="100" v-model.number="form.term_weights.final"
                                            class="input input-bordered input-sm w-full mt-1" />
                                    </div>
                                </div>
                                <p class="text-[11px] text-base-content/55 leading-snug">
                                    Result generation will pull each student's marks for the 1st-term and 2nd-term exams
                                    of the same session, then blend them with this final-term's marks using these
                                    percentages. The combined number is what appears on the final result card.
                                </p>
                                <p v-if="form.errors.term_weights" class="text-xs text-error">{{ form.errors.term_weights }}</p>
                                <p v-if="form.errors.combine_previous_terms" class="text-xs text-error">{{ form.errors.combine_previous_terms }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ═══════════ STEP 2: SCOPE ═══════════ -->
            <div v-show="step === 2" class="space-y-5">
                <section class="surface">
                    <header class="surface-header">
                        <h3>
                            <span class="w-7 h-7 rounded-lg bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                                <BuildingOfficeIcon class="w-4 h-4" />
                            </span>
                            Which schools will run this exam?
                        </h3>
                    </header>
                    <div class="surface-body space-y-4">
                        <!-- Principal info banner -->
                        <div v-if="!isSuperAdmin" class="rounded-xl bg-sky-500/10 border border-sky-500/25 p-3 flex items-start gap-2 text-sm">
                            <InformationCircleIcon class="w-5 h-5 text-sky-600 dark:text-sky-400 flex-shrink-0 mt-0.5" />
                            <div>
                                <p class="font-semibold text-sky-900 dark:text-sky-200">School-scoped automatically</p>
                                <p class="text-xs text-sky-800/85 dark:text-sky-200/75 mt-0.5">As Principal, this exam will only apply to your own school. The DDO can create district-wide exams.</p>
                            </div>
                        </div>

                        <!-- Apply-to-all toggle (super-admin only) -->
                        <label v-if="isSuperAdmin" class="flex items-start gap-3 p-4 rounded-xl border-2 transition-colors cursor-pointer"
                            :class="form.apply_to_all_schools ? 'border-primary bg-primary/5' : 'border-base-200 hover:bg-base-200/40'">
                            <input type="checkbox" v-model="form.apply_to_all_schools"
                                class="checkbox checkbox-primary mt-0.5" />
                            <div class="flex-1">
                                <div class="font-semibold">Apply to ALL active schools</div>
                                <p class="text-xs text-base-content/55 mt-0.5">Every school in the district will get this exam in their portal.</p>
                            </div>
                        </label>

                        <!-- Specific schools picker (DDO only when not all-schools) -->
                        <div v-if="isSuperAdmin && !form.apply_to_all_schools">
                            <p class="section-eyebrow mb-2">Pick specific schools</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <label v-for="school in schools" :key="school.id"
                                    class="flex items-center gap-2 p-3 rounded-xl border transition-colors cursor-pointer"
                                    :class="form.selected_school_ids.includes(school.id)
                                        ? 'border-primary bg-primary/5'
                                        : 'border-base-200 hover:bg-base-200/40'">
                                    <input type="checkbox" :value="school.id" v-model="form.selected_school_ids"
                                        class="checkbox checkbox-sm checkbox-primary" />
                                    <span class="text-sm font-medium">{{ school.name }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ═══════════ STEP 3: SUBJECTS (with bulk apply) ═══════════ -->
            <div v-show="step === 3" class="space-y-5">
                <!-- Bulk-apply card -->
                <section class="surface surface-accent-left" style="--bc-accent: var(--p);">
                    <header class="surface-header">
                        <h3>
                            <span class="w-7 h-7 rounded-lg bg-primary/15 text-primary flex items-center justify-center">
                                <BoltIcon class="w-4 h-4" />
                            </span>
                            Bulk Apply · Map subjects to multiple classes
                        </h3>
                    </header>
                    <div class="surface-body space-y-4">
                        <p class="text-xs text-base-content/65">
                            Pick the classes, pick the subjects, set their marks once — the system fans out one row per (class, subject) pair below.
                        </p>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="section-eyebrow !mb-0">
                                        Classes
                                        <span v-if="availableClasses.length" class="text-base-content/45 normal-case font-medium">
                                            · {{ availableClasses.length }} available
                                        </span>
                                    </label>
                                    <button v-if="availableClasses.length" type="button" @click="toggleAllBulkClasses"
                                        class="text-[10px] font-bold text-primary hover:underline">
                                        {{ bulkSelectedClasses.length === availableClasses.length ? 'Uncheck all' : 'Check all' }}
                                    </button>
                                </div>
                                <div class="max-h-44 overflow-y-auto rounded-xl border border-base-200 p-2 bg-base-200/30">
                                    <p v-if="!availableClasses.length" class="text-xs text-base-content/45 p-2">
                                        Pick at least one school in Step 2 first.
                                    </p>
                                    <label v-for="cls in availableClasses" :key="cls.id"
                                        class="flex items-center gap-2 p-1.5 rounded-md hover:bg-base-100 cursor-pointer text-sm">
                                        <input type="checkbox" :value="cls.id" v-model="bulkSelectedClasses"
                                            class="checkbox checkbox-xs checkbox-primary" />
                                        {{ cls.name }}
                                    </label>
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="section-eyebrow !mb-0">
                                        Subjects
                                        <span v-if="bulkSelectedClasses.length" class="text-base-content/45 normal-case font-medium">
                                            · {{ availableSubjects.length }} assigned to picked class{{ bulkSelectedClasses.length === 1 ? '' : 'es' }}
                                        </span>
                                    </label>
                                    <button v-if="availableSubjects.length" type="button" @click="toggleAllBulkSubjects"
                                        class="text-[10px] font-bold text-primary hover:underline">
                                        {{ bulkSelectedSubjects.length === availableSubjects.length ? 'Uncheck all' : 'Check all' }}
                                    </button>
                                </div>
                                <div class="max-h-44 overflow-y-auto rounded-xl border border-base-200 p-2 bg-base-200/30">
                                    <p v-if="!bulkSelectedClasses.length" class="text-xs text-base-content/45 p-2">
                                        Pick at least one class first — its assigned subjects will appear here.
                                    </p>
                                    <p v-else-if="!availableSubjects.length" class="text-xs text-warning p-2">
                                        No subjects are assigned to the selected class{{ bulkSelectedClasses.length === 1 ? '' : 'es' }} yet.
                                        Add them under <strong>Classes → Edit → Subjects</strong> first.
                                    </p>
                                    <label v-for="s in availableSubjects" :key="s.id"
                                        class="flex items-center gap-2 p-1.5 rounded-md hover:bg-base-100 cursor-pointer text-sm">
                                        <input type="checkbox" :value="s.id" v-model="bulkSelectedSubjects"
                                            class="checkbox checkbox-xs checkbox-primary" />
                                        <span>{{ s.name }}</span>
                                        <span v-if="s.is_main" class="badge badge-xs badge-primary ml-auto">Main</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Default marks for every fanned-out row. Per-subject
                             overrides below let admin tweak only the odd ones
                             (e.g. Computer = 50/17 while everyone else = 100/33). -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-xl border border-base-200 bg-base-200/40 p-3">
                            <div>
                                <label class="text-[11px] uppercase tracking-wider font-bold text-base-content/55">
                                    Default total marks
                                </label>
                                <input v-model.number="bulkTotalMarks" type="number" min="1"
                                    class="input input-bordered input-sm rounded-lg w-full mt-1 font-mono" />
                            </div>
                            <div>
                                <label class="text-[11px] uppercase tracking-wider font-bold text-base-content/55">
                                    Default passing marks
                                </label>
                                <input v-model.number="bulkPassingMarks" type="number" min="0"
                                    class="input input-bordered input-sm rounded-lg w-full mt-1 font-mono" />
                            </div>
                        </div>

                        <!-- Per-subject override toggle -->
                        <div v-if="bulkSelectedSubjects.length">
                            <button type="button" @click="showSubjectOverrides = !showSubjectOverrides"
                                class="btn btn-ghost btn-xs gap-1 text-primary">
                                <Cog6ToothIcon class="w-3.5 h-3.5" />
                                {{ showSubjectOverrides ? 'Hide' : 'Override marks for specific subjects' }}
                                <span class="badge badge-xs badge-ghost ml-1" v-if="Object.keys(bulkSubjectOverrides).length">
                                    {{ Object.keys(bulkSubjectOverrides).length }} custom
                                </span>
                            </button>

                            <div v-if="showSubjectOverrides"
                                class="mt-2 rounded-xl border border-base-200 bg-base-100 overflow-hidden">
                                <table class="w-full text-xs">
                                    <thead class="bg-base-200/50 text-[10px] uppercase tracking-wider text-base-content/55">
                                        <tr>
                                            <th class="text-left px-3 py-2 font-bold">Subject</th>
                                            <th class="text-right px-3 py-2 font-bold w-24">Total</th>
                                            <th class="text-right px-3 py-2 font-bold w-24">Pass</th>
                                            <th class="px-2 py-2 w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-base-200">
                                        <tr v-for="sid in bulkSelectedSubjects" :key="sid">
                                            <td class="px-3 py-1.5 truncate">
                                                {{ (subjects.find(s => Number(s.id) === Number(sid)) || {}).name }}
                                            </td>
                                            <td class="px-3 py-1.5">
                                                <input type="number" min="1"
                                                    :placeholder="bulkTotalMarks"
                                                    :value="bulkSubjectOverrides[sid]?.total ?? ''"
                                                    @input="ensureOverride(sid).total = $event.target.value"
                                                    class="input input-bordered input-xs rounded w-full text-right font-mono" />
                                            </td>
                                            <td class="px-3 py-1.5">
                                                <input type="number" min="0"
                                                    :placeholder="bulkPassingMarks"
                                                    :value="bulkSubjectOverrides[sid]?.passing ?? ''"
                                                    @input="ensureOverride(sid).passing = $event.target.value"
                                                    class="input input-bordered input-xs rounded w-full text-right font-mono" />
                                            </td>
                                            <td class="px-2 py-1.5 text-right">
                                                <button v-if="bulkSubjectOverrides[sid]" type="button"
                                                    @click="clearOverride(sid)"
                                                    class="btn btn-ghost btn-xs btn-square text-base-content/55"
                                                    title="Reset to default">
                                                    <XCircleIcon class="w-3.5 h-3.5" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p class="px-3 py-2 text-[10px] text-base-content/55 bg-base-200/30">
                                    Leave a row blank to use the defaults above. Set a row to
                                    e.g. 50 / 17 for subjects like Computer or Art that carry less weight.
                                </p>
                            </div>
                        </div>

                        <button type="button" @click="applyBulkSubjects"
                            :disabled="!bulkSelectedClasses.length || !bulkSelectedSubjects.length"
                            class="btn btn-primary btn-sm w-full sm:w-auto gap-1.5">
                            <BoltIcon class="w-4 h-4" />
                            Apply to {{ bulkSelectedClasses.length * bulkSelectedSubjects.length || 0 }} (class × subject) pair{{ (bulkSelectedClasses.length * bulkSelectedSubjects.length) === 1 ? '' : 's' }}
                        </button>
                    </div>
                </section>

                <!-- Per-class subject rows -->
                <section class="surface overflow-hidden">
                    <header class="surface-header">
                        <h3>
                            <span class="w-7 h-7 rounded-lg bg-violet-500/15 text-violet-600 dark:text-violet-400 flex items-center justify-center">
                                <AcademicCapIcon class="w-4 h-4" />
                            </span>
                            Per-class subjects
                            <span class="badge badge-sm badge-ghost ml-1">{{ form.subjects.length }}</span>
                        </h3>
                        <p class="text-[10px] text-base-content/55 ml-auto sm:ml-0">
                            Paper dates &amp; times are set on the
                            <span class="font-semibold">Scheduling → Date Sheet</span> page after saving.
                        </p>
                        <div class="flex gap-2 flex-wrap">
                            <!-- Edit-only quick path: add a subject that was
                                 missed at creation, without touching anything
                                 else on the exam. -->
                            <button v-if="exam?.id" type="button" @click="openAddMissingModal"
                                class="btn btn-primary btn-xs gap-1">
                                <PlusIcon class="w-3.5 h-3.5" /> Add Missing Subject
                            </button>
                            <button type="button" @click="addSubjectRow" class="btn btn-ghost btn-xs gap-1">
                                <PlusIcon class="w-3.5 h-3.5" /> Add row
                            </button>
                            <button v-if="form.subjects.length > 0" type="button" @click="clearAllSubjects"
                                class="btn btn-ghost btn-xs text-rose-600 gap-1">
                                <TrashIcon class="w-3.5 h-3.5" /> Clear all
                            </button>
                        </div>
                    </header>

                    <div v-if="form.subjects.length === 0" class="p-10 text-center">
                        <AcademicCapIcon class="w-10 h-10 text-base-content/30 mx-auto mb-2" />
                        <p class="text-sm font-medium text-base-content/65">No subjects yet</p>
                        <p class="text-xs text-base-content/45 mt-1">Use the bulk apply above to map subjects to classes.</p>
                    </div>

                    <!-- Edit-mode safety notice: surfaces when at least one row
                         has marks entered. Total / passing are editable so the
                         admin can correct mistakes, with results recalculating
                         server-side; the subject pair itself can't be moved or
                         removed without going through Cleanup / a fresh exam. -->
                    <div v-if="form.subjects.some(s => s.marks_count > 0)"
                         class="mx-4 mt-3 rounded-xl bg-amber-500/10 border border-amber-500/30 p-3 flex items-start gap-2 text-xs">
                        <ExclamationTriangleIcon class="w-4 h-4 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                        <p class="text-amber-900 dark:text-amber-200 leading-relaxed">
                            <span class="font-bold">Some subjects already have marks entered.</span>
                            You can still correct their <span class="font-semibold">total or passing marks</span> —
                            students' obtained marks stay the same, and the system
                            will recalculate percentages, grades, and pass/fail status
                            for every affected result automatically. The subject &amp; class
                            pair can't be moved or removed without first clearing marks.
                        </p>
                    </div>

                    <!-- Bulk-edit toolbar — tick rows, set marks, apply -->
                    <div v-if="form.subjects.length" class="px-4 py-2.5 bg-violet-500/5 border-y border-violet-500/20 flex flex-wrap items-center gap-2 text-xs">
                        <span class="text-[10px] uppercase tracking-wider font-bold text-violet-700 dark:text-violet-300">
                            Bulk edit:
                        </span>
                        <span class="font-mono tabular-nums">
                            {{ editSelected.size }} selected
                        </span>

                        <div class="flex items-center gap-1">
                            <span class="text-[10px] text-base-content/55">Quick pick:</span>
                            <div class="w-40">
                                <SearchableSelect :model-value="''"
                                    :options="[{ value: '', label: '— By subject —' }, ...distinctSubjectsInRows.map(s => ({ value: s.id, label: s.name }))]"
                                    placeholder="— By subject —" size="xs"
                                    @change="(v) => v && selectBySubject(v)" />
                            </div>
                            <div class="w-40">
                                <SearchableSelect :model-value="''"
                                    :options="[{ value: '', label: '— By class —' }, ...distinctClassesInRows.map(c => ({ value: c.id, label: c.name }))]"
                                    placeholder="— By class —" size="xs"
                                    @change="(v) => v && selectByClass(v)" />
                            </div>
                        </div>

                        <div class="flex items-center gap-1 ml-auto">
                            <input v-model.number="editTotal" type="number" min="1" placeholder="Total"
                                class="input input-bordered input-xs rounded-md w-20 text-right font-mono" />
                            <span class="text-base-content/40">/</span>
                            <input v-model.number="editPass" type="number" min="0" placeholder="Pass"
                                class="input input-bordered input-xs rounded-md w-20 text-right font-mono" />
                            <button type="button" @click="applyEditMarks"
                                :disabled="!editSelected.size || (editTotal == null && editPass == null)"
                                class="btn btn-primary btn-xs gap-1">
                                Apply
                            </button>
                            <button type="button" @click="removeSelectedRows"
                                :disabled="!editSelected.size"
                                class="btn btn-ghost btn-xs gap-1 text-rose-600"
                                :title="'Remove ' + editSelected.size + ' selected row(s)'">
                                <TrashIcon class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    <div v-if="form.subjects.length" class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-base-200/40 text-[11px] uppercase tracking-wider text-base-content/55">
                                <tr>
                                    <th class="px-3 py-3 w-8">
                                        <input type="checkbox"
                                            :checked="editSelected.size === form.subjects.length"
                                            @change="selectAllRows"
                                            class="checkbox checkbox-xs checkbox-primary" />
                                    </th>
                                    <th class="text-left px-4 py-3 font-bold">Class</th>
                                    <th class="text-left px-3 py-3 font-bold">Subject</th>
                                    <th class="text-right px-3 py-3 font-bold w-24">Total</th>
                                    <th class="text-right px-3 py-3 font-bold w-24">Pass</th>
                                    <th class="px-2 py-3 w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-base-200">
                                <tr v-for="(row, i) in form.subjects" :key="i"
                                    :class="[
                                        editSelected.has(i) ? 'bg-violet-500/10' : 'hover:bg-base-200/30',
                                        row.marks_count > 0 ? 'bg-amber-500/5' : '',
                                    ]"
                                    :title="row.marks_count > 0 ? `${row.marks_count} marks already entered. Editing total / passing marks will recalculate every affected result.` : ''">
                                    <td class="px-3 py-2 text-center">
                                        <input type="checkbox" :checked="editSelected.has(i)"
                                            @change="toggleEditRow(i)"
                                            :disabled="row.marks_count > 0"
                                            class="checkbox checkbox-xs checkbox-primary" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <SearchableSelect v-model="row.school_class_id"
                                            :options="availableClasses.map(c => ({ value: c.id, label: c.name }))"
                                            placeholder="—" size="xs"
                                            :disabled="row.marks_count > 0" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-1.5">
                                            <SearchableSelect v-model="row.subject_id"
                                                :options="subjectsForClass(row.school_class_id).map(s => ({ value: s.id, label: s.name }))"
                                                placeholder="—" size="xs"
                                                :disabled="row.marks_count > 0"
                                                class="flex-1" />
                                            <span v-if="row.marks_count > 0"
                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-700 dark:text-amber-300 text-[10px] font-bold tabular-nums whitespace-nowrap"
                                                title="Editing total / passing marks here will recalculate all results for this subject.">
                                                <LockClosedIcon class="w-2.5 h-2.5" />
                                                {{ row.marks_count }} marks
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <!-- Total + passing are now editable even when marks exist;
                                             the controller cascades the change into Mark.total_marks
                                             and re-runs ResultProcessingService for affected sections. -->
                                        <input v-model.number="row.total_marks" type="number" min="1"
                                            class="input input-bordered input-xs rounded-lg w-full text-right font-mono"
                                            :class="row.marks_count > 0 ? 'border-amber-500/40 focus:border-amber-500' : ''" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input v-model.number="row.passing_marks" type="number" min="0"
                                            class="input input-bordered input-xs rounded-lg w-full text-right font-mono"
                                            :class="row.marks_count > 0 ? 'border-amber-500/40 focus:border-amber-500' : ''" />
                                    </td>
                                    <td class="px-2 py-2 text-right">
                                        <button type="button" @click="removeSubjectRow(i)"
                                            :disabled="row.marks_count > 0"
                                            :title="row.marks_count > 0 ? 'Cannot remove — marks already entered' : 'Remove this subject'"
                                            class="btn btn-ghost btn-xs btn-square text-rose-500 disabled:text-base-content/30">
                                            <XCircleIcon class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- ═══════════ STEP 4: RULE BUILDER ═══════════ -->
            <div v-show="step === 4" class="space-y-5">
                <div class="rounded-xl bg-sky-500/10 border border-sky-500/25 p-3 flex items-start gap-2 text-xs">
                    <InformationCircleIcon class="w-4 h-4 text-sky-600 dark:text-sky-400 flex-shrink-0 mt-0.5" />
                    <p class="text-sky-900 dark:text-sky-200">Build custom passing logic. Use the standard switches OR pick mandatory subjects below — combine them as needed. The next step <b>previews</b> outcomes.</p>
                </div>

                <!-- Standard rule switches -->
                <section class="surface">
                    <header class="surface-header">
                        <h3>
                            <span class="w-7 h-7 rounded-lg bg-primary/15 text-primary flex items-center justify-center">
                                <Cog6ToothIcon class="w-4 h-4" />
                            </span>
                            Standard Rules
                        </h3>
                    </header>
                    <div class="surface-body space-y-3">
                        <label class="flex items-start gap-3 p-3 rounded-xl border-2 transition-colors cursor-pointer"
                            :class="form.main_subjects_must_pass ? 'border-primary bg-primary/5' : 'border-base-200 hover:bg-base-200/40'">
                            <input type="checkbox" v-model="form.main_subjects_must_pass"
                                :disabled="form.all_subjects_must_pass"
                                class="checkbox checkbox-primary mt-0.5" />
                            <div>
                                <div class="font-semibold text-sm">Main subjects must pass</div>
                                <p class="text-[11px] text-base-content/55 mt-0.5">Student must score the passing mark in every subject marked as "main" (English, Urdu, Math, Science, etc.).</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-3 rounded-xl border-2 transition-colors cursor-pointer"
                            :class="form.all_subjects_must_pass ? 'border-primary bg-primary/5' : 'border-base-200 hover:bg-base-200/40'">
                            <input type="checkbox" v-model="form.all_subjects_must_pass"
                                @change="form.all_subjects_must_pass && (form.main_subjects_must_pass = false)"
                                class="checkbox checkbox-primary mt-0.5" />
                            <div>
                                <div class="font-semibold text-sm">ALL subjects must pass <span class="badge badge-warning badge-xs ml-1">Strict</span></div>
                                <p class="text-[11px] text-base-content/55 mt-0.5">Even one failed subject = overall fail. Common for board-style exams.</p>
                            </div>
                        </label>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-3 border-t border-base-200">
                            <div>
                                <label class="section-eyebrow">Min subjects to pass</label>
                                <input v-model.number="form.min_subjects_to_pass" type="number" min="0"
                                    placeholder="e.g. 5"
                                    class="input input-bordered input-sm w-full mt-1 font-mono" />
                            </div>
                            <div>
                                <label class="section-eyebrow">Grace marks</label>
                                <input v-model.number="form.grace_marks" type="number" min="0"
                                    placeholder="e.g. 5"
                                    class="input input-bordered input-sm w-full mt-1 font-mono" />
                            </div>
                            <div>
                                <label class="section-eyebrow">Grace max subjects</label>
                                <input v-model.number="form.grace_marks_max_subjects" type="number" min="0"
                                    placeholder="e.g. 2"
                                    class="input input-bordered input-sm w-full mt-1 font-mono" />
                            </div>
                        </div>
                        <p class="text-[11px] text-base-content/55 leading-relaxed">
                            💡 <b>Grace example:</b> "5 grace marks on max 2 subjects" means a student failing up to 2 subjects by ≤5 marks each is auto-promoted.
                        </p>
                    </div>
                </section>

                <!-- Mandatory subjects (custom) -->
                <section class="surface surface-accent-left accent-amber">
                    <header class="surface-header">
                        <h3>
                            <span class="w-7 h-7 rounded-lg bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                <SparklesIcon class="w-4 h-4" />
                            </span>
                            Mandatory Subjects
                        </h3>
                        <span class="text-[10px] text-base-content/45">Custom · Pick specific subjects</span>
                    </header>
                    <div class="surface-body">
                        <p class="text-xs text-base-content/65 mb-3">Even more strict than "main subjects must pass" — these specific subjects are non-negotiable. Pass these or fail overall.</p>

                        <div v-if="subjectsInUse.length === 0" class="text-xs text-base-content/45 p-4 bg-base-200/40 rounded-xl">
                            Map subjects in Step 3 first to choose mandatory ones.
                        </div>
                        <div v-else class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            <button v-for="s in subjectsInUse" :key="s.id" type="button"
                                @click="toggleMandatorySubject(s.id)"
                                class="text-left px-3 py-2 rounded-lg border-2 text-sm font-medium transition-colors"
                                :class="form.passing_rules.mandatory_subjects?.includes(s.id)
                                    ? 'border-amber-500 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                                    : 'border-base-200 hover:bg-base-200/40'">
                                <div class="flex items-center gap-2">
                                    <CheckIcon v-if="form.passing_rules.mandatory_subjects?.includes(s.id)" class="w-4 h-4 text-amber-600" />
                                    {{ s.name }}
                                </div>
                            </button>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ═══════════ STEP 5: LIVE PREVIEW ═══════════ -->
            <div v-show="step === 5" class="space-y-5">
                <div class="rounded-xl bg-emerald-500/10 border border-emerald-500/25 p-3 flex items-start gap-2 text-xs">
                    <BeakerIcon class="w-4 h-4 text-emerald-600 dark:text-emerald-400 flex-shrink-0 mt-0.5" />
                    <p class="text-emerald-900 dark:text-emerald-200">We've simulated 3 sample students against your rules. Adjust Step 4 if any outcome looks wrong.</p>
                </div>

                <div v-if="!subjectsInUse.length" class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-6 text-center">
                    <ExclamationTriangleIcon class="w-10 h-10 text-amber-500 mx-auto mb-2" />
                    <p class="text-sm font-medium text-amber-900 dark:text-amber-200">No subjects mapped yet</p>
                    <p class="text-xs text-amber-800/75 dark:text-amber-200/75 mt-1">Go back to Step 3 to map subjects first.</p>
                </div>

                <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <section v-for="(s, idx) in sampleStudents" :key="idx"
                        class="surface !border-2"
                        :class="s.eval.passed === true
                            ? '!border-emerald-400/50'
                            : s.eval.passed === false
                                ? '!border-rose-400/50'
                                : ''">
                        <div class="surface-body">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <div class="text-[10px] uppercase tracking-wider font-bold"
                                        :class="`text-${s.tone}-600 dark:text-${s.tone}-400`">{{ s.name }}</div>
                                    <p class="text-[11px] text-base-content/55 mt-0.5">{{ s.sublabel }}</p>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold ring-1"
                                    :class="s.eval.passed === true
                                        ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-500/30'
                                        : s.eval.passed === false
                                            ? 'bg-rose-500/15 text-rose-700 dark:text-rose-300 ring-rose-500/30'
                                            : 'bg-base-200 text-base-content/60 ring-base-300'">
                                    <CheckCircleIcon v-if="s.eval.passed === true" class="w-3.5 h-3.5 inline mr-0.5" />
                                    <XCircleIcon v-else-if="s.eval.passed === false" class="w-3.5 h-3.5 inline mr-0.5" />
                                    {{ s.eval.passed === true ? 'PASSES' : s.eval.passed === false ? 'FAILS' : '—' }}
                                </span>
                            </div>

                            <div class="text-xs text-base-content/65 mb-3 space-y-1">
                                <div v-for="reason in s.eval.reasons" :key="reason"
                                    class="flex items-start gap-1.5">
                                    <span class="text-base-content/30 mt-0.5">•</span>
                                    <span>{{ reason }}</span>
                                </div>
                            </div>

                            <div class="space-y-1 pt-3 border-t border-base-200/60">
                                <div v-for="r in s.eval.subjectRows" :key="r.id"
                                    class="flex items-center justify-between text-[11px] py-0.5">
                                    <span class="truncate text-base-content/75 flex items-center gap-1">
                                        {{ r.name }}
                                        <span v-if="r.is_main" class="text-[8px] font-bold bg-primary/15 text-primary px-1 rounded">M</span>
                                        <span v-if="form.passing_rules.mandatory_subjects?.includes(r.id)" class="text-[8px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-300 px-1 rounded">REQ</span>
                                    </span>
                                    <span class="font-mono font-bold tabular-nums"
                                        :class="r.is_passed ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                        {{ r.percentage }}%
                                    </span>
                                </div>
                            </div>

                            <div class="mt-3 pt-3 border-t border-base-200/60 flex justify-between text-xs font-medium">
                                <span class="text-base-content/55">Average</span>
                                <span class="font-mono font-bold tabular-nums">{{ s.eval.overallPct?.toFixed(1) }}%</span>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Final summary -->
                <section class="surface">
                    <header class="surface-header">
                        <h3>
                            <span class="w-7 h-7 rounded-lg bg-primary/15 text-primary flex items-center justify-center">
                                <CheckCircleIcon class="w-4 h-4" />
                            </span>
                            Configuration Summary
                        </h3>
                    </header>
                    <div class="surface-body space-y-4">
                        <!-- Per-subject marks (what teachers actually enter) -->
                        <div>
                            <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55 mb-2">
                                Per-subject marks
                                <span v-if="form.subjects.length && !subjectsMarksUniform"
                                    class="ml-1 text-amber-700 dark:text-amber-400 normal-case font-medium">
                                    · not uniform — some subjects use different marks
                                </span>
                            </p>
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                                <div>
                                    <dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Total per subject</dt>
                                    <dd class="font-mono font-bold mt-0.5">{{ subjectTotalsTypical ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Pass mark per subject</dt>
                                    <dd class="font-mono font-bold mt-0.5">{{ subjectPassingTypical ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Pass %</dt>
                                    <dd class="font-mono font-bold mt-0.5">
                                        {{ subjectPassingPctTypical != null ? subjectPassingPctTypical + '%' : '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Subjects mapped</dt>
                                    <dd class="font-mono font-bold mt-0.5">{{ form.subjects.length }}</dd>
                                </div>
                            </div>
                        </div>

                        <!-- Per-class totals — what each class will actually be graded on -->
                        <div class="pt-3 border-t border-base-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">
                                    Per-class totals
                                </p>
                                <p class="text-[10px] text-base-content/45">
                                    Each class is graded out of its own subject total — not the sum across all classes.
                                </p>
                            </div>
                            <div v-if="!subjectsByClassBreakdown.length" class="text-xs text-base-content/45 italic px-2 py-3">
                                Map subjects to classes in Step 3 to see this.
                            </div>
                            <div v-else class="rounded-xl border border-base-200 overflow-hidden">
                                <table class="w-full text-xs">
                                    <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider text-base-content/55">
                                        <tr>
                                            <th class="text-left px-3 py-2 font-bold">Class</th>
                                            <th class="text-right px-3 py-2 font-bold">Subjects</th>
                                            <th class="text-right px-3 py-2 font-bold">Total marks</th>
                                            <th class="text-right px-3 py-2 font-bold">Passing</th>
                                            <th class="text-right px-3 py-2 font-bold">Pass %</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-base-200">
                                        <tr v-for="row in subjectsByClassBreakdown" :key="row.id" class="hover:bg-base-200/30">
                                            <td class="px-3 py-2 font-semibold">{{ row.name }}</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ row.subjects }}</td>
                                            <td class="px-3 py-2 text-right font-mono font-bold">{{ row.total }}</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ row.passing }}</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ row.percentage }}%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="grid grid-cols-2 lg:grid-cols-2 gap-3 text-sm mt-3">
                                <div>
                                    <dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Grace</dt>
                                    <dd class="font-mono font-bold mt-0.5">{{ form.grace_marks || 0 }} on max {{ form.grace_marks_max_subjects || 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Schools</dt>
                                    <dd class="font-bold mt-0.5">{{ form.apply_to_all_schools ? 'All schools' : `${form.selected_school_ids.length} selected` }}</dd>
                                </div>
                            </div>
                        </div>

                        <!-- Rules / behaviour -->
                        <div class="pt-3 border-t border-base-200">
                            <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55 mb-2">
                                Passing rules &amp; calculation
                            </p>
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                                <div>
                                    <dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Position by</dt>
                                    <dd class="font-bold mt-0.5 capitalize">{{ form.position_calculation }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Strict mode</dt>
                                    <dd class="font-bold mt-0.5">
                                        <span v-if="form.all_subjects_must_pass" class="text-rose-700">All must pass</span>
                                        <span v-else-if="form.main_subjects_must_pass" class="text-amber-700">Main must pass</span>
                                        <span v-else class="text-base-content/55">No</span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Mandatory subjects</dt>
                                    <dd class="font-bold mt-0.5">{{ form.passing_rules?.mandatory_subjects?.length || 0 }}</dd>
                                </div>
                                <div v-if="form.term">
                                    <dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Term</dt>
                                    <dd class="font-bold mt-0.5 capitalize">
                                        {{ form.term }}<span v-if="form.term === 'final' && form.combine_previous_terms"> · combined</span>
                                    </dd>
                                </div>
                            </div>
                        </div>

                        <p class="text-[11px] text-base-content/55 leading-snug pt-2 border-t border-base-200">
                            💡 The result engine grades each student <strong>per subject</strong> — a student passes a
                            subject when their marks ≥ that subject's pass mark. Overall percentage =
                            <code class="text-[10px] bg-base-200 px-1 rounded">total obtained / sum of all subject totals × 100</code>.
                        </p>
                    </div>
                </section>
            </div>

            <!-- ═══════════ NAV BAR ═══════════ -->
            <div class="sticky bottom-4 z-10 surface backdrop-blur-xl bg-base-100/95 px-4 py-3 flex items-center justify-between gap-3 mt-5">
                <button v-if="step > 1" type="button" @click="back" class="btn btn-ghost btn-sm gap-1.5">
                    <ArrowLeftIcon class="w-4 h-4" /> Back
                </button>
                <span v-else></span>

                <div class="text-xs font-medium text-base-content/55 hidden sm:block">
                    Step <span class="font-bold text-base-content">{{ step }}</span> of {{ steps.length }}
                </div>

                <button v-if="step < 5" type="button" @click="next"
                    :disabled="!canAdvance(step)"
                    class="btn btn-primary btn-sm gap-1.5">
                    Next <ArrowRightIcon class="w-4 h-4" />
                </button>
                <button v-else type="submit" :disabled="form.processing"
                    class="btn btn-primary btn-sm gap-1.5">
                    <CheckCircleIcon class="w-4 h-4" />
                    {{ form.processing ? 'Saving...' : (isEdit ? 'Update Exam' : 'Create Exam') }}
                </button>
            </div>

            </form>
        </div>

        <!-- ═══════════ Add Missing Subject Modal ═══════════ -->
        <!-- Append-only flow for editing an existing exam: pick a class, see
             what's already there, tick the ones that were missed, set marks,
             save. Backend uses firstOrCreate so re-clicking is idempotent.
             Cannot affect existing marks because it never updates or deletes
             any ExamSubject row. -->
        <div v-if="showAddMissingModal"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
             @click.self="closeAddMissingModal">
            <div class="bg-base-100 rounded-2xl border border-base-300 shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">
                <header class="px-5 py-4 border-b border-base-200 flex items-center gap-3 bg-gradient-to-r from-primary/5 to-violet-500/5">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-violet-600 text-white flex items-center justify-center shadow-md">
                        <PlusIcon class="w-5 h-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="font-bold text-base">Add Missing Subject</h2>
                        <p class="text-[11px] text-base-content/55">
                            Append a subject that was omitted at creation —
                            existing marks stay untouched.
                        </p>
                    </div>
                    <button type="button" @click="closeAddMissingModal"
                            class="btn btn-ghost btn-sm btn-square">
                        <XCircleIcon class="w-5 h-5" />
                    </button>
                </header>

                <div class="px-5 py-4 space-y-4 overflow-y-auto">
                    <!-- Class picker -->
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-bold text-base-content/55 mb-1.5">
                            Class
                        </label>
                        <SearchableSelect v-model="addMissingClassId"
                            :options="(availableClasses || []).map(c => ({ value: c.id, label: c.name }))"
                            placeholder="Pick a class…" size="sm" />
                    </div>

                    <div v-if="addMissingClassId">
                        <!-- Already on the exam (read-only) -->
                        <div class="mb-4">
                            <p class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 mb-2">
                                Already on this exam · {{ addMissingExistingForClass.length }}
                            </p>
                            <div v-if="addMissingExistingForClass.length"
                                 class="rounded-xl border border-base-200 divide-y divide-base-200 bg-base-200/30">
                                <div v-for="row in addMissingExistingForClass" :key="row.subject.id"
                                     class="px-3 py-2 flex items-center gap-2 text-xs">
                                    <CheckCircleIcon class="w-4 h-4 text-emerald-500 shrink-0" />
                                    <span class="font-medium flex-1">{{ row.subject.name }}</span>
                                    <span class="text-base-content/55 tabular-nums">{{ row.total_marks }} / {{ row.passing_marks }}</span>
                                    <span v-if="row.marks_count > 0"
                                          class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-700 dark:text-amber-300 text-[10px] font-bold tabular-nums">
                                        <LockClosedIcon class="w-2.5 h-2.5" />
                                        {{ row.marks_count }} marks
                                    </span>
                                </div>
                            </div>
                            <p v-else class="text-[11px] text-base-content/45 italic">
                                No subjects on this exam for this class yet.
                            </p>
                        </div>

                        <!-- Missing subjects (tickable) -->
                        <div>
                            <p class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 mb-2">
                                Missing · {{ addMissingCandidates.length }}
                                <span class="text-[10px] text-base-content/45 normal-case font-normal">— from the class curriculum</span>
                            </p>
                            <div v-if="addMissingCandidates.length"
                                 class="rounded-xl border border-base-300 divide-y divide-base-200">
                                <label v-for="s in addMissingCandidates" :key="s.id"
                                       class="block px-3 py-2.5 hover:bg-base-200/40 cursor-pointer transition-colors">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox"
                                               :checked="!!addMissingPicks[s.id]?.ticked"
                                               @change="ensurePickRow(s.id).ticked = !ensurePickRow(s.id).ticked"
                                               class="checkbox checkbox-xs checkbox-primary" />
                                        <span class="font-medium text-sm flex-1">{{ s.name }}</span>
                                        <span class="text-[10px] font-mono text-base-content/45">{{ s.code }}</span>
                                    </div>
                                    <div v-if="addMissingPicks[s.id]?.ticked"
                                         class="mt-2 ml-7 grid grid-cols-3 gap-2">
                                        <div>
                                            <label class="block text-[10px] uppercase text-base-content/55 font-bold mb-0.5">Total marks</label>
                                            <input v-model.number="addMissingPicks[s.id].total_marks" type="number" min="1"
                                                   class="input input-bordered input-xs w-full font-mono text-right" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] uppercase text-base-content/55 font-bold mb-0.5">Passing</label>
                                            <input v-model.number="addMissingPicks[s.id].passing_marks" type="number" min="0"
                                                   class="input input-bordered input-xs w-full font-mono text-right" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] uppercase text-base-content/55 font-bold mb-0.5">Date (optional)</label>
                                            <input v-model="addMissingPicks[s.id].exam_date" type="date"
                                                   class="input input-bordered input-xs w-full" />
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <div v-else class="rounded-xl border border-dashed border-base-300 p-6 text-center">
                                <CheckCircleIcon class="w-8 h-8 text-emerald-500 mx-auto mb-1" />
                                <p class="text-xs font-medium">Nothing missing</p>
                                <p class="text-[11px] text-base-content/45 mt-0.5">
                                    All curriculum subjects for this class are already on the exam.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="px-5 py-3 border-t border-base-200 bg-base-200/40 flex items-center justify-between gap-2">
                    <p class="text-[11px] text-base-content/55">
                        <span v-if="addMissingTickedCount > 0">
                            {{ addMissingTickedCount }} subject{{ addMissingTickedCount === 1 ? '' : 's' }} selected
                        </span>
                        <span v-else>Pick a class and tick at least one subject.</span>
                    </p>
                    <div class="flex gap-2">
                        <button type="button" @click="closeAddMissingModal"
                                class="btn btn-ghost btn-sm">Cancel</button>
                        <button type="button" @click="submitAddMissing"
                                :disabled="addMissingTickedCount === 0 || addMissingBusy"
                                class="btn btn-primary btn-sm gap-1.5">
                            <CheckCircleIcon class="w-4 h-4" />
                            {{ addMissingBusy ? 'Adding…' : `Add ${addMissingTickedCount || ''} subject${addMissingTickedCount === 1 ? '' : 's'}`.trim() }}
                        </button>
                    </div>
                </footer>
            </div>
        </div>
    </AppLayout>
</template>
