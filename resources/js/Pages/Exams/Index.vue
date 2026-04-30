<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchFilter from '@/Components/SearchFilter.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import BulkActionBar from '@/Components/BulkActionBar.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { PlusIcon, PencilSquareIcon, TrashIcon, EyeIcon, LockClosedIcon, MegaphoneIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import { usePermissions } from '@/Composables/usePermissions'

const { can } = usePermissions()

const props = defineProps({ exams: Object, filters: Object, examTypes: Array })
const search = ref(props.filters?.search || '')
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

function handleSearch(val) {
    router.get(route('exams.index'), { search: val }, { preserveState: true, replace: true })
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
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h1 class="text-2xl font-bold">Exam Management</h1>
                <Link v-if="can('exams.create')" :href="route('exams.create')" class="btn btn-primary gap-2">
                    <PlusIcon class="w-5 h-5" /> Create Exam
                </Link>
            </div>

            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <SearchFilter v-model="search" @update:model-value="handleSearch" />

                    <div class="overflow-x-auto mt-4" v-if="exams?.data?.length">
                        <table class="table table-zebra">
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
                                    <th>#</th><th>Exam Name</th><th>Type</th><th>Session</th><th>Date Range</th><th>Status</th><th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(exam, i) in exams.data" :key="exam.id"
                                    :class="{ 'bg-primary/[0.04]': selectedIds.has(exam.id) }">
                                    <td>
                                        <input v-if="exam.status === 'draft'" type="checkbox"
                                            :checked="selectedIds.has(exam.id)"
                                            @change="toggleOne(exam.id)"
                                            class="checkbox checkbox-sm checkbox-primary"
                                            :aria-label="`Select ${exam.name}`" />
                                        <span v-else class="text-xs text-base-content/30" title="Only draft exams can be bulk-deleted">—</span>
                                    </td>
                                    <td>{{ exams.from + i }}</td>
                                    <td>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold text-sm">{{ exam.name }}</span>
                                            <span v-if="exam.status === 'draft' && (exam.exam_subjects_count ?? 0) === 0"
                                                class="badge badge-warning badge-xs gap-1"
                                                title="Setup incomplete — no subjects mapped to classes yet">
                                                <ExclamationTriangleIcon class="w-3 h-3" /> Setup needed
                                            </span>
                                        </div>
                                        <div class="text-xs text-base-content/60">
                                            Total: {{ exam.total_marks }} · Pass: {{ exam.passing_marks }}
                                            <span v-if="exam.exam_subjects_count" class="ml-1">· {{ exam.exam_subjects_count }} subjects</span>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-outline badge-sm">{{ exam.exam_type?.name }}</span></td>
                                    <td class="text-sm">{{ exam.academic_session?.name }}</td>
                                    <td class="text-sm">{{ exam.start_date }} - {{ exam.end_date }}</td>
                                    <td><span :class="['badge badge-sm', statusColors[exam.status] || 'badge-ghost']">{{ exam.status?.replace('_', ' ') }}</span></td>
                                    <td>
                                        <div class="flex gap-1">
                                            <Link :href="route('exams.show', exam.id)" class="btn btn-ghost btn-xs"><EyeIcon class="w-4 h-4" /></Link>
                                            <Link v-if="exam.status === 'draft' && can('exams.edit')" :href="route('exams.edit', exam.id)" class="btn btn-ghost btn-xs"><PencilSquareIcon class="w-4 h-4" /></Link>
                                            <button v-if="(exam.status === 'draft' || exam.status === 'published') && can('exams.publish')" @click="publishExam(exam)" class="btn btn-ghost btn-xs text-info" :title="exam.status === 'draft' ? 'Publish' : 'Open Marks Entry'">
                                                <MegaphoneIcon class="w-4 h-4" />
                                            </button>
                                            <button v-if="exam.status === 'marks_entry' && can('exams.publish')" @click="lockExam(exam)" class="btn btn-ghost btn-xs text-warning" title="Lock/Unlock">
                                                <LockClosedIcon class="w-4 h-4" />
                                            </button>
                                            <button v-if="exam.status === 'draft' && can('exams.delete')" @click="examToDelete = exam; confirmDelete = true" class="btn btn-ghost btn-xs text-error">
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-4"><Pagination :links="exams.links" /></div>
                    </div>
                    <EmptyState v-else title="No exams created" description="Create your first exam to get started." action-text="Create Exam" :action-href="route('exams.create')" />
                </div>
            </div>
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
    </AppLayout>
</template>
