<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchFilter from '@/Components/SearchFilter.vue'
import FormSelect from '@/Components/FormSelect.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { PlusIcon, TrashIcon, XMarkIcon, BoltIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import { useDebouncedSearch } from '@/Composables/useDebouncedSearch'

const props = defineProps({
    assignments: Object,
    filters: Object,
    teachers: Array,
    subjects: Array,
    classes: Array,
    sections: Array,
    sessions: Array,
    currentSession: Object,
})

const search = useDebouncedSearch({
    routeName: 'teacher-assignments.index',
    initial: props.filters?.search || '',
    only: ['assignments', 'filters'],
})
const confirmDelete = ref(false)
const assignmentToDelete = ref(null)
const showForm = ref(false)

const form = useForm({
    user_id: '',
    subject_id: '',
    school_class_id: '',
    section_id: '',
    academic_session_id: props.currentSession?.id || '',
})

function confirmDeleteAssignment(assignment) {
    assignmentToDelete.value = assignment
    confirmDelete.value = true
}

function deleteAssignment() {
    if (assignmentToDelete.value) {
        router.delete(route('teacher-assignments.destroy', assignmentToDelete.value.id), {
            onSuccess: () => { confirmDelete.value = false; assignmentToDelete.value = null }
        })
    }
}

function submitForm() {
    form.post(route('teacher-assignments.store'), {
        onSuccess: () => {
            form.reset()
            showForm.value = false
        }
    })
}

// ─────────────────── BULK ASSIGN ───────────────────
const bulkOpen = ref(false)
const bulkTeacherId = ref('')
const bulkLoading = ref(false)
const bulkSaving = ref(false)
// Picked set keyed by `${section_id}:${subject_id}`.
const bulkPicked = ref(new Set())
const bulkOriginal = ref(0)

const sectionMap = computed(() => {
    const m = {}
    for (const s of (props.sections || [])) m[s.id] = s
    return m
})
const bulkCount = computed(() => bulkPicked.value.size)

function openBulk() { bulkOpen.value = true; bulkTeacherId.value = ''; bulkPicked.value = new Set() }
function closeBulk() { bulkOpen.value = false }

watch(bulkTeacherId, async (id) => {
    bulkPicked.value = new Set()
    bulkOriginal.value = 0
    if (!id) return
    bulkLoading.value = true
    try {
        const res = await fetch(route('teacher-assignments.current', id), { credentials: 'same-origin' })
        const data = await res.json()
        const set = new Set()
        for (const a of (data.assignments || [])) set.add(`${a.section_id}:${a.subject_id}`)
        bulkPicked.value = set
        bulkOriginal.value = set.size
    } catch (e) {
        // ignore — leave empty
    } finally {
        bulkLoading.value = false
    }
})

function togglePick(sectionId, subjectId) {
    const k = `${sectionId}:${subjectId}`
    const next = new Set(bulkPicked.value)
    next.has(k) ? next.delete(k) : next.add(k)
    bulkPicked.value = next
}
function isPicked(sectionId, subjectId) {
    return bulkPicked.value.has(`${sectionId}:${subjectId}`)
}
function pickAllInSection(sectionId) {
    const next = new Set(bulkPicked.value)
    for (const s of props.subjects || []) next.add(`${sectionId}:${s.id}`)
    bulkPicked.value = next
}
function clearAllInSection(sectionId) {
    const next = new Set(bulkPicked.value)
    for (const s of props.subjects || []) next.delete(`${sectionId}:${s.id}`)
    bulkPicked.value = next
}

function saveBulk() {
    if (!bulkTeacherId.value || !props.currentSession?.id) return
    const assignments = [...bulkPicked.value].map(k => {
        const [secId, subId] = k.split(':').map(Number)
        return {
            subject_id: subId,
            section_id: secId,
            school_class_id: sectionMap.value[secId]?.school_class_id,
        }
    }).filter(a => a.school_class_id)

    bulkSaving.value = true
    router.post(route('teacher-assignments.bulk'), {
        user_id: bulkTeacherId.value,
        academic_session_id: props.currentSession.id,
        assignments,
    }, {
        preserveScroll: true,
        onSuccess: () => { bulkOpen.value = false },
        onFinish: () => { bulkSaving.value = false },
    })
}
</script>

