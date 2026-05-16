<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import PublicHero from '@/Components/PublicHero.vue'
import PageBlocks from '@/Components/PageBlocks.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { CalendarDaysIcon, NewspaperIcon, ArrowRightIcon, SparklesIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    site:       { type: Object, default: () => ({}) },
    hero:       { type: Object, default: () => ({}) },
    articles:   { type: Object, default: () => ({ data: [] }) },
    featured:   { type: Object, default: null },
    categories: { type: Array,  default: () => [] },
    filter:     { type: String, default: 'All' },
    blocks:     { type: Array,  default: () => [] },
})

function applyFilter(cat) {
    router.get(route('public.news'), { category: cat === 'All' ? undefined : cat },
        { preserveScroll: false, preserveState: false })
}

function fmt(d) {
    if (!d) return ''
    return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' })
}

const allFilters = ['All', ...props.categories]
</script>

<template>
    <Head :title="hero.meta_title || 'News & Events Test'">
        <meta v-if="hero.meta_description" name="description" :content="hero.meta_description" />
    </Head>
    <PublicLayout>
        <PublicHero :hero="hero" :fallback="{
            eyebrow: 'Stay Updated',
            title: 'News &',
            accent: 'events.',
            subtitle: `Latest from the halls of ${site.school_short_name || 'GBHSS No.1 Skardu'}.`,
        }" />

        <!-- Featured -->
        <section v-if="featured" class="py-16 bg-white">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="flex items-center gap-2 mb-6 reveal">
                    <SparklesIcon class="w-5 h-5 text-amber-500" />
                    <span class="text-[11px] font-bold uppercase tracking-[0.2em] text-amber-600">Featured Story</span>
                </div>
                <Link :href="route('public.news.show', featured.slug)"
                    class="reveal grid grid-cols-1 lg:grid-cols-5 gap-10 items-center rounded-3xl overflow-hidden bg-slate-50 shadow-xl border border-stone-100 group hover:shadow-2xl transition-shadow duration-500">
                    <div class="lg:col-span-2 h-64 lg:h-full bg-gradient-to-br relative overflow-hidden"
                        :class="featured.image_gradient || 'from-emerald-700 to-emerald-950'">
                        <img v-if="featured.image_url" :src="featured.image_url" :alt="featured.title"
                            class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" />
                        <NewspaperIcon v-else class="absolute right-6 bottom-6 w-28 h-28 text-white/20 group-hover:scale-110 transition-transform duration-700" />
                        <div class="absolute top-6 left-6 bg-white/95 text-slate-900 text-[10px] font-bold uppercase tracking-[0.15em] px-3 py-1.5 rounded-full">
                            {{ featured.category }}
                        </div>
                    </div>
                    <div class="lg:col-span-3 p-10">
                        <div class="flex items-center gap-2 text-xs text-slate-500 mb-4 font-medium">
                            <CalendarDaysIcon class="w-3.5 h-3.5" /> {{ fmt(featured.published_at) }}
                        </div>
                        <h2 class="text-2xl lg:text-3xl font-black text-slate-900 leading-tight mb-4 tracking-tight">{{ featured.title }}</h2>
                        <p v-if="featured.excerpt" class="text-slate-600 leading-[1.75]">{{ featured.excerpt }}</p>
                        <span class="mt-6 inline-flex items-center gap-2 text-emerald-700 font-semibold text-sm group-hover:gap-3 transition-all">
                            Continue Reading <ArrowRightIcon class="w-4 h-4" />
                        </span>
                    </div>
                </Link>
            </div>
        </section>

        <!-- Filters -->
        <section v-if="categories.length" class="sticky top-[72px] lg:top-20 z-30 py-5 bg-white/95 backdrop-blur-xl border-y border-stone-100">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10 flex gap-2 overflow-x-auto">
                <button v-for="f in allFilters" :key="f" @click="applyFilter(f)"
                        class="px-5 py-2.5 rounded-full text-sm font-semibold whitespace-nowrap transition-all duration-300"
                        :class="filter === f ? 'bg-slate-900 text-white shadow-lg' : 'bg-stone-100 text-slate-700 hover:bg-stone-200'">
                    {{ f }}
                </button>
            </div>
        </section>

        <!-- Grid -->
        <section class="py-16 bg-stone-50 min-h-[400px]">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10 space-y-10">
                <div v-if="!articles.data?.length" class="text-center py-20">
                    <NewspaperIcon class="w-12 h-12 mx-auto text-slate-300" />
                    <p class="mt-4 text-slate-500">No articles in this category yet.</p>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <Link v-for="(n, i) in articles.data" :key="n.id"
                            :href="route('public.news.show', n.slug)"
                            class="group cursor-pointer reveal-stagger"
                            :style="`--delay: ${(i % 6) * 60}ms`">
                        <div class="aspect-[4/3] rounded-2xl bg-gradient-to-br relative overflow-hidden mb-5 shadow-md group-hover:shadow-2xl transition-all duration-500"
                            :class="n.image_gradient || 'from-emerald-700 to-emerald-950'">
                            <img v-if="n.image_url" :src="n.image_url" :alt="n.title"
                                class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" />
                            <div class="absolute top-5 left-5 bg-white/95 text-slate-900 text-[10px] font-bold uppercase tracking-[0.1em] px-3 py-1.5 rounded-full">
                                {{ n.category }}
                            </div>
                            <NewspaperIcon v-if="!n.image_url" class="absolute right-5 bottom-5 w-20 h-20 text-white/15 group-hover:scale-110 transition-transform duration-700" />
                        </div>
                        <div class="flex items-center gap-2 text-[11px] text-slate-500 font-medium mb-2">
                            <CalendarDaysIcon class="w-3.5 h-3.5" /> {{ fmt(n.published_at) }}
                        </div>
                        <h3 class="font-bold text-slate-900 leading-snug mb-2 group-hover:text-emerald-700 transition-colors duration-300 text-lg tracking-tight">{{ n.title }}</h3>
                        <p v-if="n.excerpt" class="text-sm text-slate-600 leading-relaxed line-clamp-3">{{ n.excerpt }}</p>
                        <span class="inline-flex items-center gap-1.5 mt-4 text-emerald-700 text-xs font-semibold group-hover:gap-2.5 transition-all">
                            Read more <ArrowRightIcon class="w-3.5 h-3.5" />
                        </span>
                    </Link>
                </div>

                <div v-if="articles.data?.length" class="bg-white rounded-2xl px-6 py-4 border border-stone-100">
                    <Pagination :links="articles.links" :from="articles.from" :to="articles.to" :total="articles.total" />
                </div>
            </div>
        </section>

        <!-- Custom admin-managed blocks (intros, CTAs, etc.) -->
        <PageBlocks :blocks="blocks" />
    </PublicLayout>
</template>
