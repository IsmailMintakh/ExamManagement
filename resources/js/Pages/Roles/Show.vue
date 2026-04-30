<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    ShieldCheckIcon, KeyIcon, UsersIcon, CheckIcon,
    LockClosedIcon, ExclamationTriangleIcon, MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    role: Object,
    permissionGroups: Array,
    rolePermissionIds: Array,
    users: Array,
    usersCount: Number,
})

const isLocked = props.role.name === 'super-admin'

const form = useForm({
    permission_ids: [...props.rolePermissionIds],
})

const search = ref('')
const filteredGroups = computed(() => {
    if (!search.value) return props.permissionGroups
    const q = search.value.toLowerCase()
    return props.permissionGroups
        .map(g => ({
            ...g,
            permissions: g.permissions.filter(p => p.name.toLowerCase().includes(q) || g.label.toLowerCase().includes(q)),
        }))
        .filter(g => g.permissions.length > 0)
})

function togglePermission(id) {
    const idx = form.permission_ids.indexOf(id)
    if (idx > -1) form.permission_ids.splice(idx, 1)
    else form.permission_ids.push(id)
}

function toggleGroup(group) {
    const groupIds = group.permissions.map(p => p.id)
    const allSelected = groupIds.every(id => form.permission_ids.includes(id))
    if (allSelected) {
        form.permission_ids = form.permission_ids.filter(id => !groupIds.includes(id))
    } else {
        groupIds.forEach(id => {
            if (!form.permission_ids.includes(id)) form.permission_ids.push(id)
        })
    }
}

function isGroupChecked(group) {
    return group.permissions.every(p => form.permission_ids.includes(p.id))
}

function isGroupPartial(group) {
    const some = group.permissions.some(p => form.permission_ids.includes(p.id))
    const all = group.permissions.every(p => form.permission_ids.includes(p.id))
    return some && !all
}

function selectAll() {
    form.permission_ids = props.permissionGroups.flatMap(g => g.permissions.map(p => p.id))
}

function clearAll() {
    form.permission_ids = []
}

function save() {
    form.put(route('roles.update-permissions', props.role.id), { preserveScroll: true })
}

const totalPermissions = computed(() => props.permissionGroups.reduce((sum, g) => sum + g.permissions.length, 0))
const selectedCount = computed(() => form.permission_ids.length)
</script>

