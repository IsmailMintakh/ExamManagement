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
    // remove_* flags — set to true when the user clicks the trash button
    // on an existing image. On submit the controller clears the file
    // AND nulls the DB column. Without these flags, an empty file input
    // would just leave the existing image intact (per the SchoolController
    // update fix). This is the explicit "remove" path.
    remove_logo: false,
    remove_school_stamp: false,
    remove_principal_signature: false,
    remove_exam_officer_signature: false,
    is_active: props.school?.is_active ?? true,
    // Principal login fields — only sent on create. On edit, the Principal
    // user already exists; password resets happen via the User edit page.
    principal_email: '',
    principal_password: '',
    principal_password_confirmation: '',
})

// Generate a memorable random password (10 chars, mixed case + digits).
// Saves the user typing one out and ensures it passes the min:8 rule.
function generatePassword() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'
    let pw = ''
    for (let i = 0; i < 10; i++) pw += chars[Math.floor(Math.random() * chars.length)]
    form.principal_password = pw
    form.principal_password_confirmation = pw
}

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
                                :existing-url="form.remove_logo ? '' : existingUrl(props.school?.logo)"
                                :allow-remove-existing="isEdit && !!props.school?.logo && !form.remove_logo"
                                @remove-existing="form.remove_logo = true"
                                help-text="Square is best. Shown on PDFs &amp; ID cards." />
                            <FileUpload v-model="form.school_stamp" label="School Stamp"
                                accept="image/png,image/webp,image/svg+xml" :preview="true"
                                :error="form.errors.school_stamp"
                                :existing-url="form.remove_school_stamp ? '' : existingUrl(props.school?.school_stamp)"
                                :allow-remove-existing="isEdit && !!props.school?.school_stamp && !form.remove_school_stamp"
                                @remove-existing="form.remove_school_stamp = true"
                                help-text="Round/oval stamp. Transparent PNG looks best." />
                            <FileUpload v-model="form.principal_signature" label="Principal Signature"
                                accept="image/png,image/webp,image/svg+xml" :preview="true"
                                :error="form.errors.principal_signature"
                                :existing-url="form.remove_principal_signature ? '' : existingUrl(props.school?.principal_signature)"
                                :allow-remove-existing="isEdit && !!props.school?.principal_signature && !form.remove_principal_signature"
                                @remove-existing="form.remove_principal_signature = true"
                                help-text="Black ink on white. Transparent PNG ideal." />
                            <FileUpload v-model="form.exam_officer_signature" label="Exam Officer Signature"
                                accept="image/png,image/webp,image/svg+xml" :preview="true"
                                :error="form.errors.exam_officer_signature"
                                :existing-url="form.remove_exam_officer_signature ? '' : existingUrl(props.school?.exam_officer_signature)"
                                :allow-remove-existing="isEdit && !!props.school?.exam_officer_signature && !form.remove_exam_officer_signature"
                                @remove-existing="form.remove_exam_officer_signature = true"
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

                    <!-- Principal Login Account — only on create. Optional;
                         leaving blank means the school is created without a
                         login, which an admin can add later via Users → Add. -->
                    <div v-if="!isEdit" class="pt-4 border-t border-base-200">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-base-content/55 mb-1">
                            Principal Login Account
                            <span class="text-base-content/40 normal-case font-medium">· optional, recommended</span>
                        </p>
                        <p class="text-xs text-base-content/55 mb-3">
                            Creates a Principal (school-admin) user so this school can sign in.
                            Skip this and add a user later via <strong>Users → Add User</strong>.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <FormInput v-model="form.principal_email" label="Login Email" type="email"
                                :error="form.errors.principal_email"
                                placeholder="principal@school.edu.pk"
                                help-text="The email the Principal will use to sign in." />
                            <div></div>
                            <FormInput v-model="form.principal_password" label="Password" type="password"
                                :error="form.errors.principal_password"
                                placeholder="Minimum 8 characters"
                                help-text="Share this with the Principal securely." />
                            <FormInput v-model="form.principal_password_confirmation" label="Confirm Password" type="password"
                                placeholder="Type it again" />
                        </div>
                        <button type="button" @click="generatePassword"
                            class="btn btn-ghost btn-xs gap-1 mt-2">
                            ↻ Generate strong password
                        </button>
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
