<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import {
    CalendarIcon, ClockIcon, PencilSquareIcon,
    PlayIcon, LockClosedIcon, LockOpenIcon,
    CheckCircleIcon, ExclamationTriangleIcon,
    ArrowPathIcon, DocumentTextIcon,
    CalendarDaysIcon,
} from '@heroicons/vue/24/outline'
import { usePermissions } from '@/Composables/usePermissions'

const { can } = usePermissions()

const props = defineProps({
    exam: Object,
    examSubjects: Array,
    schools: Array,
    timeline: Array,
})

const page = usePage()
const roles = page.props.auth?.user?.roles || []
const isSuperAdmin = roles.includes('super-admin')

const confirmPublish = ref(false)
const confirmLock = ref(false)
const processing = ref(false)

const statusConfig = {
    draft: { label: 'Draft', class: 'badge-ghost', desc: 'Exam is in draft mode. Publish it to make it visible to schools.' },
    published: { label: 'Published', class: 'badge-info', desc: 'Exam is published. Open marks entry to allow teachers to enter marks.' },
    marks_entry: { label: 'Marks Entry', class: 'badge-warning', desc: 'Teachers can now enter marks for this exam.' },
    processing: { label: 'Processing', class: 'badge-accent', desc: 'Results are being generated.' },
    completed: { label: 'Completed', class: 'badge-success', desc: 'All results have been generated.' },
    archived: { label: 'Archived', class: 'badge-neutral', desc: 'This exam has been archived.' },
}

const currentStatus = statusConfig[props.exam?.status] || statusConfig.draft

const nextAction = {
    draft: { label: 'Publish Exam', icon: PlayIcon, desc: 'Make this exam visible to all applicable schools and teachers.' },
    published: { label: 'Open Marks Entry', icon: DocumentTextIcon, desc: 'Allow teachers to start entering marks for this exam.' },
}

function doPublish() {
    processing.value = true
    router.post(route('exams.publish', props.exam.id), {}, {
        onFinish: () => { processing.value = false; confirmPublish.value = false },
    })
}

function toggleLock() {
    processing.value = true
    router.post(route('exams.lock', props.exam.id), {}, {
        onFinish: () => { processing.value = false; confirmLock.value = false },
    })
}
</script>

