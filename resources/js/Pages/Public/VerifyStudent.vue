<script setup>
import { Head } from '@inertiajs/vue3'
import {
    CheckBadgeIcon, XCircleIcon, AcademicCapIcon,
    IdentificationIcon, BuildingLibraryIcon, MapPinIcon,
} from '@heroicons/vue/24/outline'

defineProps({
    valid: Boolean,
    student: Object,
})

function initials(name) {
    if (!name) return '?'
    return name.split(' ').filter(Boolean).map(n => n[0]).slice(0, 2).join('').toUpperCase()
}
</script>

<template>
    <Head title="Verify Student ID" />
    <div class="min-h-screen bg-gradient-to-br from-emerald-50 via-white to-amber-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl border border-stone-200 max-w-md w-full overflow-hidden">
            <!-- Top hero -->
            <div class="bg-gradient-to-br from-emerald-700 to-emerald-900 text-white p-6 text-center relative overflow-hidden">
                <div class="absolute -top-16 -right-16 w-40 h-40 bg-amber-500/30 rounded-full blur-2xl"></div>
                <div class="relative">
                    <AcademicCapIcon class="w-9 h-9 mx-auto mb-2 text-amber-300" />
                    <h1 class="text-base font-bold tracking-tight">Student ID Verification</h1>
                    <p class="text-[10px] text-emerald-100/85 uppercase tracking-[0.2em] mt-1 font-semibold">Official School Records</p>
                </div>
            </div>

            <!-- Valid -->
            <div v-if="valid" class="p-6">
                <div class="text-center mb-5">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-50 ring-4 ring-emerald-100 mb-3">
                        <CheckBadgeIcon class="w-9 h-9 text-emerald-600" />
                    </div>
                    <p class="text-emerald-700 font-bold text-base">✓ Authentic Student ID</p>
                </div>

                <div class="flex items-center gap-4 p-4 rounded-2xl bg-stone-50 border border-stone-100 mb-4">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center text-white text-xl font-black flex-shrink-0">
                        {{ initials(student.name) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-extrabold text-lg leading-tight">{{ student.name }}</h2>
                        <p class="text-xs text-base-content/55 font-mono">Adm. {{ student.admission_no }}<span v-if="student.roll_no"> · Roll {{ student.roll_no }}</span></p>
                        <span v-if="student.status" class="inline-flex items-center mt-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                            :class="student.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                            {{ student.status }}
                        </span>
                    </div>
                </div>

                <dl class="space-y-2.5 text-sm">
                    <div class="flex items-start justify-between gap-3 pb-2 border-b border-stone-100">
                        <dt class="text-base-content/55 text-[11px] uppercase tracking-wider font-bold flex items-center gap-1.5">
                            <BuildingLibraryIcon class="w-3.5 h-3.5" /> School
                        </dt>
                        <dd class="font-semibold text-right">{{ student.school_name || '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3 pb-2 border-b border-stone-100">
                        <dt class="text-base-content/55 text-[11px] uppercase tracking-wider font-bold flex items-center gap-1.5">
                            <IdentificationIcon class="w-3.5 h-3.5" /> Class
                        </dt>
                        <dd class="font-semibold">{{ student.class_name }} · Section {{ student.section_name }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3 pb-2 border-b border-stone-100">
                        <dt class="text-base-content/55 text-[11px] uppercase tracking-wider font-bold">Session</dt>
                        <dd class="font-semibold">{{ student.session || '—' }}</dd>
                    </div>
                    <div v-if="student.school_address" class="flex items-start justify-between gap-3">
                        <dt class="text-base-content/55 text-[11px] uppercase tracking-wider font-bold flex items-center gap-1.5 flex-shrink-0">
                            <MapPinIcon class="w-3.5 h-3.5" /> Address
                        </dt>
                        <dd class="text-xs text-right">{{ student.school_address }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Invalid -->
            <div v-else class="p-6 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-rose-50 ring-4 ring-rose-100 mb-3">
                    <XCircleIcon class="w-9 h-9 text-rose-600" />
                </div>
                <p class="text-rose-700 font-bold text-base mb-1">Invalid ID</p>
                <p class="text-sm text-base-content/60">This admission number is not registered in our records, or the ID may have been deactivated.</p>
            </div>

            <div class="bg-stone-50 px-6 py-3 text-center text-[10px] text-stone-500 border-t border-stone-100">
                Verified by GBHSS School Management System
            </div>
        </div>
    </div>
</template>
