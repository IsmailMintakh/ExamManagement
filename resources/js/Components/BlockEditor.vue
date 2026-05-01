<script setup>
/**
 * Block-type-aware editor.
 *
 * Renders the appropriate form fields based on `type`. Emits `update:data`
 * whenever the underlying data changes. Used by Pages/Manage.vue both for
 * editing existing blocks and for the "new block" wizard.
 */
import { computed } from 'vue'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    type: { type: String, required: true },
    data: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:data'])

// Shallow proxy: emit on every mutation so v-model in children stays reactive
const d = computed({
    get: () => props.data || {},
    set: (val) => emit('update:data', val),
})

function set(key, val) {
    d.value = { ...d.value, [key]: val }
}

// Generic helpers for the array-typed blocks (timeline, stats, features)
function items(key, fallback = []) {
    return d.value[key] || fallback
}
function setItems(key, list) {
    set(key, list)
}
function addItem(key, defaults = {}) {
    setItems(key, [...items(key), { ...defaults }])
}
function removeItem(key, index) {
    const next = [...items(key)]
    next.splice(index, 1)
    setItems(key, next)
}
function updateItem(key, index, patch) {
    const next = [...items(key)]
    next[index] = { ...next[index], ...patch }
    setItems(key, next)
}

// Icon picker — short curated list mapped to heroicons names rendered server-side
const iconChoices = [
    'AcademicCapIcon', 'BeakerIcon', 'BookOpenIcon', 'BuildingLibraryIcon',
    'CheckCircleIcon', 'ComputerDesktopIcon', 'EyeIcon', 'FlagIcon',
    'GlobeAltIcon', 'HeartIcon', 'LightBulbIcon', 'MapPinIcon',
    'ShieldCheckIcon', 'SparklesIcon', 'StarIcon', 'TrophyIcon',
    'UserGroupIcon', 'UsersIcon',
]
</script>

