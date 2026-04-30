<script setup>
import { Head } from '@inertiajs/vue3'
import { CheckBadgeIcon, XCircleIcon, AcademicCapIcon } from '@heroicons/vue/24/outline'

defineProps({
    valid: Boolean,
    certificate: Object,
})
</script>

<template>
    <Head title="Verify Certificate" />
    <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-amber-50 flex items-center justify-center p-4">
        <div class="card bg-base-100 shadow-2xl max-w-lg w-full">
            <div class="card-body p-8 text-center">
                <AcademicCapIcon class="w-12 h-12 mx-auto text-primary mb-2" />
                <h1 class="text-2xl font-bold">Certificate Verification</h1>

                <div v-if="valid" class="mt-6 space-y-4">
                    <div class="flex justify-center">
                        <div class="bg-success/10 rounded-full p-4">
                            <CheckBadgeIcon class="w-16 h-16 text-success" />
                        </div>
                    </div>
                    <div class="text-success font-semibold text-lg">Authentic Certificate</div>

                    <div class="bg-base-200 rounded-lg p-4 text-left space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Certificate No:</span>
                            <span class="font-mono font-semibold">{{ certificate.number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Student:</span>
                            <span class="font-semibold">{{ certificate.student_name }}</span>
                        </div>
                        <div v-if="certificate.admission_no" class="flex justify-between">
                            <span class="text-base-content/60">Admission No:</span>
                            <span class="font-mono">{{ certificate.admission_no }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-base-content/60">School:</span>
                            <span>{{ certificate.school_name }}</span>
                        </div>
                        <div v-if="certificate.exam_name" class="flex justify-between">
                            <span class="text-base-content/60">Exam:</span>
                            <span>{{ certificate.exam_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Type:</span>
                            <span>{{ certificate.template_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Issued:</span>
                            <span>{{ certificate.issued_at }}</span>
                        </div>
                    </div>
                </div>

                <div v-else class="mt-6 space-y-4">
                    <div class="flex justify-center">
                        <div class="bg-error/10 rounded-full p-4">
                            <XCircleIcon class="w-16 h-16 text-error" />
                        </div>
                    </div>
                    <div class="text-error font-semibold text-lg">Invalid or Revoked</div>
                    <p class="text-sm text-base-content/60">
                        This certificate could not be verified. It may have been revoked, or the verification code is incorrect.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
