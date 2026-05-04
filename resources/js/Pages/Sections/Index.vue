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
import { useDebouncedSearch } from '@/Composables/useDebouncedSearch'

const { can } = usePermissions()

const props = defineProps({
    sections: Object,
    filters: Object,
})

const search = useDebouncedSearch({
    routeName: 'sections.index',
    initial: props.filters?.search || '',
    only: ['sections', 'filters'],
    delay: 0,
})
const confirmDelete = ref(false)
const sectionToDelete = ref(null)

function confirmDeleteSection(section) {
    sectionToDelete.value = section
    confirmDelete.value = true
}

function deleteSection() {
    if (sectionToDelete.value) {
        router.delete(route('sections.destroy', sectionToDelete.value.id), {
            onSuccess: () => { confirmDelete.value = false; sectionToDelete.value = null }
        })
    }
}
</script>

<template>
    <Head title="Sections" />
    <AppLayout :breadcrumbs="[{ label: 'Sections' }]">
        <div class="space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight">Sections</h1>
                    <p class="text-sm text-base-content/55 mt-0.5">
                        {{ sections?.total || 0 }} section{{ (sections?.total || 0) === 1 ? '' : 's' }}
                        <span v-if="search">matching "{{ search }}"</span>
                    </p>
                </div>
                <Link v-if="can('sections.create')" :href="route('sections.create')" class="btn btn-primary btn-sm gap-1.5">
                    <PlusIcon class="w-4 h-4" /> Add Section
                </Link>
            </div>

            <section class="surface overflow-hidden">
                <header class="surface-header">
                    <div class="flex-1 max-w-md">
                        <SearchFilter v-model="search" placeholder="Search sections…" />
                    </div>
                </header>

                <div class="table-sticky-wrap" style="--table-max-h: 65vh;" v-if="sections?.data?.length">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="w-12">#</th>
                                <th>Class</th>
                                <th>Section</th>
                                <th>Class Teacher</th>
                                <th class="text-center">Students</th>
                                <th class="text-center">Capacity</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(section, i) in sections.data" :key="section.id">
                                <td class="text-xs font-mono text-base-content/55 tabular-nums">{{ sections.from + i }}</td>
                                <td class="font-bold text-sm">{{ section.school_class?.name || '—' }}</td>
                                <td class="text-[13px] text-base-content/75">{{ section.name }}</td>
                                <td class="text-[13px] text-base-content/75 truncate max-w-[180px]" :title="section.class_teacher?.name">{{ section.class_teacher?.name || '—' }}</td>
                                <td class="text-center"><span class="badge badge-info badge-sm tabular-nums">{{ section.students_count ?? 0 }}</span></td>
                                <td class="text-center text-[13px] text-base-content/75 tabular-nums">{{ section.capacity || '—' }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <div class="flex gap-0.5 justify-end">
                                        <Link v-if="can('sections.edit')" :href="route('sections.edit', section.id)" class="btn btn-ghost btn-xs btn-square" title="Edit">
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </Link>
                                        <button v-if="can('sections.delete')" @click="confirmDeleteSection(section)" class="btn btn-ghost btn-xs btn-square text-error" title="Delete">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState v-if="!sections?.data?.length"
                    title="No sections found"
                    :description="search ? 'Try a different search term.' : 'Get started by adding your first section.'"
                    action-text="Add Section"
                    :action-href="can('sections.create') ? route('sections.create') : null" />

                <footer v-if="sections?.data?.length && sections.last_page > 1" class="surface-footer">
                    <span class="text-xs text-base-content/55 font-medium">
                        Showing <span class="text-base-content font-bold">{{ sections.from }}–{{ sections.to }}</span>
                        of <span class="text-base-content font-bold">{{ sections.total }}</span>
                    </span>
                    <Pagination :links="sections.links" />
                </footer>
            </section>
        </div>

        <ConfirmDialog
            :show="confirmDelete"
            title="Delete Section"
            :message="`Are you sure you want to delete ${sectionToDelete?.name}? This action cannot be undone.`"
            type="danger"
            @confirm="deleteSection"
            @cancel="confirmDelete = false"
        />
    </AppLayout>
</template>
