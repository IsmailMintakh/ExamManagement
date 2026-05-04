<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchFilter from '@/Components/SearchFilter.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { PlusIcon, PencilSquareIcon, TrashIcon, EyeIcon } from '@heroicons/vue/24/outline'
import { usePermissions } from '@/Composables/usePermissions'
import { useDebouncedSearch } from '@/Composables/useDebouncedSearch'

const { can } = usePermissions()

const props = defineProps({
    schools: Object,
    filters: Object,
})

const search = useDebouncedSearch({
    routeName: 'schools.index',
    initial: props.filters?.search || '',
    only: ['schools', 'filters'],
    delay: 0,
})
const confirmDelete = ref(false)
const schoolToDelete = ref(null)

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
        <div class="space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight">Schools Management</h1>
                    <p class="text-sm text-base-content/55 mt-0.5">
                        {{ schools?.total || 0 }} school{{ (schools?.total || 0) === 1 ? '' : 's' }}
                        <span v-if="search">matching "{{ search }}"</span>
                    </p>
                </div>
                <Link v-if="can('schools.create')" :href="route('schools.create')" class="btn btn-primary btn-sm gap-1.5">
                    <PlusIcon class="w-4 h-4" /> Add School
                </Link>
            </div>

            <section class="surface overflow-hidden">
                <header class="surface-header">
                    <div class="flex-1 max-w-md">
                        <SearchFilter v-model="search" placeholder="Search schools by name or code…" />
                    </div>
                </header>

                <div class="table-sticky-wrap" style="--table-max-h: 65vh;" v-if="schools?.data?.length">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="w-12">#</th>
                                <th>Code</th>
                                <th>School</th>
                                <th>Phone</th>
                                <th>Principal</th>
                                <th class="text-center">Students</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(school, i) in schools.data" :key="school.id">
                                <td class="text-xs font-mono text-base-content/55 tabular-nums">{{ schools.from + i }}</td>
                                <td><span class="badge badge-outline badge-sm font-mono">{{ school.code }}</span></td>
                                <td>
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div v-if="!school.logo_url" class="w-10 h-10 rounded-lg bg-primary/15 text-primary flex items-center justify-center text-sm font-bold flex-shrink-0">
                                            {{ school.name?.charAt(0) }}
                                        </div>
                                        <img v-else :src="school.logo_url" class="w-10 h-10 rounded-lg object-cover flex-shrink-0" />
                                        <div class="min-w-0">
                                            <Link :href="route('schools.show', school.id)" class="font-bold text-sm truncate hover:text-primary transition-colors">
                                                {{ school.name }}
                                            </Link>
                                            <div class="text-[11px] text-base-content/55 truncate">{{ school.email || '—' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-[13px] text-base-content/75 tabular-nums whitespace-nowrap">{{ school.phone || '—' }}</td>
                                <td class="text-[13px] text-base-content/75 truncate max-w-[180px]" :title="school.principal?.name">{{ school.principal?.name || '—' }}</td>
                                <td class="text-center"><span class="badge badge-info badge-sm tabular-nums">{{ school.students_count ?? 0 }}</span></td>
                                <td>
                                    <span :class="['badge badge-sm', school.is_active ? 'badge-success' : 'badge-error']">
                                        {{ school.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <div class="flex gap-0.5 justify-end">
                                        <Link :href="route('schools.show', school.id)" class="btn btn-ghost btn-xs btn-square" title="View">
                                            <EyeIcon class="w-4 h-4" />
                                        </Link>
                                        <Link v-if="can('schools.edit')" :href="route('schools.edit', school.id)" class="btn btn-ghost btn-xs btn-square" title="Edit">
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </Link>
                                        <button v-if="can('schools.delete')" @click="confirmDeleteSchool(school)" class="btn btn-ghost btn-xs btn-square text-error" title="Delete">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState v-if="!schools?.data?.length"
                    title="No schools found"
                    :description="search ? 'Try a different search term.' : 'Get started by adding your first school.'"
                    action-text="Add School"
                    :action-href="can('schools.create') ? route('schools.create') : null" />

                <footer v-if="schools?.data?.length && schools.last_page > 1" class="surface-footer">
                    <span class="text-xs text-base-content/55 font-medium">
                        Showing <span class="text-base-content font-bold">{{ schools.from }}–{{ schools.to }}</span>
                        of <span class="text-base-content font-bold">{{ schools.total }}</span>
                    </span>
                    <Pagination :links="schools.links" />
                </footer>
            </section>
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
