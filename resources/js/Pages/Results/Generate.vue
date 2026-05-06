<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import { Head, useForm, Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    CheckCircleIcon, ExclamationTriangleIcon, EyeIcon,
    InformationCircleIcon, PaperAirplaneIcon, ChevronDownIcon,
    MagnifyingGlassIcon, AcademicCapIcon, BoltIcon,
    ArrowPathIcon, ClockIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: Object,
    classes: Array,
    sections: Array,
    marksStatus: Array,
    existingResults: Array,
})

const page = usePage()
const roles = page.props.auth?.user?.roles || []
const isSchoolAdmin = roles.includes('school-admin')

// ─── Filters ───
const search = ref('')
const filter = ref('all') // all | pending | ready | generated

// ─── Per-class accordion open state ───
// Auto-expand any class that has a "ready to generate" section so the
// teacher sees the action button without needing to click around.
const openClasses = ref(new Set())
function toggleClass(classId) {
    openClasses.value.has(classId) ? openClasses.value.delete(classId) : openClasses.value.add(classId)
    openClasses.value = new Set(openClasses.value) // trigger reactivity
}

// ─── Build per-class summary with sections + subject submission state ───
const classGroups = computed(() => {
    const ms = props.marksStatus || []
    const existingBySection = new Map((props.existingResults || []).map(r => [r.section_id, r]))

    return (props.classes || []).map(cls => {
        const classMs = ms.filter(m => m.class_id === cls.id)
        const sectionIds = [...new Set(classMs.map(m => m.section_id))]

        const sections = sectionIds.map(sid => {
            const items = classMs.filter(m => m.section_id === sid)
            const submittedCount = items.filter(i => i.submitted).length
            const total = items.length
            const allSubmitted = total > 0 && submittedCount === total
            const generated = existingBySection.get(sid)
            return {
                id: sid,
                name: items[0]?.section_name,
                subjects: items, // each: { subject, submitted, teacher }
                submitted: submittedCount,
                total,
                allSubmitted,
                generated, // null | { total, passed, failed, pass_percentage, status }
                state: generated
                    ? 'generated'
                    : (allSubmitted ? 'ready' : 'pending'),
            }
        }).sort((a, b) => (a.name || '').localeCompare(b.name || ''))

        const totalSubjects = sections.reduce((s, x) => s + x.total, 0)
        const submittedSubjects = sections.reduce((s, x) => s + x.submitted, 0)
        const generatedSections = sections.filter(s => s.state === 'generated').length
        const readySections = sections.filter(s => s.state === 'ready').length
        const pendingSections = sections.filter(s => s.state === 'pending').length

        return {
            id: cls.id,
            name: cls.name,
            sections,
            totalSubjects,
            submittedSubjects,
            generatedSections,
            readySections,
            pendingSections,
            progressPct: totalSubjects ? Math.round((submittedSubjects / totalSubjects) * 100) : 0,
        }
    })
})

// Auto-expand classes that have a ready section on first render
classGroups.value.forEach(cg => {
    if (cg.readySections > 0) openClasses.value.add(cg.id)
})

const filteredGroups = computed(() => {
    let groups = classGroups.value
    if (search.value.trim()) {
        const q = search.value.toLowerCase()
        groups = groups.filter(g => g.name?.toLowerCase().includes(q))
    }
    if (filter.value !== 'all') {
        // Hide classes that have no sections matching the filter
        groups = groups
            .map(g => ({ ...g, sections: g.sections.filter(s => s.state === filter.value) }))
            .filter(g => g.sections.length > 0)
    }
    return groups
})

