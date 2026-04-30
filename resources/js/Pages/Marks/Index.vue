<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    ClipboardDocumentListIcon,
    CheckCircleIcon,
    ClockIcon,
    PencilSquareIcon,
    EyeIcon,
    LockClosedIcon,
    ExclamationCircleIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline'

defineProps({
    exams: Array,
})

const statusConfig = {
    submitted: { label: 'Submitted', class: 'badge-success', icon: CheckCircleIcon },
    draft: { label: 'Draft', class: 'badge-info', icon: PencilSquareIcon },
    pending: { label: 'Pending', class: 'badge-warning', icon: ClockIcon },
}

function getStatus(status) {
    return statusConfig[status] || statusConfig.pending
}
</script>

<template>
    <Head title="Marks Entry" />
    <AppLayout :breadcrumbs="[{ label: 'Marks Entry' }]">
        <div class="space-y-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold">Marks Entry</h1>
                <p class="text-sm text-base-content/50">Enter and manage marks for active examinations.</p>
            </div>

            <!-- Exam Cards -->
            <div v-if="exams?.length" class="space-y-6">
                <div v-for="exam in exams" :key="exam.id" class="card-section">
                    <!-- Exam Card Header -->
                    <div class="card-header">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 w-full">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                                    <ClipboardDocumentListIcon class="w-5 h-5 text-primary" />
                                </div>
                                <div>
                                    <h3 class="font-bold text-base">{{ exam.name }}</h3>
                                    <p class="text-xs text-base-content/50">
                                        {{ exam.exam_type }} &bull; {{ exam.academic_session }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="exam.is_locked" class="badge badge-error gap-1">
                                    <LockClosedIcon class="w-3 h-3" /> Locked
                                </span>
                                <span v-else class="badge badge-success gap-1">
                                    <CheckCircleIcon class="w-3 h-3" /> Open for Entry
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Assignments Table -->
                    <div class="overflow-x-auto" v-if="exam.assignments?.length">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Class / Section</th>
                                    <th>Students</th>
                                    <th>Status</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="assignment in exam.assignments" :key="assignment.id" class="hover">
                                    <td>
                                        <span class="font-medium">{{ assignment.subject_name }}</span>
                                    </td>
                                    <td>
                                        <span class="text-sm">{{ assignment.class_name }} - {{ assignment.section_name }}</span>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-1.5">
                                            <UserGroupIcon class="w-4 h-4 text-base-content/40" />
                                            <span class="text-sm">{{ assignment.student_count }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-sm gap-1" :class="getStatus(assignment.status).class">
                                            <component :is="getStatus(assignment.status).icon" class="w-3 h-3" />
                                            {{ getStatus(assignment.status).label }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <Link
                                            v-if="!exam.is_locked"
                                            :href="route('marks.entry', [exam.id, assignment.subject_id, assignment.section_id])"
                                            class="btn btn-sm gap-1.5"
                                            :class="assignment.status === 'submitted' ? 'btn-ghost' : 'btn-primary'"
                                        >
                                            <EyeIcon v-if="assignment.status === 'submitted'" class="w-4 h-4" />
                                            <PencilSquareIcon v-else class="w-4 h-4" />
                                            {{ assignment.status === 'submitted' ? 'View' : 'Enter Marks' }}
                                        </Link>
                                        <span v-else class="badge badge-ghost badge-sm gap-1">
                                            <LockClosedIcon class="w-3 h-3" /> Locked
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- No Assignments -->
                    <div v-else class="p-6 text-center text-sm text-base-content/40">
                        No assignments available for this exam.
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="card-section">
                <EmptyState
                    title="No active exams"
                    description="There are no exams open for marks entry at this time. Exams will appear here once they are published and marks entry is opened."
                />
            </div>
        </div>
    </AppLayout>
</template>
