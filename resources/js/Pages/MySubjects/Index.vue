<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    BookOpenIcon, AcademicCapIcon, UserGroupIcon, ClipboardDocumentCheckIcon,
    MagnifyingGlassIcon, ArrowRightIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    rows: { type: Array, default: () => [] },
    currentSession: { type: Object, default: null },
    totals: { type: Object, default: () => ({}) },
})

const search = ref('')

// Group flat rows by subject so the page reads as "what do I teach", and each
// subject expands to its (class, section) cards. A teacher who teaches Math
// in three sections sees one Math card with three sub-rows, not three top-level
// rows that scan as separate subjects.
const grouped = computed(() => {
    const q = search.value.trim().toLowerCase()
    const filtered = !q
        ? props.rows
        : props.rows.filter(r =>
            (r.subject_name || '').toLowerCase().includes(q) ||
            (r.class_name || '').toLowerCase().includes(q) ||
            (r.section_name || '').toLowerCase().includes(q))
    const map = new Map()
    for (const r of filtered) {
        const key = r.subject_id
        if (!map.has(key)) {
            map.set(key, {
                subject_id: r.subject_id,
                subject_name: r.subject_name,
                subject_code: r.subject_code,
                is_main_subject: r.is_main_subject,
                placements: [],
            })
        }
        map.get(key).placements.push(r)
    }
    return Array.from(map.values()).sort((a, b) =>
        (a.subject_name || '').localeCompare(b.subject_name || ''))
})
</script>

<template>
    <Head title="My Subjects" />
    <AppLayout :breadcrumbs="[{ label: 'My Subjects' }]">
        <div class="space-y-4 max-w-[1400px] mx-auto">
            <PageHeader
                title="My Subjects"
                :subtitle="`${currentSession?.name ?? 'Current session'} · subjects + sections assigned to you`"
                :icon="BookOpenIcon"
                tone="violet" />

            <!-- ════════ KPI strip ════════ -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white flex items-center justify-center shadow-md shadow-violet-500/15 shrink-0">
                        <BookOpenIcon class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Subjects</p>
                        <p class="text-xl font-extrabold tabular-nums">{{ totals.distinct_subjects || 0 }}</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center shadow-md shadow-emerald-500/15 shrink-0">
                        <AcademicCapIcon class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Sections</p>
                        <p class="text-xl font-extrabold tabular-nums">{{ totals.distinct_sections || 0 }}</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-500 to-indigo-600 text-white flex items-center justify-center shadow-md shadow-sky-500/15 shrink-0">
                        <UserGroupIcon class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Students</p>
                        <p class="text-xl font-extrabold tabular-nums">{{ totals.total_students || 0 }}</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white flex items-center justify-center shadow-md shadow-amber-500/15 shrink-0">
                        <ClipboardDocumentCheckIcon class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wider text-base-content/55 font-bold">Assignments</p>
                        <p class="text-xl font-extrabold tabular-nums">{{ totals.assignments || 0 }}</p>
                    </div>
                </div>
            </div>

            <!-- ════════ Search ════════ -->
            <div class="surface px-3 py-2.5 flex items-center gap-2">
                <MagnifyingGlassIcon class="w-4 h-4 text-base-content/45 shrink-0" />
                <input v-model="search" placeholder="Search by subject, class, or section…"
                       class="bg-transparent outline-none flex-1 text-sm" />
            </div>

            <!-- ════════ Subject cards ════════ -->
            <EmptyState v-if="!grouped.length"
                :icon="BookOpenIcon"
                title="No subject assignments yet"
                description="Ask your school administrator to assign you subjects from Academic → Teacher Assignments. They'll appear here grouped by subject once added." />

            <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                <section v-for="group in grouped" :key="group.subject_id"
                         class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                    <header class="px-4 py-3 flex items-center gap-3 border-b border-base-200 bg-gradient-to-r from-violet-500/5 to-fuchsia-500/5">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white font-bold text-sm flex items-center justify-center shrink-0">
                            {{ (group.subject_code || group.subject_name || '?').substring(0, 2).toUpperCase() }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <h2 class="font-bold text-sm truncate">{{ group.subject_name }}</h2>
                            <p class="text-[11px] text-base-content/55">
                                {{ group.subject_code || '—' }}
                                <span v-if="group.is_main_subject" class="ml-1 px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-700 dark:text-amber-300 text-[9px] font-bold">MAIN</span>
                            </p>
                        </div>
                        <span class="text-xs text-base-content/55 font-medium">{{ group.placements.length }} section{{ group.placements.length === 1 ? '' : 's' }}</span>
                    </header>

                    <div class="divide-y divide-base-200">
                        <div v-for="p in group.placements" :key="p.id"
                             class="px-4 py-3 flex items-center gap-3 hover:bg-base-200/40 transition-colors">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold">{{ p.class_name }} <span class="text-base-content/55 font-medium">·</span> {{ p.section_name }}</p>
                                <p class="text-[11px] text-base-content/55 mt-0.5 flex items-center gap-2">
                                    <span class="inline-flex items-center gap-1">
                                        <UserGroupIcon class="w-3 h-3" /> {{ p.student_count }} students
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <ClipboardDocumentCheckIcon class="w-3 h-3" />
                                        {{ p.marks_entered }} marks entered
                                    </span>
                                </p>
                            </div>
                            <Link :href="`/marks?subject=${p.subject_id}&section=${p.section_id}`"
                                  class="btn btn-ghost btn-xs gap-1 text-violet-600 dark:text-violet-400">
                                Open
                                <ArrowRightIcon class="w-3 h-3" />
                            </Link>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
