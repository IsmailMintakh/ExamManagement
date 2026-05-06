<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import StatCard from '@/Components/StatCard.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    PlusIcon, PencilSquareIcon, TrashIcon, EyeIcon, ArrowUpTrayIcon,
    QuestionMarkCircleIcon, MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    questions: Object,
    subjects: Array,
    classes: Array,
    filters: Object,
    stats: Object,
    sourceCounts: { type: Object, default: () => ({}) },
})

const filters = ref({
    search: props.filters?.search || '',
    subject_id: props.filters?.subject_id || '',
    school_class_id: props.filters?.school_class_id || '',
    type: props.filters?.type || '',
    difficulty: props.filters?.difficulty || '',
    topic: props.filters?.topic || '',
    // Source: 'mine' (default) | 'library' (DDO globals) | 'all'.
    // The default is 'mine' so a teacher's first view is strictly their own work.
    source: props.filters?.source || 'mine',
})

const confirmDelete = ref(false)
const toDelete = ref(null)

function applyFilters() {
    router.get(route('questions.index'), { ...filters.value }, { preserveState: true, replace: true })
}

function resetFilters() {
    filters.value = { search: '', subject_id: '', school_class_id: '', type: '', difficulty: '', topic: '', source: 'mine' }
    applyFilters()
}

// Switch between Mine / Library / All. Triggers a page reload with the new source.
function setSource(value) {
    filters.value.source = value
    applyFilters()
}

function askDelete(q) { toDelete.value = q; confirmDelete.value = true }
function doDelete() {
    if (toDelete.value) {
        router.delete(route('questions.destroy', toDelete.value.id), {
            onSuccess: () => { confirmDelete.value = false }
        })
    }
}

const typeLabels = {
    mcq: 'MCQ',
    short_answer: 'Short',
    long_answer: 'Long',
    true_false: 'T/F',
    fill_blank: 'Fill',
}
const typeColors = {
    mcq: 'badge-primary',
    short_answer: 'badge-info',
    long_answer: 'badge-accent',
    true_false: 'badge-secondary',
    fill_blank: 'badge-warning',
}
const diffColors = {
    easy: 'badge-success',
    medium: 'badge-warning',
    hard: 'badge-error',
}

function truncate(str, n = 90) {
    if (!str) return ''
    return str.length > n ? str.slice(0, n) + '...' : str
}

const subjectName = computed(() => (id) => props.subjects?.find(s => s.id == id)?.name || '')
</script>

