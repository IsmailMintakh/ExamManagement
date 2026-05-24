<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import FormInput from '@/Components/FormInput.vue'
import FormSelect from '@/Components/FormSelect.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
    SparklesIcon, PrinterIcon, BoltIcon, AcademicCapIcon,
    CheckCircleIcon, ClipboardDocumentListIcon, ClockIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    subjects: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
    aiEnabled: { type: Boolean, default: false },
    defaults: { type: Object, default: () => ({}) },
    plan: { type: Object, default: null },
    input: { type: Object, default: null },
})

const form = useForm({
    topic: props.input?.topic || '',
    subject_id: props.input?.subject_id || props.defaults?.subject_id || '',
    school_class_id: props.input?.school_class_id || props.defaults?.school_class_id || '',
    duration: props.input?.duration || 40,
    medium: props.input?.medium || 'English',
    level: props.input?.level || 'mixed-ability',
    language: props.input?.language || 'en',
    notes: props.input?.notes || '',
    // Official Gilgit-Baltistan SMART LESSON PLAN meta — shown in the PDF.
    section: props.input?.section || '',
    students_count: props.input?.students_count || '',
    lesson_date: props.input?.lesson_date || new Date().toISOString().slice(0, 10),
})

const LANGUAGES = [
    { value: 'en', label: 'English' },
    { value: 'ur', label: 'Urdu / اردو' },
    { value: 'both', label: 'Both (English + Urdu)' },
]

function generate() {
    form.post(route('lesson-plan.generate'), { preserveScroll: true })
}

const csrf = computed(() =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '')

const planJson = computed(() => props.plan ? JSON.stringify(props.plan) : '')

// When the topic content itself is Urdu, render it right-to-left.
const urduPrimary = computed(() =>
    props.plan?.content?.is_urdu || props.plan?.language === 'ur')

const LEVELS = [
    { value: 'mixed-ability', label: 'Mixed ability' },
    { value: 'below grade level', label: 'Below grade level' },
    { value: 'at grade level', label: 'At grade level' },
    { value: 'advanced', label: 'Advanced' },
]
</script>

