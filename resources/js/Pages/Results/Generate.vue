<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormSelect from '@/Components/FormSelect.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import { Head, useForm, Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    CheckCircleIcon, ExclamationTriangleIcon, EyeIcon,
    InformationCircleIcon, PaperAirplaneIcon,
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
const isSuperAdmin = roles.includes('super-admin')

const form = useForm({
    school_class_id: '',
    section_id: '',
})

const confirmGenerate = ref(false)
const confirmSubmitDdo = ref(false)

const filteredSections = computed(() => {
    if (!form.school_class_id) return props.sections || []
    return props.sections?.filter(s => s.school_class_id == form.school_class_id) || []
})

const submittedCount = computed(() => props.marksStatus?.filter(m => m.submitted).length || 0)
const totalCount = computed(() => props.marksStatus?.length || 0)

function generateResults() {
    form.post(route('results.process', props.exam.id), {
        onSuccess: () => { confirmGenerate.value = false },
    })
}

function submitToDdo() {
    router.post(route('results.submit-to-ddo', props.exam.id), {}, {
        onSuccess: () => { confirmSubmitDdo.value = false },
    })
}
</script>

<template>
    <Head title="Generate Results" />
    <AppLayout :breadcrumbs="[
        { label: 'Results', href: route('results.index') },
        { label: exam.name }
    ]">
        <div class="space-y-6">
            <div class="page-header">
                <div>
                    <h1 class="page-title">{{ exam.name }}</h1>
                    <p class="page-subtitle">Result Processing &amp; Generation</p>
                </div>
            </div>

            <!-- Marks Status Overview -->
            <div class="card bg-base-100 shadow-md" v-if="marksStatus?.length">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <h2 class="card-title text-lg">Marks Submission Status</h2>
                        <span class="text-sm text-base-content/60">
                            {{ submittedCount }}/{{ totalCount }} submitted
                        </span>
                    </div>
                    <div class="overflow-x-auto mt-2">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Class / Section</th>
                                    <th>Teacher</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="ms in marksStatus" :key="ms.id">
                                    <td class="font-medium">{{ ms.subject }}</td>
                                    <td>{{ ms.class_name }} - {{ ms.section_name }}</td>
                                    <td class="text-sm text-base-content/60">{{ ms.teacher || '-' }}</td>
                                    <td>
                                        <div class="flex items-center gap-1.5">
                                            <CheckCircleIcon v-if="ms.submitted" class="w-4 h-4 text-success" />
                                            <ExclamationTriangleIcon v-else class="w-4 h-4 text-warning" />
                                            <span :class="['text-sm font-medium', ms.submitted ? 'text-success' : 'text-warning']">
                                                {{ ms.submitted ? 'Submitted' : 'Pending' }}
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Generate Form -->
            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <h2 class="card-title text-lg">Generate Results</h2>

                    <!-- Info note -->
                    <div class="rounded-xl border border-info/20 bg-info/5 px-4 py-3 flex items-start gap-3 mt-2">
                        <InformationCircleIcon class="w-5 h-5 text-info shrink-0 mt-0.5" />
                        <div class="text-xs text-base-content/70 space-y-1">
                            <p class="font-medium text-base-content/80">What happens when generating results:</p>
                            <ul class="list-disc ml-4 space-y-0.5">
                                <li>All marks for the selected class/section are fetched and totalled</li>
                                <li>Pass/fail status, grades, and positions are calculated automatically</li>
                                <li>If results already exist, they will be <strong>re-calculated and overwritten</strong></li>
                            </ul>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <FormSelect v-model="form.school_class_id" label="Class" :error="form.errors.school_class_id"
                            :options="classes?.map(c => ({ value: c.id, label: c.name })) || []" placeholder="Select Class" required />
                        <FormSelect v-model="form.section_id" label="Section" :error="form.errors.section_id"
                            :options="filteredSections.map(s => ({ value: s.id, label: s.name }))" placeholder="Select Section" required />
                        <div class="flex items-end">
                            <button @click="confirmGenerate = true" class="btn btn-primary w-full" :disabled="!form.school_class_id || !form.section_id || form.processing">
                                <span v-if="form.processing" class="loading loading-spinner loading-sm"></span>
                                Generate Results
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Existing Results -->
            <div class="card bg-base-100 shadow-md" v-if="existingResults?.length">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <h2 class="card-title text-lg">Generated Results</h2>
                        <button v-if="isSchoolAdmin" @click="confirmSubmitDdo = true" class="btn btn-success btn-sm gap-1.5">
                            <PaperAirplaneIcon class="w-4 h-4" />
                            Submit to DDO
                        </button>
                    </div>
                    <div class="overflow-x-auto mt-2">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Class / Section</th>
                                    <th>Students</th>
                                    <th>Pass</th>
                                    <th>Fail</th>
                                    <th>Pass %</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="result in existingResults" :key="result.section_id">
                                    <td class="font-medium">{{ result.class_name }} - {{ result.section_name }}</td>
                                    <td>{{ result.total }}</td>
                                    <td class="text-success font-medium">{{ result.passed }}</td>
                                    <td class="text-error font-medium">{{ result.failed }}</td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <progress class="progress progress-success w-16" :value="result.pass_percentage" max="100"></progress>
                                            <span class="text-sm font-medium">{{ result.pass_percentage }}%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span :class="['badge badge-sm', result.status === 'submitted_to_ddo' ? 'badge-success' : 'badge-info']">
                                            {{ result.status?.replace(/_/g, ' ') }}
                                        </span>
                                    </td>
                                    <td>
                                        <Link :href="route('results.show', [exam.id, result.section_id])" class="btn btn-primary btn-xs gap-1">
                                            <EyeIcon class="w-3.5 h-3.5" /> View
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog :show="confirmGenerate" title="Generate Results"
            message="This will calculate results for all students in the selected class/section. Any existing results will be re-calculated and overwritten."
            type="warning" confirm-text="Generate"
            @confirm="generateResults" @cancel="confirmGenerate = false" />

        <ConfirmDialog :show="confirmSubmitDdo" title="Submit to DDO"
            message="Submit all generated results to DDO for final review and approval? This action cannot be undone."
            type="info" confirm-text="Submit"
            @confirm="submitToDdo" @cancel="confirmSubmitDdo = false" />
    </AppLayout>
</template>
