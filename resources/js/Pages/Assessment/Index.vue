<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import {
    CheckBadgeIcon, UserGroupIcon, ClipboardDocumentCheckIcon,
    InformationCircleIcon, CheckCircleIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    sections: { type: Array, default: () => [] },
    activeSection: { type: Object, default: null },
    students: { type: Array, default: () => [] },
    existing: { type: Object, default: () => ({}) },
    currentSession: { type: Object, default: null },
    config: { type: Object, default: () => ({ total: 10, passing: 4 }) },
})

const sectionId = ref(props.activeSection?.id ?? null)
watch(sectionId, (id) => {
    if (id && Number(id) !== Number(props.activeSection?.id)) {
        router.get(route('assessment.index'), { section: id }, {
            preserveState: false, preserveScroll: true,
        })
    }
})

// Each row carries the live marks_obtained + remarks, seeded from existing
// rows (re-entry mode) or blank (first-time). Class teachers can edit any
// row at any time — there's no submit/lock step because assessment is a
// living evaluation up until the Annual Result generation.
const form = useForm({
    section_id: props.activeSection?.id ?? null,
    marks: props.students.map(s => {
        const e = props.existing?.[s.id]
        return {
            student_id: s.id,
            marks_obtained: e?.marks_obtained != null ? String(e.marks_obtained) : '',
            remarks: e?.remarks ?? '',
        }
    }),
})

const stats = computed(() => {
    let entered = 0, passed = 0, failed = 0
    for (const r of form.marks) {
        const v = parseFloat(r.marks_obtained)
        if (Number.isFinite(v)) {
            entered++
            if (v >= props.config.passing) passed++
            else failed++
        }
    }
    return {
        total: form.marks.length,
        entered,
        remaining: form.marks.length - entered,
        passed,
        failed,
    }
})

function rowError(row) {
    if (row.marks_obtained === '' || row.marks_obtained == null) return null
    const v = parseFloat(String(row.marks_obtained).replace(',', '.'))
    if (!Number.isFinite(v)) return 'Not a number'
    if (v < 0) return 'Negative'
    if (v > props.config.total) return `> ${props.config.total}`
    return null
}
const hasErrors = computed(() => form.marks.some(r => rowError(r) !== null))

function save() {
    form.transform(d => ({
        section_id: sectionId.value,
        marks: d.marks
            .filter(r => r.marks_obtained !== '' && r.marks_obtained != null)
            .map(r => ({
                student_id: r.student_id,
                marks_obtained: parseFloat(String(r.marks_obtained).replace(',', '.')),
                remarks: r.remarks || null,
            })),
    })).post(route('assessment.store'), {
        preserveScroll: true,
        preserveState: true,
    })
}

const rowsWithMeta = computed(() =>
    form.marks.map((row, idx) => {
        const v = parseFloat(row.marks_obtained)
        const hasMarks = Number.isFinite(v)
        return {
            row, idx, error: rowError(row),
            student: props.students[idx],
            passed: hasMarks ? v >= props.config.passing : null,
            hasMarks,
        }
    })
)
</script>

