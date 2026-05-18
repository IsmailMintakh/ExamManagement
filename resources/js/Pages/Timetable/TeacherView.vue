<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import TimetableSubnav from '@/Components/timetable/TimetableSubnav.vue'
import { Head } from '@inertiajs/vue3'
import { UserCircleIcon, PrinterIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    teacher: Object,
    school: Object,
    slots: { type: Array, default: () => [] },
    entries: { type: Object, default: () => ({}) },
})

const DAYS = [
    { code: 'mon', label: 'Mon' },
    { code: 'tue', label: 'Tue' },
    { code: 'wed', label: 'Wed' },
    { code: 'thu', label: 'Thu' },
    { code: 'fri', label: 'Fri' },
    { code: 'sat', label: 'Sat' },
]

function entry(day, slotId) {
    return props.entries[`${day}|${slotId}`]
}
function slotApplies(slot, day) {
    const days = slot.weekdays || ['mon','tue','wed','thu','fri','sat']
    return days.includes(day)
}
function isPeriodSlot(s) { return s.type === 'period' }
</script>

<template>
    <Head :title="`${teacher.name} — Timetable`" />
    <AppLayout :breadcrumbs="[
        { label: 'Timetable', href: route('timetable.index') },
        { label: teacher.name },
    ]">
        <div class="space-y-3 max-w-[1500px] mx-auto">

            <PageHeader :title="teacher.name"
                :subtitle="`Personal timetable · ${school?.name || ''}`"
                :icon="UserCircleIcon" tone="sky">
                <template #actions>
                    <a :href="route('timetable.teacher.pdf', teacher.id)" target="_blank"
                        class="btn btn-outline btn-sm rounded-lg gap-1.5">
                        <PrinterIcon class="w-4 h-4" /> Print PDF
                    </a>
                </template>
            </PageHeader>

            <TimetableSubnav :school-id="school?.id" />

            <div class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-base-200/50">
                                <th class="text-left px-3 py-3 font-bold text-[11px] uppercase tracking-wider text-base-content/55 sticky left-0 bg-base-200/50 z-10 min-w-[140px]">Slot</th>
                                <th v-for="d in DAYS" :key="d.code" class="text-center px-3 py-3 font-bold text-[11px] uppercase tracking-wider text-base-content/55 min-w-[150px]">
                                    {{ d.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-300">
                            <tr v-for="slot in slots" :key="slot.id" :class="!isPeriodSlot(slot) ? 'bg-amber-500/5' : ''">
                                <td class="px-3 py-3 sticky left-0 z-10"
                                    :class="!isPeriodSlot(slot) ? 'bg-amber-500/10' : 'bg-base-100'">
                                    <p class="font-bold">{{ slot.name }}</p>
                                    <p class="text-[10px] text-base-content/55 font-mono">{{ slot.starts_at?.slice(0,5) }}–{{ slot.ends_at?.slice(0,5) }}</p>
                                </td>
                                <td v-for="d in DAYS" :key="d.code" class="px-2 py-2 text-center align-middle">
                                    <div v-if="!slotApplies(slot, d.code)" class="text-[10px] text-base-content/35 italic">—</div>
                                    <div v-else-if="!isPeriodSlot(slot)" class="text-[10px] uppercase tracking-wider font-bold text-amber-700 dark:text-amber-300">
                                        {{ slot.type }}
                                    </div>
                                    <div v-else-if="entry(d.code, slot.id)" class="space-y-0.5">
                                        <p class="font-bold text-[12px] truncate">{{ entry(d.code, slot.id).subject?.name || '—' }}</p>
                                        <p class="text-[10px] text-base-content/55 truncate">{{ entry(d.code, slot.id).school_class?.name }} · {{ entry(d.code, slot.id).section?.name }}</p>
                                    </div>
                                    <div v-else class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold uppercase tracking-wider">Free</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
