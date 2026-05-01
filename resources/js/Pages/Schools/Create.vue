<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import FileUpload from '@/Components/FileUpload.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'

const props = defineProps({ school: Object })

const isEdit = !!props.school

const form = useForm({
    // _method must be a form FIELD (not an Inertia option) for PUT spoofing
    // to reach Laravel's FormMethodSpoofingMiddleware. Without this, the
    // POST hits a route that only accepts PUT/PATCH → 405.
    _method: isEdit ? 'put' : 'post',
    name: props.school?.name || '',
    code: props.school?.code || '',
    address: props.school?.address || '',
    phone: props.school?.phone || '',
    email: props.school?.email || '',
    website: props.school?.website || '',
    logo: null,
    is_active: props.school?.is_active ?? true,
})

function submit() {
    const url = isEdit ? route('schools.update', props.school.id) : route('schools.store')
    form.post(url, { forceFormData: true, preserveScroll: true })
}
</script>

<template>
    <Head :title="isEdit ? 'Edit School' : 'Add School'" />
    <AppLayout :breadcrumbs="[{ label: 'Schools', href: route('schools.index') }, { label: isEdit ? 'Edit' : 'Create' }]">
        <div class="max-w-3xl mx-auto">
            <h1 class="text-2xl font-bold mb-6">{{ isEdit ? 'Edit School' : 'Add New School' }}</h1>

            <form @submit.prevent="submit" class="card bg-base-100 shadow-md">
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput v-model="form.name" label="School Name" :error="form.errors.name" required placeholder="Enter school name" />
                        <FormInput v-model="form.code" label="School Code" :error="form.errors.code" required placeholder="e.g., GHS-01" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput v-model="form.email" label="Email" type="email" :error="form.errors.email" placeholder="school@example.com" />
                        <FormInput v-model="form.phone" label="Phone" :error="form.errors.phone" placeholder="Phone number" />
                    </div>

                    <FormInput v-model="form.website" label="Website" :error="form.errors.website" placeholder="https://..." />
                    <FormTextarea v-model="form.address" label="Address" :error="form.errors.address" :rows="3" placeholder="Full address" />
                    <FileUpload v-model="form.logo" label="School Logo" accept="image/*" :preview="true" :error="form.errors.logo" />

                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" v-model="form.is_active" class="toggle toggle-primary" />
                            <span class="label-text">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-base-200">
                        <Link :href="route('schools.index')" class="btn btn-ghost">Cancel</Link>
                        <button type="submit" class="btn btn-primary" :disabled="form.processing">
                            <span v-if="form.processing" class="loading loading-spinner loading-sm"></span>
                            {{ isEdit ? 'Update School' : 'Create School' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
