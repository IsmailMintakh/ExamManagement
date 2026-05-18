<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import TimetableSubnav from '@/Components/timetable/TimetableSubnav.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    PencilSquareIcon, PrinterIcon, AcademicCapIcon, ClockIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    section: Object,
    slots: { type: Array, default: () => [] },
    entries: { type: Object, default: () => ({}) },
})

const ALL_DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']
const DAY_LABEL = { mon: 'Mon', tue: 'Tue', wed: 'Wed', thu: 'Thu', fri: 'Fri', sat: 'Sat' }

function isPeriod(slot) {
    return slot.type === 'period'
}

// The routine is the same every day, so show ONE entry per slot — the first
// weekday it runs that has an assignment.
function slotDays(slot) {
    const d = (slot.weekdays && slot.weekdays.length) ? slot.weekdays : ALL_DAYS
    return ALL_DAYS.filter(x => d.includes(x))
}
function routineEntry(slot) {
    for (const day of slotDays(slot)) {
        const e = props.entries[`${day}|${slot.id}`]
        if (e) return e
    }
    return null
}
function daysLabel(slot) {
    const d = slotDays(slot)
    if (d.length === 6) return 'Daily'
    return d.map(x => DAY_LABEL[x]).join(', ')
}
</script>

<template>
    <Head :title="`${section.class_name} ${section.name} — Timetable`" />
    <AppLayout :breadcrumbs="[
        { label: 'Timetable', href: route('timetable.index') },
        { label: `${section.class_name} · ${section.name}` },
    ]">
        <div class="space-y-3 max-w-3xl mx-auto">

            <PageHeader :title="`${section.class_name} — Section ${section.name}`"
                :subtitle="`${section.school_name} · Daily routine (same Mon–Sat)`"
                :icon="AcademicCapIcon" tone="emerald">
                <template #actions>
                    <a :href="route('timetable.section.pdf', section.id)" target="_blank"
                        class="btn btn-outline btn-sm rounded-lg gap-1.5">
                        <PrinterIcon class="w-4 h-4" /> Print PDF
                    </a>
                    <Link :href="route('timetable.builder', section.id)" class="btn btn-primary btn-sm rounded-lg gap-1.5">
                        <PencilSquareIcon class="w-4 h-4" /> Edit
                    </Link>
                </template>
            </PageHeader>

            <TimetableSubnav />

            <div class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-base-200/50 text-[11px] uppercase tracking-wider text-base-content/55">
                            <th class="text-left px-4 py-3 font-bold">Period</th>
                            <th class="text-left px-4 py-3 font-bold">Subject</th>
                            <th class="text-left px-4 py-3 font-bold">Teacher</th>
                            <th class="text-left px-4 py-3 font-bold hidden sm:table-cell">Days</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-base-300">
                        <tr v-for="slot in slots" :key="slot.id" :class="!isPeriod(slot) ? 'bg-amber-500/5' : ''">
                            <td class="px-4 py-3 align-top">
                                <p class="font-bold">{{ slot.name }}</p>
                                <p class="text-[11px] text-base-content/55 font-mono flex items-center gap-1">
                                    <ClockIcon class="w-3 h-3" />
                                    {{ slot.starts_at?.slice(0,5) }}–{{ slot.ends_at?.slice(0,5) }}
                                </p>
                            </td>

                            <!-- Break / lunch -->
                            <td v-if="!isPeriod(slot)" colspan="3"
                                class="px-4 py-3 text-[11px] uppercase tracking-wider font-bold text-amber-700 dark:text-amber-300">
                                {{ slot.type }}
                            </td>

                            <template v-else>
                                <td class="px-4 py-3 font-semibold">
                                    {{ routineEntry(slot)?.subject?.name
                                        || '—' }}
                                </td>
                                <td class="px-4 py-3 text-base-content/70">
                                    {{ routineEntry(slot)?.teacher?.name
                                        || (routineEntry(slot) ? 'No teacher' : '') }}
                                    <span v-if="!routineEntry(slot)" class="text-base-content/35 italic">Free period</span>
                                </td>
                                <td class="px-4 py-3 text-[11px] text-base-content/55 hidden sm:table-cell">
                                    {{ daysLabel(slot) }}
                                </td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
