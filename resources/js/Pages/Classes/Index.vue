<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import Pagination from '@/Components/Pagination.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import {
    PlusIcon, PencilSquareIcon, TrashIcon, MagnifyingGlassIcon,
    FunnelIcon, ChevronDownIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'
import { usePermissions } from '@/Composables/usePermissions'

const { can } = usePermissions()

const props = defineProps({
    classes: Object,
    filters: Object,
    schools: Array,
    stages: { type: Object, default: () => ({}) },
})

const search = ref(props.filters?.search || '')
const schoolId = ref(props.filters?.school_id || '')
const stageFilter = ref(props.filters?.stage || '')

const activeFilterCount = computed(() =>
    [schoolId.value, stageFilter.value].filter(Boolean).length)
const filtersOpen = ref(activeFilterCount.value > 0)

let timer = null
function pushFilters() {
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => {
        router.get(route('classes.index'), {
            search: search.value || undefined,
            school_id: schoolId.value || undefined,
            stage: stageFilter.value || undefined,
        }, {
            preserveState: true, preserveScroll: true, replace: true, only: ['classes', 'filters'],
        })
    }, 300)
}
watch([search, schoolId, stageFilter], pushFilters)

function clearFilters() { schoolId.value = ''; stageFilter.value = '' }

const stageBadge = (s) => ({
    pre_primary: 'badge-info',
    primary: 'badge-success',
    middle: 'badge-warning',
    secondary: 'badge-accent',
    higher_secondary: 'badge-primary',
}[s] || 'badge-ghost')

const confirmDelete = ref(false)
const classToDelete = ref(null)

function confirmDeleteClass(cls) {
    classToDelete.value = cls
    confirmDelete.value = true
}

function deleteClass() {
    if (classToDelete.value) {
        router.delete(route('classes.destroy', classToDelete.value.id), {
            onSuccess: () => { confirmDelete.value = false; classToDelete.value = null }
        })
    }
}
</script>

