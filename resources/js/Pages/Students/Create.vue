<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import FormSelect from '@/Components/FormSelect.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import FileUpload from '@/Components/FileUpload.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { invalidatePageCache } from '@/Composables/useCacheInvalidation'

const props = defineProps({
    student: Object,
    schools: Array,
    classes: Array,
    sections: Array,
})

const isEdit = !!props.student

const form = useForm({
    // _method is a form field (Laravel reads it from the body), NOT an
    // Inertia option. For multipart uploads we must POST + spoof PUT,
    // because PUT requests can't carry multipart form data in browsers.
    _method: isEdit ? 'put' : 'post',
    admission_no: props.student?.admission_no || '',
    roll_no: props.student?.roll_no || '',
    name: props.student?.name || '',
    father_name: props.student?.father_name || '',
    mother_name: props.student?.mother_name || '',
    guardian_phone: props.student?.guardian_phone || '',
    date_of_birth: props.student?.date_of_birth || '',
    gender: props.student?.gender || 'male',
    address: props.student?.address || '',
    blood_group: props.student?.blood_group || '',
    religion: props.student?.religion || '',
    cnic: props.student?.cnic || '',
    school_id: props.student?.school_id || '',
    school_class_id: props.student?.school_class_id || '',
    section_id: props.student?.section_id || '',
    // Required by the update validator; auto-populated from the student
    // being edited so the form doesn't bounce with "session is required".
    academic_session_id: props.student?.academic_session_id || '',
    photo: null,
})

function submit() {
    const url = isEdit
        ? route('students.update', props.student.id)
        : route('students.store')
    // Always POST — when _method='put' is in the body, Laravel's
    // FormMethodSpoofingMiddleware translates it to a PUT route match.
    form.post(url, {
        forceFormData: true,
        preserveScroll: true,
        // After save, ask the SW to drop cached HTML/Inertia pages so
        // the next view of this student fetches the fresh photo URL
        // instead of serving a stale cached page.
        onSuccess: () => invalidatePageCache({ alsoUploads: true }),
    })
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Student' : 'Add Student'" />
    <AppLayout :breadcrumbs="[{ label: 'Students', href: route('students.index') }, { label: isEdit ? 'Edit' : 'Create' }]">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-2xl font-bold mb-6">{{ isEdit ? 'Edit Student' : 'Add New Student' }}</h1>

            <form @submit.prevent="submit" class="card bg-base-100 shadow-md">
                <div class="card-body space-y-6">
                    <!-- Basic Info -->
                    <h3 class="text-lg font-semibold border-b border-base-200 pb-2">Basic Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <FormInput v-model="form.admission_no" label="Admission No" :error="form.errors.admission_no" required />
                        <FormInput v-model="form.roll_no" label="Roll No" :error="form.errors.roll_no" />
                        <FormInput v-model="form.name" label="Student Name" :error="form.errors.name" required />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <FormInput v-model="form.father_name" label="Father's Name" :error="form.errors.father_name" />
                        <FormInput v-model="form.mother_name" label="Mother's Name" :error="form.errors.mother_name" />
                        <FormInput v-model="form.guardian_phone" label="Guardian Phone" :error="form.errors.guardian_phone" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <FormInput v-model="form.date_of_birth" label="Date of Birth" type="date" :error="form.errors.date_of_birth" />
                        <FormSelect v-model="form.gender" label="Gender" :error="form.errors.gender" :options="[
                            { value: 'male', label: 'Male' },
                            { value: 'female', label: 'Female' },
                            { value: 'other', label: 'Other' },
                        ]" />
                        <FormSelect v-model="form.blood_group" label="Blood Group" :error="form.errors.blood_group" :options="[
                            { value: '', label: 'Select' },
                            { value: 'A+', label: 'A+' }, { value: 'A-', label: 'A-' },
                            { value: 'B+', label: 'B+' }, { value: 'B-', label: 'B-' },
                            { value: 'AB+', label: 'AB+' }, { value: 'AB-', label: 'AB-' },
                            { value: 'O+', label: 'O+' }, { value: 'O-', label: 'O-' },
                        ]" />
                    </div>

                    <!-- Class/Section -->
                    <h3 class="text-lg font-semibold border-b border-base-200 pb-2">Academic Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <FormSelect v-model="form.school_id" label="School" :error="form.errors.school_id" required
                            :options="schools?.map(s => ({ value: s.id, label: s.name })) || []" placeholder="Select School" />
                        <FormSelect v-model="form.school_class_id" label="Class" :error="form.errors.school_class_id" required
                            :options="classes?.filter(c => !form.school_id || c.school_id == form.school_id).map(c => ({ value: c.id, label: c.name })) || []" placeholder="Select Class" />
                        <FormSelect v-model="form.section_id" label="Section" :error="form.errors.section_id" required
                            :options="sections?.filter(s => !form.school_class_id || s.school_class_id == form.school_class_id).map(s => ({ value: s.id, label: s.name })) || []" placeholder="Select Section" />
                    </div>

                    <!-- Additional -->
                    <h3 class="text-lg font-semibold border-b border-base-200 pb-2">Additional Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <FormInput v-model="form.religion" label="Religion" placeholder="Islam" :error="form.errors.religion" />
                        <FormInput v-model="form.cnic" label="CNIC / B-Form No"
                            placeholder="12345-1234567-1"
                            help-text="Pakistani CNIC for adults; B-Form number for minors."
                            :error="form.errors.cnic" />
                    </div>
                    <div>
                        <label class="text-[12px] font-semibold text-base-content/75 mb-1.5 block">Student Photo</label>
                        <!-- Show the current photo when editing so the user knows what's already set. -->
                        <div v-if="isEdit && student?.photo_url && !form.photo"
                             class="flex items-center gap-4 p-3 rounded-xl bg-base-200/40 mb-3">
                            <img :src="student.photo_url" alt="Current photo"
                                 class="h-24 w-24 object-cover rounded-xl" />
                            <div class="text-xs text-base-content/65">
                                <div class="font-semibold text-base-content">Current photo</div>
                                <div class="mt-1">Pick a new file below to replace it.</div>
                            </div>
                        </div>
                        <FileUpload v-model="form.photo"
                            accept="image/jpeg,image/png,image/webp"
                            :max-size="4" :preview="true"
                            :error="form.errors.photo" />
                    </div>
                    <FormTextarea v-model="form.address" label="Address" :error="form.errors.address" :rows="2" />

                    <div class="flex justify-end gap-3 pt-4 border-t border-base-200">
                        <Link :href="route('students.index')" class="btn btn-ghost">Cancel</Link>
                        <button type="submit" class="btn btn-primary" :disabled="form.processing">
                            <span v-if="form.processing" class="loading loading-spinner loading-sm"></span>
                            {{ isEdit ? 'Update Student' : 'Add Student' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
