<script setup>
/**
 * Board-exam detail — every student in the class with an inline status
 * pill (Entered / Pending) and a per-row "Enter" button that jumps into
 * the marks form.
 */
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import axios from 'axios'
import {
    AcademicCapIcon, ArrowLeftIcon, PencilSquareIcon, EyeIcon,
    CheckCircleIcon, ClockIcon, MagnifyingGlassIcon,
    UserGroupIcon, LockClosedIcon, TableCellsIcon,
    ArrowUpTrayIcon, ArrowDownTrayIcon, XMarkIcon,
    ExclamationTriangleIcon, ChartBarIcon, DocumentTextIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: { type: Object, required: true },
    students: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    canEdit: { type: Boolean, default: false },
})

const search = ref('')
const filter = ref('all')   // all | entered | pending | passed | failed | supply

const rows = computed(() => {
    let list = props.students
    if (search.value.trim()) {
        const q = search.value.toLowerCase()
        list = list.filter(s =>
            (s.name || '').toLowerCase().includes(q) ||
            (String(s.roll_no || '')).includes(q) ||
            (String(s.admission_no || '')).includes(q))
    }
    if (filter.value === 'entered')  return list.filter(s => s.has_result)
    if (filter.value === 'pending')  return list.filter(s => !s.has_result)
    if (filter.value === 'passed')   return list.filter(s => s.has_result && s.is_pass)
    if (filter.value === 'failed')   return list.filter(s => s.has_result && !s.is_pass && !s.is_supplementary)
    if (filter.value === 'supply')   return list.filter(s => s.has_result && s.is_supplementary)
    return list
})

const filterButtons = computed(() => [
    { key: 'all',     label: 'All',      count: props.stats.total ?? 0,   color: 'base-200' },
    { key: 'entered', label: 'Entered',  count: props.stats.entered ?? 0, color: 'emerald' },
    { key: 'pending', label: 'Pending',  count: props.stats.pending ?? 0, color: 'amber' },
    { key: 'passed',  label: 'Passed',   count: props.stats.passed ?? 0,  color: 'emerald' },
    { key: 'failed',  label: 'Failed',   count: props.stats.failed ?? 0,  color: 'rose' },
    { key: 'supply',  label: 'Supply',   count: props.stats.supply ?? 0,  color: 'sky' },
])

// ── Lock / unlock toggle ──
// Locking finalises the exam and makes it discoverable on the public
// result-search page. Unlocking hides it again + re-enables editing.
const lockBusy = ref(false)
function toggleLock() {
    const going = props.exam.is_locked ? 'unlock' : 'lock and publish'
    if (!confirm(`Are you sure you want to ${going} this exam?`)) return
    lockBusy.value = true
    router.post(route('board-results.toggle-lock', props.exam.id), {}, {
        preserveScroll: true,
        onFinish: () => { lockBusy.value = false },
    })
}

// ── Import modal state ──
const importOpen = ref(false)
const importFile = ref(null)
const importFileRef = ref(null)
const importBusy = ref(false)
const importPreview = ref(null)     // { preview: [...], total, errors: [...], error_total }
const importError = ref('')

function pickImportFile() { importFileRef.value?.click() }
async function onImportFileSelected(e) {
    const f = e.target.files?.[0]
    if (!f) return
    importFile.value = f
    importPreview.value = null
    importError.value = ''
    // Auto-run preview when a file is picked.
    await runPreview()
}
async function runPreview() {
    if (!importFile.value) return
    importBusy.value = true
    importError.value = ''
    try {
        const fd = new FormData()
        fd.append('file', importFile.value)
        fd.append('commit', '0')
        const { data } = await axios.post(
            route('board-results.import', props.exam.id),
            fd,
            { headers: { 'Content-Type': 'multipart/form-data' } }
        )
        importPreview.value = data
    } catch (err) {
        importError.value = err?.response?.data?.message || err?.message || 'Upload failed.'
    } finally {
        importBusy.value = false
    }
}
async function runCommit() {
    if (!importFile.value || !importPreview.value?.total) return
    if (!confirm(`Import ${importPreview.value.total} row(s) into this board exam?`)) return
    importBusy.value = true
    importError.value = ''
    try {
        const fd = new FormData()
        fd.append('file', importFile.value)
        fd.append('commit', '1')
        await axios.post(
            route('board-results.import', props.exam.id),
            fd,
            { headers: { 'Content-Type': 'multipart/form-data' } }
        )
        // Reload the page — server has fresh results + positions.
        router.visit(route('board-results.show', props.exam.id))
    } catch (err) {
        importError.value = err?.response?.data?.message || err?.message || 'Import failed.'
    } finally {
        importBusy.value = false
    }
}
function closeImport() {
    importOpen.value = false
    importFile.value = null
    importPreview.value = null
    importError.value = ''
    if (importFileRef.value) importFileRef.value.value = ''
}