// Overall counters for the top stats strip
const overall = computed(() => {
    const all = classGroups.value
    return {
        classes: all.length,
        totalSections: all.reduce((s, g) => s + g.sections.length, 0),
        readySections: all.reduce((s, g) => s + g.readySections, 0),
        generatedSections: all.reduce((s, g) => s + g.generatedSections, 0),
        pendingSections: all.reduce((s, g) => s + g.pendingSections, 0),
        totalSubjects: all.reduce((s, g) => s + g.totalSubjects, 0),
        submittedSubjects: all.reduce((s, g) => s + g.submittedSubjects, 0),
    }
})
const overallPct = computed(() => overall.value.totalSubjects
    ? Math.round((overall.value.submittedSubjects / overall.value.totalSubjects) * 100) : 0)

// ─── Generation flow ───
const form = useForm({ school_class_id: '', section_id: '' })
const confirmGenerate = ref(false)
const pendingSection = ref(null) // { class_id, section_id, name, isRegenerate }

function askGenerate(classId, section) {
    pendingSection.value = {
        class_id: classId,
        section_id: section.id,
        name: section.name,
        isRegenerate: section.state === 'generated',
    }
    form.school_class_id = classId
    form.section_id = section.id
    confirmGenerate.value = true
}

function generateResults() {
    form.post(route('results.process', props.exam.id), {
        preserveScroll: true,
        onSuccess: () => { confirmGenerate.value = false; pendingSection.value = null },
    })
}

const confirmSubmitDdo = ref(false)
function submitToDdo() {
    router.post(route('results.submit-to-ddo', props.exam.id), {}, {
        preserveScroll: true,
        onSuccess: () => { confirmSubmitDdo.value = false },
    })
}

// One-click "Generate All Ready" — kicks off sequential generation for
// every section that's marked ready. Useful when a school has many
// sections with all marks already submitted.
const bulkRunning = ref(false)
const bulkProgress = ref({ current: 0, total: 0 })
const confirmBulk = ref(false)
async function generateAllReady() {
    const targets = []
    classGroups.value.forEach(g => g.sections.forEach(s => {
        if (s.state === 'ready') targets.push({ class_id: g.id, section_id: s.id })
    }))
    if (targets.length === 0) return
    bulkRunning.value = true
    bulkProgress.value = { current: 0, total: targets.length }
    for (const t of targets) {
        bulkProgress.value.current++
        await new Promise(resolve => {
            router.post(route('results.process', props.exam.id), t, {
                preserveScroll: true,
                preserveState: true,
                onFinish: resolve,
            })
        })
    }
    bulkRunning.value = false
    confirmBulk.value = false
    router.reload({ only: ['marksStatus', 'existingResults'] })
}
</script>

