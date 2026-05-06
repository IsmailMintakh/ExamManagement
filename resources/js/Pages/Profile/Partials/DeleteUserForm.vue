<script setup>
import FormInput from '@/Components/FormInput.vue'
import Modal from '@/Components/Modal.vue'
import { useForm } from '@inertiajs/vue3'
import { ref, nextTick } from 'vue'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

const confirming = ref(false)
const passwordInput = ref(null)

const form = useForm({ password: '' })

function confirmUserDeletion() {
    confirming.value = true
    nextTick(() => passwordInput.value?.focus?.())
}

function deleteUser() {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onFinish: () => form.reset(),
    })
}

function closeModal() {
    confirming.value = false
    form.clearErrors()
    form.reset()
}
</script>

<template>
    <p class="text-sm text-base-content/55 mb-5">
        Once your account is deleted, all of its data is permanently removed.
        Download anything you need to keep first — this can't be undone.
    </p>

    <button @click="confirmUserDeletion" type="button" class="btn btn-error btn-sm gap-1.5">
        <ExclamationTriangleIcon class="w-4 h-4" />
        Delete My Account
    </button>

    <Modal :show="confirming" max-width="md" @close="closeModal">
        <div class="p-6 space-y-4">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-error/15 text-error flex items-center justify-center flex-shrink-0">
                    <ExclamationTriangleIcon class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="text-lg font-extrabold tracking-tight">Delete account?</h3>
                    <p class="text-sm text-base-content/65 mt-1">
                        This will permanently delete your profile and all associated data.
                        Enter your password to confirm.
                    </p>
                </div>
            </div>

            <FormInput
                v-model="form.password"
                type="password"
                label="Password"
                :error="form.errors.password"
                placeholder="Enter your password"
                @keyup.enter="deleteUser"
            />

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="closeModal" class="btn btn-ghost btn-sm">Cancel</button>
                <button type="button" @click="deleteUser" :disabled="form.processing"
                        class="btn btn-error btn-sm gap-1.5">
                    <span v-if="form.processing" class="loading loading-spinner loading-xs"></span>
                    Delete Permanently
                </button>
            </div>
        </div>
    </Modal>
</template>
