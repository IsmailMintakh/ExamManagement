<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import FormSelect from '@/Components/FormSelect.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    section: Object,
    classes: Array,
    teachers: Array,
    prerequisites: Object,
})

const isEdit = !!props.section

const form = useForm({
    school_class_id: props.section?.school_class_id || '',
    name: props.section?.name || '',
    class_teacher_id: props.section?.class_teacher_id || '',
    capacity: props.section?.capacity || '',
})

function submit() {
    if (isEdit) {
        form.put(route('sections.update', props.section.id))
    } else {
        form.post(route('sections.store'))
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Section' : 'Add Section'" />
    <AppLayout :breadcrumbs="[{ label: 'Sections', href: route('sections.index') }, { label: isEdit ? 'Edit' : 'Create' }]">
        <div class="max-w-3xl mx-auto space-y-4">
            <h1 class="text-2xl font-bold">{{ isEdit ? 'Edit Section' : 'Add New Section' }}</h1>

            <!-- Prerequisite Warning -->
            <div v-if="prerequisites?.teachersMessage" class="alert alert-warning shadow-sm">
                <ExclamationTriangleIcon class="w-5 h-5 shrink-0" />
                <div>
                    <p class="text-sm">{{ prerequisites.teachersMessage }}</p>
                    <Link :href="route('users.create')" class="btn btn-sm btn-outline mt-2">Add a Teacher</Link>
                </div>
            </div>

            <form @submit.prevent="submit" class="card bg-base-100 shadow-md">
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormSelect v-model="form.school_class_id" label="Class" :error="form.errors.school_class_id" required
                            :options="classes?.map(c => ({ value: c.id, label: c.name })) || []" placeholder="Select Class" />
                        <FormInput v-model="form.name" label="Section Name" :error="form.errors.name" required placeholder="e.g., A" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormSelect v-model="form.class_teacher_id" label="Class Teacher" :error="form.errors.class_teacher_id"
                            :options="teachers?.map(t => ({ value: t.id, label: t.name })) || []" placeholder="Select Teacher (optional)" />
                        <FormInput v-model.number="form.capacity" label="Capacity" type="number" :error="form.errors.capacity" placeholder="e.g., 40" />
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-base-200">
                        <Link :href="route('sections.index')" class="btn btn-ghost">Cancel</Link>
                        <button type="submit" class="btn btn-primary" :disabled="form.processing">
                            <span v-if="form.processing" class="loading loading-spinner loading-sm"></span>
                            {{ isEdit ? 'Update Section' : 'Create Section' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
