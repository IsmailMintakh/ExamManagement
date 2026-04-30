<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeftIcon, PencilSquareIcon } from '@heroicons/vue/24/outline'

defineProps({ question: Object })

const letters = ['A', 'B', 'C', 'D', 'E', 'F']

const typeLabels = {
    mcq: 'Multiple Choice',
    short_answer: 'Short Answer',
    long_answer: 'Long Answer',
    true_false: 'True / False',
    fill_blank: 'Fill in the Blank',
}
</script>

<template>
    <Head :title="`Question #${question.id}`" />
    <AppLayout :breadcrumbs="[
        { label: 'Question Bank', href: route('questions.index') },
        { label: `#${question.id}` }
    ]">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-bold">Question #{{ question.id }}</h1>
            <div class="flex gap-2">
                <Link :href="route('questions.index')" class="btn btn-ghost btn-sm gap-2">
                    <ArrowLeftIcon class="w-4 h-4" /> Back
                </Link>
                <Link :href="route('questions.edit', question.id)" class="btn btn-primary btn-sm gap-2">
                    <PencilSquareIcon class="w-4 h-4" /> Edit
                </Link>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 card bg-base-100 shadow-md">
                <div class="card-body">
                    <div class="flex flex-wrap gap-2 mb-4">
                        <span class="badge badge-primary">{{ typeLabels[question.type] }}</span>
                        <span class="badge"
                            :class="question.difficulty === 'easy' ? 'badge-success' :
                                    question.difficulty === 'medium' ? 'badge-warning' : 'badge-error'">
                            {{ question.difficulty }}
                        </span>
                        <span class="badge badge-ghost">{{ question.marks }} marks</span>
                        <span v-if="question.topic" class="badge badge-outline">{{ question.topic }}</span>
                    </div>

                    <p class="text-base whitespace-pre-wrap">{{ question.question_text }}</p>

                    <div v-if="question.type === 'mcq'" class="mt-5 space-y-2">
                        <div v-for="(opt, i) in question.options" :key="i"
                            class="flex items-start gap-3 p-3 rounded-lg border"
                            :class="opt.is_correct ? 'border-success bg-success/5' : 'border-base-200'">
                            <span class="font-bold">{{ letters[i] }})</span>
                            <span class="flex-1">{{ opt.text }}</span>
                            <span v-if="opt.is_correct" class="text-success font-semibold text-sm">✓ Correct</span>
                        </div>
                    </div>

                    <div v-else-if="question.type === 'true_false'" class="mt-5">
                        <p class="text-sm font-semibold">Correct: <span class="text-success">{{ question.correct_answer }}</span></p>
                    </div>

                    <div v-else class="mt-5 p-4 rounded-lg bg-success/5 border border-success/20">
                        <p class="text-xs uppercase tracking-wider font-bold text-success/70 mb-2">Expected Answer</p>
                        <p class="text-sm whitespace-pre-wrap">{{ question.correct_answer }}</p>
                    </div>

                    <div v-if="question.explanation" class="mt-4 p-4 rounded-lg bg-info/5 border border-info/20">
                        <p class="text-xs uppercase tracking-wider font-bold text-info/70 mb-2">Explanation</p>
                        <p class="text-sm whitespace-pre-wrap">{{ question.explanation }}</p>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-base-content/60 mb-3">Meta</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-base-content/60">Subject</dt><dd class="font-medium">{{ question.subject?.name || '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-base-content/60">Class</dt><dd class="font-medium">{{ question.school_class?.name || 'All' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-base-content/60">Created by</dt><dd class="font-medium">{{ question.creator?.name || '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-base-content/60">Times used</dt><dd class="font-medium">{{ question.times_used }}</dd></div>
                        <div class="flex justify-between"><dt class="text-base-content/60">Active</dt>
                            <dd>
                                <span class="badge badge-sm" :class="question.is_active ? 'badge-success' : 'badge-ghost'">
                                    {{ question.is_active ? 'Yes' : 'No' }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