<template>
    <Head title="Question Bank" />
    <AppLayout :breadcrumbs="[{ label: 'Question Bank' }]">
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold">Question Bank</h1>
                    <p class="text-sm text-base-content/60 mt-1">Build a reusable library of questions for paper generation.</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('questions.import')" class="btn btn-outline gap-2">
                        <ArrowUpTrayIcon class="w-5 h-5" /> Bulk Import
                    </Link>
                    <Link :href="route('questions.create')" class="btn btn-primary gap-2">
                        <PlusIcon class="w-5 h-5" /> Add Question
                    </Link>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                <StatCard title="Total" :value="stats?.total || 0" color="primary">
                    <template #icon><QuestionMarkCircleIcon class="h-5 w-5 text-primary" /></template>
                </StatCard>
                <StatCard title="MCQ" :value="stats?.by_type?.mcq || 0" color="info">
                    <template #icon><span class="font-bold text-info">M</span></template>
                </StatCard>
                <StatCard title="Short" :value="stats?.by_type?.short_answer || 0" color="accent">
                    <template #icon><span class="font-bold text-accent">S</span></template>
                </StatCard>
                <StatCard title="Long" :value="stats?.by_type?.long_answer || 0" color="secondary">
                    <template #icon><span class="font-bold text-secondary">L</span></template>
                </StatCard>
                <StatCard title="T / F" :value="stats?.by_type?.true_false || 0" color="warning">
                    <template #icon><span class="font-bold text-warning">T</span></template>
                </StatCard>
                <StatCard title="Fill Blank" :value="stats?.by_type?.fill_blank || 0" color="success">
                    <template #icon><span class="font-bold text-success">F</span></template>
                </StatCard>
            </div>

            <!-- Source toggle: Mine | Library | All. The teacher's default is
                 "Mine" so they don't have to wade through DDO library content
                 to find their own. Counts come from the controller. -->
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-base-content/55 text-xs font-semibold uppercase tracking-wider">Source:</span>
                <div class="join">
                    <button @click="setSource('mine')" type="button"
                        class="btn btn-sm join-item"
                        :class="filters.source === 'mine' ? 'btn-primary' : 'btn-ghost'">
                        Mine
                        <span class="badge badge-xs ml-1" :class="filters.source === 'mine' ? 'badge-primary-content' : 'badge-ghost'">
                            {{ sourceCounts.mine ?? 0 }}
                        </span>
                    </button>
                    <button @click="setSource('library')" type="button"
                        class="btn btn-sm join-item"
                        :class="filters.source === 'library' ? 'btn-primary' : 'btn-ghost'">
                        Library
                        <span class="badge badge-xs ml-1" :class="filters.source === 'library' ? 'badge-primary-content' : 'badge-ghost'">
                            {{ sourceCounts.library ?? 0 }}
                        </span>
                    </button>
                    <button @click="setSource('all')" type="button"
                        class="btn btn-sm join-item"
                        :class="filters.source === 'all' ? 'btn-primary' : 'btn-ghost'">
                        All
                        <span class="badge badge-xs ml-1" :class="filters.source === 'all' ? 'badge-primary-content' : 'badge-ghost'">
                            {{ sourceCounts.all ?? 0 }}
                        </span>
                    </button>
                </div>
                <span v-if="filters.source === 'library'" class="text-xs text-base-content/55 ml-2">
                    Read-only DDO library — to use one, you can copy it into your bank.
                </span>
                <span v-else-if="filters.source === 'mine' && (sourceCounts.mine ?? 0) === 0 && (sourceCounts.library ?? 0) > 0"
                    class="text-xs text-base-content/55 ml-2">
                    No questions of your own yet — try "Library" to see DDO-shared questions.
                </span>
            </div>

            <!-- Filters -->
            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
                        <div class="md:col-span-2">
                            <label class="text-[12px] font-semibold text-base-content/75">Search</label>
                            <div class="relative mt-1.5">
                                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-base-content/35" />
                                <input v-model="filters.search" @keyup.enter="applyFilters" type="text"
                                    placeholder="Search question text..."
                                    class="input input-bordered input-sm w-full pl-9" />
                            </div>
                        </div>
                        <div>
                            <label class="text-[12px] font-semibold text-base-content/75">Subject</label>
                            <select v-model="filters.subject_id" @change="applyFilters" class="select select-bordered select-sm w-full mt-1.5">
                                <option value="">All</option>
                                <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[12px] font-semibold text-base-content/75">Class</label>
                            <select v-model="filters.school_class_id" @change="applyFilters" class="select select-bordered select-sm w-full mt-1.5">
                                <option value="">All</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[12px] font-semibold text-base-content/75">Type</label>
                            <select v-model="filters.type" @change="applyFilters" class="select select-bordered select-sm w-full mt-1.5">
                                <option value="">All</option>
                                <option value="mcq">MCQ</option>
                                <option value="short_answer">Short Answer</option>
                                <option value="long_answer">Long Answer</option>
                                <option value="true_false">True / False</option>
                                <option value="fill_blank">Fill in the Blank</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[12px] font-semibold text-base-content/75">Difficulty</label>
                            <select v-model="filters.difficulty" @change="applyFilters" class="select select-bordered select-sm w-full mt-1.5">
                                <option value="">All</option>
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-end gap-2 mt-3">
                        <div class="flex-1">
                            <label class="text-[12px] font-semibold text-base-content/75">Topic</label>
                            <input v-model="filters.topic" @keyup.enter="applyFilters" type="text"
                                placeholder="e.g., Chapter 3 - Algebra"
                                class="input input-bordered input-sm w-full mt-1.5" />
                        </div>
                        <button @click="applyFilters" class="btn btn-primary btn-sm">Apply</button>
                        <button @click="resetFilters" class="btn btn-ghost btn-sm">Reset</button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card bg-base-100 shadow-md">
                <div class="card-body p-0">
                    <div v-if="questions?.data?.length" class="overflow-x-auto">
                        <table class="table table-zebra table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Question</th>
                                    <th>Subject</th>
                                    <th>Class</th>
                                    <th>Type</th>
                                    <th>Difficulty</th>
                                    <th>Marks</th>
                                    <th>Topic</th>
                                    <th>Used</th>
                                    <th>Created by</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(q, i) in questions.data" :key="q.id">
                                    <td class="text-xs text-base-content/50">{{ questions.from + i }}</td>
                                    <td class="max-w-md">
                                        <div class="text-sm font-medium">{{ truncate(q.question_text, 120) }}</div>
                                    </td>
                                    <td class="text-sm">{{ q.subject?.name || '—' }}</td>
                                    <td class="text-sm">{{ q.school_class?.name || 'All' }}</td>
                                    <td>
                                        <span class="badge badge-sm" :class="typeColors[q.type]">{{ typeLabels[q.type] }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-sm" :class="diffColors[q.difficulty]">{{ q.difficulty }}</span>
                                    </td>
                                    <td class="text-sm font-mono">{{ q.marks }}</td>
                                    <td class="text-xs text-base-content/60">{{ q.topic || '—' }}</td>
                                    <td class="text-xs text-center">{{ q.times_used }}</td>
                                    <td class="text-xs">{{ q.creator?.name || '—' }}</td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1">
                                            <Link :href="route('questions.show', q.id)" class="btn btn-ghost btn-xs btn-square" title="View">
                                                <EyeIcon class="w-4 h-4" />
                                            </Link>
                                            <Link :href="route('questions.edit', q.id)" class="btn btn-ghost btn-xs btn-square" title="Edit">
                                                <PencilSquareIcon class="w-4 h-4" />
                                            </Link>
                                            <button @click="askDelete(q)" class="btn btn-ghost btn-xs btn-square text-error" title="Delete">
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <EmptyState v-else
                        title="No questions yet"
                        description="Build your question bank by adding questions one-at-a-time or importing in bulk."
                        action-text="Add Your First Question"
                        :action-href="route('questions.create')"
                        :icon="QuestionMarkCircleIcon" />
                </div>
            </div>

            <div v-if="questions?.data?.length" class="mt-4">
                <Pagination :links="questions.links" :from="questions.from" :to="questions.to" :total="questions.total" />
            </div>
        </div>

        <ConfirmDialog
            :show="confirmDelete"
            title="Delete question?"
            message="This question will be removed from the bank."
            confirm-text="Delete"
            type="danger"
            @confirm="doDelete"
            @cancel="confirmDelete = false"
        />
    </AppLayout>
</template>
