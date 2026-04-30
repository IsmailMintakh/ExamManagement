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
    subjects: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const confirmDelete = ref(false)
const subjectToDelete = ref(null)

function handleSearch(val) {
    router.get(route('subjects.index'), { search: val }, { preserveState: true, replace: true })
}

function confirmDeleteSubject(subject) {
    subjectToDelete.value = subject
    confirmDelete.value = true
}

function deleteSubject() {
    if (subjectToDelete.value) {
        router.delete(route('subjects.destroy', subjectToDelete.value.id), {
            onSuccess: () => { confirmDelete.value = false; subjectToDelete.value = null }
        })
    }
}
</script>

<template>
    <Head title="Subjects" />
    <AppLayout :breadcrumbs="[{ label: 'Subjects' }]">
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h1 class="text-2xl font-bold">Subjects Management</h1>
                <Link v-if="can('subjects.create')" :href="route('subjects.create')" class="btn btn-primary gap-2">
                    <PlusIcon class="w-5 h-5" /> Add Subject
                </Link>
            </div>

            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <SearchFilter v-model="search" @update:model-value="handleSearch" />

                    <div class="overflow-x-auto mt-4" v-if="subjects?.data?.length">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Main Subject</th>
                                    <th>Sort Order</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(subject, i) in subjects.data" :key="subject.id">
                                    <td>{{ subjects.from + i }}</td>
                                    <td><span class="badge badge-ghost">{{ subject.code }}</span></td>
                                    <td class="font-medium">{{ subject.name }}</td>
                                    <td>
                                        <span :class="['badge badge-sm', subject.type === 'core' ? 'badge-primary' : 'badge-secondary']">
                                            {{ subject.type }}
                                        </span>
                                    </td>
                                    <td>
                                        <span v-if="subject.is_main" class="badge badge-success badge-sm">Main</span>
                                        <span v-else class="text-base-content/40">-</span>
                                    </td>
                                    <td>{{ subject.sort_order }}</td>
                                    <td>
                                        <div class="flex gap-1">
                                            <Link v-if="can('subjects.edit')" :href="route('subjects.edit', subject.id)" class="btn btn-ghost btn-xs">
                                                <PencilSquareIcon class="w-4 h-4" />
                                            </Link>
                                            <button v-if="can('subjects.delete')" @click="confirmDeleteSubject(subject)" class="btn btn-ghost btn-xs text-error">
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-4">
                            <Pagination :links="subjects.links" />
                        </div>
                    </div>
                    <EmptyState v-else title="No subjects found" description="Get started by adding your first subject." action-text="Add Subject" :action-href="route('subjects.create')" />
                </div>
            </div>
        </div>

        <ConfirmDialog
            :show="confirmDelete"
            title="Delete Subject"
            :message="`Are you sure you want to delete ${subjectToDelete?.name}? This action cannot be undone.`"
            type="danger"
            @confirm="deleteSubject"
            @cancel="confirmDelete = false"
        />
    </AppLayout>
</template>
