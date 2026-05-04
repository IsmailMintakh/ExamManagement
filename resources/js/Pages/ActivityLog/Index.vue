<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import {
    ClockIcon, MagnifyingGlassIcon, FunnelIcon, ChevronDownIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'
import { formatRelative, formatStatus } from '@/Utils/format'

const props = defineProps({
    activities: Object,
    filters: Object,
    logNames: Array,
    subjectTypes: Array,
})

const search = ref(props.filters?.search || '')
const logName = ref(props.filters?.log_name || '')
const subjectType = ref(props.filters?.subject_type || '')
const dateFrom = ref(props.filters?.date_from || '')
const dateTo = ref(props.filters?.date_to || '')

const activeFilterCount = computed(() =>
    [logName.value, subjectType.value, dateFrom.value, dateTo.value].filter(Boolean).length
)
const filtersOpen = ref(activeFilterCount.value > 0)

let timer = null
function pushFilters() {
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => {
        router.get(route('activity-log.index'), {
            search: search.value || undefined,
            log_name: logName.value || undefined,
            subject_type: subjectType.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        }, {
            preserveState: true, preserveScroll: true, replace: true, only: ['activities', 'filters'],
        })
    }, 300)
}
watch([search, logName, subjectType, dateFrom, dateTo], pushFilters)

function clearFilters() {
    logName.value = ''
    subjectType.value = ''
    dateFrom.value = ''
    dateTo.value = ''
}

function formatSubjectType(type) {
    if (!type) return '-'
    return type.split('\\').pop()
}
</script>

<template>
    <Head title="Activity Log" />
    <AppLayout :breadcrumbs="[{ label: 'Activity Log' }]">
        <div class="space-y-5">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight">Activity Log</h1>
                <p class="text-sm text-base-content/55 mt-0.5">{{ activities?.total || 0 }} event{{ (activities?.total || 0) === 1 ? '' : 's' }} recorded</p>
            </div>

            <section class="surface overflow-hidden">
                <header class="surface-header">
                    <div class="relative flex-1 max-w-md">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-base-content/40" />
                        <input v-model="search" type="text"
                            placeholder="Search activity…"
                            class="input input-bordered input-sm w-full pl-9 text-sm" />
                    </div>
                    <button type="button" @click="filtersOpen = !filtersOpen"
                        class="btn btn-sm gap-1.5"
                        :class="filtersOpen ? 'btn-primary' : 'btn-outline'">
                        <FunnelIcon class="w-4 h-4" /> Filters
                        <span v-if="activeFilterCount > 0" class="badge badge-sm badge-warning text-warning-content tabular-nums">{{ activeFilterCount }}</span>
                        <ChevronDownIcon class="w-3.5 h-3.5 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" />
                    </button>
                </header>

                <Transition name="filter-panel">
                    <div v-if="filtersOpen" class="border-b border-base-200 bg-base-200/30 px-5 sm:px-6 py-4 space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/60 mb-1.5 block">Log Name</label>
                                <select v-model="logName" class="select select-bordered select-sm w-full text-sm">
                                    <option value="">All logs</option>
                                    <option v-for="n in logNames" :key="n" :value="n">{{ n }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/60 mb-1.5 block">Subject Type</label>
                                <select v-model="subjectType" class="select select-bordered select-sm w-full text-sm">
                                    <option value="">All types</option>
                                    <option v-for="s in subjectTypes" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/60 mb-1.5 block">From</label>
                                <input v-model="dateFrom" type="date" class="input input-bordered input-sm w-full text-sm" :max="dateTo || undefined" />
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/60 mb-1.5 block">To</label>
                                <input v-model="dateTo" type="date" class="input input-bordered input-sm w-full text-sm" :min="dateFrom || undefined" />
                            </div>
                        </div>
                        <div v-if="activeFilterCount > 0" class="flex items-center justify-between gap-2 pt-2 border-t border-base-200">
                            <span class="text-xs text-base-content/55">
                                <span class="font-bold text-base-content">{{ activeFilterCount }}</span>
                                filter{{ activeFilterCount === 1 ? '' : 's' }} applied
                                · {{ activities?.total || 0 }} event{{ (activities?.total || 0) === 1 ? '' : 's' }} found
                            </span>
                            <button type="button" @click="clearFilters" class="btn btn-ghost btn-xs gap-1 text-base-content/65">
                                <XMarkIcon class="w-3.5 h-3.5" /> Clear all
                            </button>
                        </div>
                    </div>
                </Transition>

                <div class="table-sticky-wrap" style="--table-max-h: 65vh;" v-if="activities?.data?.length">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="w-12">#</th>
                                <th>When</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Subject</th>
                                <th>Log</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(activity, i) in activities.data" :key="activity.id">
                                <td class="text-xs font-mono text-base-content/55 tabular-nums">{{ activities.from + i }}</td>
                                <td class="whitespace-nowrap">
                                    <div class="flex items-center gap-1.5 text-[12px] text-base-content/70">
                                        <ClockIcon class="w-3.5 h-3.5" />
                                        <span class="tabular-nums">{{ formatRelative(activity.created_at) }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-slate-500 to-slate-700 text-white text-[10px] font-bold flex items-center justify-center flex-shrink-0">
                                            {{ activity.causer?.name?.charAt(0)?.toUpperCase() || '?' }}
                                        </div>
                                        <span class="text-[13px] font-medium truncate">{{ activity.causer?.name || 'System' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span :class="[
                                        'badge badge-sm',
                                        activity.description === 'created' ? 'badge-success' :
                                        activity.description === 'updated' ? 'badge-info' :
                                        activity.description === 'deleted' ? 'badge-error' : 'badge-ghost'
                                    ]">
                                        {{ formatStatus(activity.description) }}
                                    </span>
                                </td>
                                <td class="text-[13px]">
                                    <span class="font-medium">{{ formatSubjectType(activity.subject_type) }}</span>
                                    <span v-if="activity.subject_id" class="text-base-content/40 font-mono ml-1">#{{ activity.subject_id }}</span>
                                </td>
                                <td class="text-[13px] text-base-content/65">{{ activity.log_name || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState v-if="!activities?.data?.length"
                    title="No activity logs"
                    description="Activity will appear here as users interact with the system." />

                <footer v-if="activities?.data?.length && activities.last_page > 1" class="surface-footer">
                    <span class="text-xs text-base-content/55 font-medium">
                        Showing <span class="text-base-content font-bold">{{ activities.from }}–{{ activities.to }}</span>
                        of <span class="text-base-content font-bold">{{ activities.total }}</span>
                    </span>
                    <Pagination :links="activities.links" />
                </footer>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.filter-panel-enter-active,
.filter-panel-leave-active {
    transition: opacity 0.2s ease, max-height 0.25s ease;
    overflow: hidden;
}
.filter-panel-enter-from,
.filter-panel-leave-to {
    opacity: 0;
    max-height: 0;
}
.filter-panel-enter-to,
.filter-panel-leave-from {
    opacity: 1;
    max-height: 400px;
}
</style>