<template>
    <Head title="Generate Results" />
    <AppLayout :breadcrumbs="[
        { label: 'Results', href: route('results.index') },
        { label: exam.name }
    ]">
        <div class="space-y-5">

            <!-- ───── Page header ───── -->
            <div class="page-header">
                <div>
                    <h1 class="page-title flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-secondary shadow-lg shadow-primary/25">
                            <AcademicCapIcon class="h-5 w-5 text-white" />
                        </div>
                        {{ exam.name }}
                    </h1>
                    <p class="page-subtitle">Generate &amp; manage results — class by class</p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        v-if="overall.readySections > 0"
                        @click="confirmBulk = true"
                        class="btn btn-primary btn-sm gap-1.5"
                        :disabled="bulkRunning"
                    >
                        <BoltIcon class="w-4 h-4" />
                        Generate All Ready ({{ overall.readySections }})
                    </button>
                    <button
                        v-if="isSchoolAdmin && overall.generatedSections > 0"
                        @click="confirmSubmitDdo = true"
                        class="btn btn-success btn-sm gap-1.5"
                    >
                        <PaperAirplaneIcon class="w-4 h-4" /> Submit to DDO
                    </button>
                </div>
            </div>

            <!-- ───── Top progress bar — overall marks submission ───── -->
            <div class="surface">
                <div class="surface-body">
                    <div class="flex items-start gap-4 flex-col sm:flex-row sm:items-center">
                        <div class="flex-1 w-full">
                            <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-base-content/55 mb-2">
                                <span>Marks Submission Progress</span>
                                <span class="text-primary">{{ overall.submittedSubjects }}/{{ overall.totalSubjects }} subjects</span>
                            </div>
                            <div class="h-2.5 w-full rounded-full bg-base-200 overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-primary to-secondary"
                                     :style="`width: ${overallPct}%; transition: width .4s cubic-bezier(.16,1,.3,1)`"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 w-full sm:w-auto sm:min-w-[280px]">
                            <div class="rounded-xl border border-warning/20 bg-warning/5 p-2.5 text-center">
                                <div class="text-lg font-extrabold text-warning leading-none">{{ overall.pendingSections }}</div>
                                <div class="text-[10px] font-bold uppercase tracking-wider text-base-content/55 mt-1">Pending</div>
                            </div>
                            <div class="rounded-xl border border-info/20 bg-info/5 p-2.5 text-center">
                                <div class="text-lg font-extrabold text-info leading-none">{{ overall.readySections }}</div>
                                <div class="text-[10px] font-bold uppercase tracking-wider text-base-content/55 mt-1">Ready</div>
                            </div>
                            <div class="rounded-xl border border-success/20 bg-success/5 p-2.5 text-center">
                                <div class="text-lg font-extrabold text-success leading-none">{{ overall.generatedSections }}</div>
                                <div class="text-[10px] font-bold uppercase tracking-wider text-base-content/55 mt-1">Generated</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ───── Filters ───── -->
            <div class="surface">
                <div class="surface-body">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="flex flex-wrap items-center gap-1 rounded-xl bg-base-200/50 p-1">
                            <button v-for="f in [
                                { key: 'all', label: 'All', count: overall.totalSections },
                                { key: 'pending', label: 'Pending Marks', count: overall.pendingSections },
                                { key: 'ready', label: 'Ready', count: overall.readySections },
                                { key: 'generated', label: 'Generated', count: overall.generatedSections },
                            ]" :key="f.key"
                                @click="filter = f.key"
                                class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                                :class="filter === f.key ? 'bg-base-100 shadow-sm text-primary' : 'text-base-content/55 hover:text-base-content'"
                            >
                                {{ f.label }}
                                <span class="rounded-md bg-base-200/70 px-1.5 py-0.5 text-[10px] font-bold">{{ f.count }}</span>
                            </button>
                        </div>
                        <div class="relative flex-1">
                            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-base-content/35" />
                            <input v-model="search" type="text" placeholder="Search class…"
                                class="input input-bordered w-full pl-9 text-sm" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- ───── Empty state ───── -->
            <div v-if="!filteredGroups.length" class="surface">
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <MagnifyingGlassIcon class="h-7 w-7 text-base-content/30" />
                    </div>
                    <h3 class="text-base font-bold">No classes match this view</h3>
                    <p class="mt-1.5 text-sm text-base-content/55">
                        Try a different filter or clear your search.
                    </p>
                    <button @click="search = ''; filter = 'all'" class="btn btn-ghost btn-sm mt-4">
                        Clear filters
                    </button>
                </div>
            </div>

            <!-- ───── Class accordion list ───── -->
            <div v-for="cls in filteredGroups" :key="cls.id" class="surface overflow-hidden">
                <!-- Class header (clickable) -->
                <button @click="toggleClass(cls.id)"
                    class="w-full flex items-center gap-3 px-4 py-3 hover:bg-base-200/40 transition text-left">
                    <div class="flex items-center justify-center w-9 h-9 rounded-xl shrink-0"
                        :class="cls.progressPct === 100 ? 'bg-success/10 text-success'
                                : cls.progressPct > 0 ? 'bg-warning/10 text-warning'
                                : 'bg-base-200 text-base-content/40'">
                        <AcademicCapIcon class="w-5 h-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-baseline gap-2">
                            <h3 class="font-bold text-sm">{{ cls.name }}</h3>
                            <span class="text-[11px] text-base-content/55">{{ cls.sections.length }} section{{ cls.sections.length === 1 ? '' : 's' }}</span>
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <div class="h-1.5 flex-1 max-w-[200px] rounded-full bg-base-200 overflow-hidden">
                                <div class="h-full transition-all"
                                    :class="cls.progressPct === 100 ? 'bg-success' : 'bg-warning'"
                                    :style="`width:${cls.progressPct}%`"></div>
                            </div>
                            <span class="text-[11px] font-semibold text-base-content/65">
                                {{ cls.submittedSubjects }}/{{ cls.totalSubjects }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-[11px] font-bold">
                        <span v-if="cls.pendingSections > 0" class="px-2 py-0.5 rounded-md bg-warning/10 text-warning">
                            {{ cls.pendingSections }} pending
                        </span>
                        <span v-if="cls.readySections > 0" class="px-2 py-0.5 rounded-md bg-info/10 text-info">
                            {{ cls.readySections }} ready
                        </span>
                        <span v-if="cls.generatedSections > 0" class="px-2 py-0.5 rounded-md bg-success/10 text-success">
                            {{ cls.generatedSections }} generated
                        </span>
                    </div>
                    <ChevronDownIcon class="w-4 h-4 text-base-content/40 transition-transform"
                        :class="openClasses.has(cls.id) ? 'rotate-180' : ''" />
                </button>

                <!-- Per-section rows (visible when expanded) -->
                <div v-if="openClasses.has(cls.id)" class="border-t border-base-200">
                    <div v-for="sec in cls.sections" :key="sec.id"
                        class="flex items-start gap-3 px-4 py-3 border-b border-base-200/60 last:border-b-0 hover:bg-base-200/20">
                        <!-- Section name + state -->
                        <div class="w-32 shrink-0">
                            <div class="font-semibold text-sm">Section {{ sec.name }}</div>
                            <span class="inline-flex items-center gap-1 mt-1 text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-md"
                                :class="sec.state === 'generated' ? 'bg-success/10 text-success'
                                        : sec.state === 'ready' ? 'bg-info/10 text-info'
                                        : 'bg-warning/10 text-warning'">
                                <CheckCircleIcon v-if="sec.state === 'generated'" class="w-3 h-3" />
                                <BoltIcon v-else-if="sec.state === 'ready'" class="w-3 h-3" />
                                <ClockIcon v-else class="w-3 h-3" />
                                {{ sec.state === 'generated' ? 'Generated' : sec.state === 'ready' ? 'Ready' : 'Pending' }}
                            </span>
                        </div>

                        <!-- Subject chips -->
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap gap-1">
                                <span v-for="sub in sec.subjects" :key="sub.subject"
                                    class="inline-flex items-center gap-1 text-[10.5px] font-medium px-2 py-0.5 rounded-md border"
                                    :class="sub.submitted
                                        ? 'border-success/20 bg-success/5 text-success'
                                        : 'border-warning/30 bg-warning/5 text-warning'"
                                    :title="sub.submitted ? `Submitted by ${sub.teacher || 'teacher'}` : 'Awaiting marks'">
                                    <CheckCircleIcon v-if="sub.submitted" class="w-3 h-3" />
                                    <ExclamationTriangleIcon v-else class="w-3 h-3" />
                                    {{ sub.subject }}
                                </span>
                            </div>
                            <!-- Generated stats -->
                            <div v-if="sec.generated" class="flex items-center gap-3 mt-2 text-[11px] text-base-content/65">
                                <span><strong class="text-base-content">{{ sec.generated.total }}</strong> students</span>
                                <span class="text-success font-semibold">{{ sec.generated.passed }} pass</span>
                                <span class="text-error font-semibold">{{ sec.generated.failed }} fail</span>
                                <span>{{ sec.generated.pass_percentage }}% avg</span>
                            </div>
                        </div>

                        <!-- Action button -->
                        <div class="shrink-0 flex items-center gap-1.5">
                            <Link v-if="sec.generated"
                                :href="route('results.show', [exam.id, sec.id])"
                                class="btn btn-ghost btn-xs gap-1">
                                <EyeIcon class="w-3.5 h-3.5" /> View
                            </Link>
                            <button v-if="sec.allSubmitted"
                                @click="askGenerate(cls.id, sec)"
                                class="btn btn-xs gap-1"
                                :class="sec.generated ? 'btn-outline' : 'btn-primary'"
                                :disabled="form.processing">
                                <ArrowPathIcon v-if="sec.generated" class="w-3.5 h-3.5" />
                                <BoltIcon v-else class="w-3.5 h-3.5" />
                                {{ sec.generated ? 'Re-generate' : 'Generate' }}
                            </button>
                            <span v-else class="text-[10.5px] text-base-content/45 italic">
                                {{ sec.total - sec.submitted }} subject{{ (sec.total - sec.submitted) === 1 ? '' : 's' }} pending
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ───── Help note ───── -->
            <div class="rounded-2xl border border-info/15 bg-gradient-to-br from-info/5 to-primary/5 p-4">
                <div class="flex items-start gap-3">
                    <InformationCircleIcon class="w-5 h-5 text-info shrink-0 mt-0.5" />
                    <div class="text-xs text-base-content/70 space-y-1">
                        <p class="font-semibold text-base-content/85">How this page works</p>
                        <ul class="list-disc ml-4 space-y-0.5">
                            <li>Each class is collapsed by default. Classes with sections ready to generate auto-expand.</li>
                            <li>A section turns <strong class="text-info">Ready</strong> once every subject's marks are submitted by teachers.</li>
                            <li>Click <strong>Generate</strong> on a section to calculate totals, pass/fail, grades, and ranks.</li>
                            <li>Use <strong>Generate All Ready</strong> to process every prepared section at once.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- ───── Confirm dialogs ───── -->
        <ConfirmDialog :show="confirmGenerate"
            :title="pendingSection?.isRegenerate ? 'Re-generate Results' : 'Generate Results'"
            :message="pendingSection?.isRegenerate
                ? `Re-calculate results for Section ${pendingSection?.name}? Existing data will be overwritten with the latest marks.`
                : `Calculate results for Section ${pendingSection?.name}? Pass/fail, grades, and positions will be computed automatically.`"
            :type="pendingSection?.isRegenerate ? 'warning' : 'info'"
            :confirm-text="pendingSection?.isRegenerate ? 'Re-generate' : 'Generate'"
            @confirm="generateResults"
            @cancel="confirmGenerate = false; pendingSection = null" />

        <ConfirmDialog :show="confirmBulk"
            title="Generate All Ready Sections"
            :message="`Generate results for all ${overall.readySections} ready section(s) of ${exam.name}? This runs them one by one.`"
            type="info" confirm-text="Generate All"
            @confirm="generateAllReady" @cancel="confirmBulk = false" />

        <ConfirmDialog :show="confirmSubmitDdo" title="Submit to DDO"
            message="Submit all generated results to DDO for final review and approval? This action cannot be undone."
            type="info" confirm-text="Submit"
            @confirm="submitToDdo" @cancel="confirmSubmitDdo = false" />

        <!-- ───── Bulk generation overlay ───── -->
        <div v-if="bulkRunning" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center">
            <div class="bg-base-100 rounded-2xl p-6 max-w-sm w-full mx-4 text-center shadow-2xl">
                <div class="flex justify-center mb-4">
                    <span class="loading loading-spinner loading-lg text-primary"></span>
                </div>
                <h3 class="font-bold text-base mb-1">Generating Results…</h3>
                <p class="text-sm text-base-content/65 mb-4">
                    Section {{ bulkProgress.current }} of {{ bulkProgress.total }}
                </p>
                <div class="h-2 w-full rounded-full bg-base-200 overflow-hidden">
                    <div class="h-full bg-primary transition-all"
                        :style="`width: ${bulkProgress.total ? (bulkProgress.current / bulkProgress.total) * 100 : 0}%`"></div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
