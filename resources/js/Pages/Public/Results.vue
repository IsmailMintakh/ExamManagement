<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { TrophyIcon, ChartBarIcon, ArrowTrendingUpIcon } from '@heroicons/vue/24/outline'

const overallStats = [
    { label: 'Matric Pass Rate', value: '94.2%', change: '+3.1%', color: 'emerald' },
    { label: 'A+ Grades', value: '28', change: '+6', color: 'amber' },
    { label: 'GB Ranking', value: '#2', change: '6th year', color: 'rose' },
    { label: 'FSc Pass Rate', value: '91.8%', change: '+2.4%', color: 'sky' },
]

const toppers = [
    { rank: 1, name: 'Muhammad Abbas Ali', class: 'Matric Science · Class 10', marks: '1087/1100', percent: '98.8%' },
    { rank: 2, name: 'Sultan Mehdi Abbasi', class: 'Matric Science · Class 10', marks: '1082/1100', percent: '98.4%' },
    { rank: 3, name: 'Ali Raza Baltistani', class: 'Matric Science · Class 10', marks: '1078/1100', percent: '98.0%' },
    { rank: 4, name: 'Ghulam Hussain', class: 'FSc Pre-Medical · Class 12', marks: '1068/1100', percent: '97.1%' },
    { rank: 5, name: 'Wazir Ahmed Khan', class: 'FSc Pre-Engineering · Class 12', marks: '1061/1100', percent: '96.5%' },
    { rank: 6, name: 'Syed Mehdi Hussain', class: 'Matric Arts · Class 10', marks: '1054/1100', percent: '95.8%' },
    { rank: 7, name: 'Muhammad Ismail', class: 'ICS · Class 12', marks: '1048/1100', percent: '95.3%' },
    { rank: 8, name: 'Ahmed Hassan', class: 'FSc Pre-Medical · Class 12', marks: '1041/1100', percent: '94.6%' },
]

const subjectStats = [
    { subject: 'English', pass: 93.2, a_plus: 11 },
    { subject: 'Urdu', pass: 96.8, a_plus: 19 },
    { subject: 'Islamiyat', pass: 98.4, a_plus: 24 },
    { subject: 'Mathematics', pass: 88.6, a_plus: 14 },
    { subject: 'Physics', pass: 91.2, a_plus: 16 },
    { subject: 'Chemistry', pass: 90.4, a_plus: 13 },
    { subject: 'Biology', pass: 92.8, a_plus: 18 },
    { subject: 'Pakistan Studies', pass: 97.2, a_plus: 22 },
    { subject: 'Computer Science', pass: 93.6, a_plus: 12 },
]

const yearlyTrend = [
    { year: '2021', pass: 82.4 },
    { year: '2022', pass: 86.2 },
    { year: '2023', pass: 89.1 },
    { year: '2024', pass: 90.8 },
    { year: '2025', pass: 91.1 },
    { year: '2026', pass: 94.2 },
]
</script>

