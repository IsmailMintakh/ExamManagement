<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted } from 'vue'
import { ArrowLeftIcon, CalendarDaysIcon, XMarkIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    site:   { type: Object, default: () => ({}) },
    album:  { type: Object, required: true },
    photos: { type: Array,  default: () => [] },
})

const lightboxIndex = ref(null)

function openLightbox(i) { lightboxIndex.value = i }
function closeLightbox() { lightboxIndex.value = null }
function prev() {
    if (lightboxIndex.value === null) return
    lightboxIndex.value = (lightboxIndex.value - 1 + props.photos.length) % props.photos.length
}
function next() {
    if (lightboxIndex.value === null) return
    lightboxIndex.value = (lightboxIndex.value + 1) % props.photos.length
}

function onKey(e) {
    if (lightboxIndex.value === null) return
    if (e.key === 'Escape') closeLightbox()
    if (e.key === 'ArrowLeft') prev()
    if (e.key === 'ArrowRight') next()
}

onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))

function fmt(d) {
    if (!d) return ''
    return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' })
}
</script>

<template>
    <Head :title="album.title" />
    <PublicLayout>
        <section class="relative bg-slate-950 text-white py-20 overflow-hidden">
            <div v-if="album.cover_url" class="absolute inset-0">
                <img :src="album.cover_url" :alt="album.title" class="absolute inset-0 w-full h-full object-cover" />
                <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/75 to-black/95"></div>
            </div>
            <div v-else class="absolute inset-0 bg-gradient-to-br from-slate-950 via-emerald-950 to-slate-900"></div>

            <div class="relative max-w-5xl mx-auto px-6 lg:px-10">
                <Link href="/gallery" class="inline-flex items-center gap-1.5 text-amber-300 hover:text-amber-200 text-sm font-semibold mb-7 transition-colors">
                    <ArrowLeftIcon class="w-4 h-4" /> All Albums
                </Link>
                <h1 class="text-4xl lg:text-6xl font-black tracking-tight leading-[1.05]">{{ album.title }}</h1>
                <div class="mt-5 flex flex-wrap items-center gap-4 text-stone-300 text-sm">
                    <span v-if="album.event_date" class="inline-flex items-center gap-1.5">
                        <CalendarDaysIcon class="w-4 h-4" /> {{ fmt(album.event_date) }}
                    </span>
                    <span class="text-stone-400">{{ photos.length }} photos</span>
                </div>
                <p v-if="album.description" class="mt-6 text-stone-200 leading-relaxed max-w-3xl">{{ album.description }}</p>
            </div>
        </section>

        <section class="py-16 bg-stone-50 min-h-[400px]">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div v-if="!photos.length" class="text-center py-20 bg-white rounded-3xl shadow-sm border border-stone-100">
                    <p class="text-slate-500">No photos in this album yet.</p>
                </div>

                <!-- Masonry-like grid -->
                <div v-else class="columns-1 sm:columns-2 lg:columns-3 xl:columns-4 gap-4 space-y-4">
                    <button v-for="(p, i) in photos" :key="p.id"
                            @click="openLightbox(i)"
                            class="block w-full break-inside-avoid group overflow-hidden rounded-2xl shadow-sm hover:shadow-2xl transition-all duration-500 bg-white">
                        <img :src="p.image_url" :alt="p.caption || 'Photo'"
                            class="w-full h-auto group-hover:scale-105 transition-transform duration-700" />
                        <p v-if="p.caption" class="px-4 py-3 text-xs text-slate-600 text-left">{{ p.caption }}</p>
                    </button>
                </div>
            </div>
        </section>

        <!-- Lightbox -->
        <div v-if="lightboxIndex !== null"
             class="fixed inset-0 z-[100] bg-black/95 backdrop-blur-md flex items-center justify-center p-4 sm:p-10"
             @click.self="closeLightbox">
            <button @click="closeLightbox"
                class="absolute top-5 right-5 z-10 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors">
                <XMarkIcon class="w-5 h-5" />
            </button>

            <button v-if="photos.length > 1" @click="prev"
                class="absolute left-3 sm:left-8 z-10 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors">
                <ChevronLeftIcon class="w-6 h-6" />
            </button>
            <button v-if="photos.length > 1" @click="next"
                class="absolute right-3 sm:right-8 z-10 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors">
                <ChevronRightIcon class="w-6 h-6" />
            </button>

            <div class="max-w-[90vw] max-h-[88vh] flex flex-col items-center gap-4">
                <img :src="photos[lightboxIndex].image_url" :alt="photos[lightboxIndex].caption || 'Photo'"
                    class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl" />
                <div class="text-white text-sm flex items-center gap-3 bg-white/5 backdrop-blur rounded-full px-5 py-2">
                    <span v-if="photos[lightboxIndex].caption">{{ photos[lightboxIndex].caption }}</span>
                    <span class="text-white/50">{{ lightboxIndex + 1 }} / {{ photos.length }}</span>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
