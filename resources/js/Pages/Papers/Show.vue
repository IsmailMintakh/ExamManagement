<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    ArrowLeftIcon, ArrowDownTrayIcon, KeyIcon, ArrowPathIcon, TrashIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    paper: Object,
    questions: Object,
})

const letters = ['A', 'B', 'C', 'D', 'E', 'F']
const romanMap = ['I','II','III','IV','V','VI','VII','VIII','IX','X']

const confirmDelete = ref(false)

function questionById(id) {
    return props.questions?.[id] || null
}

function regenerate() {
    router.post(route('papers.regenerate', props.paper.id))
}

function doDelete() {
    router.delete(route('papers.destroy', props.paper.id), {
        onSuccess: () => { confirmDelete.value = false }
    })
}

let globalQNum = 0
function resetNum() { globalQNum = 0 }
function nextNum() { return ++globalQNum }
</script>

<template>
    <Head :title="paper.title" />
    <AppLayout :breadcrumbs="[
        { label: 'Paper Generator', href: route('papers.index') },
        { label: paper.title }
    ]">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-5">
            <div>
                <h1 class="text-2xl font-bold">{{ paper.title }}</h1>
                <p v-if="paper.exam_name" class="text-sm text-base-content/60 mt-1">{{ paper.exam_name }}</p>
                <div class="flex flex-wrap gap-2 mt-2">
                    <span class="badge badge-primary badge-sm">Set {{ paper.set_code }}</span>
                    <span class="badge badge-ghost badge-sm">{{ paper.subject?.name }}</span>
                    <span v-if="paper.school_class?.name" class="badge badge-ghost badge-sm">{{ paper.school_class.name }}</span>
                    <span class="badge badge-ghost badge-sm">{{ paper.duration_minutes }} min</span>
                    <span class="badge badge-ghost badge-sm">{{ paper.total_marks }} marks</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('papers.index')" class="btn btn-ghost btn-sm gap-2">
                    <ArrowLeftIcon class="w-4 h-4" /> Back
                </Link>
                <a :href="route('papers.download', paper.id)" target="_blank" class="btn btn-primary btn-sm gap-2">
                    <ArrowDownTrayIcon class="w-4 h-4" /> Download Paper
                </a>
                <a :href="route('papers.answer-key', paper.id)" target="_blank" class="btn btn-success btn-sm gap-2">
                    <KeyIcon class="w-4 h-4" /> Answer Key
                </a>
                <button @click="regenerate" class="btn btn-info btn-sm gap-2">
                    <ArrowPathIcon class="w-4 h-4" /> New Set
                </button>
                <button @click="confirmDelete = true" class="btn btn-outline btn-error btn-sm gap-2">
                    <TrashIcon class="w-4 h-4" /> Delete
                </button>
            </div>
        </div>

        <!-- Preview -->
        <div class="card bg-base-100 shadow-md">
            <div class="card-body">
                <div v-if="paper.instructions" class="mb-5 rounded-lg border border-dashed border-base-300 bg-base-200/40 p-4">
                    <h4 class="text-xs uppercase tracking-wider font-bold text-base-content/55 mb-2">Instructions</h4>
                    <p class="text-sm whitespace-pre-wrap">{{ paper.instructions }}</p>
                </div>

                <div v-for="(section, sIdx) in paper.sections" :key="sIdx" class="mb-6">
                    <div class="border-t-2 border-b-2 border-base-content/20 py-2 my-4 text-center">
                        <h3 class="text-sm font-bold uppercase tracking-widest">
                            Section {{ romanMap[sIdx] || (sIdx + 1) }} — {{ section.label }}
                        </h3>
                        <p class="text-xs text-base-content/55 mt-0.5">
                            {{ section.count }} &times; {{ section.marks_each }} = {{ (section.count * section.marks_each).toFixed(2) }} marks
                            <span v-if="section.difficulty && section.difficulty !== 'mixed'"> · {{ section.difficulty }}</span>
                        </p>
                    </div>

                    <div class="space-y-4">
                        <template v-for="(qEntry, qIdx) in section.questions" :key="qIdx">
                            <div v-if="questionById(qEntry.question_id)" class="text-sm">
                                <div class="flex items-start justify-between gap-4">
                                    <p class="flex-1">
                                        <span class="font-semibold">Q{{ nextNum() }}.</span>
                                        {{ questionById(qEntry.question_id).question_text }}
                                    </p>
                                    <span class="font-mono text-xs text-base-content/60 shrink-0">[{{ qEntry.marks }}]</span>
                                </div>

                                <!-- MCQ options -->
                                <div v-if="questionById(qEntry.question_id).type === 'mcq'"
                                     class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1 pl-7">
                                    <template v-for="(origIdx, pos) in (qEntry.option_order || Object.keys(questionById(qEntry.question_id).options || {}))"
                                              :key="pos">
                                        <div class="text-sm">
                                            <span class="font-semibold">{{ letters[pos] }})</span>
                                            {{ questionById(qEntry.question_id).options?.[origIdx]?.text || '' }}
                                        </div>
                                    </template>
                                </div>

                                <!-- True/False -->
                                <div v-else-if="questionById(qEntry.question_id).type === 'true_false'"
                                     class="mt-1 pl-7 text-sm text-base-content/70">
                                    <span class="mr-6">(a) True</span>
                                    <span>(b) False</span>
                                </div>

                                <!-- Answer space placeholder -->
                                <div v-else-if="questionById(qEntry.question_id).type === 'short_answer'"
                                     class="mt-2 pl-7 space-y-2">
                                    <div class="h-3 border-b border-base-300"></div>
                                    <div class="h-3 border-b border-base-300"></div>
                                </div>
                                <div v-else-if="questionById(qEntry.question_id).type === 'long_answer'"
                                     class="mt-2 pl-7 space-y-2">
                                    <div v-for="n in 5" :key="n" class="h-3 border-b border-base-300"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <p class="text-center text-xs font-bold uppercase tracking-[4px] text-base-content/50 mt-8">— End of Paper —</p>
            </div>
        </div>

        <ConfirmDialog
            :show="confirmDelete"
            title="Delete paper?"
            message="The generated paper record will be removed. Questions in the bank are not affected."
            confirm-text="Delete"
            type="danger"
            @confirm="doDelete"
            @cancel="confirmDelete = false"
        />
    </AppLayout>
</template>
