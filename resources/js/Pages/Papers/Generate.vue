<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import {
    PlusIcon, TrashIcon, ArrowLeftIcon, BoltIcon,
    CheckCircleIcon, SparklesIcon, AcademicCapIcon, BookOpenIcon,
    Cog6ToothIcon, CheckIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    subjects: Array,
    classes: Array,
    topics: Array,
    sourceCounts: { type: Object, default: () => null },
    defaultSource: { type: String, default: 'all' },
})

const form = useForm({
    subject_id: '',
    school_class_id: '',
    title: '',
    exam_name: '',
    duration_minutes: 90,
    instructions: 'Attempt all questions. Write clearly and neatly.',
    set_code: 'A',
    shuffle: true,
    show_sections: true, // false = print questions in one continuous numbered list, no section headings
    topics: [],
    // Question pool source: 'mine' (own creations) | 'library' (DDO globals) | 'all'.
    // Default 'all' so the user has the widest pool when generating a paper.
    source: props.defaultSource || 'all',
    sections: [
        // Counts are auto-capped to what the bank actually has once a subject
        // is selected — these are just the upper-end defaults.
        { label: 'Section A — MCQs', type: 'mcq', difficulty: 'mixed', count: 10, numbering_style: 'arabic', restart_numbering: false },
        { label: 'Section B — Short Answers', type: 'short_answer', difficulty: 'mixed', count: 5, numbering_style: 'arabic', restart_numbering: true },
        { label: 'Section C — Long Answers', type: 'long_answer', difficulty: 'mixed', count: 2, numbering_style: 'arabic', restart_numbering: true },
    ],
})

const showAdvanced = ref(false)
const activePresetKey = ref('test') // default to Class Test
const availableCounts = ref(null)

const presets = [
    {
        key: 'quiz',
        label: 'Quick Quiz',
        icon: SparklesIcon,
        desc: '15 MCQs',
        duration: 30,
        sections: [
            { label: 'Multiple Choice Questions', type: 'mcq', difficulty: 'mixed', count: 15 },
        ],
    },
    {
        key: 'test',
        label: 'Class Test',
        icon: BookOpenIcon,
        desc: '10 MCQ · 5 Short · 2 Long',
        duration: 90,
        sections: [
            { label: 'Section A — MCQs', type: 'mcq', difficulty: 'mixed', count: 10 },
            { label: 'Section B — Short Answers', type: 'short_answer', difficulty: 'mixed', count: 5 },
            { label: 'Section C — Long Answers', type: 'long_answer', difficulty: 'mixed', count: 2 },
        ],
    },
    {
        key: 'exam',
        label: 'Term Exam',
        icon: AcademicCapIcon,
        desc: '20 MCQ · 10 Short · 5 Long',
        duration: 180,
        sections: [
            { label: 'Section A — Multiple Choice', type: 'mcq', difficulty: 'mixed', count: 20 },
            { label: 'Section B — Short Answers', type: 'short_answer', difficulty: 'mixed', count: 10 },
            { label: 'Section C — Long Answers', type: 'long_answer', difficulty: 'mixed', count: 5 },
        ],
    },
]

const typeOptions = [
    { value: 'mcq', label: 'MCQ' },
    { value: 'short_answer', label: 'Short Answer' },
    { value: 'long_answer', label: 'Long Answer' },
    { value: 'true_false', label: 'True / False' },
    { value: 'fill_blank', label: 'Fill in the Blank' },
]

// Friendly section name auto-derived from the question type. Used to keep
// the printed section heading honest — e.g. if a section's type is changed
// to true_false but its label still says "Short Answers", the heading
// (and therefore the printed paper) lies. We auto-rewrite the label when
// the user changes the type, only if the label still matches the OLD
// type's auto-name (i.e. the user hasn't customized it).
const sectionTitleByType = {
    mcq: 'Multiple Choice Questions',
    true_false: 'True / False',
    short_answer: 'Short Answer Questions',
    long_answer: 'Long Answer Questions',
    fill_blank: 'Fill in the Blanks',
}
function defaultLabelFor(idx, type) {
    const letter = String.fromCharCode(65 + idx)
    return `Section ${letter} — ${sectionTitleByType[type] ?? 'Questions'}`
}
function onSectionTypeChange(section, idx) {
    // Rebuild label if it matches ANY type's default for this index, so we
    // know the user didn't pick a custom label. Otherwise leave their text alone.
    const looksLikeDefault = Object.values(sectionTitleByType)
        .some(name => section.label === `Section ${String.fromCharCode(65 + idx)} — ${name}`)
        || /^Section [A-Z]\b/i.test(section.label || '')
    if (looksLikeDefault) {
        section.label = defaultLabelFor(idx, section.type)
    }
}
const difficultyOptions = [
    { value: 'mixed', label: 'Mixed' },
    { value: 'easy', label: 'Easy' },
    { value: 'medium', label: 'Medium' },
    { value: 'hard', label: 'Hard' },
]

