<script setup>
/**
 * Grid batch entry — all students (rows) × all subjects (columns).
 * Every cell is theory / practical inputs. Live-updating overall %
 * per row so teachers can spot obvious data-entry mistakes as they type.
 */
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    AcademicCapIcon, ArrowLeftIcon, TableCellsIcon,
    DocumentCheckIcon, MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: { type: Object, required: true },
    subjects: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
})

const form = useForm({
    rows: props.rows.map(r => ({
        student_id: r.student_id,
        board_roll_no: r.board_roll_no ?? '',
        cells: r.cells.map(c => ({ ...c })),
    })),
})

// Search filter — hides rows that don't match name / roll.
const search = ref('')
const visibleRowIndexes = computed(() => {
    if (!search.value.trim()) return props.rows.map((_, i) => i)
    const q = search.value.toLowerCase()
    return props.rows
        .map((r, i) => ({ r, i }))
        .filter(({ r }) =>
            (r.name || '').toLowerCase().includes(q) ||
            String(r.roll_no || '').includes(q) ||
            String(r.admission_no || '').includes(q))
        .map(({ i }) => i)
})

// Live overall % per row so teachers see obvious typos immediately.
function rowPercent(cells) {
    let obt = 0, max = 0
    for (const c of cells) {
        const t = (Number(c.theory_marks)    || 0) + (Number(c.practical_marks) || 0)
        const m = (Number(c.theory_max)      || 0) + (Number(c.practical_max)   || 0)
        if (m > 0) { obt += t; max += m }
    }
    if (max === 0) return null
    return Math.round((obt / max) * 100 * 10) / 10
}

const enteredCount = computed(() =>
    form.rows.filter(r =>
        (r.board_roll_no && r.board_roll_no.trim() !== '') ||
        r.cells.some(c =>
            (c.theory_marks !== null && c.theory_marks !== '') ||
            (c.practical_marks !== null && c.practical_marks !== '') ||
            c.is_absent)
    ).length
)

function submit() {
    form.post(route('board-results.batch-store', props.exam.id))
}
</script>

