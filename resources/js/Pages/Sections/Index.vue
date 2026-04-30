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
    sections: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const confirmDelete = ref(false)
const sectionToDelete = ref(null)

function handleSearch(val) {
    router.get(route('sections.index'), { search: val }, { preserveState: true, replace: true })
}

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
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h1 class="text-2xl font-bold">Sections Management</h1>
                <Link v-if="can('sections.create')" :href="route('sections.create')" class="btn btn-primary gap-2">
                    <PlusIcon class="w-5 h-5" /> Add Section
                </Link>
            </div>

            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <SearchFilter v-model="search" @update:model-value="handleSearch" />

                    <div class="overflow-x-auto mt-4" v-if="sections?.data?.length">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Class Teacher</th>
                                    <th>Students</th>
                                    <th>Capacity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(section, i) in sections.data" :key="section.id">
                                    <td>{{ sections.from + i }}</td>
                                    <td class="font-medium">{{ section.school_class?.name || '-' }}</td>
                                    <td>{{ section.name }}</td>
                                    <td class="text-sm">{{ section.class_teacher?.name || '-' }}</td>
                                    <td><span class="badge badge-info badge-sm">{{ section.students_count ?? 0 }}</span></td>
                                    <td class="text-sm">{{ section.capacity || '-' }}</td>
                                    <td>
                                        <div class="flex gap-1">
                                            <Link v-if="can('sections.edit')" :href="route('sections.edit', section.id)" class="btn btn-ghost btn-xs">
                                                <PencilSquareIcon class="w-4 h-4" />
                                            </Link>
                                            <button v-if="can('sections.delete')" @click="confirmDeleteSection(section)" class="btn btn-ghost btn-xs text-error">
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-4">
                            <Pagination :links="sections.links" />
                        </div>
                    </div>
                    <EmptyState v-else title="No sections found" description="Get started by adding your first section." action-text="Add Section" :action-href="route('sections.create')" />
                </div>
            </div>
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
