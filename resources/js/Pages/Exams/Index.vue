<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchFilter from '@/Components/SearchFilter.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import BulkActionBar from '@/Components/BulkActionBar.vue'
import FloatingActionButton from '@/Components/FloatingActionButton.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import {
    PlusIcon, PencilSquareIcon, TrashIcon, EyeIcon, LockClosedIcon,
    MegaphoneIcon, ExclamationTriangleIcon,
    ClipboardDocumentListIcon, DocumentTextIcon, ClockIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline'
import { usePermissions } from '@/Composables/usePermissions'
import { useDebouncedSearch } from '@/Composables/useDebouncedSearch'
import { formatDateRange, formatStatus, formatNumber } from '@/Utils/format'

const { can } = usePermissions()

const props = defineProps({
    exams: Object,
    filters: Object,
    examTypes: Array,
    statusCounts: { type: Object, default: () => ({ total: 0, draft: 0, marks_entry: 0, completed: 0 }) },
})
// SearchFilter already debounces input by 300ms before emitting.
// We pass delay: 0 so the request fires as soon as SearchFilter emits,
// instead of waiting another 300ms.
const search = useDebouncedSearch({
    routeName: 'exams.index',
    initial: props.filters?.search || '',
    only: ['exams', 'filters'],
    delay: 0,
})
const confirmDelete = ref(false)
const examToDelete = ref(null)

// ─── Multi-select (drafts only — non-draft exams cannot be bulk-deleted) ───
const selectedIds = ref(new Set())
const confirmBulkDelete = ref(false)

const draftIdsOnPage = computed(() =>
    (props.exams?.data || []).filter(e => e.status === 'draft').map(e => e.id)
)
const allDraftSelected = computed(() =>
    draftIdsOnPage.value.length > 0 && draftIdsOnPage.value.every(id => selectedIds.value.has(id))
)
const someDraftSelected = computed(() => selectedIds.value.size > 0 && !allDraftSelected.value)

function toggleOne(id) {
    const s = new Set(selectedIds.value)
    if (s.has(id)) s.delete(id); else s.add(id)
    selectedIds.value = s
}
function toggleAll() {
    if (allDraftSelected.value) {
        const s = new Set(selectedIds.value)
        draftIdsOnPage.value.forEach(id => s.delete(id))
        selectedIds.value = s
    } else {
        const s = new Set(selectedIds.value)
        draftIdsOnPage.value.forEach(id => s.add(id))
        selectedIds.value = s
    }
}
function clearSelection() { selectedIds.value = new Set() }

watch(() => props.exams?.data, () => clearSelection(), { deep: false })

const bulkActions = computed(() => {
    const acts = []
    if (can('exams.delete')) acts.push({ key: 'delete', label: 'Delete drafts', icon: TrashIcon, danger: true })
    return acts
})

function onBulkAction(key) {
    if (key === 'delete') confirmBulkDelete.value = true
}

function doBulkDelete() {
    const ids = Array.from(selectedIds.value)
    if (!ids.length) return
    router.post(route('exams.bulk-delete'), { ids }, {
        preserveScroll: true,
        onSuccess: () => {
            confirmBulkDelete.value = false
            clearSelection()
        },
    })
}

function deleteExam() {
    if (examToDelete.value) {
        router.delete(route('exams.destroy', examToDelete.value.id), { onSuccess: () => { confirmDelete.value = false } })
    }
}

function publishExam(exam) { router.post(route('exams.publish', exam.id)) }
function lockExam(exam) { router.post(route('exams.lock', exam.id)) }

const statusColors = {
    draft: 'badge-ghost',
    published: 'badge-info',
    marks_entry: 'badge-warning',
    processing: 'badge-secondary',
    completed: 'badge-success',
    archived: 'badge-neutral',
}
</script>

