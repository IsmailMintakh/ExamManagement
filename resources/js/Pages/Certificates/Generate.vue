<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import {
    TrophyIcon, StarIcon, CheckBadgeIcon, SparklesIcon,
    UserGroupIcon, DocumentCheckIcon, CheckIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exams: Array,
    templates: Array,
    initialExamId: Number,
})

const form = useForm({
    exam_id: props.initialExamId || '',
    type: 'merit',
    template_id: '',
    student_ids: [],
})

const selectedDesign = ref('modern')

const types = [
    { value: 'merit', label: 'Merit (Top 3)', icon: TrophyIcon, desc: 'Top 3 students by position — automatic.', color: 'warning' },
    { value: 'subject_topper', label: 'Subject Topper', icon: StarIcon, desc: 'Highest scorer per subject.', color: 'info' },
    { value: 'pass', label: 'All Pass', icon: CheckBadgeIcon, desc: 'All students who passed.', color: 'success' },
    { value: 'participation', label: 'Participation', icon: UserGroupIcon, desc: 'All students who appeared.', color: 'ghost' },
    { value: 'special_achievement', label: 'Special', icon: SparklesIcon, desc: 'Custom selection — pick manually.', color: 'secondary' },
]

const designs = [
    {
        value: 'modern',
        label: 'Modern Corporate',
        desc: 'Blue & gold geometric ribbons',
        preview: 'linear-gradient(135deg, #1e3a8a 0%, #1e3a8a 30%, #f59e0b 30%, #f59e0b 45%, #fff 45%)',
    },
    {
        value: 'gold',
        label: 'Gold Honors',
        desc: 'Orange title with gold medal',
        preview: 'linear-gradient(135deg, #1e293b 0%, #f97316 12%, #fff 30%, #fff 75%, #f97316 88%, #1e293b 100%)',
    },
    {
        value: 'graduation',
        label: 'Graduation',
        desc: 'Navy corners with cap & medal',
        preview: 'linear-gradient(135deg, #1e3a8a 15%, #fff 16%, #fff 84%, #1e3a8a 85%)',
    },
    {
        value: 'classic',
        label: 'Classic Elegant',
        desc: 'Traditional double border',
        preview: 'repeating-linear-gradient(45deg, #c9a227 0, #c9a227 1.5px, #fff 1.5px, #fff 14px)',
    },
]

// Pick the template that matches both the chosen type AND the chosen design
function pickTemplate() {
    const match = props.templates.find(t => t.type === form.type && t.design_layout === selectedDesign.value)
    if (match) {
        form.template_id = match.id
        return
    }
    // fallback: any template matching the type, prefer default
    const byType = props.templates.filter(t => t.type === form.type)
    const def = byType.find(t => t.is_default) || byType[0]
    if (def) form.template_id = def.id
}

watch(() => form.type, () => {
    pickTemplate()
    form.student_ids = []
}, { immediate: true })

watch(() => selectedDesign.value, pickTemplate)

const needsStudentSelection = computed(() => form.type === 'special_achievement')

const currentTemplate = computed(() =>
    props.templates.find(t => t.id === form.template_id)
)

function submit() {
    form.post(route('certificates.generate.store'))
}
</script>

