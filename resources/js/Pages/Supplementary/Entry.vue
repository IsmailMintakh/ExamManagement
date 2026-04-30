<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { reactive, computed } from 'vue'
import {
    ArrowPathIcon,
    ArrowUturnLeftIcon,
    PaperAirplaneIcon,
    AcademicCapIcon,
    InformationCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    result: { type: Object, required: true },
    exam: { type: Object, required: true },
    subjects: { type: Array, default: () => [] },
})

const initialMarks = {}
props.subjects.forEach((s) => {
    initialMarks[s.subject_id] = s.supplementary_marks ?? ''
})

const form = useForm({
    marks: reactive(initialMarks),
})

const hasAnyMark = computed(() =>
    Object.values(form.marks).some((v) => v !== '' && v !== null && v !== undefined)
)

function submit() {
    form.post(route('supplementary.store-marks', props.result.id), {
        preserveScroll: true,
    })
}

function fieldError(subjectId) {
    return form.errors[`marks.${subjectId}`]
}
</script>

<template>
    <Head :title="`Supplementary Marks - ${result.student_name}`" />
    <AppLayout
        :breadcrumbs="[
            { label: 'Supplementary', href: '/supplementary' },
            { label: exam.name, href: `/supplementary/exams/${exam.id}` },
            { label: result.student_name },
        ]"
    >
        <div class="space-y-5">
            <!-- Header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <ArrowPathIcon class="h-6 w-6 text-primary" />
                        Enter Supplementary Marks
                    </h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        Record marks for the student's failed subjects only.
                    </p>
                </div>
                <Link :href="route('supplementary.show', exam.id)" class="btn btn-ghost btn-sm gap-2">
                    <ArrowUturnLeftIcon class="h-4 w-4" />
                    Back
                </Link>
            </div>

            <!-- Student Info Card -->
            <div class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body p-5 flex flex-row items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <AcademicCapIcon class="h-6 w-6" />
                    </div>
                    <div class="flex-1">
                        <h2 class="text-base font-bold">{{ result.student_name }}</h2>
                        <p class="text-xs text-base-content/60 mt-0.5">
                            Roll: {{ result.roll_no || '-' }}
                            <span v-if="result.class_name"> · {{ result.class_name }}</span>
                            <span v-if="result.section_name"> · Section {{ result.section_name }}</span>
                        </p>
                    </div>
                    <span
                        class="badge badge-sm"
                        :class="result.supplementary_status === 'appeared' ? 'badge-info' : 'badge-warning'"
                    >
                        {{ result.supplementary_status }}
                    </span>
                </div>
            </div>

            <div v-if="!subjects.length" class="alert alert-warning">
                <InformationCircleIcon class="h-5 w-5 shrink-0" />
                <span class="text-sm">There are no failed subjects flagged for this student.</span>
            </div>

            <!-- Marks Form -->
            <form v-if="subjects.length" @submit.prevent="submit" class="space-y-4">
                <div class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body p-0">
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th class="w-12">#</th>
                                        <th>Subject</th>
                                        <th class="w-24">Total</th>
                                        <th class="w-24">Passing</th>
                                        <th class="w-32">Previous</th>
                                        <th class="w-44">Supplementary Marks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(sub, i) in subjects" :key="sub.subject_id">
                                        <td>{{ i + 1 }}</td>
                                        <td>
                                            <div class="font-medium text-sm">{{ sub.name }}</div>
                                            <div v-if="sub.code" class="text-2xs text-base-content/50">{{ sub.code }}</div>
                                        </td>
                                        <td class="text-sm">{{ sub.total_marks }}</td>
                                        <td class="text-sm">{{ sub.passing_marks }}</td>
                                        <td class="text-sm text-base-content/60">
                                            {{ sub.previous_marks !== null ? sub.previous_marks : '—' }}
                                        </td>
                                        <td>
                                            <FormInput
                                                v-model="form.marks[sub.subject_id]"
                                                type="number"
                                                :placeholder="`0 - ${sub.total_marks}`"
                                                :error="fieldError(sub.subject_id)"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-sm border border-base-200 sticky bottom-4">
                    <div class="card-body p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <p class="text-xs text-base-content/60">
                            Leave blank to skip a subject. Saving will mark this result as <strong>Appeared</strong>.
                        </p>
                        <div class="flex gap-2 justify-end">
                            <Link :href="route('supplementary.show', exam.id)" class="btn btn-ghost btn-sm">Cancel</Link>
                            <button
                                type="submit"
                                class="btn btn-primary btn-sm gap-2"
                                :disabled="form.processing || !hasAnyMark"
                            >
                                <PaperAirplaneIcon class="h-4 w-4" />
                                Save Supplementary Marks
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
