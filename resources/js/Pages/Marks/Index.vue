<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import EmptyState from '@/Components/EmptyState.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    ClipboardDocumentListIcon, CheckCircleIcon, ClockIcon,
    PencilSquareIcon, EyeIcon, LockClosedIcon, UserGroupIcon,
    ChevronRightIcon, DocumentTextIcon, MagnifyingGlassIcon,
    XCircleIcon, AdjustmentsHorizontalIcon, Squares2X2Icon,
    AcademicCapIcon, UserIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exams: { type: Array, default: () => [] },
    isAdmin: { type: Boolean, default: false },
})

const authUser = computed(() => usePage().props.auth?.user)
const isClassTeacher = computed(() => !!authUser.value?.isClassTeacher)
const teachesSubjects = computed(() => !!authUser.value?.teachesSubjects)

// ─── Filter state ───
const examFilter = ref('')          // exam id (empty = all)
const search = ref('')              // free-text across subject/class/section/teacher
const classFilter = ref('')         // class id
const subjectFilter = ref('')       // subject id
const teacherFilter = ref('')       // teacher id (admin view only)
const statusFilter = ref('all')     // 'all' | 'pending' | 'done'
const groupBy = ref('exam')         // 'exam' | 'class' | 'subject' | 'teacher'

// Helpful — every assignment across all exams in one flat array. Filters
// + grouping operate on this; we re-attach the exam meta to each row.
const allRows = computed(() => {
    const out = []
    for (const e of props.exams) {
        for (const a of (e.assignments || [])) {
            out.push({
                ...a,
                exam_id: e.id,
                exam_name: e.name,
                exam_type: e.exam_type,
                is_locked: e.is_locked,
            })
        }
    }
    return out
})

// Distinct option lists for the filter dropdowns.
function distinct(rows, idKey, nameKey) {
    const seen = new Map()
    for (const r of rows) {
        const id = r[idKey]
        if (id && !seen.has(id)) seen.set(id, { id, name: r[nameKey] })
    }
    return [...seen.values()].sort((a, b) => (a.name || '').localeCompare(b.name || ''))
}
const classOpts = computed(() => [{ value: '', label: 'All classes' },
    ...distinct(allRows.value, 'class_id', 'class_name').map(c => ({ value: c.id, label: c.name }))])
const subjectOpts = computed(() => [{ value: '', label: 'All subjects' },
    ...distinct(allRows.value, 'subject_id', 'subject_name').map(s => ({ value: s.id, label: s.name }))])
const teacherOpts = computed(() => [{ value: '', label: 'All teachers' },
    ...distinct(allRows.value, 'teacher_id', 'teacher_name').map(t => ({ value: t.id, label: t.name }))])
const examOpts = computed(() => [{ value: '', label: 'All exams' },
    ...props.exams.map(e => ({ value: e.id, label: e.name, sublabel: e.exam_type }))])

const isDone = (s) => s === 'submitted' || s === 'verified'
const isPending = (s) => !s || s === 'draft' || s === 'pending'

// Apply search + filters to the flat row list.
const filteredRows = computed(() => {
    const q = search.value.trim().toLowerCase()
    return allRows.value.filter(r => {
        if (examFilter.value && r.exam_id !== Number(examFilter.value)) return false
        if (classFilter.value && r.class_id !== Number(classFilter.value)) return false
        if (subjectFilter.value && r.subject_id !== Number(subjectFilter.value)) return false
        if (teacherFilter.value && Number(r.teacher_id) !== Number(teacherFilter.value)) return false
        if (statusFilter.value === 'done' && !isDone(r.status)) return false
        if (statusFilter.value === 'pending' && !isPending(r.status)) return false
        if (q) {
            const hay = `${r.subject_name} ${r.class_name} ${r.section_name} ${r.teacher_name || ''} ${r.exam_name}`.toLowerCase()
            if (!hay.includes(q)) return false
        }
        return true
    })
})

