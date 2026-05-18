<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import TimetableSubnav from '@/Components/timetable/TimetableSubnav.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { ref, reactive, computed } from 'vue'
import {
    PencilSquareIcon, EyeIcon, CheckIcon, XCircleIcon,
    BoltIcon, LockClosedIcon, LockOpenIcon, ClockIcon, TrashIcon,
    InformationCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    section: Object,
    slots: { type: Array, default: () => [] },
    entries: { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    teachers: { type: Array, default: () => [] },
})

const ALL_DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']

function isPeriod(slot) {
    return slot.type === 'period'
}
const periodSlots = computed(() => props.slots.filter(isPeriod))

// ─── Pickable "Subject — Teacher" options ───
// Built from the subject→teacher assignments. If an existing entry uses a
// pair that isn't in the assignment list (set manually before), keep it as
// an option so nothing silently disappears.
const optionList = computed(() => {
    const map = new Map()
    for (const a of props.assignments) {
        map.set(`${a.subject_id}:${a.teacher_id}`, {
            key: `${a.subject_id}:${a.teacher_id}`,
            subject_id: a.subject_id,
            teacher_id: a.teacher_id,
            label: `${a.subject_name}${a.subject_code ? ' (' + a.subject_code + ')' : ''} — ${a.teacher_name}`,
        })
    }
    for (const e of props.entries) {
        if (!e.subject_id || !e.teacher_id) continue
        const key = `${e.subject_id}:${e.teacher_id}`
        if (!map.has(key)) {
            map.set(key, {
                key,
                subject_id: e.subject_id,
                teacher_id: e.teacher_id,
                label: `${e.subject_name || 'Subject'} — ${e.teacher_name || 'Teacher'} (manual)`,
            })
        }
    }
    return [...map.values()]
})

// ─── routine: one choice per period slot (same every day) ───
const routine = reactive({})
for (const slot of props.slots) {
    if (!isPeriod(slot)) continue
    const hit = props.entries.find(e => e.time_slot_id === slot.id && e.subject_id && e.teacher_id)
    routine[slot.id] = hit ? `${hit.subject_id}:${hit.teacher_id}` : ''
}

function clearSlot(slotId) {
    routine[slotId] = ''
}

const filledCount = computed(() =>
    periodSlots.value.filter(s => routine[s.id]).length
)

// ─── Auto-fill (whole section) ───
const generating = ref(false)
function autoFill() {
    if (!confirm('Auto-fill this section from its subject–teacher assignments? This replaces the current routine (you can fine-tune after).')) return
    router.post(route('timetable.generate'),
        { section_id: props.section.id, overwrite: true },
        {
            preserveScroll: true,
            onStart: () => { generating.value = true },
            onFinish: () => { generating.value = false },
        },
    )
}

// ─── Lock / unlock ───
function lockSection() {
    if (!confirm('Lock this section\'s timetable? Edits will be blocked until unlocked.')) return
    router.post(route('timetable.section.lock', props.section.id), {}, { preserveScroll: true })
}
function unlockSection() {
    if (!confirm('Unlock to allow edits?')) return
    router.post(route('timetable.section.unlock', props.section.id), {}, { preserveScroll: true })
}

// ─── Save: expand each period slot to every weekday it runs ───
const form = useForm({ entries: [] })

function save() {
    const out = []
    for (const slot of props.slots) {
        if (!isPeriod(slot)) continue
        const val = routine[slot.id]
        if (!val) continue
        const [subjectId, teacherId] = val.split(':').map(Number)
        const days = (slot.weekdays && slot.weekdays.length) ? slot.weekdays : ALL_DAYS
        for (const day of days) {
            out.push({
                weekday: day,
                time_slot_id: slot.id,
                subject_id: subjectId,
                teacher_id: teacherId,
                room: null,
            })
        }
    }
    form.entries = out
    form.post(route('timetable.builder.save', props.section.id), { preserveScroll: true })
}
</script>

