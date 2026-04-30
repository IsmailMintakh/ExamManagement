<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import FormSelect from '@/Components/FormSelect.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import { ref, computed } from 'vue'

const props = defineProps({
    schoolClass: Object,
    schools: Array,
    subjects: Array,
    prerequisites: Object,
})

const isEdit = !!props.schoolClass

const form = useForm({
    school_id: props.schoolClass?.school_id || '',
    name: props.schoolClass?.name || '',
    numeric_name: props.schoolClass?.numeric_name || '',
    sort_order: props.schoolClass?.sort_order || 0,
    subject_ids: props.schoolClass?.subjects?.map(s => s.id) || [],
})

const searchSubject = ref('')

const filteredSubjects = computed(() => {
    if (!searchSubject.value) return props.subjects || []
    const q = searchSubject.value.toLowerCase()
    return (props.subjects || []).filter(s => s.name.toLowerCase().includes(q) || s.code?.toLowerCase().includes(q))
})

function toggleSubject(id) {
    const idx = form.subject_ids.indexOf(id)
    if (idx > -1) {
        form.subject_ids.splice(idx, 1)
    } else {
        form.subject_ids.push(id)
    }
}

function submit() {
    if (isEdit) {
        form.put(route('classes.update', props.schoolClass.id))
    } else {
        form.post(route('classes.store'))
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Class' : 'Add Class'" />
    <AppLayout :breadcrumbs="[{ label: 'Classes', href: route('classes.index') }, { label: isEdit ? 'Edit' : 'Create' }]">
        <div class="max-w-3xl mx-auto">
            <h1 class="text-2xl font-bold mb-6">{{ isEdit ? 'Edit Class' : 'Add New Class' }}</h1>

            <div v-if="prerequisites?.subjectsMessage" class="alert alert-warning shadow-sm">
                <ExclamationTriangleIcon class="w-5 h-5 shrink-0" />
                <div>
                    <p class="text-sm">{{ prerequisites.subjectsMessage }}</p>
                    <Link :href="route('subjects.create')" class="btn btn-sm btn-outline mt-2">Add Subjects</Link>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body space-y-4">
                        <h3 class="text-lg font-semibold border-b border-base-200 pb-2">Class Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <FormSelect v-model="form.school_id" label="School" :error="form.errors.school_id" required
                                :options="schools?.map(s => ({ value: s.id, label: s.name })) || []" placeholder="Select School" />
                            <FormInput v-model="form.name" label="Class Name" :error="form.errors.name" required placeholder="e.g., Class 10" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <FormInput v-model.number="form.numeric_name" label="Numeric Name" type="number" :error="form.errors.numeric_name" placeholder="e.g., 10" />
                            <FormInput v-model.number="form.sort_order" label="Sort Order" type="number" :error="form.errors.sort_order" placeholder="0" />
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-md">
                    <div class="card-body space-y-4">
                        <h3 class="text-lg font-semibold border-b border-base-200 pb-2">Subjects for this Class</h3>
                        <div class="form-control">
                            <input v-model="searchSubject" type="text" placeholder="Search subjects..." class="input input-bordered input-sm w-full max-w-xs" />
                        </div>

                        <div v-if="filteredSubjects.length" class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-64 overflow-y-auto">
                            <label v-for="subject in filteredSubjects" :key="subject.id"
                                class="label cursor-pointer justify-start gap-3 p-3 border border-base-200 rounded-lg hover:bg-base-200 transition-colors">
                                <input type="checkbox" :checked="form.subject_ids.includes(subject.id)" @change="toggleSubject(subject.id)" class="checkbox checkbox-primary checkbox-sm" />
                                <div>
                                    <span class="label-text font-medium">{{ subject.name }}</span>
                                    <span class="text-xs text-base-content/60 block">{{ subject.code }} &bull; {{ subject.type }}</span>
                                </div>
                            </label>
                        </div>
                        <p v-else class="text-sm text-base-content/50">No subjects available.</p>

                        <p v-if="form.subject_ids.length" class="text-sm text-base-content/60">
                            {{ form.subject_ids.length }} subject(s) selected
                        </p>
                        <p v-if="form.errors.subject_ids" class="text-sm text-error">{{ form.errors.subject_ids }}</p>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <Link :href="route('classes.index')" class="btn btn-ghost">Cancel</Link>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">
                        <span v-if="form.processing" class="loading loading-spinner loading-sm"></span>
                        {{ isEdit ? 'Update Class' : 'Create Class' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
