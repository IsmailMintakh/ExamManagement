<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Pagination from '@/Components/Pagination.vue'
import Modal from '@/Components/Modal.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import {
    ArrowsRightLeftIcon,
    ClockIcon,
    CheckCircleIcon,
    BuildingOffice2Icon,
    BuildingOfficeIcon,
    EyeIcon,
    XCircleIcon,
    CheckIcon,
} from '@heroicons/vue/24/outline'
import { formatDate, formatStatus } from '@/Utils/format'
import { confirmAction } from '@/lib/swal'

const props = defineProps({
    transfers: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    currentSchoolId: { type: [Number, String], default: null },
    isSuperAdmin: { type: Boolean, default: false },
})

const STATUS_BADGE = {
    pending: 'badge-warning',
    approved: 'badge-info',
    completed: 'badge-success',
    rejected: 'badge-error',
}

const TYPE_BADGE = {
    intra_school: 'badge-ghost',
    inter_school: 'badge-primary',
}

function canApprove(t) {
    if (t.status !== 'pending') return false
    return props.isSuperAdmin || Number(t.to_school_id) === Number(props.currentSchoolId)
}

function canComplete(t) {
    if (t.status !== 'approved') return false
    return props.isSuperAdmin || Number(t.to_school_id) === Number(props.currentSchoolId)
}

function canReject(t) {
    if (t.status !== 'pending') return false
    return props.isSuperAdmin || Number(t.to_school_id) === Number(props.currentSchoolId)
}

async function approve(t) {
    if (!await confirmAction({ title: 'Approve this transfer?', confirmText: 'Yes, approve', icon: 'question' })) return
    router.post(route('transfers.approve', t.id), {}, { preserveScroll: true })
}

async function complete(t) {
    const ok = await confirmAction({
        title: 'Complete this transfer?',
        text: 'The student will be moved to the new school/class/section.',
        confirmText: 'Yes, complete',
        icon: 'question',
    })
    if (!ok) return
    router.post(route('transfers.complete', t.id), {}, { preserveScroll: true })
}

const rejectModalOpen = ref(false)
const rejectTarget = ref(null)
const rejectForm = useForm({ rejection_reason: '' })

function openReject(t) {
    rejectTarget.value = t
    rejectForm.reset()
    rejectModalOpen.value = true
}

function submitReject() {
    if (!rejectTarget.value) return
    rejectForm.post(route('transfers.reject', rejectTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            rejectModalOpen.value = false
            rejectTarget.value = null
        },
    })
}

function typeLabel(t) {
    return t === 'intra_school' ? 'Intra-School' : 'Inter-School'
}
</script>