<template>
    <Head title="Board Results — GBHSS No.1 Skardu" />
    <PublicLayout>
        <section class="relative bg-slate-950 text-white py-28 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-emerald-950 to-slate-900"></div>
            <div class="absolute top-0 right-0 w-[40rem] h-[40rem] bg-amber-500/15 rounded-full blur-[140px] animate-float"></div>
            <div class="relative max-w-5xl mx-auto px-6 lg:px-10 text-center reveal">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-amber-500/10 border border-amber-500/20 text-[11px] font-semibold mb-5">
                    <TrophyIcon class="w-3.5 h-3.5 text-amber-300" />
                    <span class="text-amber-300 uppercase tracking-[0.2em]">Ranked #2 in Gilgit-Baltistan · 6 Years Running</span>
                </div>
                <h1 class="text-5xl lg:text-7xl font-black tracking-tight leading-[1.0]">
                    FBISE Results<br />
                    <span class="bg-gradient-to-r from-amber-300 to-emerald-300 bg-clip-text text-transparent">2026.</span>
                </h1>
                <p class="mt-7 text-lg text-stone-300 max-w-2xl mx-auto font-light">Federal Board · Matric &amp; Intermediate results from the class of 2026.</p>
            </div>
        </section>

        <!-- Overall stats -->
        <section class="py-14 bg-white -mt-10 relative z-10">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 reveal">
                    <div v-for="(s, i) in overallStats" :key="s.label"
                         class="bg-white rounded-3xl p-8 shadow-xl border border-stone-100 text-center hover:shadow-2xl hover:-translate-y-1 transition-all duration-500 reveal-stagger"
                         :style="`--delay: ${i * 80}ms`">
                        <div class="text-5xl lg:text-6xl font-black tabular-nums tracking-tight"
                             :class="{
                                 'text-emerald-600': s.color === 'emerald',
                                 'text-amber-600': s.color === 'amber',
                                 'text-rose-600': s.color === 'rose',
                                 'text-sky-600': s.color === 'sky',
                             }">{{ s.value }}</div>
                        <div class="text-xs text-slate-600 font-semibold uppercase tracking-wider mt-2">{{ s.label }}</div>
                        <div class="text-xs text-emerald-600 font-bold mt-3 inline-flex items-center gap-1">
                            <ArrowTrendingUpIcon class="w-3.5 h-3.5" /> {{ s.change }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Toppers -->
        <section class="py-24 bg-stone-50">
            <div class="max-w-6xl mx-auto px-6 lg:px-10">
                <div class="mb-14 text-center reveal">
                    <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">Hall of Fame</div>
                    <h2 class="text-4xl lg:text-5xl font-black text-slate-900 tracking-tight">Top performers · 2026.</h2>
                </div>

                <!-- Podium -->
                <div class="grid grid-cols-3 gap-4 max-w-3xl mx-auto mb-10 items-end">
                    <div v-for="(t, i) in [toppers[1], toppers[0], toppers[2]]" :key="t.rank"
                         class="text-center rounded-3xl overflow-hidden shadow-xl border-2 transition-all duration-500 hover:-translate-y-1 reveal-stagger"
                         :class="i === 1 ? 'bg-gradient-to-b from-amber-50 to-white border-amber-300' : 'bg-white border-stone-200'"
                         :style="`--delay: ${i * 120}ms; min-height: ${i === 1 ? '340px' : '290px'}`">
                        <div class="h-2.5" :class="i === 0 ? 'bg-slate-400' : i === 1 ? 'bg-amber-500' : 'bg-orange-600'"></div>
                        <div class="p-6">
                            <div class="mx-auto mb-4 w-16 h-16 rounded-full flex items-center justify-center font-black text-xl shadow-lg"
                                 :class="i === 0 ? 'bg-slate-200 text-slate-700' : i === 1 ? 'bg-gradient-to-br from-amber-400 to-amber-600 text-white' : 'bg-gradient-to-br from-orange-400 to-orange-600 text-white'">
                                #{{ t.rank }}
                            </div>
                            <TrophyIcon v-if="i === 1" class="w-8 h-8 mx-auto text-amber-500 mb-3" />
                            <h3 class="font-bold text-slate-900 text-sm leading-tight">{{ t.name }}</h3>
                            <p class="text-xs text-slate-500 mt-1">{{ t.class }}</p>
                            <div class="mt-4 text-3xl font-black text-emerald-600 tabular-nums">{{ t.percent }}</div>
                            <div class="text-xs text-slate-500 mt-1 font-mono">{{ t.marks }}</div>
                        </div>
                    </div>
                </div>

                <!-- Table for rest -->
                <div class="reveal bg-white rounded-3xl shadow-sm border border-stone-100 overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-stone-50 text-[11px] uppercase tracking-[0.1em] text-slate-500 border-b border-stone-200">
                            <tr><th class="text-left p-5 font-semibold">Rank</th><th class="text-left p-5 font-semibold">Name</th><th class="text-left p-5 font-semibold hidden sm:table-cell">Program</th><th class="text-right p-5 font-semibold">Marks</th><th class="text-right p-5 font-semibold">%</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in toppers.slice(3)" :key="t.rank" class="border-t border-stone-50 hover:bg-stone-50 transition-colors">
                                <td class="p-5"><span class="inline-flex w-9 h-9 items-center justify-center rounded-full bg-slate-100 text-slate-700 font-bold text-sm">{{ t.rank }}</span></td>
                                <td class="p-5 font-semibold text-slate-900 text-sm">{{ t.name }}</td>
                                <td class="p-5 text-sm text-slate-500 hidden sm:table-cell">{{ t.class }}</td>
                                <td class="p-5 text-right text-sm font-mono text-slate-600">{{ t.marks }}</td>
                                <td class="p-5 text-right font-bold text-emerald-600 tabular-nums">{{ t.percent }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Subject & Trend -->
        <section class="py-24 bg-white">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10 grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="reveal bg-white rounded-3xl border border-stone-200 p-10 shadow-sm">
                    <ChartBarIcon class="w-10 h-10 text-emerald-700 mb-5" />
                    <h3 class="text-2xl font-black text-slate-900 tracking-tight mb-6">Subject-wise pass rates</h3>
                    <div class="space-y-4">
                        <div v-for="s in subjectStats" :key="s.subject">
                            <div class="flex justify-between items-center text-sm mb-1.5">
                                <span class="font-medium text-slate-700">{{ s.subject }}</span>
                                <div class="flex items-center gap-3">
                                    <span class="text-xs text-amber-600 font-semibold">{{ s.a_plus }} A+</span>
                                    <span class="font-bold text-emerald-600 tabular-nums">{{ s.pass }}%</span>
                                </div>
                            </div>
                            <div class="h-2 bg-stone-100 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-full transition-all duration-1000" :style="`width: ${s.pass}%`"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="reveal-stagger bg-white rounded-3xl border border-stone-200 p-10 shadow-sm" style="--delay: 150ms">
                    <ArrowTrendingUpIcon class="w-10 h-10 text-amber-600 mb-5" />
                    <h3 class="text-2xl font-black text-slate-900 tracking-tight mb-6">Six-year progression</h3>
                    <div class="flex items-end justify-between gap-2.5 h-64 pt-6">
                        <div v-for="y in yearlyTrend" :key="y.year" class="flex-1 flex flex-col items-center gap-2">
                            <div class="text-xs font-bold text-slate-700">{{ y.pass }}%</div>
                            <div class="w-full bg-gradient-to-t from-emerald-700 via-emerald-500 to-emerald-400 rounded-t-xl transition-all duration-700 hover:from-amber-700 hover:via-amber-500 hover:to-amber-400" :style="`height: ${(y.pass / 100) * 220}px`"></div>
                            <div class="text-xs text-slate-500 font-semibold">{{ y.year }}</div>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 text-center mt-5 leading-relaxed">Pass rate up <b class="text-emerald-700">11.8 points</b> since 2021.</p>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