<template>
    <Head :title="`Build · ${section.class_name} ${section.name}`" />
    <AppLayout :breadcrumbs="[
        { label: 'Timetable', href: route('timetable.index') },
        { label: `${section.class_name} · ${section.name}` },
    ]">
        <div class="space-y-3 max-w-3xl mx-auto">

            <PageHeader title="Daily routine"
                :subtitle="`${section.class_name} · Section ${section.name} · ${section.school_name}`"
                :icon="PencilSquareIcon" tone="violet">
                <template #actions>
                    <button v-if="!section.timetable_locked && assignments.length" @click="autoFill" :disabled="generating"
                        class="btn btn-sm rounded-lg gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white border-0 disabled:opacity-60">
                        <BoltIcon class="w-4 h-4" /> {{ generating ? 'Filling…' : 'Auto-fill' }}
                    </button>
                    <button v-if="!section.timetable_locked" @click="lockSection"
                        class="btn btn-ghost btn-sm rounded-lg gap-1.5" title="Lock to prevent edits">
                        <LockClosedIcon class="w-4 h-4" /> Lock
                    </button>
                    <button v-else @click="unlockSection" class="btn btn-warning btn-sm rounded-lg gap-1.5">
                        <LockOpenIcon class="w-4 h-4" /> Unlock
                    </button>
                    <Link :href="route('timetable.section', section.id)" class="btn btn-ghost btn-sm rounded-lg gap-1.5">
                        <EyeIcon class="w-4 h-4" /> View
                    </Link>
                    <button @click="save" :disabled="form.processing || section.timetable_locked"
                        class="btn btn-primary btn-sm rounded-lg gap-2">
                        <CheckIcon class="w-4 h-4" />
                        {{ form.processing ? 'Saving…' : 'Save routine' }}
                    </button>
                </template>
            </PageHeader>

            <TimetableSubnav />

            <!-- Explainer -->
            <div class="rounded-xl border border-violet-500/25 bg-violet-500/5 px-4 py-3 flex items-start gap-2.5 text-sm">
                <InformationCircleIcon class="w-5 h-5 text-violet-600 dark:text-violet-400 shrink-0 mt-0.5" />
                <p class="text-base-content/75">
                    Pick one <strong>subject &amp; teacher</strong> per period. This routine runs the
                    <strong>same every day</strong> (Mon–Sat) — Period 1 stays Period 1 all week.
                </p>
            </div>

            <!-- Lock banner -->
            <div v-if="section.timetable_locked"
                class="rounded-xl border-2 border-amber-500/40 bg-amber-500/10 p-3 flex items-start gap-2.5">
                <LockClosedIcon class="w-5 h-5 text-amber-700 dark:text-amber-300 mt-0.5 shrink-0" />
                <p class="text-xs text-amber-800 dark:text-amber-200">
                    <strong>Timetable locked</strong>
                    <span v-if="section.timetable_locked_at"> on {{ section.timetable_locked_at }}</span>
                    <span v-if="section.timetable_locked_by_name"> by {{ section.timetable_locked_by_name }}</span>.
                    Unlock above to edit.
                </p>
            </div>

            <!-- No assignments yet -->
            <div v-if="!assignments.length"
                class="rounded-xl border-2 border-amber-500/30 bg-amber-500/10 p-4 flex items-start gap-3">
                <XCircleIcon class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" />
                <div class="text-sm">
                    <p class="font-bold mb-1">No subject–teacher assignments for this section</p>
                    <p class="text-xs text-base-content/70">
                        Assign subjects to teachers for <strong>{{ section.class_name }} · {{ section.name }}</strong> in
                        <Link href="/teacher-assignments" class="text-violet-600 underline">Teacher Assignments</Link>,
                        then come back and Auto-fill.
                    </p>
                </div>
            </div>

            <!-- Coverage -->
            <div v-if="assignments.length" class="rounded-xl border border-base-300 bg-base-100 px-4 py-3 flex items-center gap-3">
                <div class="flex-1">
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="font-bold">Filled periods</span>
                        <span class="text-base-content/55 tabular-nums">{{ filledCount }} / {{ periodSlots.length }}</span>
                    </div>
                    <div class="h-1.5 bg-base-200 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full transition-all"
                            :style="{ width: (periodSlots.length ? (filledCount / periodSlots.length * 100) : 0) + '%' }"></div>
                    </div>
                </div>
            </div>

            <p v-if="form.errors.entries" class="rounded-lg bg-error/10 text-error text-xs px-3 py-2">
                {{ form.errors.entries }}
            </p>

            <!-- Routine list -->
            <div v-if="assignments.length" class="rounded-2xl border border-base-300 bg-base-100 overflow-hidden divide-y divide-base-300">
                <div v-for="slot in slots" :key="slot.id"
                    class="flex items-center gap-3 px-4 py-3"
                    :class="!isPeriod(slot) ? 'bg-amber-500/5' : ''">

                    <!-- Period / time -->
                    <div class="w-32 shrink-0">
                        <p class="font-bold text-sm">{{ slot.name }}</p>
                        <p class="text-[11px] text-base-content/55 font-mono flex items-center gap-1">
                            <ClockIcon class="w-3 h-3" />
                            {{ slot.starts_at?.slice(0,5) }}–{{ slot.ends_at?.slice(0,5) }}
                        </p>
                    </div>

                    <!-- Break / lunch -->
                    <div v-if="!isPeriod(slot)"
                        class="flex-1 text-center text-[11px] uppercase tracking-wider font-bold text-amber-700 dark:text-amber-300">
                        {{ slot.type }}
                    </div>

                    <!-- Period picker -->
                    <template v-else>
                        <select v-model="routine[slot.id]"
                            :disabled="section.timetable_locked"
                            class="select select-bordered select-sm rounded-lg flex-1 text-sm disabled:opacity-60"
                            :class="routine[slot.id] ? 'border-emerald-500/40 bg-emerald-500/5' : ''">
                            <option value="">— Free period —</option>
                            <option v-for="o in optionList" :key="o.key" :value="o.key">{{ o.label }}</option>
                        </select>
                        <button v-if="routine[slot.id]" type="button"
                            @click="clearSlot(slot.id)"
                            :disabled="section.timetable_locked"
                            class="btn btn-ghost btn-sm btn-square rounded-lg text-base-content/50 hover:text-rose-600"
                            title="Clear">
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </template>
                </div>
            </div>

            <p v-if="assignments.length" class="text-xs text-base-content/55 text-center pb-4">
                "Free period" = no class. The server blocks a teacher being placed in the same
                period in two different sections.
            </p>
        </div>
    </AppLayout>
</template>
