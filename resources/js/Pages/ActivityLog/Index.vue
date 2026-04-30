<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchFilter from '@/Components/SearchFilter.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { ClockIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    activities: Object,
    filters: Object,
    logNames: Array,
    subjectTypes: Array,
})

const search = ref(props.filters?.search || '')
const dateFrom = ref(props.filters?.date_from || '')
const dateTo = ref(props.filters?.date_to || '')

function buildParams(extra = {}) {
    const params = { ...extra }
    if (search.value) params.search = search.value
    if (dateFrom.value) params.date_from = dateFrom.value
    if (dateTo.value) params.date_to = dateTo.value
    return params
}

function handleSearch(val) {
    search.value = val
    router.get(route('activity-log.index'), buildParams(), { preserveState: true, replace: true })
}

function handleFilter(filters) {
    router.get(route('activity-log.index'), buildParams({
        log_name: filters.log_name || undefined,
        subject_type: filters.subject_type || undefined,
    }), { preserveState: true, replace: true })
}

function applyDateFilter() {
    router.get(route('activity-log.index'), buildParams(), { preserveState: true, replace: true })
}

function formatSubjectType(type) {
    if (!type) return '-'
    // Extract class basename from fully-qualified class name
    return type.split('\\').pop()
}
</script>

<template>
    <Head title="Activity Log" />
    <AppLayout :breadcrumbs="[{ label: 'Activity Log' }]">
        <div class="space-y-4">
            <h1 class="text-2xl font-bold">Activity Log</h1>

            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <SearchFilter
                        v-model="search"
                        @update:model-value="handleSearch"
                        :filters="[
                            { key: 'log_name', label: 'Log Name', options: logNames?.map(n => ({ value: n, label: n })) || [] },
                            { key: 'subject_type', label: 'Subject Type', options: subjectTypes?.map(s => ({ value: s.value, label: s.label })) || [] },
                        ]"
                        @filter="handleFilter"
                    />

                    <div class="flex flex-wrap gap-3 mt-3">
                        <div class="form-control">
                            <label class="label"><span class="label-text text-xs">From</span></label>
                            <input v-model="dateFrom" type="date" class="input input-bordered input-sm" @change="applyDateFilter" />
                        </div>
                        <div class="form-control">
                            <label class="label"><span class="label-text text-xs">To</span></label>
                            <input v-model="dateTo" type="date" class="input input-bordered input-sm" @change="applyDateFilter" />
                        </div>
                    </div>

                    <div class="overflow-x-auto mt-4" v-if="activities?.data?.length">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Subject</th>
                                    <th>Log</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(activity, i) in activities.data" :key="activity.id">
                                    <td>{{ activities.from + i }}</td>
                                    <td class="text-sm whitespace-nowrap">
                                        <div class="flex items-center gap-1 text-base-content/60">
                                            <ClockIcon class="w-3.5 h-3.5" />
                                            {{ activity.created_at }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="avatar placeholder">
                                                <div class="bg-neutral text-neutral-content rounded-full w-6 h-6">
                                                    <span class="text-xs">{{ activity.causer?.name?.charAt(0) || '?' }}</span>
                                                </div>
                                            </div>
                                            <span class="text-sm">{{ activity.causer?.name || 'System' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span :class="[
                                            'badge badge-sm',
                                            activity.description === 'created' ? 'badge-success' :
                                            activity.description === 'updated' ? 'badge-info' :
                                            activity.description === 'deleted' ? 'badge-error' : 'badge-ghost'
                                        ]">
                                            {{ activity.description }}
                                        </span>
                                    </td>
                                    <td class="text-sm">
                                        {{ formatSubjectType(activity.subject_type) }}
                                        <span v-if="activity.subject_id" class="text-base-content/40">#{{ activity.subject_id }}</span>
                                    </td>
                                    <td class="text-sm">{{ activity.log_name || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-4">
                            <Pagination :links="activities.links" />
                        </div>
                    </div>
                    <EmptyState v-else title="No activity logs" description="Activity will appear here as users interact with the system." />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