function applyPreset(p) {
    activePresetKey.value = p.key
    form.duration_minutes = p.duration
    form.sections = p.sections.map(s => ({ ...s }))
}

async function fetchCounts() {
    if (!form.subject_id) { availableCounts.value = null; return }
    try {
        const res = await window.axios.post(route('papers.preview'), {
            subject_id: form.subject_id,
            school_class_id: form.school_class_id || null,
            topics: form.topics,
            source: form.source,
        })
        availableCounts.value = res.data?.counts || null
    } catch (e) {
        availableCounts.value = null
    }
}

watch(() => [form.subject_id, form.school_class_id, form.topics, form.source], fetchCounts, { deep: true })

// Whenever the available counts arrive (or the user picks a different subject),
// cap each section's `count` to what the bank actually has — so the button
// isn't silently disabled with "need 10, have 2" issues. User can still
// raise the count manually; we only ever shrink, never grow.
watch(availableCounts, (counts) => {
    if (!counts) return
    for (const s of form.sections) {
        const have = counts[s.type]?.total ?? 0
        if (Number(s.count) > have) {
            s.count = Math.max(1, have)
        }
    }
}, { deep: false })

// Auto-fill title
// Auto-fill title with the preset label only — subject already shows in the
// meta row on the printed paper, so duplicating it ("Computer Science — Class
// Test" + Subject: Computer Science) was redundant.
watch(() => form.subject_id, (id) => {
    if (id && !form.title) {
        const preset = presets.find(p => p.key === activePresetKey.value)
        form.title = preset?.label || 'Question Paper'
    }
})

function availableForSection(section) {
    if (!availableCounts.value || !section.type) return null
    const typeData = availableCounts.value[section.type]
    if (!typeData) return 0
    if (!section.difficulty || section.difficulty === 'mixed') return typeData.total
    return typeData[section.difficulty] || 0
}

// Average marks per question of this type, taken straight from the bank.
// Used to show "auto from questions ≈ X marks each" + an estimated total
// before generation. NOT sent to backend — backend always uses the actual
// marks of the questions it picks.
function avgMarksForSection(section) {
    if (!availableCounts.value || !section.type) return null
    const typeData = availableCounts.value[section.type]
    return typeData?.avg_marks ?? null
}
function estimatedSectionMarks(section) {
    const avg = avgMarksForSection(section)
    if (avg === null) return null
    return (Number(section.count) || 0) * avg
}
const estimatedTotalMarks = computed(() =>
    form.sections.reduce((sum, s) => {
        const est = estimatedSectionMarks(s)
        return sum + (est ?? 0)
    }, 0)
)

const totalQuestions = computed(() =>
    form.sections.reduce((sum, s) => sum + (Number(s.count) || 0), 0)
)

// Check if a preset will work given current available counts
function presetIsPossible(preset) {
    if (!availableCounts.value) return null
    for (const s of preset.sections) {
        const have = availableCounts.value[s.type]?.total ?? 0
        if (have < s.count) return { ok: false, issue: `Only ${have} ${s.type.replace('_', ' ')}s available, need ${s.count}` }
    }
    return { ok: true }
}

const blockingIssues = computed(() => {
    if (!form.subject_id) return ['Select a subject to continue.']
    if (!form.title?.trim()) return ['Enter a paper title.']
    if (!availableCounts.value) return []

    const issues = []
    for (const s of form.sections) {
        const have = availableForSection(s)
        const need = Number(s.count) || 0
        if (have !== null && have < need) {
            issues.push(`${s.label}: need ${need} but only ${have} available.`)
        }
    }
    return issues
})

