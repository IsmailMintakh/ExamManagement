<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import FileUpload from '@/Components/FileUpload.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps({
    school: Object,
    schools: Array,
    filters: Object,
})

const activeTab = ref('school')
const selectedSchoolId = ref(props.filters?.school_id || '')

const form = useForm({
    school_id: props.school?.id || '',
    name: props.school?.name || '',
    address: props.school?.address || '',
    phone: props.school?.phone || '',
    email: props.school?.email || '',
    website: props.school?.website || '',
    logo: null,
    principal_signature: null,
    school_stamp: null,
})

// When super admin switches school, reload the page with the selected school
function onSchoolChange() {
    router.get(route('settings.index'), {
        school_id: selectedSchoolId.value,
    }, { preserveState: false })
}

// When school prop changes (after reload), update form
watch(() => props.school, (newSchool) => {
    if (newSchool) {
        form.school_id = newSchool.id
        form.name = newSchool.name || ''
        form.address = newSchool.address || ''
        form.phone = newSchool.phone || ''
        form.email = newSchool.email || ''
        form.website = newSchool.website || ''
        form.logo = null
        form.principal_signature = null
        form.school_stamp = null
    }
})

function saveSettings() {
    form.post(route('settings.update'), { forceFormData: true, preserveScroll: true })
}

const tabs = [
    { key: 'school', label: 'School Info' },
    { key: 'signatures', label: 'Signatures & Stamp' },
]
</script>

<template>
    <Head title="Settings" />
    <AppLayout :breadcrumbs="[{ label: 'Settings' }]">
        <div class="max-w-4xl mx-auto space-y-6">
            <h1 class="text-2xl font-bold">Settings</h1>

            <!-- Super admin school selector -->
            <div v-if="schools?.length" class="card bg-base-100 shadow-md">
                <div class="card-body p-4">
                    <label class="label"><span class="label-text font-medium">Select School</span></label>
                    <select v-model="selectedSchoolId" class="select select-bordered w-full max-w-xs" @change="onSchoolChange">
                        <option value="">-- Select a school --</option>
                        <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
            </div>

            <div v-if="school">
                <div role="tablist" class="tabs tabs-bordered mb-6">
                    <button v-for="tab in tabs" :key="tab.key" type="button" role="tab"
                        :class="['tab', activeTab === tab.key ? 'tab-active' : '']"
                        @click="activeTab = tab.key">
                        {{ tab.label }}
                    </button>
                </div>

                <form @submit.prevent="saveSettings">
                    <!-- School Info -->
                    <div v-show="activeTab === 'school'" class="card bg-base-100 shadow-md">
                        <div class="card-body space-y-4">
                            <h3 class="text-lg font-semibold border-b border-base-200 pb-2">School Information</h3>

                            <FileUpload v-model="form.logo" label="School Logo" accept="image/*" :preview="true" :error="form.errors.logo" />
                            <div v-if="school?.logo" class="flex items-center gap-2">
                                <span class="text-xs text-base-content/50">Current:</span>
                                <img :src="'/storage/' + school.logo" class="max-h-12 rounded" alt="Current logo" />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <FormInput v-model="form.name" label="School Name" :error="form.errors.name" required />
                                <FormInput v-model="form.email" label="Email" type="email" :error="form.errors.email" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <FormInput v-model="form.phone" label="Phone" :error="form.errors.phone" />
                                <FormInput v-model="form.website" label="Website" :error="form.errors.website" />
                            </div>
                            <FormTextarea v-model="form.address" label="Address" :error="form.errors.address" :rows="3" />

                            <div class="flex justify-end pt-4 border-t border-base-200">
                                <button type="submit" class="btn btn-primary" :disabled="form.processing">
                                    <span v-if="form.processing" class="loading loading-spinner loading-sm"></span>
                                    Save School Info
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Signatures & Stamp -->
                    <div v-show="activeTab === 'signatures'" class="card bg-base-100 shadow-md">
                        <div class="card-body space-y-4">
                            <h3 class="text-lg font-semibold border-b border-base-200 pb-2">Signatures & Stamp</h3>
                            <p class="text-sm text-base-content/60">Upload signature and stamp images that will appear on report cards and official documents.</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <FileUpload v-model="form.principal_signature" label="Principal Signature" accept="image/*" :preview="true" :error="form.errors.principal_signature" />
                                    <div v-if="school?.principal_signature" class="mt-2">
                                        <p class="text-xs text-base-content/50 mb-1">Current:</p>
                                        <img :src="'/storage/' + school.principal_signature" class="max-h-16 border border-base-200 rounded p-1" alt="Principal signature" />
                                    </div>
                                </div>
                                <div>
                                    <FileUpload v-model="form.school_stamp" label="School Stamp" accept="image/*" :preview="true" :error="form.errors.school_stamp" />
                                    <div v-if="school?.school_stamp" class="mt-2">
                                        <p class="text-xs text-base-content/50 mb-1">Current:</p>
                                        <img :src="'/storage/' + school.school_stamp" class="max-h-16 border border-base-200 rounded p-1" alt="School stamp" />
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end pt-4 border-t border-base-200">
                                <button type="submit" class="btn btn-primary" :disabled="form.processing">
                                    <span v-if="form.processing" class="loading loading-spinner loading-sm"></span>
                                    Save Signatures & Stamp
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div v-else-if="!schools?.length" class="card bg-base-100 shadow-md">
                <div class="card-body text-center text-base-content/60">
                    <p>No school is associated with your account.</p>
                </div>
            </div>
            <div v-else class="card bg-base-100 shadow-md">
                <div class="card-body text-center text-base-content/60">
                    <p>Please select a school above to manage its settings.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
