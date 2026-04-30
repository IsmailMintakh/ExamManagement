<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import FormSelect from '@/Components/FormSelect.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import FileUpload from '@/Components/FileUpload.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'

const props = defineProps({
    student: Object,
    schools: Array,
    classes: Array,
    sections: Array,
})

const isEdit = !!props.student

const form = useForm({
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
    category: props.student?.category || '',
    caste: props.student?.caste || '',
    aadhaar_no: props.student?.aadhaar_no || '',
    school_id: props.student?.school_id || '',
    school_class_id: props.student?.school_class_id || '',
    section_id: props.student?.section_id || '',
    photo: null,
})

function submit() {
    if (isEdit) {
        form.post(route('students.update', props.student.id), { _method: 'PUT', forceFormData: true })
    } else {
        form.post(route('students.store'), { forceFormData: true })
    }
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
                        <FormInput v-model="form.religion" label="Religion" :error="form.errors.religion" />
                        <FormInput v-model="form.category" label="Category" :error="form.errors.category" />
                        <FormInput v-model="form.caste" label="Caste" :error="form.errors.caste" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormInput v-model="form.aadhaar_no" label="Aadhaar No" :error="form.errors.aadhaar_no" />
                        <FileUpload v-model="form.photo" label="Student Photo" accept="image/*" :preview="true" :error="form.errors.photo" />
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
