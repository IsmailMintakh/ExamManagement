<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import {
    PlusIcon, PencilSquareIcon, TrashIcon, PhotoIcon,
    ArrowsUpDownIcon, EyeIcon, EyeSlashIcon, Bars3Icon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    slides: { type: Array, default: () => [] },
})

const items = ref([...props.slides])
const dragIndex = ref(null)

function onDragStart(i) { dragIndex.value = i }
function onDragOver(e) { e.preventDefault() }
function onDrop(targetIdx) {
    if (dragIndex.value === null || dragIndex.value === targetIdx) return
    const [moved] = items.value.splice(dragIndex.value, 1)
    items.value.splice(targetIdx, 0, moved)
    dragIndex.value = null
    // Persist new order
    router.post(route('website.hero-slides.reorder'), {
        order: items.value.map(s => s.id),
    }, { preserveScroll: true, preserveState: true })
}

function destroy(id) {
    if (!confirm('Delete this slide? This cannot be undone.')) return
    router.delete(route('website.hero-slides.destroy', id), { preserveScroll: true })
}
</script>

<template>
    <Head title="Hero Slider" />
    <AppLayout :breadcrumbs="[{ label: 'Website' }, { label: 'Hero Slider' }]">
        <div class="max-w-5xl mx-auto space-y-6">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight">Hero Slider</h1>
                    <p class="text-sm text-base-content/55 mt-0.5">
                        {{ items.length }} slide{{ items.length === 1 ? '' : 's' }} in homepage rotation · drag to reorder
                    </p>
                </div>
                <Link :href="route('website.hero-slides.create')" class="btn btn-primary btn-sm gap-1.5">
                    <PlusIcon class="w-4 h-4" /> Add Slide
                </Link>
            </div>

            <EmptyState v-if="!items.length"
                :icon="PhotoIcon"
                title="No slides yet"
                description="Add your first hero slide. Slides auto-rotate on the public homepage with smooth fade animations."
                action-text="Add First Slide"
                :action-href="route('website.hero-slides.create')" />

            <div v-else class="grid gap-4">
                <div v-for="(slide, i) in items" :key="slide.id"
                    draggable="true"
                    @dragstart="onDragStart(i)"
                    @dragover="onDragOver"
                    @drop="onDrop(i)"
                    class="surface surface-hover overflow-hidden"
                    :class="dragIndex === i ? 'opacity-50' : ''">
                    <div>
                        <div class="flex items-stretch">
                            <!-- Drag handle -->
                            <div class="flex items-center px-3 cursor-grab active:cursor-grabbing text-base-content/30 hover:text-base-content/60 border-r border-base-200">
                                <Bars3Icon class="w-5 h-5" />
                            </div>

                            <!-- Image preview -->
                            <div class="w-32 sm:w-44 shrink-0 relative bg-gradient-to-br from-emerald-700 to-slate-900 overflow-hidden">
                                <img v-if="slide.image_url" :src="slide.image_url" :alt="slide.title"
                                    class="absolute inset-0 w-full h-full object-cover" />
                                <PhotoIcon v-else class="absolute inset-0 m-auto w-10 h-10 text-white/40" />
                                <div v-if="!slide.is_active"
                                    class="absolute inset-0 bg-black/60 flex items-center justify-center">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-white/80">Hidden</span>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0 p-4 flex flex-col justify-center">
                                <p v-if="slide.eyebrow" class="text-[10px] uppercase tracking-[0.18em] font-bold text-amber-600 mb-1">
                                    {{ slide.eyebrow }}
                                </p>
                                <h3 class="text-base font-bold text-base-content truncate">{{ slide.title }}</h3>
                                <p v-if="slide.subtitle" class="text-sm text-base-content/65 truncate mt-0.5">
                                    {{ slide.subtitle }}
                                </p>
                                <div class="flex items-center gap-3 mt-2 text-[11px] text-base-content/50">
                                    <span class="inline-flex items-center gap-1">
                                        <ArrowsUpDownIcon class="w-3 h-3" />
                                        Order #{{ i + 1 }}
                                    </span>
                                    <span v-if="slide.cta_label" class="inline-flex items-center gap-1">
                                        <span class="w-1 h-1 rounded-full bg-base-content/30"></span>
                                        CTA: {{ slide.cta_label }}
                                    </span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-1 px-3 border-l border-base-200">
                                <span v-if="slide.is_active" class="hidden sm:inline-flex items-center gap-1 text-[10px] font-bold text-success uppercase tracking-wider mr-2">
                                    <EyeIcon class="w-3.5 h-3.5" /> Active
                                </span>
                                <span v-else class="hidden sm:inline-flex items-center gap-1 text-[10px] font-bold text-base-content/40 uppercase tracking-wider mr-2">
                                    <EyeSlashIcon class="w-3.5 h-3.5" /> Hidden
                                </span>
                                <Link :href="route('website.hero-slides.edit', slide.id)"
                                    class="btn btn-ghost btn-sm btn-square" title="Edit">
                                    <PencilSquareIcon class="w-4 h-4" />
                                </Link>
                                <button @click="destroy(slide.id)"
                                    class="btn btn-ghost btn-sm btn-square text-error" title="Delete">
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
