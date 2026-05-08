<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    UserCircleIcon, ArrowLeftIcon, EnvelopeIcon, AcademicCapIcon,
    BookOpenIcon, ClipboardDocumentCheckIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    teacher: Object,
    session: String,
    rows: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({}) },
})

const passColor = pct => pct === null ? 'text-base-content/45'
    : pct >= 80 ? 'text-emerald-600' : pct >= 50 ? 'text-amber-600' : 'text-rose-600'
const submissionColor = (entered, total) => {
    if (!total) return 'text-base-content/45'
    const ratio = entered / total
    if (ratio >= 1) return 'text-emerald-600'
    if (ratio >= 0.5) return 'text-amber-600'
    return 'text-rose-600'
}
</script>

<template>
    <Head :title="`Report Card — ${teacher.name}`" />
    <AppLayout :breadcrumbs="[
        { label: 'Reports', href: route('reports.index') },
        { label: teacher.name },
    ]">
        <div class="space-y-5 max-w-5xl mx-auto">

            <!-- Header -->
            <div>
                <Link :href="route('reports.index')" class="btn btn-ghost btn-sm gap-1 mb-2 -ml-2">
                    <ArrowLeftIcon class="w-4 h-4" /> Back to reports
                </Link>
                <div class="flex items-start gap-4">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-sky-500 to-violet-600 text-white grid place-items-center text-2xl font-bold shrink-0">
                        {{ teacher.name?.charAt(0)?.toUpperCase() || '?' }}
                    </div>
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight">{{ teacher.name }}</h1>
                        <p class="text-sm text-base-content/65 mt-1 flex items-center gap-1.5">
                            <EnvelopeIcon class="w-3.5 h-3.5" /> {{ teacher.email }}
                        </p>
                        <p v-if="teacher.school" class="text-xs text-base-content/55 mt-0.5">
                            {{ teacher.school }} · Session {{ session }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- ════════ TOTALS ════════ -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Subjects</p>
                    <p class="text-2xl font-extrabold tabular-nums">{{ totals.subjects || 0 }}</p>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Sections</p>
                    <p class="text-2xl font-extrabold tabular-nums">{{ totals.sections || 0 }}</p>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Students</p>
                    <p class="text-2xl font-extrabold tabular-nums">{{ totals.students || 0 }}</p>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Avg pass rate</p>
                    <p class="text-2xl font-extrabold tabular-nums" :class="passColor(totals.avg_pass_rate)">
                        {{ totals.avg_pass_rate ?? '—' }}%
                    </p>
                </div>
            </div>

            <!-- ════════ ASSIGNMENT BREAKDOWN ════════ -->
            <section class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <header class="px-5 py-3 border-b border-base-300 flex items-center gap-2">
                    <ClipboardDocumentCheckIcon class="w-4 h-4 text-base-content/55" />
                    <h2 class="text-sm font-bold">Subject Assignments</h2>
                    <span class="text-xs text-base-content/45">· {{ rows.length }}</span>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider text-base-content/55">
                            <tr>
                                <th class="text-left px-3 py-2.5 font-bold">Subject</th>
                                <th class="text-left px-3 py-2.5 font-bold">Class · Section</th>
                                <th class="text-center px-3 py-2.5 font-bold">Students</th>
                                <th class="text-center px-3 py-2.5 font-bold">Marks entered</th>
                                <th class="text-center px-3 py-2.5 font-bold">Submitted</th>
                                <th class="text-center px-3 py-2.5 font-bold">Pass rate</th>
                                <th class="text-center px-3 py-2.5 font-bold">Avg %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-300">
                            <tr v-for="r in rows" :key="`${r.subject_id}-${r.section_name}`" class="hover:bg-base-200/30">
                                <td class="px-3 py-2.5 text-xs font-bold">{{ r.subject_name }}</td>
                                <td class="px-3 py-2.5 text-xs">{{ r.class_name }} · {{ r.section_name }}</td>
                                <td class="px-3 py-2.5 text-center text-xs tabular-nums">{{ r.students_taught }}</td>
                                <td class="px-3 py-2.5 text-center text-xs tabular-nums" :class="submissionColor(r.entries, r.students_taught)">
                                    {{ r.entries }} / {{ r.students_taught }}
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <CheckCircleIcon v-if="r.submitted >= r.students_taught && r.students_taught > 0"
                                        class="w-4 h-4 text-emerald-500 mx-auto" />
                                    <span v-else class="text-xs tabular-nums text-base-content/55">
                                        {{ r.submitted }} / {{ r.students_taught }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs font-bold tabular-nums" :class="passColor(r.pass_rate)">
                                    {{ r.pass_rate ?? '—' }}{{ r.pass_rate !== null ? '%' : '' }}
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs tabular-nums">
                                    {{ r.avg_percentage ?? '—' }}{{ r.avg_percentage !== null ? '%' : '' }}
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="7" class="text-center py-10 text-sm text-base-content/55">
                                    This teacher has no active subject assignments in the current session.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
