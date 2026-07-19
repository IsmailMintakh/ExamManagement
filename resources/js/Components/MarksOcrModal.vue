<script setup>
/**
 * MarksOcrModal — upload a photo of handwritten "roll_no marks" pairs.
 *
 * Server does the OCR (Groq Llama vision LLM, free tier) — because
 * Tesseract can't read regionally-styled handwritten digits like a "5"
 * written cursively as "S" or a "4" written open-top as "u". An LLM
 * with vision uses page context ("this is a roll/marks table") and
 * handles those variants far better.
 *
 * Flow: file picker → POST /marks/ocr → { pairs: [{roll_no, marks}...] }
 * → editable review table → user confirms → emit('apply') to parent.
 * Nothing auto-saves; the review step is mandatory.
 */
import axios from 'axios'
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import {
    XMarkIcon, CameraIcon, ArrowUpTrayIcon, ArrowPathIcon,
    CheckCircleIcon, ExclamationTriangleIcon, TrashIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    show: { type: Boolean, required: true },
    // Max valid mark — used to flag values > this as suspicious in the
    // review table (highlighted red so the teacher spots them fast).
    totalMarks: { type: Number, default: 100 },
    // Every valid roll number for THIS section — anything extracted that
    // isn't in this set gets highlighted so misreads are caught.
    validRollNumbers: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'apply'])

// ─── State ───
const fileInputRef = ref(null)
const previewUrl = ref(null)
const processing = ref(false)
const progressStatus = ref('')
const extracted = ref([])   // [{ roll_no: '1', marks: '19', include: true }]
const errorMsg = ref('')

// Roll-number comparison is loose: "02" from the DB should match "2"
// from OCR (and vice-versa). Strip leading zeros before comparison.
// "0" stays "0", "01" → "1", "A12" → "A12" (non-numeric prefixes safe).
const normalizeRoll = (s) => String(s ?? '').trim().replace(/^0+(?=\d)/, '')
const validRollSet = computed(() => new Set(props.validRollNumbers.map(normalizeRoll)))

const includedCount = computed(() => extracted.value.filter(r => r.include).length)
const suspiciousCount = computed(() =>
    extracted.value.filter(r => r.include && rowIssue(r)).length
)

function rowIssue(row) {
    if (!row.roll_no || row.roll_no === '') return 'missing_roll'
    if (validRollSet.value.size > 0 && !validRollSet.value.has(normalizeRoll(row.roll_no))) return 'unknown_roll'
    if (row.marks === '' || row.marks == null) return 'missing_marks'
    const n = Number(String(row.marks).replace(',', '.'))
    if (!Number.isFinite(n)) return 'bad_marks'
    if (n < 0) return 'negative'
    if (props.totalMarks && n > props.totalMarks) return 'over_max'
    return null
}

function issueLabel(kind) {
    return {
        missing_roll: 'No roll #',
        unknown_roll: 'Not in section',
        missing_marks: 'No marks',
        bad_marks: 'Not a number',
        negative: 'Negative',
        over_max: `> ${props.totalMarks}`,
    }[kind] || ''
}

// ─── File picker ───
function pickFile() { fileInputRef.value?.click() }

async function onFileSelected(event) {
    const file = event.target.files?.[0]
    if (!file) return
    if (!file.type.startsWith('image/')) {
        errorMsg.value = 'Please upload an image file (jpg / png).'
        return
    }
    reset()
    previewUrl.value = URL.createObjectURL(file)
    await runOcr(file)
}

function reset() {
    errorMsg.value = ''
    extracted.value = []
    progressStatus.value = ''
    if (previewUrl.value) { URL.revokeObjectURL(previewUrl.value); previewUrl.value = null }
}

