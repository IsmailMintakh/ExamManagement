<script setup>
/**
 * Public FBISE result lookup page.
 *
 * Anyone (no login) can search their result: pick the exam from the
 * dropdown (only locked/finalised exams appear), type the board roll
 * number, get the full mark-sheet card rendered inline.
 *
 * Two states: [search form] → [result card].
 */
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import axios from 'axios'
import {
    AcademicCapIcon, MagnifyingGlassIcon, ArrowLeftIcon,
    CheckCircleIcon, XCircleIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exams: { type: Array, default: () => [] },
})

const boardExamId = ref(props.exams[0]?.id ?? null)
const boardRollNo = ref('')
const busy = ref(false)
const error = ref('')
const found = ref(null)     // { exam, result } when found
const notFound = ref(null)  // error message when the lookup returned no match

async function submit() {
    if (!boardExamId.value || !boardRollNo.value.trim()) return
    busy.value = true
    error.value = ''
    found.value = null
    notFound.value = null
    try {
        const { data } = await axios.post('/board-result-search', {
            board_exam_id: boardExamId.value,
            board_roll_no: boardRollNo.value.trim(),
        })
        if (data.found) {
            found.value = data
        } else {
            notFound.value = data.message || 'No result found.'
        }
    } catch (e) {
        error.value = e?.response?.data?.message || e?.message || 'Search failed.'
    } finally {
        busy.value = false
    }
}

function reset() {
    found.value = null
    notFound.value = null
    boardRollNo.value = ''
}

