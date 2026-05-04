<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, usePage, router } from '@inertiajs/vue3'
import {
    ChartBarIcon, DocumentArrowDownIcon, EyeIcon,
    PaperAirplaneIcon, CheckBadgeIcon, AcademicCapIcon,
} from '@heroicons/vue/24/outline'
import { usePermissions } from '@/Composables/usePermissions'

defineProps({ exams: Array })

const page = usePage()
const roles = page.props.auth?.user?.roles || []
const isSuperAdmin = roles.includes('super-admin')
const { can } = usePermissions()

const statusColors = {
    draft: 'badge-ghost',
    published: 'badge-info',
    marks_entry: 'badge-warning',
    processing: 'badge-secondary',
    completed: 'badge-success',
    archived: 'badge-neutral',
}

const statusLabels = {
    draft: 'Draft',
    published: 'Published',
    marks_entry: 'Marks Entry',
    processing: 'Processing',
    completed: 'Completed',
    archived: 'Archived',
}

const submissionStatusColors = {
    pending: 'badge-ghost',
    generated: 'badge-info',
    submitted: 'badge-warning',
    finalized: 'badge-success',
}

function finalizeSchool(examId, schoolId) {
    router.post(route('results.finalize', [examId, schoolId]))
}
</script>

<template>
    <Head title="Results" />
    <AppLayout :breadcrumbs="[{ label: 'Results' }]">
        <div class="space-y-5">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight">Result Processing</h1>
                <p class="text-sm text-base-content/55 mt-0.5">Generate, review and manage examination results — {{ exams?.length || 0 }} exam{{ (exams?.length || 0) === 1 ? '' : 's' }} ready</p>
            </div>

            <template v-if="exams?.length">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                    <section v-for="exam in exams" :key="exam.id" class="surface surface-hover">
                        <div class="surface-body !p-5">
                            <!-- Header -->
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/15 text-primary">
                                        <AcademicCapIcon class="w-5 h-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="font-bold text-sm leading-tight truncate">{{ exam.name }}</h3>
                                        <p class="text-xs text-base-content/55 mt-0.5">{{ exam.exam_type }}</p>
                                    </div>
                                </div>
                                <span :class="['badge badge-sm whitespace-nowrap', statusColors[exam.status]]">
                                    {{ statusLabels[exam.status] || exam.status }}
                                </span>
                            </div>

                            <div class="mt-3 flex items-center gap-4 text-xs text-base-content/65">
                                <span>{{ exam.session }}</span>
                                <span class="flex items-center gap-1">
                                    <ChartBarIcon class="w-3.5 h-3.5" />
                                    <span class="tabular-nums">{{ exam.results_count || 0 }}</span> results
                                </span>
                            </div>

                            <div v-if="isSuperAdmin && exam.school_submissions?.length" class="mt-3 space-y-1.5">
                                <p class="section-eyebrow">School Status</p>
                                <div v-for="sub in exam.school_submissions" :key="sub.school_id"
                                        class="flex items-center justify-between rounded-lg bg-base-200/50 px-3 py-1.5">
                                    <span class="text-xs font-medium truncate mr-2">{{ sub.school_name }}</span>
                                    <span :class="['badge badge-xs', submissionStatusColors[sub.status]]">
                                        {{ sub.status }}
                                    </span>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <Link v-if="can('results.generate') && ['marks_entry', 'published', 'completed', 'processing'].includes(exam.status)"
                                        :href="route('results.generate', exam.id)"
                                        class="btn btn-primary btn-sm flex-1 gap-1.5">
                                    <ChartBarIcon class="w-4 h-4" /> Generate
                                </Link>
                                <Link v-if="exam.results_count"
                                        :href="route('results.generate', exam.id)"
                                        class="btn btn-outline btn-sm flex-1 gap-1.5">
                                    <EyeIcon class="w-4 h-4" /> View Results
                                </Link>

                                <template v-if="can('results.finalize') && exam.school_submissions?.length">
                                    <template v-for="sub in exam.school_submissions" :key="'action-' + sub.school_id">
                                        <button v-if="sub.status === 'submitted'"
                                                @click="finalizeSchool(exam.id, sub.school_id)"
                                                class="btn btn-success btn-sm flex-1 gap-1.5">
                                            <CheckBadgeIcon class="w-4 h-4" /> Finalize {{ sub.school_name }}
                                        </button>
                                    </template>
                                </template>
                            </div>
                        </div>
                    </section>
                </div>
            </template>

            <EmptyState v-else title="No exams available" description="Results can be generated once marks entry is complete." />
        </div>
    </AppLayout>
</template>
