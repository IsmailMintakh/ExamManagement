<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchFilter from '@/Components/SearchFilter.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import BulkActionBar from '@/Components/BulkActionBar.vue'
import FloatingActionButton from '@/Components/FloatingActionButton.vue'
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

                    <!-- ════════ MOBILE: tap-friendly exam cards ════════ -->
                    <div v-if="exams?.data?.length" class="sm:hidden mt-4 space-y-2.5">
                        <div v-for="exam in exams.data" :key="`m-${exam.id}`"
                             class="rounded-2xl bg-base-100 border border-base-200 active:bg-base-200/40 transition-colors overflow-hidden"
                             :class="{ 'border-primary/40 bg-primary/[0.04]': selectedIds.has(exam.id) }">
                            <!-- Body — tappable -->
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
                                                {{ exam.status?.replace('_', ' ') }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-base-content/55 mt-1 truncate">
                                            <span v-if="exam.exam_type?.name" class="font-semibold">{{ exam.exam_type.name }}</span>
                                            <span v-if="exam.academic_session?.name"> · {{ exam.academic_session.name }}</span>
                                            <span v-if="exam.exam_subjects_count !== undefined"> · {{ exam.exam_subjects_count }} subjects</span>
                                        </div>
                                        <div class="text-[11px] text-base-content/45 mt-0.5 truncate">
                                            {{ exam.start_date }} → {{ exam.end_date }} · Total {{ exam.total_marks }} · Pass {{ exam.passing_marks }}
                                        </div>
                                        <div v-if="exam.status === 'draft' && (exam.exam_subjects_count ?? 0) === 0"
                                             class="mt-2 inline-flex items-center gap-1 text-[10px] font-bold uppercase text-amber-700 bg-amber-100 rounded-md px-1.5 py-0.5">
                                            <ExclamationTriangleIcon class="w-3 h-3" /> Setup needed
                                        </div>
                                    </div>
                                </div>
                            </Link>
                            <!-- Quick actions strip -->
                            <div class="flex items-stretch border-t border-base-200 bg-base-200/30">
                                <Link v-if="exam.status === 'draft' && can('exams.edit')"
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
                        <div class="pt-2"><Pagination :links="exams.links" /></div>
                    </div>

                    <!-- ════════ DESKTOP: full table ════════ -->
                    <div class="hidden sm:block overflow-x-auto mt-4" v-if="exams?.data?.length">
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

        <FloatingActionButton
            v-if="can('exams.create')"
            :href="route('exams.create')"
            label="New Exam"
        />
    </AppLayout>
</template>
