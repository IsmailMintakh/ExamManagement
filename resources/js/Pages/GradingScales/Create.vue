<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import FormSelect from '@/Components/FormSelect.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    gradingScale: Object,
    schools: Array,
})

const isEdit = !!props.gradingScale

const form = useForm({
    name: props.gradingScale?.name || '',
    school_id: props.gradingScale?.school_id || '',
    is_default: props.gradingScale?.is_default ?? false,
    entries: props.gradingScale?.entries?.map(e => ({
        grade: e.grade,
        label: e.label || '',
        min_percentage: e.min_percentage,
        max_percentage: e.max_percentage,
        grade_point: e.grade_point,
        remark: e.remark || '',
    })) || [],
})

function addEntry() {
    form.entries.push({
        grade: '',
        label: '',
        min_percentage: '',
        max_percentage: '',
        grade_point: '',
        remark: '',
    })
}

function removeEntry(index) {
    form.entries.splice(index, 1)
}

function submit() {
    if (isEdit) {
        form.put(route('grading-scales.update', props.gradingScale.id))
    } else {
        form.post(route('grading-scales.store'))
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Grading Scale' : 'Add Grading Scale'" />
    <AppLayout :breadcrumbs="[{ label: 'Grading Scales', href: route('grading-scales.index') }, { label: isEdit ? 'Edit' : 'Create' }]">
        <div class="max-w-5xl mx-auto">
            <h1 class="text-2xl font-bold mb-6">{{ isEdit ? 'Edit Grading Scale' : 'Add New Grading Scale' }}</h1>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body space-y-4">
                        <h3 class="text-lg font-semibold border-b border-base-200 pb-2">Scale Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <FormInput v-model="form.name" label="Scale Name" :error="form.errors.name" required placeholder="e.g., FBISE Grading Scale" />
                            <FormSelect v-model="form.school_id" label="School (optional)" :error="form.errors.school_id"
                                :options="schools?.map(s => ({ value: s.id, label: s.name })) || []" placeholder="All Schools (default)" />
                        </div>
                        <div class="form-control">
                            <label class="label cursor-pointer justify-start gap-3">
                                <input type="checkbox" v-model="form.is_default" class="checkbox checkbox-primary" />
                                <div>
                                    <span class="label-text font-medium">Default Scale</span>
                                    <p class="text-xs text-base-content/50">Use this scale as default when no other scale is specified.</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-md">
                    <div class="card-body space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold">Grade Entries</h3>
                            <button type="button" @click="addEntry" class="btn btn-primary btn-sm gap-1">
                                <PlusIcon class="w-4 h-4" /> Add Grade
                            </button>
                        </div>

                        <div v-if="form.entries.length === 0" class="text-center py-8 text-base-content/50">
                            No grade entries yet. Click "Add Grade" to begin defining grade boundaries.
                        </div>

                        <div class="overflow-x-auto" v-else>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Grade</th>
                                        <th>Label</th>
                                        <th>Min %</th>
                                        <th>Max %</th>
                                        <th>Grade Point</th>
                                        <th>Remark</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(entry, idx) in form.entries" :key="idx">
                                        <td>
                                            <input v-model="entry.grade" type="text" class="input input-bordered input-sm w-20" placeholder="A+" />
                                        </td>
                                        <td>
                                            <input v-model="entry.label" type="text" class="input input-bordered input-sm w-28" placeholder="Excellent" />
                                        </td>
                                        <td>
                                            <input v-model.number="entry.min_percentage" type="number" step="0.01" class="input input-bordered input-sm w-20" placeholder="90" />
                                        </td>
                                        <td>
                                            <input v-model.number="entry.max_percentage" type="number" step="0.01" class="input input-bordered input-sm w-20" placeholder="100" />
                                        </td>
                                        <td>
                                            <input v-model.number="entry.grade_point" type="number" step="0.1" class="input input-bordered input-sm w-20" placeholder="10" />
                                        </td>
                                        <td>
                                            <input v-model="entry.remark" type="text" class="input input-bordered input-sm w-28" placeholder="Outstanding" />
                                        </td>
                                        <td>
                                            <button type="button" @click="removeEntry(idx)" class="btn btn-ghost btn-xs text-error">
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p v-if="form.errors.entries" class="text-sm text-error">{{ form.errors.entries }}</p>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <Link :href="route('grading-scales.index')" class="btn btn-ghost">Cancel</Link>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">
                        <span v-if="form.processing" class="loading loading-spinner loading-sm"></span>
                        {{ isEdit ? 'Update Scale' : 'Create Scale' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
