<script setup>
import FormInput from '@/Components/FormInput.vue'
import { useForm } from '@inertiajs/vue3'
import { CheckCircleIcon } from '@heroicons/vue/24/outline'

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

function updatePassword() {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) form.reset('password', 'password_confirmation')
            if (form.errors.current_password) form.reset('current_password')
        },
    })
}
</script>

<template>
    <p class="text-sm text-base-content/55 mb-5">
        Use a long, random password to keep your account secure.
    </p>

    <form @submit.prevent="updatePassword" class="space-y-4">
        <FormInput v-model="form.current_password" type="password" label="Current Password"
                   :error="form.errors.current_password" autocomplete="current-password" />
        <FormInput v-model="form.password" type="password" label="New Password"
                   :error="form.errors.password" autocomplete="new-password" />
        <FormInput v-model="form.password_confirmation" type="password" label="Confirm New Password"
                   :error="form.errors.password_confirmation" autocomplete="new-password" />

        <div class="flex items-center gap-3 pt-2 border-t border-base-200">
            <button type="submit" class="btn btn-primary btn-sm" :disabled="form.processing">
                <span v-if="form.processing" class="loading loading-spinner loading-xs"></span>
                Update Password
            </button>
            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0 -translate-x-2"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition-opacity duration-300"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <span v-if="form.recentlySuccessful" class="text-xs font-semibold text-success inline-flex items-center gap-1">
                    <CheckCircleIcon class="w-4 h-4" /> Password updated
                </span>
            </Transition>
        </div>
    </form>
</template>
