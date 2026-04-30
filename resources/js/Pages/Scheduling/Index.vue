<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    CalendarDaysIcon,
    BuildingOffice2Icon,
    TableCellsIcon,
    UserGroupIcon,
    IdentificationIcon,
    ChevronRightIcon,
    ClipboardDocumentListIcon,
    CheckCircleIcon,
    ExclamationCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: Object,
    stats: Object,
})

const cards = [
    {
        key: 'datesheet',
        title: 'Date Sheet',
        icon: CalendarDaysIcon,
        color: 'text-primary bg-primary/10',
        desc: 'Schedule exam dates, times and durations per subject & class.',
        statusKey: 'schedules',
        totalKey: 'exam_subjects',
        href: (id) => route('scheduling.datesheet', id),
        cta: 'Manage Date Sheet',
    },
    {
        key: 'rooms',
        title: 'Exam Rooms',
        icon: BuildingOffice2Icon,
        color: 'text-info bg-info/10',
        desc: 'Define exam rooms with seat layout (rows × columns).',
        statusKey: 'rooms',
        href: () => route('scheduling.rooms'),
        cta: 'Manage Rooms',
    },
    {
        key: 'seating',
        title: 'Seating Plan',
        icon: TableCellsIcon,
        color: 'text-accent bg-accent/10',
        desc: 'Auto-assign students to seats in rooms. Pick a section to begin.',
        statusKey: 'seats',
        totalKey: 'students',
        href: (id) => route('sections.index') + '?exam=' + id,
        cta: 'Open Sections',
    },
    {
        key: 'invigilators',
        title: 'Invigilators',
        icon: UserGroupIcon,
        color: 'text-warning bg-warning/10',
        desc: 'Assign teachers to rooms for each scheduled session.',
        statusKey: 'invigilators',
        href: (id) => route('scheduling.invigilators', id),
        cta: 'Manage Duties',
    },
    {
        key: 'admit',
        title: 'Admit Cards',
        icon: IdentificationIcon,
        color: 'text-success bg-success/10',
        desc: 'Generate and download bulk admit cards with QR verification.',
        href: (id) => route('scheduling.admit-cards', id),
        cta: 'Generate Cards',
    },
]

function pct(card) {
    const total = card.totalKey ? props.stats?.[card.totalKey] || 0 : 0
    const done = props.stats?.[card.statusKey] || 0
    if (!total) return 0
    return Math.min(100, Math.round((done / total) * 100))
}
</script>

<template>
    <Head :title="`Scheduling — ${exam.name}`" />
    <AppLayout
        :breadcrumbs="[
            { label: 'Exams', href: route('exams.index') },
            { label: exam.name, href: route('exams.show', exam.id) },
            { label: 'Scheduling' },
        ]"
    >
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <ClipboardDocumentListIcon class="h-6 w-6 text-primary" />
                        Exam Scheduling
                    </h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        Run the complete scheduling workflow for
                        <span class="font-semibold text-base-content">{{ exam.name }}</span>.
                    </p>
                </div>
                <div class="rounded-xl border border-base-200 bg-base-100 px-4 py-2 text-xs">
                    <p class="text-2xs uppercase tracking-wider text-base-content/40">Exam Window</p>
                    <p class="font-semibold">
                        {{ exam.start_date || '—' }} to {{ exam.end_date || '—' }}
                    </p>
                </div>
            </div>

            <!-- Stat strip -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div v-for="item in [
                    { label: 'Subjects', value: stats.exam_subjects, color: 'text-base-content' },
                    { label: 'Schedules', value: stats.schedules, color: 'text-primary' },
                    { label: 'Rooms', value: stats.rooms, color: 'text-info' },
                    { label: 'Students', value: stats.students, color: 'text-accent' },
                    { label: 'Seats Assigned', value: stats.seats, color: 'text-success' },
                    { label: 'Invigilators', value: stats.invigilators, color: 'text-warning' },
                ]" :key="item.label" class="rounded-xl border border-base-200 bg-base-100 p-3 text-center">
                    <p class="text-2xs uppercase tracking-wider text-base-content/40">{{ item.label }}</p>
                    <p class="mt-1 text-xl font-bold" :class="item.color">{{ item.value }}</p>
                </div>
            </div>

            <!-- Feature cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="card in cards"
                    :key="card.key"
                    class="card bg-base-100 shadow-sm border border-base-200 hover:border-primary/40 hover:shadow-md transition-all"
                >
                    <div class="card-body p-5">
                        <div class="flex items-start justify-between">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl" :class="card.color">
                                <component :is="card.icon" class="h-5 w-5" />
                            </div>
                            <span
                                v-if="card.totalKey"
                                class="badge badge-sm"
                                :class="pct(card) === 100 ? 'badge-success' : pct(card) > 0 ? 'badge-warning' : 'badge-ghost'"
                            >
                                <CheckCircleIcon v-if="pct(card) === 100" class="h-3 w-3 mr-1" />
                                <ExclamationCircleIcon v-else class="h-3 w-3 mr-1" />
                                {{ stats[card.statusKey] || 0 }} / {{ stats[card.totalKey] || 0 }}
                            </span>
                            <span
                                v-else-if="card.statusKey"
                                class="badge badge-sm badge-ghost"
                            >
                                {{ stats[card.statusKey] || 0 }} total
                            </span>
                        </div>

                        <h3 class="mt-3 text-base font-bold">{{ card.title }}</h3>
                        <p class="text-xs text-base-content/60 leading-relaxed">{{ card.desc }}</p>

                        <div v-if="card.totalKey" class="mt-3 h-1.5 w-full rounded-full bg-base-200 overflow-hidden">
                            <div class="h-full bg-primary" :style="{ width: pct(card) + '%' }" />
                        </div>

                        <div class="card-actions mt-4">
                            <Link :href="card.href(exam.id)" class="btn btn-primary btn-sm w-full gap-1.5">
                                {{ card.cta }}
                                <ChevronRightIcon class="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
