<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import BlockEditor from '@/Components/BlockEditor.vue'
import Modal from '@/Components/Modal.vue'
import FormInput from '@/Components/FormInput.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import {
    ArrowLeftIcon, PlusIcon, Bars3Icon, PencilSquareIcon,
    TrashIcon, EyeIcon, EyeSlashIcon, Squares2X2Icon, EyeIcon as EyePreview,
    SparklesIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    page:   { type: Object, required: true },
    hero:   { type: Object, default: () => ({}) },
    blocks: { type: Array,  default: () => [] },
    types:  { type: Object, default: () => ({}) },
    styles: { type: Object, default: () => ({}) },
})

// ────── Hero editor ──────
const heroForm = useForm({
    hero_eyebrow:      props.hero?.hero_eyebrow || '',
    hero_title:        props.hero?.hero_title || '',
    hero_title_accent: props.hero?.hero_title_accent || '',
    hero_subtitle:     props.hero?.hero_subtitle || '',
    hero_style:        props.hero?.hero_style || 'emerald-night',
    meta_title:        props.hero?.meta_title || '',
    meta_description:  props.hero?.meta_description || '',
})
function saveHero() {
    heroForm.put(route('website.pages.hero.update', props.page.key), { preserveScroll: true })
}

// ────── Block list (only for pages with blocks=true) ──────
const items = ref([...props.blocks])
watch(() => props.blocks, (b) => { items.value = [...b] })

const dragIndex = ref(null)
function onDragStart(i) { dragIndex.value = i }
function onDragOver(e) { e.preventDefault() }
function onDrop(targetIdx) {
    if (dragIndex.value === null || dragIndex.value === targetIdx) return
    const [moved] = items.value.splice(dragIndex.value, 1)
    items.value.splice(targetIdx, 0, moved)
    dragIndex.value = null
    router.post(route('website.pages.reorder', props.page.key),
        { order: items.value.map(b => b.id) },
        { preserveScroll: true, preserveState: true })
}

const addModalOpen = ref(false)
const newType = ref(null)
const newData = ref({})
function startAdd(type) {
    newType.value = type
    newData.value = {}
    addModalOpen.value = true
}
function saveNew() {
    router.post(route('website.page-blocks.store'), {
        page_key: props.page.key,
        type:     newType.value,
        data:     newData.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { addModalOpen.value = false; newType.value = null; newData.value = {} },
    })
}

const editing = ref(null)
const editingData = ref({})
function startEdit(block) {
    editing.value = block
    editingData.value = JSON.parse(JSON.stringify(block.data || {}))
}
function saveEdit() {
    router.put(route('website.page-blocks.update', editing.value.id),
        { data: editingData.value },
        { preserveScroll: true, onSuccess: () => { editing.value = null } })
}

function toggleActive(block) {
    router.put(route('website.page-blocks.update', block.id),
        { is_active: !block.is_active },
        { preserveScroll: true, preserveState: true })
}

function destroy(block) {
    if (!confirm('Remove this block? This cannot be undone.')) return
    router.delete(route('website.page-blocks.destroy', block.id), { preserveScroll: true })
}

function summary(block) {
    const d = block.data || {}
    if (block.type === 'rich_text')      return d.heading || d.eyebrow || (d.body ? d.body.slice(0, 90) + '…' : 'Rich text')
    if (block.type === 'mission_vision') return `Mission: ${d.mission_title || '—'} / Vision: ${d.vision_title || '—'}`
    if (block.type === 'feature_grid')   return `${d.heading || 'Features'} (${(d.items || []).length} cards)`
    if (block.type === 'stats_strip')    return `${(d.items || []).length} stats`
    if (block.type === 'timeline')       return `${d.heading || 'Timeline'} (${(d.items || []).length} milestones)`
    if (block.type === 'image_text')     return d.heading || 'Image + Text'
    if (block.type === 'testimonials')   return `${d.heading || 'Testimonials'} (${(d.items || []).length} quotes)`
    if (block.type === 'toppers_table')  return `${d.heading || 'Toppers'} (${(d.items || []).length} entries)`
    if (block.type === 'cta')            return d.heading || 'Call to Action'
    return block.type
}
</script>