// Group the filtered rows by the chosen axis. Returns [{ label, rows: [] }].
const grouped = computed(() => {
    const map = new Map()
    const labelFor = (r) => ({
        exam:    r.exam_name,
        class:   r.class_name,
        subject: r.subject_name,
        teacher: r.teacher_name || '— Unassigned —',
    }[groupBy.value])

    for (const r of filteredRows.value) {
        const label = labelFor(r) || '—'
        if (!map.has(label)) map.set(label, [])
        map.get(label).push(r)
    }
    return [...map.entries()]
        .sort((a, b) => a[0].localeCompare(b[0]))
        .map(([label, rows]) => {
            const done = rows.filter(r => isDone(r.status)).length
            return { label, rows, done, total: rows.length, pct: rows.length ? Math.round((done / rows.length) * 100) : 0 }
        })
})

// Aggregate stats for the strip at the top.
const stats = computed(() => {
    const all = filteredRows.value
    const done = all.filter(r => isDone(r.status)).length
    const pending = all.filter(r => isPending(r.status)).length
    return { total: all.length, done, pending, pct: all.length ? Math.round((done / all.length) * 100) : 0 }
})

function clearFilters() {
    examFilter.value = ''
    classFilter.value = ''
    subjectFilter.value = ''
    teacherFilter.value = ''
    statusFilter.value = 'all'
    search.value = ''
}

const statusConfig = {
    verified:  { label: 'Verified',  class: 'badge-info',    dot: 'bg-sky-500',     icon: CheckCircleIcon },
    submitted: { label: 'Submitted', class: 'badge-success', dot: 'bg-emerald-500', icon: CheckCircleIcon },
    draft:     { label: 'In progress', class: 'badge-warning', dot: 'bg-amber-500',  icon: PencilSquareIcon },
    pending:   { label: 'Pending',   class: 'badge-ghost', dot: 'bg-base-content/30', icon: ClockIcon },
}
function getStatus(s) { return statusConfig[s] || statusConfig.pending }
function getLockedBadge(row) {
    return row.is_locked ? { label: 'Locked', class: 'badge-error' } : null
}
</script>

