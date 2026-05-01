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
import {
    PlusIcon, PencilSquareIcon, TrashIcon, EyeIcon, ArrowUpTrayIcon,
    ArrowDownTrayIcon, IdentificationIcon,
} from '@heroicons/vue/24/outline'
import { usePermissions } from '@/Composables/usePermissions'

const { can } = usePermissions()

const props = defineProps({
    students: Object,
    filters: Object,
    classes: Array,
    sections: Array,
})

const search = ref(props.filters?.search || '')
const confirmDelete = ref(false)
const studentToDelete = ref(null)

// ─── Multi-select state ───
const selectedIds = ref(new Set())
const confirmBulkDelete = ref(false)

const allOnPageIds = computed(() => (props.students?.data || []).map(s => s.id))
const allSelected = computed(() =>
    allOnPageIds.value.length > 0 && allOnPageIds.value.every(id => selectedIds.value.has(id))
)
const someSelected = computed(() => selectedIds.value.size > 0 && !allSelected.value)

function toggleOne(id) {
    const s = new Set(selectedIds.value)
    if (s.has(id)) s.delete(id); else s.add(id)
    selectedIds.value = s
}
function toggleAll() {
    if (allSelected.value) {
        // remove all-on-page from selection
        const s = new Set(selectedIds.value)
        allOnPageIds.value.forEach(id => s.delete(id))
        selectedIds.value = s
    } else {
        const s = new Set(selectedIds.value)
        allOnPageIds.value.forEach(id => s.add(id))
        selectedIds.value = s
    }
}
function clearSelection() { selectedIds.value = new Set() }

// reset selection when page changes (filters/search/pagination)
watch(() => props.students?.data, () => clearSelection(), { deep: false })

const bulkActions = computed(() => {
    const acts = []
    acts.push({ key: 'id-cards', label: 'Print ID Cards', icon: IdentificationIcon, variant: 'primary' })
    acts.push({ key: 'export', label: 'Export CSV', icon: ArrowDownTrayIcon })
    if (can('students.delete')) acts.push({ key: 'delete', label: 'Delete', icon: TrashIcon, danger: true })
    return acts
})

function onBulkAction(key) {
    if (key === 'export') doBulkExport()
    if (key === 'delete') confirmBulkDelete.value = true
    if (key === 'id-cards') doBulkIdCards()
}

function doBulkIdCards() {
    const ids = Array.from(selectedIds.value)
    if (!ids.length) return
    const form = document.createElement('form')
    form.method = 'POST'
    form.action = route('students.bulk-id-cards')
    form.target = '_blank'
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value
    if (csrf) {
        const t = document.createElement('input'); t.type = 'hidden'; t.name = '_token'; t.value = csrf
        form.appendChild(t)
    }
    ids.forEach(id => {
        const i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = id
        form.appendChild(i)
    })
    document.body.appendChild(form)
    form.submit()
    form.remove()
}

function doBulkExport() {
    const ids = Array.from(selectedIds.value)
    if (!ids.length) return
    // POST to a download endpoint — open via form submit with CSRF
    const form = document.createElement('form')
    form.method = 'POST'
    form.action = route('students.bulk-export')
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value
    if (csrf) {
        const t = document.createElement('input'); t.type = 'hidden'; t.name = '_token'; t.value = csrf
        form.appendChild(t)
    }
    ids.forEach(id => {
        const i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = id
        form.appendChild(i)
    })
    document.body.appendChild(form)
    form.submit()
    form.remove()
}

function doBulkDelete() {
    const ids = Array.from(selectedIds.value)
    if (!ids.length) return
    router.post(route('students.bulk-delete'), { ids }, {
        preserveScroll: true,
        onSuccess: () => {
            confirmBulkDelete.value = false
            clearSelection()
        },
    })
}

function handleSearch(val) {
    router.get(route('students.index'), { search: val }, { preserveState: true, replace: true })
}

function handleFilter(filters) {
    router.get(route('students.index'), { ...filters, search: search.value }, { preserveState: true, replace: true })
}

function confirmDeleteStudent(student) {
    studentToDelete.value = student
    confirmDelete.value = true
}

