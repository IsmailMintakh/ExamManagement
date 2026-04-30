<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import FormSelect from '@/Components/FormSelect.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'

const props = defineProps({ subject: Object })

const isEdit = !!props.subject

const form = useForm({
    name: props.subject?.name || '',
    code: props.subject?.code || '',
    type: props.subject?.type || 'core',
    is_main: props.subject?.is_main ?? false,
    sort_order: props.subject?.sort_order || 0,
})

function submit() {
    if (isEdit) {
        form.put(route('subjects.update', props.subject.id))
    } else {
        form.post(route('subjects.store'))
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Subject' : 'Add Subject'" />
    <AppLayout :breadcrumbs="[{ label: 'Subjects', href: route('subjects.index') }, { label: isEdit ? 'Edit' : 'Create' }]">
        <div class="max-w-3xl mx-auto">
            <h1 class="text-2xl font-bold mb-6">{{ isEdit ? 'Edit Subject' : 'Add New Subject' }}</h1>

            <form @submit.prevent="submit" class="card bg-base-100 shadow-md">
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput v-model="form.name" label="Subject Name" :error="form.errors.name" required placeholder="e.g., Mathematics" />
                        <FormInput v-model="form.code" label="Subject Code" :error="form.errors.code" required placeholder="e.g., MATH" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormSelect v-model="form.type" label="Type" :error="form.errors.type" required :options="[
                            { value: 'core', label: 'Core' },
                            { value: 'elective', label: 'Elective' },
                        ]" />
                        <FormInput v-model.number="form.sort_order" label="Sort Order" type="number" :error="form.errors.sort_order" placeholder="0" />
                    </div>

                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" v-model="form.is_main" class="checkbox checkbox-primary" />
                            <span class="label-text">Main Subject</span>
                        </label>
                        <p class="text-xs text-base-content/50 ml-10">Main subjects may have special passing rules applied during result calculation.</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-base-200">
                        <Link :href="route('subjects.index')" class="btn btn-ghost">Cancel</Link>
                        <button type="submit" class="btn btn-primary" :disabled="form.processing">
                            <span v-if="form.processing" class="loading loading-spinner loading-sm"></span>
                            {{ isEdit ? 'Update Subject' : 'Create Subject' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
