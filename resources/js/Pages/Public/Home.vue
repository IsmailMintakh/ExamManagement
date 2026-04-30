<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted } from 'vue'
import {
    AcademicCapIcon, UserGroupIcon, BookOpenIcon, TrophyIcon,
    ArrowRightIcon, CheckBadgeIcon, SparklesIcon, BuildingLibraryIcon,
    HeartIcon, GlobeAltIcon, LightBulbIcon, ShieldCheckIcon,
    StarIcon, ChatBubbleLeftRightIcon, ArrowTrendingUpIcon,
    NewspaperIcon, CalendarDaysIcon, PlayCircleIcon,
    MapPinIcon, BeakerIcon, ComputerDesktopIcon, MusicalNoteIcon,
    ArrowDownIcon,
} from '@heroicons/vue/24/outline'

// Animated counter logic
const stats = ref([
    { label: 'Students Enrolled', value: 0, target: 1248, suffix: '', icon: UserGroupIcon },
    { label: 'Board Pass Rate', value: 0, target: 94, suffix: '%', icon: TrophyIcon },
    { label: 'Qualified Teachers', value: 0, target: 68, suffix: '', icon: AcademicCapIcon },
    { label: 'Years of Legacy', value: 0, target: 72, suffix: '', icon: BuildingLibraryIcon },
])

