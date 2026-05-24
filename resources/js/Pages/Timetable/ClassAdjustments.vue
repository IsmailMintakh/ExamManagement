<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import PageHeader from '@/Components/PageHeader.vue'
import TimetableSubnav from '@/Components/timetable/TimetableSubnav.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    ArrowsRightLeftIcon, BoltIcon,
    CheckCircleIcon, XCircleIcon, PrinterIcon, ExclamationTriangleIcon,
    UserMinusIcon, UserPlusIcon, ClockIcon, PencilSquareIcon,
    QuestionMarkCircleIcon,
} from '@heroicons/vue/24/outline'
import { confirmAction } from '@/lib/swal'

const props = defineProps({
    fairness: { type: Object, default: () => ({ rows: [], avg: 0, total: 0, month_label: '' }) },
    date: String,
    today: String,
    teachers: { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
    todaySlots: { type: Array, default: () => [] }, // school-wide period list (fallback)
    stageLabels: { type: Object, default: () => ({}) },
    coverStagePolicy: { type: String, default: 'strict' }, // 'strict' | 'open'
})

const dateInput = ref(props.date)

function changeDate(newDate) {
    router.get(route('timetable.adjustments'), { date: newDate }, { preserveScroll: true })
}

// ─── Cover policy toggle ───
async function setPolicy(p) {
    if (p === props.coverStagePolicy) return
    const ok = await confirmAction({
        title: p === 'strict' ? 'Switch to strict policy?' : 'Switch to open policy?',
        text: p === 'strict'
            ? 'Substitutes will be matched within the same stage only (e.g. Primary teachers stay with Primary classes).'
            : 'Any qualified colleague can cover any stage (cluster-school mode).',
        confirmText: 'Yes, switch',
    })
    if (!ok) return
    router.post(route('timetable.adjustments.policy'),
        { policy: p, date: props.date },
        { preserveScroll: true })
}

// ─── Absence modal ───
// mode: 'full'    → entire day
//       'partial' → "left at HH:MM" (every period from that time on)
//       'periods' → tick exact periods to cover (handles "left for P3-4
//                   then came back for P5"). slotIds[] holds the picks.
const absenceModal = ref(null) // null | { teacher, mode, fromTime, slotIds, reason, isEdit }

// Picker shows the slots the teacher actually teaches (e.g. Primary's 6
// periods for a Primary teacher); falls back to today's general slot list
// so the picker is never empty.
function slotsForAbsence(teacher) {
    const own = Array.isArray(teacher?.slots) && teacher.slots.length ? teacher.slots : null
    return own || props.todaySlots || []
}

function openMarkAbsent(teacher) {
    const slots = slotsForAbsence(teacher)
    absenceModal.value = {
        teacher,
        mode: 'full',
        fromTime: slots[0]?.starts_at || '',
        slotIds: [],
        reason: '',
        slots,
        isEdit: false,
    }
}
function openEditAbsence(teacher) {
    const presetSlots = Array.isArray(teacher.absent_slot_ids) ? teacher.absent_slot_ids.map(Number) : []
    const mode = presetSlots.length > 0 ? 'periods' : (teacher.from_time ? 'partial' : 'full')
    const slots = slotsForAbsence(teacher)
    absenceModal.value = {
        teacher,
        mode,
        fromTime: teacher.from_time || (slots[0]?.starts_at || ''),
        slotIds: presetSlots,
        reason: teacher.reason || '',
        slots,
        isEdit: true,
    }
}
function closeAbsenceModal() { absenceModal.value = null }

function toggleSlotForAbsence(slotId) {
    const m = absenceModal.value
    if (!m) return
    const i = m.slotIds.indexOf(slotId)
    if (i > -1) m.slotIds.splice(i, 1)
    else m.slotIds.push(slotId)
}

function submitAbsence() {
    const m = absenceModal.value
    if (!m) return
    if (m.mode === 'periods' && !m.slotIds.length) {
        alert('Tick at least one period the teacher will miss.')
        return
    }
    router.post(route('timetable.adjustments.absence'), {
        user_id: m.teacher.id,
        date: props.date,
        reason: m.reason || null,
        from_time: m.mode === 'partial' ? m.fromTime : null,
        absent_slot_ids: m.mode === 'periods' ? m.slotIds : null,
        action: m.isEdit ? 'update' : 'toggle',
    }, {
        preserveScroll: true,
        onSuccess: closeAbsenceModal,
    })
}

// Quick mark-present (toggle off without modal)
async function markPresent(teacher) {
    const ok = await confirmAction({
        title: `Mark ${teacher.name} as present?`,
        text: 'Any suggested covers for them today will be cleared.',
        confirmText: 'Yes, mark present',
    })
    if (!ok) return
    router.post(route('timetable.adjustments.absence'), {
        user_id: teacher.id,
        date: props.date,
    }, { preserveScroll: true })
}

function generate() {
    router.post(route('timetable.adjustments.generate'), { date: props.date }, { preserveScroll: true })
}

function confirmA(a) {
    router.post(route('timetable.adjustments.confirm', a.id), {}, { preserveScroll: true })
}
function declineA(a) {
    router.post(route('timetable.adjustments.decline', a.id), {}, { preserveScroll: true })
}

// Score-trace popover — shows admin "why this teacher was picked"
const scoreTrace = ref(null)
function openScore(a) { scoreTrace.value = a }
function closeScore() { scoreTrace.value = null }

// Reassignment modal
const reassignTarget = ref(null)
const reassignTo = ref(null)
function openReassign(a) {
    reassignTarget.value = a
    reassignTo.value = a.substitute_teacher_id
}
function closeReassign() {
    reassignTarget.value = null
    reassignTo.value = null
}
function submitReassign() {
    if (!reassignTarget.value || !reassignTo.value) return
    router.post(route('timetable.adjustments.reassign', reassignTarget.value.id),
        { substitute_teacher_id: reassignTo.value },
        { preserveScroll: true, onSuccess: closeReassign })
}

// Counts
const absentFullDay = computed(() => props.teachers.filter(t => t.absent && !t.from_time).length)
const absentPartial = computed(() => props.teachers.filter(t => t.absent && t.from_time).length)
const absentCount = computed(() => absentFullDay.value + absentPartial.value)
const stats = computed(() => ({
    total: props.assignments.length,
    confirmed: props.assignments.filter(a => a.status === 'confirmed').length,
    suggested: props.assignments.filter(a => a.status === 'suggested').length,
    declined: props.assignments.filter(a => a.status === 'declined').length,
}))

const assignmentsBySubstitute = computed(() => {
    const map = {}
    for (const a of props.assignments) {
        if (a.status === 'declined') continue
        const key = a.substitute_teacher
        if (!map[key]) map[key] = []
        map[key].push(a)
    }
    return Object.entries(map)
        .map(([name, rows]) => ({ name, count: rows.length, rows }))
        .sort((a, b) => b.count - a.count)
})

// Validation errors from confirm / reassign / decline guards.
const formError = computed(() => usePage().props.errors?.adjustment)

// Fairness UI — highlight rows that are ≥150% of the average, so an admin
// instantly spots an overloaded teacher.
const showFairness = ref(false)
function fairnessClass(row) {
    if (!props.fairness?.avg) return ''
    if (row.covers_taken === 0) return 'text-base-content/40'
    if (row.covers_taken >= props.fairness.avg * 1.5) return 'text-rose-700 dark:text-rose-300 font-bold'
    if (row.covers_taken >= props.fairness.avg) return 'text-amber-700 dark:text-amber-300 font-semibold'
    return 'text-emerald-700 dark:text-emerald-300'
}

function statusPill(status) {
    return {
        suggested: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 ring-amber-500/30',
        confirmed: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-500/30',
        declined: 'bg-rose-500/15 text-rose-700 dark:text-rose-300 ring-rose-500/30',
    }[status] || 'bg-base-content/10 text-base-content/65 ring-base-content/15'
}
</script>

<template>
    <Head title="Class Adjustments" />
    <AppLayout :breadcrumbs="[
        { label: 'Timetable', href: route('timetable.index') },
        { label: 'Class Adjustments' },
    ]">
        <div class="space-y-3 max-w-[1500px] mx-auto">

            <PageHeader title="Class adjustments"
                subtitle="Mark absences (full or partial) and auto-cover empty periods fairly"
                :icon="ArrowsRightLeftIcon" tone="amber">
                <template #actions>
                    <input v-model="dateInput" @change="changeDate(dateInput)" type="date"
                        class="input input-bordered input-sm rounded-lg text-sm font-mono" />
                    <a :href="route('timetable.adjustments.slip', { date })" target="_blank"
                        class="btn btn-outline btn-sm rounded-lg gap-1.5">
                        <PrinterIcon class="w-4 h-4" /> Print slip
                    </a>
                </template>
            </PageHeader>

            <TimetableSubnav />

            <!-- Cover policy toggle — strict vs open -->
            <div class="rounded-2xl border border-base-300 bg-base-100 p-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-xs text-base-content/70 flex-1">
                    <p class="font-bold text-sm text-base-content">Who can cover for whom?</p>
                    <p class="mt-0.5 leading-snug">
                        <strong v-if="coverStagePolicy === 'strict'" class="text-rose-700 dark:text-rose-300">Strict:</strong>
                        <strong v-else class="text-emerald-700 dark:text-emerald-300">Open:</strong>
                        {{ coverStagePolicy === 'strict'
                            ? 'Pre-Primary / Primary teachers will NOT be picked to cover Middle, Secondary or Higher Secondary classes (and vice-versa). A teacher who teaches across stages can cover any of their stages.'
                            : 'Any qualified colleague can cover any class regardless of stage (cluster-school mode).' }}
                    </p>
                </div>
                <div class="flex gap-1 rounded-xl border border-base-300 bg-base-200/40 p-1">
                    <button type="button" @click="setPolicy('strict')"
                        class="rounded-lg px-3 py-1.5 text-xs font-bold transition-colors"
                        :class="coverStagePolicy === 'strict'
                            ? 'bg-rose-500 text-white'
                            : 'text-base-content/55 hover:bg-base-200'">
                        Strict (by stage)
                    </button>
                    <button type="button" @click="setPolicy('open')"
                        class="rounded-lg px-3 py-1.5 text-xs font-bold transition-colors"
                        :class="coverStagePolicy === 'open'
                            ? 'bg-emerald-500 text-white'
                            : 'text-base-content/55 hover:bg-base-200'">
                        Open (cluster)
                    </button>
                </div>
            </div>

            <!-- Validation error (reassign / confirm blocked by a constraint) -->
            <div v-if="formError" class="rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-2.5 flex items-start gap-2.5">
                <XCircleIcon class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
                <p class="text-sm text-rose-800 dark:text-rose-200 font-medium">{{ formError }}</p>
            </div>

            <!-- Dense stat strip -->
            <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                <div v-for="s in [
                        { label: 'Absent', value: absentCount, tone: absentCount ? 'rose' : '' },
                        { label: 'Partial-day', value: absentPartial },
                        { label: 'Suggested', value: stats.suggested, tone: stats.suggested ? 'amber' : '' },
                        { label: 'Confirmed', value: stats.confirmed, tone: stats.confirmed ? 'emerald' : '' },
                        { label: 'Total', value: stats.total },
                    ]" :key="s.label"
                    class="rounded-xl border border-base-300 bg-base-100 px-3 py-2">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/50">{{ s.label }}</p>
                    <p class="text-xl font-extrabold tabular-nums"
                        :class="{ 'text-rose-600': s.tone === 'rose', 'text-amber-600': s.tone === 'amber', 'text-emerald-600': s.tone === 'emerald' }">
                        {{ s.value }}
                    </p>
                </div>
            </div>

            <!-- Step 1 — mark absences + Generate button -->
            <section class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <header class="px-5 py-3 border-b border-base-300 flex items-center gap-2 flex-wrap">
                    <div class="w-8 h-8 rounded-lg bg-rose-500/15 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                        <UserMinusIcon class="w-4 h-4" />
                    </div>
                    <h2 class="text-sm font-bold">Step 1 — Mark today's absences</h2>
                    <button @click="generate" class="ml-auto btn btn-primary btn-sm rounded-xl gap-1.5">
                        <BoltIcon class="w-4 h-4" /> Generate adjustments
                    </button>
                </header>
                <div class="p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    <div v-for="t in teachers" :key="t.id"
                        class="rounded-xl border p-3 transition-colors"
                        :class="t.absent
                            ? ((t.absent_slot_ids?.length || t.from_time) ? 'bg-orange-500/10 border-orange-500/30' : 'bg-rose-500/10 border-rose-500/30')
                            : 'bg-base-200/30 border-base-300'">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
                                :class="t.absent
                                    ? ((t.absent_slot_ids?.length || t.from_time) ? 'bg-orange-500 text-white' : 'bg-rose-500 text-white')
                                    : 'bg-base-300 text-base-content/55'">
                                <UserMinusIcon v-if="t.absent" class="w-4 h-4" />
                                <UserPlusIcon v-else class="w-4 h-4" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <p class="text-sm font-bold truncate">{{ t.name }}</p>
                                    <span v-for="stg in (t.stages || [])" :key="stg"
                                        class="badge badge-xs badge-ghost whitespace-nowrap">
                                        {{ stageLabels?.[stg] || stg }}
                                    </span>
                                </div>
                                <p v-if="t.absent && t.absent_slot_ids?.length"
                                    class="text-[11px] text-orange-700 dark:text-orange-300 truncate font-semibold">
                                    Absent for {{ t.absent_slot_ids.length }} period(s){{ t.reason ? ' · ' + t.reason : '' }}
                                </p>
                                <p v-else-if="t.absent && t.from_time"
                                    class="text-[11px] text-orange-700 dark:text-orange-300 truncate font-semibold">
                                    Left after {{ t.from_time }}{{ t.reason ? ' · ' + t.reason : '' }}
                                </p>
                                <p v-else-if="t.absent"
                                    class="text-[11px] text-rose-700 dark:text-rose-300 truncate font-semibold">
                                    Absent (full day){{ t.reason ? ' · ' + t.reason : '' }}
                                </p>
                                <p v-else class="text-[11px] text-base-content/50 truncate">Present</p>
                            </div>
                        </div>
                        <div class="mt-2 flex gap-1.5">
                            <button v-if="!t.absent" @click="openMarkAbsent(t)"
                                class="btn btn-ghost btn-xs rounded-lg gap-1 flex-1 text-rose-600 dark:text-rose-400 hover:bg-rose-500/10">
                                <UserMinusIcon class="w-3 h-3" /> Mark absent
                            </button>
                            <template v-else>
                                <button @click="openEditAbsence(t)"
                                    class="btn btn-ghost btn-xs rounded-lg gap-1 flex-1"
                                    title="Change full-day vs partial-day">
                                    <PencilSquareIcon class="w-3 h-3" /> Edit
                                </button>
                                <button @click="markPresent(t)"
                                    class="btn btn-ghost btn-xs rounded-lg gap-1 flex-1 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/10">
                                    <UserPlusIcon class="w-3 h-3" /> Mark present
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Fairness audit — monthly cover counts -->
            <section v-if="fairness?.rows?.length" class="rounded-2xl border border-violet-500/30 bg-violet-500/5 overflow-hidden">
                <header class="px-5 py-3 border-b border-violet-500/30 flex items-center gap-2 cursor-pointer"
                    @click="showFairness = !showFairness">
                    <div class="w-8 h-8 rounded-lg bg-violet-500/15 text-violet-700 dark:text-violet-300 flex items-center justify-center">
                        <QuestionMarkCircleIcon class="w-4 h-4" />
                    </div>
                    <div class="flex-1">
                        <h2 class="text-sm font-bold">Fairness audit — {{ fairness.month_label }}</h2>
                        <p class="text-[11px] text-base-content/55 mt-0.5">
                            Total covers: <span class="font-bold tabular-nums">{{ fairness.total }}</span>
                            · Avg per teacher: <span class="font-bold tabular-nums">{{ fairness.avg }}</span>
                            · Click to {{ showFairness ? 'hide' : 'show' }} breakdown
                        </p>
                    </div>
                </header>
                <div v-if="showFairness" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider text-base-content/55">
                            <tr>
                                <th class="text-left px-3 py-2 font-bold">Teacher</th>
                                <th class="text-right px-3 py-2 font-bold">Covers taken</th>
                                <th class="text-right px-3 py-2 font-bold">Covered for them</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-300">
                            <tr v-for="row in fairness.rows" :key="row.id" class="hover:bg-base-200/30">
                                <td class="px-3 py-2 text-sm">{{ row.name }}</td>
                                <td class="px-3 py-2 text-right tabular-nums" :class="fairnessClass(row)">
                                    {{ row.covers_taken }}
                                </td>
                                <td class="px-3 py-2 text-right text-xs text-base-content/60 tabular-nums">
                                    {{ row.covered_for }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="text-[11px] text-base-content/55 px-3 py-2 border-t border-base-300 bg-base-200/30">
                        Colour key: <span class="text-rose-700 dark:text-rose-300 font-bold">≥ 150% of avg (overloaded)</span>
                        · <span class="text-amber-700 dark:text-amber-300 font-semibold">≥ avg</span>
                        · <span class="text-emerald-700 dark:text-emerald-300">below avg</span>
                        · <span class="text-base-content/40">none yet</span>.
                        The algorithm penalises teachers with high recent loads to keep this distribution even.
                    </p>
                </div>
            </section>

            <!-- Step 2 — review generated assignments -->
            <section v-if="assignments.length" class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden">
                <header class="px-5 py-3 border-b border-base-300 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <CheckCircleIcon class="w-4 h-4" />
                    </div>
                    <h2 class="text-sm font-bold">Step 2 — Review &amp; confirm adjustments</h2>
                </header>

                <!-- Load summary -->
                <div v-if="assignmentsBySubstitute.length" class="p-4 border-b border-base-300 bg-base-200/30">
                    <p class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 mb-2">Load distribution</p>
                    <div class="flex flex-wrap gap-1.5">
                        <span v-for="g in assignmentsBySubstitute" :key="g.name"
                            class="inline-flex items-center gap-1.5 bg-base-100 ring-1 ring-base-300 rounded-md px-2.5 py-1 text-xs">
                            <span class="font-semibold">{{ g.name }}</span>
                            <span class="font-mono font-bold tabular-nums px-1.5 py-0 rounded text-[10px]"
                                :class="g.count >= 4 ? 'bg-rose-500/15 text-rose-700 dark:text-rose-300' :
                                       g.count === 3 ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300' :
                                       'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'">
                                {{ g.count }}
                            </span>
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider text-base-content/55">
                            <tr>
                                <th class="text-left px-3 py-2.5 font-bold">Period</th>
                                <th class="text-left px-3 py-2.5 font-bold">Class · Section</th>
                                <th class="text-left px-3 py-2.5 font-bold">Subject</th>
                                <th class="text-left px-3 py-2.5 font-bold">Original (absent)</th>
                                <th class="text-left px-3 py-2.5 font-bold">Substitute</th>
                                <th class="text-center px-3 py-2.5 font-bold">Status</th>
                                <th class="text-right px-3 py-2.5 font-bold w-32">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-300">
                            <tr v-for="a in assignments" :key="a.id" class="hover:bg-base-200/30 transition-colors">
                                <td class="px-3 py-2.5">
                                    <p class="font-bold">{{ a.time_slot }}</p>
                                    <p class="text-[10px] text-base-content/55 font-mono">{{ a.time_range }}</p>
                                </td>
                                <td class="px-3 py-2.5 text-xs">{{ a.class }} · {{ a.section }}</td>
                                <td class="px-3 py-2.5 text-xs font-semibold">{{ a.subject || '—' }}</td>
                                <td class="px-3 py-2.5 text-xs text-rose-600 dark:text-rose-400">{{ a.original_teacher }}</td>
                                <td class="px-3 py-2.5 text-xs font-bold">
                                    <span>{{ a.substitute_teacher }}</span>
                                    <button v-if="a.score_breakdown" @click="openScore(a)"
                                        class="ml-1 align-middle text-base-content/40 hover:text-violet-600"
                                        title="Why this teacher?">
                                        <QuestionMarkCircleIcon class="w-3.5 h-3.5 inline" />
                                    </button>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold ring-1 capitalize"
                                        :class="statusPill(a.status)">
                                        {{ a.status }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                    <button v-if="a.status !== 'confirmed'" @click="confirmA(a)"
                                        class="btn btn-ghost btn-xs rounded-lg" title="Confirm">
                                        <CheckCircleIcon class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                                    </button>
                                    <button @click="openReassign(a)"
                                        class="btn btn-ghost btn-xs rounded-lg" title="Reassign">
                                        <ArrowsRightLeftIcon class="w-3.5 h-3.5" />
                                    </button>
                                    <button v-if="a.status !== 'declined'" @click="declineA(a)"
                                        class="btn btn-ghost btn-xs rounded-lg" title="Decline">
                                        <XCircleIcon class="w-3.5 h-3.5 text-rose-500" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div v-else-if="absentCount > 0" class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-6 text-center">
                <ExclamationTriangleIcon class="w-9 h-9 text-amber-500 mx-auto mb-2" />
                <p class="font-bold text-sm">{{ absentCount }} teacher{{ absentCount === 1 ? '' : 's' }} marked absent</p>
                <p class="text-xs text-base-content/65 mt-1">Click <strong>Generate adjustments</strong> to auto-cover their empty periods.</p>
            </div>

            <!-- ═════════ SCORE TRACE POPOVER ═════════ -->
            <div v-if="scoreTrace" class="modal modal-open">
                <div class="modal-box max-w-md">
                    <div class="flex items-center gap-2 mb-2">
                        <QuestionMarkCircleIcon class="w-5 h-5 text-violet-600 dark:text-violet-400" />
                        <h3 class="text-base font-bold">Why this teacher?</h3>
                    </div>
                    <p class="text-xs text-base-content/65 mb-4">
                        How <strong>{{ scoreTrace.substitute_teacher }}</strong> was picked for {{ scoreTrace.subject }} ({{ scoreTrace.class }} · {{ scoreTrace.section }}, {{ scoreTrace.time_slot }}).
                    </p>
                    <div v-if="scoreTrace.score_breakdown?.reasons?.length" class="space-y-1.5 mb-3">
                        <div v-for="(r, idx) in scoreTrace.score_breakdown.reasons" :key="idx"
                            class="flex items-start gap-2 text-sm">
                            <span class="font-mono font-bold tabular-nums w-10 text-right shrink-0"
                                :class="parseInt(r[0]) > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                {{ r[0] }}
                            </span>
                            <span class="text-base-content/75">{{ r[1] }}</span>
                        </div>
                    </div>
                    <div v-else class="text-sm text-base-content/55 italic">
                        No score detail recorded — this assignment may have been made manually or before the trace was added.
                    </div>
                    <div v-if="scoreTrace.score_breakdown?.total !== undefined"
                        class="border-t border-base-300 pt-2 mt-3 flex items-center justify-between text-sm">
                        <span class="font-bold">Total score</span>
                        <span class="font-mono font-bold tabular-nums text-violet-700 dark:text-violet-300">
                            {{ scoreTrace.score_breakdown.total }}
                        </span>
                    </div>
                    <div class="modal-action">
                        <button @click="closeScore" class="btn btn-ghost btn-sm">Close</button>
                    </div>
                </div>
                <div class="modal-backdrop" @click="closeScore"></div>
            </div>

            <!-- ═════════ ABSENCE MODAL — full vs partial day ═════════ -->
            <div v-if="absenceModal" class="modal modal-open">
                <div class="modal-box max-w-md">
                    <h3 class="text-base font-bold mb-1">
                        {{ absenceModal.isEdit ? 'Edit absence' : 'Mark absent' }}
                    </h3>
                    <p class="text-xs text-base-content/65 mb-4">{{ absenceModal.teacher.name }}</p>

                    <!-- Mode picker -->
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <button type="button" @click="absenceModal.mode = 'full'"
                            class="rounded-xl p-3 text-left ring-2 transition-colors"
                            :class="absenceModal.mode === 'full'
                                ? 'ring-rose-500 bg-rose-500/10'
                                : 'ring-base-300 bg-base-200/30 hover:bg-base-200/60'">
                            <div class="flex items-center gap-2 mb-1">
                                <UserMinusIcon class="w-4 h-4 text-rose-600 dark:text-rose-400" />
                                <span class="font-bold text-xs">Full day</span>
                            </div>
                            <p class="text-[10px] text-base-content/60">Absent whole day.</p>
                        </button>
                        <button type="button" @click="absenceModal.mode = 'partial'"
                            class="rounded-xl p-3 text-left ring-2 transition-colors"
                            :class="absenceModal.mode === 'partial'
                                ? 'ring-orange-500 bg-orange-500/10'
                                : 'ring-base-300 bg-base-200/30 hover:bg-base-200/60'">
                            <div class="flex items-center gap-2 mb-1">
                                <ClockIcon class="w-4 h-4 text-orange-600 dark:text-orange-400" />
                                <span class="font-bold text-xs">Left at time</span>
                            </div>
                            <p class="text-[10px] text-base-content/60">All periods after a time.</p>
                        </button>
                        <button type="button" @click="absenceModal.mode = 'periods'"
                            class="rounded-xl p-3 text-left ring-2 transition-colors"
                            :class="absenceModal.mode === 'periods'
                                ? 'ring-amber-500 bg-amber-500/10'
                                : 'ring-base-300 bg-base-200/30 hover:bg-base-200/60'">
                            <div class="flex items-center gap-2 mb-1">
                                <ClockIcon class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                                <span class="font-bold text-xs">Pick periods</span>
                            </div>
                            <p class="text-[10px] text-base-content/60">Tick exact periods to cover.</p>
                        </button>
                    </div>

                    <!-- Slot picker (left-at-time mode) -->
                    <div v-if="absenceModal.mode === 'partial'" class="mb-3">
                        <label class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 block mb-1.5">
                            Absent from
                        </label>
                        <SearchableSelect v-model="absenceModal.fromTime" size="sm"
                            :options="absenceModal.slots.map(s => ({
                                value: s.starts_at,
                                label: `${s.name} (from ${s.starts_at})`,
                                sublabel: s.stage ? (stageLabels?.[s.stage] || s.stage) : '',
                            }))"
                            placeholder="Select start time" />
                        <p class="text-[11px] text-base-content/55 mt-1">
                            All periods starting at <strong>{{ absenceModal.fromTime }}</strong> or later will be auto-covered.
                        </p>
                    </div>

                    <!-- Per-period checkbox picker -->
                    <div v-if="absenceModal.mode === 'periods'" class="mb-3">
                        <label class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 block mb-1.5">
                            Periods to cover ({{ absenceModal.slotIds.length }} ticked)
                        </label>
                        <div class="rounded-xl border border-amber-500/30 bg-amber-500/5 p-2 max-h-60 overflow-y-auto">
                            <div v-if="!absenceModal.slots.length" class="text-xs text-base-content/55 italic p-2">
                                No bell schedule configured for this teacher's stage. Set one up in
                                Timetable → Bell Schedule first.
                            </div>
                            <label v-for="s in absenceModal.slots" :key="s.id"
                                class="flex items-center gap-2 px-2 py-1.5 rounded-lg cursor-pointer hover:bg-base-200/40 transition-colors">
                                <input type="checkbox"
                                    :checked="absenceModal.slotIds.includes(s.id)"
                                    @change="toggleSlotForAbsence(s.id)"
                                    class="checkbox checkbox-sm checkbox-warning" />
                                <span class="font-bold text-sm flex-1">{{ s.name }}</span>
                                <span v-if="s.stage" class="badge badge-xs badge-ghost">{{ stageLabels?.[s.stage] || s.stage }}</span>
                                <span class="font-mono text-[11px] text-base-content/55">from {{ s.starts_at }}</span>
                            </label>
                        </div>
                        <p class="text-[11px] text-base-content/55 mt-1.5 leading-snug">
                            Tick only the periods the teacher will <strong>not</strong> take. Unticked periods stay
                            with them (e.g. she takes P1, P2, then leaves for P3–P4, returns for P5).
                        </p>
                    </div>

                    <!-- Reason -->
                    <div class="mb-4">
                        <label class="text-[11px] uppercase tracking-wider font-bold text-base-content/55 block mb-1.5">
                            Reason (optional)
                        </label>
                        <input v-model="absenceModal.reason" type="text"
                            placeholder="Sick leave, emergency, etc."
                            class="input input-bordered input-sm rounded-lg w-full text-sm" />
                    </div>

                    <div class="modal-action">
                        <button @click="closeAbsenceModal" class="btn btn-ghost btn-sm">Cancel</button>
                        <button @click="submitAbsence" class="btn btn-primary btn-sm gap-1.5">
                            <CheckCircleIcon class="w-4 h-4" />
                            {{ absenceModal.isEdit ? 'Update absence' : 'Mark absent' }}
                        </button>
                    </div>
                </div>
                <div class="modal-backdrop" @click="closeAbsenceModal"></div>
            </div>

            <!-- Reassignment modal -->
            <div v-if="reassignTarget" class="modal modal-open">
                <div class="modal-box max-w-md">
                    <h3 class="text-base font-bold mb-3">Reassign teacher</h3>
                    <p class="text-xs text-base-content/65 mb-3">
                        Pick a different teacher to cover <strong>{{ reassignTarget.subject }}</strong> for {{ reassignTarget.class }} · {{ reassignTarget.section }} ({{ reassignTarget.time_slot }}).
                    </p>
                    <SearchableSelect v-model="reassignTo"
                        :options="[{ value: '', label: '— Select teacher —' }, ...teachers.filter(x => !x.absent).map(t => ({ value: t.id, label: t.name }))]"
                        placeholder="— Select teacher —" />
                    <div class="modal-action">
                        <button @click="closeReassign" class="btn btn-ghost btn-sm">Cancel</button>
                        <button @click="submitReassign" :disabled="!reassignTo" class="btn btn-primary btn-sm">Reassign</button>
                    </div>
                </div>
                <div class="modal-backdrop" @click="closeReassign"></div>
            </div>
        </div>
    </AppLayout>
</template>
