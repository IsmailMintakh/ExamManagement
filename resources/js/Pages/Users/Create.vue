<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import FormSelect from '@/Components/FormSelect.vue'
import FileUpload from '@/Components/FileUpload.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'

const props = defineProps({
    user: Object,
    schools: Array,
    roles: Array,
})

const isEdit = !!props.user

const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    password: '',
    password_confirmation: '',
    phone: props.user?.phone || '',
    school_id: props.user?.school_id || '',
    role: props.user?.roles?.[0]?.name || props.user?.role || '',
    is_active: props.user?.is_active ?? true,
    avatar: null,
})

function submit() {
    if (isEdit) {
        form.post(route('users.update', props.user.id), { _method: 'PUT', forceFormData: true })
    } else {
        form.post(route('users.store'), { forceFormData: true })
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit User' : 'Add User'" />
    <AppLayout :breadcrumbs="[{ label: 'Users', href: route('users.index') }, { label: isEdit ? 'Edit' : 'Create' }]">
        <div class="max-w-3xl mx-auto">
            <h1 class="text-2xl font-bold mb-6">{{ isEdit ? 'Edit User' : 'Add New User' }}</h1>

            <form @submit.prevent="submit" class="card bg-base-100 shadow-md">
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput v-model="form.name" label="Full Name" :error="form.errors.name" required placeholder="Enter full name" />
                        <FormInput v-model="form.email" label="Email" type="email" :error="form.errors.email" required placeholder="user@example.com" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput v-model="form.password" label="Password" type="password" :error="form.errors.password"
                            :required="!isEdit" :placeholder="isEdit ? 'Leave blank to keep current' : 'Enter password'" />
                        <FormInput v-model="form.password_confirmation" label="Confirm Password" type="password" :error="form.errors.password_confirmation"
                            :required="!isEdit" :placeholder="isEdit ? 'Leave blank to keep current' : 'Confirm password'" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput v-model="form.phone" label="Phone" :error="form.errors.phone" placeholder="Phone number" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormSelect v-model="form.school_id" label="School" :error="form.errors.school_id"
                            :options="schools?.map(s => ({ value: s.id, label: s.name })) || []" placeholder="Select School (optional)" />
                        <FormSelect v-model="form.role" label="Role" :error="form.errors.role" required
                            :options="roles?.map(r => {
                                const val = r.name || r
                                const labels = { 'super-admin': 'Super Admin (DDO)', 'school-admin': 'School Admin (Principal)', 'class-teacher': 'Class Teacher', 'subject-teacher': 'Subject Teacher' }
                                return { value: val, label: labels[val] || val }
                            }) || []" placeholder="Select Role" />
                    </div>

                    <FileUpload v-model="form.avatar" label="Avatar" accept="image/*" :preview="true" :error="form.errors.avatar" />

                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" v-model="form.is_active" class="toggle toggle-primary" />
                            <span class="label-text">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-base-200">
                        <Link :href="route('users.index')" class="btn btn-ghost">Cancel</Link>
                        <button type="submit" class="btn btn-primary" :disabled="form.processing">
                            <span v-if="form.processing" class="loading loading-spinner loading-sm"></span>
                            {{ isEdit ? 'Update User' : 'Create User' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
