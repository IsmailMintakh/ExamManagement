<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    Squares2X2Icon, ArrowDownTrayIcon, FunnelIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    classes: { type: Array, default: () => [] },
    exams: { type: Array, default: () => [] },
    selectedClassId: { type: [Number, String], default: null },
    selectedExamId: { type: [Number, String], default: null },
    students: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    cells: { type: Array, default: () => [] },
    averages: { type: Array, default: () => [] },
})

const classId = ref(props.selectedClassId || '')
const examId = ref(props.selectedExamId || '')

function apply() {
    router.get(route('insights.subject-heatmap'), {
        class_id: classId.value || undefined,
        exam_id: examId.value || undefined,
    }, { preserveState: false })
}

function cellClass(cat) {
    if (cat === 'excellent') return 'bg-green-700 text-white'
    if (cat === 'good') return 'bg-green-400 text-white'
    if (cat === 'average') return 'bg-yellow-300 text-yellow-900'
    if (cat === 'weak') return 'bg-orange-400 text-white'
    if (cat === 'fail') return 'bg-red-500 text-white'
    if (cat === 'absent') return 'bg-slate-300 text-slate-700'
    return 'bg-base-200 text-base-content/40'
}

function exportCsv() {
    if (!props.students.length || !props.subjects.length) return
    const header = ['Roll', 'Name', ...props.subjects.map((s) => s.name)].join(',')
    const rows = props.students.map((stu, sIdx) => {
        const cellsRow = props.cells[sIdx] || []
        const vals = cellsRow.map((c) => {
            if (c.category === 'absent') return 'Absent'
            if (c.category === 'no_data') return ''
            return Number(c.percentage).toFixed(2)
        })
        return [stu.roll_no || '', `"${(stu.name || '').replace(/"/g, '""')}"`, ...vals].join(',')
    })
    const avgRow = ['', 'Class Average', ...props.averages.map((a) => a.average !== null ? Number(a.average).toFixed(2) : '')].join(',')
    const csv = [header, ...rows, avgRow].join('\n')
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `subject-heatmap-${classId.value}-${examId.value}.csv`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
}

const hasMatrix = computed(() => props.students.length > 0 && props.subjects.length > 0)
</script>

