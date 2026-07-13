<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchFilter from '@/Components/SearchFilter.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useDebouncedSearch } from '@/Composables/useDebouncedSearch'

const props = defineProps({
    examTypes: Object,
    filters: Object,
})

const search = useDebouncedSearch({
    routeName: 'exam-types.index',
    initial: props.filters?.search || '',
    only: ['examTypes', 'filters'],
})
const confirmDelete = ref(false)
const typeToDelete = ref(null)

function confirmDeleteType(type) {
    typeToDelete.value = type
    confirmDelete.value = true
}

function deleteType() {
    if (typeToDelete.value) {
        router.delete(route('exam-types.destroy', typeToDelete.value.id), {
            onSuccess: () => { confirmDelete.value = false; typeToDelete.value = null }
        })
    }
}
</script>

<template>
    <Head title="Exam Types" />
    <AppLayout :breadcrumbs="[{ label: 'Exam Types' }]">
        <div class="space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight">Exam Types</h1>
                    <p class="text-sm text-base-content/55 mt-0.5">
                        {{ examTypes?.total || 0 }} type{{ (examTypes?.total || 0) === 1 ? '' : 's' }}
                        <span v-if="search">matching "{{ search }}"</span>
                    </p>
                </div>
                <Link :href="route('exam-types.create')" class="btn btn-primary btn-sm gap-1.5">
                    <PlusIcon class="w-4 h-4" /> Add Exam Type
                </Link>
            </div>

            <section class="surface overflow-hidden">
                <header class="surface-header">
                    <div class="flex-1 max-w-md">
                        <SearchFilter v-model="search" placeholder="Search exam types…" />
                    </div>
                </header>

                <div class="table-sticky-wrap" style="--table-max-h: 65vh;" v-if="examTypes?.data?.length">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="w-12">#</th>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Description</th>
                                <th class="text-center">Order</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(type, i) in examTypes.data" :key="type.id">
                                <td class="text-xs font-mono text-base-content/55 tabular-nums">{{ examTypes.from + i }}</td>
                                <td class="font-bold text-sm">{{ type.name }}</td>
                                <td><span class="badge badge-outline badge-sm font-mono">{{ type.slug }}</span></td>
                                <td class="text-[13px] text-base-content/75 max-w-xs truncate" :title="type.description">{{ type.description || '—' }}</td>
                                <td class="text-center text-xs font-mono text-base-content/55 tabular-nums">{{ type.sort_order }}</td>
                                <td>
                                    <span :class="['badge badge-sm', type.is_active ? 'badge-success' : 'badge-error']">
                                        {{ type.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <div class="flex gap-0.5 justify-end">
                                        <Link :href="route('exam-types.edit', type.id)" class="btn btn-ghost btn-xs btn-square" title="Edit">
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </Link>
                                        <button @click="confirmDeleteType(type)" class="btn btn-ghost btn-xs btn-square text-error" title="Delete">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState v-if="!examTypes?.data?.length"
                    title="No exam types found"
                    :description="search ? 'Try a different search term.' : 'Get started by adding your first exam type.'"
                    action-text="Add Exam Type"
                    :action-href="route('exam-types.create')" />

                <footer v-if="examTypes?.data?.length && examTypes.last_page > 1" class="surface-footer">
                    <span class="text-xs text-base-content/55 font-medium">
                        Showing <span class="text-base-content font-bold">{{ examTypes.from }}–{{ examTypes.to }}</span>
                        of <span class="text-base-content font-bold">{{ examTypes.total }}</span>
                    </span>
                    <Pagination :links="examTypes.links" />
                </footer>
            </section>
        </div>

        <ConfirmDialog
            :show="confirmDelete"
            title="Delete Exam Type"
            :message="`Are you sure you want to delete ${typeToDelete?.name}? This action cannot be undone.`"
            type="danger"
            @confirm="deleteType"
            @cancel="confirmDelete = false"
        />
    </AppLayout>
</template>
