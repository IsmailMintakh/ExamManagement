<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    ChartBarIcon, CheckCircleIcon, XCircleIcon,
    EyeIcon, FunnelIcon, TrophyIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    results: Array,
    sessions: Array,
    grouped: Object,
})

const sessionFilter = ref('all')

const filteredResults = computed(() => {
    if (sessionFilter.value === 'all') return props.results || []
    return (props.results || []).filter(r => String(r.session_id) === String(sessionFilter.value))
})

const groupedFiltered = computed(() => {
    const groups = {}
    for (const r of filteredResults.value) {
        const key = r.session_name || 'Unknown Session'
        if (!groups[key]) groups[key] = []
        groups[key].push(r)
    }
    return groups
})

function fmtPct(v) {
    if (v === null || v === undefined) return '-'
    return `${Number(v).toFixed(2)}%`
}
</script>

<template>
    <Head title="My Results" />
    <AppLayout :breadcrumbs="[{ label: 'My Results' }]">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold flex items-center gap-2">
                        <ChartBarIcon class="h-6 w-6 text-primary" />
                        My Results
                    </h1>
                    <p class="text-sm text-base-content/55 mt-0.5">
                        All your exam results across academic sessions
                    </p>
                </div>

                <!-- Session Filter -->
                <div class="flex items-center gap-2">
                    <FunnelIcon class="h-4 w-4 text-base-content/50" />
                    <div class="min-w-[180px]">
                        <SearchableSelect v-model="sessionFilter" size="sm"
                            :options="[{ value: 'all', label: 'All Sessions' }, ...sessions.map(s => ({ value: s.id, label: s.name }))]"
                            placeholder="All Sessions" />
                    </div>
                </div>
            </div>

            <!-- No results -->
            <div v-if="!filteredResults.length" class="card bg-base-100 shadow-md">
                <div class="card-body p-8 text-center">
                    <ChartBarIcon class="h-12 w-12 text-base-content/20 mx-auto mb-3" />
                    <p class="text-base-content/55">No results found for the selected session.</p>
                </div>
            </div>

            <!-- Grouped Results -->
            <div v-for="(group, sessionName) in groupedFiltered" :key="sessionName" class="space-y-3">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-base-content/60">
                        {{ sessionName }}
                    </h2>
                    <span class="badge badge-ghost badge-sm">{{ group.length }} exam{{ group.length !== 1 ? 's' : '' }}</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="r in group" :key="r.id" class="card bg-base-100 shadow-md hover:shadow-lg transition">
                        <div class="card-body p-5">
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-bold truncate">{{ r.exam_name }}</h3>
                                    <p class="text-xs text-base-content/55 mt-0.5">{{ r.exam_type }}</p>
                                </div>
                                <span
                                    class="badge badge-sm gap-1"
                                    :class="r.is_passed ? 'badge-success' : 'badge-error'"
                                >
                                    <CheckCircleIcon v-if="r.is_passed" class="h-3 w-3" />
                                    <XCircleIcon v-else class="h-3 w-3" />
                                    {{ r.is_passed ? 'Pass' : 'Fail' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-3 gap-2 mb-4">
                                <div class="rounded-lg bg-base-200/50 p-2 text-center">
                                    <p class="text-2xs uppercase text-base-content/50">Marks</p>
                                    <p class="text-sm font-bold">{{ r.obtained_marks }}/{{ r.total_marks }}</p>
                                </div>
                                <div class="rounded-lg bg-primary/10 p-2 text-center">
                                    <p class="text-2xs uppercase text-primary/70">Percentage</p>
                                    <p class="text-sm font-bold text-primary">{{ fmtPct(r.percentage) }}</p>
                                </div>
                                <div class="rounded-lg bg-base-200/50 p-2 text-center">
                                    <p class="text-2xs uppercase text-base-content/50">Grade</p>
                                    <p class="text-sm font-bold">{{ r.grade || '-' }}</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <div v-if="r.position" class="flex items-center gap-1 text-sm">
                                    <TrophyIcon class="h-4 w-4 text-warning" />
                                    <span class="font-semibold">Position #{{ r.position }}</span>
                                </div>
                                <div v-else class="text-xs text-base-content/40">Position pending</div>
                                <Link
                                    :href="route('student-portal.result-detail', r.id)"
                                    class="btn btn-primary btn-xs gap-1"
                                >
                                    <EyeIcon class="h-3.5 w-3.5" />
                                    View Details
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