<template>
    <Head title="Smart Lesson Plan" />
    <AppLayout :breadcrumbs="[{ label: 'Smart Lesson Plan' }]">
        <div class="space-y-4 max-w-4xl mx-auto">

            <PageHeader title="Smart lesson plan"
                subtitle="Enter a topic, subject &amp; class — get a ready-to-teach plan"
                :icon="SparklesIcon" tone="violet">
                <template #actions>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-semibold"
                        :class="aiEnabled ? 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300'
                            : 'bg-amber-500/12 text-amber-700 dark:text-amber-300'">
                        <span class="w-1.5 h-1.5 rounded-full" :class="aiEnabled ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                        {{ aiEnabled ? 'AI enabled' : 'Template mode' }}
                    </span>
                </template>
            </PageHeader>

            <!-- Input form -->
            <form @submit.prevent="generate"
                class="rounded-xl border border-base-300 bg-base-100 shadow-sm p-4 space-y-4">
                <FormInput v-model="form.topic" label="Topic" required
                    :error="form.errors.topic"
                    placeholder="e.g. Photosynthesis, Quadratic Equations, The Mughal Empire" />
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <FormSelect v-model="form.subject_id" label="Subject" required
                        :error="form.errors.subject_id"
                        :options="subjects.map(s => ({ value: s.id, label: s.name }))"
                        placeholder="Select subject" />
                    <FormSelect v-model="form.school_class_id" label="Class" required
                        :error="form.errors.school_class_id"
                        :options="classes.map(c => ({ value: c.id, label: c.name }))"
                        placeholder="Select class" />
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <FormInput v-model="form.duration" type="number" label="Lesson duration (min)"
                        :error="form.errors.duration" />
                    <FormSelect v-model="form.language" label="Plan language"
                        :options="LANGUAGES" />
                    <FormInput v-model="form.medium" label="Medium of instruction"
                        placeholder="English / Urdu" />
                    <FormSelect v-model="form.level" label="Learner level"
                        :options="LEVELS" />
                </div>
                <!-- Official SMART LESSON PLAN meta (used in the PDF) -->
                <div class="rounded-lg border border-base-300 bg-base-200/30 p-3 space-y-2">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-base-content/55">For the printed lesson plan (Gilgit-Baltistan format)</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <FormInput v-model="form.section" label="Section" placeholder="e.g. A" />
                        <FormInput v-model="form.lesson_date" type="date" label="Lesson date" />
                        <FormInput v-model="form.students_count" type="number" label="No. of students" placeholder="e.g. 28" />
                    </div>
                </div>
                <div>
                    <label class="text-[12px] font-semibold text-base-content/75 mb-1.5 block">
                        Write something about the topic <span class="text-base-content/45">(optional) · موضوع کے بارے میں نوٹ</span>
                    </label>
                    <textarea v-model="form.notes" rows="3"
                        class="textarea textarea-bordered w-full rounded-lg text-sm"
                        placeholder="Any specific points, examples, definitions or instructions you want included in the plan…"></textarea>
                </div>
                <p v-if="!subjects.length" class="rounded-lg bg-amber-500/10 text-amber-700 dark:text-amber-300 text-xs px-3 py-2">
                    No subjects are assigned to you yet — ask the admin to assign your subjects/classes in Teacher Assignments.
                </p>
                <div class="flex items-center justify-between gap-3 pt-1">
                    <p class="text-[11px] text-base-content/45">
                        {{ aiEnabled ? 'Written by AI for your exact topic.' : 'Add ANTHROPIC_API_KEY in .env for true AI plans — a structured template is used meanwhile.' }}
                    </p>
                    <button type="submit" :disabled="form.processing"
                        class="btn btn-primary btn-sm rounded-lg gap-1.5 shrink-0">
                        <BoltIcon class="w-4 h-4" />
                        {{ form.processing ? 'Generating…' : (plan ? 'Regenerate' : 'Generate plan') }}
                    </button>
                </div>
            </form>

            <!-- Generated plan -->
            <template v-if="plan">
                <div class="rounded-xl border border-base-300 bg-base-100 shadow-sm overflow-hidden">
                    <header class="px-4 py-3 border-b border-base-300 bg-base-200/30 flex items-center gap-2 flex-wrap">
                        <AcademicCapIcon class="w-4 h-4 text-violet-600 dark:text-violet-400" />
                        <h2 class="text-sm font-bold">{{ plan.topic }}</h2>
                        <span class="text-[11px] text-base-content/55">
                            {{ plan.subject }} · {{ plan.class }} · {{ plan.duration_minutes }} min
                        </span>
                        <span class="ml-auto text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded"
                            :class="plan.generated_by === 'ai'
                                ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                : plan.generated_by === 'reference'
                                ? 'bg-sky-500/15 text-sky-700 dark:text-sky-300'
                                : 'bg-amber-500/15 text-amber-700 dark:text-amber-300'">
                            {{ plan.generated_by === 'ai' ? 'AI generated'
                                : plan.generated_by === 'reference' ? 'Sourced (Wikipedia)' : 'Template' }}
                        </span>
                        <!-- Real form POST so the browser downloads the PDF -->
                        <form :action="route('lesson-plan.pdf')" method="POST" target="_blank" class="inline">
                            <input type="hidden" name="_token" :value="csrf" />
                            <input type="hidden" name="plan" :value="planJson" />
                            <input type="hidden" name="topic" :value="plan.topic" />
                            <input type="hidden" name="subject" :value="plan.subject" />
                            <input type="hidden" name="class" :value="plan.class" />
                            <input type="hidden" name="duration" :value="plan.duration_minutes" />
                            <input type="hidden" name="section" :value="form.section" />
                            <input type="hidden" name="students_count" :value="form.students_count" />
                            <input type="hidden" name="lesson_date" :value="form.lesson_date" />
                            <input type="hidden" name="language" :value="plan.language || form.language || 'en'" />
                            <button type="submit" class="btn btn-outline btn-xs rounded-lg gap-1.5">
                                <PrinterIcon class="w-3.5 h-3.5" /> Download
                            </button>
                        </form>
                    </header>

                    <div class="p-4 space-y-5 text-sm" :dir="urduPrimary ? 'rtl' : 'ltr'" :lang="urduPrimary ? 'ur' : 'en'"
                        :class="urduPrimary ? 'leading-7' : ''">
                        <!-- Official GB Schools Education Department format —
                             three sections: Outcomes / Content Knowledge / Homework. -->

                        <!-- 1. Student Learning Outcomes -->
                        <section>
                            <h3 class="font-bold text-[13px] mb-1.5 flex items-center gap-1.5">
                                <CheckCircleIcon class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                1. Student Learning Outcomes
                                <span class="font-normal text-[11px] text-base-content/55">(Focus on subject and topics)</span>
                            </h3>
                            <ol class="space-y-1 text-base-content/80" :class="urduPrimary ? 'mr-6' : 'ml-6'">
                                <li v-for="(o, i) in plan.objectives" :key="i" class="flex gap-2">
                                    <span class="font-bold text-base-content/55 w-5 shrink-0">{{ ['i','ii','iii','iv','v','vi'][i] || (i+1) }}.</span>
                                    <span>{{ o }}</span>
                                </li>
                            </ol>
                        </section>

                        <!-- 2. Content Knowledge -->
                        <section v-if="plan.content" class="rounded-lg border border-violet-500/20 bg-violet-500/[0.04] p-3.5 space-y-3">
                            <h3 class="font-bold text-[13px] flex items-center gap-1.5">
                                <SparklesIcon class="w-4 h-4 text-violet-600 dark:text-violet-400" />
                                2. Content Knowledge
                            </h3>

                            <div v-if="plan.content.introduction">
                                <p class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 mb-0.5">Introduction</p>
                                <p class="text-base-content/80">{{ plan.content.introduction }}</p>
                            </div>

                            <div v-if="plan.content.definition">
                                <p class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 mb-0.5">Definition</p>
                                <p class="text-base-content/80">{{ plan.content.definition }}</p>
                            </div>

                            <div v-if="plan.content.characteristics?.length">
                                <p class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 mb-0.5">Characteristics</p>
                                <ul class="list-disc space-y-0.5 text-base-content/80" :class="urduPrimary ? 'mr-5' : 'ml-5'">
                                    <li v-for="(c, i) in plan.content.characteristics" :key="i">{{ c }}</li>
                                </ul>
                            </div>

                            <div v-if="plan.content.examples?.length">
                                <p class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 mb-0.5">Examples</p>
                                <ol class="list-decimal space-y-0.5 text-base-content/80" :class="urduPrimary ? 'mr-5' : 'ml-5'">
                                    <li v-for="(e, i) in plan.content.examples" :key="i">{{ e }}</li>
                                </ol>
                            </div>

                            <div v-if="plan.content.class_activity">
                                <p class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 mb-0.5">Class Activity</p>
                                <p class="text-base-content/80">{{ plan.content.class_activity }}</p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div v-if="plan.content.teaching_methods?.length">
                                    <p class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 mb-0.5">Teaching Method</p>
                                    <ul class="list-disc space-y-0.5 text-base-content/80" :class="urduPrimary ? 'mr-5' : 'ml-5'">
                                        <li v-for="(m, i) in plan.content.teaching_methods" :key="i">{{ m }}</li>
                                    </ul>
                                </div>
                                <div v-if="plan.content.teaching_aids?.length">
                                    <p class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 mb-0.5">Teaching Aids</p>
                                    <ul class="list-disc space-y-0.5 text-base-content/80" :class="urduPrimary ? 'mr-5' : 'ml-5'">
                                        <li v-for="(a, i) in plan.content.teaching_aids" :key="i">{{ a }}</li>
                                    </ul>
                                </div>
                            </div>

                            <div v-if="plan.content.teacher_notes"
                                class="rounded-md border border-amber-500/30 bg-amber-500/[0.06] p-2.5 mt-2">
                                <p class="text-[11px] font-bold text-amber-700 dark:text-amber-300 mb-0.5">Teacher's note</p>
                                <p class="text-[12px] text-base-content/75 whitespace-pre-line">{{ plan.content.teacher_notes }}</p>
                            </div>
                        </section>

                        <!-- 3. Homework -->
                        <section v-if="plan.homework">
                            <h3 class="font-bold text-[13px] mb-1.5 flex items-center gap-1.5">
                                <ClipboardDocumentListIcon class="w-4 h-4 text-sky-600 dark:text-sky-400" />
                                3. Homework
                            </h3>
                            <ol class="list-decimal space-y-1 text-base-content/80" :class="urduPrimary ? 'mr-6' : 'ml-6'">
                                <template v-if="Array.isArray(plan.homework)">
                                    <li v-for="(h, i) in plan.homework" :key="i">{{ h }}</li>
                                </template>
                                <li v-else>{{ plan.homework }}</li>
                            </ol>
                        </section>
                    </div>
                </div>
            </template>

            <div v-else class="rounded-xl border border-base-300 bg-base-100 shadow-sm p-10 text-center">
                <ClipboardDocumentListIcon class="w-10 h-10 text-base-content/25 mx-auto mb-2" />
                <p class="text-sm text-base-content/55">Fill the form and generate a lesson plan.</p>
            </div>
        </div>
    </AppLayout>
</template>