// ─── OCR (server-side) ───
// Ship the image straight to /marks/ocr — the backend forwards it to a
// vision LLM and returns { pairs: [{roll_no, marks}] }. Client keeps the
// original image (no lossy preprocessing) because the LLM handles color
// / contrast / shadows itself.
async function runOcr(file) {
    processing.value = true
    errorMsg.value = ''
    progressStatus.value = 'Reading photo…'

    const form = new FormData()
    form.append('image', file)
    if (props.totalMarks) form.append('max_marks', String(props.totalMarks))

    try {
        const { data } = await axios.post('/marks/ocr', form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        if (!data.ok) {
            errorMsg.value = data.error || 'Could not read the photo.'
            return
        }
        extracted.value = (data.pairs || []).map(p => ({
            roll_no: String(p.roll_no ?? ''),
            marks:   String(p.marks   ?? ''),
            include: true,
        }))
        if (extracted.value.length === 0) {
            errorMsg.value = 'No roll numbers detected. Try a clearer photo, or add rows manually below.'
        } else {
            progressStatus.value = `Read ${extracted.value.length} row${extracted.value.length === 1 ? '' : 's'}`
        }
    } catch (e) {
        errorMsg.value = e?.response?.data?.error
            || e?.message
            || 'Upload failed. Check your connection and try again.'
    } finally {
        processing.value = false
    }
}

// ─── Table actions ───
function removeRow(idx) { extracted.value.splice(idx, 1) }
function addBlankRow() { extracted.value.push({ roll_no: '', marks: '', include: true }) }
function toggleAll(val) { extracted.value.forEach(r => r.include = val) }

function apply() {
    const usable = extracted.value
        .filter(r => r.include && r.roll_no !== '' && r.marks !== '')
    emit('apply', usable.map(r => ({ roll_no: String(r.roll_no), marks: String(r.marks) })))
    close()
}

function close() {
    reset()
    emit('close')
}

onBeforeUnmount(() => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
})