// Only the truly minimum requirements — never disable for "not enough
// questions in bank" because the user might still want to try and read the
// real backend error. Blocking issues are still shown above the button.
const canGenerate = computed(() =>
    form.subject_id && form.title?.trim() && form.sections.length > 0
)

function addSection() {
    form.sections.push({
        label: `Section ${String.fromCharCode(64 + form.sections.length + 1)}`,
        type: 'short_answer',
        difficulty: 'mixed',
        count: 5,
        answer_lines: null, // null = use defaults; 0 = no writing space; N = N lines
        numbering_style: 'arabic', // arabic (1,2,3) | roman (I,II,III) | alpha_upper (A,B,C) | alpha_lower (a,b,c)
        restart_numbering: false,  // true = this section's questions start at 1 again
    })
}

const numberingStyleOptions = [
    { value: 'arabic',      label: '1, 2, 3' },
    { value: 'roman',       label: 'I, II, III' },
    { value: 'alpha_upper', label: 'A, B, C' },
    { value: 'alpha_lower', label: 'a, b, c' },
]

// Friendly label for the answer-lines selector — null is "Auto" (the type's
// sensible default), 0 means "no writing space at all".
const answerLinesPresets = [
    { value: null, label: 'Auto' },
    { value: 0, label: 'None' },
    { value: 2, label: '2 lines' },
    { value: 3, label: '3 lines' },
    { value: 5, label: '5 lines' },
    { value: 8, label: '8 lines' },
    { value: 10, label: '10 lines' },
    { value: 15, label: '15 lines' },
]
function removeSection(i) {
    if (form.sections.length <= 1) return
    form.sections.splice(i, 1)
}
function toggleTopic(topic) {
    const i = form.topics.indexOf(topic)
    if (i >= 0) form.topics.splice(i, 1)
    else form.topics.push(topic)
}

const submitError = ref('')
function submit() {
    submitError.value = ''
    if (!form.subject_id) { submitError.value = 'Please pick a subject first.'; return }
    if (!form.title?.trim()) { submitError.value = 'Please enter a paper title.'; return }
    form.post(route('papers.store'), {
        onError: (errors) => {
            const first = Object.values(errors)[0]
            submitError.value = (Array.isArray(first) ? first[0] : first) || 'Validation failed.'
            console.error('Paper generation errors:', errors)
        },
    })
}
</script>

