<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import PageBlocks from '@/Components/PageBlocks.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import {
    AcademicCapIcon, UserGroupIcon, TrophyIcon,
    ArrowRightIcon, BuildingLibraryIcon,
    ArrowTrendingUpIcon,
    NewspaperIcon, CalendarDaysIcon, PlayCircleIcon,
    MapPinIcon,
    ArrowDownIcon,
} from '@heroicons/vue/24/outline'

// Swiper.js — auto-rotating hero
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Autoplay, EffectFade, Pagination, Keyboard } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/effect-fade'
import 'swiper/css/pagination'

const props = defineProps({
    site: { type: Object, default: () => ({}) },
    slides: { type: Array, default: () => [] },
    liveStats: { type: Object, default: () => ({}) },
    latestNews: { type: Array, default: () => [] },
    blocks: { type: Array, default: () => [] },
})

const s = (k, fb = '') => props.site?.[k] || fb

// ===== Stats with animated counters =====
const stats = ref([
    { label: 'Students Enrolled', value: 0, target: props.liveStats.students || 0,    suffix: '', icon: UserGroupIcon },
    { label: 'Board Pass Rate',   value: 0, target: props.liveStats.pass_rate || 0,    suffix: '%', icon: TrophyIcon, decimals: 1 },
    { label: 'Qualified Teachers', value: 0, target: props.liveStats.teachers || 0,   suffix: '', icon: AcademicCapIcon },
    { label: 'Years of Legacy',   value: 0, target: props.liveStats.years_legacy || 0, suffix: '', icon: BuildingLibraryIcon },
])

function animateCounters() {
    stats.value.forEach((stat) => {
        if (!stat.target) return
        const duration = 2000
        const stepTime = 20
        const steps = duration / stepTime
        const increment = stat.target / steps
        let current = 0
        const timer = setInterval(() => {
            current += increment
            if (current >= stat.target) { current = stat.target; clearInterval(timer) }
            stat.value = stat.decimals ? Math.round(current * 10) / 10 : Math.floor(current)
        }, stepTime)
    })
}

let observer = null
onMounted(() => {
    observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting && entry.target.dataset.counter) {
                animateCounters()
                observer.unobserve(entry.target)
            }
        })
    }, { threshold: 0.3 })
    document.querySelectorAll('[data-counter]').forEach(el => observer.observe(el))
})
onUnmounted(() => observer?.disconnect())

// ===== Fallback hero slide if admin hasn't created any yet =====
const heroSlides = computed(() => {
    if (props.slides && props.slides.length > 0) return props.slides
    return [{
        id: 'default',
        eyebrow: s('established_year') ? `Est. ${s('established_year')} · Skardu, Gilgit-Baltistan` : 'Skardu, Gilgit-Baltistan',
        title: s('tagline', 'Where Mountains Meet Excellence'),
        subtitle: null,
        description: 'Government Boys Higher Secondary School No.1 — shaping young men of character, intellect, and purpose for over seventy-two years.',
        image_url: null,
        cta_label: 'Begin Your Application',
        cta_url: '/admissions',
        cta_secondary_label: 'Our Story',
        cta_secondary_url: '/about',
        overlay_color: '#0f172a',
        overlay_opacity: 60,
    }]
})

