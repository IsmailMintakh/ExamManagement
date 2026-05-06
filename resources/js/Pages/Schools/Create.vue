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
    principal_name: props.school?.principal_name || '',
    exam_officer_name: props.school?.exam_officer_name || '',
    logo: null,
    school_stamp: null,
    principal_signature: null,
    exam_officer_signature: null,
    is_active: props.school?.is_active ?? true,
})

// Build absolute URLs for already-saved images so the FileUpload preview can
// render them on the edit screen. The /storage prefix maps to the public
// disk's symlink (storage:link).
function existingUrl(path) {
    return path ? `/storage/${path}` : ''
}

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

                    <!-- ─── Authenticity assets — used on date sheets, admit cards, certificates ─── -->
                    <div class="pt-4 border-t border-base-200">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-base-content/55 mb-3">
                            Logos &amp; Authenticity
                            <span class="text-base-content/40 normal-case font-medium">· optional, used on PDFs</span>
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <FileUpload v-model="form.logo" label="School Logo"
                                accept="image/*" :preview="true" :error="form.errors.logo"
                                :existing-url="existingUrl(props.school?.logo)"
                                help-text="Square is best. Shown on PDFs &amp; ID cards." />
                            <FileUpload v-model="form.school_stamp" label="School Stamp"
                                accept="image/png,image/webp,image/svg+xml" :preview="true"
                                :error="form.errors.school_stamp"
                                :existing-url="existingUrl(props.school?.school_stamp)"
                                help-text="Round/oval stamp. Transparent PNG looks best." />
                            <FileUpload v-model="form.principal_signature" label="Principal Signature"
                                accept="image/png,image/webp,image/svg+xml" :preview="true"
                                :error="form.errors.principal_signature"
                                :existing-url="existingUrl(props.school?.principal_signature)"
                                help-text="Black ink on white. Transparent PNG ideal." />
                            <FileUpload v-model="form.exam_officer_signature" label="Exam Officer Signature"
                                accept="image/png,image/webp,image/svg+xml" :preview="true"
                                :error="form.errors.exam_officer_signature"
                                :existing-url="existingUrl(props.school?.exam_officer_signature)"
                                help-text="Used on date sheets &amp; admit cards." />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <FormInput v-model="form.principal_name" label="Principal Name"
                                :error="form.errors.principal_name"
                                placeholder="e.g. Mr. Ali Raza"
                                help-text="Shown beneath the Principal signature on PDFs." />
                            <FormInput v-model="form.exam_officer_name" label="Exam Officer Name"
                                :error="form.errors.exam_officer_name"
                                placeholder="e.g. Mr. Ahmad Khan"
                                help-text="Shown beneath the Exam Officer signature on PDFs." />
                        </div>
                    </div>

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
