<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormSelect from '@/Components/FormSelect.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import {
    ArrowsRightLeftIcon,
    ArrowUturnLeftIcon,
    UserCircleIcon,
    PaperAirplaneIcon,
    AcademicCapIcon,
    BuildingOfficeIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    student: { type: Object, required: true },
    schools: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
})

const form = useForm({
    student_id: props.student?.id,
    to_school_id: props.student?.school_id || '',
    to_class_id: '',
    to_section_id: '',
    reason: '',
})

const schoolOptions = computed(() =>
    props.schools.map((s) => ({ value: s.id, label: s.name }))
)

const classOptions = computed(() => {
    if (!form.to_school_id) return []
    return props.classes
        .filter((c) => String(c.school_id) === String(form.to_school_id))
        .map((c) => ({ value: c.id, label: c.name }))
})

const sectionOptions = computed(() => {
    if (!form.to_class_id) return []
    return props.sections
        .filter((s) => String(s.school_class_id) === String(form.to_class_id))
        .map((s) => ({ value: s.id, label: s.name }))
})

watch(() => form.to_school_id, () => {
    form.to_class_id = ''
    form.to_section_id = ''
})

watch(() => form.to_class_id, () => {
    form.to_section_id = ''
})

const transferType = computed(() => {
    if (!form.to_school_id || !props.student?.school_id) return null
    return Number(form.to_school_id) === Number(props.student.school_id) ? 'Intra-School' : 'Inter-School'
})

function submit() {
    form.post(route('transfers.store'))
}
</script>

<template>
    <Head :title="`Transfer Student - ${student?.name || ''}`" />
    <AppLayout
        :breadcrumbs="[
            { label: 'Students', href: route('students.index') },
            { label: 'Transfers', href: route('transfers.index') },
            { label: student?.name || 'Transfer' },
        ]"
    >
        <div class="space-y-5">
            <!-- Header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <ArrowsRightLeftIcon class="h-6 w-6 text-primary" />
                        Transfer Student - {{ student?.name }}
                    </h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        Initiate a transfer to a new school, class, and section.
                    </p>
                </div>
                <Link :href="route('transfers.index')" class="btn btn-ghost btn-sm gap-2">
                    <ArrowUturnLeftIcon class="h-4 w-4" />
                    Back
                </Link>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <!-- Form -->
                <form class="lg:col-span-2 card bg-base-100 shadow-sm border border-base-200" @submit.prevent="submit">
                    <div class="card-body p-5 space-y-4">
                        <h2 class="card-title text-base">
                            <ArrowsRightLeftIcon class="h-5 w-5 text-primary" />
                            Transfer Details
                        </h2>

                        <FormSelect
                            v-model="form.to_school_id"
                            label="Target School"
                            :options="schoolOptions"
                            placeholder="Select target school"
                            :error="form.errors.to_school_id"
                            required
                        />

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <FormSelect
                                v-model="form.to_class_id"
                                label="Target Class"
                                :options="classOptions"
                                :disabled="!form.to_school_id"
                                placeholder="Select class"
                                :error="form.errors.to_class_id"
                                required
                            />
                            <FormSelect
                                v-model="form.to_section_id"
                                label="Target Section"
                                :options="sectionOptions"
                                :disabled="!form.to_class_id"
                                placeholder="Select section"
                                :error="form.errors.to_section_id"
                                required
                            />
                        </div>

                        <FormTextarea
                            v-model="form.reason"
                            label="Reason for Transfer"
                            placeholder="Please provide a reason for this transfer request"
                            :rows="4"
                            :error="form.errors.reason"
                            required
                        />

                        <div v-if="transferType" class="alert alert-info text-sm">
                            <ArrowsRightLeftIcon class="h-5 w-5 shrink-0" />
                            <span>
                                This is an <strong>{{ transferType }}</strong> transfer.
                                <span v-if="transferType === 'Inter-School'">
                                    It will require approval from the receiving school's admin or super-admin before completion.
                                </span>
                                <span v-else>
                                    It will be auto-approved and completed immediately for school-admin.
                                </span>
                            </span>
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t border-base-200">
                            <Link :href="route('transfers.index')" class="btn btn-ghost btn-sm">
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                class="btn btn-primary btn-sm gap-2"
                                :disabled="form.processing"
                            >
                                <PaperAirplaneIcon class="h-4 w-4" />
                                Submit Transfer
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Sidebar: Current student info -->
                <aside class="card bg-base-100 shadow-sm border border-base-200 h-fit">
                    <div class="card-body p-5 space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="avatar placeholder">
                                <div v-if="!student?.photo_url" class="bg-primary text-primary-content rounded-xl w-14 h-14">
                                    <span class="text-lg">{{ student?.name?.charAt(0) }}</span>
                                </div>
                                <img v-else :src="student.photo_url" class="w-14 h-14 rounded-xl object-cover" alt="Student photo" />
                            </div>
                            <div>
                                <p class="font-semibold text-sm">{{ student?.name }}</p>
                                <p class="text-2xs text-base-content/50">
                                    Adm. No: {{ student?.admission_no || '-' }}
                                </p>
                            </div>
                        </div>

                        <div class="border-t border-base-200 pt-4 space-y-3 text-sm">
                            <div class="flex items-start gap-2">
                                <BuildingOfficeIcon class="h-4 w-4 mt-0.5 text-base-content/40" />
                                <div>
                                    <p class="text-2xs uppercase tracking-wider text-base-content/40">Current School</p>
                                    <p class="font-medium">{{ student?.school?.name || '-' }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-2">
                                <AcademicCapIcon class="h-4 w-4 mt-0.5 text-base-content/40" />
                                <div>
                                    <p class="text-2xs uppercase tracking-wider text-base-content/40">Current Class</p>
                                    <p class="font-medium">{{ student?.school_class?.name || '-' }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-2">
                                <UserCircleIcon class="h-4 w-4 mt-0.5 text-base-content/40" />
                                <div>
                                    <p class="text-2xs uppercase tracking-wider text-base-content/40">Current Section</p>
                                    <p class="font-medium">{{ student?.section?.name || '-' }}</p>
                                </div>
                            </div>
                            <div v-if="student?.father_name">
                                <p class="text-2xs uppercase tracking-wider text-base-content/40">Father</p>
                                <p class="font-medium">{{ student.father_name }}</p>
                            </div>
                            <div v-if="student?.guardian_phone">
                                <p class="text-2xs uppercase tracking-wider text-base-content/40">Phone</p>
                                <p class="font-medium">{{ student.guardian_phone }}</p>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
