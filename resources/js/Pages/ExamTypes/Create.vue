<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'

const props = defineProps({ examType: Object })

const isEdit = !!props.examType

const form = useForm({
    name: props.examType?.name || '',
    description: props.examType?.description || '',
    sort_order: props.examType?.sort_order || 0,
    is_active: props.examType?.is_active ?? true,
})

function submit() {
    if (isEdit) {
        form.put(route('exam-types.update', props.examType.id))
    } else {
        form.post(route('exam-types.store'))
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Exam Type' : 'Add Exam Type'" />
    <AppLayout :breadcrumbs="[{ label: 'Exam Types', href: route('exam-types.index') }, { label: isEdit ? 'Edit' : 'Create' }]">
        <div class="max-w-3xl mx-auto">
            <h1 class="text-2xl font-bold mb-6">{{ isEdit ? 'Edit Exam Type' : 'Add New Exam Type' }}</h1>

            <form @submit.prevent="submit" class="card bg-base-100 shadow-md">
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput v-model="form.name" label="Name" :error="form.errors.name" required placeholder="e.g., Mid Term" />
                        <FormInput v-model.number="form.sort_order" label="Sort Order" type="number" :error="form.errors.sort_order" placeholder="0" />
                    </div>

                    <FormTextarea v-model="form.description" label="Description" :error="form.errors.description" :rows="3" placeholder="Brief description of this exam type" />

                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" v-model="form.is_active" class="toggle toggle-primary" />
                            <span class="label-text">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-base-200">
                        <Link :href="route('exam-types.index')" class="btn btn-ghost">Cancel</Link>
                        <button type="submit" class="btn btn-primary" :disabled="form.processing">
                            <span v-if="form.processing" class="loading loading-spinner loading-sm"></span>
                            {{ isEdit ? 'Update Exam Type' : 'Create Exam Type' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