<template>
    <Head title="Subject Heatmap" />
    <AppLayout :breadcrumbs="[{ label: 'Insights' }, { label: 'Subject Heatmap' }]">
        <div class="space-y-6">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Subject Heatmap</h1>
                    <p class="page-subtitle">Student × Subject performance matrix</p>
                </div>
                <button v-if="hasMatrix" class="btn btn-ghost btn-sm" @click="exportCsv">
                    <ArrowDownTrayIcon class="h-4 w-4" />
                    Export CSV
                </button>
            </div>

            <!-- Selector -->
            <div class="card-section">
                <div class="card-content space-y-4">
                    <div class="flex items-center gap-3 border-b border-base-200 pb-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <FunnelIcon class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold">Filters</h3>
                            <p class="text-xs text-base-content/50">Choose class and exam</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <select v-model="classId" class="select select-bordered w-full">
                            <option value="">Select Class</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">
                                {{ c.name }}<span v-if="c.school_name"> — {{ c.school_name }}</span>
                            </option>
                        </select>
                        <select v-model="examId" class="select select-bordered w-full">
                            <option value="">Select Exam</option>
                            <option v-for="e in exams" :key="e.id" :value="e.id">
                                {{ e.name }}<span v-if="e.start_date"> ({{ e.start_date }})</span>
                            </option>
                        </select>
                        <button class="btn btn-primary" :disabled="!classId || !examId" @click="apply">
                            Apply
                        </button>
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div v-if="hasMatrix" class="card-section">
                <div class="card-content">
                    <p class="mb-3 text-2xs font-bold uppercase tracking-wider text-base-content/40">Legend</p>
                    <div class="flex flex-wrap gap-3">
                        <div class="flex items-center gap-2"><div class="h-5 w-5 rounded bg-green-700" /><span class="text-xs">Excellent (80+)</span></div>
                        <div class="flex items-center gap-2"><div class="h-5 w-5 rounded bg-green-400" /><span class="text-xs">Good (60–79)</span></div>
                        <div class="flex items-center gap-2"><div class="h-5 w-5 rounded bg-yellow-300" /><span class="text-xs">Average (40–59)</span></div>
                        <div class="flex items-center gap-2"><div class="h-5 w-5 rounded bg-orange-400" /><span class="text-xs">Weak (33–39)</span></div>
                        <div class="flex items-center gap-2"><div class="h-5 w-5 rounded bg-red-500" /><span class="text-xs">Fail (&lt;33)</span></div>
                        <div class="flex items-center gap-2"><div class="h-5 w-5 rounded bg-slate-300" /><span class="text-xs">Absent</span></div>
                    </div>
                </div>
            </div>

            <!-- Matrix -->
            <div v-if="hasMatrix" class="card-section">
                <div class="card-content">
                    <div class="overflow-x-auto">
                        <table class="w-full border-separate border-spacing-0 text-xs">
                            <thead>
                                <tr>
                                    <th class="sticky left-0 z-10 border-b border-base-200 bg-base-100 px-3 py-2 text-left font-semibold">
                                        Student
                                    </th>
                                    <th
                                        v-for="s in subjects"
                                        :key="s.id"
                                        class="border-b border-base-200 px-2 py-2 text-center font-semibold"
                                    >
                                        <div class="truncate" :title="s.name">{{ s.code || s.name }}</div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(stu, sIdx) in students" :key="stu.id">
                                    <td class="sticky left-0 z-10 border-b border-base-100 bg-base-100 px-3 py-2">
                                        <div class="text-xs font-semibold">{{ stu.name }}</div>
                                        <div class="text-2xs text-base-content/50">
                                            <span v-if="stu.roll_no">#{{ stu.roll_no }}</span>
                                            <span v-if="stu.admission_no"> &middot; {{ stu.admission_no }}</span>
                                        </div>
                                    </td>
                                    <td
                                        v-for="(cell, cIdx) in cells[sIdx]"
                                        :key="cIdx"
                                        class="border-b border-base-100 px-1 py-1 text-center"
                                    >
                                        <div
                                            class="inline-flex h-8 min-w-[54px] items-center justify-center rounded-md text-2xs font-bold"
                                            :class="cellClass(cell.category)"
                                            :title="cell.category === 'absent' ? 'Absent' : (cell.percentage !== null ? `${cell.percentage}%` : 'No data')"
                                        >
                                            <span v-if="cell.category === 'absent'">A</span>
                                            <span v-else-if="cell.percentage !== null">{{ Number(cell.percentage).toFixed(0) }}</span>
                                            <span v-else>—</span>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Averages -->
                                <tr>
                                    <td class="sticky left-0 z-10 border-t-2 border-base-300 bg-base-200 px-3 py-2">
                                        <div class="text-xs font-bold">Class Average</div>
                                    </td>
                                    <td
                                        v-for="(avg, idx) in averages"
                                        :key="idx"
                                        class="border-t-2 border-base-300 bg-base-200 px-1 py-1 text-center"
                                    >
                                        <div
                                            class="inline-flex h-8 min-w-[54px] items-center justify-center rounded-md text-2xs font-bold"
                                            :class="cellClass(avg.category)"
                                        >
                                            <span v-if="avg.average !== null">{{ Number(avg.average).toFixed(1) }}</span>
                                            <span v-else>—</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div v-else class="card-section">
                <div class="card-content py-16 text-center">
                    <Squares2X2Icon class="mx-auto h-12 w-12 text-base-content/30" />
                    <p class="mt-4 text-sm text-base-content/50">Select a class and exam to view the heatmap.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