<template>
    <Head title="Student Transfers" />
    <AppLayout :breadcrumbs="[{ label: 'Students', href: '/students' }, { label: 'Transfers' }]">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <ArrowsRightLeftIcon class="h-6 w-6 text-primary" />
                        Student Transfers
                    </h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        Manage intra-school and inter-school student transfer requests.
                    </p>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="surface !p-4 flex flex-row items-center gap-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-warning/15 text-warning">
                        <ClockIcon class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="section-eyebrow">Pending</p>
                        <p class="text-2xl font-extrabold tabular-nums">{{ stats.pending ?? 0 }}</p>
                    </div>
                </div>
                <div class="surface !p-4 flex flex-row items-center gap-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-success/15 text-success">
                        <CheckCircleIcon class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="section-eyebrow">Completed (Month)</p>
                        <p class="text-2xl font-extrabold tabular-nums">{{ stats.completed_this_month ?? 0 }}</p>
                    </div>
                </div>
                <div class="surface !p-4 flex flex-row items-center gap-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/15 text-primary">
                        <BuildingOffice2Icon class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="section-eyebrow">Inter-School</p>
                        <p class="text-2xl font-extrabold tabular-nums">{{ stats.inter_school ?? 0 }}</p>
                    </div>
                </div>
                <div class="surface !p-4 flex flex-row items-center gap-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-secondary/15 text-secondary">
                        <BuildingOfficeIcon class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="section-eyebrow">Intra-School</p>
                        <p class="text-2xl font-extrabold tabular-nums">{{ stats.intra_school ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <section class="surface overflow-hidden">
                <div class="table-sticky-wrap" style="--table-max-h: 65vh;" v-if="transfers.data && transfers.data.length">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Initiated</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in transfers.data" :key="t.id">
                                <td>
                                    <div class="font-bold text-sm">{{ t.student?.name || '—' }}</div>
                                    <div class="text-[11px] text-base-content/55 font-mono">Adm: {{ t.student?.admission_no || '—' }}</div>
                                </td>
                                <td>
                                    <div class="text-[13px] truncate max-w-[180px]" :title="t.from_school?.name">{{ t.from_school?.name || '—' }}</div>
                                    <div class="text-[11px] text-base-content/55">{{ t.from_class?.name || '—' }}</div>
                                </td>
                                <td>
                                    <div class="text-[13px] truncate max-w-[180px]" :title="t.to_school?.name">{{ t.to_school?.name || '—' }}</div>
                                    <div class="text-[11px] text-base-content/55">{{ t.to_class?.name || '—' }}</div>
                                </td>
                                <td>
                                    <span class="badge badge-sm whitespace-nowrap" :class="TYPE_BADGE[t.type] || 'badge-ghost'">
                                        {{ typeLabel(t.type) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-sm whitespace-nowrap" :class="STATUS_BADGE[t.status] || 'badge-ghost'">
                                        {{ formatStatus(t.status) }}
                                    </span>
                                </td>
                                <td class="text-[12px] whitespace-nowrap">
                                    <div class="tabular-nums">{{ formatDate(t.initiated_at) }}</div>
                                    <div class="text-base-content/55">{{ t.initiated_by?.name || '—' }}</div>
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <button v-if="canApprove(t)" type="button" class="btn btn-ghost btn-xs gap-1 text-info" @click="approve(t)">
                                            <CheckIcon class="h-3.5 w-3.5" /> Approve
                                        </button>
                                        <button v-if="canComplete(t)" type="button" class="btn btn-ghost btn-xs gap-1 text-success" @click="complete(t)">
                                            <CheckCircleIcon class="h-3.5 w-3.5" /> Complete
                                        </button>
                                        <button v-if="canReject(t)" type="button" class="btn btn-ghost btn-xs gap-1 text-error" @click="openReject(t)">
                                            <XCircleIcon class="h-3.5 w-3.5" /> Reject
                                        </button>
                                        <Link v-if="t.student?.id" :href="route('students.show', t.student.id)" class="btn btn-ghost btn-xs btn-square" title="View student">
                                            <EyeIcon class="h-3.5 w-3.5" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState v-else
                    title="No transfers yet"
                    description="Student transfers will appear here once initiated from a student profile." />

                <footer v-if="transfers.data?.length && transfers.last_page > 1" class="surface-footer">
                    <span class="text-xs text-base-content/55 font-medium">
                        Showing <span class="text-base-content font-bold">{{ transfers.from }}–{{ transfers.to }}</span>
                        of <span class="text-base-content font-bold">{{ transfers.total }}</span>
                    </span>
                    <Pagination :links="transfers.links" :from="transfers.from" :to="transfers.to" :total="transfers.total" />
                </footer>
            </section>
        </div>

        <!-- Reject Modal -->
        <Modal :show="rejectModalOpen" max-width="md" @close="rejectModalOpen = false">
            <h3 class="text-lg font-bold flex items-center gap-2">
                <XCircleIcon class="h-5 w-5 text-error" />
                Reject Transfer
            </h3>
            <p class="mt-1 text-sm text-base-content/60">
                Provide a reason for rejecting this transfer request.
            </p>
            <div class="mt-4">
                <FormTextarea
                    v-model="rejectForm.rejection_reason"
                    label="Rejection Reason"
                    placeholder="Why is this transfer being rejected?"
                    :error="rejectForm.errors.rejection_reason"
                    :rows="4"
                    required
                />
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="btn btn-ghost btn-sm" @click="rejectModalOpen = false">
                    Cancel
                </button>
                <button
                    type="button"
                    class="btn btn-error btn-sm gap-1"
                    :disabled="rejectForm.processing || !rejectForm.rejection_reason"
                    @click="submitReject"
                >
                    <XCircleIcon class="h-4 w-4" />
                    Reject Transfer
                </button>
            </div>
        </Modal>
    </AppLayout>
</template>
