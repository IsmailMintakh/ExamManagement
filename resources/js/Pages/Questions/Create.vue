<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import { invalidatePageCache } from '@/Composables/useCacheInvalidation'
import {
    PlusIcon, TrashIcon, ArrowLeftIcon, CheckIcon,
    ListBulletIcon, PencilSquareIcon, DocumentTextIcon,
    CheckCircleIcon, MinusCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    question: Object,
    subjects: Array,
    classes: Array,
    topics: Array,
})

const isEdit = !!props.question

const form = useForm({
    type: props.question?.type || 'mcq',
    subject_id: props.question?.subject_id || '',
    school_class_id: props.question?.school_class_id || '',
    difficulty: props.question?.difficulty || 'medium',
    question_text: props.question?.question_text || '',
    options: props.question?.options || [
        { text: '', is_correct: true },
        { text: '', is_correct: false },
    ],
    correct_answer: props.question?.correct_answer || '',
    explanation: props.question?.explanation || '',
    marks: props.question?.marks ?? 1,
    topic: props.question?.topic || '',
    is_active: props.question?.is_active ?? true,
})

const typeTiles = [
    { value: 'mcq', label: 'MCQ', desc: 'Multiple choice', icon: ListBulletIcon, color: 'primary' },
    { value: 'true_false', label: 'True / False', desc: 'Binary answer', icon: CheckCircleIcon, color: 'success' },
    { value: 'short_answer', label: 'Short', desc: '2–3 lines', icon: PencilSquareIcon, color: 'info' },
    { value: 'long_answer', label: 'Long', desc: '1+ paragraph', icon: DocumentTextIcon, color: 'warning' },
    { value: 'fill_blank', label: 'Fill Blank', desc: 'One word / phrase', icon: MinusCircleIcon, color: 'secondary' },
]

const difficultyChips = [
    { value: 'easy', label: 'Easy', color: 'success' },
    { value: 'medium', label: 'Medium', color: 'warning' },
    { value: 'hard', label: 'Hard', color: 'error' },
]

watch(() => form.type, (t, prev) => {
    if (t === prev) return
    if (t === 'mcq' && !form.options?.length) {
        form.options = [
            { text: '', is_correct: true },
            { text: '', is_correct: false },
        ]
    }
    if (t === 'true_false' && !['True', 'False'].includes(form.correct_answer)) {
        form.correct_answer = 'True'
    }
})

const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G']

function addOption() {
    if (form.options.length >= 6) return
    form.options.push({ text: '', is_correct: false })
}
function removeOption(i) {
    if (form.options.length <= 2) return
    const wasCorrect = form.options[i].is_correct
    form.options.splice(i, 1)
    if (wasCorrect && form.options.length) form.options[0].is_correct = true
}
function setCorrect(i) {
    form.options = form.options.map((o, idx) => ({ ...o, is_correct: idx === i }))
}