// Format published_at into a friendly date for the news strip
function fmtDate(d) {
    if (!d) return ''
    return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<template>
    <Head :title="s('school_name', 'Government Boys Higher Secondary School No.1, Skardu')" />
    <PublicLayout>
        <!-- ══════════════════════════════════════════════════════════════
             HERO SLIDER — Auto-rotating with smooth fade
        ═══════════════════════════════════════════════════════════════ -->
        <section class="relative overflow-hidden min-h-[92vh] flex items-center bg-slate-950">
            <Swiper
                :modules="[Autoplay, EffectFade, Pagination, Keyboard]"
                effect="fade"
                :fade-effect="{ crossFade: true }"
                :autoplay="{ delay: 6000, disableOnInteraction: false }"
                :pagination="{ clickable: true }"
                :keyboard="{ enabled: true }"
                :loop="heroSlides.length > 1"
                :speed="900"
                class="absolute inset-0 w-full h-full"
            >
                <SwiperSlide v-for="slide in heroSlides" :key="slide.id" class="relative">
                    <!-- Background image (or layered gradient fallback) -->
                    <div class="absolute inset-0">
                        <img v-if="slide.image_url"
                            :src="slide.image_url"
                            :alt="slide.title"
                            class="absolute inset-0 w-full h-full object-cover scale-110 animate-ken-burns" />
                        <template v-else>
                            <!-- Cinematic gradient backdrop fallback -->
                            <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-emerald-950 to-slate-900"></div>
                            <div class="absolute top-0 left-1/4 w-[50rem] h-[50rem] bg-emerald-600/20 rounded-full blur-[140px] animate-float"></div>
                            <div class="absolute bottom-0 right-0 w-[40rem] h-[40rem] bg-amber-500/10 rounded-full blur-[120px] animate-float" style="animation-delay: 2s"></div>
                        </template>

                        <!-- Configurable color overlay -->
                        <div class="absolute inset-0"
                            :style="`background-color: ${slide.overlay_color || '#0f172a'}; opacity: ${(slide.overlay_opacity ?? 60) / 100}`"></div>

                        <!-- Mountain silhouette -->
                        <svg class="absolute bottom-0 left-0 w-full h-64 opacity-20 pointer-events-none" viewBox="0 0 1440 400" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#0f172a" d="M0,400 L0,250 L120,180 L220,220 L340,120 L460,200 L560,90 L680,180 L800,60 L920,160 L1040,100 L1160,200 L1280,130 L1440,180 L1440,400 Z"/>
                            <path fill="#020617" opacity="0.8" d="M0,400 L0,300 L80,260 L180,290 L280,220 L400,280 L520,230 L640,290 L780,250 L900,310 L1040,270 L1180,320 L1320,280 L1440,310 L1440,400 Z"/>
                        </svg>
                    </div>

                    <!-- Slide content -->
                    <div class="relative max-w-[1400px] mx-auto px-6 lg:px-10 py-20 lg:py-28 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center w-full min-h-[92vh]">
                        <div class="lg:col-span-7 text-white">
                            <div v-if="slide.eyebrow" class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-emerald-500/15 border border-emerald-400/25 backdrop-blur-sm text-[11px] font-semibold mb-6 slide-in-up">
                                <span class="flex items-center gap-1.5">
                                    <span class="relative flex w-2 h-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span></span>
                                    <span class="text-emerald-100 tracking-wider uppercase">{{ slide.eyebrow }}</span>
                                </span>
                            </div>

                            <h1 class="font-black text-[clamp(2.5rem,6vw,5rem)] leading-[1.02] tracking-tight slide-in-up" style="animation-delay: 100ms">
                                <span class="bg-gradient-to-r from-amber-300 via-amber-400 to-emerald-300 bg-clip-text text-transparent animate-gradient">
                                    {{ slide.title }}
                                </span>
                            </h1>

                            <p v-if="slide.subtitle" class="mt-4 text-xl lg:text-2xl text-stone-200 font-light leading-snug max-w-2xl slide-in-up" style="animation-delay: 200ms">
                                {{ slide.subtitle }}
                            </p>

                            <p v-if="slide.description" class="mt-7 text-lg lg:text-xl text-stone-300 leading-relaxed max-w-2xl slide-in-up font-light" style="animation-delay: 300ms">
                                {{ slide.description }}
                            </p>

                            <div v-if="slide.cta_label || slide.cta_secondary_label" class="mt-10 flex flex-wrap gap-4 slide-in-up" style="animation-delay: 400ms">
                                <Link v-if="slide.cta_label" :href="slide.cta_url || '/'"
                                      class="group inline-flex items-center gap-2 bg-gradient-to-br from-amber-400 to-amber-600 hover:from-amber-300 hover:to-amber-500 text-slate-950 px-7 py-4 rounded-full text-sm font-bold shadow-2xl shadow-amber-500/30 hover:shadow-amber-500/50 hover:-translate-y-0.5 transition-all duration-300">
                                    {{ slide.cta_label }}
                                    <ArrowRightIcon class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                                </Link>
                                <Link v-if="slide.cta_secondary_label" :href="slide.cta_secondary_url || '/'"
                                      class="group inline-flex items-center gap-2 bg-white/5 hover:bg-white/10 border border-white/20 hover:border-white/40 backdrop-blur-xl text-white px-7 py-4 rounded-full text-sm font-medium transition-all duration-300">
                                    <PlayCircleIcon class="w-5 h-5 text-amber-400" />
                                    {{ slide.cta_secondary_label }}
                                </Link>
                            </div>

                            <!-- Trust badges (shown on every slide) -->
                            <div class="mt-12 grid grid-cols-2 sm:grid-cols-3 gap-6 slide-in-up" style="animation-delay: 500ms">
                                <div>
                                    <div class="text-[10px] uppercase tracking-[0.2em] text-stone-500 mb-1">Accredited by</div>
                                    <div class="text-sm font-semibold text-stone-200">FBISE · Islamabad</div>
                                </div>
                                <div v-if="s('ddo_name')">
                                    <div class="text-[10px] uppercase tracking-[0.2em] text-stone-500 mb-1">Governed by</div>
                                    <div class="text-sm font-semibold text-stone-200">DDO {{ s('ddo_name') }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase tracking-[0.2em] text-stone-500 mb-1">Region</div>
                                    <div class="text-sm font-semibold text-stone-200">Gilgit-Baltistan</div>
                                </div>
                            </div>
                        </div>

                        <!-- Glass performance dashboard card (always visible, on every slide) -->
                        <div class="lg:col-span-5 slide-in-up" style="animation-delay: 600ms">
                            <div class="relative">
                                <div class="relative bg-white/[0.06] backdrop-blur-2xl border border-white/10 rounded-3xl p-8 shadow-2xl">
                                    <div class="flex items-start justify-between mb-6">
                                        <div>
                                            <div class="text-[10px] uppercase tracking-[0.2em] text-amber-400 font-semibold mb-1">Live · Academic Year</div>
                                            <h3 class="text-2xl font-bold text-white">Performance Dashboard</h3>
                                        </div>
                                        <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-500/15 border border-emerald-500/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                            <span class="text-[10px] font-bold text-emerald-300 uppercase tracking-wider">Active</span>
                                        </div>
                                    </div>

                                    <div class="space-y-5">
                                        <div>
                                            <div class="flex justify-between items-center mb-2">
                                                <span class="text-sm text-stone-300">Board Pass Rate</span>
                                                <span class="font-bold text-lg text-amber-300 tabular-nums">{{ liveStats.pass_rate || '—' }}{{ liveStats.pass_rate ? '%' : '' }}</span>
                                            </div>
                                            <div class="h-1.5 bg-white/10 rounded-full overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-amber-400 to-amber-500 rounded-full transition-all duration-1000" :style="`width: ${Math.min(liveStats.pass_rate || 0, 100)}%`"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="flex justify-between items-center mb-2">
                                                <span class="text-sm text-stone-300">Active Students</span>
                                                <span class="font-bold text-lg text-emerald-300 tabular-nums">{{ liveStats.students?.toLocaleString() || 0 }}</span>
                                            </div>
                                            <div class="h-1.5 bg-white/10 rounded-full overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-emerald-400 to-teal-400 rounded-full" style="width: 100%"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="flex justify-between items-center mb-2">
                                                <span class="text-sm text-stone-300">Faculty Members</span>
                                                <span class="font-bold text-lg text-sky-300 tabular-nums">{{ liveStats.teachers || 0 }}</span>
                                            </div>
                                            <div class="h-1.5 bg-white/10 rounded-full overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-sky-400 to-indigo-400 rounded-full" style="width: 100%"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-7 pt-6 border-t border-white/10 flex items-center justify-between">
                                        <div class="text-xs">
                                            <div class="text-stone-400">Years of Legacy</div>
                                            <div class="font-bold text-base text-white mt-0.5">{{ liveStats.years_legacy || 72 }}+ Years</div>
                                        </div>
                                        <div class="flex items-center gap-1 text-emerald-400 text-sm font-bold">
                                            <ArrowTrendingUpIcon class="w-4 h-4" />
                                            <span>Top in GB</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="absolute -top-6 -left-6 bg-white rounded-2xl shadow-2xl p-4 flex items-center gap-3 animate-float" style="animation-delay: 0.5s">
                                    <div class="w-11 h-11 bg-gradient-to-br from-amber-400 to-amber-600 rounded-xl flex items-center justify-center shadow-lg">
                                        <TrophyIcon class="w-5 h-5 text-white" />
                                    </div>
                                    <div>
                                        <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Ranked</div>
                                        <div class="font-bold text-slate-900 text-sm">Top 3 in GB</div>
                                    </div>
                                </div>

                                <div class="absolute -bottom-6 -right-4 bg-white rounded-2xl shadow-2xl p-4 flex items-center gap-3 animate-float" style="animation-delay: 1.5s">
                                    <div class="w-11 h-11 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-xl flex items-center justify-center shadow-lg">
                                        <MapPinIcon class="w-5 h-5 text-white" />
                                    </div>
                                    <div>
                                        <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Located in</div>
                                        <div class="font-bold text-slate-900 text-sm">Skardu Valley</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </SwiperSlide>
            </Swiper>

            <!-- Scroll indicator -->
            <div class="absolute bottom-8 left-1/2 -translate-x-1/2 z-10 hidden lg:flex flex-col items-center gap-2 text-white/50 animate-float pointer-events-none">
                <span class="text-[10px] uppercase tracking-[0.3em]">Explore</span>
                <ArrowDownIcon class="w-4 h-4" />
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════════════
             CHECK RESULT BANNER — Direct entry to the public lookup page
             so students/parents don't have to hunt through the nav.
        ═══════════════════════════════════════════════════════════════ -->
        <section class="bg-gradient-to-r from-emerald-600 via-emerald-700 to-emerald-800 text-white py-10">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-start md:items-center gap-4">
                    <div class="hidden md:flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/15 backdrop-blur">
                        <AcademicCapIcon class="w-6 h-6 text-amber-300" />
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.2em] font-bold text-amber-300">Students &amp; Parents</p>
                        <h2 class="text-xl md:text-2xl font-bold mt-1">Check your exam result online</h2>
                        <p class="text-sm text-emerald-50/85 mt-1 max-w-xl">
                            Enter your admission number and date of birth to view your published results — no login needed.
                        </p>
                    </div>
                </div>
                <Link href="/check-result"
                    class="inline-flex items-center gap-2 bg-amber-400 hover:bg-amber-300 text-slate-950 px-6 py-3 rounded-full text-sm font-bold shadow-lg transition-all">
                    Check Result
                    <ArrowRightIcon class="w-4 h-4" />
                </Link>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════════════
             STATS STRIP — Live counts from database
        ═══════════════════════════════════════════════════════════════ -->
        <section class="bg-white border-b border-stone-100 py-14" data-counter>
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-4">
                    <div v-for="(stat, i) in stats" :key="stat.label"
                         class="text-center lg:text-left lg:pl-8 lg:border-l lg:first:pl-0 lg:first:border-l-0 border-stone-200 reveal-stagger"
                         :style="`--delay: ${i * 100}ms`">
                        <component :is="stat.icon" class="w-6 h-6 text-emerald-600 mx-auto lg:mx-0 mb-3" />
                        <div class="text-4xl lg:text-5xl font-black text-slate-900 tabular-nums tracking-tight">
                            {{ stat.value.toLocaleString() }}<span class="text-amber-600">{{ stat.suffix }}</span>
                        </div>
                        <div class="text-xs uppercase tracking-[0.15em] text-slate-500 font-semibold mt-2">{{ stat.label }}</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════════════
             DDO MESSAGE — Now dynamic from settings
        ═══════════════════════════════════════════════════════════════ -->
        <section v-if="s('ddo_name') || s('ddo_message')" class="py-24 bg-stone-50 relative overflow-hidden">
            <div class="absolute inset-0 opacity-[0.02] pointer-events-none">
                <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg">
                    <pattern id="dots" width="40" height="40" patternUnits="userSpaceOnUse">
                        <circle cx="20" cy="20" r="1" fill="currentColor" />
                    </pattern>
                    <rect width="100%" height="100%" fill="url(#dots)" />
                </svg>
            </div>

            <div class="relative max-w-5xl mx-auto px-6 lg:px-10">
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-10 items-center">
                    <div class="lg:col-span-2 reveal">
                        <div class="relative">
                            <div class="aspect-[4/5] rounded-3xl bg-gradient-to-br from-emerald-700 via-emerald-800 to-slate-900 p-1 shadow-2xl">
                                <div class="w-full h-full rounded-3xl bg-gradient-to-br from-emerald-600 to-emerald-900 flex items-center justify-center relative overflow-hidden">
                                    <div class="absolute inset-0 opacity-30 bg-gradient-to-t from-black/60 to-transparent"></div>
                                    <span class="relative text-[6rem] font-black text-white/80 tracking-tighter">
                                        {{ (s('ddo_name', 'WZ').split(' ').map(n => n[0]).slice(0, 2).join('')) }}
                                    </span>
                                </div>
                            </div>
                            <div v-if="s('ddo_serving_since')" class="absolute -bottom-5 -right-5 bg-white rounded-2xl shadow-xl px-4 py-3 flex items-center gap-3 max-w-[200px]">
                                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                <div>
                                    <div class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">Serving Since</div>
                                    <div class="text-sm font-bold text-slate-900">{{ s('ddo_serving_since') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-3 reveal-stagger" style="--delay: 150ms">
                        <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-3">A Message From</div>
                        <h2 class="text-3xl lg:text-4xl font-black text-slate-900 tracking-tight mb-2">{{ s('ddo_name', 'DDO') }}</h2>
                        <p v-if="s('ddo_title')" class="text-sm text-emerald-700 font-semibold uppercase tracking-wider mb-7">{{ s('ddo_title') }}</p>

                        <div v-if="s('ddo_message')" class="space-y-5 text-[15px] leading-[1.75] text-slate-700 whitespace-pre-line">
                            {{ s('ddo_message') }}
                        </div>

                        <div class="mt-8 pt-6 border-t border-stone-200 flex items-center gap-4">
                            <div class="text-2xl text-slate-800" style="font-family: 'Dancing Script', cursive">{{ s('ddo_name') }}</div>
                            <span class="w-px h-8 bg-stone-300"></span>
                            <div class="text-xs text-slate-500">
                                <div class="font-semibold text-slate-700">{{ s('ddo_title') || 'DDO · Skardu' }}</div>
                                <div>Government of Gilgit-Baltistan</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════════════
             DYNAMIC ADMIN-MANAGED BLOCKS (features, programs, testimonials, etc.)
        ═══════════════════════════════════════════════════════════════ -->
        <PageBlocks :blocks="blocks" />

        <!-- ══════════════════════════════════════════════════════════════
             NEWS
        ═══════════════════════════════════════════════════════════════ -->
        <section class="py-24 bg-white">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6 mb-12 reveal">
                    <div class="max-w-xl">
                        <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">Latest from the School</div>
                        <h2 class="text-4xl lg:text-5xl font-black text-slate-900 tracking-tight leading-[1.1]">News &amp; Events</h2>
                    </div>
                    <Link href="/news" class="group inline-flex items-center gap-2 text-emerald-700 font-semibold text-sm whitespace-nowrap">
                        All Updates <ArrowRightIcon class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                    </Link>
                </div>

                <div v-if="latestNews.length" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <Link v-for="(n, i) in latestNews" :key="n.id"
                          :href="route('public.news.show', n.slug)"
                          class="group cursor-pointer reveal-stagger"
                          :style="`--delay: ${i * 100}ms`">
                        <div class="aspect-[4/3] rounded-2xl bg-gradient-to-br relative overflow-hidden mb-5"
                             :class="n.image_gradient || (i === 0 ? 'from-emerald-700 to-emerald-950' : i === 1 ? 'from-amber-600 to-orange-800' : 'from-sky-700 to-indigo-900')">
                            <img v-if="n.image_url" :src="n.image_url" :alt="n.title"
                                class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" />
                            <NewspaperIcon v-else class="absolute right-6 bottom-6 w-24 h-24 text-white/20 group-hover:scale-110 transition-transform duration-700" />
                            <div class="absolute top-5 left-5 bg-white/95 text-slate-900 text-[10px] font-bold uppercase tracking-[0.1em] px-3 py-1.5 rounded-full">
                                {{ n.category }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-[11px] text-slate-500 font-medium mb-2">
                            <CalendarDaysIcon class="w-3.5 h-3.5" />
                            <span>{{ fmtDate(n.published_at) }}</span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 leading-snug mb-3 group-hover:text-emerald-700 transition-colors duration-300">{{ n.title }}</h3>
                        <p v-if="n.excerpt" class="text-sm text-slate-600 leading-relaxed line-clamp-2">{{ n.excerpt }}</p>
                        <div class="inline-flex items-center gap-1 mt-4 text-emerald-700 text-xs font-semibold group-hover:gap-2 transition-all">
                            Read more <ArrowRightIcon class="w-3 h-3" />
                        </div>
                    </Link>
                </div>

                <div v-else class="text-center py-12 bg-stone-50 rounded-2xl border border-stone-100">
                    <NewspaperIcon class="w-10 h-10 mx-auto text-slate-300" />
                    <p class="mt-3 text-sm text-slate-500">News articles will appear here once published.</p>
                </div>
            </div>
        </section>

    </PublicLayout>
</template>

<style>
/* Swiper pagination styling — emerald/amber to match brand */
.swiper-pagination {
    bottom: 2rem !important;
}
.swiper-pagination-bullet {
    width: 32px !important;
    height: 4px !important;
    border-radius: 2px !important;
    background: rgba(255, 255, 255, 0.3) !important;
    opacity: 1 !important;
    transition: all 0.3s ease !important;
}
.swiper-pagination-bullet-active {
    background: linear-gradient(90deg, #fbbf24, #34d399) !important;
    width: 56px !important;
}

/* Slide-in entrance for content */
@keyframes slide-in-up {
    from {
        opacity: 0;
        transform: translateY(28px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.swiper-slide-active .slide-in-up {
    animation: slide-in-up 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
}

/* Slow Ken Burns zoom for image slides */
@keyframes ken-burns {
    0%   { transform: scale(1.0); }
    100% { transform: scale(1.15); }
}
.animate-ken-burns {
    animation: ken-burns 14s ease-out forwards;
}
</style>