const gradePill = (g) => ({
    'A1': 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    'A':  'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    'B':  'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    'C':  'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    'D':  'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    'E':  'bg-orange-500/15 text-orange-700 dark:text-orange-300',
    'F':  'bg-rose-500/15 text-rose-700 dark:text-rose-300',
}[g] || 'bg-base-200 text-base-content/60')
</script>

<template>
    <Head :title="`${exam.title} · Students`" />
    <AppLayout :breadcrumbs="[
        { label: 'Board Results', href: route('board-results.index') },
        { label: exam.title }
    ]">
        <div class="space-y-4 max-w-7xl mx-auto">
            <PageHeader :title="exam.title"
                        :subtitle="`${exam.school_class?.name} · ${exam.school?.name} · ${exam.academic_session?.name}`"
                        :icon="AcademicCapIcon" tone="violet">
                <template #actions>
                    <Link :href="route('board-results.index')" class="btn btn-ghost btn-sm rounded-xl gap-1.5">
                        <ArrowLeftIcon class="w-4 h-4" /> Back
                    </Link>
                    <!-- Reports group — analytics dashboard + summary PDF + class Excel -->
                    <Link :href="route('board-results.analytics', exam.id)"
                          class="btn btn-outline btn-sm rounded-xl gap-1.5"
                          title="Charts, position holders, subject-wise pass rate, fail / supply lists.">
                        <ChartBarIcon class="w-4 h-4" /> Analytics
                    </Link>
                    <a :href="route('board-results.summary-pdf', exam.id)" target="_blank"
                       class="btn btn-outline btn-sm rounded-xl gap-1.5"
                       title="Class summary as one printable PDF sheet.">
                        <DocumentTextIcon class="w-4 h-4" /> Summary PDF
                    </a>
                    <a :href="route('board-results.bulk-cards-pdf', exam.id)" target="_blank"
                       class="btn btn-outline btn-sm rounded-xl gap-1.5"
                       title="Every student's individual result card in one PDF (one per page). Print + fold + hand out.">
                        <DocumentTextIcon class="w-4 h-4" /> All Cards PDF
                    </a>
                    <button v-if="canEdit || exam.is_locked"
                            @click="toggleLock" :disabled="lockBusy"
                            class="btn btn-sm rounded-xl gap-1.5"
                            :class="exam.is_locked ? 'btn-warning btn-outline' : 'btn-success btn-outline'"
                            :title="exam.is_locked
                                ? 'Unlock — hides results from the public search page and re-enables editing.'
                                : 'Lock — finalises results and publishes them to the public search page.'">
                        <span v-if="lockBusy" class="loading loading-spinner loading-xs"></span>
                        <LockClosedIcon v-else class="w-4 h-4" />
                        {{ exam.is_locked ? 'Unlock' : 'Lock & Publish' }}
                    </button>
                    <a :href="route('board-results.export-xlsx', exam.id)"
                       class="btn btn-outline btn-sm rounded-xl gap-1.5"
                       title="Every student × every subject in one Excel workbook.">
                        <ArrowDownTrayIcon class="w-4 h-4" /> Export Excel
                    </a>
                    <!-- Entry / import group -->
                    <Link :href="route('board-results.subjects', exam.id)"
                          class="btn btn-outline btn-sm rounded-xl gap-1.5"
                          title="Set theory / practical max marks + passing % per subject. Applied to every student's entry form.">
                        <TableCellsIcon class="w-4 h-4" /> Subjects
                    </Link>
                    <a :href="route('board-results.template', exam.id)"
                       class="btn btn-outline btn-sm rounded-xl gap-1.5"
                       title="Download an Excel template pre-filled with this class's students, fill it offline, then Import.">
                        <ArrowDownTrayIcon class="w-4 h-4" /> Template
                    </a>
                    <button v-if="canEdit" @click="importOpen = true"
                            class="btn btn-outline btn-sm rounded-xl gap-1.5"
                            title="Upload a filled Excel template — previews before committing.">
                        <ArrowUpTrayIcon class="w-4 h-4" /> Import
                    </button>
                    <Link v-if="canEdit" :href="route('board-results.batch', exam.id)"
                          class="btn btn-primary btn-sm rounded-xl gap-1.5"
                          title="Enter marks for every student in one grid.">
                        <TableCellsIcon class="w-4 h-4" /> Batch Entry
                    </Link>
                    <span v-if="exam.is_locked"
                          class="inline-flex items-center gap-1 text-xs font-bold uppercase tracking-wider
                                 px-2 py-1 rounded-lg bg-amber-500/15 text-amber-700 dark:text-amber-300">
                        <LockClosedIcon class="w-3.5 h-3.5" /> Locked
                    </span>
                </template>
            </PageHeader>

            <!-- Filter chips + search -->
            <div class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm p-3 sm:p-4
                        flex items-center gap-2 flex-wrap">
                <div class="flex items-center gap-1.5 flex-wrap flex-1 min-w-[240px]">
                    <button v-for="f in filterButtons" :key="f.key"
                        @click="filter = f.key"
                        class="text-[11px] font-semibold px-2.5 py-1.5 rounded-lg border transition-colors
                               flex items-center gap-1.5"
                        :class="filter === f.key
                            ? 'bg-primary/15 text-primary border-primary/30'
                            : 'bg-base-100 text-base-content/65 border-base-300 hover:border-primary/30 hover:text-primary'">
                        {{ f.label }}
                        <span class="text-[10px] px-1 rounded bg-base-200 tabular-nums">{{ f.count }}</span>
                    </button>
                </div>
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-base-100 border border-base-300
                            focus-within:border-primary/60 min-w-[180px]">
                    <MagnifyingGlassIcon class="w-4 h-4 text-base-content/40 shrink-0" />
                    <input v-model="search" type="text" placeholder="Search name / roll…"
                           class="bg-transparent outline-none flex-1 text-xs min-w-0" />
                </div>
            </div>

            <!-- Students table -->
            <div class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm overflow-hidden">
                <div v-if="!rows.length" class="p-8 text-center text-sm text-base-content/60">
                    <UserGroupIcon class="w-8 h-8 mx-auto mb-2 text-base-content/30" />
                    No students match this filter.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider font-bold text-base-content/55">
                            <tr>
                                <th class="text-left px-4 py-2.5 w-16">Roll</th>
                                <th class="text-left px-2 py-2.5">Student</th>
                                <th class="text-left px-2 py-2.5">Board Roll</th>
                                <th class="text-right px-2 py-2.5">%</th>
                                <th class="text-center px-2 py-2.5 w-16">Grade</th>
                                <th class="text-center px-2 py-2.5 w-24">Division</th>
                                <th class="text-center px-2 py-2.5 w-20">Position</th>
                                <th class="text-right px-4 py-2.5 w-32">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-200">
                            <tr v-for="s in rows" :key="s.id" class="hover:bg-base-200/30 transition-colors">
                                <td class="px-4 py-2.5 tabular-nums text-base-content/70">{{ s.roll_no || '—' }}</td>
                                <td class="px-2 py-2.5">
                                    <p class="font-semibold text-sm">{{ s.name }}</p>
                                    <p class="text-[11px] text-base-content/55">{{ s.father_name }}</p>
                                </td>
                                <td class="px-2 py-2.5 text-xs tabular-nums text-base-content/60">{{ s.board_roll_no || '—' }}</td>
                                <td class="px-2 py-2.5 text-right tabular-nums font-semibold">
                                    <span v-if="s.has_result">{{ Number(s.percentage).toFixed(1) }}%</span>
                                    <span v-else class="text-base-content/30">—</span>
                                </td>
                                <td class="px-2 py-2.5 text-center">
                                    <span v-if="s.has_result"
                                          :class="['px-2 py-0.5 rounded-md text-[11px] font-bold', gradePill(s.grade)]">
                                        {{ s.grade }}
                                    </span>
                                    <span v-else class="text-base-content/30">—</span>
                                </td>
                                <td class="px-2 py-2.5 text-center text-xs">
                                    <span v-if="s.has_result" class="font-semibold"
                                          :class="s.is_pass ? 'text-emerald-700 dark:text-emerald-300'
                                                : s.is_supplementary ? 'text-sky-700 dark:text-sky-300'
                                                : 'text-rose-700 dark:text-rose-300'">
                                        {{ s.is_supplementary ? 'Supply' : s.division }}
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 text-amber-700 dark:text-amber-300 text-[11px] font-semibold">
                                        <ClockIcon class="w-3 h-3" /> Pending
                                    </span>
                                </td>
                                <td class="px-2 py-2.5 text-center tabular-nums text-xs font-semibold">
                                    <span v-if="s.position" class="px-2 py-0.5 rounded-md bg-base-200">#{{ s.position }}</span>
                                    <span v-else class="text-base-content/30">—</span>
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <a v-if="s.has_result"
                                           :href="route('board-results.student-card-pdf', [exam.id, s.id])"
                                           target="_blank"
                                           class="btn btn-xs btn-outline rounded-lg gap-1"
                                           title="Result Card PDF">
                                            <DocumentTextIcon class="w-3 h-3" />
                                        </a>
                                        <Link :href="route('board-results.enter-student', [exam.id, s.id])"
                                            class="btn btn-xs rounded-lg gap-1"
                                            :class="s.has_result ? 'btn-ghost' : 'btn-primary'">
                                            <PencilSquareIcon class="w-3 h-3" />
                                            {{ s.has_result ? 'Edit' : 'Enter' }}
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ═══ Import modal ═══
             Pick an Excel → preview (server validates rows) → Commit.
             Preview shows first 30 rows + any errors so the teacher can
             verify before committing to the DB. -->
        <Teleport to="body">
            <div v-if="importOpen" class="fixed inset-0 z-50 bg-black/60 flex items-start sm:items-center justify-center p-3 overflow-y-auto">
                <div class="w-full max-w-3xl bg-base-100 rounded-2xl shadow-2xl my-4">
                    <header class="flex items-center justify-between p-4 sm:p-5 border-b border-base-200">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary to-teal-600 text-primary-content
                                        flex items-center justify-center shadow-sm">
                                <ArrowUpTrayIcon class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="text-base font-bold">Import Marks from Excel</h3>
                                <p class="text-xs text-base-content/60">
                                    Fill the downloaded template offline, then upload it here.
                                </p>
                            </div>
                        </div>
                        <button @click="closeImport" class="btn btn-sm btn-ghost btn-circle">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </header>

                    <div class="p-4 sm:p-5 space-y-4">
                        <!-- File pick zone -->
                        <div v-if="!importFile" @click="pickImportFile"
                             class="border-2 border-dashed border-base-300 rounded-xl p-6 sm:p-8 text-center cursor-pointer
                                    hover:bg-base-200/40 transition-colors">
                            <ArrowUpTrayIcon class="w-10 h-10 mx-auto text-base-content/40 mb-2" />
                            <div class="font-semibold text-sm">Tap to choose an .xlsx / .csv file</div>
                            <div class="text-xs text-base-content/55 mt-1">
                                Only the template format is accepted.
                                <a :href="route('board-results.template', exam.id)"
                                   class="text-primary font-semibold underline" @click.stop>Download template</a>
                            </div>
                        </div>

                        <!-- File chosen — preview or errors -->
                        <div v-else>
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <p class="text-sm">
                                    <b>{{ importFile.name }}</b>
                                    <span class="text-base-content/50 ml-1">({{ (importFile.size / 1024).toFixed(0) }} KB)</span>
                                </p>
                                <button @click="pickImportFile" class="btn btn-ghost btn-xs gap-1">Change</button>
                            </div>

                            <div v-if="importBusy" class="rounded-xl border border-base-200 p-4 text-center">
                                <span class="loading loading-spinner loading-sm"></span>
                                <p class="text-xs text-base-content/60 mt-2">Reading…</p>
                            </div>

                            <div v-if="importError" class="rounded-xl border border-error/30 bg-error/10 p-3 text-sm text-error">
                                <ExclamationTriangleIcon class="w-5 h-5 inline mr-1" />
                                {{ importError }}
                            </div>

                            <div v-if="importPreview" class="space-y-3">
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
                                    <div class="rounded-lg bg-emerald-500/10 border border-emerald-500/20 p-2.5">
                                        <p class="text-[10px] uppercase tracking-wider font-bold text-emerald-700 dark:text-emerald-300">Rows OK</p>
                                        <p class="text-lg font-extrabold tabular-nums">{{ importPreview.total }}</p>
                                    </div>
                                    <div v-if="importPreview.error_total > 0"
                                         class="rounded-lg bg-rose-500/10 border border-rose-500/20 p-2.5">
                                        <p class="text-[10px] uppercase tracking-wider font-bold text-rose-700 dark:text-rose-300">Errors</p>
                                        <p class="text-lg font-extrabold tabular-nums">{{ importPreview.error_total }}</p>
                                    </div>
                                </div>

                                <div v-if="importPreview.errors?.length"
                                     class="rounded-xl border border-rose-500/25 bg-rose-500/5 p-3 text-xs max-h-40 overflow-auto">
                                    <p class="font-bold text-rose-700 dark:text-rose-300 mb-1">Rows with problems (first 30):</p>
                                    <ul class="list-disc pl-4 space-y-0.5 text-rose-800 dark:text-rose-200">
                                        <li v-for="(e, i) in importPreview.errors" :key="i">{{ e }}</li>
                                    </ul>
                                </div>

                                <div v-if="importPreview.preview?.length"
                                     class="rounded-xl border border-base-200 overflow-hidden">
                                    <div class="px-3 py-2 bg-base-200/40 text-[10px] uppercase tracking-wider font-bold text-base-content/55">
                                        Preview (first 30 rows)
                                    </div>
                                    <div class="max-h-64 overflow-auto">
                                        <table class="w-full text-xs">
                                            <thead class="bg-base-200/30 text-[10px] uppercase text-base-content/55">
                                                <tr>
                                                    <th class="text-left px-3 py-1.5">Roll</th>
                                                    <th class="text-left px-2 py-1.5">Name</th>
                                                    <th class="text-left px-2 py-1.5">Board #</th>
                                                    <th class="text-right px-3 py-1.5">Cells</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-base-200">
                                                <tr v-for="p in importPreview.preview" :key="p.student_id">
                                                    <td class="px-3 py-1.5 tabular-nums">{{ p.roll_no }}</td>
                                                    <td class="px-2 py-1.5">{{ p.name }}</td>
                                                    <td class="px-2 py-1.5 tabular-nums text-base-content/60">{{ p.board_roll_no || '—' }}</td>
                                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ p.cells.length }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <input ref="importFileRef" type="file" accept=".xlsx,.xls,.csv"
                           class="hidden" @change="onImportFileSelected" />

                    <footer class="flex items-center justify-between gap-2 p-4 border-t border-base-200 bg-base-200/30">
                        <p class="text-[11px] text-base-content/60">
                            <span v-if="importPreview">
                                <b>{{ importPreview.total }}</b> rows ready to commit
                            </span>
                        </p>
                        <div class="flex items-center gap-2">
                            <button @click="closeImport" class="btn btn-sm btn-ghost">Cancel</button>
                            <button @click="runCommit"
                                    :disabled="importBusy || !importPreview?.total"
                                    class="btn btn-sm btn-primary gap-1.5">
                                <CheckCircleIcon class="w-4 h-4" />
                                Commit {{ importPreview?.total || 0 }} row{{ (importPreview?.total || 0) === 1 ? '' : 's' }}
                            </button>
                        </div>
                    </footer>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
