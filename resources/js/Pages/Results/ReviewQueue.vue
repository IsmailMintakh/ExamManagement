<script setup>
import { ref } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import StatCard from '@/Components/StatCard.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import Modal from '@/Components/Modal.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import {
    EyeIcon, CheckBadgeIcon, ArrowUturnLeftIcon, BuildingOfficeIcon,
    UserCircleIcon, ClockIcon, AcademicCapIcon, UsersIcon,
    ClipboardDocumentCheckIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

defineProps({
    groups: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({ pending: 0, approved_today: 0, returned: 0 }),
    },
})

// --- Approve dialog state ---
const showApproveDialog = ref(false)
const approveTarget = ref(null)

function openApprove(submission) {
    approveTarget.value = submission
    showApproveDialog.value = true
}

function cancelApprove() {
    showApproveDialog.value = false
    approveTarget.value = null
}

function confirmApprove() {
    if (!approveTarget.value) return
    router.post(
        route('result-submissions.approve', approveTarget.value.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                showApproveDialog.value = false
                approveTarget.value = null
            },
        },
    )
}

// --- Return for correction modal state ---
const showReturnModal = ref(false)
const returnTarget = ref(null)
const returnForm = useForm({ remarks: '' })

function openReturn(submission) {
    returnTarget.value = submission
    returnForm.reset()
    returnForm.clearErrors()
    showReturnModal.value = true
}

function cancelReturn() {
    showReturnModal.value = false
    returnTarget.value = null
    returnForm.reset()
    returnForm.clearErrors()
}

function submitReturn() {
    if (!returnTarget.value) return
    returnForm.post(route('result-submissions.return', returnTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showReturnModal.value = false
            returnTarget.value = null
            returnForm.reset()
        },
    })
}
</script>