<template>
    <Head :title="`Batch entry · ${exam.title}`" />
    <AppLayout :breadcrumbs="[
        { label: 'Board Results', href: route('board-results.index') },
        { label: exam.title, href: route('board-results.show', exam.id) },
        { label: 'Batch' }
    ]">
        <div class="space-y-4 max-w-full">
            <PageHeader :title="`Batch Entry — ${exam.title}`"
                        :subtitle="`${exam.school_class?.name} · ${exam.school?.name} · ${enteredCount} of ${form.rows.length} students touched`"
                        :icon="TableCellsIcon" tone="violet">
                <template #actions>
                    <Link :href="route('board-results.show', exam.id)" class="btn btn-ghost btn-sm rounded-xl gap-1.5">
                        <ArrowLeftIcon class="w-4 h-4" /> Back
                    </Link>
                    <button type="button" @click="submit" :disabled="form.processing || !canEdit"
                            class="btn btn-primary btn-sm rounded-xl gap-1.5">
                        <span v-if="form.processing" class="loading loading-spinner loading-xs"></span>
                        <DocumentCheckIcon v-else class="w-4 h-4" />
                        Save all
                    </button>
                </template>
            </PageHeader>

            <!-- Search -->
            <div class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm p-3 sm:p-4 flex items-center gap-3 flex-wrap">
                <div class="flex-1 min-w-[200px] flex items-center gap-2 px-3 py-2 rounded-lg bg-base-100 border border-base-300 focus-within:border-primary/60">
                    <MagnifyingGlassIcon class="w-4 h-4 text-base-content/40 shrink-0" />
                    <input v-model="search" type="text" placeholder="Search name / roll…"
                           class="bg-transparent outline-none flex-1 text-xs" />
                </div>
                <p class="text-xs text-base-content/60">
                    Fill any subset — blank rows / cells are skipped on save.
                </p>
            </div>

            <!-- Grid -->
            <div class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <!-- Two-row header: subject name spans 2 cols, then Theory / Practical labels below -->
                            <tr class="bg-base-200/60">
                                <th class="sticky left-0 z-20 bg-base-200/60 text-left px-3 py-2 w-14">Roll</th>
                                <th class="sticky left-14 z-20 bg-base-200/60 text-left px-2 py-2 min-w-[140px]">Name</th>
                                <th class="sticky left-[168px] z-20 bg-base-200/60 text-left px-2 py-2 w-24">Board #</th>
                                <th v-for="sub in subjects" :key="'h-' + sub.id"
                                    :colspan="2"
                                    class="text-center px-2 py-2 border-l border-base-300 font-semibold">
                                    {{ sub.name }}
                                </th>
                                <th class="text-right px-3 py-2 w-16 border-l border-base-300">%</th>
                            </tr>
                            <tr class="bg-base-200/30 text-[10px] uppercase tracking-wider font-bold text-base-content/55">
                                <th class="sticky left-0 z-20 bg-base-200/30"></th>
                                <th class="sticky left-14 z-20 bg-base-200/30"></th>
                                <th class="sticky left-[168px] z-20 bg-base-200/30"></th>
                                <template v-for="sub in subjects" :key="'h2-' + sub.id">
                                    <th class="text-center px-1 py-1 border-l border-base-300">Th</th>
                                    <th class="text-center px-1 py-1">Pr</th>
                                </template>
                                <th class="border-l border-base-300"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-200">
                            <template v-for="(r, ri) in form.rows" :key="r.student_id">
                                <tr v-if="visibleRowIndexes.includes(ri)"
                                    class="hover:bg-base-200/20">
                                    <td class="sticky left-0 z-10 bg-base-100 px-3 py-1 tabular-nums text-base-content/70">
                                        {{ props.rows[ri].roll_no || '—' }}
                                    </td>
                                    <td class="sticky left-14 z-10 bg-base-100 px-2 py-1">
                                        <p class="font-semibold text-xs truncate max-w-[160px]">{{ props.rows[ri].name }}</p>
                                    </td>
                                    <td class="sticky left-[168px] z-10 bg-base-100 px-1 py-1">
                                        <input v-model="r.board_roll_no" type="text"
                                               class="input input-bordered input-xs w-20 text-[11px] tabular-nums"
                                               placeholder="—" />
                                    </td>
                                    <template v-for="(c, ci) in r.cells" :key="'c-' + ri + '-' + ci">
                                        <td class="px-1 py-1 border-l border-base-200">
                                            <input v-model.number="c.theory_marks" type="number" step="0.5" min="0"
                                                   :max="c.theory_max"
                                                   :disabled="c.is_absent || !canEdit"
                                                   class="input input-bordered input-xs w-14 text-right tabular-nums" />
                                        </td>
                                        <td class="px-1 py-1">
                                            <input v-model.number="c.practical_marks" type="number" step="0.5" min="0"
                                                   :max="c.practical_max"
                                                   :disabled="c.is_absent || !canEdit"
                                                   class="input input-bordered input-xs w-14 text-right tabular-nums" />
                                        </td>
                                    </template>
                                    <td class="px-3 py-1 text-right tabular-nums font-bold text-primary border-l border-base-200">
                                        <span v-if="rowPercent(r.cells) !== null">{{ rowPercent(r.cells) }}%</span>
                                        <span v-else class="text-base-content/30">—</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sticky save bar (also visible on mobile) -->
            <div class="sticky bottom-0 -mx-2 sm:mx-0 rounded-t-2xl sm:rounded-2xl bg-base-100
                        border-t border-base-300 sm:border shadow-lg p-3 flex items-center gap-3">
                <p class="text-xs text-base-content/70 flex-1">
                    <b>{{ enteredCount }}</b> of <b>{{ form.rows.length }}</b> students have data.
                    Empty rows are safely skipped.
                </p>
                <button type="button" @click="submit" :disabled="form.processing || !canEdit"
                        class="btn btn-primary btn-sm rounded-xl gap-1.5">
                    <span v-if="form.processing" class="loading loading-spinner loading-xs"></span>
                    <DocumentCheckIcon v-else class="w-4 h-4" />
                    Save all
                </button>
            </div>

            <div v-if="Object.keys(form.errors).length"
                 class="rounded-xl border border-error/30 bg-error/10 p-3 text-xs">
                <p class="font-bold text-error mb-1">Please fix:</p>
                <ul class="list-disc pl-4 space-y-0.5">
                    <li v-for="(msg, key) in form.errors" :key="key">{{ msg }}</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