<template>
    <Head title="Exams" />
    <AppLayout :breadcrumbs="[{ label: 'Exams' }]">
        <div class="space-y-5">
            <PageHeader title="Exam management"
                :subtitle="`${exams?.total || 0} exam${(exams?.total || 0) === 1 ? '' : 's'}${search ? ' matching “' + search + '”' : ''}`"
                :icon="ClipboardDocumentListIcon" tone="primary">
                <template #actions>
                    <Link v-if="can('exams.create')" :href="route('exams.create')" class="btn btn-primary btn-sm gap-1.5">
                        <PlusIcon class="w-4 h-4" /> Create Exam
                    </Link>
                </template>
            </PageHeader>

            <!-- KPI strip — workflow phase counts across exams in scope.
                 Matches the Results module pattern so admins eyeball the same
                 lifecycle terms in both places. -->
            <div v-if="statusCounts.total > 0" class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3">
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-500 to-indigo-600 text-white flex items-center justify-center shadow-md shadow-sky-500/15 shrink-0">
                        <ClipboardDocumentListIcon class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Exams</p>
                        <p class="text-xl font-extrabold tabular-nums">{{ statusCounts.total }}</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-base-300 to-base-400 text-base-content flex items-center justify-center shadow-md shrink-0">
                        <DocumentTextIcon class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Drafts</p>
                        <p class="text-xl font-extrabold tabular-nums">{{ statusCounts.draft }}</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white flex items-center justify-center shadow-md shadow-amber-500/15 shrink-0">
                        <ClockIcon class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wider font-bold text-amber-700 dark:text-amber-300">In marks entry</p>
                        <p class="text-xl font-extrabold tabular-nums">{{ statusCounts.marks_entry }}</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center shadow-md shadow-emerald-500/15 shrink-0">
                        <CheckCircleIcon class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wider font-bold text-emerald-700 dark:text-emerald-300">Completed</p>
                        <p class="text-xl font-extrabold tabular-nums">{{ statusCounts.completed }}</p>
                    </div>
                </div>
            </div>

            <!-- ═══════════ MAIN CARD ═══════════ -->
            <section class="surface overflow-hidden">
                <header class="surface-header">
                    <div class="flex-1 max-w-md">
                        <SearchFilter v-model="search" placeholder="Search exams by name…" />
                    </div>
                </header>

                <!-- ════════ MOBILE: tap-friendly exam cards ════════ -->
                <div v-if="exams?.data?.length" class="sm:hidden p-3 space-y-2.5">
                    <div v-for="exam in exams.data" :key="`m-${exam.id}`"
                            class="rounded-2xl bg-base-100 border border-base-200 active:bg-base-200/40 transition-colors overflow-hidden"
                            :class="{ 'border-primary/40 bg-primary/[0.04]': selectedIds.has(exam.id) }">
                        <Link :href="route('exams.show', exam.id)" class="block p-3.5">
                            <div class="flex items-start gap-3">
                                <label v-if="exam.status === 'draft'" @click.stop
                                        class="flex items-center justify-center pt-1">
                                    <input type="checkbox" :checked="selectedIds.has(exam.id)"
                                            @change="toggleOne(exam.id)" class="checkbox checkbox-sm checkbox-primary" />
                                </label>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-bold text-[15px] truncate">{{ exam.name }}</span>
                                        <span :class="['badge badge-xs', statusColors[exam.status] || 'badge-ghost']">
                                            {{ formatStatus(exam.status) }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-base-content/55 mt-1 truncate">
                                        <span v-if="exam.exam_type?.name" class="font-semibold">{{ exam.exam_type.name }}</span>
                                        <span v-if="exam.academic_session?.name"> · {{ exam.academic_session.name }}</span>
                                        <span v-if="exam.exam_subjects_count !== undefined"> · {{ exam.exam_subjects_count }} subjects</span>
                                    </div>
                                    <div class="text-[11px] text-base-content/45 mt-0.5 truncate">
                                        {{ formatDateRange(exam.start_date, exam.end_date) }}
                                        · Total {{ formatNumber(exam.total_marks) }}
                                        · Pass {{ formatNumber(exam.passing_marks) }}
                                    </div>
                                    <div v-if="exam.status === 'draft' && (exam.exam_subjects_count ?? 0) === 0"
                                            class="mt-2 inline-flex items-center gap-1 text-[10px] font-bold uppercase text-amber-700 dark:text-amber-300 bg-amber-500/15 rounded-md px-1.5 py-0.5">
                                        <ExclamationTriangleIcon class="w-3 h-3" /> Setup needed
                                    </div>
                                </div>
                            </div>
                        </Link>
                        <div class="flex items-stretch border-t border-base-200 bg-base-200/30">
                            <Link v-if="!exam.results_published_at && can('exams.edit')"
                                    :href="route('exams.edit', exam.id)"
                                    class="flex-1 inline-flex items-center justify-center gap-1.5 py-2 text-[12px] font-semibold text-base-content/65 active:bg-base-300/50">
                                <PencilSquareIcon class="w-4 h-4" /> Edit
                            </Link>
                            <button v-if="(exam.status === 'draft' || exam.status === 'published') && can('exams.publish')"
                                    @click="publishExam(exam)"
                                    class="flex-1 inline-flex items-center justify-center gap-1.5 py-2 text-[12px] font-semibold text-info active:bg-info/10">
                                <MegaphoneIcon class="w-4 h-4" />
                                {{ exam.status === 'draft' ? 'Publish' : 'Open Marks' }}
                            </button>
                            <button v-if="exam.status === 'marks_entry' && can('exams.publish')"
                                    @click="lockExam(exam)"
                                    class="flex-1 inline-flex items-center justify-center gap-1.5 py-2 text-[12px] font-semibold text-warning active:bg-warning/10">
                                <LockClosedIcon class="w-4 h-4" /> Lock
                            </button>
                            <button v-if="exam.status === 'draft' && can('exams.delete')"
                                    @click="examToDelete = exam; confirmDelete = true"
                                    class="w-12 inline-flex items-center justify-center text-error active:bg-error/10">
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ════════ DESKTOP: full table ════════ -->
                <div class="hidden sm:block table-sticky-wrap" style="--table-max-h: 65vh;" v-if="exams?.data?.length">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="w-10">
                                    <input type="checkbox"
                                        :checked="allDraftSelected"
                                        :indeterminate.prop="someDraftSelected"
                                        @change="toggleAll"
                                        :disabled="draftIdsOnPage.length === 0"
                                        class="checkbox checkbox-sm checkbox-primary"
                                        aria-label="Select all draft exams" />
                                </th>
                                <th class="w-12">#</th>
                                <th>Exam</th>
                                <th>Type</th>
                                <th>Session</th>
                                <th>Date Range</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(exam, i) in exams.data" :key="exam.id"
                                :class="{ 'is-selected': selectedIds.has(exam.id) }">
                                <td>
                                    <input v-if="exam.status === 'draft'" type="checkbox"
                                        :checked="selectedIds.has(exam.id)"
                                        @change="toggleOne(exam.id)"
                                        class="checkbox checkbox-sm checkbox-primary"
                                        :aria-label="`Select ${exam.name}`" />
                                    <span v-else class="text-xs text-base-content/30" title="Only draft exams can be bulk-deleted">—</span>
                                </td>
                                <td class="text-xs font-mono text-base-content/55 tabular-nums">{{ exams.from + i }}</td>
                                <td>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <Link :href="route('exams.show', exam.id)"
                                              class="font-bold text-sm hover:text-primary transition-colors">
                                            {{ exam.name }}
                                        </Link>
                                        <span v-if="exam.status === 'draft' && (exam.exam_subjects_count ?? 0) === 0"
                                            class="badge badge-warning badge-xs gap-1"
                                            title="Setup incomplete — no subjects mapped to classes yet">
                                            <ExclamationTriangleIcon class="w-3 h-3" /> Setup needed
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-base-content/55 mt-0.5">
                                        Total {{ formatNumber(exam.total_marks) }}
                                        · Pass {{ formatNumber(exam.passing_marks) }}
                                        <span v-if="exam.exam_subjects_count">· {{ exam.exam_subjects_count }} subjects</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-outline badge-sm font-medium">{{ exam.exam_type?.name }}</span>
                                </td>
                                <td class="text-[13px] text-base-content/75 whitespace-nowrap">{{ exam.academic_session?.name }}</td>
                                <td class="text-[13px] text-base-content/75 whitespace-nowrap font-medium tabular-nums">
                                    {{ formatDateRange(exam.start_date, exam.end_date) }}
                                </td>
                                <td>
                                    <span :class="['badge badge-sm whitespace-nowrap', statusColors[exam.status] || 'badge-ghost']">
                                        {{ formatStatus(exam.status) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex gap-0.5 justify-end">
                                        <Link :href="route('exams.show', exam.id)" class="btn btn-ghost btn-xs btn-square" title="View">
                                            <EyeIcon class="w-4 h-4" />
                                        </Link>
                                        <Link v-if="!exam.results_published_at && can('exams.edit')" :href="route('exams.edit', exam.id)"
                                              class="btn btn-ghost btn-xs btn-square" title="Edit">
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </Link>
                                        <button v-if="(exam.status === 'draft' || exam.status === 'published') && can('exams.publish')"
                                                @click="publishExam(exam)" class="btn btn-ghost btn-xs btn-square text-info"
                                                :title="exam.status === 'draft' ? 'Publish' : 'Open Marks Entry'">
                                            <MegaphoneIcon class="w-4 h-4" />
                                        </button>
                                        <button v-if="exam.status === 'marks_entry' && can('exams.publish')"
                                                @click="lockExam(exam)" class="btn btn-ghost btn-xs btn-square text-warning" title="Lock / Unlock">
                                            <LockClosedIcon class="w-4 h-4" />
                                        </button>
                                        <button v-if="exam.status === 'draft' && can('exams.delete')"
                                                @click="examToDelete = exam; confirmDelete = true"
                                                class="btn btn-ghost btn-xs btn-square text-error" title="Delete">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState v-if="!exams?.data?.length"
                    title="No exams found"
                    :description="search ? 'Try a different search term.' : 'Create your first exam to get started.'"
                    action-text="Create Exam"
                    :action-href="can('exams.create') ? route('exams.create') : null" />

                <footer v-if="exams?.data?.length && exams.last_page > 1" class="surface-footer">
                    <span class="text-xs text-base-content/55 font-medium">
                        Showing <span class="text-base-content font-bold">{{ exams.from }}–{{ exams.to }}</span>
                        of <span class="text-base-content font-bold">{{ exams.total }}</span>
                    </span>
                    <Pagination :links="exams.links" />
                </footer>
            </section>
        </div>
        <ConfirmDialog :show="confirmDelete" title="Delete Exam" :message="`Delete ${examToDelete?.name}?`" type="danger" @confirm="deleteExam" @cancel="confirmDelete = false" />

        <ConfirmDialog
            :show="confirmBulkDelete"
            :title="`Delete ${selectedIds.size} draft exam${selectedIds.size === 1 ? '' : 's'}?`"
            message="Only draft exams will be deleted. This cannot be undone."
            type="danger"
            confirm-text="Delete Drafts"
            @confirm="doBulkDelete"
            @cancel="confirmBulkDelete = false"
        />

        <BulkActionBar
            :count="selectedIds.size"
            :total="draftIdsOnPage.length"
            :all-selected="allDraftSelected"
            :actions="bulkActions"
            @action="onBulkAction"
            @clear="clearSelection"
            @toggle-all="toggleAll"
        />

        <FloatingActionButton
            v-if="can('exams.create')"
            :href="route('exams.create')"
            label="New Exam"
        />
    </AppLayout>
</template>
