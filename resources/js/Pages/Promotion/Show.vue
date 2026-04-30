<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormSelect from '@/Components/FormSelect.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref, computed, reactive } from 'vue'
import {
    ArrowPathIcon,
    ArrowUturnLeftIcon,
    PaperAirplaneIcon,
    BoltIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    class: { type: Object, required: true },
    students: { type: Array, default: () => [] },
    nextClasses: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    currentSession: { type: Object, default: null },
})

const ACTION_OPTIONS = [
    { value: 'promote', label: 'Promote' },
    { value: 'retain', label: 'Retain' },
    { value: 'graduate', label: 'Graduate' },
]

// Build per-student state
const rows = reactive(
    props.students.map((s) => ({
        id: s.id,
        name: s.name,
        roll_no: s.roll_no,
        father_name: s.father_name,
        section_name: s.section?.name || '-',
        promotion_status: s.promotion_status,
        action: s.promotion_status || 'promote',
        target_class_id: '',
        target_section_id: '',
    }))
)

// Bulk action state
const bulkClassId = ref('')
const bulkSectionId = ref('')

const classOptions = computed(() =>
    props.nextClasses.map((c) => ({ value: c.id, label: c.name }))
)

function sectionOptionsFor(classId) {
    if (!classId) return []
    return props.sections
        .filter((s) => String(s.school_class_id) === String(classId))
        .map((s) => ({ value: s.id, label: s.name }))
}

const bulkSectionOptions = computed(() => sectionOptionsFor(bulkClassId.value))

function applyBulkPromote() {
    if (!bulkClassId.value || !bulkSectionId.value) return
    rows.forEach((r) => {
        r.action = 'promote'
        r.target_class_id = bulkClassId.value
        r.target_section_id = bulkSectionId.value
    })
}

function onClassChange(row) {
    // Reset section if it doesn't belong to the selected class
    const valid = sectionOptionsFor(row.target_class_id).some(
        (o) => String(o.value) === String(row.target_section_id)
    )
    if (!valid) row.target_section_id = ''
}

const confirmOpen = ref(false)

const form = useForm({
    students: [],
})

const summary = computed(() => {
    const out = { promote: 0, retain: 0, graduate: 0, missing: 0 }
    rows.forEach((r) => {
        out[r.action] = (out[r.action] || 0) + 1
        if (r.action === 'promote' && (!r.target_class_id || !r.target_section_id)) {
            out.missing++
        }
    })
    return out
})

function openConfirm() {
    if (rows.length === 0) return
    confirmOpen.value = true
}

function submit() {
    form.students = rows.map((r) => ({
        id: r.id,
        action: r.action,
        target_class_id: r.action === 'promote' ? r.target_class_id : null,
        target_section_id: r.action === 'promote' ? r.target_section_id : null,
    }))
    form.post(route('promotion.process', props.class.id), {
        onFinish: () => { confirmOpen.value = false },
    })
}
</script>

