<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import SchedulingSubnav from '@/Components/scheduling/SchedulingSubnav.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    CalendarDaysIcon, BuildingOffice2Icon, TableCellsIcon,
    UserGroupIcon, IdentificationIcon, ArrowRightIcon,
    ClipboardDocumentListIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: Object,
    stats: Object,
})

const steps = [
    { key: 'datesheet', n: 1, title: 'Date sheet', icon: CalendarDaysIcon,
      tone: 'text-primary bg-primary/10', desc: 'When each subject is examined — date, time & duration.',
      doneKey: 'schedules', totalKey: 'exam_subjects', href: (id) => route('scheduling.datesheet', id), cta: 'Set date sheet' },
    { key: 'rooms', n: 2, title: 'Exam rooms', icon: BuildingOffice2Icon,
      tone: 'text-sky-600 dark:text-sky-400 bg-sky-500/10', desc: 'Define rooms with a seat layout (rows × columns).',
      countKey: 'rooms', href: () => route('scheduling.rooms'), cta: 'Manage rooms' },
    { key: 'seating', n: 3, title: 'Seating plan', icon: TableCellsIcon,
      tone: 'text-violet-600 dark:text-violet-400 bg-violet-500/10', desc: 'Auto-assign students to seats. Pick a section to begin.',
      doneKey: 'seats', totalKey: 'students', href: (id) => route('sections.index') + '?exam=' + id, cta: 'Open sections' },
    { key: 'invigilators', n: 4, title: 'Invigilators', icon: UserGroupIcon,
      tone: 'text-amber-600 dark:text-amber-400 bg-amber-500/10', desc: 'Assign teachers to rooms for each session.',
      countKey: 'invigilators', href: (id) => route('scheduling.invigilators', id), cta: 'Assign duties' },
    { key: 'admit', n: 5, title: 'Admit cards', icon: IdentificationIcon,
      tone: 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10', desc: 'Generate bulk admit cards with QR verification.',
      href: (id) => route('scheduling.admit-cards', id), cta: 'Generate cards' },
]

function pct(s) {
    const total = s.totalKey ? props.stats?.[s.totalKey] || 0 : 0
    if (!total) return 0
    return Math.min(100, Math.round(((props.stats?.[s.doneKey] || 0) / total) * 100))
}

const statCells = [
    { label: 'Subjects', key: 'exam_subjects' },
    { label: 'Scheduled', key: 'schedules' },
    { label: 'Rooms', key: 'rooms' },
    { label: 'Students', key: 'students' },
    { label: 'Seats', key: 'seats' },
    { label: 'Invigilators', key: 'invigilators' },
]
</script>

<template>
    <Head :title="`Scheduling — ${exam.name}`" />
    <AppLayout :breadcrumbs="[
        { label: 'Exam Scheduling', href: route('scheduling.exams') },
        { label: exam.name },
    ]">
        <div class="space-y-4 max-w-5xl mx-auto">

            <PageHeader :title="exam.name"
                :subtitle="`Scheduling workflow · ${exam.start_date || '—'} → ${exam.end_date || '—'}`"
                :icon="ClipboardDocumentListIcon" tone="primary" />

            <SchedulingSubnav :exam-id="exam.id" />

            <!-- Stat strip -->
            <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                <div v-for="c in statCells" :key="c.label"
                    class="rounded-xl border border-base-300 bg-base-100 shadow-sm px-3 py-2 text-center">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/45">{{ c.label }}</p>
                    <p class="text-lg font-extrabold tabular-nums mt-0.5">{{ stats?.[c.key] ?? 0 }}</p>
                </div>
            </div>

            <!-- Workflow steps -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <div v-for="s in steps" :key="s.key"
                    class="rounded-xl border border-base-300 bg-base-100 shadow-sm p-4 flex flex-col">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" :class="s.tone">
                            <component :is="s.icon" class="w-5 h-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-base-content/40">Step {{ s.n }}</p>
                            <h3 class="font-bold text-sm leading-tight">{{ s.title }}</h3>
                        </div>
                        <span v-if="s.totalKey"
                            class="text-[10px] font-bold px-1.5 py-0.5 rounded shrink-0"
                            :class="pct(s) === 100 ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                : pct(s) > 0 ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                                : 'bg-base-200 text-base-content/55'">
                            <CheckCircleIcon v-if="pct(s) === 100" class="w-3 h-3 inline -mt-0.5" />
                            {{ stats?.[s.doneKey] || 0 }}/{{ stats?.[s.totalKey] || 0 }}
                        </span>
                        <span v-else-if="s.countKey" class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-base-200 text-base-content/55 shrink-0">
                            {{ stats?.[s.countKey] || 0 }}
                        </span>
                    </div>

                    <p class="text-xs text-base-content/55 mt-2.5 flex-1">{{ s.desc }}</p>

                    <div v-if="s.totalKey" class="mt-3 h-1.5 rounded-full bg-base-200 overflow-hidden">
                        <div class="h-full rounded-full transition-all"
                            :class="pct(s) === 100 ? 'bg-emerald-500' : 'bg-primary'"
                            :style="{ width: pct(s) + '%' }"></div>
                    </div>

                    <Link :href="s.href(exam.id)" class="btn btn-primary btn-sm rounded-lg w-full gap-1.5 mt-3">
                        {{ s.cta }} <ArrowRightIcon class="w-3.5 h-3.5" />
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
