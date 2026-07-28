<script setup>
/**
 * Per-student FBISE marks entry.
 * Live-updates percentage / grade / division as you type — same rules the
 * server calculator uses, so the preview matches the saved value exactly.
 */
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import axios from 'axios'
import {
    AcademicCapIcon, ArrowLeftIcon, DocumentCheckIcon,
    CheckCircleIcon, XCircleIcon, CameraIcon, XMarkIcon,
    ArrowUpTrayIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: { type: Object, required: true },
    student: { type: Object, required: true },
    result: { type: Object, default: null },
    subjects: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
})

const form = useForm({
    board_roll_no: props.result?.board_roll_no ?? '',
    remarks: props.result?.remarks ?? '',
    subjects: props.subjects.map(s => ({
        subject_id: s.id,
        name: s.name,
        code: s.code,
        included: s.included !== false,   // included by default
        is_absent: !!s.is_absent,
        theory_marks: s.theory_marks,
        practical_marks: s.practical_marks,
        theory_max: s.theory_max ?? 75,
        practical_max: s.practical_max ?? 25,
    })),
})

// ─── Live grade / division preview — mirrors BoardResultCalculatorService ───
const PASS_PCT = 33
const SUPPLY_MAX_FAILS = 2

function subjectTotal(s) {
    return (Number(s.theory_marks) || 0) + (Number(s.practical_marks) || 0)
}
function subjectMax(s) {
    return (Number(s.theory_max) || 0) + (Number(s.practical_max) || 0)
}
function subjectPct(s) {
    const max = subjectMax(s)
    return max > 0 ? (subjectTotal(s) / max) * 100 : 0
}
function subjectGrade(s) {
    if (s.is_absent) return 'F'
    const p = subjectPct(s)
    if (p >= 80) return 'A1'
    if (p >= 70) return 'A'
    if (p >= 60) return 'B'
    if (p >= 50) return 'C'
    if (p >= 40) return 'D'
    if (p >= 33) return 'E'
    return 'F'
}
function subjectPass(s) {
    return !s.is_absent && subjectPct(s) >= PASS_PCT
}

const included = computed(() => form.subjects.filter(s => s.included))
const totalObtained = computed(() => included.value.reduce((a, s) => a + subjectTotal(s), 0))
const totalMax      = computed(() => included.value.reduce((a, s) => a + subjectMax(s), 0))
const overallPct    = computed(() => totalMax.value > 0
    ? Math.round((totalObtained.value / totalMax.value) * 10000) / 100
    : 0)
const failedCount   = computed(() => included.value.filter(s => !subjectPass(s)).length)
const everyPass     = computed(() => included.value.length > 0 && failedCount.value === 0)
const isPass        = computed(() => everyPass.value && overallPct.value >= PASS_PCT)
const isSupply      = computed(() => !isPass.value && failedCount.value > 0 && failedCount.value <= SUPPLY_MAX_FAILS)
const overallGrade = computed(() => {
    const p = overallPct.value
    if (p >= 80) return 'A1'; if (p >= 70) return 'A'; if (p >= 60) return 'B'
    if (p >= 50) return 'C';  if (p >= 40) return 'D'; if (p >= 33) return 'E'
    return 'F'
})
const division = computed(() => {
    if (!isPass.value) return 'Fail'
    if (overallPct.value >= 60) return '1st'
    if (overallPct.value >= 45) return '2nd'
    return '3rd'
})
const gradePill = (g) => ({
    'A1': 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    'A':  'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    'B':  'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    'C':  'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    'D':  'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    'E':  'bg-orange-500/15 text-orange-700 dark:text-orange-300',
    'F':  'bg-rose-500/15 text-rose-700 dark:text-rose-300',
}[g] || 'bg-base-200 text-base-content/60')

function submit() {
    form.post(route('board-results.store-student', [props.exam.id, props.student.id]))
}

