<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    PrinterIcon, DocumentArrowDownIcon,
    UserGroupIcon, CheckCircleIcon,
    XCircleIcon, AcademicCapIcon,
    ChartBarIcon, IdentificationIcon,
    DocumentDuplicateIcon, PencilSquareIcon,
    ArrowPathIcon, ClockIcon, XMarkIcon, ArrowDownTrayIcon, DocumentTextIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: Object,
    section: Object,
    schoolClass: Object,
    results: Object,
    summary: Object,
    subjects: Array,
    latestAmendments: { type: Object, default: () => ({}) },
    canAmend: { type: Boolean, default: false },
    // True for ECD–5 sections. When set, each result row carries an
    // assessment_payload {obtained, total, passed} and the table renders
    // an extra "Assess" column between subjects and Total.
    isPrimary: { type: Boolean, default: false },
})

// ─── Amendment workflow ───
// Open the modal pre-filled with the result's current marks; teacher edits
// only what changed + writes a reason; submit logs an audit row and notifies
// the linked student/parent.
const amendDialog = ref(false)
const amendingResult = ref(null)
const amendForm = useForm({
    reason: '',
    obtained_marks: null,
    percentage: null,
    grade: '',
    position: null,
    remarks: '',
})

function openAmend(result) {
    amendingResult.value = result
    amendForm.reset()
    // Prefill with current values so the teacher can see what's there.
    amendForm.obtained_marks = result.obtained_marks
    amendForm.percentage = result.percentage
    amendForm.grade = result.grade
    amendForm.position = result.position
    amendForm.remarks = result.remarks || ''
    amendForm.reason = ''
    amendDialog.value = true
}

function submitAmendment() {
    if (!amendingResult.value) return
    amendForm.post(route('results.amend', amendingResult.value.id), {
        preserveScroll: true,
        onSuccess: () => { amendDialog.value = false; amendingResult.value = null },
    })
}

function printResultSheet(examId, classId, sectionId = null) {
    // sectionId is included as a query param — the controller filters
    // Results to that section only when present. Without it, the sheet
    // covers the whole class (all sections combined).
    const base = route('reports.result-sheet', [examId, classId])
    const url = sectionId ? `${base}?section=${sectionId}` : base
    window.open(url, '_blank')
}

function exportResults(examId) {
    window.open(route('reports.export', ['results', examId]), '_blank')
}

function openReportCard(examId, studentId) {
    window.open(route('reports.report-card', [examId, studentId]), '_blank')
}

function downloadAllMarkSheets(examId, sectionId) {
    // Preview flow — inline PDF in a new tab.
    window.open(route('reports.section-mark-sheets', [examId, sectionId]), '_blank')
}

function downloadAllMarkSheetsPdf(examId, sectionId) {
    // Explicit download — ?download=1 makes the controller send
    // Content-Disposition: attachment so the browser saves the file
    // instead of previewing. Use a hidden <a> so we get the browser's
    // native download UX + filename hint.
    const url = route('reports.section-mark-sheets', [examId, sectionId]) + '?download=1'
    const a = document.createElement('a')
    a.href = url
    a.rel = 'noopener'
    document.body.appendChild(a)
    a.click()
    a.remove()
}

// Board-pattern term-wise result — primary classes only. Two formats:
// pdf opens in a new tab (preview), xlsx downloads the workbook.
function openBoardPrimaryPdf(examId, sectionId) {
    window.open(route('reports.board-primary', [examId, sectionId]), '_blank')
}
function downloadBoardPrimaryXlsx(examId, sectionId) {
    const url = route('reports.board-primary', [examId, sectionId]) + '?format=xlsx'
    const a = document.createElement('a')
    a.href = url
    a.rel = 'noopener'
    document.body.appendChild(a)
    a.click()
    a.remove()
}

// Every primary section, one workbook — one sheet per section. Same
// per-section layout as the single-section export, just batched.
function downloadBoardPrimaryAllSectionsXlsx(examId) {
    const url = route('reports.board-primary-all', [examId])
    const a = document.createElement('a')
    a.href = url
    a.rel = 'noopener'
    document.body.appendChild(a)
    a.click()
    a.remove()
}
</script>

