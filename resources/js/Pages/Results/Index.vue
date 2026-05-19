<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, usePage, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    ChartBarIcon, EyeIcon, CheckBadgeIcon, AcademicCapIcon,
    ClipboardDocumentCheckIcon, ArrowRightIcon,
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
const visible = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return props.exams || []
    return (props.exams || []).filter(e =>
        e.name.toLowerCase().includes(q) || (e.exam_type || '').toLowerCase().includes(q))
})

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
                <input v-model="search" type="text" placeholder="Search exams…"
                    class="input input-bordered input-sm w-full rounded-lg text-sm" />

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <section v-for="exam in visible" :key="exam.id"
                        class="rounded-xl border border-base-300 bg-base-100 shadow-sm overflow-hidden flex flex-col">
                        <!-- Header -->
                        <div class="p-4 flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
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

                <div v-if="!visible.length" class="rounded-xl border border-base-300 bg-base-100 shadow-sm p-10 text-center">
                    <p class="text-sm text-base-content/55">No exams match “{{ search }}”.</p>
                    <button @click="search = ''" class="btn btn-ghost btn-sm mt-3 rounded-lg">Clear search</button>
                </div>
            </template>

            <EmptyState v-else title="No exams available"
                description="Results can be generated once an exam reaches marks entry." />
        </div>
    </AppLayout>
</template>
