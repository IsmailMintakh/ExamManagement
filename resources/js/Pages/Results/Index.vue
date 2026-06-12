<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, usePage, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    ChartBarIcon, EyeIcon, CheckBadgeIcon, AcademicCapIcon,
    ClipboardDocumentCheckIcon, ArrowRightIcon,
    MagnifyingGlassIcon, XCircleIcon, ClockIcon, CheckCircleIcon,
    PaperAirplaneIcon, DocumentCheckIcon,
} from '@heroicons/vue/24/outline'
import { usePermissions } from '@/Composables/usePermissions'

const props = defineProps({ exams: Array })

const page = usePage()
const roles = page.props.auth?.user?.roles || []
const isSuperAdmin = roles.includes('super-admin')
const { can } = usePermissions()

// Plain-language status: what it means + what to do next.
const STATUS = {
    marks_entry: { label: 'Marks entry', hint: 'Teachers are still entering marks', cta: 'Generate results',
        chip: 'bg-amber-500/12 text-amber-700 dark:text-amber-300', dot: 'bg-amber-500' },
    processing:  { label: 'Generated', hint: 'Results generated — review & finalize', cta: 'Review & finalize',
        chip: 'bg-sky-500/12 text-sky-700 dark:text-sky-300', dot: 'bg-sky-500' },
    completed:   { label: 'Completed', hint: 'Results finalized', cta: 'View results',
        chip: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300', dot: 'bg-emerald-500' },
    published:   { label: 'Published', hint: 'Visible to students & parents', cta: 'View results',
        chip: 'bg-violet-500/12 text-violet-700 dark:text-violet-300', dot: 'bg-violet-500' },
    draft:       { label: 'Draft', hint: 'Not open yet', cta: 'Open',
        chip: 'bg-base-200 text-base-content/55', dot: 'bg-base-content/30' },
    archived:    { label: 'Archived', hint: 'Past session', cta: 'View results',
        chip: 'bg-base-200 text-base-content/55', dot: 'bg-base-content/30' },
}
const meta = (s) => STATUS[s] || STATUS.draft

const subChip = {
    pending: 'bg-base-200 text-base-content/55',
    generated: 'bg-sky-500/12 text-sky-700 dark:text-sky-300',
    submitted: 'bg-amber-500/12 text-amber-700 dark:text-amber-300',
    finalized: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
}

const search = ref('')
const statusFilter = ref('all') // all | marks_entry | processing | completed | published

// At-a-glance summary across the filtered/listed exams so the admin sees
// "how many are in each phase" without scanning the cards individually.
const stats = computed(() => {
    const all = props.exams || []
    return {
        total:       all.length,
        marks_entry: all.filter(e => e.status === 'marks_entry').length,
        processing:  all.filter(e => e.status === 'processing').length,
        completed:   all.filter(e => e.status === 'completed' || e.status === 'published').length,
        results:     all.reduce((sum, e) => sum + (e.results_count || 0), 0),
    }
})

const visible = computed(() => {
    const q = search.value.trim().toLowerCase()
    return (props.exams || []).filter(e => {
        if (statusFilter.value !== 'all') {
            if (statusFilter.value === 'completed') {
                if (e.status !== 'completed' && e.status !== 'published') return false
            } else if (e.status !== statusFilter.value) return false
        }
        if (q && !(e.name.toLowerCase().includes(q) || (e.exam_type || '').toLowerCase().includes(q))) {
            return false
        }
        return true
    })
})

function clearFilters() {
    search.value = ''
    statusFilter.value = 'all'
}

// Per-status gradient for the card avatar — keeps the visual language
// consistent with the Dashboard and Marks Index (gradient pills + shadow).
function gradientFor(status) {
    return {
        marks_entry: 'bg-gradient-to-br from-amber-500 to-orange-600 shadow-amber-500/15',
        processing:  'bg-gradient-to-br from-sky-500 to-indigo-600 shadow-sky-500/15',
        completed:   'bg-gradient-to-br from-emerald-500 to-teal-600 shadow-emerald-500/15',
        published:   'bg-gradient-to-br from-violet-500 to-fuchsia-600 shadow-violet-500/15',
        draft:       'bg-gradient-to-br from-base-300 to-base-200 shadow-base-300/15 text-base-content',
        archived:    'bg-gradient-to-br from-base-300 to-base-200 shadow-base-300/15 text-base-content',
    }[status] || 'bg-gradient-to-br from-base-300 to-base-200 shadow-base-300/15 text-base-content'
}

function finalizeSchool(examId, schoolId) {
    router.post(route('results.finalize', [examId, schoolId]))
}
</script>

<template>
    <Head title="Results" />
    <AppLayout :breadcrumbs="[{ label: 'Results' }]">
        <div class="space-y-4 max-w-6xl mx-auto">

            <PageHeader title="Results"
                subtitle="Generate, review and publish examination results"
                :icon="ChartBarIcon" tone="primary">
                <template #actions>
                    <Link v-if="can('results.review')" :href="route('results.review-queue')"
                        class="btn btn-outline btn-sm rounded-lg gap-1.5">
                        <ClipboardDocumentCheckIcon class="w-4 h-4" /> Review queue
                    </Link>
                </template>
            </PageHeader>

            <template v-if="exams?.length">
                <!-- KPI strip — phase counts across all exams. Same gradient
                     icon-pill pattern as Dashboard / Marks Index for visual
                     consistency, and the colors match the phase the count
                     represents (amber for marks-entry, sky for processing,
                     emerald for completed/published). -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3">
                    <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-500 to-indigo-600 text-white flex items-center justify-center shadow-md shadow-sky-500/15 shrink-0">
                            <ChartBarIcon class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Exams</p>
                            <p class="text-xl font-extrabold tabular-nums">{{ stats.total }}</p>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white flex items-center justify-center shadow-md shadow-amber-500/15 shrink-0">
                            <ClockIcon class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-wider font-bold text-amber-700 dark:text-amber-300">In marks entry</p>
                            <p class="text-xl font-extrabold tabular-nums">{{ stats.marks_entry }}</p>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white flex items-center justify-center shadow-md shadow-violet-500/15 shrink-0">
                            <ClipboardDocumentCheckIcon class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-wider font-bold text-violet-700 dark:text-violet-300">Generated</p>
                            <p class="text-xl font-extrabold tabular-nums">{{ stats.processing }}</p>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3.5 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center shadow-md shadow-emerald-500/15 shrink-0">
                            <CheckCircleIcon class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-wider font-bold text-emerald-700 dark:text-emerald-300">Completed</p>
                            <p class="text-xl font-extrabold tabular-nums">{{ stats.completed }}</p>
                        </div>
                    </div>
                </div>

                <!-- Search + status filter pills — turns the list into a
                     triage view. Pills wrap cleanly on narrow widths. -->
                <div class="rounded-2xl border border-base-300 bg-base-100 p-3 space-y-2.5">
                    <div class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-base-100 border border-base-300 focus-within:border-primary/50">
                        <MagnifyingGlassIcon class="w-4 h-4 text-base-content/40 shrink-0" />
                        <input v-model="search" type="text" placeholder="Search exam name or type…"
                            class="bg-transparent outline-none flex-1 text-sm min-w-0" />
                    </div>
                    <div class="flex items-center gap-1 flex-wrap rounded-xl border border-base-300 bg-base-200/40 p-1 text-xs w-fit">
                        <button v-for="f in [
                            { k: 'all',         label: 'All',         active: 'bg-base-100 shadow-sm' },
                            { k: 'marks_entry', label: 'Marks entry', active: 'bg-amber-500 text-white' },
                            { k: 'processing',  label: 'Generated',   active: 'bg-violet-500 text-white' },
                            { k: 'completed',   label: 'Completed',   active: 'bg-emerald-500 text-white' },
                        ]" :key="f.k" @click="statusFilter = f.k"
                            class="rounded-lg px-3 py-1.5 font-bold transition-colors whitespace-nowrap"
                            :class="statusFilter === f.k ? f.active : 'text-base-content/55 hover:text-base-content'">
                            {{ f.label }}
                        </button>
                        <button v-if="search || statusFilter !== 'all'" @click="clearFilters"
                            class="text-base-content/55 hover:text-base-content px-2 py-1.5 inline-flex items-center gap-1">
                            <XCircleIcon class="w-3.5 h-3.5" /> Clear
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <section v-for="exam in visible" :key="exam.id"
                        class="rounded-2xl border border-base-300 bg-base-100 shadow-sm overflow-hidden flex flex-col hover:shadow-md transition-shadow">
                        <!-- Header -->
                        <div class="p-4 flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl text-white flex items-center justify-center shrink-0 shadow-md"
                                 :class="gradientFor(exam.status)">
                                <AcademicCapIcon class="w-5 h-5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-sm leading-tight truncate">{{ exam.name }}</h3>
                                <p class="text-[11px] text-base-content/55 mt-0.5 truncate">
                                    {{ exam.exam_type }} · {{ exam.session }}
                                </p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg text-[11px] font-semibold whitespace-nowrap"
                                :class="meta(exam.status).chip">
                                <span class="w-1.5 h-1.5 rounded-full" :class="meta(exam.status).dot"></span>
                                {{ meta(exam.status).label }}
                            </span>
                        </div>

                        <!-- Status hint + result count -->
                        <div class="px-4 pb-3 -mt-1">
                            <p class="text-xs text-base-content/60">{{ meta(exam.status).hint }}</p>
                            <p class="text-[11px] text-base-content/45 mt-1 flex items-center gap-1">
                                <ChartBarIcon class="w-3.5 h-3.5" />
                                <span class="tabular-nums font-semibold text-base-content/70">{{ exam.results_count || 0 }}</span>
                                result{{ (exam.results_count || 0) === 1 ? '' : 's' }} generated
                            </p>
                        </div>

                        <!-- Super-admin: per-school progress -->
                        <div v-if="isSuperAdmin && exam.school_submissions?.length"
                            class="px-4 pb-3 space-y-1.5">
                            <div v-for="sub in exam.school_submissions" :key="sub.school_id"
                                class="flex items-center justify-between rounded-lg bg-base-200/40 px-3 py-1.5">
                                <span class="text-[11px] font-medium truncate mr-2">{{ sub.school_name }}</span>
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded capitalize shrink-0"
                                    :class="subChip[sub.status]">{{ sub.status }}</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mt-auto border-t border-base-300 p-3 flex flex-wrap gap-2">
                            <Link v-if="can('results.generate')"
                                :href="route('results.generate', exam.id)"
                                class="btn btn-primary btn-sm rounded-lg flex-1 gap-1.5">
                                {{ exam.results_count ? (meta(exam.status).cta) : 'Generate results' }}
                                <ArrowRightIcon class="w-3.5 h-3.5" />
                            </Link>
                            <Link v-else-if="exam.results_count"
                                :href="route('results.generate', exam.id)"
                                class="btn btn-outline btn-sm rounded-lg flex-1 gap-1.5">
                                <EyeIcon class="w-4 h-4" /> View results
                            </Link>

                            <template v-if="can('results.finalize') && exam.school_submissions?.length">
                                <button v-for="sub in exam.school_submissions.filter(s => s.status === 'submitted')"
                                    :key="'fin-' + sub.school_id"
                                    @click="finalizeSchool(exam.id, sub.school_id)"
                                    class="btn btn-success btn-sm rounded-lg w-full gap-1.5">
                                    <CheckBadgeIcon class="w-4 h-4" /> Finalize {{ sub.school_name }}
                                </button>
                            </template>
                        </div>
                    </section>
                </div>

                <div v-if="!visible.length" class="rounded-2xl border border-base-300 bg-base-100 shadow-sm p-10 text-center">
                    <MagnifyingGlassIcon class="w-10 h-10 text-base-content/25 mx-auto mb-2" />
                    <p class="text-sm font-medium">No exams match your filters.</p>
                    <button @click="clearFilters" class="btn btn-ghost btn-sm mt-3 rounded-lg">Clear filters</button>
                </div>
            </template>

            <EmptyState v-else title="No exams available"
                description="Results can be generated once an exam reaches marks entry." />
        </div>
    </AppLayout>
</template>
