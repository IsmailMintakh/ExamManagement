<script setup>
import FormInput from '@/Components/FormInput.vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { CheckCircleIcon } from '@heroicons/vue/24/outline'

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
})

const user = usePage().props.auth.user

const form = useForm({
    name: user.name,
    email: user.email,
})
</script>

<template>
    <p class="text-sm text-base-content/55 mb-5">
        Update the name and email tied to your account.
    </p>

    <form @submit.prevent="form.patch(route('profile.update'))" class="space-y-4">
        <FormInput v-model="form.name" label="Full Name" :error="form.errors.name" required />
        <FormInput v-model="form.email" type="email" label="Email Address" :error="form.errors.email" required />

        <!-- Email verification banner -->
        <div v-if="mustVerifyEmail && user.email_verified_at === null"
             class="rounded-xl bg-warning/10 border border-warning/30 px-4 py-3 text-sm">
            <p class="text-warning-content/80">
                Your email address is unverified.
                <Link
                    :href="route('verification.send')"
                    method="post"
                    as="button"
                    class="font-semibold underline hover:no-underline ml-1"
                >
                    Re-send verification email
                </Link>
            </p>
            <div v-show="status === 'verification-link-sent'"
                 class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-success">
                <CheckCircleIcon class="w-4 h-4" />
                A new verification link has been sent.
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2 border-t border-base-200">
            <button type="submit" class="btn btn-primary btn-sm" :disabled="form.processing">
                <span v-if="form.processing" class="loading loading-spinner loading-xs"></span>
                Save Changes
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
                    <CheckCircleIcon class="w-4 h-4" /> Saved
                </span>
            </Transition>
        </div>
    </form>
</template>