<template>
    <Head :title="`Role: ${role.label}`" />
    <AppLayout :breadcrumbs="[
        { label: 'Roles & Permissions', href: route('roles.index') },
        { label: role.label }
    ]">
        <div class="space-y-6">
            <!-- Role Header -->
            <div class="card-section">
                <div class="card-content">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                            <ShieldCheckIcon class="h-6 w-6 text-primary" />
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <h1 class="text-xl font-bold">{{ role.label }}</h1>
                                <span v-if="isLocked" class="badge badge-error badge-sm gap-1">
                                    <LockClosedIcon class="w-3 h-3" /> Locked
                                </span>
                                <span v-else-if="role.is_system" class="badge badge-ghost badge-sm">System Role</span>
                            </div>
                            <p class="mt-1 text-sm text-base-content/60">{{ role.description }}</p>
                            <div class="mt-3 flex items-center gap-4 text-xs">
                                <span class="inline-flex items-center gap-1 text-base-content/60">
                                    <UsersIcon class="w-3.5 h-3.5" />
                                    <strong>{{ usersCount }}</strong> users
                                </span>
                                <span class="inline-flex items-center gap-1 text-base-content/60">
                                    <KeyIcon class="w-3.5 h-3.5" />
                                    <strong>{{ selectedCount }}</strong> / {{ totalPermissions }} permissions
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Locked warning -->
            <div v-if="isLocked" class="rounded-xl border border-warning/20 bg-warning/5 px-5 py-4 flex items-start gap-3 text-sm">
                <ExclamationTriangleIcon class="w-5 h-5 text-warning shrink-0 mt-0.5" />
                <div>
                    <p class="font-semibold">Super Admin Permissions Locked</p>
                    <p class="mt-1 text-base-content/60 text-xs">
                        The Super Admin (DDO) role has full access by design and cannot be modified to maintain system integrity.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Permissions Grid -->
                <div class="lg:col-span-2">
                    <div class="card-section">
                        <div class="card-header flex-wrap gap-2">
                            <h3>Permissions</h3>
                            <div class="flex items-center gap-2">
                                <button v-if="!isLocked" type="button" @click="selectAll" class="btn btn-ghost btn-xs">Select All</button>
                                <button v-if="!isLocked" type="button" @click="clearAll" class="btn btn-ghost btn-xs">Clear All</button>
                            </div>
                        </div>
                        <div class="px-5 py-3 border-b border-base-200">
                            <div class="relative">
                                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-base-content/35" />
                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Search permissions..."
                                    class="input input-bordered input-sm w-full pl-10 text-sm"
                                />
                            </div>
                        </div>
                        <div class="card-content space-y-4 max-h-[60vh] overflow-y-auto">
                            <div v-for="group in filteredGroups" :key="group.module" class="rounded-xl border border-base-200 overflow-hidden">
                                <!-- Group header -->
                                <label class="flex items-center gap-3 bg-base-200/40 px-4 py-2.5 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        :checked="isGroupChecked(group)"
                                        :indeterminate.prop="isGroupPartial(group)"
                                        :disabled="isLocked"
                                        @change="toggleGroup(group)"
                                        class="checkbox checkbox-primary checkbox-sm"
                                    />
                                    <span class="flex-1 font-semibold text-sm">{{ group.label }}</span>
                                    <span class="text-xs text-base-content/50">
                                        {{ group.permissions.filter(p => form.permission_ids.includes(p.id)).length }} / {{ group.permissions.length }}
                                    </span>
                                </label>
                                <!-- Permissions -->
                                <div class="grid grid-cols-1 gap-1 p-2 sm:grid-cols-2">
                                    <label
                                        v-for="perm in group.permissions"
                                        :key="perm.id"
                                        class="flex items-center gap-2 rounded-lg px-3 py-1.5 cursor-pointer hover:bg-base-200 transition-colors"
                                        :class="{ 'opacity-50 cursor-not-allowed': isLocked }"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="form.permission_ids.includes(perm.id)"
                                            :disabled="isLocked"
                                            @change="togglePermission(perm.id)"
                                            class="checkbox checkbox-primary checkbox-xs"
                                        />
                                        <span class="text-xs text-base-content/70 capitalize">{{ perm.action.replace(/-/g, ' ') }}</span>
                                        <code class="ml-auto text-2xs text-base-content/40">{{ perm.name }}</code>
                                    </label>
                                </div>
                            </div>
                            <div v-if="filteredGroups.length === 0" class="text-center text-sm text-base-content/40 py-8">
                                No permissions found.
                            </div>
                        </div>
                        <!-- Save bar -->
                        <div v-if="!isLocked" class="border-t border-base-200 px-5 py-3 flex items-center justify-between">
                            <p class="text-xs text-base-content/50">
                                {{ selectedCount }} of {{ totalPermissions }} permissions selected
                            </p>
                            <button @click="save" :disabled="form.processing" class="btn btn-primary btn-sm gap-1.5">
                                <CheckIcon class="w-4 h-4" />
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Users with this role -->
                <div>
                    <div class="card-section">
                        <div class="card-header">
                            <h3>Users with this Role</h3>
                            <span class="badge badge-ghost badge-sm">{{ usersCount }}</span>
                        </div>
                        <div class="card-content">
                            <div v-if="users?.length" class="space-y-2 max-h-[55vh] overflow-y-auto">
                                <div v-for="user in users" :key="user.id" class="flex items-center gap-3 rounded-lg border border-base-200 p-2.5">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-secondary text-white text-xs font-bold">
                                        {{ user.name?.charAt(0)?.toUpperCase() }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold truncate">{{ user.name }}</p>
                                        <p class="text-2xs text-base-content/50 truncate">
                                            {{ user.school || user.email }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-center text-base-content/40 py-4">No users assigned to this role.</p>

                            <Link :href="route('roles.assign-users')" class="btn btn-outline btn-sm w-full mt-3 gap-1.5">
                                <UsersIcon class="w-4 h-4" /> Assign Users
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
