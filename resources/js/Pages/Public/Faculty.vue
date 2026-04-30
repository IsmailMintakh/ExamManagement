<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { AcademicCapIcon, StarIcon, EnvelopeIcon } from '@heroicons/vue/24/outline'

const faculty = [
    { name: 'Muhammad Ismail Khan', role: 'Principal', subject: 'Mathematics', exp: 22, qual: 'M.Phil Mathematics, UoK', gradient: 'from-emerald-600 to-emerald-900' },
    { name: 'Prof. Ghulam Hussain Baltistani', role: 'Vice Principal · Academics', subject: 'Urdu Literature', exp: 28, qual: 'M.A Urdu', gradient: 'from-amber-600 to-amber-900' },
    { name: 'Syed Ali Mehdi', role: 'Dean of Student Affairs', subject: 'Physics', exp: 24, qual: 'M.Sc Physics', gradient: 'from-sky-600 to-indigo-900' },
    { name: 'Dr. Wazir Hassan', role: 'Head · Science Department', subject: 'Chemistry', exp: 17, qual: 'Ph.D Chemistry, QAU', gradient: 'from-violet-600 to-purple-900' },
    { name: 'Mr. Sultan Mehdi Abbasi', role: 'Senior Teacher', subject: 'Biology', exp: 15, qual: 'M.Sc Biology', gradient: 'from-rose-600 to-pink-900' },
    { name: 'Mr. Muhammad Abbas', role: 'Head · Computer Science', subject: 'Computer Science', exp: 11, qual: 'M.S Computer Science', gradient: 'from-indigo-600 to-blue-900' },
    { name: 'Mr. Ghulam Mehdi', role: 'Senior Teacher', subject: 'English Language', exp: 19, qual: 'M.A English Literature', gradient: 'from-teal-600 to-emerald-900' },
    { name: 'Moulvi Syed Ahmed', role: 'Head · Islamiyat', subject: 'Islamiyat &amp; Arabic', exp: 26, qual: 'Shahadat-ul-Aalmia · M.A', gradient: 'from-green-700 to-teal-900' },
]

const deptStats = [
    { name: 'Mathematics &amp; Sciences', count: 22 },
    { name: 'Languages (Eng / Urdu / Arabic)', count: 14 },
    { name: 'Islamiyat &amp; Pak Studies', count: 8 },
    { name: 'Computer Science', count: 6 },
    { name: 'Social Sciences', count: 9 },
    { name: 'Physical Education &amp; Arts', count: 9 },
]
</script>

<template>
    <Head title="Faculty — GBHSS No.1 Skardu" />
    <PublicLayout>
        <section class="relative bg-slate-950 text-white py-28 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-emerald-950 to-slate-900"></div>
            <div class="absolute top-0 right-0 w-[40rem] h-[40rem] bg-emerald-600/20 rounded-full blur-[140px] animate-float"></div>
            <div class="relative max-w-5xl mx-auto px-6 lg:px-10 text-center reveal">
                <div class="text-[11px] uppercase tracking-[0.25em] text-amber-400 font-semibold mb-5">Our Educators</div>
                <h1 class="text-5xl lg:text-7xl font-black tracking-tight leading-[1.0]">
                    Sixty-eight teachers.<br />
                    <span class="bg-gradient-to-r from-amber-300 to-emerald-300 bg-clip-text text-transparent">One mission.</span>
                </h1>
                <p class="mt-7 text-lg text-stone-300 max-w-2xl mx-auto font-light">Every member of our faculty is a mentor, a guide, and a friend.</p>
            </div>
        </section>

        <!-- Department stats -->
        <section class="py-20 bg-white border-b border-stone-100">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="mb-10 reveal">
                    <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">By Department</div>
                    <h2 class="text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">Strength of our faculty.</h2>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-px bg-stone-200 border border-stone-200 rounded-3xl overflow-hidden">
                    <div v-for="(d, i) in deptStats" :key="d.name"
                         class="bg-white p-8 hover:bg-stone-50 transition-colors duration-500 reveal-stagger"
                         :style="`--delay: ${i * 60}ms`">
                        <div class="text-4xl lg:text-5xl font-black text-slate-900 tabular-nums tracking-tight">{{ d.count }}</div>
                        <div class="text-xs text-slate-600 mt-2 font-semibold uppercase tracking-wider" v-html="d.name"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Featured faculty -->
        <section class="py-24 bg-stone-50">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="text-center mb-14 reveal">
                    <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">Featured</div>
                    <h2 class="text-4xl lg:text-5xl font-black text-slate-900 tracking-tight">Leading educators.</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div v-for="(f, i) in faculty" :key="f.name"
                         class="group reveal-stagger"
                         :style="`--delay: ${i * 60}ms`">
                        <div class="relative aspect-[3/4] rounded-3xl bg-gradient-to-br overflow-hidden mb-4 shadow-lg"
                             :class="f.gradient">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent"></div>
                            <span class="absolute inset-0 flex items-center justify-center text-[4.5rem] font-black text-white/85 tracking-tighter group-hover:scale-110 transition-transform duration-700">
                                {{ f.name.split(' ').map(n => n[0]).filter(c => /[A-Z]/.test(c)).slice(0, 2).join('') }}
                            </span>
                            <div class="absolute bottom-0 left-0 right-0 p-5 text-white">
                                <div class="text-[10px] uppercase tracking-[0.15em] text-amber-300 font-bold" v-html="f.subject"></div>
                            </div>
                        </div>
                        <h3 class="font-bold text-slate-900 text-base tracking-tight" v-html="f.name"></h3>
                        <p class="text-xs text-emerald-700 font-semibold mt-0.5" v-html="f.role"></p>
                        <div class="mt-3 pt-3 border-t border-stone-200 space-y-1 text-xs text-slate-600">
                            <div class="flex items-center gap-2"><AcademicCapIcon class="w-3.5 h-3.5 flex-shrink-0 text-emerald-600" /><span v-html="f.qual"></span></div>
                            <div class="flex items-center gap-2"><StarIcon class="w-3.5 h-3.5 flex-shrink-0 text-amber-500" /><span>{{ f.exp }}+ years experience</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
