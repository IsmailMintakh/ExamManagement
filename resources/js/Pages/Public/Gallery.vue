<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { CameraIcon } from '@heroicons/vue/24/outline'

const categories = ['All', 'Events', 'Sports', 'Academics', 'Ceremonies', 'Campus']
const activeCategory = ref('All')

const photos = ref([
    { title: 'Annual Prize Distribution 2026', category: 'Ceremonies', gradient: 'from-amber-500 via-orange-600 to-rose-800' },
    { title: 'Inter-House Cricket Tournament', category: 'Sports', gradient: 'from-emerald-600 via-teal-700 to-sky-900' },
    { title: 'Science Exhibition — Class 11', category: 'Academics', gradient: 'from-violet-600 via-purple-700 to-indigo-900' },
    { title: 'Pakistan Day Parade · Skardu', category: 'Events', gradient: 'from-emerald-700 via-green-800 to-emerald-950' },
    { title: 'Chemistry Laboratory', category: 'Campus', gradient: 'from-sky-600 via-blue-700 to-indigo-900' },
    { title: 'Matriculation Graduation 2026', category: 'Ceremonies', gradient: 'from-amber-600 via-yellow-600 to-orange-800' },
    { title: 'Urdu Debate Competition', category: 'Academics', gradient: 'from-rose-600 via-pink-700 to-fuchsia-900' },
    { title: 'Traditional Polo Match', category: 'Sports', gradient: 'from-lime-600 via-green-700 to-emerald-900' },
    { title: 'Our Library — 4,000+ Books', category: 'Campus', gradient: 'from-stone-600 via-neutral-700 to-slate-900' },
    { title: 'Independence Day · 14 August', category: 'Events', gradient: 'from-green-700 via-emerald-700 to-green-950' },
    { title: 'Quran Recitation Competition', category: 'Events', gradient: 'from-teal-600 via-cyan-700 to-sky-900' },
    { title: 'Biology Lab · Hands-on', category: 'Campus', gradient: 'from-rose-500 via-red-600 to-pink-900' },
    { title: 'Teachers Day Tribute', category: 'Ceremonies', gradient: 'from-violet-500 via-purple-600 to-violet-900' },
    { title: 'FBISE Toppers 2026', category: 'Academics', gradient: 'from-amber-600 via-orange-700 to-red-900' },
    { title: 'Volleyball Finals', category: 'Sports', gradient: 'from-orange-600 via-red-700 to-rose-900' },
    { title: 'Calligraphy Exhibition', category: 'Events', gradient: 'from-fuchsia-600 via-pink-700 to-rose-900' },
])

const filtered = computed(() => activeCategory.value === 'All' ? photos.value : photos.value.filter(p => p.category === activeCategory.value))
</script>

<template>
    <Head title="Gallery — GBHSS No.1 Skardu" />
    <PublicLayout>
        <section class="relative bg-slate-950 text-white py-28 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-emerald-950 to-slate-900"></div>
            <div class="absolute top-0 right-0 w-[40rem] h-[40rem] bg-amber-500/15 rounded-full blur-[140px] animate-float"></div>
            <div class="relative max-w-5xl mx-auto px-6 lg:px-10 text-center reveal">
                <div class="text-[11px] uppercase tracking-[0.25em] text-amber-400 font-semibold mb-5">Moments Captured</div>
                <h1 class="text-5xl lg:text-7xl font-black tracking-tight leading-[1.0]">
                    A school in<br />
                    <span class="bg-gradient-to-r from-amber-300 to-emerald-300 bg-clip-text text-transparent">pictures.</span>
                </h1>
                <p class="mt-7 text-lg text-stone-300 max-w-2xl mx-auto font-light">Seventy-two years of memories, milestones, and mountains.</p>
            </div>
        </section>

        <!-- Filters -->
        <section class="sticky top-[72px] lg:top-20 z-30 py-5 bg-white/95 backdrop-blur-xl border-b border-stone-100">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10 flex gap-2 overflow-x-auto">
                <button v-for="c in categories" :key="c" @click="activeCategory = c"
                        class="px-5 py-2.5 rounded-full text-sm font-semibold whitespace-nowrap transition-all duration-300"
                        :class="activeCategory === c ? 'bg-slate-900 text-white shadow-lg' : 'bg-stone-100 text-slate-700 hover:bg-stone-200'">
                    {{ c }}
                </button>
            </div>
        </section>

        <!-- Grid -->
        <section class="py-14 bg-stone-50">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <div v-for="(p, i) in filtered" :key="p.title + i"
                         class="group relative rounded-2xl overflow-hidden aspect-square cursor-pointer shadow-lg hover:shadow-2xl transition-all duration-500 reveal-stagger"
                         :style="`--delay: ${(i % 8) * 40}ms`">
                        <div class="absolute inset-0 bg-gradient-to-br transition-transform duration-700 group-hover:scale-110" :class="p.gradient"></div>
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-all duration-500"></div>
                        <CameraIcon class="absolute top-4 right-4 w-5 h-5 text-white/40 group-hover:text-white group-hover:rotate-12 transition-all duration-500" />
                        <div class="absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black/80 via-black/40 to-transparent">
                            <span class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-300">{{ p.category }}</span>
                            <h3 class="text-white font-bold text-sm leading-tight mt-1 tracking-tight" v-html="p.title"></h3>
                        </div>
                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                            <div class="w-14 h-14 rounded-full bg-white/20 backdrop-blur border-2 border-white/40 flex items-center justify-center">
                                <CameraIcon class="w-6 h-6 text-white" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
