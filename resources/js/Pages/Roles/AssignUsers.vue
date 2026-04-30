<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import {
    UsersIcon, ShieldCheckIcon, MagnifyingGlassIcon,
    CheckIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    users: Object,
    roles: Array,
})

const editingUser = ref(null)
const editingRoles = ref([])

function startEdit(user) {
    editingUser.value = user.id
    editingRoles.value = user.roles?.map(r => r.name) || []
}

function cancelEdit() {
    editingUser.value = null
    editingRoles.value = []
}

function toggleRole(roleName) {
    const idx = editingRoles.value.indexOf(roleName)
    if (idx > -1) editingRoles.value.splice(idx, 1)
    else editingRoles.value.push(roleName)
}

function saveRoles(user) {
    router.post(route('roles.sync-user', user.id), {
        role_names: editingRoles.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { editingUser.value = null }
    })
}

const search = ref('')
function handleSearch() {
    router.get(route('roles.assign-users'), { search: search.value }, { preserveState: true, replace: true })
}

const roleColors = {
    'super-admin': 'badge-primary',
    'school-admin': 'badge-secondary',
    'class-teacher': 'badge-accent',
    'subject-teacher': 'badge-info',
    'student': 'badge-success',
    'parent': 'badge-warning',
}
</script>

<template>
    <Head title="Assign User Roles" />
    <AppLayout :breadcrumbs="[
        { label: 'Roles & Permissions', href: route('roles.index') },
        { label: 'Assign Users' }
    ]">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Assign User Roles</h1>
                    <p class="mt-1 text-sm text-base-content/50">Click a user to edit their roles. Users can have multiple roles.</p>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card-section">
                <div class="px-5 py-3 border-b border-base-200">
                    <div class="relative">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-base-content/35" />
                        <input
                            v-model="search"
                            @input="handleSearch"
                            type="text"
                            placeholder="Search users by name or email..."
                            class="input input-bordered input-sm w-full pl-10 text-sm"
                        />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>School</th>
                                <th>Current Roles</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="user in users.data" :key="user.id">
                                <!-- View row -->
                                <tr v-if="editingUser !== user.id" class="hover">
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-secondary text-white text-xs font-bold">
                                                {{ user.name?.charAt(0)?.toUpperCase() }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold">{{ user.name }}</p>
                                                <p class="text-2xs text-base-content/50">{{ user.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-sm">{{ user.school?.name || '—' }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            <span v-for="role in user.roles" :key="role.id"
                                                class="badge badge-sm" :class="roleColors[role.name] || 'badge-ghost'">
                                                {{ role.name }}
                                            </span>
                                            <span v-if="!user.roles?.length" class="text-xs text-base-content/40">No roles</span>
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <button @click="startEdit(user)" class="btn btn-ghost btn-xs gap-1">
                                            <ShieldCheckIcon class="w-3.5 h-3.5" />
                                            Edit Roles
                                        </button>
                                    </td>
                                </tr>

                                <!-- Edit row -->
                                <tr v-else class="bg-primary/5">
                                    <td colspan="4">
                                        <div class="space-y-3 p-2">
                                            <div class="flex items-center gap-2.5">
                                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-secondary text-white text-xs font-bold">
                                                    {{ user.name?.charAt(0)?.toUpperCase() }}
                                                </div>
                                                <div class="flex-1">
                                                    <p class="text-sm font-semibold">{{ user.name }}</p>
                                                    <p class="text-2xs text-base-content/50">{{ user.email }}</p>
                                                </div>
                                            </div>
                                            <div>
                                                <p class="text-2xs uppercase tracking-wider text-base-content/40 mb-2">Select Roles:</p>
                                                <div class="flex flex-wrap gap-2">
                                                    <label v-for="role in roles" :key="role.id"
                                                        class="flex items-center gap-2 rounded-lg border px-3 py-1.5 cursor-pointer transition-all"
                                                        :class="editingRoles.includes(role.name) ? 'border-primary bg-primary/10' : 'border-base-200 hover:bg-base-200'"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            :checked="editingRoles.includes(role.name)"
                                                            @change="toggleRole(role.name)"
                                                            class="checkbox checkbox-primary checkbox-xs"
                                                        />
                                                        <span class="text-xs font-medium">{{ role.label }}</span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="flex justify-end gap-2 pt-2 border-t border-base-200">
                                                <button @click="cancelEdit" class="btn btn-ghost btn-sm gap-1">
                                                    <XMarkIcon class="w-4 h-4" /> Cancel
                                                </button>
                                                <button @click="saveRoles(user)" class="btn btn-primary btn-sm gap-1">
                                                    <CheckIcon class="w-4 h-4" /> Save Roles
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="!users.data?.length">
                                <td colspan="4" class="text-center text-sm text-base-content/40 py-8">No users found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-5 py-3 border-t border-base-200">
                    <Pagination :links="users.links" :from="users.from" :to="users.to" :total="users.total" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
