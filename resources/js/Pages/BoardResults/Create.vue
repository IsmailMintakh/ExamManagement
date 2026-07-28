<script setup>
/**
 * Create a new FBISE board-exam container. School + class + session
 * define the unique tuple; the rest are display / config fields.
 */
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { AcademicCapIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    schools: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
    sessions: { type: Array, default: () => [] },
    gradingScales: { type: Array, default: () => [] },
    currentSessionId: { type: Number, default: null },
    defaultSchoolId: { type: Number, default: null },
})

const form = useForm({
    school_id: props.defaultSchoolId ?? (props.schools[0]?.id ?? null),
    school_class_id: null,
    academic_session_id: props.currentSessionId ?? (props.sessions[0]?.id ?? null),
    level: 'SSC',
    title: '',
    announced_on: null,
    total_marks: 550,
    pass_percentage: 33,
    // NULL = use hardcoded FBISE bands. Pick a scale to override.
    grading_scale_id: props.gradingScales.find(s => s.is_default)?.id ?? null,
    notes: '',
})

// Filter class picker to schools the user selected.
const classesForSchool = computed(() =>
    props.classes.filter(c => Number(c.school_id) === Number(form.school_id))
)

function submit() {
    form.post(route('board-results.store'))
}
</script>

<template>
    <Head title="New Board Exam" />
    <AppLayout :breadcrumbs="[
        { label: 'Board Results', href: route('board-results.index') },
        { label: 'New' }
    ]">
        <div class="space-y-4 max-w-3xl mx-auto">
            <PageHeader title="Create Board Exam"
                        subtitle="Set up a FBISE result container for one class + session."
                        :icon="AcademicCapIcon" tone="violet">
                <template #actions>
                    <Link :href="route('board-results.index')" class="btn btn-ghost btn-sm rounded-xl gap-1.5">
                        <ArrowLeftIcon class="w-4 h-4" /> Back
                    </Link>
                </template>
            </PageHeader>

            <form @submit.prevent="submit" class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm p-5 sm:p-6 space-y-4">
                <!-- School (super-admin) -->
                <div v-if="schools.length > 1" class="form-control">
                    <label class="label"><span class="label-text font-semibold">School</span></label>
                    <select v-model="form.school_id" class="select select-bordered w-full" required>
                        <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>

                <!-- Class + Session -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text font-semibold">Class</span></label>
                        <select v-model="form.school_class_id" class="select select-bordered w-full" required>
                            <option :value="null" disabled>Select class…</option>
                            <option v-for="c in classesForSchool" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-if="!classesForSchool.length" class="text-xs text-warning mt-1">
                            No secondary (9th / 10th) classes found for this school.
                        </p>
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text font-semibold">Academic Session</span></label>
                        <select v-model="form.academic_session_id" class="select select-bordered w-full" required>
                            <option v-for="s in sessions" :key="s.id" :value="s.id">
                                {{ s.name }} <span v-if="s.is_current">(current)</span>
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Level + Title -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text font-semibold">Level</span></label>
                        <select v-model="form.level" class="select select-bordered w-full" required>
                            <option value="SSC">SSC (10th)</option>
                            <option value="SSC-I">SSC-I (9th)</option>
                            <option value="SSC-II">SSC-II (10th)</option>
                            <option value="HSSC-I">HSSC-I (11th)</option>
                            <option value="HSSC-II">HSSC-II (12th)</option>
                        </select>
                    </div>
                    <div class="form-control sm:col-span-2">
                        <label class="label"><span class="label-text font-semibold">Title</span></label>
                        <input v-model="form.title" type="text"
                               placeholder="e.g. SSC-I Annual 2026"
                               class="input input-bordered w-full" required />
                    </div>
                </div>

                <!-- Announced + Marks -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text font-semibold">Announced On</span></label>
                        <input v-model="form.announced_on" type="date" class="input input-bordered w-full" />
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text font-semibold">Total Marks</span></label>
                        <input v-model.number="form.total_marks" type="number" min="100" max="1200"
                               class="input input-bordered w-full" required />
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text font-semibold">Pass %</span></label>
                        <input v-model.number="form.pass_percentage" type="number" min="1" max="100"
                               class="input input-bordered w-full" required />
                    </div>
                </div>

                <!-- Grading Scale (optional) -->
                <div class="form-control">
                    <label class="label pb-1">
                        <span class="label-text font-semibold">Grading Scale</span>
                        <span class="label-text-alt text-xs text-base-content/55">
                            Leave blank for FBISE default (A1 ≥80 / A ≥70 / B ≥60 / C ≥50 / D ≥40 / E ≥33 / F)
                        </span>
                    </label>
                    <select v-model="form.grading_scale_id" class="select select-bordered w-full">
                        <option :value="null">FBISE default (built-in)</option>
                        <option v-for="s in gradingScales" :key="s.id" :value="s.id">
                            {{ s.name }}<span v-if="s.is_default"> · default</span>
                        </option>
                    </select>
                    <p class="text-[11px] text-base-content/50 mt-1">
                        Manage custom scales at <a href="/grading-scales" class="text-primary underline">Grading Scales</a>.
                    </p>
                </div>

                <!-- Notes -->
                <div class="form-control">
                    <label class="label"><span class="label-text font-semibold">Notes</span></label>
                    <textarea v-model="form.notes" rows="2" class="textarea textarea-bordered w-full"
                              placeholder="Optional — supplementary window, revaluation deadline, etc."></textarea>
                </div>

                <!-- Error list -->
                <div v-if="Object.keys(form.errors).length"
                     class="rounded-xl border border-error/30 bg-error/10 p-3 text-xs">
                    <p class="font-bold text-error mb-1">Please fix these:</p>
                    <ul class="list-disc pl-4 space-y-0.5">
                        <li v-for="(msg, key) in form.errors" :key="key">{{ msg }}</li>
                    </ul>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Link :href="route('board-results.index')" class="btn btn-ghost btn-sm rounded-xl">Cancel</Link>
                    <button type="submit" :disabled="form.processing"
                            class="btn btn-primary btn-sm rounded-xl gap-1.5">
                        <span v-if="form.processing" class="loading loading-spinner loading-xs"></span>
                        Create Board Exam
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