// ─── OCR: snap the FBISE gazette page → pre-fill this form ───
// Server uses Gemini vision + a FBISE-specific prompt, returns matched
// subject rows. We map each match onto the existing form.subjects entry
// (by subject_id) so the teacher just reviews + saves.
const ocrOpen = ref(false)
const ocrFileRef = ref(null)
const ocrBusy = ref(false)
const ocrError = ref('')
const ocrPreview = ref(null)   // { board_roll_no, subjects: [...] }
function openOcr() { ocrOpen.value = true; ocrPreview.value = null; ocrError.value = '' }
function pickOcrFile() { ocrFileRef.value?.click() }
async function onOcrFileSelected(e) {
    const f = e.target.files?.[0]
    if (!f) return
    ocrBusy.value = true
    ocrError.value = ''
    ocrPreview.value = null
    const fd = new FormData()
    fd.append('image', f)
    try {
        const { data } = await axios.post(
            route('board-results.ocr-gazette', [props.exam.id, props.student.id]),
            fd,
            { headers: { 'Content-Type': 'multipart/form-data' } }
        )
        if (!data.ok) {
            ocrError.value = data.error || 'Could not read the photo.'
            return
        }
        ocrPreview.value = data
    } catch (err) {
        ocrError.value = err?.response?.data?.error || err?.message || 'Upload failed.'
    } finally {
        ocrBusy.value = false
    }
}
function applyOcr() {
    if (!ocrPreview.value) return
    let filled = 0
    // Populate board roll if OCR read one and form is empty.
    if (ocrPreview.value.board_roll_no && !form.board_roll_no) {
        form.board_roll_no = ocrPreview.value.board_roll_no
    }
    // For each matched subject, fill theory/practical and tick "included".
    for (const s of ocrPreview.value.subjects || []) {
        if (!s.matched || !s.subject_id) continue
        const row = form.subjects.find(r => r.subject_id === s.subject_id)
        if (!row) continue
        row.included = true
        row.is_absent = false
        if (s.theory_marks !== null && s.theory_marks !== undefined) row.theory_marks = s.theory_marks
        if (s.practical_marks !== null && s.practical_marks !== undefined) row.practical_marks = s.practical_marks
        filled++
    }
    ocrOpen.value = false
    // Reset file input so re-selecting the SAME file still triggers change.
    if (ocrFileRef.value) ocrFileRef.value.value = ''
    // Toast if available, else silent — the form values are already updated.
    try { window.$toast?.success?.(`Filled ${filled} subject${filled === 1 ? '' : 's'} from OCR — review, then Save.`) } catch (_) {}
}
function closeOcr() {
    ocrOpen.value = false
    ocrError.value = ''
    ocrPreview.value = null
    if (ocrFileRef.value) ocrFileRef.value.value = ''
}
</script>

