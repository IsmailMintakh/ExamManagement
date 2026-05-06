<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import {
    ArrowLeftIcon, ArrowRightIcon, CheckCircleIcon, ClipboardDocumentListIcon,
    BuildingOfficeIcon, AcademicCapIcon, Cog6ToothIcon, EyeIcon,
    SparklesIcon, BoltIcon, PlusIcon, TrashIcon, ExclamationTriangleIcon,
    InformationCircleIcon, XCircleIcon, CheckIcon, BookmarkIcon, BeakerIcon,
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
})

const isEdit = !!props.exam?.id

const form = useForm({
    name: props.exam?.name || '',
    exam_type_id: props.exam?.exam_type_id || '',
    academic_session_id: props.exam?.academic_session_id || '',
    grading_scale_id: props.exam?.grading_scale_id || '',
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
watch(subjectsTotalMarksSum, (newSum) => {
    if (totalMarksAutoSync.value && newSum > 0) {
        form.total_marks = newSum
    }
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

function applyBulkSubjects() {
    if (!bulkSelectedClasses.value.length || !bulkSelectedSubjects.value.length) return
    let added = 0
    for (const classId of bulkSelectedClasses.value) {
        // Per-class scope: only add the subjects this specific class teaches
        // (a subject ticked in bulk shouldn't get added to a class that doesn't
        // teach it, even if another picked class does).
        const cls = (props.classes || []).find(c => Number(c.id) === Number(classId))
        const classSubjectIds = new Set((cls?.subjects || []).map(s => s.id))
        for (const subjectId of bulkSelectedSubjects.value) {
            if (!classSubjectIds.has(Number(subjectId))) continue
            const exists = form.subjects.some(s =>
                Number(s.school_class_id) === Number(classId) && Number(s.subject_id) === Number(subjectId))
            if (exists) continue
            form.subjects.push({
                subject_id: subjectId,
                school_class_id: classId,
                total_marks: bulkTotalMarks.value,
                passing_marks: bulkPassingMarks.value,
                exam_date: '',
            })
            added++
        }
    }
    if (added > 0) {
        bulkSelectedClasses.value = []
        bulkSelectedSubjects.value = []
    }
}

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
                                <select v-model="form.exam_type_id" required class="select select-bordered w-full mt-1.5">
                                    <option value="">Select type...</option>
                                    <option v-for="t in examTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Academic Session *</label>
                                <select v-model="form.academic_session_id" required class="select select-bordered w-full mt-1.5">
                                    <option value="">Select session...</option>
                                    <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
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
                                <select v-model="form.grading_scale_id" class="select select-bordered w-full mt-1.5">
                                    <option value="">Default</option>
                                    <option v-for="g in gradingScales" :key="g.id" :value="g.id">{{ g.name }}</option>
                                </select>
                            </div>
                            <!-- Optional: pick a teacher to designate as Exam Controller for this exam.
                                 Their name will appear on the date sheet, admit cards, etc. as the
                                 Examination Officer. Leave blank to fall back to the school's default. -->
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">
                                    Exam Controller
                                    <span class="text-base-content/40 normal-case font-medium">· optional</span>
                                </label>
                                <select v-model="form.exam_controller_id" class="select select-bordered w-full mt-1.5">
                                    <option value="">— None —</option>
                                    <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                                <p class="text-[10px] text-base-content/45 mt-1">
                                    Pick a teacher to sign exam paperwork (date sheet, admit cards, etc.).
                                </p>
                            </div>
                        </div>

                        <div class="pt-5 border-t border-base-200">
                            <p class="section-eyebrow mb-3">Marks &amp; Position</p>
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                <div>
                                    <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">
                                        Total Marks *
                                        <span v-if="totalMarksAutoSync && subjectsTotalMarksSum > 0"
                                            class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400 normal-case tracking-normal">
                                            · auto from subjects
                                        </span>
                                    </label>
                                    <input v-model.number="form.total_marks" type="number" min="1"
                                        @input="disableTotalMarksAutoSync"
                                        :readonly="totalMarksAutoSync && subjectsTotalMarksSum > 0"
                                        :class="totalMarksAutoSync && subjectsTotalMarksSum > 0 ? 'bg-base-200/50' : ''"
                                        class="input input-bordered w-full mt-1.5 font-mono" />
                                    <p v-if="!totalMarksAutoSync && subjectsTotalMarksSum > 0 && form.total_marks !== subjectsTotalMarksSum"
                                        class="text-[10px] mt-1">
                                        <button type="button" @click="reEnableTotalMarksAutoSync"
                                            class="text-primary font-semibold hover:underline">
                                            ↺ Sync from subjects ({{ subjectsTotalMarksSum }})
                                        </button>
                                    </p>
                                </div>
                                <div>
                                    <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Passing %</label>
                                    <input v-model.number="form.passing_percentage" type="number" min="0" max="100"
                                        class="input input-bordered w-full mt-1.5 font-mono" />
                                </div>
                                <div>
                                    <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Passing Marks</label>
                                    <input v-model.number="form.passing_marks" type="number" min="0" readonly
                                        class="input input-bordered w-full mt-1.5 font-mono bg-base-200/50" />
                                </div>
                                <div>
                                    <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Position by</label>
                                    <select v-model="form.position_calculation" class="select select-bordered w-full mt-1.5">
                                        <option value="section">Section</option>
                                        <option value="class">Class</option>
                                        <option value="school">School</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Description</label>
                            <textarea v-model="form.description" rows="2" placeholder="Optional notes about this exam..."
                                class="textarea textarea-bordered w-full mt-1.5"></textarea>
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
                                <label class="section-eyebrow mb-2 block">Classes</label>
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

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="section-eyebrow">Total Marks</label>
                                <input v-model.number="bulkTotalMarks" type="number" min="1"
                                    class="input input-bordered input-sm w-full mt-1 font-mono" />
                            </div>
                            <div>
                                <label class="section-eyebrow">Passing Marks</label>
                                <input v-model.number="bulkPassingMarks" type="number" min="0"
                                    class="input input-bordered input-sm w-full mt-1 font-mono" />
                            </div>
                            <div class="flex items-end">
                                <button type="button" @click="applyBulkSubjects"
                                    :disabled="!bulkSelectedClasses.length || !bulkSelectedSubjects.length"
                                    class="btn btn-primary btn-sm w-full gap-1.5">
                                    <BoltIcon class="w-4 h-4" /> Apply to {{ bulkSelectedClasses.length * bulkSelectedSubjects.length || 0 }} pairs
                                </button>
                            </div>
                        </div>
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
                        <div class="flex gap-2">
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

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-base-200/40 text-[11px] uppercase tracking-wider text-base-content/55">
                                <tr>
                                    <th class="text-left px-4 py-3 font-bold">Class</th>
                                    <th class="text-left px-3 py-3 font-bold">Subject</th>
                                    <th class="text-right px-3 py-3 font-bold w-24">Total</th>
                                    <th class="text-right px-3 py-3 font-bold w-24">Pass</th>
                                    <th class="px-2 py-3 w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-base-200">
                                <tr v-for="(row, i) in form.subjects" :key="i" class="hover:bg-base-200/30">
                                    <td class="px-4 py-2">
                                        <select v-model="row.school_class_id" class="select select-bordered select-xs rounded-lg w-full">
                                            <option value="">—</option>
                                            <option v-for="c in availableClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <select v-model="row.subject_id" class="select select-bordered select-xs rounded-lg w-full">
                                            <option value="">—</option>
                                            <option v-for="s in subjectsForClass(row.school_class_id)" :key="s.id" :value="s.id">{{ s.name }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input v-model.number="row.total_marks" type="number" min="1"
                                            class="input input-bordered input-xs rounded-lg w-full text-right font-mono" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input v-model.number="row.passing_marks" type="number" min="0"
                                            class="input input-bordered input-xs rounded-lg w-full text-right font-mono" />
                                    </td>
                                    <td class="px-2 py-2 text-right">
                                        <button type="button" @click="removeSubjectRow(i)"
                                            class="btn btn-ghost btn-xs btn-square text-rose-500">
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
                    <div class="surface-body">
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                        <div><dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Total Marks</dt><dd class="font-mono font-bold mt-0.5">{{ form.total_marks }}</dd></div>
                        <div><dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Pass %</dt><dd class="font-mono font-bold mt-0.5">{{ form.passing_percentage }}%</dd></div>
                        <div><dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Grace</dt><dd class="font-mono font-bold mt-0.5">{{ form.grace_marks || 0 }} on max {{ form.grace_marks_max_subjects || 0 }}</dd></div>
                        <div><dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Subjects mapped</dt><dd class="font-mono font-bold mt-0.5">{{ form.subjects.length }}</dd></div>
                        <div><dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Schools</dt><dd class="font-bold mt-0.5">{{ form.apply_to_all_schools ? 'All schools' : `${form.selected_school_ids.length} selected` }}</dd></div>
                        <div><dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Position by</dt><dd class="font-bold mt-0.5 capitalize">{{ form.position_calculation }}</dd></div>
                        <div><dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Strict mode</dt>
                            <dd class="font-bold mt-0.5">
                                <span v-if="form.all_subjects_must_pass" class="text-rose-700">All must pass</span>
                                <span v-else-if="form.main_subjects_must_pass" class="text-amber-700">Main must pass</span>
                                <span v-else class="text-base-content/55">No</span>
                            </dd>
                        </div>
                        <div><dt class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Mandatory subjects</dt><dd class="font-bold mt-0.5">{{ form.passing_rules?.mandatory_subjects?.length || 0 }}</dd></div>
                        </div>
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
    </AppLayout>
</template>