<template>
    <!-- ════════ rich_text ════════ -->
    <div v-if="type === 'rich_text'" class="space-y-4">
        <div>
            <label class="text-[12px] font-semibold text-base-content/75 mb-1.5 block">Eyebrow (small uppercase tag)</label>
            <input :value="d.eyebrow" @input="set('eyebrow', $event.target.value)"
                placeholder="Our Story" class="input input-bordered input-sm w-full text-sm" />
        </div>
        <div>
            <label class="text-[12px] font-semibold text-base-content/75 mb-1.5 block">Heading</label>
            <input :value="d.heading" @input="set('heading', $event.target.value)"
                placeholder="A school built on trust" class="input input-bordered w-full text-sm" />
        </div>
        <div>
            <label class="text-[12px] font-semibold text-base-content/75 mb-1.5 block">Body</label>
            <textarea :value="d.body" @input="set('body', $event.target.value)" rows="6"
                placeholder="Plain paragraphs separated by blank lines."
                class="textarea textarea-bordered w-full text-sm"></textarea>
            <p class="text-[11px] text-base-content/45 mt-1">Plain text with paragraph breaks. Markdown bold (**word**) supported.</p>
        </div>
    </div>

    <!-- ════════ mission_vision ════════ -->
    <div v-else-if="type === 'mission_vision'" class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="space-y-3 p-4 rounded-xl bg-emerald-50/50 border border-emerald-100">
            <h4 class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Our Mission</h4>
            <input :value="d.mission_title" @input="set('mission_title', $event.target.value)"
                placeholder="Our Mission" class="input input-bordered input-sm w-full text-sm" />
            <textarea :value="d.mission_body" @input="set('mission_body', $event.target.value)" rows="5"
                placeholder="What we strive to do every day."
                class="textarea textarea-bordered w-full text-sm"></textarea>
        </div>
        <div class="space-y-3 p-4 rounded-xl bg-amber-50/50 border border-amber-100">
            <h4 class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Our Vision</h4>
            <input :value="d.vision_title" @input="set('vision_title', $event.target.value)"
                placeholder="Our Vision" class="input input-bordered input-sm w-full text-sm" />
            <textarea :value="d.vision_body" @input="set('vision_body', $event.target.value)" rows="5"
                placeholder="The future we're building toward."
                class="textarea textarea-bordered w-full text-sm"></textarea>
        </div>
    </div>

    <!-- ════════ feature_grid ════════ -->
    <div v-else-if="type === 'feature_grid'" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <input :value="d.eyebrow" @input="set('eyebrow', $event.target.value)"
                placeholder="Eyebrow (e.g. Our Values)" class="input input-bordered input-sm w-full text-sm" />
            <input :value="d.heading" @input="set('heading', $event.target.value)"
                placeholder="Section heading" class="input input-bordered input-sm w-full text-sm" />
        </div>
        <div class="space-y-2">
            <label class="text-[12px] font-semibold text-base-content/75">Feature Cards</label>
            <div v-for="(item, i) in items('items')" :key="i"
                class="grid grid-cols-12 gap-2 p-3 rounded-lg bg-base-200/40">
                <select :value="item.icon"
                    @change="updateItem('items', i, { icon: $event.target.value })"
                    class="col-span-3 select select-bordered select-sm text-xs">
                    <option v-for="ic in iconChoices" :key="ic" :value="ic">{{ ic.replace('Icon', '') }}</option>
                </select>
                <input :value="item.title"
                    @input="updateItem('items', i, { title: $event.target.value })"
                    placeholder="Title" class="col-span-4 input input-bordered input-sm text-sm" />
                <input :value="item.desc"
                    @input="updateItem('items', i, { desc: $event.target.value })"
                    placeholder="One-line description" class="col-span-4 input input-bordered input-sm text-sm" />
                <button type="button" @click="removeItem('items', i)" class="col-span-1 btn btn-ghost btn-sm btn-square text-error">
                    <TrashIcon class="w-4 h-4" />
                </button>
            </div>
            <button type="button" @click="addItem('items', { icon: 'StarIcon', title: '', desc: '' })"
                class="btn btn-ghost btn-sm gap-1 text-emerald-700">
                <PlusIcon class="w-4 h-4" /> Add Feature
            </button>
        </div>
    </div>

    <!-- ════════ stats_strip ════════ -->
    <div v-else-if="type === 'stats_strip'" class="space-y-4">
        <input :value="d.heading" @input="set('heading', $event.target.value)"
            placeholder="Optional heading" class="input input-bordered input-sm w-full text-sm" />
        <div class="space-y-2">
            <label class="text-[12px] font-semibold text-base-content/75">Stats</label>
            <div v-for="(item, i) in items('items')" :key="i"
                class="grid grid-cols-12 gap-2 p-3 rounded-lg bg-base-200/40">
                <select :value="item.icon"
                    @change="updateItem('items', i, { icon: $event.target.value })"
                    class="col-span-3 select select-bordered select-sm text-xs">
                    <option v-for="ic in iconChoices" :key="ic" :value="ic">{{ ic.replace('Icon', '') }}</option>
                </select>
                <input :value="item.label"
                    @input="updateItem('items', i, { label: $event.target.value })"
                    placeholder="Label" class="col-span-4 input input-bordered input-sm text-sm" />
                <input :value="item.value"
                    @input="updateItem('items', i, { value: $event.target.value })"
                    placeholder="Value (e.g. 1248)" class="col-span-3 input input-bordered input-sm text-sm" />
                <input :value="item.suffix"
                    @input="updateItem('items', i, { suffix: $event.target.value })"
                    placeholder="Suffix (e.g. %)" class="col-span-1 input input-bordered input-sm text-sm" />
                <button type="button" @click="removeItem('items', i)" class="col-span-1 btn btn-ghost btn-sm btn-square text-error">
                    <TrashIcon class="w-4 h-4" />
                </button>
            </div>
            <button type="button" @click="addItem('items', { icon: 'UserGroupIcon', label: '', value: '', suffix: '' })"
                class="btn btn-ghost btn-sm gap-1 text-emerald-700">
                <PlusIcon class="w-4 h-4" /> Add Stat
            </button>
        </div>
    </div>

    <!-- ════════ timeline ════════ -->
    <div v-else-if="type === 'timeline'" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <input :value="d.eyebrow" @input="set('eyebrow', $event.target.value)"
                placeholder="Eyebrow" class="input input-bordered input-sm w-full text-sm" />
            <input :value="d.heading" @input="set('heading', $event.target.value)"
                placeholder="Section heading" class="input input-bordered input-sm w-full text-sm" />
        </div>
        <div class="space-y-2">
            <label class="text-[12px] font-semibold text-base-content/75">Milestones</label>
            <div v-for="(item, i) in items('items')" :key="i" class="space-y-2 p-3 rounded-lg bg-base-200/40">
                <div class="flex gap-2">
                    <input :value="item.year"
                        @input="updateItem('items', i, { year: $event.target.value })"
                        placeholder="Year (e.g. 1954)" class="input input-bordered input-sm w-32 text-sm" />
                    <input :value="item.title"
                        @input="updateItem('items', i, { title: $event.target.value })"
                        placeholder="Title" class="input input-bordered input-sm flex-1 text-sm" />
                    <button type="button" @click="removeItem('items', i)" class="btn btn-ghost btn-sm btn-square text-error">
                        <TrashIcon class="w-4 h-4" />
                    </button>
                </div>
                <textarea :value="item.desc"
                    @input="updateItem('items', i, { desc: $event.target.value })"
                    rows="2" placeholder="Description"
                    class="textarea textarea-bordered w-full text-sm"></textarea>
            </div>
            <button type="button" @click="addItem('items', { year: '', title: '', desc: '' })"
                class="btn btn-ghost btn-sm gap-1 text-emerald-700">
                <PlusIcon class="w-4 h-4" /> Add Milestone
            </button>
        </div>
    </div>

    <!-- ════════ image_text ════════ -->
    <div v-else-if="type === 'image_text'" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <input :value="d.heading" @input="set('heading', $event.target.value)"
                placeholder="Heading" class="input input-bordered w-full text-sm" />
            <select :value="d.layout || 'image-left'"
                @change="set('layout', $event.target.value)"
                class="select select-bordered text-sm">
                <option value="image-left">Image on left</option>
                <option value="image-right">Image on right</option>
            </select>
        </div>
        <textarea :value="d.body" @input="set('body', $event.target.value)" rows="5"
            placeholder="Description / paragraphs"
            class="textarea textarea-bordered w-full text-sm"></textarea>
        <input :value="d.image_url" @input="set('image_url', $event.target.value)"
            placeholder="Image URL (uploaded files: /storage/...)"
            class="input input-bordered input-sm w-full text-sm" />
        <p class="text-[11px] text-base-content/45">Tip: paste a hosted URL, or upload an image elsewhere and copy its /storage/ path.</p>
    </div>

    <!-- ════════ testimonials ════════ -->
    <div v-else-if="type === 'testimonials'" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <input :value="d.eyebrow" @input="set('eyebrow', $event.target.value)"
                placeholder="Eyebrow (e.g. Voices of Our Community)" class="input input-bordered input-sm w-full text-sm" />
            <input :value="d.heading" @input="set('heading', $event.target.value)"
                placeholder="Section heading" class="input input-bordered input-sm w-full text-sm" />
        </div>
        <div class="space-y-2">
            <label class="text-[12px] font-semibold text-base-content/75">Testimonials</label>
            <div v-for="(item, i) in items('items')" :key="i" class="space-y-2 p-3 rounded-lg bg-base-200/40">
                <div class="flex gap-2">
                    <input :value="item.name"
                        @input="updateItem('items', i, { name: $event.target.value })"
                        placeholder="Name" class="input input-bordered input-sm flex-1 text-sm" />
                    <input :value="item.role"
                        @input="updateItem('items', i, { role: $event.target.value })"
                        placeholder="Role / Description" class="input input-bordered input-sm flex-1 text-sm" />
                    <button type="button" @click="removeItem('items', i)" class="btn btn-ghost btn-sm btn-square text-error">
                        <TrashIcon class="w-4 h-4" />
                    </button>
                </div>
                <textarea :value="item.quote"
                    @input="updateItem('items', i, { quote: $event.target.value })"
                    rows="3" placeholder="The quote / testimonial text"
                    class="textarea textarea-bordered w-full text-sm"></textarea>
            </div>
            <button type="button" @click="addItem('items', { name: '', role: '', quote: '' })"
                class="btn btn-ghost btn-sm gap-1 text-emerald-700">
                <PlusIcon class="w-4 h-4" /> Add Testimonial
            </button>
        </div>
    </div>

    <!-- ════════ toppers_table ════════ -->
    <div v-else-if="type === 'toppers_table'" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <input :value="d.eyebrow" @input="set('eyebrow', $event.target.value)"
                placeholder="Eyebrow (e.g. FBISE Matric Board 2026)" class="input input-bordered input-sm w-full text-sm" />
            <input :value="d.heading" @input="set('heading', $event.target.value)"
                placeholder="Section heading (e.g. Our Top Performers)" class="input input-bordered input-sm w-full text-sm" />
        </div>
        <div class="space-y-2">
            <label class="text-[12px] font-semibold text-base-content/75">Toppers</label>
            <div v-for="(item, i) in items('items')" :key="i"
                class="grid grid-cols-12 gap-2 p-3 rounded-lg bg-base-200/40 items-center">
                <input :value="item.rank"
                    @input="updateItem('items', i, { rank: $event.target.value })"
                    placeholder="#" class="col-span-1 input input-bordered input-sm text-sm text-center" />
                <input :value="item.name"
                    @input="updateItem('items', i, { name: $event.target.value })"
                    placeholder="Student name" class="col-span-4 input input-bordered input-sm text-sm" />
                <input :value="item.class"
                    @input="updateItem('items', i, { class: $event.target.value })"
                    placeholder="Class / Stream" class="col-span-3 input input-bordered input-sm text-sm" />
                <input :value="item.marks"
                    @input="updateItem('items', i, { marks: $event.target.value })"
                    placeholder="Marks (e.g. 1087/1100)" class="col-span-2 input input-bordered input-sm text-sm" />
                <input :value="item.percent"
                    @input="updateItem('items', i, { percent: $event.target.value })"
                    placeholder="%" class="col-span-1 input input-bordered input-sm text-sm" />
                <button type="button" @click="removeItem('items', i)" class="col-span-1 btn btn-ghost btn-sm btn-square text-error">
                    <TrashIcon class="w-4 h-4" />
                </button>
            </div>
            <button type="button" @click="addItem('items', { rank: '', name: '', class: '', marks: '', percent: '' })"
                class="btn btn-ghost btn-sm gap-1 text-emerald-700">
                <PlusIcon class="w-4 h-4" /> Add Topper
            </button>
        </div>
    </div>

    <!-- ════════ cta ════════ -->
    <div v-else-if="type === 'cta'" class="space-y-4">
        <input :value="d.eyebrow" @input="set('eyebrow', $event.target.value)"
            placeholder="Eyebrow (e.g. Admissions 2026–27)" class="input input-bordered input-sm w-full text-sm" />
        <input :value="d.heading" @input="set('heading', $event.target.value)"
            placeholder="Big heading" class="input input-bordered w-full text-sm" />
        <textarea :value="d.body" @input="set('body', $event.target.value)" rows="3"
            placeholder="Supporting text" class="textarea textarea-bordered w-full text-sm"></textarea>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <input :value="d.cta_label" @input="set('cta_label', $event.target.value)"
                placeholder="Button label" class="input input-bordered input-sm w-full text-sm" />
            <input :value="d.cta_url" @input="set('cta_url', $event.target.value)"
                placeholder="Button URL (e.g. /admissions)" class="input input-bordered input-sm w-full text-sm" />
        </div>
    </div>

    <div v-else class="text-sm text-base-content/55 italic p-4 bg-base-200/40 rounded-lg">
        Unknown block type: {{ type }}
    </div>
</template>