// Group locked exams by school for the dropdown.
const examOptions = computed(() => {
    const groups = new Map()
    for (const e of props.exams) {
        const key = e.school?.name || 'School'
        if (!groups.has(key)) groups.set(key, [])
        groups.get(key).push(e)
    }
    return Array.from(groups.entries())
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
</script>

<template>
    <Head title="Result Search" />
    <div class="min-h-screen bg-gradient-to-br from-primary/[0.04] via-base-200 to-base-200 py-8 sm:py-14">
        <div class="max-w-3xl mx-auto px-3 sm:px-4">
            <!-- Header banner -->
            <div class="text-center mb-6">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-gradient-to-br from-primary to-teal-600 text-primary-content
                            flex items-center justify-center shadow-lg shadow-primary/25 mb-3">
                    <AcademicCapIcon class="w-7 h-7" />
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">FBISE Result Search</h1>
                <p class="text-sm text-base-content/60 mt-2 max-w-md mx-auto">
                    Enter your board roll number and pick your exam to see the result instantly.
                </p>
            </div>

            <!-- Search form -->
            <form v-if="!found" @submit.prevent="submit"
                  class="rounded-2xl bg-base-100 border border-base-300/70 shadow-md p-5 sm:p-6 space-y-4">
                <div class="form-control">
                    <label class="label pb-1"><span class="label-text font-semibold text-sm">Exam</span></label>
                    <select v-model="boardExamId" class="select select-bordered w-full" required>
                        <option :value="null" disabled>Pick an exam…</option>
                        <optgroup v-for="[schoolName, list] in examOptions" :key="schoolName" :label="schoolName">
                            <option v-for="e in list" :key="e.id" :value="e.id">
                                {{ e.title }} — Class {{ e.school_class?.name }} ({{ e.academic_session?.name }})
                            </option>
                        </optgroup>
                    </select>
                    <p v-if="!exams.length" class="text-xs text-warning mt-1">
                        No published exams available for search yet.
                    </p>
                </div>

                <div class="form-control">
                    <label class="label pb-1"><span class="label-text font-semibold text-sm">Board Roll Number</span></label>
                    <input v-model="boardRollNo" type="text" class="input input-bordered w-full uppercase tabular-nums"
                           placeholder="e.g. 123456" autocomplete="off" required />
                </div>

                <div v-if="notFound"
                     class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-200">
                    <ExclamationTriangleIcon class="w-5 h-5 inline mr-1" />
                    {{ notFound }}
                </div>
                <div v-if="error"
                     class="rounded-xl border border-error/30 bg-error/10 p-3 text-sm text-error">
                    {{ error }}
                </div>

                <button type="submit" :disabled="busy || !exams.length"
                        class="btn btn-primary w-full rounded-xl gap-1.5">
                    <span v-if="busy" class="loading loading-spinner loading-sm"></span>
                    <MagnifyingGlassIcon v-else class="w-5 h-5" />
                    Search
                </button>

                <p class="text-[11px] text-base-content/50 text-center">
                    This search returns only results that have been officially finalised by the school.
                </p>
            </form>

            <!-- Result card (inline) -->
            <div v-else class="space-y-4">
                <button @click="reset" class="btn btn-ghost btn-sm rounded-xl gap-1.5">
                    <ArrowLeftIcon class="w-4 h-4" /> Search another
                </button>

                <div class="rounded-2xl bg-base-100 border border-base-300/70 shadow-md p-5 sm:p-6">
                    <!-- Header -->
                    <header class="text-center border-b border-base-200 pb-4 mb-4">
                        <p class="text-lg font-extrabold">{{ found.exam.school?.name }}</p>
                        <p class="text-sm text-base-content/60">
                            FBISE {{ found.exam.level }} · {{ found.exam.title }}
                        </p>
                        <p class="text-xs text-base-content/50 mt-0.5">
                            Class {{ found.exam.school_class?.name }} · {{ found.exam.academic_session?.name }}
                        </p>
                    </header>

                    <!-- Student info -->
                    <div class="grid grid-cols-2 gap-3 text-sm mb-4">
                        <div>
                            <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Name</p>
                            <p class="font-semibold">{{ found.result.student.name }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Father's Name</p>
                            <p class="font-semibold">{{ found.result.student.father_name }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Board Roll</p>
                            <p class="font-semibold tabular-nums">{{ found.result.board_roll_no }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Class Roll</p>
                            <p class="font-semibold tabular-nums">{{ found.result.student.roll_no || '—' }}</p>
                        </div>
                    </div>

                    <!-- Subjects table -->
                    <div class="rounded-xl border border-base-200 overflow-hidden mb-4">
                        <table class="w-full text-xs">
                            <thead class="bg-base-200/50 text-[10px] uppercase tracking-wider font-bold text-base-content/60">
                                <tr>
                                    <th class="text-left px-3 py-2">Subject</th>
                                    <th class="text-right px-2 py-2">Theory</th>
                                    <th class="text-right px-2 py-2">Practical</th>
                                    <th class="text-right px-2 py-2">Total</th>
                                    <th class="text-center px-2 py-2">Grade</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-base-200">
                                <tr v-for="sub in found.result.subjects" :key="sub.id">
                                    <td class="px-3 py-2 font-semibold">{{ sub.subject?.name }}</td>
                                    <td class="px-2 py-2 text-right tabular-nums">{{ sub.theory_marks }} / {{ sub.theory_max }}</td>
                                    <td class="px-2 py-2 text-right tabular-nums">{{ sub.practical_marks }} / {{ sub.practical_max }}</td>
                                    <td class="px-2 py-2 text-right tabular-nums font-bold">{{ sub.total_marks }} / {{ sub.max_marks }}</td>
                                    <td class="px-2 py-2 text-center">
                                        <span :class="['px-1.5 py-0.5 rounded font-bold text-[10px]', gradePill(sub.grade)]">
                                            {{ sub.grade }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Aggregate strip -->
                    <div class="grid grid-cols-3 sm:grid-cols-5 gap-2 mb-4">
                        <div class="rounded-lg border border-base-200 p-2.5 text-center">
                            <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Obtained</p>
                            <p class="text-base font-extrabold tabular-nums mt-0.5">{{ found.result.total_obtained }}</p>
                        </div>
                        <div class="rounded-lg border border-base-200 p-2.5 text-center">
                            <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Out of</p>
                            <p class="text-base font-extrabold tabular-nums mt-0.5">{{ found.result.total_max }}</p>
                        </div>
                        <div class="rounded-lg border border-base-200 p-2.5 text-center">
                            <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Percentage</p>
                            <p class="text-base font-extrabold tabular-nums mt-0.5 text-primary">{{ Number(found.result.percentage).toFixed(2) }}%</p>
                        </div>
                        <div class="rounded-lg border border-base-200 p-2.5 text-center">
                            <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Grade</p>
                            <p class="mt-0.5"><span :class="['px-2 py-0.5 rounded font-bold text-xs', gradePill(found.result.grade)]">{{ found.result.grade }}</span></p>
                        </div>
                        <div class="rounded-lg border border-base-200 p-2.5 text-center">
                            <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Position</p>
                            <p class="text-base font-extrabold tabular-nums mt-0.5">{{ found.result.position ? '#' + found.result.position : '—' }}</p>
                        </div>
                    </div>

                    <!-- Result banner -->
                    <div class="rounded-xl border-2 text-center py-3 font-bold text-lg"
                         :class="found.result.is_pass
                            ? 'border-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                            : found.result.is_supplementary
                                ? 'border-sky-600 bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300'
                                : 'border-rose-600 bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300'">
                        <CheckCircleIcon v-if="found.result.is_pass" class="w-5 h-5 inline mr-1" />
                        <XCircleIcon v-else class="w-5 h-5 inline mr-1" />
                        {{ found.result.is_pass
                            ? `PASSED · ${(found.result.division || '').toUpperCase()} DIVISION`
                            : found.result.is_supplementary ? 'SUPPLEMENTARY' : 'NOT PASSED' }}
                    </div>

                    <p class="text-[11px] text-base-content/50 text-center mt-4 italic">
                        Computer-generated. The official FBISE result gazette remains authoritative.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
