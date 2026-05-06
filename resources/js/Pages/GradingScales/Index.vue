<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { PlusIcon, PencilSquareIcon, TrashIcon, StarIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    gradingScales: Array,
    filters: { type: Object, default: () => ({}) },
    sourceCounts: { type: Object, default: () => null },
})

const source = ref(props.filters?.source || 'all')

function setSource(v) {
    source.value = v
    router.get(route('grading-scales.index'), { source: v }, { preserveState: false, replace: true })
}

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
        <div class="space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight">Grading Scales</h1>
                    <p class="text-sm text-base-content/55 mt-0.5">{{ gradingScales?.length || 0 }} scale{{ (gradingScales?.length || 0) === 1 ? '' : 's' }} defined</p>
                </div>
                <Link :href="route('grading-scales.create')" class="btn btn-primary btn-sm gap-1.5">
                    <PlusIcon class="w-4 h-4" /> Add Grading Scale
                </Link>
            </div>

            <!-- Source toggle. Hidden for super-admins (DDO sees everything). -->
            <div v-if="sourceCounts" class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-base-content/55 text-xs font-semibold uppercase tracking-wider">Source:</span>
                <div class="join">
                    <button @click="setSource('mine')" type="button"
                        class="btn btn-sm join-item"
                        :class="source === 'mine' ? 'btn-primary' : 'btn-ghost'">
                        Mine
                        <span class="badge badge-xs ml-1">{{ sourceCounts.mine }}</span>
                    </button>
                    <button @click="setSource('library')" type="button"
                        class="btn btn-sm join-item"
                        :class="source === 'library' ? 'btn-primary' : 'btn-ghost'">
                        Library
                        <span class="badge badge-xs ml-1">{{ sourceCounts.library }}</span>
                    </button>
                    <button @click="setSource('all')" type="button"
                        class="btn btn-sm join-item"
                        :class="source === 'all' ? 'btn-primary' : 'btn-ghost'">
                        All
                        <span class="badge badge-xs ml-1">{{ sourceCounts.all }}</span>
                    </button>
                </div>
                <span v-if="source === 'library'" class="text-xs text-base-content/55">DDO-shipped defaults · read-only.</span>
            </div>

            <div v-if="gradingScales?.length" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <section v-for="scale in gradingScales" :key="scale.id" class="surface overflow-hidden">
                    <header class="surface-header">
                        <h3>
                            {{ scale.name }}
                            <span v-if="scale.is_default" class="badge badge-warning badge-sm gap-1 ml-1">
                                <StarIcon class="w-3 h-3" /> Default
                            </span>
                        </h3>
                        <div class="flex gap-0.5">
                            <Link :href="route('grading-scales.edit', scale.id)" class="btn btn-ghost btn-xs btn-square" title="Edit">
                                <PencilSquareIcon class="w-4 h-4" />
                            </Link>
                            <button @click="confirmDeleteScale(scale)" class="btn btn-ghost btn-xs btn-square text-error" title="Delete">
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </div>
                    </header>
                    <div class="px-2 pb-2">
                        <p v-if="scale.school" class="text-xs text-base-content/55 px-3 pt-2 pb-1">School: <span class="font-semibold">{{ scale.school.name }}</span></p>
                        <table v-if="scale.entries?.length" class="table table-compact">
                            <thead>
                                <tr>
                                    <th>Grade</th>
                                    <th>Label</th>
                                    <th class="text-right">Min %</th>
                                    <th class="text-right">Max %</th>
                                    <th class="text-right">GP</th>
                                    <th>Remark</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="entry in scale.entries" :key="entry.id">
                                    <td><span class="badge badge-outline badge-sm font-bold">{{ entry.grade }}</span></td>
                                    <td class="text-[13px]">{{ entry.label || '—' }}</td>
                                    <td class="text-[13px] text-right tabular-nums">{{ entry.min_percentage }}%</td>
                                    <td class="text-[13px] text-right tabular-nums">{{ entry.max_percentage }}%</td>
                                    <td class="text-[13px] text-right font-bold tabular-nums">{{ entry.grade_point }}</td>
                                    <td class="text-[13px] text-base-content/65 truncate max-w-[140px]" :title="entry.remark">{{ entry.remark || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="text-sm text-base-content/45 text-center py-6">No grade entries defined.</p>
                    </div>
                </section>
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
