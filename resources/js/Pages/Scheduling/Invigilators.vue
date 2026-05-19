<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SchedulingSubnav from '@/Components/scheduling/SchedulingSubnav.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    UserGroupIcon,
    PlusIcon,
    TrashIcon,
    ArrowLeftIcon,
    DocumentArrowDownIcon,
    UserCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: Object,
    schedules: { type: Array, default: () => [] },
    rooms: { type: Array, default: () => [] },
    teachers: { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
})

const form = useForm({
    exam_schedule_id: '',
    exam_room_id: '',
    user_id: '',
    role: 'invigilator',
})

const byTeacher = computed(() => {
    const map = {}
    for (const a of props.assignments) {
        const key = a.teacher_name || 'Unassigned'
        if (!map[key]) map[key] = []
        map[key].push(a)
    }
    return Object.entries(map).sort((a, b) => a[0].localeCompare(b[0]))
})

const groupedBySchedule = computed(() => {
    const map = {}
    for (const a of props.assignments) {
        const k = a.exam_schedule_id
        if (!map[k]) map[k] = []
        map[k].push(a)
    }
    return map
})

function assign() {
    form.post(route('scheduling.store-invigilator', props.exam.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('user_id', 'exam_room_id', 'role')
            form.role = 'invigilator'
        },
    })
}

function remove(id) {
    router.delete(route('scheduling.delete-invigilator', id), { preserveScroll: true })
}

function openPdf() {
    window.open(route('scheduling.invigilator-pdf', props.exam.id), '_blank')
}
</script>

<template>
    <Head :title="`Invigilators — ${exam.name}`" />
    <AppLayout
        :breadcrumbs="[
            { label: 'Exams', href: route('exams.index') },
            { label: exam.name, href: route('exams.show', exam.id) },
            { label: 'Scheduling', href: route('scheduling.index', exam.id) },
            { label: 'Invigilators' },
        ]"
    >
        <div class="space-y-4 max-w-5xl mx-auto">
            <SchedulingSubnav :exam-id="exam.id" />
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <UserGroupIcon class="h-6 w-6 text-primary" />
                        Invigilator Duty Chart
                    </h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        Assign teachers to rooms for each scheduled exam session.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('scheduling.index', exam.id)" class="btn btn-ghost btn-sm gap-1.5">
                        <ArrowLeftIcon class="h-4 w-4" /> Back
                    </Link>
                    <button @click="openPdf" class="btn btn-outline btn-sm gap-1.5">
                        <DocumentArrowDownIcon class="h-4 w-4" /> Duty Chart PDF
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                <!-- Left: Schedule list with per-schedule assignments -->
                <div class="xl:col-span-2 space-y-5">
                    <!-- Assign form -->
                    <div class="card-section">
                        <div class="card-header"><h3>Assign Invigilator</h3></div>
                        <div class="card-content">
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                                <div class="form-control md:col-span-2">
                                    <label class="label-text text-xs uppercase tracking-wider text-base-content/50 mb-1">Session</label>
                                    <select v-model="form.exam_schedule_id" class="select select-bordered select-sm">
                                        <option value="">Select session</option>
                                        <option v-for="s in schedules" :key="s.id" :value="s.id">
                                            {{ s.exam_date }} · {{ s.start_time }} · {{ s.subject_name }} ({{ s.class_name }})
                                        </option>
                                    </select>
                                </div>
                                <div class="form-control">
                                    <label class="label-text text-xs uppercase tracking-wider text-base-content/50 mb-1">Room</label>
                                    <select v-model="form.exam_room_id" class="select select-bordered select-sm">
                                        <option value="">Select room</option>
                                        <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }}</option>
                                    </select>
                                </div>
                                <div class="form-control">
                                    <label class="label-text text-xs uppercase tracking-wider text-base-content/50 mb-1">Teacher</label>
                                    <select v-model="form.user_id" class="select select-bordered select-sm">
                                        <option value="">Select teacher</option>
                                        <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                                    </select>
                                </div>
                                <div class="form-control">
                                    <label class="label-text text-xs uppercase tracking-wider text-base-content/50 mb-1">Role</label>
                                    <select v-model="form.role" class="select select-bordered select-sm">
                                        <option value="chief">Chief</option>
                                        <option value="invigilator">Invigilator</option>
                                        <option value="relief">Relief</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex justify-end mt-3">
                                <button @click="assign"
                                    :disabled="!form.exam_schedule_id || !form.exam_room_id || !form.user_id || form.processing"
                                    class="btn btn-primary btn-sm gap-1.5">
                                    <PlusIcon class="h-4 w-4" /> Assign
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Schedules with assignments -->
                    <div class="card-section">
                        <div class="card-header"><h3>Scheduled Sessions ({{ schedules.length }})</h3></div>
                        <div v-if="schedules.length" class="divide-y divide-base-200">
                            <div v-for="s in schedules" :key="s.id" class="p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-semibold">{{ s.subject_name }}</div>
                                        <div class="text-2xs text-base-content/60 mt-0.5">
                                            {{ s.class_name }} · {{ s.exam_date }} · {{ s.start_time }}–{{ s.end_time }}
                                        </div>
                                    </div>
                                    <span class="badge badge-sm badge-ghost">
                                        {{ (groupedBySchedule[s.id] || []).length }} assigned
                                    </span>
                                </div>
                                <div v-if="(groupedBySchedule[s.id] || []).length" class="mt-3 flex flex-wrap gap-2">
                                    <div v-for="a in groupedBySchedule[s.id]" :key="a.id"
                                        class="inline-flex items-center gap-2 rounded-full border border-base-200 bg-base-100 px-3 py-1 text-xs">
                                        <span :class="{
                                            'badge badge-xs badge-primary': a.role === 'chief',
                                            'badge badge-xs badge-ghost': a.role === 'invigilator',
                                            'badge badge-xs badge-warning': a.role === 'relief',
                                        }">{{ a.role }}</span>
                                        <span class="font-medium">{{ a.teacher_name }}</span>
                                        <span class="text-base-content/50">· {{ a.room_name }}</span>
                                        <button @click="remove(a.id)" class="text-error hover:text-error-focus">
                                            <TrashIcon class="h-3 w-3" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <EmptyState v-else title="No sessions scheduled yet"
                            description="Add sessions on the Date Sheet page first." />
                    </div>
                </div>

                <!-- Right: duties per teacher -->
                <div class="card-section">
                    <div class="card-header"><h3>Duty List by Teacher</h3></div>
                    <div v-if="byTeacher.length" class="divide-y divide-base-200 max-h-[600px] overflow-y-auto">
                        <div v-for="[teacher, duties] in byTeacher" :key="teacher" class="p-3">
                            <div class="flex items-center gap-2">
                                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-base-200">
                                    <UserCircleIcon class="h-5 w-5 text-base-content/50" />
                                </div>
                                <div class="font-medium text-sm">{{ teacher }}</div>
                                <span class="ml-auto badge badge-sm badge-ghost">{{ duties.length }}</span>
                            </div>
                            <ul class="mt-2 space-y-1 text-2xs text-base-content/70 ml-9">
                                <li v-for="d in duties" :key="d.id">
                                    {{ d.exam_date }} · {{ d.start_time }} · {{ d.room_name }}
                                    <span class="text-base-content/40">({{ d.subject_name }})</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <EmptyState v-else title="No duties assigned" description="Assignments will appear here." />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
