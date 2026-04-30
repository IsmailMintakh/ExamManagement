<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchFilter from '@/Components/SearchFilter.vue'
import FormSelect from '@/Components/FormSelect.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { PlusIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    assignments: Object,
    filters: Object,
    teachers: Array,
    subjects: Array,
    classes: Array,
    sections: Array,
    sessions: Array,
    currentSession: Object,
})

const search = ref(props.filters?.search || '')
const confirmDelete = ref(false)
const assignmentToDelete = ref(null)
const showForm = ref(false)

const form = useForm({
    user_id: '',
    subject_id: '',
    school_class_id: '',
    section_id: '',
    academic_session_id: props.currentSession?.id || '',
})

function handleSearch(val) {
    router.get(route('teacher-assignments.index'), { search: val }, { preserveState: true, replace: true })
}

function confirmDeleteAssignment(assignment) {
    assignmentToDelete.value = assignment
    confirmDelete.value = true
}

function deleteAssignment() {
    if (assignmentToDelete.value) {
        router.delete(route('teacher-assignments.destroy', assignmentToDelete.value.id), {
            onSuccess: () => { confirmDelete.value = false; assignmentToDelete.value = null }
        })
    }
}

function submitForm() {
    form.post(route('teacher-assignments.store'), {
        onSuccess: () => {
            form.reset()
            showForm.value = false
        }
    })
}
</script>

<template>
    <Head title="Teacher Assignments" />
    <AppLayout :breadcrumbs="[{ label: 'Teacher Assignments' }]">
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h1 class="text-2xl font-bold">Teacher Assignments</h1>
                <button @click="showForm = !showForm" class="btn btn-primary gap-2">
                    <PlusIcon v-if="!showForm" class="w-5 h-5" />
                    <XMarkIcon v-else class="w-5 h-5" />
                    {{ showForm ? 'Cancel' : 'Add Assignment' }}
                </button>
            </div>

            <!-- Inline Add Form -->
            <div v-if="showForm" class="card bg-base-100 shadow-md border-2 border-primary/20">
                <div class="card-body">
                    <h3 class="text-lg font-semibold mb-4">New Teacher Assignment</h3>
                    <form @submit.prevent="submitForm" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <FormSelect v-model="form.user_id" label="Teacher" :error="form.errors.user_id" required
                                :options="teachers?.map(t => ({ value: t.id, label: t.name })) || []" placeholder="Select Teacher" />
                            <FormSelect v-model="form.subject_id" label="Subject" :error="form.errors.subject_id" required
                                :options="subjects?.map(s => ({ value: s.id, label: s.name })) || []" placeholder="Select Subject" />
                            <FormSelect v-model="form.school_class_id" label="Class" :error="form.errors.school_class_id" required
                                :options="classes?.map(c => ({ value: c.id, label: c.name })) || []" placeholder="Select Class" />
                            <FormSelect v-model="form.section_id" label="Section" :error="form.errors.section_id" required
                                :options="sections?.filter(s => !form.school_class_id || s.school_class_id == form.school_class_id).map(s => ({ value: s.id, label: s.name })) || []" placeholder="Select Section" />
                            <FormSelect v-model="form.academic_session_id" label="Session" :error="form.errors.academic_session_id" required
                                :options="sessions?.map(s => ({ value: s.id, label: s.name })) || []" placeholder="Select Session" />
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="showForm = false" class="btn btn-ghost">Cancel</button>
                            <button type="submit" class="btn btn-primary" :disabled="form.processing">
                                <span v-if="form.processing" class="loading loading-spinner loading-sm"></span>
                                Save Assignment
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <SearchFilter v-model="search" @update:model-value="handleSearch" />

                    <div class="overflow-x-auto mt-4" v-if="assignments?.data?.length">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Teacher</th>
                                    <th>Subject</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Session</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(assignment, i) in assignments.data" :key="assignment.id">
                                    <td>{{ assignments.from + i }}</td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="avatar placeholder">
                                                <div class="bg-neutral text-neutral-content rounded-full w-8 h-8">
                                                    <span class="text-xs">{{ assignment.user?.name?.charAt(0) }}</span>
                                                </div>
                                            </div>
                                            <span class="font-medium text-sm">{{ assignment.user?.name || '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="text-sm">{{ assignment.subject?.name || '-' }}</td>
                                    <td class="text-sm">{{ assignment.school_class?.name || '-' }}</td>
                                    <td class="text-sm">{{ assignment.section?.name || '-' }}</td>
                                    <td class="text-sm">{{ assignment.academic_session?.name || '-' }}</td>
                                    <td>
                                        <button @click="confirmDeleteAssignment(assignment)" class="btn btn-ghost btn-xs text-error">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-4">
                            <Pagination :links="assignments.links" />
                        </div>
                    </div>
                    <EmptyState v-else title="No teacher assignments found" description="Assign teachers to subjects and classes to get started." />
                </div>
            </div>
        </div>

        <ConfirmDialog
            :show="confirmDelete"
            title="Delete Assignment"
            message="Are you sure you want to remove this teacher assignment?"
            type="danger"
            @confirm="deleteAssignment"
            @cancel="confirmDelete = false"
        />
    </AppLayout>
</template>
