<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    AcademicCapIcon, MagnifyingGlassIcon, IdentificationIcon,
    CheckCircleIcon, XCircleIcon, ChartBarIcon, TrophyIcon,
    ArrowLeftIcon, ExclamationCircleIcon, InformationCircleIcon,
    ClipboardDocumentListIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    publishedExams: { type: Array, default: () => [] },
    searched: Boolean,
    student: Object,
    exam: Object,
    result: Object,
    noResult: Boolean,
    error: String,
    admission_no: String,
    exam_id: Number,
})

const form = useForm({
    exam_id: props.exam_id || '',
    admission_no: props.admission_no || '',
})

function submit() {
    form.post(route('public.result-lookup.search'), {
        preserveScroll: true,
        preserveState: false,
    })
}

function fmtPct(v) {
    if (v === null || v === undefined) return '—'
    return `${Number(v).toFixed(2)}%`
}

// Hide the form and show a "search again" button after a successful match.
const showForm = ref(!props.searched || props.error || !props.student)

// No exams published yet — show a helpful banner instead of an empty form.
const nothingPublished = computed(() => (props.publishedExams?.length ?? 0) === 0)
</script>

<template>
    <Head title="Check Result" />
    <div class="min-h-screen bg-gradient-to-br from-emerald-50 via-white to-amber-50 flex items-start justify-center p-4 py-10">
        <div class="w-full max-w-3xl">

            <!-- Hero header -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-700 to-emerald-900 shadow-lg mb-3">
                    <AcademicCapIcon class="w-7 h-7 text-amber-300" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight">Check Your Exam Result</h1>
                <p class="text-sm text-stone-600 mt-1">
                    Pick the exam and enter your admission number.
                </p>
            </div>

            <!-- Empty state when nothing has been published yet. -->
            <div v-if="nothingPublished"
                class="bg-white rounded-2xl shadow-xl border border-stone-200 p-8 text-center">
                <ClipboardDocumentListIcon class="w-12 h-12 text-stone-300 mx-auto mb-3" />
                <p class="font-bold text-stone-700">No results announced yet</p>
                <p class="text-sm text-stone-500 mt-1.5 max-w-sm mx-auto">
                    The school hasn't published any exam results to the public lookup. Please check back after the result announcement.
                </p>
                <Link :href="route('login')" class="inline-block mt-4 text-sm font-semibold text-emerald-700 hover:underline">
                    Sign in to the Family Portal →
                </Link>
            </div>

            <!-- Lookup form -->
            <div v-else-if="showForm" class="bg-white rounded-2xl shadow-xl border border-stone-200 overflow-hidden">
                <div class="p-6">
                    <form @submit.prevent="submit" class="space-y-4">

                        <!-- Exam selector — only shows currently-published exams. -->
                        <div>
                            <label class="block text-sm font-semibold text-stone-700 mb-1.5">
                                Exam
                            </label>
                            <div class="relative">
                                <ClipboardDocumentListIcon class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-stone-400" />
                                <select
                                    v-model="form.exam_id"
                                    required
                                    class="w-full pl-10 pr-3 py-2.5 rounded-lg border-2 border-stone-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 outline-none transition appearance-none bg-white"
                                >
                                    <option value="">Select exam...</option>
                                    <option v-for="e in publishedExams" :key="e.id" :value="e.id">
                                        {{ e.label }}
                                    </option>
                                </select>
                            </div>
                            <p v-if="form.errors.exam_id" class="text-xs text-red-600 mt-1">{{ form.errors.exam_id }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-stone-700 mb-1.5">
                                Admission Number
                            </label>
                            <div class="relative">
                                <IdentificationIcon class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-stone-400" />
                                <input
                                    v-model="form.admission_no"
                                    type="text"
                                    placeholder="e.g. 100"
                                    required
                                    class="w-full pl-10 pr-3 py-2.5 rounded-lg border-2 border-stone-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 outline-none transition" />
                            </div>
                            <p v-if="form.errors.admission_no" class="text-xs text-red-600 mt-1">{{ form.errors.admission_no }}</p>
                            <p class="text-[11px] text-stone-500 mt-1">Find it on your admit card or school ID.</p>
                        </div>

                        <button type="submit"
                            :disabled="form.processing"
                            class="w-full bg-gradient-to-r from-emerald-600 to-emerald-700 text-white font-semibold py-2.5 rounded-lg hover:shadow-lg hover:from-emerald-700 hover:to-emerald-800 transition-all disabled:opacity-50 flex items-center justify-center gap-2">
                            <MagnifyingGlassIcon class="w-5 h-5" />
                            <span v-if="form.processing">Searching…</span>
                            <span v-else>Check Result</span>
                        </button>
                    </form>

                    <div v-if="error" class="mt-5 rounded-xl bg-red-50 border border-red-200 p-4 flex items-start gap-3">
                        <ExclamationCircleIcon class="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
                        <div class="text-sm text-red-800">{{ error }}</div>
                    </div>
                </div>

                <div class="bg-stone-50 border-t border-stone-200 p-4 text-center text-[11px] text-stone-500">
                    Only <strong>published</strong> exam results are shown. If your school hasn't announced a result yet, the exam won't appear in the dropdown above.
                </div>
            </div>

            <!-- Result display -->
            <div v-else class="space-y-5">
                <button @click="showForm = true; form.reset()" type="button"
                    class="text-sm font-semibold text-emerald-700 hover:text-emerald-800 flex items-center gap-1">
                    <ArrowLeftIcon class="w-4 h-4" /> Search another result
                </button>

                <!-- Student card -->
                <div class="bg-white rounded-2xl shadow-md border border-stone-200 overflow-hidden">
                    <div class="bg-gradient-to-br from-emerald-700 to-emerald-900 text-white p-5">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-emerald-100/85 font-semibold">Student</p>
                        <h2 class="text-xl font-bold mt-1">{{ student?.name }}</h2>
                        <p class="text-sm text-emerald-100/85 mt-1">{{ student?.school_name }}</p>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-px bg-stone-200">
                        <div class="bg-white p-3">
                            <p class="text-[10px] uppercase tracking-wider text-stone-500 font-bold">Admission #</p>
                            <p class="text-sm font-bold mt-0.5">{{ student?.admission_no }}</p>
                        </div>
                        <div class="bg-white p-3">
                            <p class="text-[10px] uppercase tracking-wider text-stone-500 font-bold">Roll #</p>
                            <p class="text-sm font-bold mt-0.5">{{ student?.roll_no || '—' }}</p>
                        </div>
                        <div class="bg-white p-3">
                            <p class="text-[10px] uppercase tracking-wider text-stone-500 font-bold">Class</p>
                            <p class="text-sm font-bold mt-0.5">{{ student?.class_name || '—' }}</p>
                        </div>
                        <div class="bg-white p-3">
                            <p class="text-[10px] uppercase tracking-wider text-stone-500 font-bold">Section</p>
                            <p class="text-sm font-bold mt-0.5">{{ student?.section_name || '—' }}</p>
                        </div>
                    </div>
                </div>

                <!-- No-result-for-this-exam state. Different from "wrong
                     creds" — student matched but their result for the
                     selected exam isn't generated/published. -->
                <div v-if="noResult"
                    class="bg-amber-50 border border-amber-200 rounded-2xl p-6 text-center">
                    <InformationCircleIcon class="w-8 h-8 text-amber-600 mx-auto mb-2" />
                    <p class="font-semibold text-amber-900">No result for this exam yet</p>
                    <p class="text-sm text-amber-800/85 mt-1">
                        Your record for <strong>{{ exam?.name }}</strong> isn't published yet. The school may still be processing it.
                    </p>
                </div>

                <!-- Single result card -->
                <div v-else class="bg-white rounded-2xl shadow-sm border border-stone-200 p-5">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div>
                            <h4 class="text-lg font-bold">{{ exam?.name }}</h4>
                            <p class="text-xs text-stone-500 mt-0.5">
                                {{ exam?.type || 'Exam' }}<span v-if="exam?.session_name"> · {{ exam.session_name }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <CheckCircleIcon v-if="result?.is_passed" class="w-7 h-7 text-emerald-600" />
                            <XCircleIcon v-else class="w-7 h-7 text-red-600" />
                            <span class="text-sm font-bold" :class="result?.is_passed ? 'text-emerald-700' : 'text-red-700'">
                                {{ result?.is_passed ? 'Passed' : 'Failed' }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
                        <div class="rounded-lg bg-stone-50 border border-stone-200 p-3 text-center">
                            <p class="text-[10px] uppercase tracking-wider text-stone-500 font-bold">Marks</p>
                            <p class="text-base font-bold mt-1 font-mono">{{ result?.obtained_marks }} / {{ result?.total_marks }}</p>
                        </div>
                        <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-center">
                            <p class="text-[10px] uppercase tracking-wider text-emerald-700 font-bold">Percentage</p>
                            <p class="text-base font-bold mt-1 text-emerald-800 font-mono">{{ fmtPct(result?.percentage) }}</p>
                        </div>
                        <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-center">
                            <p class="text-[10px] uppercase tracking-wider text-amber-700 font-bold">Grade</p>
                            <p class="text-base font-bold mt-1 text-amber-800">{{ result?.grade || '—' }}</p>
                        </div>
                        <div class="rounded-lg bg-sky-50 border border-sky-200 p-3 text-center">
                            <p class="text-[10px] uppercase tracking-wider text-sky-700 font-bold flex items-center justify-center gap-0.5">
                                <TrophyIcon class="w-3 h-3" /> Position
                            </p>
                            <p class="text-base font-bold mt-1 text-sky-800">{{ result?.position || '—' }}</p>
                        </div>
                    </div>

                    <p v-if="result?.published_on" class="text-[11px] text-stone-500 text-right mt-3">
                        Published on {{ result.published_on }}
                    </p>
                </div>
            </div>

            <p class="text-center text-xs text-stone-500 mt-6">
                <Link :href="route('login')" class="text-emerald-700 font-semibold hover:underline">
                    Sign in
                </Link>
                for the full Family Portal with subject-wise breakdown and report card download.
            </p>

        </div>
    </div>
</template>
