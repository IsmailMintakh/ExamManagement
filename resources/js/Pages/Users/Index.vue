<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import {
    PlusIcon, PencilSquareIcon, TrashIcon, MagnifyingGlassIcon,
    FunnelIcon, ChevronDownIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'
import { usePermissions } from '@/Composables/usePermissions'
import { formatStatus } from '@/Utils/format'

const { can } = usePermissions()

const props = defineProps({
    users: Object,
    filters: Object,
    roles: Array,
})

// Filter state — bound 1:1 to backend query params
const search = ref(props.filters?.search || '')
const role = ref(props.filters?.role || '')
const status = ref(props.filters?.status || '')

const activeFilterCount = computed(() => [role.value, status.value].filter(Boolean).length)
const filtersOpen = ref(activeFilterCount.value > 0)

let timer = null
function pushFilters() {
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => {
        router.get(route('users.index'), {
            search: search.value || undefined,
            role: role.value || undefined,
            status: status.value || undefined,
        }, {
            preserveState: true, preserveScroll: true, replace: true, only: ['users', 'filters'],
        })
    }, 300)
}
watch([search, role, status], pushFilters)

function clearFilters() { role.value = ''; status.value = '' }

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
        <div class="space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight">User Management</h1>
                    <p class="text-sm text-base-content/55 mt-0.5">
                        {{ users?.total || 0 }} user{{ (users?.total || 0) === 1 ? '' : 's' }}
                        <span v-if="search">matching "{{ search }}"</span>
                    </p>
                </div>
                <Link v-if="can('users.create')" :href="route('users.create')" class="btn btn-primary btn-sm gap-1.5">
                    <PlusIcon class="w-4 h-4" /> Add User
                </Link>
            </div>

            <section class="surface overflow-hidden">
                <header class="surface-header">
                    <div class="relative flex-1 max-w-md">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-base-content/40" />
                        <input v-model="search" type="text"
                            placeholder="Search users by name or email…"
                            class="input input-bordered input-sm w-full pl-9 text-sm" />
                    </div>
                    <button type="button" @click="filtersOpen = !filtersOpen"
                        class="btn btn-sm gap-1.5"
                        :class="filtersOpen ? 'btn-primary' : 'btn-outline'">
                        <FunnelIcon class="w-4 h-4" /> Filters
                        <span v-if="activeFilterCount > 0" class="badge badge-sm badge-warning text-warning-content tabular-nums">{{ activeFilterCount }}</span>
                        <ChevronDownIcon class="w-3.5 h-3.5 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" />
                    </button>
                </header>

                <Transition name="filter-panel">
                    <div v-if="filtersOpen" class="border-b border-base-200 bg-base-200/30 px-5 sm:px-6 py-4 space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/60 mb-1.5 block">Role</label>
                                <select v-model="role" class="select select-bordered select-sm w-full text-sm">
                                    <option value="">All roles</option>
                                    <option v-for="r in roles" :key="r.name || r" :value="r.name || r">{{ formatStatus(r.name || r) }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/60 mb-1.5 block">Status</label>
                                <select v-model="status" class="select select-bordered select-sm w-full text-sm">
                                    <option value="">All statuses</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div v-if="activeFilterCount > 0" class="flex items-center justify-between gap-2 pt-2 border-t border-base-200">
                            <span class="text-xs text-base-content/55">
                                <span class="font-bold text-base-content">{{ activeFilterCount }}</span>
                                filter{{ activeFilterCount === 1 ? '' : 's' }} applied
                                · {{ users?.total || 0 }} user{{ (users?.total || 0) === 1 ? '' : 's' }} found
                            </span>
                            <button type="button" @click="clearFilters" class="btn btn-ghost btn-xs gap-1 text-base-content/65">
                                <XMarkIcon class="w-3.5 h-3.5" /> Clear all
                            </button>
                        </div>
                    </div>
                </Transition>

                <div class="table-sticky-wrap" style="--table-max-h: 65vh;" v-if="users?.data?.length">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="w-12">#</th>
                                <th>User</th>
                                <th class="hidden md:table-cell">Phone</th>
                                <th class="hidden md:table-cell">School</th>
                                <th>Roles</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(user, i) in users.data" :key="user.id">
                                <td class="text-xs font-mono text-base-content/55 tabular-nums">{{ users.from + i }}</td>
                                <td>
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-slate-500 to-slate-700 text-white flex items-center justify-center text-xs font-bold flex-shrink-0 overflow-hidden">
                                            <img v-if="user.avatar_url" :src="user.avatar_url" :alt="user.name" class="w-full h-full object-cover" />
                                            <span v-else>{{ user.name?.charAt(0)?.toUpperCase() }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-sm truncate">{{ user.name }}</div>
                                            <div class="text-[11px] text-base-content/55 truncate">{{ user.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden md:table-cell text-[13px] text-base-content/75 tabular-nums whitespace-nowrap">{{ user.phone || '—' }}</td>
                                <td class="hidden md:table-cell text-[13px] text-base-content/75 truncate max-w-[180px]" :title="user.school?.name">{{ user.school?.name || '—' }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="role in user.roles" :key="role.id || role" class="badge badge-outline badge-sm font-medium">
                                            {{ formatStatus(role.name || role) }}
                                        </span>
                                        <span v-if="!user.roles?.length" class="text-xs text-base-content/40">—</span>
                                    </div>
                                </td>
                                <td>
                                    <span :class="['badge badge-sm', user.is_active ? 'badge-success' : 'badge-error']">
                                        {{ user.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <div class="flex gap-0.5 justify-end">
                                        <Link v-if="can('users.edit')" :href="route('users.edit', user.id)" class="btn btn-ghost btn-xs btn-square" title="Edit">
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </Link>
                                        <button v-if="can('users.delete')" @click="confirmDeleteUser(user)" class="btn btn-ghost btn-xs btn-square text-error" title="Delete">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState v-if="!users?.data?.length"
                    title="No users found"
                    :description="search ? 'Try a different search term.' : 'Get started by adding your first user.'"
                    action-text="Add User"
                    :action-href="can('users.create') ? route('users.create') : null" />

                <footer v-if="users?.data?.length && users.last_page > 1" class="surface-footer">
                    <span class="text-xs text-base-content/55 font-medium">
                        Showing <span class="text-base-content font-bold">{{ users.from }}–{{ users.to }}</span>
                        of <span class="text-base-content font-bold">{{ users.total }}</span>
                    </span>
                    <Pagination :links="users.links" />
                </footer>
            </section>
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

<style scoped>
.filter-panel-enter-active,
.filter-panel-leave-active {
    transition: opacity 0.2s ease, max-height 0.25s ease;
    overflow: hidden;
}
.filter-panel-enter-from,
.filter-panel-leave-to {
    opacity: 0;
    max-height: 0;
}
.filter-panel-enter-to,
.filter-panel-leave-from {
    opacity: 1;
    max-height: 400px;
}
</style>