<template>
    <Head :title="exam.name" />
    <AppLayout :breadcrumbs="[{ label: 'Exams', href: route('exams.index') }, { label: exam.name }]">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                        <h1 class="text-xl sm:text-2xl font-bold">{{ exam.name }}</h1>
                        <span class="badge" :class="currentStatus.class">{{ currentStatus.label }}</span>
                        <span v-if="exam.is_locked" class="badge badge-error gap-1">
                            <LockClosedIcon class="w-3 h-3" /> Locked
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-base-content/50">
                        {{ exam.exam_type?.name }} &bull; {{ exam.academic_session?.name }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link v-if="exam.status === 'draft' && can('exams.edit')" :href="route('exams.edit', exam.id)" class="btn btn-outline btn-sm gap-1.5">
                        <PencilSquareIcon class="w-4 h-4" /> Edit
                    </Link>
                    <Link v-if="(exam.status === 'marks_entry' || exam.status === 'completed') && can('results.generate')" :href="route('results.generate', exam.id)" class="btn btn-outline btn-accent btn-sm gap-1.5">
                        <ArrowPathIcon class="w-4 h-4" /> Generate Results
                    </Link>
                    <Link v-if="can('scheduling.view')" :href="route('scheduling.index', exam.id)" class="btn btn-outline btn-primary btn-sm gap-1.5">
                        <CalendarDaysIcon class="w-4 h-4" /> Scheduling
                    </Link>
                </div>
            </div>

            <!-- Status Info + Next Action -->
            <div class="rounded-2xl border border-base-300/60 bg-base-100 p-5 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                            :class="exam.status === 'draft' ? 'bg-base-200' : exam.status === 'marks_entry' ? 'bg-warning/10' : 'bg-success/10'">
                            <CheckCircleIcon v-if="exam.status === 'completed'" class="w-5 h-5 text-success" />
                            <ClockIcon v-else-if="exam.status === 'marks_entry'" class="w-5 h-5 text-warning" />
                            <ExclamationTriangleIcon v-else class="w-5 h-5 text-base-content/40" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold">Status: {{ currentStatus.label }}</p>
                            <p class="text-xs text-base-content/50 mt-0.5">{{ currentStatus.desc }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <!-- Publish / Open Marks Entry -->
                        <button
                            v-if="nextAction[exam.status] && can('exams.publish')"
                            @click="confirmPublish = true"
                            class="btn btn-primary btn-sm gap-1.5"
                        >
                            <component :is="nextAction[exam.status].icon" class="w-4 h-4" />
                            {{ nextAction[exam.status].label }}
                        </button>
                        <!-- Lock / Unlock -->
                        <button
                            v-if="exam.status === 'marks_entry' && can('exams.publish')"
                            @click="confirmLock = true"
                            class="btn btn-sm gap-1.5"
                            :class="exam.is_locked ? 'btn-success' : 'btn-warning'"
                        >
                            <LockOpenIcon v-if="exam.is_locked" class="w-4 h-4" />
                            <LockClosedIcon v-else class="w-4 h-4" />
                            {{ exam.is_locked ? 'Unlock Entry' : 'Lock Entry' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- ═══════════ SETUP WIZARD PROMPT ═══════════
                 Only shown when the exam is in draft AND has no subjects.
                 This is the most common configuration gap — a 1-click path to fix it. -->
            <div v-if="exam.status === 'draft' && (!examSubjects || examSubjects.length === 0)"
                 class="rounded-2xl border-2 border-amber-300 bg-gradient-to-br from-amber-50 to-orange-50/50 p-5 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-500 text-white shadow-lg shadow-amber-500/25">
                        <ExclamationTriangleIcon class="w-6 h-6" />
                    </div>
                    <div class="flex-1">
                        <div class="text-[10px] uppercase tracking-[0.2em] font-bold text-amber-700 mb-1">Setup Required</div>
                        <h3 class="text-lg font-bold text-amber-950">This exam has no subjects yet</h3>
                        <p class="text-sm text-amber-900/80 mt-1.5 leading-relaxed">
                            Before you can publish this exam and let teachers enter marks, you need to map subjects to each class with their total &amp; passing marks.
                        </p>
                        <div class="mt-4 flex flex-col sm:flex-row gap-2">
                            <Link v-if="can('exams.edit')" :href="route('exams.edit', exam.id)"
                                class="btn btn-sm bg-amber-600 hover:bg-amber-700 text-white border-0 rounded-xl gap-1.5 shadow-md hover:shadow-lg transition-all">
                                <PencilSquareIcon class="w-4 h-4" />
                                Configure Subjects
                            </Link>
                            <Link v-if="can('exams.delete')" :href="route('exams.index')"
                                class="btn btn-sm btn-ghost rounded-xl gap-1.5 text-amber-900/70">
                                Cancel &amp; go back
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Exam Rules & Configuration -->
                    <div class="card-section">
                        <div class="card-header">
                            <h3>Exam Rules & Configuration</h3>
                        </div>
                        <div class="card-content">
                            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                                <div class="rounded-xl bg-base-200/50 p-3 text-center">
                                    <p class="text-2xl font-extrabold text-primary">{{ exam.total_marks }}</p>
                                    <p class="mt-1 text-2xs font-medium uppercase tracking-wider text-base-content/40">Total Marks</p>
                                </div>
                                <div class="rounded-xl bg-base-200/50 p-3 text-center">
                                    <p class="text-2xl font-extrabold text-secondary">{{ exam.passing_marks }}</p>
                                    <p class="mt-1 text-2xs font-medium uppercase tracking-wider text-base-content/40">Passing Marks</p>
                                </div>
                                <div class="rounded-xl bg-base-200/50 p-3 text-center">
                                    <p class="text-2xl font-extrabold text-accent">{{ exam.passing_percentage }}%</p>
                                    <p class="mt-1 text-2xs font-medium uppercase tracking-wider text-base-content/40">Pass Percentage</p>
                                </div>
                                <div class="rounded-xl bg-base-200/50 p-3 text-center">
                                    <p class="text-lg font-bold capitalize">{{ exam.position_calculation }}</p>
                                    <p class="mt-1 text-2xs font-medium uppercase tracking-wider text-base-content/40">Position By</p>
                                </div>
                            </div>

                            <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="flex items-center justify-between rounded-lg border border-base-200 px-4 py-3">
                                    <span class="text-sm text-base-content/60">Grace Marks</span>
                                    <span class="text-sm font-semibold">{{ exam.grace_marks }} <span class="text-xs font-normal text-base-content/40">(max {{ exam.grace_marks_max_subjects }} subjects)</span></span>
                                </div>
                                <div class="flex items-center justify-between rounded-lg border border-base-200 px-4 py-3">
                                    <span class="text-sm text-base-content/60">Min Subjects to Pass</span>
                                    <span class="text-sm font-semibold">{{ exam.min_subjects_to_pass || 'No limit' }}</span>
                                </div>
                                <div class="flex items-center justify-between rounded-lg border border-base-200 px-4 py-3">
                                    <span class="text-sm text-base-content/60">Main Subjects Must Pass</span>
                                    <span class="badge badge-sm" :class="exam.main_subjects_must_pass ? 'badge-success' : 'badge-ghost'">
                                        {{ exam.main_subjects_must_pass ? 'Yes' : 'No' }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between rounded-lg border border-base-200 px-4 py-3">
                                    <span class="text-sm text-base-content/60">All Subjects Must Pass</span>
                                    <span class="badge badge-sm" :class="exam.all_subjects_must_pass ? 'badge-success' : 'badge-ghost'">
                                        {{ exam.all_subjects_must_pass ? 'Yes' : 'No' }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="exam.grading_scale" class="mt-4">
                                <p class="text-xs font-semibold uppercase tracking-wider text-base-content/40 mb-2">Grading Scale: {{ exam.grading_scale.name }}</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <span v-for="entry in exam.grading_scale.entries" :key="entry.id"
                                        class="inline-flex items-center gap-1 rounded-lg bg-base-200 px-2.5 py-1 text-xs">
                                        <span class="font-bold">{{ entry.grade }}</span>
                                        <span class="text-base-content/40">{{ entry.min_percentage }}-{{ entry.max_percentage }}%</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Exam Subjects -->
                    <div class="card-section">
                        <div class="card-header">
                            <h3>Exam Subjects ({{ examSubjects?.length || 0 }})</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="table" v-if="examSubjects?.length">
                                <thead>
                                    <tr><th>Subject</th><th>Class</th><th>Total</th><th>Pass</th><th>Date</th></tr>
                                </thead>
                                <tbody>
                                    <tr v-for="es in examSubjects" :key="es.id" class="hover">
                                        <td class="font-medium">{{ es.subject?.name }}</td>
                                        <td>{{ es.school_class?.name }}</td>
                                        <td>
                                            <span class="badge badge-sm badge-primary badge-outline">{{ es.total_marks }}</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-sm badge-ghost">{{ es.passing_marks }}</span>
                                        </td>
                                        <td class="text-base-content/50">{{ es.exam_date || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-else class="p-8 text-center text-sm text-base-content/40">
                                No subjects added to this exam yet.
                                <Link v-if="exam.status === 'draft'" :href="route('exams.edit', exam.id)" class="link link-primary ml-1">Add subjects</Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Schedule -->
                    <div class="card-section">
                        <div class="card-header"><h3>Schedule</h3></div>
                        <div class="card-content space-y-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                    <CalendarIcon class="w-4 h-4 text-primary" />
                                </div>
                                <div>
                                    <p class="text-2xs uppercase tracking-wider text-base-content/40">Start Date</p>
                                    <p class="text-sm font-semibold">{{ exam.start_date || 'Not set' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-error/10">
                                    <CalendarIcon class="w-4 h-4 text-error" />
                                </div>
                                <div>
                                    <p class="text-2xs uppercase tracking-wider text-base-content/40">End Date</p>
                                    <p class="text-sm font-semibold">{{ exam.end_date || 'Not set' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-warning/10">
                                    <ClockIcon class="w-4 h-4 text-warning" />
                                </div>
                                <div>
                                    <p class="text-2xs uppercase tracking-wider text-base-content/40">Marks Deadline</p>
                                    <p class="text-sm font-semibold">{{ exam.marks_entry_deadline || 'Not set' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Schools -->
                    <div class="card-section">
                        <div class="card-header"><h3>Applicable Schools</h3></div>
                        <div class="card-content">
                            <div v-if="exam.apply_to_all_schools" class="flex items-center gap-2 text-sm">
                                <CheckCircleIcon class="w-4 h-4 text-success" />
                                <span>Applied to all schools</span>
                            </div>
                            <div v-else-if="schools?.length" class="space-y-2">
                                <div v-for="school in schools" :key="school.id" class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-success" />
                                    <span class="text-sm">{{ school.name }}</span>
                                </div>
                            </div>
                            <p v-else class="text-sm text-base-content/40">No schools selected</p>
                        </div>
                    </div>

                    <!-- Exam Workflow Steps -->
                    <div class="card-section">
                        <div class="card-header"><h3>Workflow</h3></div>
                        <div class="card-content">
                            <ul class="steps steps-vertical text-xs w-full">
                                <li class="step" :class="{ 'step-primary': ['draft','published','marks_entry','processing','completed'].includes(exam.status) }">
                                    Create Exam
                                </li>
                                <li class="step" :class="{ 'step-primary': ['published','marks_entry','processing','completed'].includes(exam.status) }">
                                    Publish
                                </li>
                                <li class="step" :class="{ 'step-primary': ['marks_entry','processing','completed'].includes(exam.status) }">
                                    Marks Entry
                                </li>
                                <li class="step" :class="{ 'step-primary': ['processing','completed'].includes(exam.status) }">
                                    Generate Results
                                </li>
                                <li class="step" :class="{ 'step-primary': exam.status === 'completed' }">
                                    Complete
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Description -->
                    <div v-if="exam.description" class="card-section">
                        <div class="card-header"><h3>Description</h3></div>
                        <div class="card-content">
                            <p class="text-sm text-base-content/60 leading-relaxed">{{ exam.description }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Publish Confirm -->
        <ConfirmDialog
            :show="confirmPublish"
            :title="nextAction[exam.status]?.label || 'Confirm'"
            :message="nextAction[exam.status]?.desc || 'Are you sure?'"
            type="info"
            :confirm-text="nextAction[exam.status]?.label || 'Confirm'"
            @confirm="doPublish"
            @cancel="confirmPublish = false"
        />

        <!-- Lock Confirm -->
        <ConfirmDialog
            :show="confirmLock"
            :title="exam.is_locked ? 'Unlock Marks Entry' : 'Lock Marks Entry'"
            :message="exam.is_locked ? 'Teachers will be able to enter/edit marks again.' : 'Teachers will no longer be able to enter or edit marks.'"
            :type="exam.is_locked ? 'info' : 'warning'"
            :confirm-text="exam.is_locked ? 'Unlock' : 'Lock'"
            @confirm="toggleLock"
            @cancel="confirmLock = false"
        />
    </AppLayout>
</template>
