<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import SchedulingSubnav from '@/Components/scheduling/SchedulingSubnav.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { invalidatePageCache } from '@/Composables/useCacheInvalidation'
import {
    BuildingOffice2Icon,
    PlusIcon,
    TableCellsIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    rooms: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    defaultSchoolId: { type: [Number, null], default: null },
})

const showAdd = ref(false)

const form = useForm({
    school_id: props.defaultSchoolId,
    name: '',
    capacity: 30,
    rows: 6,
    cols: 5,
})

const previewGrid = computed(() => {
    const rows = []
    const r = Math.max(1, Number(form.rows) || 1)
    const c = Math.max(1, Number(form.cols) || 1)
    for (let i = 0; i < r; i++) {
        const row = []
        for (let j = 0; j < c; j++) {
            row.push(String.fromCharCode(65 + i) + '-' + String(j + 1).padStart(2, '0'))
        }
        rows.push(row)
    }
    return rows
})

function save() {
    form.post(route('scheduling.store-room'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('name')
            form.capacity = 30
            form.rows = 6
            form.cols = 5
            showAdd.value = false
            invalidatePageCache()  // refresh the rooms list on PWA
        },
    })
}

function autoCapacity() {
    form.capacity = (Number(form.rows) || 0) * (Number(form.cols) || 0)
}
</script>

<template>
    <Head title="Exam Rooms" />
    <AppLayout :breadcrumbs="[{ label: 'Scheduling' }, { label: 'Exam Rooms' }]">
        <div class="space-y-4 max-w-5xl mx-auto">
            <SchedulingSubnav />
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <BuildingOffice2Icon class="h-6 w-6 text-primary" />
                        Exam Rooms
                    </h1>
                    <p class="mt-1 text-sm text-base-content/60">
                        Define examination rooms with a grid layout for seat assignment.
                    </p>
                </div>
                <button @click="showAdd = !showAdd" class="btn btn-primary btn-sm gap-1.5">
                    <PlusIcon class="h-4 w-4" /> Add Room
                </button>
            </div>

            <!-- Add form -->
            <div v-if="showAdd" class="card-section">
                <div class="card-header"><h3>New Exam Room</h3></div>
                <div class="card-content">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 space-y-3">
                            <div v-if="schools.length > 1" class="form-control">
                                <label class="label-text text-xs uppercase tracking-wider text-base-content/50 mb-1">School</label>
                                <SearchableSelect v-model="form.school_id" size="sm"
                                    :options="[{ value: null, label: 'Select school' }, ...schools.map(s => ({ value: s.id, label: s.name }))]"
                                    placeholder="Select school" />
                                <p v-if="form.errors.school_id" class="text-error text-xs mt-1">{{ form.errors.school_id }}</p>
                            </div>
                            <div class="form-control">
                                <label class="label-text text-xs uppercase tracking-wider text-base-content/50 mb-1">Room Name</label>
                                <input v-model="form.name" placeholder="e.g., Room 101, Hall A" class="input input-bordered input-sm" />
                                <p v-if="form.errors.name" class="text-error text-xs mt-1">{{ form.errors.name }}</p>
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                <div class="form-control">
                                    <label class="label-text text-xs uppercase tracking-wider text-base-content/50 mb-1">Rows</label>
                                    <input v-model.number="form.rows" @input="autoCapacity" type="number" min="1" max="50"
                                        class="input input-bordered input-sm" />
                                </div>
                                <div class="form-control">
                                    <label class="label-text text-xs uppercase tracking-wider text-base-content/50 mb-1">Columns</label>
                                    <input v-model.number="form.cols" @input="autoCapacity" type="number" min="1" max="50"
                                        class="input input-bordered input-sm" />
                                </div>
                                <div class="form-control">
                                    <label class="label-text text-xs uppercase tracking-wider text-base-content/50 mb-1">Capacity</label>
                                    <input v-model.number="form.capacity" type="number" min="1" max="500"
                                        class="input input-bordered input-sm" />
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 pt-2">
                                <button @click="showAdd = false" class="btn btn-ghost btn-sm">Cancel</button>
                                <button @click="save" :disabled="form.processing" class="btn btn-primary btn-sm">
                                    {{ form.processing ? 'Saving…' : 'Save Room' }}
                                </button>
                            </div>
                        </div>

                        <div class="lg:col-span-1">
                            <p class="text-xs uppercase tracking-wider text-base-content/50 mb-2">Preview</p>
                            <div class="rounded-xl border border-base-200 bg-base-200/40 p-3">
                                <div class="mb-3 rounded-md bg-base-content/80 py-1 text-center text-2xs font-bold uppercase tracking-wider text-base-100">
                                    Board / Front
                                </div>
                                <div class="grid gap-1" :style="{ gridTemplateColumns: `repeat(${form.cols}, minmax(0, 1fr))` }">
                                    <div v-for="(row, i) in previewGrid" :key="i" class="contents">
                                        <div v-for="(seat, j) in row" :key="j"
                                            class="aspect-square rounded bg-base-100 border border-base-300 flex items-center justify-center text-2xs font-mono text-base-content/60">
                                            {{ seat }}
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-2 text-center text-2xs text-base-content/50">
                                    {{ (form.rows || 0) * (form.cols || 0) }} seats ({{ form.rows }}×{{ form.cols }})
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rooms list -->
            <div class="card-section">
                <div class="card-header"><h3>Rooms ({{ rooms.length }})</h3></div>
                <div class="overflow-x-auto">
                    <table v-if="rooms.length" class="table table-sm">
                        <thead>
                            <tr>
                                <th>Room</th>
                                <th>School</th>
                                <th>Layout</th>
                                <th>Capacity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in rooms" :key="r.id" class="hover">
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <TableCellsIcon class="h-4 w-4" />
                                        </div>
                                        <span class="font-medium">{{ r.name }}</span>
                                    </div>
                                </td>
                                <td class="text-sm">{{ r.school_name }}</td>
                                <td class="text-sm">{{ r.rows }} × {{ r.cols }}</td>
                                <td>
                                    <span class="badge badge-sm badge-ghost">{{ r.capacity }} seats</span>
                                </td>
                                <td>
                                    <span class="badge badge-sm" :class="r.is_active ? 'badge-success' : 'badge-neutral'">
                                        {{ r.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <EmptyState v-else title="No exam rooms yet" description="Create your first room to start planning seating." />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
