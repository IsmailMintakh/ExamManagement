<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import {
    PlusIcon, UserGroupIcon, PencilSquareIcon, TrashIcon,
    StarIcon, MagnifyingGlassIcon, AcademicCapIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    members:     { type: Object, default: () => ({ data: [] }) },
    filters:     { type: Object, default: () => ({}) },
    departments: { type: Array,  default: () => [] },
})

const search = ref(props.filters.search || '')
const department = ref(props.filters.department || '')

let debounceTimer = null
watch([search, department], () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get(route('website.faculty.index'),
            { search: search.value || undefined, department: department.value || undefined },
            { preserveState: true, preserveScroll: true, replace: true })
    }, 300)
})

function destroy(id) {
    if (!confirm('Remove this faculty member?')) return
    router.delete(route('website.faculty.destroy', id), { preserveScroll: true })
}

function initials(name) {
    return (name || '?').split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase()
}
</script>

<template>
    <Head title="Faculty Members" />
    <AppLayout :breadcrumbs="[{ label: 'Website' }, { label: 'Faculty' }]">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight">Faculty Members</h1>
                    <p class="text-sm text-base-content/55 mt-0.5">
                        {{ members?.total || 0 }} member{{ (members?.total || 0) === 1 ? '' : 's' }} on the public Faculty page
                    </p>
                </div>
                <Link :href="route('website.faculty.create')" class="btn btn-primary btn-sm gap-1.5">
                    <PlusIcon class="w-4 h-4" /> Add Faculty Member
                </Link>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[220px] max-w-md">
                    <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/40" />
                    <input v-model="search" type="text" placeholder="Search by name, designation…"
                        class="input input-bordered input-sm w-full pl-9 text-sm" />
                </div>
                <select v-model="department" class="select select-bordered select-sm text-sm">
                    <option value="">All departments</option>
                    <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
                </select>
            </div>

            <EmptyState v-if="!members.data?.length"
                :icon="UserGroupIcon"
                title="No faculty members yet"
                description="Add your first teacher or staff member. They'll appear on the public Faculty page."
                action-text="Add First Member"
                :action-href="route('website.faculty.create')" />

            <template v-else>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div v-for="m in members.data" :key="m.id" class="surface surface-hover !p-4">
                        <div class="flex items-start gap-3">
                            <div class="relative shrink-0">
                                <div class="w-16 h-16 rounded-2xl overflow-hidden bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center text-white text-lg font-bold">
                                    <img v-if="m.photo_url" :src="m.photo_url" :alt="m.name" class="w-full h-full object-cover" />
                                    <span v-else>{{ initials(m.name) }}</span>
                                </div>
                                <StarIcon v-if="m.is_featured" class="absolute -top-1 -right-1 w-4 h-4 text-amber-500 fill-amber-500" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-sm truncate">{{ m.name }}</h3>
                                <p v-if="m.designation" class="text-xs text-base-content/65 truncate mt-0.5">{{ m.designation }}</p>
                                <p v-if="m.department" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold mt-1 inline-flex items-center gap-1">
                                    <AcademicCapIcon class="w-3 h-3" /> {{ m.department }}
                                </p>
                            </div>
                        </div>
                        <p v-if="m.qualification" class="text-[11px] text-base-content/55 mt-3 line-clamp-2">
                            {{ m.qualification }}
                        </p>
                        <div class="flex items-center justify-between gap-1 mt-3 pt-3 border-t border-base-200">
                            <span v-if="m.is_principal" class="badge badge-sm badge-warning gap-1">Principal</span>
                            <span v-else-if="!m.is_active" class="badge badge-sm badge-ghost">Hidden</span>
                            <span v-else class="text-[11px] text-success font-bold">Active</span>
                            <div class="flex gap-0.5">
                                <Link :href="route('website.faculty.edit', m.id)" class="btn btn-ghost btn-xs btn-square" title="Edit">
                                    <PencilSquareIcon class="w-3.5 h-3.5" />
                                </Link>
                                <button @click="destroy(m.id)" class="btn btn-ghost btn-xs btn-square text-error" title="Delete">
                                    <TrashIcon class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <Pagination :links="members.links" :from="members.from" :to="members.to" :total="members.total" />
            </template>
        </div>
    </AppLayout>
</template>
