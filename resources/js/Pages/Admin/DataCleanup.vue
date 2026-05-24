<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    ExclamationTriangleIcon,
    TrashIcon,
    ShieldExclamationIcon,
    DocumentMinusIcon,
    CalendarDaysIcon,
    ClipboardDocumentListIcon,
    LockClosedIcon,
    InformationCircleIcon,
    TableCellsIcon,
    BuildingOffice2Icon,
} from '@heroicons/vue/24/outline'
import { confirmDelete } from '@/lib/swal'

const props = defineProps({
    counts: { type: Object, default: () => ({}) },
    exams: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    selectedSchoolId: { type: [Number, String, null], default: null },
})

// ─── School scope ───
// Bound to the dropdown. Changing it re-loads the page with ?school_id=…
// so the row counts + exam list reflect the chosen school.
const schoolId = ref(props.selectedSchoolId ? Number(props.selectedSchoolId) : 0)

function applySchoolFilter() {
    const params = schoolId.value ? { school_id: schoolId.value } : {}
    router.get(route('admin.data-cleanup.index'), params, { preserveScroll: true, replace: true })
}

const selectedSchoolName = computed(() => {
    if (!schoolId.value) return 'All schools'
    return props.schools.find(s => s.id === schoolId.value)?.name || 'Selected school'
})

const scopeNote = computed(() =>
    schoolId.value
        ? `Only rows belonging to “${selectedSchoolName.value}” will be removed. Other schools are untouched.`
        : 'Every school in the system will be affected. Use the dropdown above to scope to a single school first.'
)

// ─── Password-prompt modal state ───
const pending = ref(null)
const password = ref('')
const pwError = ref('')
const submitting = ref(false)

function openPasswordModal(action) {
    pending.value = action
    password.value = ''
    pwError.value = ''
}

function closePasswordModal() {
    pending.value = null
    password.value = ''
    pwError.value = ''
    submitting.value = false
}

function submitPasswordModal() {
    if (!password.value) {
        pwError.value = 'Enter your password to confirm.'
        return
    }
    submitting.value = true
    pending.value.run(password.value)
}

function bodyWithSchool(pw) {
    return schoolId.value
        ? { password: pw, school_id: schoolId.value }
        : { password: pw }
}

// ─── Action runners ───
async function deleteExam(exam) {
    const scope = schoolId.value
        ? `Will clear this exam's data for “${selectedSchoolName.value}” only (other schools' data on this exam stays).`
        : 'Will also delete: exam subjects, schedules, seats, invigilators, marks, results and all submissions across every school.'

    const ok = await confirmDelete({
        title: `Delete exam “${exam.name}”?`,
        text: scope + ' This cannot be undone.',
        confirmText: 'Yes, continue',
    })
    if (!ok) return

    openPasswordModal({
        label: `Confirm deletion of “${exam.name}”`,
        verb: 'Delete exam',
        run: (pw) => router.delete(route('admin.data-cleanup.destroy-exam', exam.id), {
            data: bodyWithSchool(pw),
            preserveScroll: true,
            onError: (errors) => { pwError.value = errors.password || 'Wrong password.'; submitting.value = false },
            onSuccess: closePasswordModal,
            onFinish: () => { submitting.value = false },
        }),
    })
}

async function wipeAll(kind, label) {
    const rows = computedDanger(kind)
    const ok = await confirmDelete({
        title: `Wipe ALL ${label}?`,
        text: schoolId.value
            ? `Removes ${rows} from “${selectedSchoolName.value}” only. Other schools unaffected.`
            : `Removes ${rows} across every school. Setup (students, teachers, classes, bell schedule) is untouched.`,
        confirmText: 'Yes, continue',
    })
    if (!ok) return

    openPasswordModal({
        label: schoolId.value
            ? `Confirm wiping ${label} for “${selectedSchoolName.value}”`
            : `Confirm wiping ALL ${label}`,
        verb: `Wipe ${label}`,
        run: (pw) => router.post(route(`admin.data-cleanup.wipe-${kind}`), bodyWithSchool(pw), {
            preserveScroll: true,
            onError: (errors) => { pwError.value = errors.password || 'Wrong password.'; submitting.value = false },
            onSuccess: closePasswordModal,
            onFinish: () => { submitting.value = false },
        }),
    })
}

function computedDanger(kind) {
    const c = props.counts || {}
    const num = (n) => (n || 0).toLocaleString()
    if (kind === 'exams') {
        const total = ['exam_subjects', 'exam_schedules', 'marks', 'results']
            .reduce((acc, k) => acc + (c[k] || 0), 0)
        return `${num(total)} dependent rows`
    }
    if (kind === 'results') return `${num((c.results || 0) + (c.result_submissions || 0) + (c.result_amendments || 0))} result rows`
    if (kind === 'marks') return `${num((c.marks || 0) + (c.marks_submissions || 0))} mark rows`
    if (kind === 'datesheets') return `${num(c.exam_schedules)} schedule rows`
    if (kind === 'timetable') return `${num((c.timetable_entries || 0) + (c.teacher_absences || 0) + (c.substitution_assignments || 0))} period + absence + class-adjustment rows`
    return '—'
}

