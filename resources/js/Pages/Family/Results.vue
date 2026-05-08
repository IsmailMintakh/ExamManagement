<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    UserCircleIcon, ChartBarIcon, CheckCircleIcon, XCircleIcon,
    UserGroupIcon, ArrowRightIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    role: String,
    students: { type: Array, default: () => [] },
    activeStudentId: Number,
    student: Object,
    results: { type: Array, default: () => [] },
    sessions: { type: Array, default: () => [] },
})

const sessionFilter = ref('')

const filtered = computed(() => {
    if (!sessionFilter.value) return props.results
    return props.results.filter(r => Number(r.session_id) === Number(sessionFilter.value))
})

function fmtPct(v) {
    if (v === null || v === undefined) return '-'
    return `${Number(v).toFixed(2)}%`
}

function switchTo(studentId) {
    router.get(route('portal.results'), { student_id: studentId }, { preserveScroll: true })
}
</script>

<template>
    <Head :title="role === 'parent' ? `${student?.name} — Results` : 'My Results'" />
    <AppLayout :breadcrumbs="[
        { label: role === 'parent' ? 'Family Portal' : 'My Account', href: route('portal.dashboard', { student_id: activeStudentId }) },
        { label: 'Results' },
    ]">
        <div class="space-y-5">

            <!-- Child picker -->
            <div v-if="role === 'parent' && students.length > 1"
                class="card bg-base-100 shadow-sm">
                <div class="card-body p-3">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-base-content/55 mb-2 flex items-center gap-1.5">
                        <UserGroupIcon class="h-3.5 w-3.5" /> Viewing
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="child in students" :key="child.id"
                            type="button"
                            @click="switchTo(child.id)"
                            class="flex items-center gap-2 rounded-xl border-2 px-3 py-2 text-left transition-colors"
                            :class="child.id === activeStudentId
                                ? 'border-primary bg-primary/5'
                                : 'border-base-200 hover:border-primary/40 hover:bg-base-200/40'">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-base-200">
                                <img v-if="child.photo_url" :src="child.photo_url" :alt="child.name" class="h-full w-full object-cover" />
                                <UserCircleIcon v-else class="h-5 w-5 text-base-content/40" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold truncate">{{ child.name }}</p>
                                <p class="text-[10px] text-base-content/55">
                                    {{ child.class_name || '—' }}{{ child.section_name ? ' · ' + child.section_name : '' }}
                                </p>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <ChartBarIcon class="h-6 w-6 text-primary" />
                        {{ role === 'parent' ? `${student?.name} — Results` : 'My Results' }}
                    </h1>
                    <p class="text-sm text-base-content/55 mt-1">
                        {{ filtered.length }} result{{ filtered.length === 1 ? '' : 's' }}
                    </p>
                </div>
                <div v-if="sessions.length > 1">
                    <select v-model="sessionFilter" class="select select-bordered select-sm">
                        <option value="">All sessions</option>
                        <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
            </div>

            <div v-if="!filtered.length" class="card bg-base-100 shadow-md">
                <div class="card-body p-10 text-center">
                    <ChartBarIcon class="h-10 w-10 mx-auto text-base-content/30" />
                    <p class="mt-2 text-sm text-base-content/55">No results to show yet.</p>
                </div>
            </div>

            <div v-else class="card bg-base-100 shadow-md">
                <div class="card-body p-0">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>Exam</th>
                                <th>Type</th>
                                <th>Session</th>
                                <th class="text-right">Marks</th>
                                <th class="text-right">%</th>
                                <th class="text-center">Grade</th>
                                <th class="text-center">Position</th>
                                <th class="text-center">Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in filtered" :key="r.id">
                                <td class="font-semibold">{{ r.exam_name }}</td>
                                <td class="text-sm text-base-content/65">{{ r.exam_type || '—' }}</td>
                                <td class="text-sm text-base-content/65">{{ r.session_name || '—' }}</td>
                                <td class="text-right font-mono text-sm">{{ r.obtained_marks }} / {{ r.total_marks }}</td>
                                <td class="text-right font-mono">{{ fmtPct(r.percentage) }}</td>
                                <td class="text-center"><span class="badge badge-sm">{{ r.grade || '—' }}</span></td>
                                <td class="text-center">{{ r.position || '—' }}</td>
                                <td class="text-center">
                                    <CheckCircleIcon v-if="r.is_passed" class="h-5 w-5 text-success inline" />
                                    <XCircleIcon v-else class="h-5 w-5 text-error inline" />
                                </td>
                                <td class="text-right">
                                    <Link :href="route('portal.result-detail', r.id)" class="btn btn-ghost btn-xs gap-1">
                                        View <ArrowRightIcon class="h-3 w-3" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
