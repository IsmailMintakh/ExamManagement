<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import {
    BookOpenIcon, CalculatorIcon, BeakerIcon, GlobeAltIcon,
    ComputerDesktopIcon, LanguageIcon, HeartIcon,
    CalendarDaysIcon, ChartBarIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const subjects = [
    { icon: LanguageIcon, name: 'English', desc: 'Compulsory · Language &amp; Literature.' },
    { icon: BookOpenIcon, name: 'Urdu', desc: 'Compulsory · Grammar, poetry, and prose.' },
    { icon: HeartIcon, name: 'Islamiyat', desc: 'Compulsory · Faith and ethics.' },
    { icon: CalculatorIcon, name: 'Mathematics', desc: 'Algebra, geometry, trigonometry.' },
    { icon: BeakerIcon, name: 'Physics', desc: 'Pre-Engineering &amp; Pre-Medical.' },
    { icon: BeakerIcon, name: 'Chemistry', desc: 'Pre-Engineering &amp; Pre-Medical.' },
    { icon: BeakerIcon, name: 'Biology', desc: 'Pre-Medical stream.' },
    { icon: GlobeAltIcon, name: 'Pakistan Studies', desc: 'National history &amp; constitution.' },
    { icon: ComputerDesktopIcon, name: 'Computer Science', desc: 'Programming &amp; digital literacy.' },
    { icon: LanguageIcon, name: 'Balti (Optional)', desc: 'Elective · Our regional heritage.' },
]

const grades = [
    { grade: 'A1', range: '80–100', label: 'Outstanding', color: 'bg-emerald-500' },
    { grade: 'A', range: '70–79', label: 'Excellent', color: 'bg-emerald-400' },
    { grade: 'B', range: '60–69', label: 'Very Good', color: 'bg-sky-500' },
    { grade: 'C', range: '50–59', label: 'Good', color: 'bg-amber-500' },
    { grade: 'D', range: '40–49', label: 'Satisfactory', color: 'bg-orange-500' },
    { grade: 'E', range: '33–39', label: 'Pass', color: 'bg-rose-400' },
    { grade: 'F', range: '0–32', label: 'Fail', color: 'bg-rose-600' },
]

const calendar = [
    { term: '1st Term', period: 'April – July' },
    { term: 'Summer Break', period: '1–15 August' },
    { term: '2nd Term', period: 'September – December' },
    { term: 'Winter Break', period: '20 Dec – 15 Jan' },
    { term: 'Final Term', period: 'February – March' },
    { term: 'New Session', period: 'April 1st' },
]
</script>

<template>
    <Head title="Academics — GBHSS No.1 Skardu" />
    <PublicLayout>
        <section class="relative bg-slate-950 text-white py-28 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-emerald-950 to-slate-900"></div>
            <div class="absolute top-0 right-0 w-[40rem] h-[40rem] bg-emerald-600/20 rounded-full blur-[140px] animate-float"></div>
            <div class="relative max-w-5xl mx-auto px-6 lg:px-10 text-center reveal">
                <div class="text-[11px] uppercase tracking-[0.25em] text-amber-400 font-semibold mb-5">Curriculum</div>
                <h1 class="text-5xl lg:text-7xl font-black tracking-tight leading-[1.0]">
                    Rigor in every<br />
                    <span class="bg-gradient-to-r from-amber-300 to-emerald-300 bg-clip-text text-transparent">subject.</span>
                </h1>
                <p class="mt-7 text-lg text-stone-300 max-w-2xl mx-auto font-light">Federal Board (FBISE) affiliated — Urdu &amp; English medium instruction.</p>
            </div>
        </section>

        <!-- Subjects -->
        <section class="py-24 bg-white">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="mb-14 reveal">
                    <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">Subjects Offered</div>
                    <h2 class="text-4xl lg:text-5xl font-black text-slate-900 tracking-tight leading-[1.1]">Ten subjects.<br /><span class="text-slate-400">Endless futures.</span></h2>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-px bg-stone-200 border border-stone-200 rounded-3xl overflow-hidden">
                    <div v-for="(s, i) in subjects" :key="s.name"
                         class="bg-white p-6 hover:bg-stone-50 transition-colors duration-500 group reveal-stagger"
                         :style="`--delay: ${i * 40}ms`">
                        <component :is="s.icon" class="w-8 h-8 text-emerald-600 mb-4 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-500" />
                        <h3 class="font-bold text-slate-900 text-sm tracking-tight">{{ s.name }}</h3>
                        <p class="text-[11px] text-slate-500 mt-1.5 leading-relaxed" v-html="s.desc"></p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Grading -->
        <section class="py-24 bg-stone-50">
            <div class="max-w-4xl mx-auto px-6 lg:px-10">
                <div class="mb-14 text-center reveal">
                    <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">FBISE Grading Scale</div>
                    <h2 class="text-4xl lg:text-5xl font-black text-slate-900 tracking-tight">How we evaluate.</h2>
                </div>
                <div class="bg-white rounded-3xl shadow-sm border border-stone-100 overflow-hidden reveal">
                    <div v-for="(g, i) in grades" :key="g.grade"
                         class="flex items-center gap-5 p-5 border-b border-stone-50 last:border-0 hover:bg-stone-50 transition-colors duration-300">
                        <div :class="g.color" class="w-14 h-14 rounded-2xl flex items-center justify-center text-white font-black text-xl shadow-md">{{ g.grade }}</div>
                        <div class="flex-1">
                            <div class="font-bold text-slate-900 text-base">{{ g.label }}</div>
                            <div class="text-xs text-slate-500 font-mono mt-0.5">{{ g.range }}</div>
                        </div>
                        <div class="hidden md:block w-60 bg-stone-100 h-2 rounded-full overflow-hidden">
                            <div :class="g.color" class="h-full rounded-full transition-all duration-700" :style="`width: ${(7-i)/7*100}%`"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Academic Calendar -->
        <section class="py-24 bg-white">
            <div class="max-w-6xl mx-auto px-6 lg:px-10">
                <div class="mb-14 reveal">
                    <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">Academic Calendar</div>
                    <h2 class="text-4xl lg:text-5xl font-black text-slate-900 tracking-tight leading-[1.1]">2025–26 Session.</h2>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-5">
                    <div v-for="(c, i) in calendar" :key="c.term"
                         class="p-8 rounded-3xl bg-gradient-to-br border border-stone-100 hover:shadow-xl hover:-translate-y-1 transition-all duration-500 reveal-stagger group"
                         :class="i % 2 === 0 ? 'from-emerald-50 to-white' : 'from-amber-50 to-white'"
                         :style="`--delay: ${i * 80}ms`">
                        <CalendarDaysIcon class="w-8 h-8 text-emerald-700 mb-4 group-hover:rotate-6 transition-transform duration-500" />
                        <h3 class="font-black text-xl text-slate-900 tracking-tight">{{ c.term }}</h3>
                        <p class="text-sm text-slate-600 mt-2 font-medium">{{ c.period }}</p>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
