<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    TableCellsIcon,
    SparklesIcon,
    TrashIcon,
    PrinterIcon,
    ArrowLeftIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: Object,
    section: Object,
    students: { type: Array, default: () => [] },
    rooms: { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
})

const selectedRoomId = ref(props.assignments.length ? props.assignments[0].exam_room_id : (props.rooms[0]?.id || ''))
const orderBy = ref('roll')
const confirmClear = ref(false)

const selectedRoom = computed(() => props.rooms.find(r => r.id === Number(selectedRoomId.value)))

const capacityOk = computed(() => {
    if (!selectedRoom.value) return false
    return (selectedRoom.value.rows * selectedRoom.value.cols) >= props.students.length
})

const assignmentByKey = computed(() => {
    if (!selectedRoom.value) return {}
    const map = {}
    for (const a of props.assignments) {
        if (a.exam_room_id === selectedRoom.value.id) {
            map[`${a.row_num}-${a.col_num}`] = a
        }
    }
    return map
})

const studentById = computed(() => {
    const map = {}
    for (const s of props.students) map[s.id] = s
    return map
})

const grid = computed(() => {
    if (!selectedRoom.value) return []
    const rows = []
    for (let r = 1; r <= selectedRoom.value.rows; r++) {
        const row = []
        for (let c = 1; c <= selectedRoom.value.cols; c++) {
            const a = assignmentByKey.value[`${r}-${c}`]
            const student = a ? studentById.value[a.student_id] : null
            const seatNumber = String.fromCharCode(64 + r) + '-' + String(c).padStart(2, '0')
            row.push({
                row: r,
                col: c,
                seat_number: a?.seat_number || seatNumber,
                student,
                assignment: a,
            })
        }
        rows.push(row)
    }
    return rows
})

const assignForm = useForm({
    exam_room_id: '',
    order_by: 'roll',
})

function autoAssign() {
    if (!selectedRoomId.value) return
    assignForm.exam_room_id = selectedRoomId.value
    assignForm.order_by = orderBy.value
    assignForm.post(route('scheduling.auto-seats', [props.exam.id, props.section.id]), {
        preserveScroll: true,
    })
}

function clearSeats() {
    router.post(route('scheduling.clear-seats', [props.exam.id, props.section.id]), {}, {
        preserveScroll: true,
        onFinish: () => (confirmClear.value = false),
    })
}

function openPdf() {
    if (!selectedRoomId.value) return
    window.open(route('scheduling.seating-pdf', [props.exam.id, selectedRoomId.value]), '_blank')
}
</script>

<template>
    <Head :title="`Seating — ${section.full_name}`" />
    <AppLayout
        :breadcrumbs="[
            { label: 'Exams', href: route('exams.index') },
            { label: exam.name, href: route('exams.show', exam.id) },
            { label: 'Scheduling', href: route('scheduling.index', exam.id) },
            { label: 'Seating' },
        ]"
    >
        <div class="space-y-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <TableCellsIcon class="h-6 w-6 text-primary" />
                        Seating Plan
                    </h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        {{ section.full_name }} · {{ students.length }} student(s)
                    </p>
                </div>
                <Link :href="route('scheduling.index', exam.id)" class="btn btn-ghost btn-sm gap-1.5">
                    <ArrowLeftIcon class="h-4 w-4" /> Back
                </Link>
            </div>

            <!-- Controls -->
            <div class="card-section">
                <div class="card-content">
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-3 items-end">
                        <div class="form-control">
                            <label class="label-text text-xs uppercase tracking-wider text-base-content/50 mb-1">Room</label>
                            <select v-model="selectedRoomId" class="select select-bordered select-sm">
                                <option value="">Select a room…</option>
                                <option v-for="r in rooms" :key="r.id" :value="r.id">
                                    {{ r.name }} ({{ r.rows }}×{{ r.cols }} = {{ r.rows * r.cols }})
                                </option>
                            </select>
                        </div>
                        <div class="form-control">
                            <label class="label-text text-xs uppercase tracking-wider text-base-content/50 mb-1">Order</label>
                            <select v-model="orderBy" class="select select-bordered select-sm">
                                <option value="roll">By Roll No.</option>
                                <option value="name">Alphabetical</option>
                                <option value="random">Random</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button @click="autoAssign" :disabled="!selectedRoomId || !capacityOk || assignForm.processing"
                                class="btn btn-primary btn-sm gap-1.5 flex-1">
                                <SparklesIcon class="h-4 w-4" />
                                {{ assignForm.processing ? 'Assigning…' : 'Auto-Assign' }}
                            </button>
                        </div>
                        <div class="flex gap-2">
                            <button @click="confirmClear = true" class="btn btn-outline btn-error btn-sm gap-1.5">
                                <TrashIcon class="h-4 w-4" /> Clear
                            </button>
                            <button @click="openPdf" :disabled="!selectedRoomId" class="btn btn-outline btn-sm gap-1.5">
                                <PrinterIcon class="h-4 w-4" /> Print
                            </button>
                        </div>
                    </div>

                    <div v-if="selectedRoom && !capacityOk"
                        class="mt-3 rounded-lg border border-warning/40 bg-warning/10 px-4 py-2 text-xs text-warning-content">
                        Room capacity is {{ selectedRoom.rows * selectedRoom.cols }} but there are {{ students.length }} students.
                    </div>
                </div>
            </div>

            <!-- Grid -->
            <div v-if="selectedRoom" class="card-section">
                <div class="card-header"><h3>Layout — {{ selectedRoom.name }}</h3></div>
                <div class="card-content">
                    <div class="mb-4 rounded-md bg-base-content/80 py-1.5 text-center text-xs font-bold uppercase tracking-widest text-base-100">
                        Board / Front
                    </div>
                    <div class="grid gap-2" :style="{ gridTemplateColumns: `repeat(${selectedRoom.cols}, minmax(0, 1fr))` }">
                        <template v-for="(row, i) in grid" :key="i">
                            <div v-for="(cell, j) in row" :key="j"
                                class="aspect-[4/3] rounded-lg border p-2 flex flex-col justify-between"
                                :class="cell.student
                                    ? 'border-primary/30 bg-primary/5'
                                    : 'border-base-300 bg-base-200/30'"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-2xs font-mono font-bold text-base-content/60">{{ cell.seat_number }}</span>
                                    <span v-if="cell.student && cell.student.roll_no" class="badge badge-xs badge-primary">
                                        {{ cell.student.roll_no }}
                                    </span>
                                </div>
                                <div v-if="cell.student" class="text-2xs leading-tight text-base-content truncate">
                                    {{ cell.student.name }}
                                </div>
                                <div v-else class="text-2xs text-base-content/30 italic">Empty</div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <EmptyState v-else title="Pick a room to get started"
                description="Create rooms on the Exam Rooms page, then auto-assign students to seats here." />

            <ConfirmDialog
                :show="confirmClear"
                title="Clear seat assignments?"
                message="This will remove all seat assignments for students in this section."
                type="warning"
                confirm-text="Clear"
                @confirm="clearSeats"
                @cancel="confirmClear = false"
            />
        </div>
    </AppLayout>
</template>
