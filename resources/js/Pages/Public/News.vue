<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { CalendarDaysIcon, NewspaperIcon, ArrowRightIcon, SparklesIcon } from '@heroicons/vue/24/outline'

const filter = ref('All')
const filters = ['All', 'Achievement', 'Event', 'Announcement', 'Policy']

const news = ref([
    { date: '21 April 2026', category: 'Achievement', title: 'Three Students Secure Top Positions in FBISE Matric 2026', excerpt: 'Muhammad Abbas Ali (1st position), Sultan Mehdi (4th), and Ali Raza (9th) — all from our Matric batch — brought honor to Skardu by securing top national positions. DDO Wazir Zamin Ali personally honored them at a ceremony attended by the Chief Secretary, GB.', featured: true, gradient: 'from-emerald-600 to-emerald-900' },
    { date: '15 April 2026', category: 'Event', title: 'Annual Sports Gala 2026 Concludes at Municipal Ground', excerpt: 'Three days of fierce but friendly competition across cricket, football, volleyball, and traditional polo. Class 10-A emerged as overall champions with 184 points.', gradient: 'from-sky-600 to-indigo-800' },
    { date: '10 April 2026', category: 'Announcement', title: 'Admissions for Matric &amp; FSc (2026–27) Open Until May 31', excerpt: 'Applications for Class 9 and Class 11 (all streams: Pre-Medical, Pre-Engineering, ICS, FA) are now being accepted. Submit in person at the school office.', gradient: 'from-amber-600 to-orange-800' },
    { date: '4 April 2026', category: 'Achievement', title: 'Science Olympiad — Sultan Abbasi Wins Bronze at National Level', excerpt: 'Our Class 12 student Sultan Abbasi represented Gilgit-Baltistan at the National Physics Olympiad held in Islamabad and returned with the bronze medal.', gradient: 'from-rose-600 to-pink-800' },
    { date: '28 March 2026', category: 'Policy', title: 'New Attendance Policy Effective May 1, 2026', excerpt: 'Students require a minimum of 75% attendance to sit for annual examinations. Parents will receive daily SMS updates via a new digital attendance system.', gradient: 'from-slate-700 to-slate-900' },
    { date: '20 March 2026', category: 'Announcement', title: 'Library Wing Expansion — 2,000 New Titles Added', excerpt: 'Our library now holds over 6,000 books. New additions include science reference texts, Urdu classics, Balti folk literature, and English fiction collections.', gradient: 'from-violet-600 to-purple-800' },
    { date: '14 March 2026', category: 'Event', title: 'Pakistan Day Celebrations Held with Full Honors', excerpt: 'Flag-hoisting ceremony, national songs performance, and a speech contest on the ideology of Pakistan. DDO Wazir Zamin Ali delivered the keynote address.', gradient: 'from-green-700 to-emerald-900' },
    { date: '8 March 2026', category: 'Achievement', title: 'Twelve Teachers Awarded "Excellence in Teaching 2026"', excerpt: 'Annual recognition ceremony honored outstanding educators. Awards presented by the Education Minister, Government of Gilgit-Baltistan, in Gilgit.', gradient: 'from-amber-700 to-red-900' },
])

const filtered = computed(() => filter.value === 'All' ? news.value : news.value.filter(n => n.category === filter.value))
const featured = computed(() => news.value.find(n => n.featured))
</script>

