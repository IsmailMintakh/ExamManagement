<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { CloudArrowUpIcon, TrashIcon, CheckIcon } from '@heroicons/vue/24/outline'
import { confirmDelete } from '@/lib/swal'

const user = computed(() => usePage().props.auth?.user)
const hasSignature = computed(() => !!user.value?.signature_url)

const fileInput = ref(null)
const previewUrl = ref(null)

const form = useForm({ signature: null })

function pickFile() { fileInput.value?.click() }

function onFile(e) {
    const file = e.target.files?.[0]
    if (!file) return
    form.signature = file
    previewUrl.value = URL.createObjectURL(file)
}

function upload() {
    if (!form.signature) return
    form.post(route('profile.signature.upload'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset()
            previewUrl.value = null
            if (fileInput.value) fileInput.value.value = ''
        },
    })
}

async function remove() {
    const ok = await confirmDelete({
        title: 'Remove your signature?',
        text: 'Future reports will print a blank signature line again until you upload a new one.',
        confirmText: 'Yes, remove',
    })
    if (!ok) return
    router.delete(route('profile.signature.delete'), { preserveScroll: true })
}
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-base-content/70">
            Upload an image of your signature (PNG preferred — transparent background works best).
            Once uploaded it will automatically appear on attendance sheets, award lists, mark sheets and any other
            report that asks for your signature — no more hand-signing every page.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
            <!-- Current / preview -->
            <div class="rounded-xl border border-base-300 bg-base-200/30 p-4 flex flex-col items-center justify-center min-h-[140px]">
                <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55 mb-2">
                    {{ previewUrl ? 'Preview (not saved yet)' : (hasSignature ? 'Current signature' : 'No signature uploaded') }}
                </p>
                <img v-if="previewUrl" :src="previewUrl" alt="Signature preview"
                    class="max-h-20 max-w-full object-contain bg-white p-2 rounded border border-base-300" />
                <img v-else-if="hasSignature" :src="user.signature_url" alt="Your signature"
                    class="max-h-20 max-w-full object-contain bg-white p-2 rounded border border-base-300" />
                <p v-else class="text-xs text-base-content/45 italic">
                    Reports will print a blank line until you upload one.
                </p>
            </div>

            <!-- Actions -->
            <div class="space-y-3">
                <input ref="fileInput" type="file" accept="image/png,image/jpeg" class="hidden" @change="onFile" />
                <button type="button" @click="pickFile"
                    class="btn btn-outline btn-sm w-full gap-2">
                    <CloudArrowUpIcon class="w-4 h-4" />
                    {{ form.signature ? form.signature.name : 'Choose image…' }}
                </button>
                <button v-if="form.signature" type="button" @click="upload"
                    :disabled="form.processing"
                    class="btn btn-primary btn-sm w-full gap-2">
                    <CheckIcon class="w-4 h-4" />
                    {{ form.processing ? 'Uploading…' : 'Save signature' }}
                </button>
                <button v-if="hasSignature && !form.signature" type="button" @click="remove"
                    class="btn btn-ghost btn-sm w-full gap-2 text-rose-600">
                    <TrashIcon class="w-4 h-4" /> Remove current signature
                </button>
                <p v-if="form.errors.signature" class="text-xs text-error">{{ form.errors.signature }}</p>
                <p class="text-[11px] text-base-content/45">
                    PNG or JPG. Max 1 MB. Recommended: dark signature on a transparent / white background, around 600×200 px.
                </p>
            </div>
        </div>
    </div>
</template>
