<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchFilter from '@/Components/SearchFilter.vue'
import FormSelect from '@/Components/FormSelect.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { PlusIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { useDebouncedSearch } from '@/Composables/useDebouncedSearch'

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

const search = useDebouncedSearch({
    routeName: 'teacher-assignments.index',
    initial: props.filters?.search || '',
    only: ['assignments', 'filters'],
    delay: 0,
})
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
        <div class="space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight">Teacher Assignments</h1>
                    <p class="text-sm text-base-content/55 mt-0.5">
                        {{ assignments?.total || 0 }} assignment{{ (assignments?.total || 0) === 1 ? '' : 's' }}
                        <span v-if="search">matching "{{ search }}"</span>
                    </p>
                </div>
                <button @click="showForm = !showForm" class="btn btn-primary btn-sm gap-1.5">
                    <PlusIcon v-if="!showForm" class="w-4 h-4" />
                    <XMarkIcon v-else class="w-4 h-4" />
                    {{ showForm ? 'Cancel' : 'Add Assignment' }}
                </button>
            </div>

            <!-- Inline Add Form -->
            <section v-if="showForm" class="surface surface-accent-left">
                <header class="surface-header">
                    <h3>
                        <span class="w-7 h-7 rounded-lg bg-primary/15 text-primary flex items-center justify-center">
                            <PlusIcon class="w-4 h-4" />
                        </span>
                        New Teacher Assignment
                    </h3>
                </header>
                <div class="surface-body">
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
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="showForm = false" class="btn btn-ghost btn-sm">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm" :disabled="form.processing">
                                <span v-if="form.processing" class="loading loading-spinner loading-xs"></span>
                                Save Assignment
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="surface overflow-hidden">
                <header class="surface-header">
                    <div class="flex-1 max-w-md">
                        <SearchFilter v-model="search" placeholder="Search assignments…" />
                    </div>
                </header>

                <div class="table-sticky-wrap" style="--table-max-h: 65vh;" v-if="assignments?.data?.length">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="w-12">#</th>
                                <th>Teacher</th>
                                <th>Subject</th>
                                <th>Class</th>
                                <th>Section</th>
                                <th>Session</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(assignment, i) in assignments.data" :key="assignment.id">
                                <td class="text-xs font-mono text-base-content/55 tabular-nums">{{ assignments.from + i }}</td>
                                <td>
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-slate-500 to-slate-700 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">
                                            {{ assignment.user?.name?.charAt(0)?.toUpperCase() }}
                                        </div>
                                        <span class="font-bold text-sm truncate">{{ assignment.user?.name || '—' }}</span>
                                    </div>
                                </td>
                                <td class="text-[13px] text-base-content/75">{{ assignment.subject?.name || '—' }}</td>
                                <td class="text-[13px] text-base-content/75">{{ assignment.school_class?.name || '—' }}</td>
                                <td class="text-[13px] text-base-content/75">{{ assignment.section?.name || '—' }}</td>
                                <td class="text-[13px] text-base-content/75">{{ assignment.academic_session?.name || '—' }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <button @click="confirmDeleteAssignment(assignment)" class="btn btn-ghost btn-xs btn-square text-error" title="Remove">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState v-if="!assignments?.data?.length"
                    title="No teacher assignments found"
                    :description="search ? 'Try a different search term.' : 'Assign teachers to subjects and classes to get started.'" />

                <footer v-if="assignments?.data?.length && assignments.last_page > 1" class="surface-footer">
                    <span class="text-xs text-base-content/55 font-medium">
                        Showing <span class="text-base-content font-bold">{{ assignments.from }}–{{ assignments.to }}</span>
                        of <span class="text-base-content font-bold">{{ assignments.total }}</span>
                    </span>
                    <Pagination :links="assignments.links" />
                </footer>
            </section>
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
