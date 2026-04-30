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
    users: Object,
    filters: Object,
    roles: Array,
})

const search = ref(props.filters?.search || '')
const confirmDelete = ref(false)
const userToDelete = ref(null)

function handleSearch(val) {
    router.get(route('users.index'), { search: val }, { preserveState: true, replace: true })
}

function handleFilter(filters) {
    router.get(route('users.index'), { ...filters, search: search.value }, { preserveState: true, replace: true })
}

function confirmDeleteUser(user) {
    userToDelete.value = user
    confirmDelete.value = true
}

function deleteUser() {
    if (userToDelete.value) {
        router.delete(route('users.destroy', userToDelete.value.id), {
            onSuccess: () => { confirmDelete.value = false; userToDelete.value = null }
        })
    }
}
</script>

<template>
    <Head title="Users" />
    <AppLayout :breadcrumbs="[{ label: 'Users' }]">
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h1 class="text-2xl font-bold">User Management</h1>
                <Link v-if="can('users.create')" :href="route('users.create')" class="btn btn-primary gap-2">
                    <PlusIcon class="w-5 h-5" /> Add User
                </Link>
            </div>

            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <SearchFilter
                        v-model="search"
                        @update:model-value="handleSearch"
                        :filters="[
                            { key: 'role', label: 'Role', options: roles?.map(r => ({ value: r.name || r, label: r.name || r })) || [] },
                        ]"
                        @filter="handleFilter"
                    />

                    <div class="overflow-x-auto mt-4" v-if="users?.data?.length">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>School</th>
                                    <th>Roles</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(user, i) in users.data" :key="user.id">
                                    <td>{{ users.from + i }}</td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="avatar placeholder">
                                                <div class="bg-neutral text-neutral-content rounded-full w-8 h-8">
                                                    <span class="text-xs">{{ user.name?.charAt(0) }}</span>
                                                </div>
                                            </div>
                                            <span class="font-medium text-sm">{{ user.name }}</span>
                                        </div>
                                    </td>
                                    <td class="text-sm">{{ user.email }}</td>
                                    <td class="text-sm">{{ user.phone || '-' }}</td>
                                    <td class="text-sm">{{ user.school?.name || '-' }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            <span v-for="role in user.roles" :key="role.id || role" class="badge badge-outline badge-sm">
                                                {{ role.name || role }}
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span :class="['badge badge-sm', user.is_active ? 'badge-success' : 'badge-error']">
                                            {{ user.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex gap-1">
                                            <Link v-if="can('users.edit')" :href="route('users.edit', user.id)" class="btn btn-ghost btn-xs">
                                                <PencilSquareIcon class="w-4 h-4" />
                                            </Link>
                                            <button v-if="can('users.delete')" @click="confirmDeleteUser(user)" class="btn btn-ghost btn-xs text-error">
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-4">
                            <Pagination :links="users.links" />
                        </div>
                    </div>
                    <EmptyState v-else title="No users found" description="Get started by adding your first user." action-text="Add User" :action-href="route('users.create')" />
                </div>
            </div>
        </div>

        <ConfirmDialog
            :show="confirmDelete"
            title="Delete User"
            :message="`Are you sure you want to delete ${userToDelete?.name}? This action cannot be undone.`"
            type="danger"
            @confirm="deleteUser"
            @cancel="confirmDelete = false"
        />
    </AppLayout>
</template>