function submit() {
    // Drop SW page cache after a successful save so the index page
    // immediately reflects the new/updated question on PWA mobile.
    const opts = { onSuccess: () => invalidatePageCache() }
    if (isEdit) form.put(route('questions.update', props.question.id), opts)
    else form.post(route('questions.store'), opts)
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Question' : 'Add Question'" />
    <AppLayout :breadcrumbs="[
        { label: 'Question Bank', href: route('questions.index') },
        { label: isEdit ? 'Edit' : 'Add' }
    ]">
        <div class="max-w-3xl mx-auto">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-bold">{{ isEdit ? 'Edit Question' : 'Add Question' }}</h1>
                    <p class="text-sm text-base-content/60 mt-0.5">Fill in the essentials — the rest has smart defaults.</p>
                </div>
                <Link :href="route('questions.index')" class="btn btn-ghost btn-sm gap-2">
                    <ArrowLeftIcon class="w-4 h-4" /> Back
                </Link>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <!-- Type tile picker -->
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider mb-2">Question Type</label>
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                            <label v-for="t in typeTiles" :key="t.value"
                                class="cursor-pointer rounded-lg border-2 p-3 text-center transition-all"
                                :class="form.type === t.value
                                    ? `border-${t.color} bg-${t.color}/5`
                                    : 'border-base-200 hover:border-base-300 hover:bg-base-200/40'">
                                <input type="radio" v-model="form.type" :value="t.value" class="hidden" />
                                <component :is="t.icon" class="w-6 h-6 mx-auto mb-1"
                                    :class="form.type === t.value ? `text-${t.color}` : 'text-base-content/50'" />
                                <div class="text-xs font-bold">{{ t.label }}</div>
                                <div class="text-[10px] text-base-content/50 hidden sm:block">{{ t.desc }}</div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Question -->
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4 space-y-4">
                        <div>
                            <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider">
                                Your Question <span class="text-error">*</span>
                            </label>
                            <textarea v-model="form.question_text" required rows="3"
                                placeholder="Type your question here..."
                                class="textarea textarea-bordered w-full mt-1.5 text-base"></textarea>
                            <p v-if="form.errors.question_text" class="mt-1 text-xs text-error">{{ form.errors.question_text }}</p>
                        </div>

                        <!-- MCQ Options -->
                        <div v-if="form.type === 'mcq'">
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider">
                                    Answer Options <span class="text-error">*</span>
                                </label>
                                <button type="button" @click="addOption" v-if="form.options.length < 6"
                                    class="btn btn-ghost btn-xs gap-1">
                                    <PlusIcon class="w-3.5 h-3.5" /> Add
                                </button>
                            </div>
                            <div class="space-y-1.5">
                                <div v-for="(opt, i) in form.options" :key="i"
                                    class="flex items-center gap-2 rounded-lg border p-2 transition-all"
                                    :class="opt.is_correct ? 'border-success bg-success/5' : 'border-base-200'">
                                    <button type="button" @click="setCorrect(i)"
                                        class="flex h-7 w-7 items-center justify-center rounded-full border-2 shrink-0 transition-all"
                                        :class="opt.is_correct ? 'border-success bg-success text-white' : 'border-base-300 text-base-content/40 hover:border-success/50'"
                                        :title="opt.is_correct ? 'Correct' : 'Mark as correct'">
                                        <CheckIcon v-if="opt.is_correct" class="w-4 h-4" />
                                        <span v-else class="text-xs font-bold">{{ letters[i] }}</span>
                                    </button>
                                    <input type="text" v-model="opt.text"
                                        :placeholder="`Option ${letters[i]}`"
                                        class="input input-ghost input-sm flex-1 focus:outline-none" />
                                    <button type="button" v-if="form.options.length > 2" @click="removeOption(i)"
                                        class="btn btn-ghost btn-xs btn-square text-base-content/40 hover:text-error">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                            <p v-if="form.errors.options" class="mt-1 text-xs text-error">{{ form.errors.options }}</p>
                            <p class="mt-2 text-[11px] text-base-content/50">Click the letter to mark it as the correct answer.</p>
                        </div>

                        <!-- True / False -->
                        <div v-if="form.type === 'true_false'">
                            <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider">
                                Correct Answer <span class="text-error">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-2 mt-1.5">
                                <label class="cursor-pointer border-2 rounded-lg py-3 text-center font-semibold transition-all"
                                    :class="form.correct_answer === 'True' ? 'bg-success/10 border-success text-success' : 'border-base-200 hover:bg-base-200/40'">
                                    <input type="radio" v-model="form.correct_answer" value="True" class="hidden" />
                                    ✓ True
                                </label>
                                <label class="cursor-pointer border-2 rounded-lg py-3 text-center font-semibold transition-all"
                                    :class="form.correct_answer === 'False' ? 'bg-error/10 border-error text-error' : 'border-base-200 hover:bg-base-200/40'">
                                    <input type="radio" v-model="form.correct_answer" value="False" class="hidden" />
                                    ✗ False
                                </label>
                            </div>
                        </div>

                        <!-- Short / Long / Fill -->
                        <div v-if="['short_answer', 'long_answer', 'fill_blank'].includes(form.type)">
                            <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider">
                                {{ form.type === 'fill_blank' ? 'Fill with' : 'Model Answer' }} <span class="text-error">*</span>
                            </label>
                            <textarea v-model="form.correct_answer" required
                                :rows="form.type === 'long_answer' ? 4 : 2"
                                :placeholder="form.type === 'fill_blank' ? 'The word or phrase that fills the blank' : 'Example of a correct answer...'"
                                class="textarea textarea-bordered w-full mt-1.5"></textarea>
                            <p class="mt-1 text-[11px] text-base-content/50">This appears on the answer key PDF.</p>
                        </div>
                    </div>
                </div>

                <!-- Classification (compact row) -->
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4 space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider">Subject <span class="text-error">*</span></label>
                                <select v-model="form.subject_id" required class="select select-bordered select-sm w-full mt-1">
                                    <option value="">Choose subject...</option>
                                    <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                                <p v-if="form.errors.subject_id" class="mt-1 text-xs text-error">{{ form.errors.subject_id }}</p>
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider">Class <span class="text-base-content/40">(any)</span></label>
                                <select v-model="form.school_class_id" class="select select-bordered select-sm w-full mt-1">
                                    <option value="">Any class</option>
                                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider">Difficulty</label>
                                <div class="flex gap-1.5 mt-1">
                                    <label v-for="d in difficultyChips" :key="d.value"
                                        class="flex-1 cursor-pointer border rounded-md py-1.5 text-center text-xs font-medium transition-all"
                                        :class="form.difficulty === d.value
                                            ? `bg-${d.color}/10 border-${d.color} text-${d.color}`
                                            : 'border-base-300 text-base-content/60 hover:bg-base-200'">
                                        <input type="radio" v-model="form.difficulty" :value="d.value" class="hidden" />
                                        {{ d.label }}
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider">Marks</label>
                                <input v-model.number="form.marks" type="number" step="0.25" min="0.25"
                                    class="input input-bordered input-sm w-full mt-1" />
                            </div>
                        </div>

                        <details class="text-sm">
                            <summary class="cursor-pointer text-base-content/60 hover:text-base-content select-none">
                                Advanced (optional topic &amp; explanation)
                            </summary>
                            <div class="mt-3 space-y-3 pl-1">
                                <div>
                                    <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider">Topic / Chapter</label>
                                    <input v-model="form.topic" type="text" list="topic-list"
                                        placeholder="e.g., Chapter 3 — Algebra"
                                        class="input input-bordered input-sm w-full mt-1" />
                                    <datalist id="topic-list">
                                        <option v-for="t in topics" :key="t" :value="t" />
                                    </datalist>
                                </div>
                                <div>
                                    <label class="text-[11px] font-semibold text-base-content/65 uppercase tracking-wider">Explanation (for answer key)</label>
                                    <textarea v-model="form.explanation" rows="2"
                                        placeholder="Why is this the correct answer?"
                                        class="textarea textarea-bordered w-full mt-1 text-sm"></textarea>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>

                <!-- Action bar -->
                <div class="sticky bottom-0 flex items-center justify-between gap-2 bg-base-100 border-t border-base-200 p-3 rounded-lg shadow-lg">
                    <Link :href="route('questions.index')" class="btn btn-ghost btn-sm">Cancel</Link>
                    <button type="submit" class="btn btn-primary gap-2" :disabled="form.processing">
                        <CheckIcon class="w-5 h-5" />
                        {{ form.processing ? 'Saving...' : (isEdit ? 'Update' : 'Save Question') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