<template>
    <Head :title="`Promote Students - ${props.class.name}`" />
    <AppLayout
        :breadcrumbs="[
            { label: 'Students', href: '/students' },
            { label: 'Promotion', href: '/promotion' },
            { label: props.class.name },
        ]"
    >
        <div class="space-y-5">
            <!-- Header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <ArrowPathIcon class="h-6 w-6 text-primary" />
                        Promote Students - {{ props.class.name }}
                    </h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        Choose an action for each student. Promoted students will move to the selected target class and section.
                    </p>
                </div>
                <Link :href="route('promotion.index')" class="btn btn-ghost btn-sm gap-2">
                    <ArrowUturnLeftIcon class="h-4 w-4" />
                    Back
                </Link>
            </div>

            <!-- Bulk Action Bar -->
            <div v-if="rows.length" class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <BoltIcon class="h-4 w-4 text-secondary" />
                        <h3 class="text-sm font-semibold">Bulk Action - Promote All To</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                        <FormSelect
                            v-model="bulkClassId"
                            label="Target Class"
                            :options="classOptions"
                            placeholder="Select class"
                        />
                        <FormSelect
                            v-model="bulkSectionId"
                            label="Target Section"
                            :options="bulkSectionOptions"
                            :disabled="!bulkClassId"
                            placeholder="Select section"
                        />
                        <button
                            type="button"
                            class="btn btn-secondary btn-sm gap-2"
                            :disabled="!bulkClassId || !bulkSectionId"
                            @click="applyBulkPromote"
                        >
                            <BoltIcon class="h-4 w-4" />
                            Apply to All
                        </button>
                    </div>
                </div>
            </div>

            <!-- Students Table -->
            <div class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body p-0">
                    <div v-if="rows.length" class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th class="w-12">#</th>
                                    <th>Roll No</th>
                                    <th>Name</th>
                                    <th>Father Name</th>
                                    <th class="w-44">Action</th>
                                    <th class="w-44">Target Class</th>
                                    <th class="w-44">Target Section</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, i) in rows" :key="row.id">
                                    <td>{{ i + 1 }}</td>
                                    <td class="text-sm">{{ row.roll_no || '-' }}</td>
                                    <td>
                                        <div class="font-medium text-sm">{{ row.name }}</div>
                                        <div v-if="row.promotion_status" class="text-2xs text-base-content/50">
                                            Last: {{ row.promotion_status }}
                                        </div>
                                    </td>
                                    <td class="text-sm">{{ row.father_name || '-' }}</td>
                                    <td>
                                        <FormSelect
                                            v-model="row.action"
                                            :options="ACTION_OPTIONS"
                                            placeholder="Action"
                                        />
                                    </td>
                                    <td>
                                        <FormSelect
                                            v-if="row.action === 'promote'"
                                            v-model="row.target_class_id"
                                            :options="classOptions"
                                            placeholder="Class"
                                            @update:model-value="onClassChange(row)"
                                        />
                                        <span v-else class="text-xs text-base-content/40">—</span>
                                    </td>
                                    <td>
                                        <FormSelect
                                            v-if="row.action === 'promote'"
                                            v-model="row.target_section_id"
                                            :options="sectionOptionsFor(row.target_class_id)"
                                            :disabled="!row.target_class_id"
                                            placeholder="Section"
                                        />
                                        <span v-else class="text-xs text-base-content/40">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <EmptyState
                        v-else
                        title="No active students"
                        description="There are no active students to promote in this class."
                    />
                </div>
            </div>

            <!-- Summary + Actions -->
            <div v-if="rows.length" class="card bg-base-100 shadow-sm border border-base-200 sticky bottom-4">
                <div class="card-body p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="badge badge-success badge-sm">Promote: {{ summary.promote }}</span>
                        <span class="badge badge-warning badge-sm">Retain: {{ summary.retain }}</span>
                        <span class="badge badge-info badge-sm">Graduate: {{ summary.graduate }}</span>
                        <span v-if="summary.missing" class="badge badge-error badge-sm">
                            {{ summary.missing }} missing target
                        </span>
                    </div>
                    <div class="flex gap-2 justify-end">
                        <Link :href="route('promotion.index')" class="btn btn-ghost btn-sm">Cancel</Link>
                        <button
                            type="button"
                            class="btn btn-primary btn-sm gap-2"
                            :disabled="form.processing || summary.missing > 0"
                            @click="openConfirm"
                        >
                            <PaperAirplaneIcon class="h-4 w-4" />
                            Process Promotion
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :show="confirmOpen"
            title="Process Promotion"
            :message="`You are about to promote ${summary.promote}, retain ${summary.retain}, and graduate ${summary.graduate} student(s). This action will update their records. Continue?`"
            confirm-text="Yes, Process"
            type="warning"
            @confirm="submit"
            @cancel="confirmOpen = false"
        />
    </AppLayout>
</template>
