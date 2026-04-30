<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormSelect from '@/Components/FormSelect.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeftIcon, ArrowDownTrayIcon, DocumentArrowUpIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    subjects: Array,
    classes: Array,
})

const form = useForm({
    file: null,
    school_class_id: '',
})

function onFile(e) {
    form.file = e.target.files?.[0] || null
}

function submit() {
    form.post(route('questions.process-import'), { forceFormData: true })
}

function downloadTemplate() {
    const header = [
        'type', 'question_text',
        'option_1', 'option_2', 'option_3', 'option_4',
        'correct_option_index', 'correct_answer',
        'subject_code', 'difficulty', 'marks', 'topic'
    ]
    const sample = [
        ['mcq', 'What is 2 + 2?', '3', '4', '5', '6', '2', '', 'MATH', 'easy', '1', 'Arithmetic'],
        ['short_answer', 'Define photosynthesis in one line.', '', '', '', '', '', 'The process by which plants convert light into energy.', 'SCI', 'medium', '2', 'Biology'],
        ['true_false', 'The earth is flat.', '', '', '', '', '', 'False', 'SCI', 'easy', '1', ''],
        ['fill_blank', 'The capital of France is ______.', '', '', '', '', '', 'Paris', 'GK', 'easy', '1', 'Geography'],
        ['long_answer', 'Explain the causes of World War II.', '', '', '', '', '', 'Economic collapse, nationalism, appeasement policy, Treaty of Versailles...', 'HIS', 'hard', '5', 'Modern History'],
    ]
    const rows = [header, ...sample].map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n')
    const blob = new Blob([rows], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'questions-template.csv'
    a.click()
    URL.revokeObjectURL(url)
}
</script>

<template>
    <Head title="Import Questions" />
    <AppLayout :breadcrumbs="[
        { label: 'Question Bank', href: route('questions.index') },
        { label: 'Bulk Import' }
    ]">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-2xl font-bold">Bulk Import Questions</h1>
                <p class="text-sm text-base-content/60 mt-1">Upload a CSV to add many questions at once.</p>
            </div>
            <Link :href="route('questions.index')" class="btn btn-ghost btn-sm gap-2">
                <ArrowLeftIcon class="w-4 h-4" /> Back
            </Link>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 card bg-base-100 shadow-md">
                <div class="card-body">
                    <form @submit.prevent="submit" class="space-y-4">
                        <FormSelect v-model="form.school_class_id" label="Default Class (optional)"
                            :options="[{ value: '', label: 'Any / unscoped' }, ...(classes?.map(c => ({ value: c.id, label: c.name })) || [])]"
                            placeholder="Any class" />

                        <div>
                            <label class="text-[12px] font-semibold text-base-content/75">CSV File <span class="text-error">*</span></label>
                            <div class="mt-1.5 border-2 border-dashed border-base-300 rounded-xl p-6 text-center">
                                <DocumentArrowUpIcon class="mx-auto h-10 w-10 text-base-content/30" />
                                <p class="mt-2 text-sm text-base-content/60">Drop your CSV here, or click to browse</p>
                                <input type="file" accept=".csv,text/csv" @change="onFile"
                                    class="file-input file-input-bordered file-input-sm w-full max-w-xs mt-3 mx-auto" />
                                <p v-if="form.file" class="mt-2 text-xs text-success">{{ form.file.name }}</p>
                            </div>
                            <p v-if="form.errors.file" class="mt-1.5 text-xs text-error">{{ form.errors.file }}</p>
                        </div>

                        <div class="flex gap-2 justify-end">
                            <button type="button" @click="downloadTemplate" class="btn btn-outline gap-2">
                                <ArrowDownTrayIcon class="w-4 h-4" /> Download Template
                            </button>
                            <button type="submit" :disabled="!form.file || form.processing" class="btn btn-primary">
                                {{ form.processing ? 'Importing...' : 'Import Questions' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-base-content/60 mb-3">CSV Format</h3>
                    <ul class="text-xs space-y-2 text-base-content/70">
                        <li><strong>type</strong> — mcq / short_answer / long_answer / true_false / fill_blank</li>
                        <li><strong>question_text</strong> — the question</li>
                        <li><strong>option_1..option_4</strong> — MCQ options (blank for non-MCQ)</li>
                        <li><strong>correct_option_index</strong> — 1-based index for MCQ (e.g., 2 = option B)</li>
                        <li><strong>correct_answer</strong> — answer for non-MCQ types; for true_false use True / False</li>
                        <li><strong>subject_code</strong> — subject code or name</li>
                        <li><strong>difficulty</strong> — easy / medium / hard</li>
                        <li><strong>marks</strong> — numeric, defaults to 1</li>
                        <li><strong>topic</strong> — optional chapter / category</li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