<template>
    <Head title="News &amp; Events — GBHSS No.1 Skardu" />
    <PublicLayout>
        <section class="relative bg-slate-950 text-white py-28 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-emerald-950 to-slate-900"></div>
            <div class="absolute top-0 right-0 w-[40rem] h-[40rem] bg-emerald-600/20 rounded-full blur-[140px] animate-float"></div>
            <div class="relative max-w-5xl mx-auto px-6 lg:px-10 text-center reveal">
                <div class="text-[11px] uppercase tracking-[0.25em] text-amber-400 font-semibold mb-5">Stay Updated</div>
                <h1 class="text-5xl lg:text-7xl font-black tracking-tight leading-[1.0]">
                    News &amp;<br />
                    <span class="bg-gradient-to-r from-amber-300 to-emerald-300 bg-clip-text text-transparent">events.</span>
                </h1>
                <p class="mt-7 text-lg text-stone-300 max-w-2xl mx-auto font-light">Latest from the halls of GBHSS No.1 Skardu.</p>
            </div>
        </section>

        <!-- Featured -->
        <section v-if="featured" class="py-16 bg-white">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="flex items-center gap-2 mb-6 reveal">
                    <SparklesIcon class="w-5 h-5 text-amber-500" />
                    <span class="text-[11px] font-bold uppercase tracking-[0.2em] text-amber-600">Featured Story</span>
                </div>
                <div class="reveal grid grid-cols-1 lg:grid-cols-5 gap-10 items-center rounded-3xl overflow-hidden bg-slate-50 shadow-xl border border-stone-100 group hover:shadow-2xl transition-shadow duration-500">
                    <div class="lg:col-span-2 h-64 lg:h-full bg-gradient-to-br relative overflow-hidden" :class="featured.gradient">
                        <NewspaperIcon class="absolute right-6 bottom-6 w-28 h-28 text-white/20 group-hover:scale-110 transition-transform duration-700" />
                        <div class="absolute top-6 left-6 bg-white/95 text-slate-900 text-[10px] font-bold uppercase tracking-[0.15em] px-3 py-1.5 rounded-full">{{ featured.category }}</div>
                    </div>
                    <div class="lg:col-span-3 p-10">
                        <div class="flex items-center gap-2 text-xs text-slate-500 mb-4 font-medium">
                            <CalendarDaysIcon class="w-3.5 h-3.5" /> {{ featured.date }}
                        </div>
                        <h2 class="text-2xl lg:text-3xl font-black text-slate-900 leading-tight mb-4 tracking-tight" v-html="featured.title"></h2>
                        <p class="text-slate-600 leading-[1.75]" v-html="featured.excerpt"></p>
                        <button class="mt-6 inline-flex items-center gap-2 text-emerald-700 font-semibold text-sm group-hover:gap-3 transition-all">
                            Continue Reading <ArrowRightIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filters -->
        <section class="sticky top-[72px] lg:top-20 z-30 py-5 bg-white/95 backdrop-blur-xl border-y border-stone-100">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10 flex gap-2 overflow-x-auto">
                <button v-for="f in filters" :key="f" @click="filter = f"
                        class="px-5 py-2.5 rounded-full text-sm font-semibold whitespace-nowrap transition-all duration-300"
                        :class="filter === f ? 'bg-slate-900 text-white shadow-lg' : 'bg-stone-100 text-slate-700 hover:bg-stone-200'">
                    {{ f }}
                </button>
            </div>
        </section>

        <!-- Grid -->
        <section class="py-16 bg-stone-50">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <article v-for="(n, i) in filtered" :key="n.title"
                             class="group cursor-pointer reveal-stagger"
                             :style="`--delay: ${(i % 6) * 60}ms`">
                        <div class="aspect-[4/3] rounded-2xl bg-gradient-to-br relative overflow-hidden mb-5 shadow-md group-hover:shadow-2xl transition-all duration-500" :class="n.gradient">
                            <div class="absolute top-5 left-5 bg-white/95 text-slate-900 text-[10px] font-bold uppercase tracking-[0.1em] px-3 py-1.5 rounded-full">{{ n.category }}</div>
                            <NewspaperIcon class="absolute right-5 bottom-5 w-20 h-20 text-white/15 group-hover:scale-110 transition-transform duration-700" />
                        </div>
                        <div class="flex items-center gap-2 text-[11px] text-slate-500 font-medium mb-2">
                            <CalendarDaysIcon class="w-3.5 h-3.5" /> {{ n.date }}
                        </div>
                        <h3 class="font-bold text-slate-900 leading-snug mb-2 group-hover:text-emerald-700 transition-colors duration-300 text-lg tracking-tight" v-html="n.title"></h3>
                        <p class="text-sm text-slate-600 leading-relaxed line-clamp-3" v-html="n.excerpt"></p>
                        <button class="inline-flex items-center gap-1.5 mt-4 text-emerald-700 text-xs font-semibold group-hover:gap-2.5 transition-all">
                            Read more <ArrowRightIcon class="w-3.5 h-3.5" />
                        </button>
                    </article>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
