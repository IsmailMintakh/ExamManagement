<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    CalendarDaysIcon,
    DocumentArrowDownIcon,
    CheckIcon,
    ArrowLeftIcon,
    ClockIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: Object,
    rows: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
})

const classFilter = ref('')

const form = useForm({
    schedules: props.rows.map(r => ({
        subject_id: r.subject_id,
        school_class_id: r.school_class_id,
        exam_date: r.exam_date || '',
        start_time: r.start_time || '',
        end_time: r.end_time || '',
        instructions: r.instructions || '',
        _subject_name: r.subject_name,
        _class_name: r.class_name,
        _subject_code: r.subject_code,
        _total_marks: r.total_marks,
    })),
})

const filtered = computed(() => {
    if (!classFilter.value) return form.schedules
    return form.schedules.filter(s => s.school_class_id === Number(classFilter.value))
})

function computeDuration(row) {
    if (!row.exam_date || !row.start_time || !row.end_time) return null
    const s = new Date(`${row.exam_date}T${row.start_time}`)
    const e = new Date(`${row.exam_date}T${row.end_time}`)
    if (isNaN(s) || isNaN(e)) return null
    const mins = Math.round((e - s) / 60000)
    return mins > 0 ? mins : null
}

function save() {
    form.post(route('scheduling.store-schedule', props.exam.id), {
        preserveScroll: true,
    })
}

function openPdf() {
    window.open(route('scheduling.datesheet-pdf', props.exam.id), '_blank')
}
</script>

<template>
    <Head :title="`Date Sheet — ${exam.name}`" />
    <AppLayout
        :breadcrumbs="[
            { label: 'Exams', href: route('exams.index') },
            { label: exam.name, href: route('exams.show', exam.id) },
            { label: 'Scheduling', href: route('scheduling.index', exam.id) },
            { label: 'Date Sheet' },
        ]"
    >
        <div class="space-y-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <CalendarDaysIcon class="h-6 w-6 text-primary" />
                        Date Sheet
                    </h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        Set exam dates, start/end times and instructions. Duration is computed automatically.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('scheduling.index', exam.id)" class="btn btn-ghost btn-sm gap-1.5">
                        <ArrowLeftIcon class="h-4 w-4" /> Back
                    </Link>
                    <button @click="openPdf" class="btn btn-outline btn-sm gap-1.5">
                        <DocumentArrowDownIcon class="h-4 w-4" /> Generate PDF
                    </button>
                    <button @click="save" :disabled="form.processing" class="btn btn-primary btn-sm gap-1.5">
                        <CheckIcon class="h-4 w-4" /> {{ form.processing ? 'Saving…' : 'Save Schedule' }}
                    </button>
                </div>
            </div>

            <div class="card-section">
                <div class="card-header flex items-center justify-between">
                    <h3>Subject Schedule ({{ filtered.length }})</h3>
                    <select v-model="classFilter" class="select select-bordered select-sm w-48">
                        <option value="">All Classes</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div class="overflow-x-auto">
                    <table v-if="filtered.length" class="table table-sm">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Subject</th>
                                <th style="width:10%">Marks</th>
                                <th style="width:14%">Date</th>
                                <th style="width:11%">Start</th>
                                <th style="width:11%">End</th>
                                <th style="width:10%">Duration</th>
                                <th>Instructions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in filtered" :key="row.subject_id + '-' + row.school_class_id" class="hover">
                                <td class="text-sm">{{ row._class_name }}</td>
                                <td>
                                    <div class="font-medium">{{ row._subject_name }}</div>
                                    <div v-if="row._subject_code" class="text-2xs text-base-content/50">{{ row._subject_code }}</div>
                                </td>
                                <td><span class="badge badge-sm badge-ghost">{{ row._total_marks }}</span></td>
                                <td>
                                    <input v-model="row.exam_date" type="date" class="input input-bordered input-xs w-full" />
                                </td>
                                <td>
                                    <input v-model="row.start_time" type="time" class="input input-bordered input-xs w-full" />
                                </td>
                                <td>
                                    <input v-model="row.end_time" type="time" class="input input-bordered input-xs w-full" />
                                </td>
                                <td class="text-xs">
                                    <span v-if="computeDuration(row)" class="inline-flex items-center gap-1 text-base-content/60">
                                        <ClockIcon class="h-3 w-3" />
                                        {{ computeDuration(row) }} min
                                    </span>
                                    <span v-else class="text-base-content/30">—</span>
                                </td>
                                <td>
                                    <input v-model="row.instructions" type="text" placeholder="Optional"
                                        class="input input-bordered input-xs w-full" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <EmptyState v-else title="No subjects to schedule" description="Add subjects to this exam first." />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
