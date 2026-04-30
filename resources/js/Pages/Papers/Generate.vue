<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
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
    topics: [],
    sections: [
        { label: 'Section A — MCQs', type: 'mcq', difficulty: 'mixed', count: 10, marks_each: 1 },
        { label: 'Section B — Short Answers', type: 'short_answer', difficulty: 'mixed', count: 5, marks_each: 2 },
        { label: 'Section C — Long Answers', type: 'long_answer', difficulty: 'mixed', count: 2, marks_each: 7.5 },
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
        marks: 15,
        duration: 30,
        sections: [
            { label: 'Multiple Choice Questions', type: 'mcq', difficulty: 'mixed', count: 15, marks_each: 1 },
        ],
    },
    {
        key: 'test',
        label: 'Class Test',
        icon: BookOpenIcon,
        desc: '10 MCQ · 5 Short · 2 Long',
        marks: 35,
        duration: 90,
        sections: [
            { label: 'Section A — MCQs', type: 'mcq', difficulty: 'mixed', count: 10, marks_each: 1 },
            { label: 'Section B — Short Answers', type: 'short_answer', difficulty: 'mixed', count: 5, marks_each: 2 },
            { label: 'Section C — Long Answers', type: 'long_answer', difficulty: 'mixed', count: 2, marks_each: 7.5 },
        ],
    },
    {
        key: 'exam',
        label: 'Term Exam',
        icon: AcademicCapIcon,
        desc: '20 MCQ · 10 Short · 5 Long',
        marks: 100,
        duration: 180,
        sections: [
            { label: 'Section A — Multiple Choice', type: 'mcq', difficulty: 'mixed', count: 20, marks_each: 1 },
            { label: 'Section B — Short Answers', type: 'short_answer', difficulty: 'mixed', count: 10, marks_each: 3 },
            { label: 'Section C — Long Answers', type: 'long_answer', difficulty: 'mixed', count: 5, marks_each: 10 },
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
        })
        availableCounts.value = res.data?.counts || null
    } catch (e) {
        availableCounts.value = null
    }
}

watch(() => [form.subject_id, form.school_class_id, form.topics], fetchCounts, { deep: true })

// Auto-fill title
watch(() => form.subject_id, (id) => {
    if (id && !form.title) {
        const sub = props.subjects?.find(s => s.id === Number(id) || s.id === id)
        const preset = presets.find(p => p.key === activePresetKey.value)
        if (sub) form.title = `${sub.name} — ${preset?.label || 'Paper'}`
    }
})

function availableForSection(section) {
    if (!availableCounts.value || !section.type) return null
    const typeData = availableCounts.value[section.type]
    if (!typeData) return 0
    if (!section.difficulty || section.difficulty === 'mixed') return typeData.total
    return typeData[section.difficulty] || 0
}

const totalMarks = computed(() =>
    form.sections.reduce((sum, s) => sum + ((Number(s.count) || 0) * (Number(s.marks_each) || 0)), 0)
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

const canGenerate = computed(() =>
    form.subject_id && form.title?.trim() && form.sections.length > 0 && blockingIssues.value.length === 0
)

function addSection() {
    form.sections.push({
        label: `Section ${String.fromCharCode(64 + form.sections.length + 1)}`,
        type: 'short_answer',
        difficulty: 'mixed',
        count: 5,
        marks_each: 2,
    })
}
function removeSection(i) {
    if (form.sections.length <= 1) return
    form.sections.splice(i, 1)
}
function toggleTopic(topic) {
    const i = form.topics.indexOf(topic)
    if (i >= 0) form.topics.splice(i, 1)
    else form.topics.push(topic)
}

function submit() {
    form.post(route('papers.store'))
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
                                    <select v-model="form.subject_id" required class="select select-bordered w-full">
                                        <option value="">Choose subject...</option>
                                        <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                                    </select>
                                    <select v-model="form.school_class_id" class="select select-bordered w-full">
                                        <option value="">Any class</option>
                                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                </div>
                                <p v-if="form.errors.subject_id" class="mt-1 text-xs text-error">{{ form.errors.subject_id }}</p>
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
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                    <select v-model="section.type" class="select select-bordered select-xs">
                                        <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                                    </select>
                                    <select v-model="section.difficulty" class="select select-bordered select-xs">
                                        <option v-for="d in difficultyOptions" :key="d.value" :value="d.value">{{ d.label }}</option>
                                    </select>
                                    <input v-model.number="section.count" type="number" min="1"
                                        class="input input-bordered input-xs" placeholder="Count" />
                                    <input v-model.number="section.marks_each" type="number" step="0.25" min="0.25"
                                        class="input input-bordered input-xs" placeholder="Marks each" />
                                </div>
                                <div class="mt-2 flex items-center justify-between text-[11px]">
                                    <span v-if="availableForSection(section) !== null"
                                        class="badge badge-xs"
                                        :class="availableForSection(section) >= section.count ? 'badge-success' : 'badge-error'">
                                        {{ availableForSection(section) }} available
                                    </span>
                                    <span v-else class="text-base-content/40">—</span>
                                    <span class="font-mono text-base-content/60">
                                        {{ ((section.count || 0) * (section.marks_each || 0)).toFixed(2) }} marks
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

                        <details>
                            <summary class="cursor-pointer text-sm text-base-content/60 select-none">Advanced (instructions, topics, shuffle)</summary>
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
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" v-model="form.shuffle" class="checkbox checkbox-sm checkbox-primary" />
                                    <span class="text-sm">Shuffle MCQ option order</span>
                                </label>
                            </div>
                        </details>
                    </div>
                </div>

                <!-- Summary + blocking issues + Generate -->
                <div class="card bg-gradient-to-br from-primary/10 to-secondary/5 shadow-md sticky bottom-4">
                    <div class="card-body p-4">
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
                                    <div class="text-[11px] text-base-content/55 uppercase tracking-wider">Total Marks</div>
                                    <div class="text-lg font-extrabold text-primary">{{ totalMarks.toFixed(2) }}</div>
                                </div>
                            </div>
                            <button type="submit" :disabled="form.processing || !canGenerate"
                                class="btn btn-primary gap-2">
                                <BoltIcon class="w-5 h-5" />
                                {{ form.processing ? 'Generating...' : 'Generate Paper' }}
                            </button>
                        </div>
                        <div v-if="blockingIssues.length" class="mt-3 p-3 rounded-lg bg-error/10 border border-error/20 text-xs">
                            <p class="font-bold text-error uppercase tracking-wider mb-1">Fix before generating</p>
                            <ul class="text-error/90 list-disc list-inside space-y-0.5">
                                <li v-for="(issue, i) in blockingIssues" :key="i">{{ issue }}</li>
                            </ul>
                            <Link :href="route('questions.index')" class="link link-primary text-[11px] mt-1 inline-block">
                                → Add more questions to the bank
                            </Link>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