function animateCounters() {
    stats.value.forEach((stat) => {
        const duration = 2000
        const stepTime = 20
        const steps = duration / stepTime
        const increment = stat.target / steps
        let current = 0
        const timer = setInterval(() => {
            current += increment
            if (current >= stat.target) { current = stat.target; clearInterval(timer) }
            stat.value = Math.floor(current)
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

const features = [
    { icon: ShieldCheckIcon, title: 'Seven Decades of Trust', desc: 'Established in 1954, we are the oldest and most respected boys\' institution in Baltistan — a heritage of excellence passed down through generations.' },
    { icon: BeakerIcon, title: 'Modern Science Facilities', desc: 'Fully-equipped physics, chemistry, and biology laboratories. Computer lab with 40 workstations and high-speed satellite internet.' },
    { icon: HeartIcon, title: 'Values & Character', desc: 'Rooted in Islamic and Baltistani cultural values — we shape honest, responsible, and compassionate young men ready for the world.' },
    { icon: GlobeAltIcon, title: 'Federal Board Affiliated', desc: 'Registered with the Federal Board of Intermediate &amp; Secondary Education, Islamabad (FBISE) for Matric and FSc / FA / ICS programs.' },
    { icon: UserGroupIcon, title: 'Dedicated Faculty', desc: 'Sixty-eight teachers, many holding Master\'s and M.Phil degrees — mentoring students in both Urdu and English medium.' },
    { icon: TrophyIcon, title: 'Top in Gilgit-Baltistan', desc: 'Ranked among the top 3 higher secondary schools in Gilgit-Baltistan region for the past six years running — by FBISE results.' },
]

const programs = [
    { level: 'Matric', range: 'Class 9–10', desc: 'Science &amp; Arts streams. Foundation for professional careers.', icon: BookOpenIcon },
    { level: 'FSc Pre-Medical', range: 'Class 11–12', desc: 'Physics, Chemistry, Biology — path to medicine.', icon: BeakerIcon },
    { level: 'FSc Pre-Engineering', range: 'Class 11–12', desc: 'Math, Physics, Chemistry — engineering track.', icon: LightBulbIcon },
    { level: 'ICS / FA', range: 'Class 11–12', desc: 'Computer Science and Arts specializations.', icon: ComputerDesktopIcon },
]

const testimonials = [
    { name: 'Dr. Ghulam Hussain', role: 'Alumnus — Matric 1998 · Cardiologist, AKU Karachi', quote: 'This school gave me wings. My teachers in Skardu saw in me what I could not see in myself. Every success in my career traces back to these mountain classrooms.' },
    { name: 'Col. Muhammad Ali', role: 'Alumnus — FSc 1989 · Pakistan Army (Retd.)', quote: 'Seventy-two years of legacy is not a number — it is a responsibility. This school builds character first, and knowledge second. Both serve you for life.' },
    { name: 'Wazir Ahmed', role: 'Parent — Grade 10 student', quote: 'As a father, I see my son grow not just in marks but in manners. The discipline, the prayers, the respect for elders — all nurtured here. A school worthy of our sons.' },
]

const news = [
    { date: '21 Apr 2026', category: 'Achievement', title: 'Three Students Secure Top Positions in FBISE Matric Board 2026', excerpt: 'Muhammad Abbas Ali (1st Position), Sultan Mehdi (4th), and Ali Raza (9th) — all from our Matric Batch 2026 — have brought honor to Skardu by securing top positions in the Federal Board.' },
    { date: '15 Apr 2026', category: 'Event', title: 'Annual Sports Gala 2026 Concludes at Municipal Ground', excerpt: 'Our students competed in cricket, football, volleyball, and traditional polo across three days. Class 10-A emerged as overall champions with 184 points.' },
    { date: '10 Apr 2026', category: 'Announcement', title: 'Admissions for Matric &amp; FSc (2026–27) Open Until May 31', excerpt: 'Applications are being received at the school office daily between 9:00 AM and 2:00 PM. Fresh admissions for Class 9 and Class 11 (all streams) are now welcome.' },
]
</script>

<template>
    <Head title="Government Boys Higher Secondary School No.1, Skardu" />
    <PublicLayout>
        <!-- ══════════════════════════════════════════════════════════════
             HERO — Cinematic mountain gradient with glass-morphism
        ═══════════════════════════════════════════════════════════════ -->
        <section class="relative overflow-hidden min-h-[92vh] flex items-center bg-slate-950">
            <!-- Layered gradient backdrop evoking Karakoram snow & twilight -->
            <div class="absolute inset-0">
                <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-emerald-950 to-slate-900"></div>
                <div class="absolute top-0 left-1/4 w-[50rem] h-[50rem] bg-emerald-600/20 rounded-full blur-[140px] animate-float"></div>
                <div class="absolute bottom-0 right-0 w-[40rem] h-[40rem] bg-amber-500/10 rounded-full blur-[120px] animate-float" style="animation-delay: 2s"></div>
                <div class="absolute top-1/2 -translate-y-1/2 left-0 w-[30rem] h-[30rem] bg-sky-500/10 rounded-full blur-[100px] animate-float" style="animation-delay: 4s"></div>

                <!-- Mountain silhouette using SVG -->
                <svg class="absolute bottom-0 left-0 w-full h-64 opacity-20" viewBox="0 0 1440 400" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill="#0f172a" d="M0,400 L0,250 L120,180 L220,220 L340,120 L460,200 L560,90 L680,180 L800,60 L920,160 L1040,100 L1160,200 L1280,130 L1440,180 L1440,400 Z"/>
                    <path fill="#020617" opacity="0.8" d="M0,400 L0,300 L80,260 L180,290 L280,220 L400,280 L520,230 L640,290 L780,250 L900,310 L1040,270 L1180,320 L1320,280 L1440,310 L1440,400 Z"/>
                </svg>

                <!-- Fine grid -->
                <svg class="absolute inset-0 w-full h-full opacity-[0.03]" xmlns="http://www.w3.org/2000/svg">
                    <pattern id="grid" width="60" height="60" patternUnits="userSpaceOnUse">
                        <path d="M 60 0 L 0 0 0 60" fill="none" stroke="white" stroke-width="0.5" />
                    </pattern>
                    <rect width="100%" height="100%" fill="url(#grid)" />
                </svg>
            </div>

            <!-- Content -->
            <div class="relative max-w-[1400px] mx-auto px-6 lg:px-10 py-20 lg:py-28 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center w-full">
                <div class="lg:col-span-7 text-white">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-emerald-500/10 border border-emerald-500/20 backdrop-blur-sm text-[11px] font-semibold mb-6 reveal">
                        <span class="flex items-center gap-1.5">
                            <span class="relative flex w-2 h-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span></span>
                            <span class="text-emerald-100 tracking-wider uppercase">Est. 1954 · Skardu, Gilgit-Baltistan</span>
                        </span>
                    </div>

                    <h1 class="font-black text-[clamp(2.5rem,6vw,5rem)] leading-[1.02] tracking-tight reveal-stagger" style="--delay: 100ms">
                        Where Mountains
                        <br />
                        <span class="bg-gradient-to-r from-amber-300 via-amber-400 to-emerald-300 bg-clip-text text-transparent animate-gradient">Meet Excellence</span>
                    </h1>

                    <p class="mt-7 text-lg lg:text-xl text-stone-300 leading-relaxed max-w-2xl reveal-stagger font-light" style="--delay: 200ms">
                        Government Boys Higher Secondary School No.1 — the oldest and most distinguished boys' institution in Baltistan. Shaping young men of character, intellect, and purpose for over seventy-two years.
                    </p>

                    <div class="mt-10 flex flex-wrap gap-4 reveal-stagger" style="--delay: 300ms">
                        <Link href="/admissions"
                              class="group inline-flex items-center gap-2 bg-gradient-to-br from-amber-400 to-amber-600 hover:from-amber-300 hover:to-amber-500 text-slate-950 px-7 py-4 rounded-full text-sm font-bold shadow-2xl shadow-amber-500/30 hover:shadow-amber-500/50 hover:-translate-y-0.5 transition-all duration-300">
                            Begin Your Application
                            <ArrowRightIcon class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                        </Link>
                        <Link href="/about"
                              class="group inline-flex items-center gap-2 bg-white/5 hover:bg-white/10 border border-white/20 hover:border-white/40 backdrop-blur-xl text-white px-7 py-4 rounded-full text-sm font-medium transition-all duration-300">
                            <PlayCircleIcon class="w-5 h-5 text-amber-400" />
                            Our Story
                        </Link>
                    </div>

                    <!-- Trust badges -->
                    <div class="mt-12 grid grid-cols-2 sm:grid-cols-3 gap-6 reveal-stagger" style="--delay: 400ms">
                        <div>
                            <div class="text-[10px] uppercase tracking-[0.2em] text-stone-500 mb-1">Accredited by</div>
                            <div class="text-sm font-semibold text-stone-200">FBISE · Islamabad</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase tracking-[0.2em] text-stone-500 mb-1">Governed by</div>
                            <div class="text-sm font-semibold text-stone-200">DDO Wazir Zamin Ali</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase tracking-[0.2em] text-stone-500 mb-1">Region</div>
                            <div class="text-sm font-semibold text-stone-200">Gilgit-Baltistan</div>
                        </div>
                    </div>
                </div>

                <!-- Glass hero card -->
                <div class="lg:col-span-5 reveal-stagger" style="--delay: 500ms">
                    <div class="relative">
                        <!-- Main glass card -->
                        <div class="relative bg-white/[0.04] backdrop-blur-2xl border border-white/10 rounded-3xl p-8 shadow-2xl">
                            <div class="flex items-start justify-between mb-6">
                                <div>
                                    <div class="text-[10px] uppercase tracking-[0.2em] text-amber-400 font-semibold mb-1">Live · Academic Year 2025–26</div>
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
                                        <span class="text-sm text-stone-300">Matric Board Pass Rate</span>
                                        <span class="font-bold text-lg text-amber-300 tabular-nums">94.2%</span>
                                    </div>
                                    <div class="h-1.5 bg-white/10 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-amber-400 to-amber-500 rounded-full" style="width: 94.2%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm text-stone-300">A+ Grade Achievers</span>
                                        <span class="font-bold text-lg text-emerald-300 tabular-nums">28 students</span>
                                    </div>
                                    <div class="h-1.5 bg-white/10 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-emerald-400 to-teal-400 rounded-full" style="width: 78%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm text-stone-300">FSc Pass Rate</span>
                                        <span class="font-bold text-lg text-sky-300 tabular-nums">91.8%</span>
                                    </div>
                                    <div class="h-1.5 bg-white/10 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-sky-400 to-indigo-400 rounded-full" style="width: 91.8%"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-7 pt-6 border-t border-white/10 flex items-center justify-between">
                                <div class="text-xs">
                                    <div class="text-stone-400">FBISE Matric 2026</div>
                                    <div class="font-bold text-base text-white mt-0.5">Top 3 in GB · #2 overall</div>
                                </div>
                                <div class="flex items-center gap-1 text-emerald-400 text-sm font-bold">
                                    <ArrowTrendingUpIcon class="w-4 h-4" />
                                    <span>+3.1%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Floating accent card: Trophy -->
                        <div class="absolute -top-6 -left-6 bg-white rounded-2xl shadow-2xl p-4 flex items-center gap-3 animate-float" style="animation-delay: 0.5s">
                            <div class="w-11 h-11 bg-gradient-to-br from-amber-400 to-amber-600 rounded-xl flex items-center justify-center shadow-lg">
                                <TrophyIcon class="w-5 h-5 text-white" />
                            </div>
                            <div>
                                <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Ranked</div>
                                <div class="font-bold text-slate-900 text-sm">Top 3 in GB</div>
                            </div>
                        </div>

                        <!-- Floating accent: Location -->
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

            <!-- Scroll indicator -->
            <div class="absolute bottom-8 left-1/2 -translate-x-1/2 hidden lg:flex flex-col items-center gap-2 text-white/50 animate-float">
                <span class="text-[10px] uppercase tracking-[0.3em]">Explore</span>
                <ArrowDownIcon class="w-4 h-4" />
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════════════
             STATS STRIP — Clean, editorial
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
             DDO MESSAGE — Elegant editorial block
        ═══════════════════════════════════════════════════════════════ -->
        <section class="py-24 bg-stone-50 relative overflow-hidden">
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
                                    <span class="relative text-[6rem] font-black text-white/80 tracking-tighter">WZ</span>
                                </div>
                            </div>
                            <div class="absolute -bottom-5 -right-5 bg-white rounded-2xl shadow-xl px-4 py-3 flex items-center gap-3 max-w-[200px]">
                                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                <div>
                                    <div class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">Serving Since</div>
                                    <div class="text-sm font-bold text-slate-900">2019 · 7 years</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-3 reveal-stagger" style="--delay: 150ms">
                        <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-3">A Message From</div>
                        <h2 class="text-3xl lg:text-4xl font-black text-slate-900 tracking-tight mb-2">Wazir Zamin Ali</h2>
                        <p class="text-sm text-emerald-700 font-semibold uppercase tracking-wider mb-7">District Drawing Officer · Skardu District</p>

                        <div class="space-y-5 text-[15px] leading-[1.75] text-slate-700">
                            <p class="relative pl-5 border-l-2 border-amber-400 italic text-slate-800">
                                "Here in Baltistan, we say — <span class="font-semibold">the mountains do not bow, but neither do our sons</span>. For over seven decades, this school has been a beacon on the banks of the Indus — teaching not just subjects, but the spirit of resilience, faith, and service."
                            </p>
                            <p>
                                As District Drawing Officer for Skardu, it is my duty and my honor to ensure that every young man who walks through these gates receives the finest education our nation can offer. We are committed to modernizing our facilities, supporting our faculty, and opening doors to opportunities — whether in Karachi, Islamabad, or the great universities of the world.
                            </p>
                        </div>

                        <div class="mt-8 pt-6 border-t border-stone-200 flex items-center gap-4">
                            <div class="font-['Brush_Script_MT','cursive'] text-2xl text-slate-800" style="font-family: 'Dancing Script', cursive">Wazir Zamin Ali</div>
                            <span class="w-px h-8 bg-stone-300"></span>
                            <div class="text-xs text-slate-500">
                                <div class="font-semibold text-slate-700">DDO · Skardu</div>
                                <div>Government of Gilgit-Baltistan</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════════════
             FEATURES — Minimal editorial grid
        ═══════════════════════════════════════════════════════════════ -->
        <section class="py-24 bg-white">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="max-w-2xl mb-16 reveal">
                    <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">What Sets Us Apart</div>
                    <h2 class="text-4xl lg:text-5xl font-black text-slate-900 tracking-tight leading-[1.1]">
                        A foundation of trust.<br />
                        <span class="text-slate-400">A future of possibility.</span>
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-px bg-stone-200 border border-stone-200 rounded-3xl overflow-hidden">
                    <div v-for="(f, i) in features" :key="f.title"
                         class="bg-white p-8 lg:p-10 hover:bg-stone-50 transition-colors duration-500 group reveal-stagger"
                         :style="`--delay: ${i * 80}ms`">
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center mb-6 group-hover:bg-emerald-600 group-hover:text-white group-hover:-translate-y-0.5 transition-all duration-500">
                            <component :is="f.icon" class="w-6 h-6" />
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 mb-3 tracking-tight" v-html="f.title"></h3>
                        <p class="text-[14px] text-slate-600 leading-[1.7]" v-html="f.desc"></p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════════════
             ACADEMIC PROGRAMS — Premium tiles
        ═══════════════════════════════════════════════════════════════ -->
        <section class="py-24 bg-gradient-to-b from-white via-stone-50 to-white">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-8 mb-14 reveal">
                    <div class="max-w-2xl">
                        <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">Academic Programs</div>
                        <h2 class="text-4xl lg:text-5xl font-black text-slate-900 tracking-tight leading-[1.1]">
                            Four paths.<br />One foundation.
                        </h2>
                    </div>
                    <Link href="/academics" class="group inline-flex items-center gap-2 text-emerald-700 font-semibold text-sm whitespace-nowrap">
                        Explore Full Curriculum
                        <ArrowRightIcon class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                    </Link>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div v-for="(p, i) in programs" :key="p.level"
                         class="group relative overflow-hidden rounded-3xl bg-white border border-stone-200 p-8 hover:shadow-2xl hover:-translate-y-1 hover:border-emerald-300 transition-all duration-500 reveal-stagger"
                         :style="`--delay: ${i * 100}ms`">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-emerald-100 to-transparent rounded-full -translate-y-12 translate-x-12 group-hover:scale-150 transition-transform duration-700"></div>
                        <div class="relative">
                            <component :is="p.icon" class="w-10 h-10 text-emerald-600 mb-6 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-500" />
                            <div class="text-[11px] uppercase tracking-[0.15em] font-bold text-amber-600">{{ p.range }}</div>
                            <h3 class="text-2xl font-black text-slate-900 mt-1 tracking-tight">{{ p.level }}</h3>
                            <p class="text-[13px] text-slate-600 mt-4 leading-relaxed" v-html="p.desc"></p>
                            <div class="mt-6 inline-flex items-center gap-1 text-emerald-700 text-xs font-semibold group-hover:gap-2 transition-all">
                                Learn more <ArrowRightIcon class="w-3 h-3" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════════════
             TESTIMONIALS — Refined editorial
        ═══════════════════════════════════════════════════════════════ -->
        <section class="py-24 bg-slate-950 text-white relative overflow-hidden">
            <div class="absolute inset-0 opacity-20 pointer-events-none">
                <div class="absolute top-0 left-1/4 w-96 h-96 bg-emerald-600 rounded-full blur-[100px]"></div>
                <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-amber-600 rounded-full blur-[100px]"></div>
            </div>

            <div class="relative max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="text-center max-w-2xl mx-auto mb-16 reveal">
                    <div class="text-[11px] uppercase tracking-[0.2em] text-amber-400 font-bold mb-4">Voices of Our Community</div>
                    <h2 class="text-4xl lg:text-5xl font-black tracking-tight leading-[1.1]">
                        From sons of the mountains,<br />
                        <span class="bg-gradient-to-r from-amber-300 to-emerald-300 bg-clip-text text-transparent">stories of success.</span>
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div v-for="(t, i) in testimonials" :key="t.name"
                         class="group relative bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-8 hover:bg-white/[0.08] hover:border-emerald-400/30 hover:-translate-y-1 transition-all duration-500 reveal-stagger"
                         :style="`--delay: ${i * 120}ms`">
                        <ChatBubbleLeftRightIcon class="w-8 h-8 text-amber-400/60 mb-5" />
                        <p class="text-[15px] leading-[1.7] text-stone-100 italic">"{{ t.quote }}"</p>
                        <div class="mt-7 pt-6 border-t border-white/10 flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center text-white font-bold text-lg">
                                {{ t.name.split(' ').map(n => n[0]).slice(0, 2).join('') }}
                            </div>
                            <div>
                                <div class="font-bold text-white text-sm">{{ t.name }}</div>
                                <div class="text-xs text-stone-400 mt-0.5">{{ t.role }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

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

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <article v-for="(n, i) in news" :key="n.title"
                             class="group cursor-pointer reveal-stagger"
                             :style="`--delay: ${i * 100}ms`">
                        <div class="aspect-[4/3] rounded-2xl bg-gradient-to-br relative overflow-hidden mb-5"
                             :class="i === 0 ? 'from-emerald-700 to-emerald-950' : i === 1 ? 'from-amber-600 to-orange-800' : 'from-sky-700 to-indigo-900'">
                            <div class="absolute inset-0 opacity-20"
                                 :style="`background-image: radial-gradient(circle at ${30 + i*15}% ${45 + i*10}%, rgba(255,255,255,0.4) 0%, transparent 60%)`"></div>
                            <NewspaperIcon class="absolute right-6 bottom-6 w-24 h-24 text-white/20 group-hover:scale-110 transition-transform duration-700" />
                            <div class="absolute top-5 left-5 bg-white/95 text-slate-900 text-[10px] font-bold uppercase tracking-[0.1em] px-3 py-1.5 rounded-full">
                                {{ n.category }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-[11px] text-slate-500 font-medium mb-2">
                            <CalendarDaysIcon class="w-3.5 h-3.5" />
                            <span>{{ n.date }}</span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 leading-snug mb-3 group-hover:text-emerald-700 transition-colors duration-300" v-html="n.title"></h3>
                        <p class="text-sm text-slate-600 leading-relaxed line-clamp-2" v-html="n.excerpt"></p>
                        <div class="inline-flex items-center gap-1 mt-4 text-emerald-700 text-xs font-semibold group-hover:gap-2 transition-all">
                            Read more <ArrowRightIcon class="w-3 h-3" />
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════════════════════════════════════
             CTA
        ═══════════════════════════════════════════════════════════════ -->
        <section class="relative overflow-hidden py-28 bg-gradient-to-br from-emerald-900 via-slate-900 to-emerald-950 text-white">
            <div class="absolute inset-0 opacity-30 pointer-events-none">
                <div class="absolute top-0 left-0 w-96 h-96 bg-amber-500 rounded-full blur-[140px] animate-float"></div>
                <div class="absolute bottom-0 right-0 w-[30rem] h-[30rem] bg-emerald-500 rounded-full blur-[140px] animate-float" style="animation-delay: 3s"></div>
            </div>

            <div class="relative max-w-4xl mx-auto px-6 lg:px-10 text-center reveal">
                <div class="text-[11px] uppercase tracking-[0.3em] text-amber-400 font-bold mb-6">Admissions 2026–27 · Now Open</div>
                <h2 class="text-4xl lg:text-6xl font-black leading-[1.05] tracking-tight">
                    Join the legacy.<br />
                    <span class="bg-gradient-to-r from-amber-300 via-amber-400 to-emerald-300 bg-clip-text text-transparent animate-gradient">Write your chapter.</span>
                </h2>
                <p class="mt-7 text-lg lg:text-xl text-stone-300 max-w-2xl mx-auto font-light leading-relaxed">
                    Applications close May 31, 2026. Whether in Matric, FSc Pre-Medical, Pre-Engineering, or ICS — your future begins here, in Skardu.
                </p>
                <div class="mt-10 flex flex-wrap justify-center gap-4">
                    <Link href="/admissions"
                          class="group inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-slate-950 px-8 py-4 rounded-full font-bold text-sm shadow-2xl shadow-amber-500/30 hover:shadow-amber-500/50 hover:-translate-y-0.5 transition-all duration-300">
                        Start Your Application
                        <ArrowRightIcon class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                    </Link>
                    <Link href="/contact"
                          class="inline-flex items-center gap-2 bg-white/5 hover:bg-white/10 border border-white/20 text-white px-8 py-4 rounded-full font-medium text-sm transition-all duration-300">
                        Visit Our Campus
                    </Link>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
