<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchFilter from '@/Components/SearchFilter.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { usePermissions } from '@/Composables/usePermissions'
import { useDebouncedSearch } from '@/Composables/useDebouncedSearch'
import { formatStatus } from '@/Utils/format'

const { can } = usePermissions()

const props = defineProps({
    subjects: Object,
    filters: Object,
})

const search = useDebouncedSearch({
    routeName: 'subjects.index',
    initial: props.filters?.search || '',
    only: ['subjects', 'filters'],
    delay: 0,
})
const confirmDelete = ref(false)
const subjectToDelete = ref(null)

function confirmDeleteSubject(subject) {
    subjectToDelete.value = subject
    confirmDelete.value = true
}

function deleteSubject() {
    if (subjectToDelete.value) {
        router.delete(route('subjects.destroy', subjectToDelete.value.id), {
            onSuccess: () => { confirmDelete.value = false; subjectToDelete.value = null }
        })
    }
}
</script>

<template>
    <Head title="Subjects" />
    <AppLayout :breadcrumbs="[{ label: 'Subjects' }]">
        <div class="space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight">Subjects</h1>
                    <p class="text-sm text-base-content/55 mt-0.5">
                        {{ subjects?.total || 0 }} subject{{ (subjects?.total || 0) === 1 ? '' : 's' }}
                        <span v-if="search">matching "{{ search }}"</span>
                    </p>
                </div>
                <Link v-if="can('subjects.create')" :href="route('subjects.create')" class="btn btn-primary btn-sm gap-1.5">
                    <PlusIcon class="w-4 h-4" /> Add Subject
                </Link>
            </div>

            <section class="surface overflow-hidden">
                <header class="surface-header">
                    <div class="flex-1 max-w-md">
                        <SearchFilter v-model="search" placeholder="Search subjects by name or code…" />
                    </div>
                </header>

                <div class="table-sticky-wrap" style="--table-max-h: 65vh;" v-if="subjects?.data?.length">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="w-12">#</th>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th class="text-center">Main</th>
                                <th class="text-center">Order</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(subject, i) in subjects.data" :key="subject.id">
                                <td class="text-xs font-mono text-base-content/55 tabular-nums">{{ subjects.from + i }}</td>
                                <td><span class="badge badge-outline badge-sm font-mono">{{ subject.code }}</span></td>
                                <td class="font-bold text-sm">{{ subject.name }}</td>
                                <td>
                                    <span :class="['badge badge-sm', subject.type === 'core' ? 'badge-primary' : 'badge-secondary']">
                                        {{ formatStatus(subject.type) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span v-if="subject.is_main" class="badge badge-success badge-sm">Main</span>
                                    <span v-else class="text-xs text-base-content/30">—</span>
                                </td>
                                <td class="text-center text-xs font-mono text-base-content/55 tabular-nums">{{ subject.sort_order }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <div class="flex gap-0.5 justify-end">
                                        <Link v-if="can('subjects.edit')" :href="route('subjects.edit', subject.id)" class="btn btn-ghost btn-xs btn-square" title="Edit">
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </Link>
                                        <button v-if="can('subjects.delete')" @click="confirmDeleteSubject(subject)" class="btn btn-ghost btn-xs btn-square text-error" title="Delete">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState v-if="!subjects?.data?.length"
                    title="No subjects found"
                    :description="search ? 'Try a different search term.' : 'Get started by adding your first subject.'"
                    action-text="Add Subject"
                    :action-href="can('subjects.create') ? route('subjects.create') : null" />

                <footer v-if="subjects?.data?.length && subjects.last_page > 1" class="surface-footer">
                    <span class="text-xs text-base-content/55 font-medium">
                        Showing <span class="text-base-content font-bold">{{ subjects.from }}–{{ subjects.to }}</span>
                        of <span class="text-base-content font-bold">{{ subjects.total }}</span>
                    </span>
                    <Pagination :links="subjects.links" />
                </footer>
            </section>
        </div>

        <ConfirmDialog
            :show="confirmDelete"
            title="Delete Subject"
            :message="`Are you sure you want to delete ${subjectToDelete?.name}? This action cannot be undone.`"
            type="danger"
            @confirm="deleteSubject"
            @cancel="confirmDelete = false"
        />
    </AppLayout>
</template>
