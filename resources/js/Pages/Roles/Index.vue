<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    ShieldCheckIcon, KeyIcon, UsersIcon,
    UserGroupIcon, AcademicCapIcon, BuildingOfficeIcon,
    UserCircleIcon, ChevronRightIcon, LockClosedIcon, BookOpenIcon,
} from '@heroicons/vue/24/outline'

defineProps({
    roles: Array,
    stats: Object,
})

const roleIcons = {
    'super-admin': BuildingOfficeIcon,
    'school-admin': BuildingOfficeIcon,
    'class-teacher': AcademicCapIcon,
    'subject-teacher': BookOpenIcon,
    'student': UserCircleIcon,
    'parent': UserGroupIcon,
}

const roleColors = {
    'super-admin': 'bg-primary/10 text-primary',
    'school-admin': 'bg-secondary/10 text-secondary',
    'class-teacher': 'bg-accent/10 text-accent',
    'subject-teacher': 'bg-info/10 text-info',
    'student': 'bg-success/10 text-success',
    'parent': 'bg-warning/10 text-warning',
}
</script>

<template>
    <Head title="Roles & Permissions" />
    <AppLayout :breadcrumbs="[{ label: 'Roles & Permissions' }]">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Roles & Permissions</h1>
                    <p class="mt-1 text-sm text-base-content/50">Manage user roles and what each role can do in the system</p>
                </div>
                <Link :href="route('roles.assign-users')" class="btn btn-primary btn-sm gap-2">
                    <UsersIcon class="w-4 h-4" /> Assign User Roles
                </Link>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard title="Total Roles" :value="stats.totalRoles" subtitle="System + custom" color="primary">
                    <template #icon><ShieldCheckIcon class="h-5 w-5" /></template>
                </StatCard>
                <StatCard title="Permissions" :value="stats.totalPermissions" subtitle="Granular controls" color="secondary">
                    <template #icon><KeyIcon class="h-5 w-5" /></template>
                </StatCard>
                <StatCard title="Total Users" :value="stats.totalUsers" subtitle="Across system" color="accent">
                    <template #icon><UsersIcon class="h-5 w-5" /></template>
                </StatCard>
            </div>

            <!-- Roles Grid -->
            <div class="card-section">
                <div class="card-header">
                    <h3>System Roles</h3>
                </div>
                <div class="card-content">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <Link
                            v-for="role in roles"
                            :key="role.id"
                            :href="route('roles.show', role.id)"
                            class="group flex items-start gap-4 rounded-xl border border-base-200 p-4 transition-all hover:border-primary/30 hover:shadow-md"
                        >
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl" :class="roleColors[role.name] || 'bg-base-200 text-base-content'">
                                <component :is="roleIcons[role.name] || ShieldCheckIcon" class="h-6 w-6" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <h4 class="font-semibold text-sm">{{ role.label }}</h4>
                                    <span v-if="role.name === 'super-admin'" class="badge badge-error badge-sm gap-1">
                                        <LockClosedIcon class="w-2.5 h-2.5" /> Locked
                                    </span>
                                    <span v-else-if="role.is_system" class="badge badge-ghost badge-sm">System</span>
                                </div>
                                <p class="mt-1 text-xs text-base-content/50 line-clamp-2">{{ role.description }}</p>
                                <div class="mt-2 flex items-center gap-3 text-xs">
                                    <span class="inline-flex items-center gap-1 text-base-content/60">
                                        <UsersIcon class="w-3 h-3" /> {{ role.users_count }} users
                                    </span>
                                    <span class="inline-flex items-center gap-1 text-base-content/60">
                                        <KeyIcon class="w-3 h-3" /> {{ role.permissions_count }} permissions
                                    </span>
                                </div>
                            </div>
                            <ChevronRightIcon class="h-5 w-5 shrink-0 text-base-content/30 transition-transform group-hover:translate-x-0.5 group-hover:text-primary" />
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Info banner -->
            <div class="rounded-xl border border-info/20 bg-info/5 px-5 py-4 flex items-start gap-3 text-sm">
                <ShieldCheckIcon class="w-5 h-5 text-info shrink-0 mt-0.5" />
                <div>
                    <p class="font-semibold">About Roles & Permissions</p>
                    <p class="mt-1 text-base-content/60 text-xs">
                        Click a role to view and customize its permissions. Super Admin (DDO) has all permissions and cannot be modified.
                        Use "Assign User Roles" to give roles to individual users.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