<template>
    <Head title="Classes" />
    <AppLayout :breadcrumbs="[{ label: 'Classes' }]">
        <div class="space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight">Classes</h1>
                    <p class="text-sm text-base-content/55 mt-0.5">
                        {{ classes?.total || 0 }} class{{ (classes?.total || 0) === 1 ? '' : 'es' }}
                        <span v-if="search">matching "{{ search }}"</span>
                    </p>
                </div>
                <Link v-if="can('classes.create')" :href="route('classes.create')" class="btn btn-primary btn-sm gap-1.5">
                    <PlusIcon class="w-4 h-4" /> Add Class
                </Link>
            </div>

            <section class="surface overflow-hidden">
                <header class="surface-header">
                    <div class="relative flex-1 max-w-md">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-base-content/40" />
                        <input v-model="search" type="text"
                            placeholder="Search classes…"
                            class="input input-bordered input-sm w-full pl-9 text-sm" />
                    </div>
                    <button v-if="schools?.length" type="button" @click="filtersOpen = !filtersOpen"
                        class="btn btn-sm gap-1.5"
                        :class="filtersOpen ? 'btn-primary' : 'btn-outline'">
                        <FunnelIcon class="w-4 h-4" /> Filters
                        <span v-if="activeFilterCount > 0" class="badge badge-sm badge-warning text-warning-content tabular-nums">{{ activeFilterCount }}</span>
                        <ChevronDownIcon class="w-3.5 h-3.5 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" />
                    </button>
                </header>

                <Transition name="filter-panel">
                    <div v-if="filtersOpen && schools?.length" class="border-b border-base-200 bg-base-200/30 px-5 sm:px-6 py-4 space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/60 mb-1.5 block">School</label>
                                <SearchableSelect v-model="schoolId" size="sm"
                                    :options="[{ value: '', label: 'All schools' }, ...(schools || []).map(s => ({ value: s.id, label: s.name }))]"
                                    placeholder="All schools" />
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-wider text-base-content/60 mb-1.5 block">Stage</label>
                                <select v-model="stageFilter" class="select select-bordered select-sm w-full text-sm">
                                    <option value="">All stages</option>
                                    <option v-for="(label, key) in stages" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>
                        </div>
                        <div v-if="activeFilterCount > 0" class="flex items-center justify-between gap-2 pt-2 border-t border-base-200">
                            <span class="text-xs text-base-content/55">
                                <span class="font-bold text-base-content">{{ activeFilterCount }}</span>
                                filter applied
                                · {{ classes?.total || 0 }} class{{ (classes?.total || 0) === 1 ? '' : 'es' }} found
                            </span>
                            <button type="button" @click="clearFilters" class="btn btn-ghost btn-xs gap-1 text-base-content/65">
                                <XMarkIcon class="w-3.5 h-3.5" /> Clear
                            </button>
                        </div>
                    </div>
                </Transition>

                <div class="table-sticky-wrap" style="--table-max-h: 65vh;" v-if="classes?.data?.length">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="w-12">#</th>
                                <th>School</th>
                                <th>Class</th>
                                <th>Stage</th>
                                <th class="text-center">Sections</th>
                                <th class="text-center">Students</th>
                                <th class="text-center">Subjects</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(cls, i) in classes.data" :key="cls.id">
                                <td class="text-xs font-mono text-base-content/55 tabular-nums">{{ classes.from + i }}</td>
                                <td class="text-[13px] text-base-content/75 truncate max-w-[200px]" :title="cls.school?.name">{{ cls.school?.name || '—' }}</td>
                                <td class="font-bold text-sm">{{ cls.name }}</td>
                                <td>
                                    <span v-if="cls.stage" class="badge badge-sm" :class="stageBadge(cls.stage)">
                                        {{ stages?.[cls.stage] || cls.stage }}
                                    </span>
                                    <span v-else class="text-xs text-base-content/40">—</span>
                                </td>
                                <td class="text-center"><span class="badge badge-info badge-sm tabular-nums">{{ cls.sections_count ?? 0 }}</span></td>
                                <td class="text-center"><span class="badge badge-ghost badge-sm tabular-nums">{{ cls.students_count ?? 0 }}</span></td>
                                <td class="text-center"><span class="badge badge-ghost badge-sm tabular-nums">{{ cls.subjects_count ?? 0 }}</span></td>
                                <td class="text-right whitespace-nowrap">
                                    <div class="flex gap-0.5 justify-end">
                                        <Link v-if="can('classes.edit')" :href="route('classes.edit', cls.id)" class="btn btn-ghost btn-xs btn-square" title="Edit">
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </Link>
                                        <button v-if="can('classes.delete')" @click="confirmDeleteClass(cls)" class="btn btn-ghost btn-xs btn-square text-error" title="Delete">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState v-if="!classes?.data?.length"
                    title="No classes found"
                    :description="search ? 'Try a different search term.' : 'Get started by adding your first class.'"
                    action-text="Add Class"
                    :action-href="can('classes.create') ? route('classes.create') : null" />

                <footer v-if="classes?.data?.length && classes.last_page > 1" class="surface-footer">
                    <span class="text-xs text-base-content/55 font-medium">
                        Showing <span class="text-base-content font-bold">{{ classes.from }}–{{ classes.to }}</span>
                        of <span class="text-base-content font-bold">{{ classes.total }}</span>
                    </span>
                    <Pagination :links="classes.links" />
                </footer>
            </section>
        </div>

        <ConfirmDialog
            :show="confirmDelete"
            title="Delete Class"
            :message="`Are you sure you want to delete ${classToDelete?.name}? This will remove all associated sections and data.`"
            type="danger"
            @confirm="deleteClass"
            @cancel="confirmDelete = false"
        />
    </AppLayout>
</template>

<style scoped>
.filter-panel-enter-active,
.filter-panel-leave-active {
    transition: opacity 0.2s ease, max-height 0.25s ease;
    overflow: hidden;
}
.filter-panel-enter-from,
.filter-panel-leave-to {
    opacity: 0;
    max-height: 0;
}
.filter-panel-enter-to,
.filter-panel-leave-from {
    opacity: 1;
    max-height: 400px;
}
</style>