<template>
    <Head title="Teacher Assignments" />
    <AppLayout :breadcrumbs="[{ label: 'Teacher Assignments' }]">
        <div class="space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight">Teacher Assignments</h1>
                    <p class="text-sm text-base-content/55 mt-0.5">
                        {{ assignments?.total || 0 }} assignment{{ (assignments?.total || 0) === 1 ? '' : 's' }}
                        <span v-if="search">matching "{{ search }}"</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="openBulk" class="btn btn-sm rounded-lg gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white border-0">
                        <BoltIcon class="w-4 h-4" /> Bulk Assign
                    </button>
                    <button @click="showForm = !showForm" class="btn btn-primary btn-sm gap-1.5">
                        <PlusIcon v-if="!showForm" class="w-4 h-4" />
                        <XMarkIcon v-else class="w-4 h-4" />
                        {{ showForm ? 'Cancel' : 'Add one' }}
                    </button>
                </div>
            </div>

            <!-- Inline Add Form -->
            <section v-if="showForm" class="surface surface-accent-left">
                <header class="surface-header">
                    <h3>
                        <span class="w-7 h-7 rounded-lg bg-primary/15 text-primary flex items-center justify-center">
                            <PlusIcon class="w-4 h-4" />
                        </span>
                        New Teacher Assignment
                    </h3>
                </header>
                <div class="surface-body">
                    <form @submit.prevent="submitForm" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <FormSelect v-model="form.user_id" label="Teacher" :error="form.errors.user_id" required
                                :options="teachers?.map(t => ({ value: t.id, label: t.name })) || []" placeholder="Select Teacher" />
                            <FormSelect v-model="form.subject_id" label="Subject" :error="form.errors.subject_id" required
                                :options="subjects?.map(s => ({ value: s.id, label: s.name })) || []" placeholder="Select Subject" />
                            <FormSelect v-model="form.school_class_id" label="Class" :error="form.errors.school_class_id" required
                                :options="classes?.map(c => ({ value: c.id, label: c.name })) || []" placeholder="Select Class" />
                            <FormSelect v-model="form.section_id" label="Section" :error="form.errors.section_id" required
                                :options="sections?.filter(s => !form.school_class_id || s.school_class_id == form.school_class_id).map(s => ({ value: s.id, label: s.name })) || []" placeholder="Select Section" />
                            <FormSelect v-model="form.academic_session_id" label="Session" :error="form.errors.academic_session_id" required
                                :options="sessions?.map(s => ({ value: s.id, label: s.name })) || []" placeholder="Select Session" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="showForm = false" class="btn btn-ghost btn-sm">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm" :disabled="form.processing">
                                <span v-if="form.processing" class="loading loading-spinner loading-xs"></span>
                                Save Assignment
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="surface overflow-hidden">
                <header class="surface-header">
                    <div class="flex-1 max-w-md">
                        <SearchFilter v-model="search" placeholder="Search assignments…" />
                    </div>
                </header>

                <div class="table-sticky-wrap" style="--table-max-h: 65vh;" v-if="assignments?.data?.length">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="w-12">#</th>
                                <th>Teacher</th>
                                <th>Subject</th>
                                <th>Class</th>
                                <th>Section</th>
                                <th>Session</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(assignment, i) in assignments.data" :key="assignment.id">
                                <td class="text-xs font-mono text-base-content/55 tabular-nums">{{ assignments.from + i }}</td>
                                <td>
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-slate-500 to-slate-700 text-white flex items-center justify-center text-xs font-bold flex-shrink-0">
                                            {{ assignment.user?.name?.charAt(0)?.toUpperCase() }}
                                        </div>
                                        <span class="font-bold text-sm truncate">{{ assignment.user?.name || '—' }}</span>
                                    </div>
                                </td>
                                <td class="text-[13px] text-base-content/75">{{ assignment.subject?.name || '—' }}</td>
                                <td class="text-[13px] text-base-content/75">{{ assignment.school_class?.name || '—' }}</td>
                                <td class="text-[13px] text-base-content/75">{{ assignment.section?.name || '—' }}</td>
                                <td class="text-[13px] text-base-content/75">{{ assignment.academic_session?.name || '—' }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <button @click="confirmDeleteAssignment(assignment)" class="btn btn-ghost btn-xs btn-square text-error" title="Remove">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState v-if="!assignments?.data?.length"
                    title="No teacher assignments found"
                    :description="search ? 'Try a different search term.' : 'Assign teachers to subjects and classes to get started.'" />

                <footer v-if="assignments?.data?.length && assignments.last_page > 1" class="surface-footer">
                    <span class="text-xs text-base-content/55 font-medium">
                        Showing <span class="text-base-content font-bold">{{ assignments.from }}–{{ assignments.to }}</span>
                        of <span class="text-base-content font-bold">{{ assignments.total }}</span>
                    </span>
                    <Pagination :links="assignments.links" />
                </footer>
            </section>
        </div>

        <!-- ═══ BULK ASSIGN modal ═══ -->
        <div v-if="bulkOpen" class="modal modal-open" @keydown.esc="closeBulk">
            <div class="modal-box max-w-5xl">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 grid place-items-center">
                        <BoltIcon class="w-4 h-4" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-base font-bold">Bulk assign — one teacher, many subjects/sections</h3>
                        <p class="text-[11px] text-base-content/55">Pick a teacher, tick every subject they teach in each section, save once. Re-running replaces their previous list.</p>
                    </div>
                    <button @click="closeBulk" class="btn btn-ghost btn-sm btn-square"><XMarkIcon class="w-4 h-4" /></button>
                </div>

                <!-- Teacher picker -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
                    <div class="sm:col-span-2">
                        <FormSelect v-model="bulkTeacherId" label="Teacher"
                            :options="teachers?.map(t => ({ value: t.id, label: t.name })) || []"
                            placeholder="Select a teacher to begin" />
                    </div>
                    <div class="flex items-end">
                        <p class="text-[11px] text-base-content/55">
                            <span v-if="bulkLoading">Loading current assignments…</span>
                            <span v-else-if="bulkTeacherId">
                                <CheckCircleIcon class="w-3.5 h-3.5 inline text-emerald-500" />
                                {{ bulkOriginal }} existing · <strong>{{ bulkCount }}</strong> selected
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Matrix: per class → per section → subject checkboxes -->
                <div v-if="bulkTeacherId && !bulkLoading"
                    class="max-h-[55vh] overflow-y-auto rounded-lg border border-base-300 divide-y divide-base-300">
                    <div v-for="cls in classes" :key="cls.id" class="p-3">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-base-content/60 mb-2">{{ cls.name }}</p>
                        <div v-if="!cls.sections?.length" class="text-[11px] text-base-content/45 italic">No sections.</div>
                        <div v-else class="space-y-2">
                            <div v-for="sec in cls.sections" :key="sec.id"
                                class="rounded-lg border border-base-300 bg-base-200/30 p-2.5">
                                <div class="flex items-center justify-between mb-1.5 flex-wrap gap-2">
                                    <p class="text-sm font-bold">Section {{ sec.name }}</p>
                                    <div class="flex items-center gap-1">
                                        <button @click="pickAllInSection(sec.id)" type="button"
                                            class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 hover:underline">All</button>
                                        <span class="text-base-content/30">·</span>
                                        <button @click="clearAllInSection(sec.id)" type="button"
                                            class="text-[10px] font-bold text-rose-600 dark:text-rose-400 hover:underline">None</button>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <button v-for="s in subjects" :key="s.id" type="button"
                                        @click="togglePick(sec.id, s.id)"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold rounded-md px-2 py-1 ring-1 transition-colors"
                                        :class="isPicked(sec.id, s.id)
                                            ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-500/40'
                                            : 'bg-base-100 text-base-content/65 ring-base-300 hover:bg-base-200'">
                                        <CheckCircleIcon v-if="isPicked(sec.id, s.id)" class="w-3 h-3" />
                                        {{ s.name }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else-if="!bulkTeacherId" class="rounded-lg border border-base-300 bg-base-200/30 p-6 text-center text-sm text-base-content/55">
                    Choose a teacher above to start ticking subjects per section.
                </div>

                <div class="modal-action">
                    <button @click="closeBulk" class="btn btn-ghost btn-sm">Cancel</button>
                    <button @click="saveBulk" :disabled="!bulkTeacherId || bulkSaving"
                        class="btn btn-primary btn-sm gap-1.5">
                        <span v-if="bulkSaving" class="loading loading-spinner loading-xs"></span>
                        Save {{ bulkCount }} assignment{{ bulkCount === 1 ? '' : 's' }}
                    </button>
                </div>
            </div>
            <div class="modal-backdrop" @click="closeBulk"></div>
        </div>

        <ConfirmDialog
            :show="confirmDelete"
            title="Delete Assignment"
            message="Are you sure you want to remove this teacher assignment?"
            type="danger"
            @confirm="deleteAssignment"
            @cancel="confirmDelete = false"
        />
    </AppLayout>
</template>