<template>
    <Head :title="`Manage — ${page.label}`" />
    <AppLayout :breadcrumbs="[
        { label: 'Website' },
        { label: 'Pages Content', href: route('website.pages.index') },
        { label: page.label },
    ]">
        <div class="max-w-5xl mx-auto space-y-6">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-3">
                    <Link :href="route('website.pages.index')" class="btn btn-ghost btn-sm btn-square">
                        <ArrowLeftIcon class="w-4 h-4" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold">{{ page.label }}</h1>
                        <p class="text-xs text-base-content/55 mt-1 font-mono">{{ page.route }}</p>
                    </div>
                </div>
                <a :href="page.route" target="_blank" rel="noopener"
                    class="btn btn-ghost btn-sm gap-1.5">
                    <EyePreview class="w-4 h-4" /> Preview Live
                </a>
            </div>

            <!-- ════════ HERO EDITOR ════════ -->
            <form @submit.prevent="saveHero" class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body space-y-5">
                    <div class="flex items-center gap-2">
                        <SparklesIcon class="w-5 h-5 text-amber-500" />
                        <h2 class="text-base font-bold">Page Hero</h2>
                    </div>
                    <p class="text-xs text-base-content/55 -mt-3">
                        The big colorful banner at the top of the page. Title supports a styled accent (second line).
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <FormInput v-model="heroForm.hero_eyebrow" label="Eyebrow (optional)"
                            placeholder="Our Heritage" :error="heroForm.errors.hero_eyebrow"
                            help-text="Small uppercase tag above the title." />
                        <div>
                            <label class="mb-1.5 flex items-center gap-1 text-[12px] font-semibold text-base-content/75">Color Theme</label>
                            <select v-model="heroForm.hero_style" class="select select-bordered w-full text-sm">
                                <option v-for="(meta, key) in styles" :key="key" :value="key">{{ meta.label }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <FormInput v-model="heroForm.hero_title" label="Title (primary line)" required
                            placeholder="Seventy-Two Years" :error="heroForm.errors.hero_title" />
                        <FormInput v-model="heroForm.hero_title_accent" label="Accent Title (gradient line)"
                            placeholder="of Learning."
                            help-text="Renders below the primary title with amber→emerald gradient."
                            :error="heroForm.errors.hero_title_accent" />
                    </div>

                    <FormTextarea v-model="heroForm.hero_subtitle" label="Subtitle / Description" rows="2"
                        placeholder="From a modest middle school in 1954 to Skardu's most prestigious institution..."
                        :error="heroForm.errors.hero_subtitle" />

                    <details class="bg-base-200/40 rounded-xl px-4 py-3">
                        <summary class="text-xs font-bold text-base-content/65 cursor-pointer">SEO Meta (optional)</summary>
                        <div class="mt-4 space-y-4">
                            <FormInput v-model="heroForm.meta_title" label="<title> tag override"
                                placeholder="Defaults to page title" :error="heroForm.errors.meta_title" />
                            <FormTextarea v-model="heroForm.meta_description" label="Meta description (for search engines)"
                                rows="2" placeholder="One-paragraph summary used by Google, Facebook, etc."
                                :error="heroForm.errors.meta_description" />
                        </div>
                    </details>

                    <div class="flex justify-end gap-2">
                        <span v-if="heroForm.recentlySuccessful" class="text-xs text-success font-semibold self-center">Saved</span>
                        <button type="submit" class="btn btn-primary btn-sm" :class="{ loading: heroForm.processing }" :disabled="heroForm.processing">
                            {{ heroForm.processing ? 'Saving…' : 'Save Hero' }}
                        </button>
                    </div>
                </div>
            </form>

            <!-- Auto-content info (for pages that also render data-driven sections) -->
            <div v-if="page.auto_content" class="card bg-sky-50 border border-sky-200 shadow-sm">
                <div class="card-body p-4 flex-row items-start gap-3">
                    <SparklesIcon class="w-5 h-5 text-sky-600 shrink-0 mt-0.5" />
                    <div class="text-xs text-sky-900">
                        <div class="font-bold mb-1">This page also has auto-generated content</div>
                        <p class="text-sky-900/80">
                            The body shows <b>{{ page.auto_content }}</b>. That section is managed elsewhere in the
                            Website menu. <b>Custom blocks added below</b> render after the auto section — perfect for
                            intros, FAQs, or call-to-actions.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ════════ BLOCK LIST ════════ -->
            <div class="space-y-3">
                <div class="flex items-center justify-between gap-2 mt-8">
                    <div class="flex items-center gap-2">
                        <Squares2X2Icon class="w-5 h-5 text-emerald-600" />
                        <h2 class="text-base font-bold">Page Content Sections ({{ items.length }})</h2>
                    </div>
                    <span class="text-xs text-base-content/50">Drag to reorder · Click pencil to edit</span>
                </div>

                <div v-if="items.length" class="space-y-3">
                    <div v-for="(block, i) in items" :key="block.id"
                        draggable="true"
                        @dragstart="onDragStart(i)"
                        @dragover="onDragOver"
                        @drop="onDrop(i)"
                        class="card bg-base-100 shadow-sm border border-base-200 hover:shadow-md transition-all"
                        :class="[dragIndex === i ? 'opacity-50' : '', !block.is_active ? 'opacity-60' : '']">
                        <div class="card-body p-0">
                            <div class="flex items-stretch">
                                <div class="flex items-center px-3 cursor-grab active:cursor-grabbing text-base-content/30 hover:text-base-content/60 border-r border-base-200">
                                    <Bars3Icon class="w-5 h-5" />
                                </div>
                                <div class="flex-1 min-w-0 p-4 flex items-center gap-4">
                                    <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                                        <Squares2X2Icon class="w-5 h-5" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">{{ types[block.type]?.label || block.type }}</span>
                                            <span v-if="!block.is_active" class="badge badge-ghost badge-xs">Hidden</span>
                                        </div>
                                        <p class="text-sm font-semibold truncate mt-0.5">{{ summary(block) }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 px-3 border-l border-base-200">
                                    <button @click="toggleActive(block)"
                                        class="btn btn-ghost btn-sm btn-square"
                                        :title="block.is_active ? 'Hide' : 'Show'">
                                        <EyeIcon v-if="block.is_active" class="w-4 h-4 text-success" />
                                        <EyeSlashIcon v-else class="w-4 h-4 text-base-content/40" />
                                    </button>
                                    <button @click="startEdit(block)" class="btn btn-ghost btn-sm btn-square" title="Edit">
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </button>
                                    <button @click="destroy(block)" class="btn btn-ghost btn-sm btn-square text-error" title="Delete">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <EmptyState v-else
                    :icon="Squares2X2Icon"
                    title="No content blocks yet"
                    description="Pick a block type below to start building this page's body content." />

                <!-- Add block — type picker -->
                <div class="card bg-base-100 shadow-sm border-2 border-dashed border-base-300">
                    <div class="card-body p-5">
                        <h3 class="text-sm font-bold mb-3">Add a Block</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            <button v-for="(meta, key) in types" :key="key"
                                @click="startAdd(key)"
                                class="text-left p-3 rounded-xl border border-base-200 hover:border-primary hover:bg-emerald-50/40 transition-all">
                                <div class="flex items-center gap-2 mb-1">
                                    <PlusIcon class="w-3.5 h-3.5 text-emerald-700" />
                                    <span class="text-sm font-bold">{{ meta.label }}</span>
                                </div>
                                <p class="text-[11px] text-base-content/55 leading-relaxed">{{ meta.desc }}</p>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Block Modal -->
        <Modal :show="addModalOpen" max-width="3xl" @close="addModalOpen = false">
            <div class="p-6">
                <h2 class="text-lg font-bold">New {{ types[newType]?.label || newType }} Block</h2>
                <p class="text-xs text-base-content/55 mt-1">{{ types[newType]?.desc }}</p>
                <div class="mt-5">
                    <BlockEditor v-if="newType" :type="newType" v-model:data="newData" />
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button class="btn btn-ghost btn-sm" @click="addModalOpen = false">Cancel</button>
                    <button class="btn btn-primary btn-sm" @click="saveNew">Add Block</button>
                </div>
            </div>
        </Modal>

        <!-- Edit Block Modal -->
        <Modal :show="!!editing" max-width="3xl" @close="editing = null">
            <div v-if="editing" class="p-6">
                <h2 class="text-lg font-bold">Edit {{ types[editing.type]?.label || editing.type }}</h2>
                <div class="mt-5">
                    <BlockEditor :type="editing.type" v-model:data="editingData" />
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button class="btn btn-ghost btn-sm" @click="editing = null">Cancel</button>
                    <button class="btn btn-primary btn-sm" @click="saveEdit">Save Changes</button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