<template>
    <Head title="Assessment Marks" />
    <AppLayout :breadcrumbs="[{ label: 'Assessment Marks' }]">
        <div class="space-y-4 max-w-[1400px] mx-auto">
            <PageHeader title="Primary Assessment"
                :subtitle="`Overall ${config.total}-mark conduct &amp; participation score · pass at ${config.passing} · ${currentSession?.name ?? 'No active session'}`"
                :icon="CheckBadgeIcon" tone="emerald">
                <template #actions>
                    <select v-if="sections.length > 1" v-model="sectionId"
                        class="select select-bordered select-sm rounded-lg text-sm">
                        <option v-for="s in sections" :key="s.id" :value="s.id">
                            {{ s.class_name }} · {{ s.name }}
                        </option>
                    </select>
                </template>
            </PageHeader>

            <!-- Empty state — no primary sections assigned -->
            <EmptyState v-if="!sections.length"
                :icon="CheckBadgeIcon"
                title="No primary section assigned"
                description="Assessment marks are only entered by the class teacher of an ECD–5 section. If you should have access here, ask your administrator to set you as the class teacher of a primary section." />

            <template v-else-if="activeSection">
                <!-- Info banner — explains how Assessment feeds the Annual Result -->
                <div class="rounded-2xl border border-sky-500/30 bg-sky-500/5 p-3 sm:p-4 flex items-start gap-3 text-sm">
                    <InformationCircleIcon class="w-5 h-5 text-sky-600 dark:text-sky-400 mt-0.5 shrink-0" />
                    <div class="text-sky-900 dark:text-sky-200 leading-relaxed">
                        <span class="font-bold">Overall assessment</span> — score each student
                        out of <b>{{ config.total }}</b> based on overall behaviour, participation,
                        attendance, discipline and classroom activities.
                        <b>{{ config.passing }}</b> or higher is a pass.
                        A student who scores below <b>{{ config.passing }}</b> is declared
                        <b>Fail</b> in the Annual Result even if every subject is passed.
                    </div>
                </div>

                <!-- KPI strip -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3">
                    <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-500 to-indigo-600 text-white flex items-center justify-center shadow-md shadow-sky-500/15 shrink-0">
                            <UserGroupIcon class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Students</p>
                            <p class="text-xl font-extrabold tabular-nums">{{ stats.total }}</p>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center shadow-md shadow-emerald-500/15 shrink-0">
                            <ClipboardDocumentCheckIcon class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-wider font-bold text-emerald-700 dark:text-emerald-300">Entered</p>
                            <p class="text-xl font-extrabold tabular-nums">{{ stats.entered }}</p>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white flex items-center justify-center shadow-md shadow-amber-500/15 shrink-0">
                            <ExclamationTriangleIcon class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-wider font-bold text-amber-700 dark:text-amber-300">Remaining</p>
                            <p class="text-xl font-extrabold tabular-nums">{{ stats.remaining }}</p>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-500 to-pink-600 text-white flex items-center justify-center shadow-md shadow-rose-500/15 shrink-0">
                            <ExclamationTriangleIcon class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-wider font-bold text-rose-700 dark:text-rose-300">Below pass</p>
                            <p class="text-xl font-extrabold tabular-nums">{{ stats.failed }}</p>
                        </div>
                    </div>
                </div>

                <!-- Entry table -->
                <section class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                    <header class="px-4 py-3 border-b border-base-200 flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                            <CheckBadgeIcon class="w-4 h-4" />
                        </div>
                        <h2 class="text-sm font-bold truncate">
                            {{ activeSection.class_name }} · {{ activeSection.name }}
                        </h2>
                        <span class="text-[11px] text-base-content/55">· {{ stats.total }} students</span>
                    </header>

                    <div v-if="!students.length" class="p-10 text-center">
                        <UserGroupIcon class="w-10 h-10 text-base-content/25 mx-auto mb-2" />
                        <p class="text-sm font-medium">No active students in this section.</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-base-200/40 text-[11px] uppercase tracking-wider text-base-content/55">
                                <tr>
                                    <th class="text-left px-4 py-3 font-bold">#</th>
                                    <th class="text-left px-4 py-3 font-bold">Student</th>
                                    <th class="text-left px-3 py-3 font-bold">Roll</th>
                                    <th class="text-right px-3 py-3 font-bold w-32">Marks / {{ config.total }}</th>
                                    <th class="text-center px-3 py-3 font-bold w-20">Status</th>
                                    <th class="text-left px-3 py-3 font-bold">Remarks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-base-200">
                                <tr v-for="m in rowsWithMeta" :key="m.student.id">
                                    <td class="px-4 py-2.5 text-xs font-mono text-base-content/55 tabular-nums">{{ m.idx + 1 }}</td>
                                    <td class="px-4 py-2.5">
                                        <p class="font-bold text-sm truncate">{{ m.student.name }}</p>
                                        <p v-if="m.student.father_name" class="text-[10px] text-base-content/55 truncate">S/o {{ m.student.father_name }}</p>
                                    </td>
                                    <td class="px-3 py-2.5 text-[12px] text-base-content/75 tabular-nums">{{ m.student.roll_no || '—' }}</td>
                                    <td class="px-3 py-2.5">
                                        <input v-model="m.row.marks_obtained" type="text" inputmode="decimal"
                                            :placeholder="`0–${config.total}`"
                                            class="input input-bordered input-xs rounded-lg w-full text-right font-mono"
                                            :class="m.error ? 'border-rose-500/60' : ''" />
                                        <p v-if="m.error" class="text-[10px] text-rose-600 mt-0.5">{{ m.error }}</p>
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <span v-if="m.passed === true" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-500/15 text-emerald-700 dark:text-emerald-300">
                                            <CheckCircleIcon class="w-3 h-3" /> Pass
                                        </span>
                                        <span v-else-if="m.passed === false" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-rose-500/15 text-rose-700 dark:text-rose-300">
                                            <ExclamationTriangleIcon class="w-3 h-3" /> Fail
                                        </span>
                                        <span v-else class="text-[10px] text-base-content/45 uppercase tracking-wider font-bold">—</span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <input v-model="m.row.remarks" type="text"
                                            placeholder="Optional…"
                                            class="input input-bordered input-xs rounded-lg w-full" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Sticky save bar -->
                <div class="sticky bottom-4 rounded-2xl border border-base-200 bg-base-100/95 backdrop-blur-xl shadow-xl p-3 z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-xs text-base-content/65">
                        <span v-if="stats.remaining">
                            {{ stats.remaining }} student{{ stats.remaining === 1 ? '' : 's' }} still to score.
                        </span>
                        <span v-else>All students have been scored.</span>
                        <span v-if="hasErrors" class="ml-1 text-rose-600 dark:text-rose-300 font-semibold">Fix highlighted rows first.</span>
                    </p>
                    <button type="button" @click="save"
                        :disabled="form.processing || hasErrors || !stats.entered"
                        class="btn btn-primary btn-sm gap-1.5 rounded-xl shrink-0">
                        <CheckCircleIcon class="w-4 h-4" />
                        {{ form.processing ? 'Saving…' : 'Save Assessment Marks' }}
                    </button>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
