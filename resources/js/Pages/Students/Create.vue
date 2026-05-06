<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import FormSelect from '@/Components/FormSelect.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import FileUpload from '@/Components/FileUpload.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { invalidatePageCache } from '@/Composables/useCacheInvalidation'
import { ref, watch } from 'vue'

// today in YYYY-MM-DD for the DOB max — students obviously can't be born in the future.
const today = new Date().toISOString().slice(0, 10)

const props = defineProps({
    student: Object,
    schools: Array,
    classes: Array,
    sections: Array,
    currentSession: Object,
    // Initial smart defaults from the controller — { admission_no, roll_no,
    // academic_session_id }. Updated client-side via the smart-defaults
    // endpoint when the user picks a different school or section.
    smartDefaults: { type: Object, default: () => ({}) },
})

const isEdit = !!props.student

// On a NEW student, pre-fill admission_no, roll_no and academic_session_id
// from the server's smart defaults so the teacher only needs to type the name.
// When editing an existing student we keep their stored values untouched.
const form = useForm({
    _method: isEdit ? 'put' : 'post',
    admission_no: props.student?.admission_no || (isEdit ? '' : (props.smartDefaults?.admission_no || '')),
    roll_no: props.student?.roll_no || (isEdit ? '' : (props.smartDefaults?.roll_no || '')),
    name: props.student?.name || '',
    father_name: props.student?.father_name || '',
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
    academic_session_id: props.student?.academic_session_id
        || (isEdit ? '' : (props.smartDefaults?.academic_session_id || props.currentSession?.id || '')),
    photo: null,
})

// ─── Re-fetch smart defaults when school/section change ───
// We can't reliably detect "user typed vs we assigned" via watchers alone.
// Instead we remember the LAST suggestion we wrote and only overwrite a field
// if its current value is empty OR still equals the last suggestion (i.e.
// the user hasn't replaced it). The moment they type their own value, future
// suggestions stop touching the field.
const lastSuggestedAdmission = ref(props.smartDefaults?.admission_no || '')
const lastSuggestedRoll = ref(props.smartDefaults?.roll_no || '')

async function fetchSmartDefaults() {
    if (isEdit) return
    try {
        const params = new URLSearchParams()
        if (form.school_id) params.append('school_id', form.school_id)
        if (form.section_id) params.append('section_id', form.section_id)
        const r = await fetch(`${route('students.smart-defaults')}?${params}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
        if (!r.ok) return
        const data = await r.json()
        if (data.admission_no
            && (form.admission_no === '' || form.admission_no === lastSuggestedAdmission.value)) {
            form.admission_no = data.admission_no
            lastSuggestedAdmission.value = data.admission_no
        }
        if (data.roll_no
            && (form.roll_no === '' || form.roll_no === lastSuggestedRoll.value)) {
            form.roll_no = data.roll_no
            lastSuggestedRoll.value = data.roll_no
        }
    } catch {
        // Silent — suggestions are best-effort, user can type values manually.
    }
}

watch(() => form.school_id, fetchSmartDefaults)
watch(() => form.section_id, fetchSmartDefaults)

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
            <div class="mb-5">
                <h1 class="text-2xl font-extrabold tracking-tight">{{ isEdit ? 'Edit Student' : 'Add New Student' }}</h1>
                <p class="text-sm text-base-content/55 mt-1">
                    {{ isEdit ? 'Update student information below.' : 'Fill in essential details, then optional info.' }}
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <!-- ───── Basic Info card ───── -->
                <section class="surface">
                    <header class="surface-header">
                        <h3>
                            <span class="w-7 h-7 rounded-lg bg-teal-500/15 text-teal-600 dark:text-teal-400 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </span>
                            Basic Information
                        </h3>
                    </header>
                    <div class="surface-body space-y-5">
                        <div class="form-grid-3">
                            <FormInput v-model="form.admission_no" label="Admission No" :error="form.errors.admission_no" required
                                :help-text="form.admission_no === lastSuggestedAdmission ? '↻ Auto-suggested next number for this school — type to override' : ''" />
                            <FormInput v-model="form.roll_no" label="Roll No" :error="form.errors.roll_no"
                                :help-text="form.section_id && form.roll_no === lastSuggestedRoll ? '↻ Auto-suggested next roll for this section — type to override' : ''" />
                            <FormInput v-model="form.name" label="Student Name" :error="form.errors.name" required />
                        </div>
                        <div class="form-grid">
                            <FormInput v-model="form.father_name" label="Father's Name" :error="form.errors.father_name" />
                            <FormInput v-model="form.guardian_phone" label="Guardian Phone" :error="form.errors.guardian_phone" />
                        </div>
                        <div class="form-grid-3">
                            <FormInput v-model="form.date_of_birth" label="Date of Birth" type="date" :error="form.errors.date_of_birth" :max="today" />
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
                    </div>
                </section>

                <!-- ───── Academic card ───── -->
                <section class="surface">
                    <header class="surface-header">
                        <h3>
                            <span class="w-7 h-7 rounded-lg bg-violet-500/15 text-violet-600 dark:text-violet-400 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                            </span>
                            Academic Details
                        </h3>
                    </header>
                    <div class="surface-body">
                        <div class="form-grid-3">
                            <FormSelect v-model="form.school_id" label="School" :error="form.errors.school_id" required
                                :options="schools?.map(s => ({ value: s.id, label: s.name })) || []" placeholder="Select School" />
                            <FormSelect v-model="form.school_class_id" label="Class" :error="form.errors.school_class_id" required
                                :options="classes?.filter(c => !form.school_id || c.school_id == form.school_id).map(c => ({ value: c.id, label: c.name })) || []" placeholder="Select Class" />
                            <FormSelect v-model="form.section_id" label="Section" :error="form.errors.section_id" required
                                :options="sections?.filter(s => !form.school_class_id || s.school_class_id == form.school_class_id).map(s => ({ value: s.id, label: s.name })) || []" placeholder="Select Section" />
                        </div>
                    </div>
                </section>

                <!-- ───── Additional / Photo / Address card ───── -->
                <section class="surface">
                    <header class="surface-header">
                        <h3>
                            <span class="w-7 h-7 rounded-lg bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </span>
                            Additional Details
                        </h3>
                    </header>
                    <div class="surface-body space-y-5">
                        <div class="form-grid">
                            <FormInput v-model="form.religion" label="Religion" placeholder="Islam" :error="form.errors.religion" />
                            <FormInput v-model="form.cnic" label="CNIC / B-Form No"
                                placeholder="12345-1234567-1"
                                help-text="Pakistani CNIC for adults; B-Form number for minors."
                                :error="form.errors.cnic" />
                        </div>
                        <div>
                            <label class="text-[12px] font-semibold text-base-content/75 mb-1.5 block">Student Photo</label>
                            <div v-if="isEdit && student?.photo_url && !form.photo"
                                 class="flex items-center gap-4 p-3 rounded-xl bg-base-200/40 mb-3 border border-base-200">
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
                    </div>
                </section>

                <!-- ───── Sticky save bar ───── -->
                <div class="sticky bottom-4 z-10 surface flex items-center justify-end gap-2 px-4 py-3">
                    <Link :href="route('students.index')" class="btn btn-ghost btn-sm">Cancel</Link>
                    <button type="submit" class="btn btn-primary btn-sm gap-1.5" :disabled="form.processing">
                        <span v-if="form.processing" class="loading loading-spinner loading-xs"></span>
                        {{ isEdit ? 'Update Student' : 'Add Student' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
