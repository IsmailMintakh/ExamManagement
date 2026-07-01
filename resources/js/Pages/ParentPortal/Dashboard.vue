<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    UserCircleIcon, AcademicCapIcon, BuildingOfficeIcon,
    UserGroupIcon, ChartBarIcon, ArrowRightIcon,
    CheckCircleIcon, XCircleIcon, TrophyIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    children: Array,
    currentSession: Object,
})

function fmtPct(v) {
    if (v === null || v === undefined) return '-'
    return `${Number(v).toFixed(2)}%`
}
</script>

<template>
    <Head title="My Children" />
    <AppLayout :breadcrumbs="[{ label: 'My Children' }]">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold flex items-center gap-2">
                        <UserGroupIcon class="h-6 w-6 text-primary" />
                        My Children
                    </h1>
                    <p class="text-sm text-base-content/55 mt-0.5">
                        View academic progress for your linked children
                        <span v-if="currentSession">&middot; Current Session: <strong>{{ currentSession.name }}</strong></span>
                    </p>
                </div>
            </div>

            <!-- No children -->
            <div v-if="!children?.length" class="card bg-base-100 shadow-md">
                <div class="card-body p-8 text-center">
                    <UserGroupIcon class="h-12 w-12 text-base-content/20 mx-auto mb-3" />
                    <p class="text-base-content/55">
                        No children are currently linked to your account. Please contact your school administrator.
                    </p>
                </div>
            </div>

            <!-- Child cards grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <div v-for="child in children" :key="child.id" class="card bg-base-100 shadow-md hover:shadow-lg transition">
                    <div class="card-body p-5">
                        <!-- Top: photo + identity -->
                        <div class="flex items-start gap-4 mb-4">
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-primary to-secondary text-white ring-2 ring-base-200">
                                <img v-if="child.photo_url" :src="child.photo_url" :alt="child.name" class="h-full w-full object-cover" />
                                <UserCircleIcon v-else class="h-10 w-10" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold truncate">{{ child.name }}</h3>
                                <p class="text-xs text-base-content/55 mt-0.5">Adm No: {{ child.admission_no }}</p>
                                <p v-if="child.roll_no" class="text-xs text-base-content/55">Roll: {{ child.roll_no }}</p>
                            </div>
                        </div>

                        <!-- Academic info -->
                        <div class="space-y-1.5 text-xs mb-4 rounded-lg bg-base-200/40 p-3">
                            <div class="flex items-center gap-2">
                                <BuildingOfficeIcon class="h-3.5 w-3.5 text-base-content/50" />
                                <span class="truncate">{{ child.school?.name || '-' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <AcademicCapIcon class="h-3.5 w-3.5 text-base-content/50" />
                                <span>{{ child.class?.name || '-' }}<template v-if="child.section"> &middot; {{ child.section.name }}</template></span>
                            </div>
                        </div>

                        <!-- Latest result summary -->
                        <div v-if="child.latest_result" class="rounded-lg border border-base-200 p-3 mb-4">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-2xs uppercase tracking-wider text-base-content/55">Latest Result</p>
                                <span
                                    class="badge badge-xs gap-1"
                                    :class="child.latest_result.is_passed ? 'badge-success' : 'badge-error'"
                                >
                                    <CheckCircleIcon v-if="child.latest_result.is_passed" class="h-2.5 w-2.5" />
                                    <XCircleIcon v-else class="h-2.5 w-2.5" />
                                    {{ child.latest_result.is_passed ? 'Pass' : 'Retry' }}
                                </span>
                            </div>
                            <p class="text-sm font-semibold truncate">{{ child.latest_result.exam_name }}</p>
                            <div class="grid grid-cols-3 gap-2 mt-2 text-center">
                                <div>
                                    <p class="text-2xs text-base-content/50">%</p>
                                    <p class="text-sm font-bold text-primary">{{ fmtPct(child.latest_result.percentage) }}</p>
                                </div>
                                <div>
                                    <p class="text-2xs text-base-content/50">Grade</p>
                                    <p class="text-sm font-bold">{{ child.latest_result.grade || '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-2xs text-base-content/50 flex items-center justify-center gap-0.5">
                                        <TrophyIcon class="h-2.5 w-2.5" /> Pos
                                    </p>
                                    <p class="text-sm font-bold">{{ child.latest_result.position ? '#' + child.latest_result.position : '-' }}</p>
                                </div>
                            </div>
                        </div>
                        <div v-else class="rounded-lg border border-dashed border-base-200 p-3 mb-4 text-center">
                            <p class="text-xs text-base-content/50">No results published yet</p>
                        </div>

                        <Link
                            :href="route('parent-portal.child-results', child.id)"
                            class="btn btn-primary btn-sm w-full gap-1.5"
                        >
                            <ChartBarIcon class="h-4 w-4" />
                            View Results
                            <ArrowRightIcon class="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
