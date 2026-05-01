<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeftIcon, CalendarDaysIcon, NewspaperIcon, ArrowRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    site:    { type: Object, default: () => ({}) },
    article: { type: Object, required: true },
    related: { type: Array,  default: () => [] },
})

function fmt(d) {
    if (!d) return ''
    return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' })
}
</script>

<template>
    <Head :title="article.title" />
    <PublicLayout>
        <!-- Article header -->
        <section class="relative bg-slate-950 text-white py-24 overflow-hidden"
                 :class="article.image_gradient || 'from-emerald-700 to-emerald-950'">
            <div v-if="article.image_url" class="absolute inset-0">
                <img :src="article.image_url" :alt="article.title" class="absolute inset-0 w-full h-full object-cover" />
                <div class="absolute inset-0 bg-gradient-to-b from-black/50 via-black/70 to-black/90"></div>
            </div>
            <div v-else class="absolute inset-0 bg-gradient-to-br" :class="article.image_gradient || 'from-slate-950 to-emerald-950'"></div>

            <div class="relative max-w-4xl mx-auto px-6 lg:px-10">
                <Link href="/news" class="inline-flex items-center gap-1.5 text-amber-300 hover:text-amber-200 text-sm font-semibold mb-7 transition-colors">
                    <ArrowLeftIcon class="w-4 h-4" /> All News
                </Link>
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-500/20 border border-amber-400/30 backdrop-blur-sm text-[10px] font-bold uppercase tracking-[0.15em] text-amber-200 mb-5">
                    {{ article.category }}
                </div>
                <h1 class="text-4xl lg:text-6xl font-black tracking-tight leading-[1.05]">{{ article.title }}</h1>
                <div class="mt-6 flex items-center gap-3 text-stone-300 text-sm">
                    <CalendarDaysIcon class="w-4 h-4" /> {{ fmt(article.published_at) }}
                    <span v-if="article.view_count" class="text-stone-400">· {{ article.view_count }} views</span>
                </div>
            </div>
        </section>

        <!-- Body -->
        <section class="py-16 bg-white">
            <div class="max-w-3xl mx-auto px-6 lg:px-10 space-y-6">
                <p v-if="article.excerpt" class="text-xl text-slate-700 font-light leading-[1.7] border-l-4 border-amber-400 pl-6">
                    {{ article.excerpt }}
                </p>
                <article v-if="article.body" class="prose prose-slate max-w-none prose-lg whitespace-pre-line text-slate-700 leading-[1.85]">
                    {{ article.body }}
                </article>
                <p v-else class="text-slate-500 italic">Full article body not provided.</p>
            </div>
        </section>

        <!-- Related -->
        <section v-if="related.length" class="py-20 bg-stone-50 border-t border-stone-100">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <h2 class="text-3xl font-black text-slate-900 mb-10 tracking-tight">More from {{ article.category }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <Link v-for="r in related" :key="r.id" :href="route('public.news.show', r.slug)"
                          class="group cursor-pointer">
                        <div class="aspect-[4/3] rounded-2xl bg-gradient-to-br relative overflow-hidden mb-5 shadow-md group-hover:shadow-2xl transition-all duration-500"
                            :class="r.image_gradient || 'from-emerald-700 to-emerald-950'">
                            <img v-if="r.image_url" :src="r.image_url" :alt="r.title"
                                class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" />
                            <NewspaperIcon v-else class="absolute right-5 bottom-5 w-20 h-20 text-white/15 group-hover:scale-110 transition-transform duration-700" />
                            <div class="absolute top-5 left-5 bg-white/95 text-slate-900 text-[10px] font-bold uppercase tracking-[0.1em] px-3 py-1.5 rounded-full">{{ r.category }}</div>
                        </div>
                        <div class="flex items-center gap-2 text-[11px] text-slate-500 font-medium mb-2">
                            <CalendarDaysIcon class="w-3.5 h-3.5" /> {{ fmt(r.published_at) }}
                        </div>
                        <h3 class="font-bold text-slate-900 leading-snug group-hover:text-emerald-700 transition-colors text-lg tracking-tight">{{ r.title }}</h3>
                        <span class="inline-flex items-center gap-1 mt-3 text-emerald-700 text-xs font-semibold group-hover:gap-2 transition-all">
                            Read <ArrowRightIcon class="w-3 h-3" />
                        </span>
                    </Link>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
