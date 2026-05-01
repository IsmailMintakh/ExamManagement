<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import PublicHero from '@/Components/PublicHero.vue'
import PageBlocks from '@/Components/PageBlocks.vue'
import { Head, Link } from '@inertiajs/vue3'
import { PhotoIcon, CalendarDaysIcon, ArrowRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    site:   { type: Object, default: () => ({}) },
    hero:   { type: Object, default: () => ({}) },
    albums: { type: Array,  default: () => [] },
    blocks: { type: Array,  default: () => [] },
})

function fmt(d) {
    if (!d) return ''
    return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<template>
    <Head :title="hero.meta_title || 'Photo Gallery'">
        <meta v-if="hero.meta_description" name="description" :content="hero.meta_description" />
    </Head>
    <PublicLayout>
        <PublicHero :hero="hero" :fallback="{
            eyebrow: 'Campus Life',
            title: 'Moments &',
            accent: 'memories.',
            subtitle: `Glimpses of school events, ceremonies, and the everyday spirit of ${site.school_short_name || 'our school'}.`,
        }" />

        <section class="py-16 bg-stone-50 min-h-[400px]">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div v-if="!albums.length" class="text-center py-20 bg-white rounded-3xl shadow-sm border border-stone-100">
                    <PhotoIcon class="w-16 h-16 mx-auto text-stone-300" />
                    <h2 class="mt-5 text-2xl font-bold text-slate-900">Photos coming soon</h2>
                    <p class="mt-3 text-slate-500">We're curating our photo collection — check back soon.</p>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <Link v-for="(a, i) in albums" :key="a.id"
                          :href="route('public.gallery.show', a.slug)"
                          class="group cursor-pointer reveal-stagger"
                          :style="`--delay: ${(i % 6) * 60}ms`">
                        <div class="aspect-[4/3] rounded-2xl bg-gradient-to-br from-emerald-700 to-slate-900 relative overflow-hidden mb-5 shadow-md group-hover:shadow-2xl transition-all duration-500">
                            <img v-if="a.cover_url" :src="a.cover_url" :alt="a.title"
                                class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" />
                            <PhotoIcon v-else class="absolute right-5 bottom-5 w-20 h-20 text-white/15 group-hover:scale-110 transition-transform duration-700" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent opacity-70 group-hover:opacity-90 transition-opacity"></div>
                            <div class="absolute top-5 right-5 bg-white/95 text-slate-900 text-[10px] font-bold uppercase tracking-[0.1em] px-3 py-1.5 rounded-full">
                                {{ a.photos_count }} photos
                            </div>
                            <div class="absolute bottom-5 left-5 right-5 text-white">
                                <h3 class="font-black text-xl tracking-tight leading-snug">{{ a.title }}</h3>
                                <p v-if="a.event_date" class="mt-1 text-xs text-white/80 inline-flex items-center gap-1">
                                    <CalendarDaysIcon class="w-3 h-3" /> {{ fmt(a.event_date) }}
                                </p>
                            </div>
                        </div>
                        <p v-if="a.description" class="text-sm text-slate-600 leading-relaxed line-clamp-2">{{ a.description }}</p>
                        <span class="inline-flex items-center gap-1.5 mt-3 text-emerald-700 text-xs font-semibold group-hover:gap-2.5 transition-all">
                            View album <ArrowRightIcon class="w-3.5 h-3.5" />
                        </span>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Custom admin-managed blocks -->
        <PageBlocks :blocks="blocks" />
    </PublicLayout>
</template>
