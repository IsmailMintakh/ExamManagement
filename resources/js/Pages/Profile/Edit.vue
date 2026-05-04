<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import DeleteUserForm from './Partials/DeleteUserForm.vue'
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue'
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue'
import PushNotificationManager from '@/Components/PushNotificationManager.vue'
import { Head } from '@inertiajs/vue3'
import { UserCircleIcon, KeyIcon, BellAlertIcon, TrashIcon } from '@heroicons/vue/24/outline'

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
})
</script>

<template>
    <Head title="My Profile" />
    <AppLayout :breadcrumbs="[{ label: 'My Profile' }]">
        <div class="max-w-3xl mx-auto space-y-5">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight">My Profile</h1>
                <p class="text-sm text-base-content/55 mt-1">Manage your account, security, and notification preferences.</p>
            </div>

            <!-- Profile Information -->
            <section class="surface">
                <header class="surface-header">
                    <h3>
                        <span class="w-7 h-7 rounded-lg bg-teal-500/15 text-teal-600 dark:text-teal-400 flex items-center justify-center">
                            <UserCircleIcon class="w-4 h-4" />
                        </span>
                        Profile Information
                    </h3>
                </header>
                <div class="surface-body">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>
            </section>

            <!-- Password -->
            <section class="surface">
                <header class="surface-header">
                    <h3>
                        <span class="w-7 h-7 rounded-lg bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                            <KeyIcon class="w-4 h-4" />
                        </span>
                        Password
                    </h3>
                </header>
                <div class="surface-body">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>
            </section>

            <!-- Push Notifications (already a card) -->
            <PushNotificationManager />

            <!-- Danger Zone -->
            <section class="surface surface-accent-left accent-rose">
                <header class="surface-header">
                    <h3>
                        <span class="w-7 h-7 rounded-lg bg-rose-500/15 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                            <TrashIcon class="w-4 h-4" />
                        </span>
                        Delete Account
                    </h3>
                </header>
                <div class="surface-body">
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </section>
        </div>
    </AppLayout>
</template>