function deleteStudent() {
    if (studentToDelete.value) {
        router.delete(route('students.destroy', studentToDelete.value.id), {
            onSuccess: () => { confirmDelete.value = false }
        })
    }
}
</script>

<template>
    <Head title="Students" />
    <AppLayout :breadcrumbs="[{ label: 'Students' }]">
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h1 class="text-2xl font-bold">Student Management</h1>
                <div class="flex gap-2">
                    <Link v-if="can('students.import')" :href="route('students.import')" class="btn btn-outline btn-secondary gap-2">
                        <ArrowUpTrayIcon class="w-5 h-5" /> Import Excel
                    </Link>
                    <Link v-if="can('students.create')" :href="route('students.create')" class="btn btn-primary gap-2">
                        <PlusIcon class="w-5 h-5" /> Add Student
                    </Link>
                </div>
            </div>

            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <SearchFilter
                        v-model="search"
                        @update:model-value="handleSearch"
                        :filters="[
                            { key: 'class_id', label: 'Class', options: classes?.map(c => ({ value: c.id, label: c.name })) || [] },
                            { key: 'section_id', label: 'Section', options: sections?.map(s => ({ value: s.id, label: s.name })) || [] },
                        ]"
                        @filter="handleFilter"
                    />

                    <!-- ════════ MOBILE: tappable card list ════════ -->
                    <div v-if="students?.data?.length" class="sm:hidden mt-4 space-y-2">
                        <div v-for="student in students.data" :key="`m-${student.id}`"
                             class="rounded-2xl bg-base-100 border border-base-200 active:bg-base-200/40 transition-colors"
                             :class="{ 'border-primary/40 bg-primary/[0.04]': selectedIds.has(student.id) }">
                            <div class="flex items-stretch">
                                <!-- Checkbox area -->
                                <label class="flex items-center justify-center w-12 shrink-0 cursor-pointer">
                                    <input type="checkbox"
                                        :checked="selectedIds.has(student.id)"
                                        @change="toggleOne(student.id)"
                                        class="checkbox checkbox-sm checkbox-primary" />
                                </label>
                                <!-- Body -->
                                <Link :href="route('students.show', student.id)"
                                      class="flex-1 flex items-center gap-3 py-3 pr-2 min-w-0">
                                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white flex items-center justify-center font-bold shrink-0">
                                        <img v-if="student.photo_url" :src="student.photo_url" :alt="student.name" class="w-full h-full object-cover rounded-2xl" />
                                        <span v-else>{{ student.name?.charAt(0)?.toUpperCase() }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-bold text-[14.5px] truncate">{{ student.name }}</div>
                                        <div class="text-[11px] text-base-content/55 truncate mt-0.5">
                                            <span class="font-mono font-semibold">#{{ student.admission_no }}</span>
                                            <span v-if="student.roll_no"> · Roll {{ student.roll_no }}</span>
                                            <span v-if="student.school_class?.name"> · {{ student.school_class.name }}-{{ student.section?.name }}</span>
                                        </div>
                                        <div v-if="student.father_name" class="text-[11px] text-base-content/45 truncate mt-0.5">
                                            S/o {{ student.father_name }}
                                        </div>
                                    </div>
                                    <span :class="['badge badge-xs shrink-0', student.status === 'active' ? 'badge-success' : 'badge-warning']">
                                        {{ student.status }}
                                    </span>
                                </Link>
                                <!-- Action menu -->
                                <div class="flex items-center pr-2 gap-0.5">
                                    <Link v-if="can('students.edit')" :href="route('students.edit', student.id)"
                                          class="w-9 h-9 flex items-center justify-center rounded-lg active:bg-base-200 text-base-content/65"
                                          :aria-label="`Edit ${student.name}`">
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </Link>
                                    <button v-if="can('students.delete')" @click.stop="confirmDeleteStudent(student)"
                                            class="w-9 h-9 flex items-center justify-center rounded-lg active:bg-error/10 text-error"
                                            :aria-label="`Delete ${student.name}`">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="pt-2"><Pagination :links="students.links" /></div>
                    </div>

                    <!-- ════════ DESKTOP: full table ════════ -->
                    <div class="hidden sm:block overflow-x-auto mt-4" v-if="students?.data?.length">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th class="w-10">
                                        <input type="checkbox"
                                            :checked="allSelected"
                                            :indeterminate.prop="someSelected"
                                            @change="toggleAll"
                                            class="checkbox checkbox-sm checkbox-primary"
                                            aria-label="Select all on this page" />
                                    </th>
                                    <th class="hidden sm:table-cell">#</th>
                                    <th>Student</th>
                                    <th class="hidden md:table-cell">Adm. No</th>
                                    <th class="hidden sm:table-cell">Roll</th>
                                    <th class="hidden md:table-cell">Class / Section</th>
                                    <th class="hidden lg:table-cell">Father Name</th>
                                    <th class="hidden lg:table-cell">Phone</th>
                                    <th class="hidden sm:table-cell">Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(student, i) in students.data" :key="student.id"
                                    :class="{ 'bg-primary/[0.04]': selectedIds.has(student.id) }">
                                    <td>
                                        <input type="checkbox"
                                            :checked="selectedIds.has(student.id)"
                                            @change="toggleOne(student.id)"
                                            class="checkbox checkbox-sm checkbox-primary"
                                            :aria-label="`Select ${student.name}`" />
                                    </td>
                                    <td class="hidden sm:table-cell">{{ students.from + i }}</td>
                                    <td>
                                        <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                                            <div class="avatar placeholder flex-shrink-0">
                                                <div class="bg-neutral text-neutral-content rounded-full w-8 h-8">
                                                    <span class="text-xs">{{ student.name?.charAt(0) }}</span>
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-medium text-sm truncate">{{ student.name }}</div>
                                                <div class="text-[10px] text-base-content/50 sm:hidden">
                                                    Roll {{ student.roll_no || '—' }} · {{ student.school_class?.name }}-{{ student.section?.name }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-sm hidden md:table-cell font-mono text-xs">{{ student.admission_no }}</td>
                                    <td class="text-sm hidden sm:table-cell">{{ student.roll_no || '-' }}</td>
                                    <td class="text-sm hidden md:table-cell">{{ student.school_class?.name }} - {{ student.section?.name }}</td>
                                    <td class="text-sm hidden lg:table-cell">{{ student.father_name || '-' }}</td>
                                    <td class="text-sm hidden lg:table-cell">{{ student.guardian_phone || '-' }}</td>
                                    <td class="hidden sm:table-cell">
                                        <span :class="['badge badge-sm', student.status === 'active' ? 'badge-success' : 'badge-warning']">
                                            {{ student.status }}
                                        </span>
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <div class="flex gap-1 justify-end">
                                            <Link :href="route('students.show', student.id)" class="btn btn-ghost btn-xs"><EyeIcon class="w-4 h-4" /></Link>
                                            <Link v-if="can('students.edit')" :href="route('students.edit', student.id)" class="btn btn-ghost btn-xs"><PencilSquareIcon class="w-4 h-4" /></Link>
                                            <button v-if="can('students.delete')" @click="confirmDeleteStudent(student)" class="btn btn-ghost btn-xs text-error"><TrashIcon class="w-4 h-4" /></button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-4"><Pagination :links="students.links" /></div>
                    </div>
                    <EmptyState v-else title="No students found" description="Add students to get started." action-text="Add Student" :action-href="route('students.create')" />
                </div>
            </div>
        </div>

        <ConfirmDialog :show="confirmDelete" title="Delete Student" :message="`Delete ${studentToDelete?.name}?`" type="danger" @confirm="deleteStudent" @cancel="confirmDelete = false" />

        <ConfirmDialog
            :show="confirmBulkDelete"
            :title="`Delete ${selectedIds.size} student${selectedIds.size === 1 ? '' : 's'}?`"
            message="This cannot be undone. Students you don't have permission to delete will be skipped."
            type="danger"
            confirm-text="Delete Selected"
            @confirm="doBulkDelete"
            @cancel="confirmBulkDelete = false"
        />

        <BulkActionBar
            :count="selectedIds.size"
            :total="allOnPageIds.length"
            :all-selected="allSelected"
            :actions="bulkActions"
            @action="onBulkAction"
            @clear="clearSelection"
            @toggle-all="toggleAll"
        />

        <FloatingActionButton
            v-if="can('students.create')"
            :href="route('students.create')"
            label="Add Student"
        />
    </AppLayout>
</template>