// Clear the file input value on close so re-selecting the SAME file re-fires @change.
watch(() => props.show, (v) => {
    if (!v && fileInputRef.value) fileInputRef.value.value = ''
})
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 bg-black/60 flex items-start sm:items-center justify-center p-2 sm:p-4 overflow-y-auto">
            <div class="w-full max-w-3xl bg-base-100 rounded-2xl shadow-2xl my-4 sm:my-8">
                <!-- Header -->
                <div class="flex items-center justify-between p-4 sm:p-5 border-b border-base-200">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary/15 text-primary flex items-center justify-center">
                            <CameraIcon class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold leading-tight">Upload marks photo</h3>
                            <p class="text-xs text-base-content/60 leading-tight mt-0.5">
                                Scans a picture of roll numbers + marks. Review each row before applying.
                            </p>
                        </div>
                    </div>
                    <button @click="close" class="btn btn-sm btn-ghost btn-circle">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-4 sm:p-5 space-y-4">
                    <!-- Upload area -->
                    <div v-if="!previewUrl"
                         @click="pickFile"
                         class="border-2 border-dashed border-base-300 rounded-xl p-6 sm:p-8 text-center cursor-pointer hover:bg-base-200/40 transition-colors">
                        <ArrowUpTrayIcon class="w-10 h-10 mx-auto text-base-content/40 mb-2" />
                        <div class="font-semibold text-sm sm:text-base">Tap to select a photo or take one</div>
                        <div class="text-xs text-base-content/60 mt-1">
                            Handwritten "roll marks" list, one pair per line. Better light = better accuracy.
                        </div>
                        <div class="text-[11px] text-base-content/50 mt-3 leading-relaxed">
                            Example:<br/>
                            <span class="font-mono">1&nbsp;&nbsp;19</span><br/>
                            <span class="font-mono">2&nbsp;&nbsp;15</span><br/>
                            <span class="font-mono">3&nbsp;&nbsp;22</span>
                        </div>
                    </div>

                    <!-- Preview + progress -->
                    <div v-if="previewUrl" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <div class="text-[10px] uppercase tracking-widest font-semibold text-base-content/60 mb-1">Uploaded image</div>
                            <img :src="previewUrl" alt="uploaded"
                                 class="w-full rounded-xl border border-base-200 max-h-64 object-contain bg-base-200/40" />
                            <button @click="pickFile" class="btn btn-sm btn-ghost mt-2 gap-1.5">
                                <ArrowPathIcon class="w-4 h-4" /> Choose different
                            </button>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase tracking-widest font-semibold text-base-content/60 mb-1">OCR status</div>
                            <div v-if="processing" class="rounded-xl border border-base-200 p-4 space-y-2">
                                <div class="text-xs flex items-center gap-2">
                                    <span class="loading loading-spinner loading-xs"></span>
                                    {{ progressStatus || 'Reading photo…' }}
                                </div>
                                <progress class="progress progress-primary w-full"></progress>
                            </div>
                            <div v-else-if="errorMsg" class="rounded-xl border border-error/30 bg-error/10 p-4 text-sm text-error">
                                <ExclamationTriangleIcon class="w-5 h-5 inline mr-1" />
                                {{ errorMsg }}
                            </div>
                            <div v-else-if="extracted.length" class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm">
                                <CheckCircleIcon class="w-5 h-5 inline mr-1 text-emerald-600" />
                                Found <b>{{ extracted.length }}</b> pair{{ extracted.length === 1 ? '' : 's' }}.
                                <span v-if="suspiciousCount > 0" class="text-amber-700 dark:text-amber-300 block mt-1">
                                    <ExclamationTriangleIcon class="w-4 h-4 inline" />
                                    <b>{{ suspiciousCount }}</b> need a look.
                                </span>
                            </div>
                            <div v-else class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-200">
                                No numbers detected. Try a clearer photo or add rows manually below.
                            </div>
                        </div>
                    </div>

                    <input ref="fileInputRef" type="file" accept="image/*" capture="environment"
                           class="hidden" @change="onFileSelected" />

                    <!-- Editable review table -->
                    <div v-if="extracted.length || (!processing && previewUrl)" class="border border-base-200 rounded-xl overflow-hidden">
                        <div class="flex items-center justify-between px-3 py-2 bg-base-200/60 text-xs">
                            <div class="font-semibold">Review &amp; fix before applying</div>
                            <div class="flex items-center gap-2">
                                <button @click="toggleAll(true)"  class="btn btn-xs btn-ghost">All</button>
                                <button @click="toggleAll(false)" class="btn btn-xs btn-ghost">None</button>
                                <button @click="addBlankRow"      class="btn btn-xs btn-outline">+ Row</button>
                            </div>
                        </div>
                        <div class="overflow-x-auto max-h-72">
                            <table class="table table-sm">
                                <thead class="sticky top-0 bg-base-100">
                                    <tr>
                                        <th class="w-10 text-center">✓</th>
                                        <th class="w-24">Roll #</th>
                                        <th class="w-24">Marks</th>
                                        <th>Issue</th>
                                        <th class="w-10"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, i) in extracted" :key="i"
                                        :class="{ 'opacity-40': !row.include, 'bg-error/5': row.include && rowIssue(row) }">
                                        <td class="text-center">
                                            <input type="checkbox" v-model="row.include" class="checkbox checkbox-xs" />
                                        </td>
                                        <td>
                                            <input v-model="row.roll_no" type="text"
                                                   class="input input-xs input-bordered w-20 tabular-nums"
                                                   inputmode="numeric" />
                                        </td>
                                        <td>
                                            <input v-model="row.marks" type="text"
                                                   class="input input-xs input-bordered w-20 tabular-nums"
                                                   inputmode="decimal" />
                                        </td>
                                        <td class="text-xs">
                                            <span v-if="row.include && rowIssue(row)" class="text-error font-semibold">
                                                {{ issueLabel(rowIssue(row)) }}
                                            </span>
                                        </td>
                                        <td>
                                            <button @click="removeRow(i)" class="btn btn-xs btn-ghost text-error">
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!extracted.length">
                                        <td colspan="5" class="text-center text-base-content/50 py-4 text-xs">
                                            No rows. Click <b>+ Row</b> to add manually.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between gap-2 p-4 border-t border-base-200 bg-base-200/30">
                    <div class="text-xs text-base-content/60">
                        <span v-if="includedCount > 0">
                            Will apply <b>{{ includedCount }}</b> row{{ includedCount === 1 ? '' : 's' }} to the form.
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="close" class="btn btn-sm btn-ghost">Cancel</button>
                        <button @click="apply"
                                :disabled="includedCount === 0"
                                class="btn btn-sm btn-primary gap-1.5">
                            <CheckCircleIcon class="w-4 h-4" />
                            Apply to form
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
