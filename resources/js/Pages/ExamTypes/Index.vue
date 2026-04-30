<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchFilter from '@/Components/SearchFilter.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    examTypes: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const confirmDelete = ref(false)
const typeToDelete = ref(null)

function handleSearch(val) {
    router.get(route('exam-types.index'), { search: val }, { preserveState: true, replace: true })
}

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
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h1 class="text-2xl font-bold">Exam Types</h1>
                <Link :href="route('exam-types.create')" class="btn btn-primary gap-2">
                    <PlusIcon class="w-5 h-5" /> Add Exam Type
                </Link>
            </div>

            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <SearchFilter v-model="search" @update:model-value="handleSearch" />

                    <div class="overflow-x-auto mt-4" v-if="examTypes?.data?.length">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Slug</th>
                                    <th>Description</th>
                                    <th>Sort Order</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(type, i) in examTypes.data" :key="type.id">
                                    <td>{{ examTypes.from + i }}</td>
                                    <td class="font-medium">{{ type.name }}</td>
                                    <td><span class="badge badge-ghost">{{ type.slug }}</span></td>
                                    <td class="text-sm max-w-xs truncate">{{ type.description || '-' }}</td>
                                    <td>{{ type.sort_order }}</td>
                                    <td>
                                        <span :class="['badge badge-sm', type.is_active ? 'badge-success' : 'badge-error']">
                                            {{ type.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex gap-1">
                                            <Link :href="route('exam-types.edit', type.id)" class="btn btn-ghost btn-xs">
                                                <PencilSquareIcon class="w-4 h-4" />
                                            </Link>
                                            <button @click="confirmDeleteType(type)" class="btn btn-ghost btn-xs text-error">
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-4">
                            <Pagination :links="examTypes.links" />
                        </div>
                    </div>
                    <EmptyState v-else title="No exam types found" description="Get started by adding your first exam type." action-text="Add Exam Type" :action-href="route('exam-types.create')" />
                </div>
            </div>
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