<template>
    <Head :title="`${student.name} · ${exam.title}`" />
    <AppLayout :breadcrumbs="[
        { label: 'Board Results', href: route('board-results.index') },
        { label: exam.title, href: route('board-results.show', exam.id) },
        { label: student.name }
    ]">
        <div class="space-y-4 max-w-6xl mx-auto">
            <PageHeader :title="student.name"
                        :subtitle="`Roll ${student.roll_no || '—'} · ${student.father_name || ''} · ${exam.title}`"
                        :icon="AcademicCapIcon" tone="violet">
                <template #actions>
                    <button v-if="canEdit" @click="openOcr"
                            class="btn btn-outline btn-sm rounded-xl gap-1.5 border-fuchsia-500/40 text-fuchsia-700 hover:bg-fuchsia-500/10"
                            title="Snap the FBISE gazette page — Gemini reads the marks and pre-fills the form.">
                        <CameraIcon class="w-4 h-4" /> OCR Photo
                    </button>
                    <Link :href="route('board-results.show', exam.id)" class="btn btn-ghost btn-sm rounded-xl gap-1.5">
                        <ArrowLeftIcon class="w-4 h-4" /> Back to list
                    </Link>
                </template>
            </PageHeader>

            <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <!-- LEFT: subjects table -->
                <div class="lg:col-span-2 rounded-2xl bg-base-100 border border-base-300/70 shadow-sm overflow-hidden">
                    <header class="px-5 py-3.5 border-b border-base-200 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white
                                        flex items-center justify-center shadow-sm shadow-violet-500/25">
                                <DocumentCheckIcon class="w-4 h-4" />
                            </div>
                            <h3 class="text-sm font-bold">Subject-wise Marks</h3>
                        </div>
                        <div class="form-control w-40">
                            <input v-model="form.board_roll_no" type="text" placeholder="Board Roll #"
                                   class="input input-bordered input-xs" />
                        </div>
                    </header>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider font-bold text-base-content/55">
                                <tr>
                                    <th class="text-center px-3 py-2 w-10">✓</th>
                                    <th class="text-left px-2 py-2">Subject</th>
                                    <th class="text-right px-2 py-2 w-24">Theory<br/><span class="text-base-content/40 normal-case">/ max</span></th>
                                    <th class="text-right px-2 py-2 w-24">Practical<br/><span class="text-base-content/40 normal-case">/ max</span></th>
                                    <th class="text-right px-2 py-2 w-20">Total</th>
                                    <th class="text-center px-2 py-2 w-14">Grade</th>
                                    <th class="text-center px-3 py-2 w-14">Abs</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-base-200">
                                <tr v-for="(s, i) in form.subjects" :key="s.subject_id"
                                    :class="{ 'opacity-40': !s.included }">
                                    <td class="text-center">
                                        <input type="checkbox" v-model="s.included" class="checkbox checkbox-xs" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <p class="font-semibold text-sm">{{ s.name }}</p>
                                        <p v-if="s.code" class="text-[10px] text-base-content/50">{{ s.code }}</p>
                                    </td>
                                    <td class="px-2 py-2">
                                        <div class="flex items-center gap-1 justify-end">
                                            <input v-model.number="s.theory_marks" type="number" step="0.5" min="0"
                                                   :disabled="!s.included || s.is_absent"
                                                   :max="s.theory_max"
                                                   class="input input-bordered input-xs w-14 text-right tabular-nums" />
                                            <span class="text-[10px] text-base-content/40">/</span>
                                            <input v-model.number="s.theory_max" type="number" min="0" max="200"
                                                   class="input input-bordered input-xs w-10 text-right tabular-nums text-[10px] text-base-content/50" />
                                        </div>
                                    </td>
                                    <td class="px-2 py-2">
                                        <div class="flex items-center gap-1 justify-end">
                                            <input v-model.number="s.practical_marks" type="number" step="0.5" min="0"
                                                   :disabled="!s.included || s.is_absent"
                                                   :max="s.practical_max"
                                                   class="input input-bordered input-xs w-14 text-right tabular-nums" />
                                            <span class="text-[10px] text-base-content/40">/</span>
                                            <input v-model.number="s.practical_max" type="number" min="0" max="200"
                                                   class="input input-bordered input-xs w-10 text-right tabular-nums text-[10px] text-base-content/50" />
                                        </div>
                                    </td>
                                    <td class="px-2 py-2 text-right tabular-nums font-bold">
                                        {{ subjectTotal(s) }} / {{ subjectMax(s) }}
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <span v-if="s.included"
                                              :class="['px-1.5 py-0.5 rounded font-bold text-[10px]', gradePill(subjectGrade(s))]">
                                            {{ subjectGrade(s) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <input type="checkbox" v-model="s.is_absent" :disabled="!s.included"
                                               class="checkbox checkbox-xs" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- RIGHT: live preview + save -->
                <div class="space-y-4">
                    <!-- Aggregate preview -->
                    <div class="rounded-2xl bg-gradient-to-br from-primary/[0.06] via-base-100 to-base-100
                                border border-primary/15 shadow-sm p-5">
                        <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-primary/70">Live Preview</p>
                        <div class="mt-3 space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/60">Total</span>
                                <span class="font-bold tabular-nums">{{ totalObtained }} / {{ totalMax }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/60">Percentage</span>
                                <span class="font-extrabold text-lg tabular-nums text-primary">{{ overallPct }}%</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/60">Grade</span>
                                <span :class="['px-2.5 py-1 rounded-lg font-bold text-xs', gradePill(overallGrade)]">
                                    {{ overallGrade }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/60">Division</span>
                                <span class="font-bold"
                                      :class="isPass ? 'text-emerald-700 dark:text-emerald-300'
                                            : isSupply ? 'text-sky-700 dark:text-sky-300'
                                            : 'text-rose-700 dark:text-rose-300'">
                                    {{ isSupply ? 'Supply' : division }}
                                </span>
                            </div>
                            <div class="pt-2 border-t border-base-200 flex items-center justify-between">
                                <span class="text-base-content/60">Result</span>
                                <span class="inline-flex items-center gap-1 font-bold text-xs"
                                      :class="isPass ? 'text-emerald-700 dark:text-emerald-300'
                                            : 'text-rose-700 dark:text-rose-300'">
                                    <CheckCircleIcon v-if="isPass" class="w-4 h-4" />
                                    <XCircleIcon v-else class="w-4 h-4" />
                                    {{ isPass ? 'Passed' : (isSupply ? 'Supplementary' : 'Failed') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm p-4">
                        <label class="label pb-1"><span class="label-text font-semibold text-xs">Remarks</span></label>
                        <textarea v-model="form.remarks" rows="3"
                                  class="textarea textarea-bordered w-full text-xs"
                                  placeholder="Any notes about this student's result (optional)…"></textarea>
                    </div>

                    <!-- Save -->
                    <button type="submit" :disabled="form.processing || !canEdit"
                            class="btn btn-primary w-full rounded-xl gap-1.5">
                        <span v-if="form.processing" class="loading loading-spinner loading-xs"></span>
                        <DocumentCheckIcon v-else class="w-4 h-4" />
                        Save Result
                    </button>
                    <p v-if="!canEdit" class="text-[11px] text-amber-700 dark:text-amber-300 text-center">
                        This board exam is locked. Ask admin to unlock before saving edits.
                    </p>

                    <!-- Errors -->
                    <div v-if="Object.keys(form.errors).length"
                         class="rounded-xl border border-error/30 bg-error/10 p-3 text-xs">
                        <p class="font-bold text-error mb-1">Please fix:</p>
                        <ul class="list-disc pl-4 space-y-0.5">
                            <li v-for="(msg, key) in form.errors" :key="key">{{ msg }}</li>
                        </ul>
                    </div>
                </div>
            </form>
        </div>

        <!-- ═══ OCR modal ═══
             Snap → server calls Gemini → returns matched subject rows →
             user Applies (fills the form) or cancels. -->
        <Teleport to="body">
            <div v-if="ocrOpen" class="fixed inset-0 z-50 bg-black/60 flex items-start sm:items-center justify-center p-3 overflow-y-auto">
                <div class="w-full max-w-2xl bg-base-100 rounded-2xl shadow-2xl my-4">
                    <header class="flex items-center justify-between p-4 sm:p-5 border-b border-base-200">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-fuchsia-500 to-pink-600 text-white flex items-center justify-center shadow-sm">
                                <CameraIcon class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="text-base font-bold">FBISE Gazette OCR</h3>
                                <p class="text-xs text-base-content/60">
                                    Snap this student's gazette page — Gemini reads the marks and pre-fills the form.
                                </p>
                            </div>
                        </div>
                        <button @click="closeOcr" class="btn btn-sm btn-ghost btn-circle">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </header>

                    <div class="p-4 sm:p-5 space-y-4">
                        <!-- Upload area -->
                        <div v-if="!ocrPreview && !ocrBusy" @click="pickOcrFile"
                             class="border-2 border-dashed border-base-300 rounded-xl p-6 sm:p-8 text-center cursor-pointer hover:bg-base-200/40 transition-colors">
                            <ArrowUpTrayIcon class="w-10 h-10 mx-auto text-base-content/40 mb-2" />
                            <div class="font-semibold text-sm">Tap to select or take a photo of the FBISE gazette</div>
                            <div class="text-xs text-base-content/60 mt-1">
                                Just this student's row — cleanly cropped, well-lit gives best accuracy.
                            </div>
                        </div>

                        <div v-if="ocrBusy" class="rounded-xl border border-base-200 p-4 text-center">
                            <span class="loading loading-spinner loading-sm"></span>
                            <p class="text-xs text-base-content/60 mt-2">Reading the gazette…</p>
                        </div>

                        <div v-if="ocrError"
                             class="rounded-xl border border-error/30 bg-error/10 p-3 text-sm text-error">
                            <ExclamationTriangleIcon class="w-5 h-5 inline mr-1" />
                            {{ ocrError }}
                        </div>

                        <div v-if="ocrPreview" class="space-y-3">
                            <div v-if="ocrPreview.board_roll_no"
                                 class="rounded-lg bg-emerald-500/10 border border-emerald-500/20 px-3 py-2 text-xs">
                                <b class="text-emerald-700 dark:text-emerald-300">Board Roll #</b>
                                <span class="ml-2 font-mono tabular-nums">{{ ocrPreview.board_roll_no }}</span>
                            </div>

                            <div class="rounded-xl border border-base-200 overflow-hidden">
                                <table class="w-full text-xs">
                                    <thead class="bg-base-200/50 text-[10px] uppercase tracking-wider font-bold text-base-content/60">
                                        <tr>
                                            <th class="text-left px-3 py-2">Subject (from photo)</th>
                                            <th class="text-right px-2 py-2">Theory</th>
                                            <th class="text-right px-2 py-2">Practical</th>
                                            <th class="text-center px-3 py-2">Match</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-base-200">
                                        <tr v-for="(s, i) in ocrPreview.subjects" :key="i">
                                            <td class="px-3 py-1.5">
                                                <p class="font-semibold">{{ s.subject_name }}</p>
                                                <p v-if="!s.matched" class="text-[10px] text-rose-600">
                                                    read as “{{ s.raw_name }}” — not in this class's subject list
                                                </p>
                                            </td>
                                            <td class="px-2 py-1.5 text-right tabular-nums">{{ s.theory_marks ?? '—' }}</td>
                                            <td class="px-2 py-1.5 text-right tabular-nums">{{ s.practical_marks ?? '—' }}</td>
                                            <td class="px-3 py-1.5 text-center">
                                                <CheckCircleIcon v-if="s.matched" class="w-4 h-4 text-emerald-600 inline" />
                                                <XCircleIcon v-else class="w-4 h-4 text-rose-500 inline" />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-[11px] text-base-content/50 text-center">
                                Only matched subjects will fill the form. You can still edit any value after applying.
                            </p>
                        </div>
                    </div>

                    <input ref="ocrFileRef" type="file" accept="image/*" capture="environment"
                           class="hidden" @change="onOcrFileSelected" />

                    <footer class="flex items-center justify-end gap-2 p-4 border-t border-base-200 bg-base-200/30">
                        <button @click="closeOcr" class="btn btn-sm btn-ghost">Cancel</button>
                        <button v-if="ocrPreview" @click="pickOcrFile" class="btn btn-sm btn-outline">Try another</button>
                        <button v-if="ocrPreview" @click="applyOcr"
                                class="btn btn-sm btn-primary gap-1.5">
                            <CheckCircleIcon class="w-4 h-4" />
                            Apply to form
                        </button>
                    </footer>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
