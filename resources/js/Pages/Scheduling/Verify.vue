<script setup>
import { Head } from '@inertiajs/vue3'
import {
    CheckBadgeIcon,
    XCircleIcon,
    CalendarDaysIcon,
    MapPinIcon,
    UserCircleIcon,
} from '@heroicons/vue/24/outline'

defineProps({
    valid: { type: Boolean, default: false },
    message: { type: String, default: '' },
    student: { type: Object, default: null },
    exam: { type: Object, default: null },
    schedules: { type: Array, default: () => [] },
    seat: { type: Object, default: null },
})
</script>

<template>
    <Head title="Admit Card Verification" />
    <div class="min-h-screen bg-gradient-to-br from-base-200 to-base-300 flex items-center justify-center p-4">
        <div class="max-w-xl w-full bg-base-100 rounded-2xl shadow-xl overflow-hidden">
            <!-- Status banner -->
            <div v-if="valid" class="bg-success text-success-content p-6 flex items-center gap-4">
                <CheckBadgeIcon class="h-10 w-10" />
                <div>
                    <p class="text-xs uppercase tracking-wider opacity-80">Verified</p>
                    <p class="text-xl font-bold">Authentic Admit Card</p>
                </div>
            </div>
            <div v-else class="bg-error text-error-content p-6 flex items-center gap-4">
                <XCircleIcon class="h-10 w-10" />
                <div>
                    <p class="text-xs uppercase tracking-wider opacity-80">Invalid</p>
                    <p class="text-xl font-bold">Cannot Verify</p>
                    <p class="text-xs mt-1 opacity-90">{{ message || 'Admit card signature mismatch.' }}</p>
                </div>
            </div>

            <!-- Details -->
            <div v-if="valid" class="p-6 space-y-5">
                <!-- Student -->
                <div class="flex items-center gap-4">
                    <div class="h-16 w-16 rounded-xl overflow-hidden bg-base-200 flex items-center justify-center shrink-0">
                        <img v-if="student.photo" :src="student.photo" :alt="student.name" class="h-full w-full object-cover" />
                        <UserCircleIcon v-else class="h-10 w-10 text-base-content/30" />
                    </div>
                    <div>
                        <p class="text-lg font-bold">{{ student.name }}</p>
                        <p class="text-xs text-base-content/60">
                            Adm: {{ student.admission_no }} · Roll: {{ student.roll_no || '—' }}
                        </p>
                        <p class="text-xs text-base-content/60">
                            {{ student.class_name }} — {{ student.section_name }}
                        </p>
                        <p class="text-xs text-base-content/50 mt-0.5">{{ student.school_name }}</p>
                    </div>
                </div>

                <!-- Exam -->
                <div class="rounded-xl border border-base-200 bg-base-200/40 p-3">
                    <p class="text-2xs uppercase tracking-wider text-base-content/50">Exam</p>
                    <p class="text-base font-semibold">{{ exam.name }}</p>
                    <p v-if="exam.session" class="text-xs text-base-content/60">{{ exam.session }}</p>
                </div>

                <!-- Seat -->
                <div v-if="seat" class="flex items-center gap-2 rounded-xl border border-primary/30 bg-primary/5 p-3 text-sm">
                    <MapPinIcon class="h-5 w-5 text-primary shrink-0" />
                    <div>
                        <span class="text-base-content/60 text-xs">Seat:</span>
                        <span class="font-semibold ml-1">{{ seat.seat_number }}</span>
                        <span class="text-base-content/60 text-xs ml-2">Room:</span>
                        <span class="font-semibold ml-1">{{ seat.room_name }}</span>
                    </div>
                </div>

                <!-- Schedule -->
                <div v-if="schedules.length">
                    <p class="text-2xs uppercase tracking-wider text-base-content/50 mb-2 flex items-center gap-1">
                        <CalendarDaysIcon class="h-3 w-3" />
                        Schedule
                    </p>
                    <div class="rounded-xl border border-base-200 overflow-hidden">
                        <table class="table table-sm">
                            <thead class="bg-base-200/50">
                                <tr>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(s, i) in schedules" :key="i">
                                    <td class="font-medium text-sm">{{ s.subject }}</td>
                                    <td class="text-xs">{{ s.exam_date }}</td>
                                    <td class="text-xs">{{ s.start_time }}–{{ s.end_time }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="border-t border-base-200 p-3 text-center text-2xs text-base-content/40">
                This page verifies the authenticity of a school-issued admit card.
            </div>
        </div>
    </div>
</template>