<template>
    <Head :title="`Results - ${schoolClass?.name} ${section?.name}`" />
    <AppLayout :breadcrumbs="[
        { label: 'Results', href: route('results.index') },
        { label: exam?.name, href: route('results.generate', exam?.id) },
        { label: `${schoolClass?.name} - ${section?.name}` }
    ]">
        <div class="space-y-6">
            <!-- Header — title stacks above the action grid on mobile,
                 sits inline on lg+. Overflow-safe truncation on the title
                 line so long exam names don't push buttons off-screen. -->
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <h1 class="text-lg sm:text-xl font-bold truncate">{{ exam?.name }}</h1>
                    <p class="text-xs sm:text-sm text-base-content/60 mt-0.5 truncate">
                        {{ schoolClass?.name }} - {{ section?.name }} &middot; Result Sheet
                    </p>
                </div>
                <!--
                    Action buttons — uniform sizing across breakpoints.
                      · mobile (default): full-width, 2-column grid
                      · sm+: 3 columns
                      · lg+: horizontal row, min-width so widths look aligned
                    Every button carries the same base class list so heights,
                    padding and gap match regardless of colour variant.
                -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:flex lg:flex-wrap gap-2 sm:w-auto">
                    <template v-if="isPrimary">
                        <button @click="openBoardPrimaryPdf(exam?.id, section?.id)"
                            class="btn btn-info btn-sm gap-1.5 justify-center lg:min-w-[164px] normal-case"
                            title="Board-pattern term-wise result sheet (T-I, T-II, T-III combined) — PDF.">
                            <DocumentTextIcon class="w-4 h-4 shrink-0" />
                            <span class="truncate">Board (PDF)</span>
                        </button>
                        <button @click="downloadBoardPrimaryXlsx(exam?.id, section?.id)"
                            class="btn btn-info btn-sm btn-outline gap-1.5 justify-center lg:min-w-[164px] normal-case"
                            title="Same board-pattern sheet as an Excel workbook.">
                            <ArrowDownTrayIcon class="w-4 h-4 shrink-0" />
                            <span class="truncate">Board (Excel)</span>
                        </button>
                        <button @click="downloadBoardPrimaryAllSectionsXlsx(exam?.id)"
                            class="btn btn-primary btn-sm gap-1.5 justify-center lg:min-w-[164px] normal-case"
                            title="Every primary section for this exam in ONE Excel workbook — one tab per section, same board format.">
                            <ArrowDownTrayIcon class="w-4 h-4 shrink-0" />
                            <span class="truncate">Board — All Sections</span>
                        </button>
                    </template>
                    <button @click="downloadAllMarkSheetsPdf(exam?.id, section?.id)"
                        class="btn btn-success btn-sm gap-1.5 justify-center lg:min-w-[164px] normal-case"
                        title="Download all students' mark sheets for this section as a single PDF file.">
                        <ArrowDownTrayIcon class="w-4 h-4 shrink-0" />
                        <span class="truncate">Mark Sheets</span>
                    </button>
                    <button @click="downloadAllMarkSheets(exam?.id, section?.id)"
                        class="btn btn-primary btn-sm gap-1.5 justify-center lg:min-w-[164px] normal-case"
                        title="Open all mark sheets in a new tab (preview only).">
                        <IdentificationIcon class="w-4 h-4 shrink-0" />
                        <span class="truncate">Preview Sheets</span>
                    </button>
                    <button @click="printResultSheet(exam?.id, schoolClass?.id, section?.id)"
                        class="btn btn-outline btn-sm gap-1.5 justify-center lg:min-w-[164px] normal-case"
                        title="Class result sheet PDF.">
                        <PrinterIcon class="w-4 h-4 shrink-0" />
                        <span class="truncate">Result Sheet</span>
                    </button>
                    <button @click="exportResults(exam?.id)"
                        class="btn btn-outline btn-sm gap-1.5 justify-center lg:min-w-[164px] normal-case"
                        title="Export the whole result set as a CSV file.">
                        <DocumentArrowDownIcon class="w-4 h-4 shrink-0" />
                        <span class="truncate">Export CSV</span>
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-4" v-if="summary">
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                <UserGroupIcon class="w-4 h-4 text-primary" />
                            </div>
                            <div>
                                <p class="text-[11px] text-base-content/50 uppercase tracking-wide">Total</p>
                                <p class="text-2xl font-bold leading-tight">{{ summary.total }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-success/10">
                                <CheckCircleIcon class="w-4 h-4 text-success" />
                            </div>
                            <div>
                                <p class="text-[11px] text-base-content/50 uppercase tracking-wide">Passed</p>
                                <p class="text-2xl font-bold leading-tight text-success">{{ summary.passed }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-error/10">
                                <XCircleIcon class="w-4 h-4 text-error" />
                            </div>
                            <div>
                                <p class="text-[11px] text-base-content/50 uppercase tracking-wide">Retry</p>
                                <p class="text-2xl font-bold leading-tight text-error">{{ summary.failed }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-warning/10">
                                <AcademicCapIcon class="w-4 h-4 text-warning" />
                            </div>
                            <div>
                                <p class="text-[11px] text-base-content/50 uppercase tracking-wide">Pass %</p>
                                <p class="text-2xl font-bold leading-tight">{{ summary.passPercentage }}%</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-info/10">
                                <ChartBarIcon class="w-4 h-4 text-info" />
                            </div>
                            <div>
                                <p class="text-[11px] text-base-content/50 uppercase tracking-wide">Average</p>
                                <p class="text-2xl font-bold leading-tight">{{ summary.avgPercentage }}%</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Table -->
            <div class="card bg-base-100 shadow-md">
                <div class="card-body p-0">
                    <div class="overflow-x-auto">
                        <table class="table table-sm table-pin-rows">
                            <thead>
                                <tr>
                                    <th class="bg-base-200 text-center w-12">Pos</th>
                                    <th class="bg-base-200 w-16">Roll</th>
                                    <th class="bg-base-200 min-w-[140px]">Student Name</th>
                                    <th class="bg-base-200 min-w-[120px]">Father Name</th>
                                    <th v-for="subj in subjects" :key="subj.id" class="bg-base-200 text-center min-w-[60px]">
                                        <div class="text-xs font-bold">{{ subj.code || subj.name?.substring(0, 4) }}</div>
                                        <div class="text-[10px] text-base-content/40 font-normal">({{ subj.total }})</div>
                                    </th>
                                    <!-- Primary-only: Overall Assessment column (10 marks). -->
                                    <th v-if="isPrimary" class="bg-emerald-500/15 text-center min-w-[60px]">
                                        <div class="text-xs font-bold text-emerald-700 dark:text-emerald-300">Assess</div>
                                        <div class="text-[10px] text-emerald-600/70 dark:text-emerald-400/70 font-normal">(10)</div>
                                    </th>
                                    <th class="bg-base-200 text-center min-w-[70px]">Total</th>
                                    <th class="bg-base-200 text-center w-16">%</th>
                                    <th class="bg-base-200 text-center w-16">Grade</th>
                                    <th class="bg-base-200 text-center w-20">Status</th>
                                    <th v-if="canAmend" class="bg-base-200 text-center w-12"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="result in results?.data" :key="result.id"
                                    :class="{ 'bg-error/5': !result.is_passed }">
                                    <td class="text-center font-bold text-base-content/70">{{ result.position }}</td>
                                    <td class="text-sm">{{ result.student?.roll_no }}</td>
                                    <td>
                                        <button
                                            @click="openReportCard(exam?.id, result.student?.id)"
                                            class="link link-primary link-hover font-medium text-sm text-left">
                                            {{ result.student?.name }}
                                        </button>
                                    </td>
                                    <td class="text-sm text-base-content/60">{{ result.student?.father_name || '-' }}</td>
                                    <td v-for="subj in subjects" :key="subj.id" class="text-center text-sm">
                                        <template v-if="result.subject_results?.[subj.id]">
                                            <span v-if="result.subject_results[subj.id].is_absent"
                                                  class="badge badge-ghost badge-xs">AB</span>
                                            <span v-else
                                                  :class="{ 'text-error font-bold': result.subject_results[subj.id].failed }">
                                                {{ result.subject_results[subj.id].obtained }}
                                            </span>
                                        </template>
                                        <span v-else class="text-base-content/20">-</span>
                                    </td>
                                    <!-- Primary-only Assessment cell. -->
                                    <td v-if="isPrimary" class="text-center text-sm bg-emerald-500/5">
                                        <template v-if="result.assessment_payload">
                                            <span :class="{ 'text-error font-bold': !result.assessment_payload.passed }">
                                                {{ result.assessment_payload.obtained }}
                                            </span>
                                        </template>
                                        <span v-else class="text-base-content/20" title="Not yet entered">—</span>
                                    </td>
                                    <td class="text-center font-bold text-sm">
                                        {{ result.obtained_marks }}<span class="text-base-content/40 font-normal">/{{ result.total_marks }}</span>
                                    </td>
                                    <td class="text-center font-bold text-sm">{{ result.percentage }}%</td>
                                    <td class="text-center">
                                        <span class="badge badge-outline badge-sm">{{ result.grade || '-' }}</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <span :class="['badge badge-sm', result.is_passed ? 'badge-success' : 'badge-error']">
                                                {{ result.is_passed ? 'PASS' : 'RETRY' }}
                                            </span>
                                            <span v-if="result.last_amended_iso"
                                                class="badge badge-xs badge-warning"
                                                :title="`Amended — see audit trail. Latest: ${latestAmendments?.[result.id]?.reason || ''}`">
                                                Amended
                                            </span>
                                        </div>
                                    </td>
                                    <td v-if="canAmend" class="text-center">
                                        <button @click="openAmend(result)"
                                            class="btn btn-ghost btn-xs btn-square"
                                            title="Amend this result">
                                            <PencilSquareIcon class="w-4 h-4 text-warning" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!results?.data?.length">
                                    <td :colspan="5 + (subjects?.length || 0) + 4 + (canAmend ? 1 : 0)" class="text-center py-8 text-base-content/50">
                                        No results found for this section.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-base-200" v-if="results?.links?.length > 3">
                        <Pagination :links="results.links" />
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Amendment dialog ───
             Pre-filled with the result's current values so the admin sees
             what's there. They edit only what changed and write a reason
             (required, min 5 chars). Saving fires a notification to the
             linked student + parent. -->
        <div v-if="amendDialog" class="modal modal-open">
            <div class="modal-box max-w-2xl">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <h3 class="text-lg font-bold flex items-center gap-2">
                            <ArrowPathIcon class="w-5 h-5 text-warning" />
                            Amend Result
                        </h3>
                        <p class="text-sm text-base-content/65 mt-1">
                            <span class="font-semibold">{{ amendingResult?.student?.name }}</span>
                            ({{ amendingResult?.student?.roll_no || '—' }}) ·
                            current total: {{ amendingResult?.obtained_marks }}/{{ amendingResult?.total_marks }} ({{ amendingResult?.percentage }}%)
                        </p>
                    </div>
                    <button @click="amendDialog = false" class="btn btn-ghost btn-sm btn-square">
                        <XMarkIcon class="w-4 h-4" />
                    </button>
                </div>

                <div class="rounded-lg bg-warning/10 border border-warning/30 p-3 text-xs text-warning-content/80 mb-4 flex items-start gap-2">
                    <ClockIcon class="w-4 h-4 shrink-0 mt-0.5 text-warning" />
                    <div>
                        Amending a published result fires a notification to the student/parent and
                        records a permanent audit trail entry. Empty fields below are left unchanged.
                    </div>
                </div>

                <form @submit.prevent="submitAmendment" class="space-y-3">
                    <div>
                        <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">
                            Reason for amendment <span class="text-error">*</span>
                        </label>
                        <textarea v-model="amendForm.reason" rows="2"
                            placeholder="e.g. Math paper re-checked, 3 extra marks found"
                            class="textarea textarea-bordered w-full mt-1"></textarea>
                        <p v-if="amendForm.errors.reason" class="text-xs text-error mt-1">{{ amendForm.errors.reason }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Obtained Marks</label>
                            <input v-model.number="amendForm.obtained_marks" type="number" step="0.01" min="0"
                                class="input input-bordered w-full mt-1 font-mono" />
                            <p v-if="amendForm.errors.obtained_marks" class="text-xs text-error mt-1">{{ amendForm.errors.obtained_marks }}</p>
                        </div>
                        <div>
                            <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Percentage</label>
                            <input v-model.number="amendForm.percentage" type="number" step="0.01" min="0" max="100"
                                class="input input-bordered w-full mt-1 font-mono" />
                            <p v-if="amendForm.errors.percentage" class="text-xs text-error mt-1">{{ amendForm.errors.percentage }}</p>
                        </div>
                        <div>
                            <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Grade</label>
                            <input v-model="amendForm.grade" type="text" maxlength="8"
                                class="input input-bordered w-full mt-1" />
                        </div>
                        <div>
                            <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Position</label>
                            <input v-model.number="amendForm.position" type="number" min="1"
                                class="input input-bordered w-full mt-1 font-mono" />
                        </div>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/65">Remarks (optional)</label>
                        <input v-model="amendForm.remarks" type="text" maxlength="500"
                            class="input input-bordered w-full mt-1" />
                    </div>

                    <div class="modal-action">
                        <button type="button" @click="amendDialog = false" class="btn btn-ghost">Cancel</button>
                        <button type="submit" class="btn btn-warning gap-1.5" :disabled="amendForm.processing">
                            <ArrowPathIcon class="w-4 h-4" />
                            {{ amendForm.processing ? 'Saving…' : 'Save Amendment' }}
                        </button>
                    </div>
                </form>
            </div>
            <div class="modal-backdrop" @click="amendDialog = false"></div>
        </div>
    </AppLayout>
</template>
