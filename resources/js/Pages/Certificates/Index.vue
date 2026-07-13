<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import EmptyState from '@/Components/EmptyState.vue'
import StatCard from '@/Components/StatCard.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import {
    PlusIcon, ArrowDownTrayIcon, TrashIcon, EyeIcon,
    TrophyIcon, StarIcon, CheckBadgeIcon, AcademicCapIcon, Cog6ToothIcon,
} from '@heroicons/vue/24/outline'
import { formatDate } from '@/Utils/format'
import { usePermissions } from '@/Composables/usePermissions'

const { can } = usePermissions()

defineProps({
    certificates: Object,
    stats: Object,
    templatesCount: Number,
})

const confirmRevoke = ref(false)
const toRevoke = ref(null)

function askRevoke(c) { toRevoke.value = c; confirmRevoke.value = true }
function doRevoke() {
    if (toRevoke.value) {
        router.delete(route('certificates.revoke', toRevoke.value.id), {
            onSuccess: () => { confirmRevoke.value = false }
        })
    }
}

const typeBadge = {
    merit: 'badge-warning',
    subject_topper: 'badge-info',
    pass: 'badge-success',
    special_achievement: 'badge-secondary',
    participation: 'badge-ghost',
    custom: 'badge-primary',
}
const typeLabel = {
    merit: 'Merit',
    subject_topper: 'Subject Topper',
    pass: 'Pass',
    special_achievement: 'Special',
    participation: 'Participation',
    custom: 'Custom',
}

</script>

<template>
    <Head title="Certificates" />
    <AppLayout :breadcrumbs="[{ label: 'Certificates' }]">
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold">Certificates</h1>
                    <p class="text-sm text-base-content/60 mt-1">
                        Generate merit and achievement certificates for students.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link v-if="can('certificates.templates.view')"
                        :href="route('certificates.templates')" class="btn btn-ghost gap-2">
                        <Cog6ToothIcon class="w-5 h-5" /> Templates
                        <span class="badge badge-sm">{{ templatesCount }}</span>
                    </Link>
                    <Link v-if="can('certificates.generate')"
                        :href="route('certificates.generate')" class="btn btn-primary gap-2">
                        <PlusIcon class="w-5 h-5" /> Generate
                    </Link>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <StatCard label="Total Issued" :value="stats.total" :icon="AcademicCapIcon" color="primary" />
                <StatCard label="Merit" :value="stats.merit" :icon="TrophyIcon" color="warning" />
                <StatCard label="Toppers" :value="stats.toppers" :icon="StarIcon" color="info" />
                <StatCard label="Pass" :value="stats.pass" :icon="CheckBadgeIcon" color="success" />
            </div>

            <!-- Certificates table -->
            <section class="surface overflow-hidden">
                <div v-if="certificates.data.length === 0" class="p-8">
                    <EmptyState
                        title="No certificates yet"
                        description="Generate your first certificate to get started."
                        :icon="AcademicCapIcon"
                    />
                </div>
                <div v-else class="table-sticky-wrap" style="--table-max-h: 65vh;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Cert. No.</th>
                                <th>Student</th>
                                <th>Type</th>
                                <th>Exam</th>
                                <th>Template</th>
                                <th>Issued</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in certificates.data" :key="c.id">
                                <td><span class="font-mono text-xs text-base-content/75">{{ c.certificate_number }}</span></td>
                                <td>
                                    <div class="font-bold text-sm">{{ c.student?.name }}</div>
                                    <div class="text-[11px] text-base-content/55 font-mono">{{ c.student?.admission_no }}</div>
                                </td>
                                <td>
                                    <span class="badge badge-sm" :class="typeBadge[c.type]">{{ typeLabel[c.type] }}</span>
                                </td>
                                <td class="text-[13px] text-base-content/75 truncate max-w-[200px]" :title="c.exam?.name">{{ c.exam?.name || '—' }}</td>
                                <td class="text-[13px] text-base-content/75">{{ c.template?.name || '—' }}</td>
                                <td class="text-[13px] text-base-content/75 whitespace-nowrap tabular-nums">{{ formatDate(c.issued_at) }}</td>
                                <td>
                                    <div class="flex justify-end gap-0.5">
                                        <a :href="route('certificates.download', c.id)" target="_blank"
                                           class="btn btn-ghost btn-xs btn-square" title="Download">
                                            <ArrowDownTrayIcon class="w-4 h-4" />
                                        </a>
                                        <button @click="askRevoke(c)" class="btn btn-ghost btn-xs btn-square text-error" title="Revoke">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <footer v-if="certificates.data.length && certificates.last_page > 1" class="surface-footer">
                    <span class="text-xs text-base-content/55 font-medium">
                        Showing <span class="text-base-content font-bold">{{ certificates.from }}–{{ certificates.to }}</span>
                        of <span class="text-base-content font-bold">{{ certificates.total }}</span>
                    </span>
                    <Pagination :links="certificates.links" />
                </footer>
            </section>
        </div>

        <ConfirmDialog
            v-model="confirmRevoke"
            title="Revoke Certificate?"
            :message="`This will permanently revoke certificate ${toRevoke?.certificate_number}. The student will no longer be able to verify it.`"
            confirm-text="Revoke"
            confirm-class="btn-error"
            @confirm="doRevoke"
        />
    </AppLayout>
</template>
