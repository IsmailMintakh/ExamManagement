<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { PlusIcon, PencilSquareIcon, TrashIcon, StarIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    gradingScales: Array,
})

const confirmDelete = ref(false)
const scaleToDelete = ref(null)

function confirmDeleteScale(scale) {
    scaleToDelete.value = scale
    confirmDelete.value = true
}

function deleteScale() {
    if (scaleToDelete.value) {
        router.delete(route('grading-scales.destroy', scaleToDelete.value.id), {
            onSuccess: () => { confirmDelete.value = false; scaleToDelete.value = null }
        })
    }
}
</script>

<template>
    <Head title="Grading Scales" />
    <AppLayout :breadcrumbs="[{ label: 'Grading Scales' }]">
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h1 class="text-2xl font-bold">Grading Scales</h1>
                <Link :href="route('grading-scales.create')" class="btn btn-primary gap-2">
                    <PlusIcon class="w-5 h-5" /> Add Grading Scale
                </Link>
            </div>

            <div v-if="gradingScales?.length" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div v-for="scale in gradingScales" :key="scale.id" class="card bg-base-100 shadow-md">
                    <div class="card-body">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <h2 class="card-title text-lg">{{ scale.name }}</h2>
                                <span v-if="scale.is_default" class="badge badge-warning badge-sm gap-1">
                                    <StarIcon class="w-3 h-3" /> Default
                                </span>
                            </div>
                            <div class="flex gap-1">
                                <Link :href="route('grading-scales.edit', scale.id)" class="btn btn-ghost btn-sm">
                                    <PencilSquareIcon class="w-4 h-4" />
                                </Link>
                                <button @click="confirmDeleteScale(scale)" class="btn btn-ghost btn-sm text-error">
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <p v-if="scale.school" class="text-sm text-base-content/60">School: {{ scale.school.name }}</p>

                        <div class="overflow-x-auto mt-3">
                            <table class="table table-sm table-zebra">
                                <thead>
                                    <tr>
                                        <th>Grade</th>
                                        <th>Label</th>
                                        <th>Min %</th>
                                        <th>Max %</th>
                                        <th>GP</th>
                                        <th>Remark</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="entry in scale.entries" :key="entry.id">
                                        <td><span class="badge badge-outline badge-sm font-bold">{{ entry.grade }}</span></td>
                                        <td class="text-sm">{{ entry.label || '-' }}</td>
                                        <td class="text-sm">{{ entry.min_percentage }}%</td>
                                        <td class="text-sm">{{ entry.max_percentage }}%</td>
                                        <td class="text-sm font-medium">{{ entry.grade_point }}</td>
                                        <td class="text-sm text-base-content/60">{{ entry.remark || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p v-if="!scale.entries?.length" class="text-sm text-base-content/50 text-center py-4">No grade entries defined.</p>
                    </div>
                </div>
            </div>
            <EmptyState v-else title="No grading scales found" description="Create your first grading scale to define grade boundaries." action-text="Add Grading Scale" :action-href="route('grading-scales.create')" />
        </div>

        <ConfirmDialog
            :show="confirmDelete"
            title="Delete Grading Scale"
            :message="`Are you sure you want to delete ${scaleToDelete?.name}? This action cannot be undone.`"
            type="danger"
            @confirm="deleteScale"
            @cancel="confirmDelete = false"
        />
    </AppLayout>
</template>
