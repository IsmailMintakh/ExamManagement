<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    IdentificationIcon,
    ArrowLeftIcon,
    DocumentArrowDownIcon,
    AcademicCapIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: Object,
    classes: { type: Array, default: () => [] },
})

const selectedSection = ref(null)
const downloading = ref(false)

const sections = computed(() => {
    return props.classes.flatMap(c => c.sections.map(s => ({ ...s, class_name: c.name, class_id: c.id })))
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
        <div class="space-y-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <IdentificationIcon class="h-6 w-6 text-primary" />
                        Admit Cards
                    </h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        Pick a section to generate admit cards for all students in a single multi-page PDF.
                    </p>
                </div>
                <Link :href="route('scheduling.index', exam.id)" class="btn btn-ghost btn-sm gap-1.5">
                    <ArrowLeftIcon class="h-4 w-4" /> Back
                </Link>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="lg:col-span-2 card-section">
                    <div class="card-header"><h3>Sections ({{ sections.length }})</h3></div>
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
                    <div class="card-header"><h3>Download</h3></div>
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
                                PDF will open in a new tab. Each card includes the exam schedule,
                                student details, seat number (if assigned) and a QR verification code.
                            </p>
                        </div>
                        <EmptyState v-else title="Select a section" description="Pick a section on the left to continue." />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