const statusBadge = (s) => ({
    draft: 'badge-ghost',
    marks_entry: 'badge-warning',
    processing: 'badge-info',
    completed: 'badge-success',
    published: 'badge-success',
}[s] || 'badge-ghost')

const fmt = (n) => (n || 0).toLocaleString()
</script>

<template>
    <Head title="Data Cleanup — Danger Zone" />
    <AppLayout :breadcrumbs="[{ label: 'System' }, { label: 'Data Cleanup' }]">
        <div class="space-y-5 max-w-5xl mx-auto">
            <!-- Header -->
            <div class="rounded-2xl border border-error/40 bg-error/5 p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-error/15 text-error">
                        <ShieldExclamationIcon class="w-5 h-5" />
                    </div>
                    <div>
                        <h1 class="text-xl font-extrabold tracking-tight text-error">Danger Zone — Data Cleanup</h1>
                        <p class="mt-1 text-sm text-base-content/70">
                            Use these tools to remove demo / test data so the school can start clean.
                            Every action requires your password and cannot be undone.
                            <br /><strong>Setup is never touched</strong> — students, teachers, classes,
                            sections, subject assignments, bell schedule and accounts stay intact.
                        </p>
                    </div>
                </div>
            </div>

            <!-- School scope -->
            <div class="rounded-2xl border border-primary/30 bg-primary/5 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/15 text-primary">
                            <BuildingOffice2Icon class="w-5 h-5" />
                        </div>
                        <div>
                            <p class="text-sm font-bold">Scope cleanup to one school</p>
                            <p class="text-xs text-base-content/60 mt-0.5">{{ scopeNote }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2 items-center">
                        <div class="min-w-[220px]">
                            <SearchableSelect v-model="schoolId" size="sm"
                                :options="[{ value: 0, label: 'All schools (wipe everywhere)' }, ...schools.map(s => ({ value: s.id, label: s.code ? `${s.name} (${s.code})` : s.name }))]"
                                placeholder="All schools"
                                @change="applySchoolFilter" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Counts strip -->
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2">
                <div v-for="(label, key) in {
                    exam_subjects: 'Exam subjects', exam_schedules: 'Schedules',
                    marks: 'Marks', results: 'Results',
                    exam_seats: 'Seats', exam_invigilators: 'Invigilators',
                    timetable_entries: 'Timetable', substitution_assignments: 'Class adj.',
                }" :key="key"
                    class="rounded-xl border border-base-300 bg-base-100 shadow-sm px-3 py-2 text-center">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/45">{{ label }}</p>
                    <p class="text-lg font-extrabold tabular-nums mt-0.5">{{ fmt(counts?.[key]) }}</p>
                </div>
            </div>

            <!-- ── Bulk wipe sections ── -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="rounded-2xl border border-base-300 bg-base-100 p-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-error/15 text-error">
                            <TrashIcon class="w-5 h-5" />
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-base">Wipe ALL exams</h3>
                            <p class="text-xs text-base-content/60 mt-1">
                                Removes every dependent row: exam_subjects, schedules, seats,
                                invigilators, marks, marks-submissions, results,
                                result-submissions, result-amendments and the school-pivot rows.
                                With a school selected, the parent exam record is kept so other
                                schools' data on the same exam isn't affected.
                            </p>
                            <button @click="wipeAll('exams', 'exams')"
                                class="btn btn-error btn-sm gap-2 mt-3">
                                <TrashIcon class="w-4 h-4" /> Wipe all exams
                            </button>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-base-300 bg-base-100 p-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-warning/15 text-warning">
                            <DocumentMinusIcon class="w-5 h-5" />
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-base">Wipe ALL results</h3>
                            <p class="text-xs text-base-content/60 mt-1">
                                Removes generated results + submissions + amendments only. Exams,
                                marks and date sheets stay — you can re-run "Generate Results" after.
                            </p>
                            <button @click="wipeAll('results', 'results')"
                                class="btn btn-warning btn-sm gap-2 mt-3">
                                <DocumentMinusIcon class="w-4 h-4" /> Wipe all results
                            </button>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-base-300 bg-base-100 p-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-warning/15 text-warning">
                            <ClipboardDocumentListIcon class="w-5 h-5" />
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-base">Wipe ALL marks</h3>
                            <p class="text-xs text-base-content/60 mt-1">
                                Removes mark entries + their per-section submissions. Exams,
                                schedules and results stay — generated results will go stale
                                until re-generated.
                            </p>
                            <button @click="wipeAll('marks', 'marks')"
                                class="btn btn-warning btn-sm gap-2 mt-3">
                                <ClipboardDocumentListIcon class="w-4 h-4" /> Wipe all marks
                            </button>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-base-300 bg-base-100 p-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-info/15 text-info">
                            <CalendarDaysIcon class="w-5 h-5" />
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-base">Wipe ALL date sheets</h3>
                            <p class="text-xs text-base-content/60 mt-1">
                                Removes only the schedule rows (paper dates + times). Exams, subjects,
                                marks and results are untouched.
                            </p>
                            <button @click="wipeAll('datesheets', 'date sheets')"
                                class="btn btn-info btn-sm gap-2 mt-3">
                                <CalendarDaysIcon class="w-4 h-4" /> Wipe all date sheets
                            </button>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-base-300 bg-base-100 p-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-warning/15 text-warning">
                            <TableCellsIcon class="w-5 h-5" />
                        </div>
                        <div class="flex-1">
                            <h3 class="font-bold text-base">Wipe ALL timetable</h3>
                            <p class="text-xs text-base-content/60 mt-1">
                                Clears the weekly routine (period assignments) + teacher absences and
                                class adjustments. The bell schedule (time slots) is kept so you can
                                re-generate quickly.
                            </p>
                            <button @click="wipeAll('timetable', 'timetable')"
                                class="btn btn-warning btn-sm gap-2 mt-3">
                                <TableCellsIcon class="w-4 h-4" /> Wipe all timetable
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Per-exam deletion ── -->
            <div class="rounded-2xl border border-base-300 bg-base-100">
                <div class="border-b border-base-300 p-4">
                    <h2 class="font-bold text-base flex items-center gap-2">
                        <ExclamationTriangleIcon class="w-5 h-5 text-error" />
                        Delete a single exam
                        <span v-if="schoolId" class="badge badge-info badge-sm">scope: {{ selectedSchoolName }}</span>
                    </h2>
                    <p class="text-xs text-base-content/55 mt-1">
                        Surgical — removes only the chosen exam's data
                        {{ schoolId ? 'for the selected school (parent exam record kept).' : '+ its dependent rows. Other exams stay.' }}
                    </p>
                </div>
                <div v-if="!exams.length" class="p-6 text-center text-sm text-base-content/55">
                    No exams found{{ schoolId ? ' for the selected school' : '' }}.
                </div>
                <div v-else class="divide-y divide-base-300">
                    <div v-for="e in exams" :key="e.id" class="p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-semibold truncate">{{ e.name }}</p>
                                <span class="badge badge-sm" :class="statusBadge(e.status)">{{ e.status }}</span>
                                <span v-if="e.is_locked" class="badge badge-sm badge-error gap-1">
                                    <LockClosedIcon class="w-3 h-3" /> Locked
                                </span>
                            </div>
                            <p class="text-xs text-base-content/55 mt-0.5 truncate">
                                {{ e.exam_type || '—' }} · {{ e.session || '—' }}
                                <span v-if="e.start_date">· {{ e.start_date }}{{ e.end_date ? ' → ' + e.end_date : '' }}</span>
                            </p>
                            <p class="text-xs text-base-content/55 mt-0.5">
                                <span class="font-semibold">{{ fmt(e.exam_subjects_count) }}</span> exam-subjects ·
                                <span class="font-semibold">{{ fmt(e.marks_count) }}</span> marks ·
                                <span class="font-semibold">{{ fmt(e.results_count) }}</span> results
                            </p>
                        </div>
                        <button @click="deleteExam(e)" class="btn btn-error btn-sm gap-1.5 shrink-0">
                            <TrashIcon class="w-4 h-4" /> {{ schoolId ? 'Clear for school' : 'Delete exam' }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-info/40 bg-info/5 p-3 text-xs text-base-content/70 flex items-start gap-2">
                <InformationCircleIcon class="w-4 h-4 shrink-0 text-info mt-0.5" />
                <span>
                    These actions only affect <strong>exam-related + timetable</strong> tables. Students, users, classes,
                    sections, subject-teacher assignments, time slots, academic sessions, schools and site settings
                    are never touched here.
                </span>
            </div>
        </div>

        <!-- ── Password-confirm modal ── -->
        <div v-if="pending" class="fixed inset-0 z-50 flex items-center justify-center bg-base-content/40 p-4"
            @click.self="closePasswordModal">
            <div class="bg-base-100 rounded-2xl shadow-xl border border-base-300 w-full max-w-md p-5">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-error/15 text-error">
                        <LockClosedIcon class="w-5 h-5" />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-base">{{ pending.label }}</h3>
                        <p class="text-xs text-base-content/60 mt-1">
                            Enter your account password to confirm. This cannot be undone.
                        </p>
                    </div>
                </div>
                <form @submit.prevent="submitPasswordModal" class="mt-4 space-y-3">
                    <input v-model="password" type="password" autocomplete="current-password" autofocus
                        placeholder="Your password"
                        class="input input-bordered w-full"
                        :class="pwError ? 'input-error' : ''" />
                    <p v-if="pwError" class="text-xs text-error">{{ pwError }}</p>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" @click="closePasswordModal" class="btn btn-ghost btn-sm">Cancel</button>
                        <button type="submit" :disabled="submitting" class="btn btn-error btn-sm gap-1.5">
                            <LockClosedIcon class="w-4 h-4" />
                            {{ submitting ? 'Working…' : pending.verb }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
