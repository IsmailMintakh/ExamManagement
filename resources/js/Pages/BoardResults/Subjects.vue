<script setup>
/**
 * Per-subject template for a board exam.
 * Set theory_max / practical_max / passing % ONCE per subject; every
 * student's entry form + batch grid will start with these values.
 * Un-tick a subject to remove it from the exam entirely.
 */
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
    AcademicCapIcon, ArrowLeftIcon, DocumentCheckIcon,
    TableCellsIcon, InformationCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    exam: { type: Object, required: true },
    rows: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
})

const form = useForm({
    rows: props.rows.map(r => ({ ...r })),
})

function toggleAll(on) {
    for (const r of form.rows) r.included = on
}
function totalMax(r) {
    return (Number(r.theory_max) || 0) + (Number(r.practical_max) || 0)
}
const includedCount = computed(() => form.rows.filter(r => r.included).length)
const grandTotal = computed(() =>
    form.rows.filter(r => r.included).reduce((s, r) => s + totalMax(r), 0)
)

function submit() {
    form.post(route('board-results.subjects-update', props.exam.id))
}
</script>

<template>
    <Head :title="`Subjects · ${exam.title}`" />
    <AppLayout :breadcrumbs="[
        { label: 'Board Results', href: route('board-results.index') },
        { label: exam.title, href: route('board-results.show', exam.id) },
        { label: 'Subjects' }
    ]">
        <div class="space-y-4 max-w-5xl mx-auto">
            <PageHeader :title="`Subjects — ${exam.title}`"
                        :subtitle="`${exam.school_class?.name} · ${exam.school?.name} · set theory / practical / passing per subject`"
                        :icon="TableCellsIcon" tone="violet">
                <template #actions>
                    <Link :href="route('board-results.show', exam.id)" class="btn btn-ghost btn-sm rounded-xl gap-1.5">
                        <ArrowLeftIcon class="w-4 h-4" /> Back
                    </Link>
                    <button type="button" @click="submit" :disabled="form.processing || !canEdit"
                            class="btn btn-primary btn-sm rounded-xl gap-1.5">
                        <span v-if="form.processing" class="loading loading-spinner loading-xs"></span>
                        <DocumentCheckIcon v-else class="w-4 h-4" />
                        Save
                    </button>
                </template>
            </PageHeader>

            <!-- Info banner -->
            <div class="rounded-xl border border-info/25 bg-info/5 p-3 flex items-start gap-3">
                <InformationCircleIcon class="w-5 h-5 text-info shrink-0 mt-0.5" />
                <div class="text-xs text-base-content/70">
                    These values are the <b>template</b> — every student's per-subject cells start with these numbers.
                    A teacher can still override any single cell during entry. Blank "Passing %" means the exam-level
                    <b>{{ exam.pass_percentage }}%</b> applies.
                </div>
            </div>

            <!-- Summary strip -->
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-xl bg-base-100 border border-base-300/70 p-3">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Subjects</p>
                    <p class="text-lg font-extrabold tabular-nums">{{ includedCount }}</p>
                </div>
                <div class="rounded-xl bg-base-100 border border-base-300/70 p-3">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Total Max</p>
                    <p class="text-lg font-extrabold tabular-nums">{{ grandTotal }}</p>
                </div>
                <div class="rounded-xl bg-base-100 border border-base-300/70 p-3">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-base-content/55">Exam-level Pass %</p>
                    <p class="text-lg font-extrabold tabular-nums">{{ exam.pass_percentage }}%</p>
                </div>
            </div>

            <!-- Table -->
            <form @submit.prevent="submit" class="rounded-2xl bg-base-100 border border-base-300/70 shadow-sm overflow-hidden">
                <div class="px-4 py-2.5 border-b border-base-200 flex items-center justify-between">
                    <p class="text-xs text-base-content/60">
                        <b class="text-base-content">{{ includedCount }}</b> included · <b>{{ form.rows.length - includedCount }}</b> excluded
                    </p>
                    <div class="flex items-center gap-1.5">
                        <button type="button" @click="toggleAll(true)"  class="btn btn-ghost btn-xs">All</button>
                        <button type="button" @click="toggleAll(false)" class="btn btn-ghost btn-xs">None</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-base-200/40 text-[10px] uppercase tracking-wider font-bold text-base-content/55">
                            <tr>
                                <th class="w-12 text-center px-3 py-2">Use</th>
                                <th class="text-left px-2 py-2">Subject</th>
                                <th class="text-right px-2 py-2 w-24">Theory Max</th>
                                <th class="text-right px-2 py-2 w-24">Practical Max</th>
                                <th class="text-right px-2 py-2 w-16">Total</th>
                                <th class="text-right px-2 py-2 w-32">
                                    Passing %
                                    <span class="text-base-content/40 normal-case font-normal">(blank = exam-level)</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-200">
                            <tr v-for="r in form.rows" :key="r.subject_id"
                                :class="!r.included ? 'opacity-40' : ''">
                                <td class="text-center">
                                    <input type="checkbox" v-model="r.included" class="checkbox checkbox-sm checkbox-primary" />
                                </td>
                                <td class="px-2 py-2">
                                    <p class="font-semibold">{{ r.name }}</p>
                                    <p v-if="r.code" class="text-[10px] text-base-content/50">{{ r.code }}</p>
                                </td>
                                <td class="px-2 py-2">
                                    <input v-model.number="r.theory_max" type="number" min="0" max="200" step="1"
                                           :disabled="!r.included || !canEdit"
                                           class="input input-bordered input-sm w-20 text-right tabular-nums" />
                                </td>
                                <td class="px-2 py-2">
                                    <input v-model.number="r.practical_max" type="number" min="0" max="200" step="1"
                                           :disabled="!r.included || !canEdit"
                                           class="input input-bordered input-sm w-20 text-right tabular-nums" />
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums font-bold">
                                    {{ totalMax(r) }}
                                </td>
                                <td class="px-2 py-2">
                                    <input v-model.number="r.pass_percentage" type="number" min="0" max="100" step="1"
                                           :disabled="!r.included || !canEdit"
                                           placeholder="—"
                                           class="input input-bordered input-sm w-20 text-right tabular-nums" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </form>

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
