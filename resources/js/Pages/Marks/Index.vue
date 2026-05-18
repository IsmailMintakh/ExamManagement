<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
    ClipboardDocumentListIcon,
    CheckCircleIcon,
    ClockIcon,
    PencilSquareIcon,
    EyeIcon,
    LockClosedIcon,
    UserGroupIcon,
    ChevronRightIcon,
    DocumentTextIcon,
} from '@heroicons/vue/24/outline'

defineProps({
    exams: Array,
})

const authUser = computed(() => usePage().props.auth?.user)
const isClassTeacher = computed(() => !!authUser.value?.isClassTeacher)
const teachesSubjects = computed(() => !!authUser.value?.teachesSubjects)

const statusConfig = {
    submitted: { label: 'Submitted', class: 'badge-success', dot: 'bg-emerald-500', icon: CheckCircleIcon },
    draft:     { label: 'Draft',     class: 'badge-info',    dot: 'bg-sky-500',     icon: PencilSquareIcon },
    pending:   { label: 'Pending',   class: 'badge-warning', dot: 'bg-amber-500',   icon: ClockIcon },
}

function getStatus(status) {
    return statusConfig[status] || statusConfig.pending
}
</script>

<template>
    <Head title="Marks Entry" />
    <AppLayout :breadcrumbs="[{ label: 'Marks Entry' }]">
        <div class="space-y-4 max-w-[1500px] mx-auto">
            <PageHeader title="Marks entry"
                subtitle="Only the subjects assigned to you. Tap a subject to enter that section's marks."
                :icon="DocumentTextIcon" tone="primary">
                <template #actions>
                    <Link v-if="isClassTeacher" href="/my-class"
                        class="btn btn-outline btn-sm rounded-lg gap-1.5">
                        <UserGroupIcon class="w-4 h-4" /> My Class (monitor)
                    </Link>
                </template>
            </PageHeader>

            <!-- Exam Cards -->
            <div v-if="exams?.length" class="space-y-5">
                <div v-for="exam in exams" :key="exam.id"
                     class="card-section overflow-hidden">
                    <!-- Exam header -->
                    <div class="card-header">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 w-full">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                                    <ClipboardDocumentListIcon class="w-5 h-5 text-primary" />
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-bold text-base truncate">{{ exam.name }}</h3>
                                    <p class="text-xs text-base-content/55 truncate">
                                        {{ exam.exam_type }} &bull; {{ exam.academic_session }}
                                    </p>
                                </div>
                            </div>
                            <span v-if="exam.is_locked" class="badge badge-error gap-1 shrink-0">
                                <LockClosedIcon class="w-3 h-3" /> Locked
                            </span>
                            <span v-else class="badge badge-success gap-1 shrink-0">
                                <CheckCircleIcon class="w-3 h-3" /> Open
                            </span>
                        </div>
                    </div>

                    <!-- ════════ MOBILE: tap-friendly stack ════════ -->
                    <div v-if="exam.assignments?.length" class="sm:hidden divide-y divide-base-200">
                        <component
                            v-for="assignment in exam.assignments" :key="`m-${assignment.id}`"
                            :is="!exam.is_locked ? Link : 'div'"
                            :href="!exam.is_locked ? route('marks.entry', [exam.id, assignment.subject_id, assignment.section_id]) : null"
                            class="flex items-center gap-3 p-4 active:bg-base-200/40 transition-colors touch-manipulation min-w-0"
                        >
                            <!-- Status dot + leading icon -->
                            <div class="relative shrink-0">
                                <div class="w-11 h-11 rounded-xl bg-base-200 flex items-center justify-center">
                                    <component :is="getStatus(assignment.status).icon" class="w-5 h-5 text-base-content/60" />
                                </div>
                                <span class="absolute -top-0.5 -right-0.5 w-3 h-3 rounded-full ring-2 ring-base-100"
                                      :class="getStatus(assignment.status).dot"></span>
                            </div>

                            <!-- Body -->
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-[15px] truncate leading-tight">{{ assignment.subject_name }}</div>
                                <div class="flex items-center gap-2 mt-1 text-xs text-base-content/55 flex-wrap">
                                    <span class="font-medium">{{ assignment.class_name }} – {{ assignment.section_name }}</span>
                                    <span class="inline-flex items-center gap-1">
                                        <UserGroupIcon class="w-3.5 h-3.5" />
                                        <span class="tabular-nums">{{ assignment.student_count }}</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1 font-bold uppercase tracking-wider text-[10px]"
                                          :class="{
                                              'text-emerald-700': assignment.status === 'submitted',
                                              'text-sky-700':     assignment.status === 'draft',
                                              'text-amber-700':   assignment.status === 'pending' || !assignment.status,
                                          }">
                                        {{ getStatus(assignment.status).label }}
                                    </span>
                                </div>
                            </div>

                            <!-- Trailing chevron / lock -->
                            <LockClosedIcon v-if="exam.is_locked" class="w-5 h-5 text-base-content/30 shrink-0" />
                            <ChevronRightIcon v-else class="w-5 h-5 text-base-content/30 shrink-0" />
                        </component>
                    </div>

                    <!-- ════════ DESKTOP: table ════════ -->
                    <div class="hidden sm:block overflow-x-auto" v-if="exam.assignments?.length">
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
                                    <td><span class="font-medium">{{ assignment.subject_name }}</span></td>
                                    <td><span class="text-sm">{{ assignment.class_name }} - {{ assignment.section_name }}</span></td>
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

                    <div v-else class="p-6 text-center text-sm text-base-content/40">
                        No assignments available for this exam.
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="card-section">
                <!-- Class teacher with no subject assignments: explain the model -->
                <EmptyState v-if="isClassTeacher && !teachesSubjects"
                    title="No subjects assigned to you"
                    description="Marks entry is only for subjects you are assigned to teach. As a class teacher you monitor your section's marks progress (read-only) from My Class."
                    action-text="Go to My Class"
                    action-href="/my-class" />
                <EmptyState v-else
                    title="No active exams"
                    description="No exams are open for your assigned subjects right now. They'll appear here once published and marks entry is opened."
                />
            </div>
        </div>
    </AppLayout>
</template>