<template>
    <Head title="Marks Entry" />
    <AppLayout :breadcrumbs="[{ label: 'Marks Entry' }]">
        <div class="space-y-4 max-w-[1500px] mx-auto">
            <PageHeader title="Marks entry"
                :subtitle="isAdmin
                    ? 'Every (subject × class × section) cell — search, filter and group to find what needs entering.'
                    : 'Only the subjects assigned to you. Pick a row to enter that section\'s marks.'"
                :icon="DocumentTextIcon" tone="primary">
                <template #actions>
                    <Link v-if="isClassTeacher" href="/my-class"
                        class="btn btn-outline btn-sm rounded-lg gap-1.5">
                        <UserGroupIcon class="w-4 h-4" /> My Class
                    </Link>
                    <Link v-if="isAdmin" :href="route('marks.progress')"
                        class="btn btn-primary btn-sm rounded-lg gap-1.5">
                        <ClipboardDocumentListIcon class="w-4 h-4" /> Progress tracker
                    </Link>
                </template>
            </PageHeader>

            <!-- Stat strip -->
            <div v-if="allRows.length" class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <div class="rounded-xl border border-base-300 bg-base-100 px-3 py-2.5">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Showing</p>
                    <p class="text-2xl font-extrabold tabular-nums">{{ stats.total }}</p>
                </div>
                <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/5 px-3 py-2.5">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-emerald-700 dark:text-emerald-300">Done</p>
                    <p class="text-2xl font-extrabold tabular-nums">{{ stats.done }}</p>
                </div>
                <div class="rounded-xl border border-amber-500/30 bg-amber-500/5 px-3 py-2.5">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-amber-700 dark:text-amber-300">Pending</p>
                    <p class="text-2xl font-extrabold tabular-nums">{{ stats.pending }}</p>
                </div>
                <div class="rounded-xl border border-base-300 bg-base-100 px-3 py-2.5">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Progress</p>
                    <p class="text-2xl font-extrabold tabular-nums">{{ stats.pct }}%</p>
                    <div class="h-1.5 bg-base-200 rounded-full mt-1 overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full transition-all" :style="{ width: stats.pct + '%' }"></div>
                    </div>
                </div>
            </div>

            <!-- Search + Filter strip -->
            <div v-if="allRows.length" class="rounded-2xl border border-base-300 bg-base-100 p-3 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                    <div class="lg:col-span-2 relative">
                        <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/40" />
                        <input v-model="search" type="text" placeholder="Search subject, class, section, teacher…"
                            class="input input-bordered input-sm w-full pl-9 text-sm" />
                    </div>
                    <SearchableSelect v-model="examFilter" :options="examOpts" placeholder="All exams" size="sm" :clearable="true" />
                    <SearchableSelect v-model="classFilter" :options="classOpts" placeholder="All classes" size="sm" :clearable="true" />
                    <SearchableSelect v-model="subjectFilter" :options="subjectOpts" placeholder="All subjects" size="sm" :clearable="true" />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <SearchableSelect v-if="isAdmin" v-model="teacherFilter" :options="teacherOpts"
                        placeholder="All teachers" size="sm" :clearable="true"
                        class="w-full sm:w-64" />

                    <!-- Status pills -->
                    <div class="flex items-center gap-1 rounded-xl border border-base-300 bg-base-200/40 p-1 text-xs">
                        <button @click="statusFilter = 'all'" class="rounded-lg px-3 py-1.5 font-bold transition-colors"
                            :class="statusFilter === 'all' ? 'bg-base-100 shadow-sm' : 'text-base-content/55 hover:text-base-content'">
                            All
                        </button>
                        <button @click="statusFilter = 'pending'" class="rounded-lg px-3 py-1.5 font-bold transition-colors"
                            :class="statusFilter === 'pending' ? 'bg-amber-500 text-white' : 'text-base-content/55 hover:text-base-content'">
                            Pending
                        </button>
                        <button @click="statusFilter = 'done'" class="rounded-lg px-3 py-1.5 font-bold transition-colors"
                            :class="statusFilter === 'done' ? 'bg-emerald-500 text-white' : 'text-base-content/55 hover:text-base-content'">
                            Done
                        </button>
                    </div>

                    <!-- Group by -->
                    <div class="flex items-center gap-1 rounded-xl border border-base-300 bg-base-200/40 p-1 text-xs ml-auto">
                        <span class="text-[10px] uppercase tracking-wider font-bold text-base-content/45 ml-1">Group by:</span>
                        <button v-for="g in [
                            { k: 'exam', label: 'Exam', icon: ClipboardDocumentListIcon },
                            { k: 'class', label: 'Class', icon: AcademicCapIcon },
                            { k: 'subject', label: 'Subject', icon: DocumentTextIcon },
                            ...(isAdmin ? [{ k: 'teacher', label: 'Teacher', icon: UserIcon }] : []),
                        ]" :key="g.k"
                            @click="groupBy = g.k"
                            class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 font-bold transition-colors"
                            :class="groupBy === g.k ? 'bg-primary text-primary-content' : 'text-base-content/60 hover:text-base-content'">
                            <component :is="g.icon" class="w-3.5 h-3.5" />
                            {{ g.label }}
                        </button>
                    </div>

                    <button v-if="search || examFilter || classFilter || subjectFilter || teacherFilter || statusFilter !== 'all'"
                        @click="clearFilters"
                        class="btn btn-ghost btn-xs gap-1 ml-auto">
                        <XCircleIcon class="w-3.5 h-3.5" /> Clear
                    </button>
                </div>
            </div>

            <!-- Grouped result list -->
            <div v-if="filteredRows.length" class="space-y-3">
                <div v-for="g in grouped" :key="g.label" class="card-section overflow-hidden">
                    <header class="card-header">
                        <div class="flex items-center gap-3 w-full">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                <Squares2X2Icon class="w-4 h-4 text-primary" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-sm truncate">{{ g.label }}</h3>
                                <p class="text-[11px] text-base-content/55 tabular-nums">
                                    <span class="font-bold text-emerald-700 dark:text-emerald-300">{{ g.done }}</span>
                                    <span class="text-base-content/45"> / {{ g.total }} done</span>
                                    · {{ g.pct }}%
                                </p>
                            </div>
                            <div class="w-24 shrink-0">
                                <div class="h-1.5 bg-base-200 rounded-full overflow-hidden">
                                    <div class="h-full transition-all"
                                        :class="g.pct === 100 ? 'bg-emerald-500' : g.pct > 0 ? 'bg-amber-500' : 'bg-rose-500/50'"
                                        :style="{ width: g.pct + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </header>

                    <div class="divide-y divide-base-200">
                        <component
                            v-for="r in g.rows" :key="r.exam_id + '-' + r.id"
                            :is="!r.is_locked ? Link : 'div'"
                            :href="!r.is_locked ? route('marks.entry', [r.exam_id, r.subject_id, r.section_id]) : null"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-base-200/30 active:bg-base-200/40 transition-colors"
                        >
                            <div class="relative shrink-0">
                                <div class="w-10 h-10 rounded-xl bg-base-200 flex items-center justify-center">
                                    <component :is="getStatus(r.status).icon" class="w-5 h-5 text-base-content/60" />
                                </div>
                                <span class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full ring-2 ring-base-100"
                                      :class="getStatus(r.status).dot"></span>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-sm truncate">{{ r.subject_name }}</span>
                                    <span v-if="groupBy !== 'class'" class="text-xs text-base-content/55">· {{ r.class_name }} – {{ r.section_name }}</span>
                                    <span v-else class="text-xs text-base-content/55">· Sec {{ r.section_name }}</span>
                                </div>
                                <div class="flex items-center gap-2 mt-0.5 text-xs text-base-content/55 flex-wrap">
                                    <span v-if="isAdmin && r.teacher_name" class="inline-flex items-center gap-1">
                                        <UserIcon class="w-3 h-3" /> {{ r.teacher_name }}
                                    </span>
                                    <span v-if="isAdmin && !r.teacher_name" class="text-rose-600 dark:text-rose-300 font-semibold">No teacher assigned</span>
                                    <span class="inline-flex items-center gap-1">
                                        <UserGroupIcon class="w-3 h-3" />
                                        <span class="tabular-nums">{{ r.student_count }}</span>
                                    </span>
                                    <span v-if="groupBy !== 'exam'" class="text-base-content/40 truncate">· {{ r.exam_name }}</span>
                                </div>
                            </div>

                            <span class="badge badge-sm gap-1 shrink-0" :class="getStatus(r.status).class">
                                {{ getStatus(r.status).label }}
                            </span>
                            <span v-if="getLockedBadge(r)" class="badge badge-sm gap-1 shrink-0" :class="getLockedBadge(r).class">
                                <LockClosedIcon class="w-3 h-3" /> {{ getLockedBadge(r).label }}
                            </span>
                            <LockClosedIcon v-if="r.is_locked" class="w-4 h-4 text-base-content/30 shrink-0" />
                            <ChevronRightIcon v-else class="w-4 h-4 text-base-content/30 shrink-0" />
                        </component>
                    </div>
                </div>
            </div>

            <!-- Filtered empty -->
            <div v-else-if="allRows.length" class="card-section p-10 text-center">
                <AdjustmentsHorizontalIcon class="w-10 h-10 text-base-content/25 mx-auto mb-2" />
                <p class="text-sm font-medium">No rows match the current filters.</p>
                <button @click="clearFilters" class="btn btn-ghost btn-sm mt-2">Clear filters</button>
            </div>

            <!-- Nothing at all -->
            <div v-else class="card-section">
                <EmptyState v-if="isClassTeacher && !teachesSubjects"
                    title="No subjects assigned to you"
                    description="Marks entry is only for subjects you are assigned to teach. As a class teacher you monitor your section's marks progress (read-only) from My Class."
                    action-text="Go to My Class" action-href="/my-class" />
                <EmptyState v-else
                    title="No active exams"
                    description="No exams are open for marks entry right now. They'll appear here once published and marks entry is opened." />
            </div>
        </div>
    </AppLayout>
</template>