<template>
    <Head title="Generate Certificates" />
    <AppLayout :breadcrumbs="[{ label: 'Certificates', href: route('certificates.index') }, { label: 'Generate' }]">
        <form @submit.prevent="submit" class="space-y-6 max-w-4xl mx-auto">
            <!-- Step 1: Exam -->
            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <h2 class="card-title">
                        <span class="badge badge-primary">1</span>
                        Choose Exam
                    </h2>
                    <div class="mt-2">
                        <SearchableSelect v-model="form.exam_id"
                            :options="exams.map(e => ({ value: e.id, label: `${e.name} (${e.exam_type?.name || '—'})` }))"
                            placeholder="Select an exam..." />
                    </div>
                    <div v-if="form.errors.exam_id" class="text-error text-sm mt-1">{{ form.errors.exam_id }}</div>
                </div>
            </div>

            <!-- Step 2: Type -->
            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <h2 class="card-title">
                        <span class="badge badge-primary">2</span>
                        Certificate Type
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mt-2">
                        <label v-for="t in types" :key="t.value"
                               class="relative cursor-pointer border-2 rounded-lg p-4 transition hover:bg-base-200"
                               :class="form.type === t.value ? 'border-primary bg-primary/5 shadow-md' : 'border-base-300'">
                            <input type="radio" v-model="form.type" :value="t.value" class="hidden" />
                            <div class="flex items-start gap-3">
                                <component :is="t.icon" class="w-6 h-6 mt-0.5 flex-shrink-0"
                                           :class="`text-${t.color}`" />
                                <div class="flex-1">
                                    <div class="font-semibold text-sm">{{ t.label }}</div>
                                    <div class="text-xs text-base-content/60 mt-0.5">{{ t.desc }}</div>
                                </div>
                                <CheckIcon v-if="form.type === t.value" class="w-4 h-4 text-primary" />
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Step 3: Design Layout -->
            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <h2 class="card-title">
                        <span class="badge badge-primary">3</span>
                        Pick a Design
                    </h2>
                    <p class="text-xs text-base-content/55 mb-2">Choose from 4 professional layouts.</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <label v-for="d in designs" :key="d.value"
                            class="cursor-pointer rounded-lg border-2 p-3 transition-all"
                            :class="selectedDesign === d.value
                                ? 'border-primary bg-primary/5 shadow-md'
                                : 'border-base-200 hover:border-primary/40'">
                            <input type="radio" v-model="selectedDesign" :value="d.value" class="hidden" />
                            <div class="w-full h-20 rounded mb-2 border border-base-200"
                                :style="{ background: d.preview }"></div>
                            <div class="flex items-center gap-1">
                                <span class="font-bold text-sm">{{ d.label }}</span>
                                <CheckIcon v-if="selectedDesign === d.value" class="w-3.5 h-3.5 text-primary" />
                            </div>
                            <div class="text-[10px] text-base-content/55 mt-0.5">{{ d.desc }}</div>
                        </label>
                    </div>
                    <div v-if="currentTemplate" class="mt-3 rounded-lg bg-success/5 border border-success/20 p-3 text-xs">
                        <span class="text-success font-semibold">✓ Using template:</span>
                        <span class="ml-1">{{ currentTemplate.name }}</span>
                    </div>
                </div>
            </div>

            <!-- Step 4: Students (only for special achievement) -->
            <div v-if="needsStudentSelection" class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <h2 class="card-title">
                        <span class="badge badge-primary">4</span>
                        Select Students
                    </h2>
                    <p class="text-sm text-base-content/60">
                        Enter student IDs (comma-separated) for special achievement certificates.
                    </p>
                    <input type="text"
                           placeholder="e.g. 1,5,12"
                           class="input input-bordered w-full mt-2"
                           @input="form.student_ids = $event.target.value.split(',').map(s => s.trim()).filter(Boolean).map(Number)" />
                </div>
            </div>

            <!-- Submit bar -->
            <div class="sticky bottom-4 bg-base-100 border border-base-200 rounded-lg shadow-lg p-4 flex items-center justify-between gap-4">
                <div class="text-sm">
                    <div class="text-base-content/60">Generating</div>
                    <div class="font-semibold">
                        {{ types.find(t => t.value === form.type)?.label }} · {{ designs.find(d => d.value === selectedDesign)?.label }}
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('certificates.index')" class="btn btn-ghost">Cancel</Link>
                    <button type="submit" :disabled="form.processing || !form.exam_id || !form.template_id"
                        class="btn btn-primary gap-2">
                        <DocumentCheckIcon class="w-5 h-5" />
                        {{ form.processing ? 'Generating...' : 'Generate Certificates' }}
                    </button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
