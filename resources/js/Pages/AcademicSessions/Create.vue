<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'

const props = defineProps({ session: Object })

const isEdit = !!props.session

const form = useForm({
    name: props.session?.name || '',
    start_date: props.session?.start_date || '',
    end_date: props.session?.end_date || '',
    is_current: props.session?.is_current ?? false,
    is_active: props.session?.is_active ?? true,
})

function submit() {
    if (isEdit) {
        form.put(route('academic-sessions.update', props.session.id))
    } else {
        form.post(route('academic-sessions.store'))
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Academic Session' : 'Add Academic Session'" />
    <AppLayout :breadcrumbs="[{ label: 'Academic Sessions', href: route('academic-sessions.index') }, { label: isEdit ? 'Edit' : 'Create' }]">
        <div class="max-w-3xl mx-auto">
            <h1 class="text-2xl font-bold mb-6">{{ isEdit ? 'Edit Academic Session' : 'Add New Academic Session' }}</h1>

            <form @submit.prevent="submit" class="card bg-base-100 shadow-md">
                <div class="card-body space-y-4">
                    <FormInput v-model="form.name" label="Session Name" :error="form.errors.name" required placeholder="e.g., 2025-2026" />

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput v-model="form.start_date" label="Start Date" type="date" :error="form.errors.start_date" required />
                        <FormInput v-model="form.end_date" label="End Date" type="date" :error="form.errors.end_date" required
                            :min="form.start_date || null" />
                    </div>

                    <div class="flex flex-col gap-3">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" v-model="form.is_current" class="checkbox checkbox-primary" />
                            <div>
                                <span class="label-text font-medium">Set as Current Session</span>
                                <p class="text-xs text-base-content/50">This will make this session the active working session across the system.</p>
                            </div>
                        </label>
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" v-model="form.is_active" class="toggle toggle-primary" />
                            <span class="label-text">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-base-200">
                        <Link :href="route('academic-sessions.index')" class="btn btn-ghost">Cancel</Link>
                        <button type="submit" class="btn btn-primary" :disabled="form.processing">
                            <span v-if="form.processing" class="loading loading-spinner loading-sm"></span>
                            {{ isEdit ? 'Update Session' : 'Create Session' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