<template>
    <Head title="Generate Paper" />
    <AppLayout :breadcrumbs="[
        { label: 'Paper Generator', href: route('papers.index') },
        { label: 'Generate' }
    ]">
        <div class="max-w-3xl mx-auto">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-bold">Generate Paper</h1>
                    <p class="text-sm text-base-content/60 mt-0.5">Pick a subject and a template — we'll do the rest.</p>
                </div>
                <Link :href="route('papers.index')" class="btn btn-ghost btn-sm gap-2">
                    <ArrowLeftIcon class="w-4 h-4" /> Back
                </Link>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <!-- Step 1: Subject + Class -->
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4 space-y-3">
                        <div class="flex items-start gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-primary-content text-xs font-bold shrink-0">1</span>
                            <div class="flex-1">
                                <label class="text-[13px] font-semibold">What subject &amp; class?</label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                                    <SearchableSelect v-model="form.subject_id"
                                        :options="subjects.map(s => ({ value: s.id, label: s.name }))"
                                        placeholder="Choose subject..." />
                                    <SearchableSelect v-model="form.school_class_id"
                                        :options="[{ value: '', label: 'Any class' }, ...classes.map(c => ({ value: c.id, label: c.name }))]"
                                        placeholder="Any class" />
                                </div>
                                <p v-if="form.errors.subject_id" class="mt-1 text-xs text-error">{{ form.errors.subject_id }}</p>

                                <!-- Question pool source. Defaults to "All" so
                                     the user has the widest possible bank to
                                     pick from. Switch to "Mine" to use only
                                     own-authored questions; "Library" to draw
                                     exclusively from the DDO-shared pool. -->
                                <div v-if="sourceCounts" class="mt-3">
                                    <label class="text-[12px] font-semibold text-base-content/75">Question Source</label>
                                    <div class="join mt-1.5">
                                        <button type="button" @click="form.source = 'mine'"
                                            class="btn btn-sm join-item"
                                            :class="form.source === 'mine' ? 'btn-primary' : 'btn-ghost'">
                                            Mine
                                            <span class="badge badge-xs ml-1">{{ sourceCounts.mine }}</span>
                                        </button>
                                        <button type="button" @click="form.source = 'library'"
                                            class="btn btn-sm join-item"
                                            :class="form.source === 'library' ? 'btn-primary' : 'btn-ghost'">
                                            Library
                                            <span class="badge badge-xs ml-1">{{ sourceCounts.library }}</span>
                                        </button>
                                        <button type="button" @click="form.source = 'all'"
                                            class="btn btn-sm join-item"
                                            :class="form.source === 'all' ? 'btn-primary' : 'btn-ghost'">
                                            All
                                            <span class="badge badge-xs ml-1">{{ sourceCounts.all }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Pick a template -->
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4 space-y-3">
                        <div class="flex items-start gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold shrink-0"
                                :class="form.subject_id ? 'bg-primary text-primary-content' : 'bg-base-200 text-base-content/50'">2</span>
                            <div class="flex-1">
                                <label class="text-[13px] font-semibold">Pick a template</label>
                                <p class="text-xs text-base-content/55 mt-0.5">Or tap "Customize" for full control.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                            <button v-for="p in presets" :key="p.key" type="button"
                                @click="applyPreset(p)"
                                class="relative text-left rounded-xl border-2 p-4 transition-all group"
                                :class="activePresetKey === p.key
                                    ? 'border-primary bg-primary/5 shadow-md'
                                    : 'border-base-200 hover:border-primary/40 hover:bg-base-200/40'">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <component :is="p.icon" class="w-5 h-5"
                                        :class="activePresetKey === p.key ? 'text-primary' : 'text-base-content/60'" />
                                    <span class="font-bold">{{ p.label }}</span>
                                    <CheckIcon v-if="activePresetKey === p.key" class="w-4 h-4 text-primary ml-auto" />
                                </div>
                                <div class="text-xs text-base-content/60 mb-2">{{ p.desc }}</div>
                                <div class="flex gap-3 text-[11px] text-base-content/70">
                                    <span><strong>{{ p.marks }}</strong> marks</span>
                                    <span><strong>{{ p.duration }}</strong> min</span>
                                </div>
                                <!-- Availability indicator -->
                                <div v-if="form.subject_id && availableCounts" class="mt-2">
                                    <span v-if="presetIsPossible(p)?.ok" class="badge badge-success badge-sm gap-1">
                                        <CheckCircleIcon class="w-3 h-3" /> Ready
                                    </span>
                                    <span v-else class="badge badge-warning badge-sm text-[10px]"
                                        :title="presetIsPossible(p)?.issue">
                                        Not enough questions
                                    </span>
                                </div>
                            </button>
                        </div>

                        <button type="button" @click="showAdvanced = !showAdvanced"
                            class="btn btn-ghost btn-sm gap-2 w-full mt-2">
                            <Cog6ToothIcon class="w-4 h-4" />
                            {{ showAdvanced ? 'Hide' : 'Customize' }} sections
                        </button>

                        <!-- Advanced: section builder -->
                        <div v-if="showAdvanced" class="pt-3 border-t border-base-200 space-y-3">
                            <div v-for="(section, i) in form.sections" :key="i"
                                class="rounded-lg border border-base-200 p-3 bg-base-200/20">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-primary text-xs font-bold">{{ i + 1 }}</span>
                                    <input v-model="section.label" type="text"
                                        class="input input-ghost input-sm flex-1 font-semibold"
                                        placeholder="Section label" />
                                    <button type="button" v-if="form.sections.length > 1" @click="removeSection(i)"
                                        class="btn btn-ghost btn-xs btn-square text-error">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <label class="text-[10px] text-base-content/55 font-semibold">Type</label>
                                        <select v-model="section.type"
                                            @change="onSectionTypeChange(section, i)"
                                            class="select select-bordered select-xs w-full">
                                            <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-[10px] text-base-content/55 font-semibold">Difficulty</label>
                                        <select v-model="section.difficulty" class="select select-bordered select-xs w-full">
                                            <option v-for="d in difficultyOptions" :key="d.value" :value="d.value">{{ d.label }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-[10px] text-base-content/55 font-semibold">Count</label>
                                        <input v-model.number="section.count" type="number" min="1"
                                            class="input input-bordered input-xs w-full" placeholder="Count" />
                                    </div>
                                </div>
                                <!-- Numbering controls per section — restart counter at 1 +
                                     pick the style (1/I/A/a). Default: arabic continuous. -->
                                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-[10px] text-base-content/55 font-semibold">
                                            Numbering style
                                        </label>
                                        <select v-model="section.numbering_style"
                                            class="select select-bordered select-xs w-full">
                                            <option v-for="ns in numberingStyleOptions" :key="ns.value" :value="ns.value">
                                                {{ ns.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="flex items-end">
                                        <label class="flex items-center gap-2 cursor-pointer text-xs">
                                            <input type="checkbox" v-model="section.restart_numbering"
                                                class="checkbox checkbox-xs checkbox-primary" />
                                            <span>Restart at 1 for this section</span>
                                        </label>
                                    </div>
                                </div>
                                <!-- Writing-space selector — controls how many blank lines are
                                     printed under each question for this section. Useful for
                                     primary classes (Nursery / Prep) that need lots of room. -->
                                <div class="mt-2">
                                    <label class="text-[10px] text-base-content/55 font-semibold flex items-center gap-1">
                                        Writing space per question
                                        <span class="text-base-content/40 normal-case font-normal">— blank lines under each question</span>
                                    </label>
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        <label v-for="p in answerLinesPresets" :key="p.label"
                                            class="cursor-pointer border rounded-md px-2 py-0.5 text-[11px] font-medium transition"
                                            :class="(section.answer_lines ?? null) === p.value
                                                ? 'bg-primary/10 border-primary text-primary'
                                                : 'border-base-300 text-base-content/60 hover:bg-base-200'">
                                            <input type="radio" :name="`alines-${i}`" :value="p.value"
                                                v-model="section.answer_lines" class="hidden" />
                                            {{ p.label }}
                                        </label>
                                    </div>
                                </div>
                                <div class="mt-2 flex items-center justify-between text-[11px]">
                                    <span v-if="availableForSection(section) !== null"
                                        class="badge badge-xs"
                                        :class="availableForSection(section) >= section.count ? 'badge-success' : 'badge-error'">
                                        {{ availableForSection(section) }} available
                                    </span>
                                    <span v-else class="text-base-content/40">—</span>
                                    <span v-if="avgMarksForSection(section) !== null"
                                        class="text-base-content/65"
                                        :title="`Auto-computed from question bank: ${avgMarksForSection(section)} marks per ${section.type.replace('_', ' ')} question on average`">
                                        ~{{ avgMarksForSection(section) }} marks each ·
                                        <strong class="text-primary">{{ estimatedSectionMarks(section)?.toFixed(2) }} total</strong>
                                    </span>
                                    <span v-else class="text-base-content/40 italic">
                                        marks auto from questions
                                    </span>
                                </div>
                            </div>
                            <button type="button" @click="addSection" class="btn btn-ghost btn-sm w-full gap-1">
                                <PlusIcon class="w-4 h-4" /> Add Section
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Paper details -->
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4 space-y-3">
                        <div class="flex items-start gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold shrink-0"
                                :class="form.subject_id ? 'bg-primary text-primary-content' : 'bg-base-200 text-base-content/50'">3</span>
                            <div class="flex-1">
                                <label class="text-[13px] font-semibold">Paper details</label>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider">Title <span class="text-error">*</span></label>
                                <input v-model="form.title" type="text" required
                                    placeholder="e.g., Mid-Term Physics"
                                    class="input input-bordered input-sm w-full mt-1" />
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider">Exam Name <span class="text-base-content/40">(optional)</span></label>
                                <input v-model="form.exam_name" type="text"
                                    placeholder="e.g., First Term 2026"
                                    class="input input-bordered input-sm w-full mt-1" />
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider">Duration (min)</label>
                                <input v-model.number="form.duration_minutes" type="number" min="15"
                                    class="input input-bordered input-sm w-full mt-1" />
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider">Set Code</label>
                                <input v-model="form.set_code" type="text"
                                    class="input input-bordered input-sm w-full mt-1" />
                            </div>
                        </div>

                        <!-- Visible layout options (was buried inside Advanced) -->
                        <div class="rounded-lg bg-base-200/40 p-3 space-y-2 border border-base-200">
                            <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider block">Paper Layout</label>
                            <label class="flex items-start gap-2 cursor-pointer">
                                <input type="checkbox" v-model="form.show_sections"
                                    class="checkbox checkbox-sm checkbox-primary mt-0.5" />
                                <div>
                                    <span class="text-sm font-medium">Show section headings (Section A, Section B…) on the printed paper</span>
                                    <p class="text-[11px] text-base-content/50 mt-0.5">
                                        Turn this OFF for primary classes (Nursery, Prep, KG…) — questions print as one continuous Q1, Q2, Q3 list with no section breaks.
                                    </p>
                                </div>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="form.shuffle" class="checkbox checkbox-sm checkbox-primary" />
                                <span class="text-sm">Shuffle MCQ option order across sets</span>
                            </label>
                        </div>

                        <details>
                            <summary class="cursor-pointer text-sm text-base-content/60 select-none">Advanced — instructions &amp; topics</summary>
                            <div class="mt-3 space-y-3">
                                <div>
                                    <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider">Instructions</label>
                                    <textarea v-model="form.instructions" rows="2"
                                        class="textarea textarea-bordered textarea-sm w-full mt-1 text-sm"></textarea>
                                </div>
                                <div v-if="topics?.length">
                                    <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider">Topics Filter</label>
                                    <div class="flex flex-wrap gap-1.5 mt-1.5">
                                        <button type="button" v-for="t in topics" :key="t"
                                            @click="toggleTopic(t)"
                                            class="btn btn-xs"
                                            :class="form.topics.includes(t) ? 'btn-primary' : 'btn-outline'">
                                            {{ t }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>

                <!-- Summary + blocking issues + Generate -->
                <div class="card bg-gradient-to-br from-primary/10 to-secondary/5 shadow-md sticky bottom-4">
                    <div class="card-body p-4">
                        <!-- Server-side error from a failed submit attempt -->
                        <div v-if="submitError" class="mb-3 p-3 rounded-lg bg-error/15 border-2 border-error">
                            <p class="font-bold text-error text-sm">⚠ {{ submitError }}</p>
                        </div>

                        <!-- BLOCKING ISSUES first — so the user sees WHY before they look for the button -->
                        <div v-if="blockingIssues.length" class="mb-3 p-3 rounded-lg bg-error/10 border border-error/30">
                            <p class="font-bold text-error text-xs uppercase tracking-wider mb-1">
                                ⚠ Cannot generate yet — fix these first:
                            </p>
                            <ul class="text-error/90 text-xs list-disc list-inside space-y-0.5">
                                <li v-for="(issue, i) in blockingIssues" :key="i">{{ issue }}</li>
                            </ul>
                            <Link :href="route('questions.index')" class="link link-primary text-xs mt-1.5 inline-block">
                                → Add more questions to the bank
                            </Link>
                        </div>

                        <div class="flex items-center justify-between gap-4 flex-wrap">
                            <div class="flex gap-5 text-sm">
                                <div>
                                    <div class="text-[11px] text-base-content/55 uppercase tracking-wider">Questions</div>
                                    <div class="text-lg font-bold">{{ totalQuestions }}</div>
                                </div>
                                <div>
                                    <div class="text-[11px] text-base-content/55 uppercase tracking-wider">Sections</div>
                                    <div class="text-lg font-bold">{{ form.sections.length }}</div>
                                </div>
                                <div>
                                    <div class="text-[11px] text-base-content/55 uppercase tracking-wider">
                                        Est. Marks
                                        <span class="text-base-content/40 normal-case font-normal">(from bank avg)</span>
                                    </div>
                                    <div class="text-lg font-extrabold text-primary">
                                        ~{{ estimatedTotalMarks.toFixed(2) }}
                                    </div>
                                </div>
                            </div>
                            <button type="submit" :disabled="form.processing || !canGenerate"
                                :title="!canGenerate
                                    ? (blockingIssues.length ? blockingIssues[0] : 'Pick a subject and enter a title')
                                    : ''"
                                class="btn btn-primary gap-2">
                                <BoltIcon class="w-5 h-5" />
                                {{ form.processing ? 'Generating...' : 'Generate Paper' }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
