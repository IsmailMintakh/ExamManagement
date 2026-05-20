<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
    ArrowsRightLeftIcon, ClockIcon, CalendarDaysIcon, CheckCircleIcon,
    XCircleIcon, FaceSmileIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'
import { confirmAction } from '@/lib/swal'

const props = defineProps({
    todayRows: { type: Array, default: () => [] },
    upcomingRows: { type: Array, default: () => [] },
    pastRows: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({}) },
})

async function declineCover(row) {
    const ok = await confirmAction({
        title: 'Decline this class adjustment?',
        text: 'Admin will be notified and they will reassign it.',
        confirmText: 'Yes, decline',
        icon: 'warning',
        danger: true,
    })
    if (!ok) return
    router.post(route('my-adjustments.decline', row.id), {}, { preserveScroll: true })
}

function statusPill(status) {
    return {
        suggested: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 ring-amber-500/30',
        confirmed: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-500/30',
        declined: 'bg-rose-500/15 text-rose-700 dark:text-rose-300 ring-rose-500/30',
    }[status] || 'bg-base-content/10'
}
</script>

<template>
    <Head title="My Class Adjustments" />
    <AppLayout :breadcrumbs="[{ label: 'My Class Adjustments' }]">
        <div class="space-y-5 max-w-5xl mx-auto">

            <!-- Header -->
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight flex items-center gap-2">
                    <ArrowsRightLeftIcon class="w-6 h-6 text-amber-600 dark:text-amber-400" />
                    My Class Adjustments
                </h1>
                <p class="text-sm text-base-content/55 mt-1">
                    Adjustment periods you've been assigned. Today's are at the top.
                </p>
            </div>

            <!-- Stat strip -->
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Today</p>
                    <p class="text-2xl font-extrabold tabular-nums" :class="totals.today > 0 ? 'text-amber-600' : ''">
                        {{ totals.today || 0 }}
                    </p>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Upcoming</p>
                    <p class="text-2xl font-extrabold tabular-nums">{{ totals.upcoming || 0 }}</p>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">All-time</p>
                    <p class="text-2xl font-extrabold tabular-nums">{{ totals.all_time || 0 }}</p>
                </div>
            </div>

            <!-- TODAY -->
            <section class="rounded-2xl border-2 border-amber-500/30 bg-amber-500/5 overflow-hidden">
                <header class="px-5 py-3 border-b border-amber-500/30 flex items-center gap-2">
                    <ClockIcon class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                    <h2 class="text-sm font-bold">Today</h2>
                    <span class="text-xs text-base-content/45">· {{ todayRows.length }}</span>
                </header>
                <div v-if="todayRows.length" class="divide-y divide-amber-500/20">
                    <div v-for="r in todayRows" :key="r.id" class="px-5 py-3 flex items-center gap-3">
                        <div class="font-mono text-xs font-bold text-amber-700 dark:text-amber-300 shrink-0 w-20">
                            {{ r.time_range || '—' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-sm truncate">{{ r.subject || 'Class' }}</p>
                            <p class="text-xs text-base-content/65 truncate">
                                {{ r.class }} · {{ r.section }}
                                <span v-if="r.replaces" class="text-base-content/45">— replacing {{ r.replaces }}</span>
                            </p>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold ring-1 capitalize"
                            :class="statusPill(r.status)">
                            {{ r.status }}
                        </span>
                        <button v-if="r.status !== 'declined'" @click="declineCover(r)"
                            class="btn btn-ghost btn-xs rounded-lg gap-1 text-rose-600">
                            <XCircleIcon class="w-3.5 h-3.5" /> Decline
                        </button>
                    </div>
                </div>
                <div v-else class="px-5 py-8 text-center">
                    <FaceSmileIcon class="w-10 h-10 text-emerald-500 mx-auto mb-2" />
                    <p class="font-bold text-sm">No class adjustments today!</p>
                </div>
            </section>

            <!-- UPCOMING -->
            <section v-if="upcomingRows.length" class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <header class="px-5 py-3 border-b border-base-300 flex items-center gap-2">
                    <CalendarDaysIcon class="w-4 h-4 text-sky-600 dark:text-sky-400" />
                    <h2 class="text-sm font-bold">Upcoming</h2>
                    <span class="text-xs text-base-content/45">· {{ upcomingRows.length }}</span>
                </header>
                <table class="w-full text-sm">
                    <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider text-base-content/55">
                        <tr>
                            <th class="text-left px-3 py-2 font-bold">Date</th>
                            <th class="text-left px-3 py-2 font-bold">Period</th>
                            <th class="text-left px-3 py-2 font-bold">Class · Section</th>
                            <th class="text-left px-3 py-2 font-bold">Subject</th>
                            <th class="text-left px-3 py-2 font-bold">Replacing</th>
                            <th class="text-center px-3 py-2 font-bold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-base-300">
                        <tr v-for="r in upcomingRows" :key="r.id" class="hover:bg-base-200/30">
                            <td class="px-3 py-2 text-xs font-bold">{{ r.date_human }}</td>
                            <td class="px-3 py-2 text-xs">
                                <p>{{ r.time_slot }}</p>
                                <p class="text-[10px] text-base-content/55 font-mono">{{ r.time_range }}</p>
                            </td>
                            <td class="px-3 py-2 text-xs">{{ r.class }} · {{ r.section }}</td>
                            <td class="px-3 py-2 text-xs font-semibold">{{ r.subject }}</td>
                            <td class="px-3 py-2 text-xs text-base-content/55">{{ r.replaces }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold ring-1 capitalize"
                                    :class="statusPill(r.status)">
                                    {{ r.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- PAST -->
            <section v-if="pastRows.length" class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <header class="px-5 py-3 border-b border-base-300 flex items-center gap-2">
                    <CheckCircleIcon class="w-4 h-4 text-base-content/55" />
                    <h2 class="text-sm font-bold">Recent (last 20)</h2>
                </header>
                <table class="w-full text-sm">
                    <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider text-base-content/55">
                        <tr>
                            <th class="text-left px-3 py-2 font-bold">Date</th>
                            <th class="text-left px-3 py-2 font-bold">Class · Section</th>
                            <th class="text-left px-3 py-2 font-bold">Subject</th>
                            <th class="text-center px-3 py-2 font-bold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-base-300">
                        <tr v-for="r in pastRows" :key="r.id" class="hover:bg-base-200/30">
                            <td class="px-3 py-2 text-xs">{{ r.date_human }}</td>
                            <td class="px-3 py-2 text-xs">{{ r.class }} · {{ r.section }}</td>
                            <td class="px-3 py-2 text-xs font-semibold">{{ r.subject }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold ring-1 capitalize"
                                    :class="statusPill(r.status)">
                                    {{ r.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </AppLayout>
</template>