<template>
    <Head title="Result Review Queue" />
    <AppLayout :breadcrumbs="[{ label: 'Results', href: '/results' }, { label: 'Review Queue' }]">
        <div class="space-y-6">
            <!-- Page Header — gradient pill keeps the visual language
                 aligned with Dashboard / Marks Index / Results Index. -->
            <div class="rounded-2xl bg-base-100 border border-base-300 shadow-sm p-4 sm:p-5 flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white flex items-center justify-center shadow-md shadow-violet-500/15 shrink-0">
                    <ClipboardDocumentCheckIcon class="w-6 h-6" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-base sm:text-lg font-extrabold tracking-tight">Result Review Queue</h1>
                    <p class="text-xs text-base-content/55">Review and approve submitted school results</p>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    title="Pending Review"
                    :value="stats.pending"
                    color="warning"
                    subtitle="Awaiting your approval"
                >
                    <template #icon>
                        <ClipboardDocumentCheckIcon class="h-5 w-5 text-warning" />
                    </template>
                </StatCard>
                <StatCard
                    title="Approved Today"
                    :value="stats.approved_today"
                    color="success"
                    subtitle="Finalized in the last 24h"
                >
                    <template #icon>
                        <CheckBadgeIcon class="h-5 w-5 text-success" />
                    </template>
                </StatCard>
                <StatCard
                    title="Returned"
                    :value="stats.returned"
                    color="error"
                    subtitle="Sent back for correction"
                >
                    <template #icon>
                        <ArrowUturnLeftIcon class="h-5 w-5 text-error" />
                    </template>
                </StatCard>
            </div>

            <!-- Submissions grouped by school -->
            <template v-if="groups?.length">
                <div v-for="group in groups" :key="group.school_id ?? group.school_name" class="space-y-3">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-indigo-600 text-white shadow-md shadow-sky-500/15 shrink-0">
                            <BuildingOfficeIcon class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-base font-bold truncate">{{ group.school_name || 'Unknown School' }}</h2>
                            <p class="text-xs text-base-content/50">
                                {{ group.submissions.length }} pending submission{{ group.submissions.length === 1 ? '' : 's' }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div
                            v-for="submission in group.submissions"
                            :key="submission.id"
                            class="card border border-base-300/60 bg-base-100 shadow-sm transition-shadow hover:shadow-md"
                        >
                            <div class="card-body p-5">
                                <!-- Header row -->
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-start gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-secondary/10">
                                            <AcademicCapIcon class="h-5 w-5 text-secondary" />
                                        </div>
                                        <div class="min-w-0">
                                            <h3 class="text-sm font-bold leading-tight">{{ submission.exam_name }}</h3>
                                            <p class="mt-0.5 text-xs text-base-content/55">
                                                {{ submission.class_name }}
                                                <span v-if="submission.section_name"> &middot; Section {{ submission.section_name }}</span>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="badge badge-warning badge-sm">Pending</span>
                                </div>

                                <!-- Meta -->
                                <div class="mt-3 grid grid-cols-2 gap-3 text-xs">
                                    <div class="flex items-center gap-1.5 text-base-content/65">
                                        <UserCircleIcon class="h-4 w-4 opacity-70" />
                                        <span class="truncate">{{ submission.submitted_by || 'Unknown' }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-base-content/65">
                                        <ClockIcon class="h-4 w-4 opacity-70" />
                                        <span class="truncate">{{ submission.submitted_at_human }}</span>
                                    </div>
                                </div>

                                <!-- Stats strip -->
                                <div class="mt-3 flex items-center justify-between rounded-lg bg-base-200/50 px-3 py-2">
                                    <div class="flex items-center gap-1.5 text-xs">
                                        <UsersIcon class="h-4 w-4 text-base-content/50" />
                                        <span class="font-semibold">{{ submission.total_students }}</span>
                                        <span class="text-base-content/55">students</span>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs">
                                        <span class="text-success font-semibold">
                                            {{ submission.pass_count }} passed
                                        </span>
                                        <span class="text-error font-semibold">
                                            {{ submission.fail_count }} retry
                                        </span>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="card-actions mt-4 flex-wrap gap-2">
                                    <Link
                                        :href="submission.view_url"
                                        class="btn btn-outline btn-sm flex-1 gap-1.5"
                                    >
                                        <EyeIcon class="h-4 w-4" /> View Result
                                    </Link>
                                    <button
                                        type="button"
                                        class="btn btn-success btn-sm flex-1 gap-1.5"
                                        @click="openApprove(submission)"
                                    >
                                        <CheckBadgeIcon class="h-4 w-4" /> Approve
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-warning btn-sm flex-1 gap-1.5"
                                        @click="openReturn(submission)"
                                    >
                                        <ArrowUturnLeftIcon class="h-4 w-4" /> Return
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <EmptyState
                v-else
                title="No submissions awaiting review"
                description="When schools submit results to the DDO, they will appear here for approval."
            />
        </div>

        <!-- Approve confirm dialog -->
        <ConfirmDialog
            :show="showApproveDialog"
            type="info"
            title="Approve Results?"
            :message="approveTarget
                ? `Approve and finalize results for ${approveTarget.exam_name} - ${approveTarget.class_name} ${approveTarget.section_name ? 'Section ' + approveTarget.section_name : ''}? This will mark all ${approveTarget.total_students} student results as finalized.`
                : 'Approve and finalize this submission?'"
            confirm-text="Yes, Approve"
            cancel-text="Cancel"
            @confirm="confirmApprove"
            @cancel="cancelApprove"
        />

        <!-- Return for correction modal -->
        <Modal :show="showReturnModal" max-width="lg" @close="cancelReturn">
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-warning/10">
                        <ExclamationTriangleIcon class="h-5 w-5 text-warning" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold">Return for Correction</h3>
                        <p class="mt-0.5 text-xs text-base-content/55">
                            <template v-if="returnTarget">
                                {{ returnTarget.school_name }} &middot; {{ returnTarget.exam_name }}
                                &middot; {{ returnTarget.class_name }}
                                <span v-if="returnTarget.section_name">- {{ returnTarget.section_name }}</span>
                            </template>
                        </p>
                    </div>
                </div>

                <FormTextarea
                    v-model="returnForm.remarks"
                    label="Remarks for Correction"
                    placeholder="Explain what needs to be corrected so the school admin can fix it..."
                    :rows="5"
                    :error="returnForm.errors.remarks"
                    required
                />
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="btn btn-ghost btn-sm"
                        :disabled="returnForm.processing"
                        @click="cancelReturn"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="btn btn-warning btn-sm gap-1.5"
                        :disabled="returnForm.processing || !returnForm.remarks.trim()"
                        @click="submitReturn"
                    >
                        <ArrowUturnLeftIcon class="h-4 w-4" />
                        Return for Correction
                    </button>
                </div>
            </template>
        </Modal>
    </AppLayout>
</template>
