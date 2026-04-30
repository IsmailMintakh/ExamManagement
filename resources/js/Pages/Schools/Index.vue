<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from '@/Components/DataTable.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import SearchFilter from '@/Components/SearchFilter.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { PlusIcon, PencilSquareIcon, TrashIcon, EyeIcon } from '@heroicons/vue/24/outline'
import { usePermissions } from '@/Composables/usePermissions'

const { can } = usePermissions()

const props = defineProps({
    schools: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const confirmDelete = ref(false)
const schoolToDelete = ref(null)

const columns = [
    { key: 'code', label: 'Code', sortable: true },
    { key: 'name', label: 'School Name', sortable: true },
    { key: 'phone', label: 'Phone' },
    { key: 'principal', label: 'Principal' },
    { key: 'students_count', label: 'Students', sortable: true },
    { key: 'is_active', label: 'Status' },
]

function handleSearch(val) {
    router.get(route('schools.index'), { search: val }, { preserveState: true, replace: true })
}

function deleteSchool() {
    if (schoolToDelete.value) {
        router.delete(route('schools.destroy', schoolToDelete.value.id), {
            onSuccess: () => { confirmDelete.value = false; schoolToDelete.value = null }
        })
    }
}

function confirmDeleteSchool(school) {
    schoolToDelete.value = school
    confirmDelete.value = true
}
</script>

<template>
    <Head title="Schools" />
    <AppLayout :breadcrumbs="[{ label: 'Schools' }]">
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h1 class="text-2xl font-bold">Schools Management</h1>
                <Link v-if="can('schools.create')" :href="route('schools.create')" class="btn btn-primary gap-2">
                    <PlusIcon class="w-5 h-5" /> Add School
                </Link>
            </div>

            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <SearchFilter v-model="search" @update:model-value="handleSearch" />

                    <div class="overflow-x-auto mt-4" v-if="schools?.data?.length">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Code</th>
                                    <th>School Name</th>
                                    <th>Phone</th>
                                    <th>Principal</th>
                                    <th>Students</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(school, i) in schools.data" :key="school.id">
                                    <td>{{ schools.from + i }}</td>
                                    <td><span class="badge badge-ghost">{{ school.code }}</span></td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="avatar placeholder" v-if="!school.logo_url">
                                                <div class="bg-primary text-primary-content rounded-lg w-10 h-10">
                                                    <span class="text-xs">{{ school.name?.charAt(0) }}</span>
                                                </div>
                                            </div>
                                            <img v-else :src="school.logo_url" class="w-10 h-10 rounded-lg object-cover" />
                                            <div>
                                                <div class="font-bold text-sm">{{ school.name }}</div>
                                                <div class="text-xs text-base-content/60">{{ school.email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-sm">{{ school.phone || '-' }}</td>
                                    <td class="text-sm">{{ school.principal?.name || '-' }}</td>
                                    <td><span class="badge badge-info badge-sm">{{ school.students_count ?? 0 }}</span></td>
                                    <td>
                                        <span :class="['badge badge-sm', school.is_active ? 'badge-success' : 'badge-error']">
                                            {{ school.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex gap-1">
                                            <Link :href="route('schools.show', school.id)" class="btn btn-ghost btn-xs">
                                                <EyeIcon class="w-4 h-4" />
                                            </Link>
                                            <Link v-if="can('schools.edit')" :href="route('schools.edit', school.id)" class="btn btn-ghost btn-xs">
                                                <PencilSquareIcon class="w-4 h-4" />
                                            </Link>
                                            <button v-if="can('schools.delete')" @click="confirmDeleteSchool(school)" class="btn btn-ghost btn-xs text-error">
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-4">
                            <Pagination :links="schools.links" />
                        </div>
                    </div>
                    <EmptyState v-else title="No schools found" description="Get started by adding your first school." action-text="Add School" :action-href="route('schools.create')" />
                </div>
            </div>
        </div>

        <ConfirmDialog
            :show="confirmDelete"
            title="Delete School"
            :message="`Are you sure you want to delete ${schoolToDelete?.name}? This action cannot be undone.`"
            type="danger"
            @confirm="deleteSchool"
            @cancel="confirmDelete = false"
        />
    </AppLayout>
</template>
