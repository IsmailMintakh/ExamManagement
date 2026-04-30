<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormSelect from '@/Components/FormSelect.vue'
import FileUpload from '@/Components/FileUpload.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ArrowDownTrayIcon, InformationCircleIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ schools: Array, classes: Array, sections: Array })

const form = useForm({
    file: null,
    school_id: '',
    school_class_id: '',
    section_id: '',
})

function submit() {
    form.post(route('students.process-import'), { forceFormData: true })
}
</script>

<template>
    <Head title="Import Students" />
    <AppLayout :breadcrumbs="[{ label: 'Students', href: route('students.index') }, { label: 'Import' }]">
        <div class="max-w-3xl mx-auto space-y-6">
            <h1 class="text-2xl font-bold">Import Students from Excel</h1>

            <!-- Instructions -->
            <div class="alert alert-info shadow-sm">
                <InformationCircleIcon class="w-6 h-6 shrink-0" />
                <div>
                    <p class="font-semibold text-sm">Excel File Requirements</p>
                    <ul class="text-xs mt-2 space-y-1">
                        <li class="flex items-start gap-1">
                            <CheckCircleIcon class="w-3.5 h-3.5 mt-0.5 shrink-0" />
                            Required columns: <strong>admission_no</strong>, <strong>name</strong>
                        </li>
                        <li class="flex items-start gap-1">
                            <CheckCircleIcon class="w-3.5 h-3.5 mt-0.5 shrink-0" />
                            Optional columns: roll_no, father_name, mother_name, guardian_phone, date_of_birth, gender, address, blood_group, religion, category, caste, aadhaar_no
                        </li>
                        <li class="flex items-start gap-1">
                            <CheckCircleIcon class="w-3.5 h-3.5 mt-0.5 shrink-0" />
                            Accepted formats: .xlsx, .xls, .csv
                        </li>
                        <li class="flex items-start gap-1">
                            <CheckCircleIcon class="w-3.5 h-3.5 mt-0.5 shrink-0" />
                            First row must be the header row with column names
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Import Form -->
            <form @submit.prevent="submit" class="card bg-base-100 shadow-md">
                <div class="card-body space-y-4">
                    <h3 class="text-lg font-semibold border-b border-base-200 pb-2">Select Target</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <FormSelect v-model="form.school_id" label="School" :error="form.errors.school_id" required
                            :options="schools?.map(s => ({ value: s.id, label: s.name })) || []" placeholder="Select School" />
                        <FormSelect v-model="form.school_class_id" label="Class" :error="form.errors.school_class_id" required
                            :options="classes?.filter(c => !form.school_id || c.school_id == form.school_id).map(c => ({ value: c.id, label: c.name })) || []" placeholder="Select Class" />
                        <FormSelect v-model="form.section_id" label="Section" :error="form.errors.section_id" required
                            :options="sections?.filter(s => !form.school_class_id || s.school_class_id == form.school_class_id).map(s => ({ value: s.id, label: s.name })) || []" placeholder="Select Section" />
                    </div>

                    <FileUpload v-model="form.file" label="Excel File" accept=".xlsx,.xls,.csv" :error="form.errors.file" />

                    <div class="flex justify-end gap-3 pt-4 border-t border-base-200">
                        <Link :href="route('students.index')" class="btn btn-ghost">Cancel</Link>
                        <button type="submit" class="btn btn-primary" :disabled="form.processing || !form.file">
                            <span v-if="form.processing" class="loading loading-spinner loading-sm"></span>
                            Import Students
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
