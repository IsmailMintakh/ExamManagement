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

const { can } = usePermissions()

const props = defineProps({
    classes: Object,
    filters: Object,
    schools: Array,
})

const search = ref(props.filters?.search || '')
const confirmDelete = ref(false)
const classToDelete = ref(null)

function handleSearch(val) {
    router.get(route('classes.index'), { search: val }, { preserveState: true, replace: true })
}

function handleFilter(filters) {
    router.get(route('classes.index'), { ...filters, search: search.value }, { preserveState: true, replace: true })
}

function confirmDeleteClass(cls) {
    classToDelete.value = cls
    confirmDelete.value = true
}

function deleteClass() {
    if (classToDelete.value) {
        router.delete(route('classes.destroy', classToDelete.value.id), {
            onSuccess: () => { confirmDelete.value = false; classToDelete.value = null }
        })
    }
}
</script>

<template>
    <Head title="Classes" />
    <AppLayout :breadcrumbs="[{ label: 'Classes' }]">
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h1 class="text-2xl font-bold">Classes Management</h1>
                <Link v-if="can('classes.create')" :href="route('classes.create')" class="btn btn-primary gap-2">
                    <PlusIcon class="w-5 h-5" /> Add Class
                </Link>
            </div>

            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <SearchFilter
                        v-model="search"
                        @update:model-value="handleSearch"
                        :filters="[
                            { key: 'school_id', label: 'School', options: schools?.map(s => ({ value: s.id, label: s.name })) || [] },
                        ]"
                        @filter="handleFilter"
                    />

                    <div class="overflow-x-auto mt-4" v-if="classes?.data?.length">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>School</th>
                                    <th>Class Name</th>
                                    <th>Sections</th>
                                    <th>Students</th>
                                    <th>Subjects</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(cls, i) in classes.data" :key="cls.id">
                                    <td>{{ classes.from + i }}</td>
                                    <td class="text-sm">{{ cls.school?.name || '-' }}</td>
                                    <td class="font-medium">{{ cls.name }}</td>
                                    <td><span class="badge badge-info badge-sm">{{ cls.sections_count ?? 0 }}</span></td>
                                    <td><span class="badge badge-ghost badge-sm">{{ cls.students_count ?? 0 }}</span></td>
                                    <td><span class="badge badge-ghost badge-sm">{{ cls.subjects_count ?? 0 }}</span></td>
                                    <td>
                                        <div class="flex gap-1">
                                            <Link v-if="can('classes.edit')" :href="route('classes.edit', cls.id)" class="btn btn-ghost btn-xs">
                                                <PencilSquareIcon class="w-4 h-4" />
                                            </Link>
                                            <button v-if="can('classes.delete')" @click="confirmDeleteClass(cls)" class="btn btn-ghost btn-xs text-error">
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-4">
                            <Pagination :links="classes.links" />
                        </div>
                    </div>
                    <EmptyState v-else title="No classes found" description="Get started by adding your first class." action-text="Add Class" :action-href="route('classes.create')" />
                </div>
            </div>
        </div>

        <ConfirmDialog
            :show="confirmDelete"
            title="Delete Class"
            :message="`Are you sure you want to delete ${classToDelete?.name}? This will remove all associated sections and data.`"
            type="danger"
            @confirm="deleteClass"
            @cancel="confirmDelete = false"
        />
    </AppLayout>
</template>
