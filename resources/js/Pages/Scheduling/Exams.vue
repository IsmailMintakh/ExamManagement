<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import SchedulingSubnav from '@/Components/scheduling/SchedulingSubnav.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    CalendarDaysIcon, BuildingOfficeIcon, ArrowRightIcon,
    CheckCircleIcon, ClockIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exams: Array,
    roomsCount: Number,
})

const search = ref('')
const filteredExams = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return props.exams || []
    return (props.exams || []).filter(e =>
        e.name?.toLowerCase().includes(q) ||
        e.exam_type?.toLowerCase().includes(q) ||
        e.session?.toLowerCase().includes(q))
})

const statusChip = (s) => ({
    draft: 'bg-base-200 text-base-content/55',
    published: 'bg-violet-500/12 text-violet-700 dark:text-violet-300',
    marks_entry: 'bg-amber-500/12 text-amber-700 dark:text-amber-300',
    processing: 'bg-sky-500/12 text-sky-700 dark:text-sky-300',
    completed: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
}[s] || 'bg-base-200 text-base-content/55')
const statusLabel = (s) => (s || '—').replace(/_/g, ' ')

function progress(e) {
    if (!e.exam_subjects_count) return 0
    return Math.round((e.schedules_count / e.exam_subjects_count) * 100)
}
</script>

<template>
    <Head title="Exam Scheduling" />
    <AppLayout :breadcrumbs="[{ label: 'Exam Scheduling' }]">
        <div class="space-y-4 max-w-5xl mx-auto">

            <PageHeader title="Exam scheduling"
                subtitle="Pick an exam to set its date sheet, seating, invigilators &amp; admit cards"
                :icon="CalendarDaysIcon" tone="primary">
                <template #actions>
                    <Link :href="route('scheduling.rooms')" class="btn btn-outline btn-sm rounded-lg gap-1.5">
                        <BuildingOfficeIcon class="w-4 h-4" /> Rooms
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-base-200">{{ roomsCount }}</span>
                    </Link>
                </template>
            </PageHeader>

            <SchedulingSubnav />

            <input v-model="search" type="text" placeholder="Search exams by name, type or session…"
                class="input input-bordered input-sm w-full rounded-lg text-sm" />

            <!-- Exam list -->
            <div v-if="filteredExams.length" class="rounded-xl border border-base-300 bg-base-100 shadow-sm divide-y divide-base-300 overflow-hidden">
                <Link v-for="exam in filteredExams" :key="exam.id"
                    :href="route('scheduling.index', exam.id)"
                    class="group flex items-center gap-4 px-4 py-3.5 hover:bg-base-200/40 transition-colors">
                    <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                        <CalendarDaysIcon class="w-5 h-5" />
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="font-bold text-sm leading-tight">{{ exam.name }}</p>
                            <span class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded capitalize"
                                :class="statusChip(exam.status)">{{ statusLabel(exam.status) }}</span>
                        </div>
                        <p class="text-[11px] text-base-content/55 mt-0.5">
                            {{ exam.exam_type }} · {{ exam.session }}
                            <span v-if="exam.start_date"> · {{ exam.start_date }} → {{ exam.end_date }}</span>
                        </p>
                        <!-- progress -->
                        <div v-if="exam.exam_subjects_count > 0" class="mt-2 flex items-center gap-2">
                            <div class="h-1.5 flex-1 max-w-[160px] rounded-full bg-base-200 overflow-hidden">
                                <div class="h-full rounded-full transition-all"
                                    :class="progress(exam) === 100 ? 'bg-emerald-500' : 'bg-primary'"
                                    :style="{ width: Math.max(progress(exam), 4) + '%' }"></div>
                            </div>
                            <span class="text-[11px] tabular-nums font-semibold"
                                :class="progress(exam) === 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-base-content/55'">
                                {{ exam.schedules_count }}/{{ exam.exam_subjects_count }} scheduled
                            </span>
                        </div>
                        <p v-else class="mt-1.5 text-[11px] text-amber-600 dark:text-amber-400">
                            ⚠ No subjects added to this exam yet
                        </p>
                    </div>

                    <div class="hidden sm:flex items-center gap-4 text-center shrink-0">
                        <div>
                            <p class="text-base font-extrabold tabular-nums leading-none text-sky-600 dark:text-sky-400">{{ exam.seats_count }}</p>
                            <p class="text-[9px] uppercase tracking-wider text-base-content/40 mt-1">Seats</p>
                        </div>
                        <div>
                            <p class="text-base font-extrabold tabular-nums leading-none text-amber-600 dark:text-amber-400">{{ exam.invigilators_count }}</p>
                            <p class="text-[9px] uppercase tracking-wider text-base-content/40 mt-1">Invig.</p>
                        </div>
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full"
                            :class="exam.is_fully_scheduled ? 'bg-emerald-500/12' : 'bg-amber-500/12'">
                            <CheckCircleIcon v-if="exam.is_fully_scheduled" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                            <ClockIcon v-else class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                        </span>
                    </div>

                    <ArrowRightIcon class="w-4 h-4 text-base-content/30 group-hover:text-primary group-hover:translate-x-0.5 transition-all shrink-0" />
                </Link>
            </div>

            <div v-else-if="search" class="rounded-xl border border-base-300 bg-base-100 shadow-sm p-10 text-center">
                <p class="text-sm text-base-content/55">No exams match “{{ search }}”.</p>
                <button @click="search = ''" class="btn btn-ghost btn-sm mt-3 rounded-lg">Clear search</button>
            </div>
            <div v-else class="rounded-xl border border-base-300 bg-base-100 shadow-sm">
                <EmptyState title="No exams to schedule"
                    description="Create an exam first, then schedule its date sheet, seating and invigilators."
                    action-text="Create Exam" :action-href="route('exams.create')" />
            </div>

            <p class="text-[11px] text-base-content/45 text-center pb-2">
                Steps per exam: <strong>Date sheet</strong> → <strong>Rooms</strong> → <strong>Seating</strong> → <strong>Invigilators</strong> → <strong>Admit cards</strong>.
            </p>
        </div>
    </AppLayout>
</template>
