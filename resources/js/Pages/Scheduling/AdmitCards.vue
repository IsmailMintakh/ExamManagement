<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SchedulingSubnav from '@/Components/scheduling/SchedulingSubnav.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    IdentificationIcon,
    ArrowLeftIcon,
    DocumentArrowDownIcon,
    AcademicCapIcon,
    Squares2X2Icon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: Object,
    classes: { type: Array, default: () => [] },
})

const selectedSection = ref(null)
const bulkClassId = ref('')      // '' = every class
const downloading = ref(false)
const bulkDownloading = ref(false)

const sections = computed(() => {
    return props.classes.flatMap(c => c.sections.map(s => ({ ...s, class_name: c.name, class_id: c.id })))
})

const totalStudents = computed(() =>
    props.classes.reduce((acc, c) => acc + c.sections.reduce((a, s) => a + (s.students_count || 0), 0), 0)
)

const bulkStudentCount = computed(() => {
    if (!bulkClassId.value) return totalStudents.value
    const c = props.classes.find(x => x.id === Number(bulkClassId.value))
    return c ? c.sections.reduce((a, s) => a + (s.students_count || 0), 0) : 0
})

function pick(section) {
    selectedSection.value = section
}

function download() {
    if (!selectedSection.value) return
    downloading.value = true
    const url = route('scheduling.admit-cards-download', props.exam.id) + '?section_id=' + selectedSection.value.id
    window.open(url, '_blank')
    setTimeout(() => { downloading.value = false }, 2000)
}

function downloadBulk() {
    bulkDownloading.value = true
    let url = route('scheduling.admit-cards-bulk', props.exam.id)
    if (bulkClassId.value) url += '?school_class_id=' + bulkClassId.value
    window.open(url, '_blank')
    setTimeout(() => { bulkDownloading.value = false }, 2000)
}
</script>

<template>
    <Head :title="`Admit Cards — ${exam.name}`" />
    <AppLayout
        :breadcrumbs="[
            { label: 'Exams', href: route('exams.index') },
            { label: exam.name, href: route('exams.show', exam.id) },
            { label: 'Scheduling', href: route('scheduling.index', exam.id) },
            { label: 'Admit Cards' },
        ]"
    >
        <div class="space-y-4 max-w-5xl mx-auto">
            <SchedulingSubnav :exam-id="exam.id" />
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <IdentificationIcon class="h-6 w-6 text-primary" />
                        Admit Cards
                    </h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        Generate bulk admit cards for the whole exam, a single class, or a specific section.
                    </p>
                </div>
                <Link :href="route('scheduling.index', exam.id)" class="btn btn-ghost btn-sm gap-1.5">
                    <ArrowLeftIcon class="h-4 w-4" /> Back
                </Link>
            </div>

            <!-- ── Bulk download (whole exam or filter by class) ── -->
            <div class="rounded-2xl border border-primary/30 bg-primary/5 p-4 sm:p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/15 text-primary">
                            <Squares2X2Icon class="w-5 h-5" />
                        </div>
                        <div>
                            <p class="text-sm font-bold">Bulk admit cards</p>
                            <p class="text-xs text-base-content/60 mt-0.5">
                                Download every admit card in one PDF — optionally narrow to a single class.
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                        <select v-model="bulkClassId" class="select select-sm select-bordered min-w-[200px]">
                            <option value="">All classes ({{ totalStudents }} students)</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">
                                {{ c.name }} ({{ c.sections.reduce((a, s) => a + (s.students_count || 0), 0) }} students)
                            </option>
                        </select>
                        <button @click="downloadBulk" :disabled="bulkDownloading || !totalStudents"
                            class="btn btn-primary btn-sm gap-2 whitespace-nowrap">
                            <DocumentArrowDownIcon class="h-4 w-4" />
                            {{ bulkDownloading ? 'Generating…' : `Download ${bulkStudentCount} cards` }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Per-section picker ── -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="lg:col-span-2 card-section">
                    <div class="card-header"><h3>By section ({{ sections.length }})</h3></div>
                    <div v-if="classes.length" class="card-content space-y-4">
                        <div v-for="c in classes" :key="c.id">
                            <div class="flex items-center gap-2 mb-2">
                                <AcademicCapIcon class="h-4 w-4 text-base-content/50" />
                                <h4 class="text-sm font-semibold">{{ c.name }}</h4>
                                <span class="badge badge-xs badge-ghost">{{ c.sections.length }} section(s)</span>
                            </div>
                            <div v-if="c.sections.length" class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                <button v-for="s in c.sections" :key="s.id" @click="pick({ ...s, class_name: c.name })"
                                    class="rounded-lg border p-3 text-left transition-all"
                                    :class="selectedSection?.id === s.id
                                        ? 'border-primary bg-primary/5'
                                        : 'border-base-200 hover:border-primary/40'">
                                    <div class="text-sm font-semibold">{{ s.name }}</div>
                                    <div class="text-2xs text-base-content/50">{{ s.students_count }} students</div>
                                </button>
                            </div>
                            <p v-else class="text-xs text-base-content/40 ml-6">No sections.</p>
                        </div>
                    </div>
                    <EmptyState v-else title="No classes available" description="Add classes and sections first." />
                </div>

                <div class="card-section">
                    <div class="card-header"><h3>Download by section</h3></div>
                    <div class="card-content">
                        <div v-if="selectedSection" class="space-y-3">
                            <div class="rounded-xl border border-base-200 bg-base-200/40 p-4">
                                <p class="text-2xs uppercase tracking-wider text-base-content/50">Selected</p>
                                <p class="text-lg font-bold mt-1">{{ selectedSection.class_name }} — {{ selectedSection.name }}</p>
                                <p class="text-xs text-base-content/60">{{ selectedSection.students_count }} admit cards will be generated</p>
                            </div>
                            <button @click="download" :disabled="downloading" class="btn btn-primary w-full gap-2">
                                <DocumentArrowDownIcon class="h-4 w-4" />
                                {{ downloading ? 'Generating…' : 'Download Admit Cards' }}
                            </button>
                            <p class="text-2xs text-base-content/50 leading-relaxed">
                                PDF opens in a new tab. Each card includes the exam schedule,
                                student details, seat number (if assigned) and a QR verification code.
                            </p>
                        </div>
                        <EmptyState v-else title="Select a section" description="Pick a section on the left to download admit cards just for them." />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
