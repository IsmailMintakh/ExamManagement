<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import { Head, Link } from '@inertiajs/vue3'
import { PencilSquareIcon, UserGroupIcon, AcademicCapIcon, BuildingOfficeIcon } from '@heroicons/vue/24/outline'

defineProps({
    school: Object,
    classes: Array,
    stats: Object,
})
</script>

<template>
    <Head :title="school.name" />
    <AppLayout :breadcrumbs="[{ label: 'Schools', href: route('schools.index') }, { label: school.name }]">
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="avatar placeholder">
                        <div class="bg-primary text-primary-content rounded-xl w-16 h-16" v-if="!school.logo_url">
                            <span class="text-2xl">{{ school.name?.charAt(0) }}</span>
                        </div>
                        <img v-else :src="school.logo_url" class="w-16 h-16 rounded-xl object-cover" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold">{{ school.name }}</h1>
                        <p class="text-base-content/60">{{ school.code }} &bull; {{ school.email }}</p>
                    </div>
                </div>
                <Link :href="route('schools.edit', school.id)" class="btn btn-outline btn-primary gap-2">
                    <PencilSquareIcon class="w-5 h-5" /> Edit School
                </Link>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <StatCard title="Classes" :value="stats?.classesCount ?? 0" color="primary">
                    <template #icon><AcademicCapIcon class="w-8 h-8" /></template>
                </StatCard>
                <StatCard title="Students" :value="stats?.studentsCount ?? 0" color="secondary">
                    <template #icon><UserGroupIcon class="w-8 h-8" /></template>
                </StatCard>
                <StatCard title="Teachers" :value="stats?.teachersCount ?? 0" color="accent">
                    <template #icon><BuildingOfficeIcon class="w-8 h-8" /></template>
                </StatCard>
            </div>

            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <h2 class="card-title">School Details</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div><span class="text-sm text-base-content/60">Address</span><p class="font-medium">{{ school.address || '-' }}</p></div>
                        <div><span class="text-sm text-base-content/60">Phone</span><p class="font-medium">{{ school.phone || '-' }}</p></div>
                        <div><span class="text-sm text-base-content/60">Email</span><p class="font-medium">{{ school.email || '-' }}</p></div>
                        <div><span class="text-sm text-base-content/60">Website</span><p class="font-medium">{{ school.website || '-' }}</p></div>
                        <div><span class="text-sm text-base-content/60">Principal</span><p class="font-medium">{{ school.principal?.name || '-' }}</p></div>
                        <div><span class="text-sm text-base-content/60">Status</span><p><span :class="['badge', school.is_active ? 'badge-success' : 'badge-error']">{{ school.is_active ? 'Active' : 'Inactive' }}</span></p></div>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow-md" v-if="classes?.length">
                <div class="card-body">
                    <h2 class="card-title">Classes & Sections</h2>
                    <div class="overflow-x-auto mt-2">
                        <table class="table">
                            <thead><tr><th>Class</th><th>Sections</th><th>Students</th></tr></thead>
                            <tbody>
                                <tr v-for="cls in classes" :key="cls.id">
                                    <td class="font-medium">{{ cls.name }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            <span v-for="sec in cls.sections" :key="sec.id" class="badge badge-outline badge-sm">{{ sec.name }}</span>
                                        </div>
                                    </td>
                                    <td>{{ cls.students_count }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
