<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import {
    DocumentCheckIcon, ClipboardDocumentCheckIcon, CheckCircleIcon,
    CalendarDaysIcon, AcademicCapIcon, ArrowRightIcon,
    DocumentTextIcon, IdentificationIcon, ClockIcon, BanknotesIcon,
} from '@heroicons/vue/24/outline'

const steps = [
    { num: '01', title: 'Visit the School Office', desc: 'Collect the admission form in person from the school office at College Road, Skardu — or download it from this website. Submit along with ₨300 application fee.', icon: DocumentTextIcon },
    { num: '02', title: 'Document Submission', desc: 'Submit all required documents at the office between 9:00 AM – 2:00 PM on any working day before the deadline (31 May 2026).', icon: ClipboardDocumentCheckIcon },
    { num: '03', title: 'Entry Test', desc: 'Written test covering English, Urdu, Mathematics, and General Knowledge. For FSc candidates, an additional subject-specific test is held.', icon: AcademicCapIcon },
    { num: '04', title: 'Interview', desc: 'A short interview with the Principal and senior faculty — to understand the student\'s interests, strengths, and aspirations.', icon: IdentificationIcon },
    { num: '05', title: 'Confirmation &amp; Fee', desc: 'Selected students receive admission confirmation via SMS and the school notice board. Deposit admission fees to secure your seat.', icon: CheckCircleIcon },
]

const documents = [
    'Birth certificate (original + 2 photocopies)',
    'B-Form or Child Registration Certificate',
    "Father's CNIC (original + 2 copies)",
    'Previous school leaving certificate (for transfers)',
    'Previous result card / marksheet',
    'Character certificate from previous school',
    '4 recent passport-sized photographs',
    'Domicile certificate of Gilgit-Baltistan',
    'Medical fitness certificate (from a government doctor)',
]

const dates = [
    { event: 'Application Opens', date: 'April 1, 2026', status: 'open' },
    { event: 'Last Date for Submission', date: 'May 31, 2026', status: 'upcoming' },
    { event: 'Entry Test', date: 'June 10–12, 2026', status: 'upcoming' },
    { event: 'Interview Schedule', date: 'June 15–17, 2026', status: 'upcoming' },
    { event: 'Merit List Announced', date: 'June 25, 2026', status: 'upcoming' },
    { event: 'Admission Fees Deadline', date: 'July 10, 2026', status: 'upcoming' },
    { event: 'Classes Begin', date: 'August 1, 2026', status: 'upcoming' },
]

const fees = [
    { level: 'Class 9 (Matric)', tuition: 'Free', misc: '₨400/month', total: '₨400/month' },
    { level: 'Class 10 (Matric)', tuition: 'Free', misc: '₨400/month', total: '₨400/month' },
    { level: 'Class 11 (FSc / FA / ICS)', tuition: '₨600/month', misc: '₨500/month', total: '₨1,100/month' },
    { level: 'Class 12 (FSc / FA / ICS)', tuition: '₨600/month', misc: '₨500/month', total: '₨1,100/month' },
]
</script>

<template>
    <Head title="Admissions — GBHSS No.1 Skardu" />
    <PublicLayout>
        <section class="relative bg-slate-950 text-white py-28 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-emerald-950 to-slate-900"></div>
            <div class="absolute top-0 right-0 w-[40rem] h-[40rem] bg-amber-500/15 rounded-full blur-[140px] animate-float"></div>
            <div class="relative max-w-5xl mx-auto px-6 lg:px-10 text-center reveal">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-amber-500/10 border border-amber-500/20 text-[11px] font-semibold mb-5">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                    <span class="text-amber-300 uppercase tracking-[0.2em]">Applications Open · 2026–27</span>
                </div>
                <h1 class="text-5xl lg:text-7xl font-black tracking-tight leading-[1.0]">
                    Begin your<br />
                    <span class="bg-gradient-to-r from-amber-300 to-emerald-300 bg-clip-text text-transparent">journey.</span>
                </h1>
                <p class="mt-7 text-lg text-stone-300 max-w-2xl mx-auto font-light">Five steps. One of the finest schools in Gilgit-Baltistan.</p>
            </div>
        </section>

        <!-- Steps -->
        <section class="py-24 bg-white">
            <div class="max-w-5xl mx-auto px-6 lg:px-10">
                <div class="mb-16 reveal">
                    <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">The Process</div>
                    <h2 class="text-4xl lg:text-5xl font-black text-slate-900 tracking-tight">Five steps to enrollment.</h2>
                </div>
                <div class="space-y-5">
                    <div v-for="(s, i) in steps" :key="s.num"
                         class="group bg-white rounded-3xl p-7 lg:p-9 border border-stone-200 hover:border-emerald-300 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-500 flex flex-col md:flex-row gap-6 items-start reveal-stagger"
                         :style="`--delay: ${i * 80}ms`">
                        <div class="flex items-center gap-5">
                            <div class="text-6xl font-black text-emerald-100 group-hover:text-emerald-300 transition-colors duration-500">{{ s.num }}</div>
                            <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white group-hover:rotate-6 transition-all duration-500">
                                <component :is="s.icon" class="w-7 h-7" />
                            </div>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-xl font-bold text-slate-900 mb-2 tracking-tight">{{ s.title }}</h3>
                            <p class="text-slate-600 leading-[1.7]" v-html="s.desc"></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Documents + Fees -->
        <section class="py-24 bg-stone-50">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10 grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="reveal bg-white rounded-3xl shadow-sm border border-stone-100 p-10">
                    <DocumentCheckIcon class="w-10 h-10 text-emerald-700 mb-6" />
                    <h3 class="text-2xl font-black text-slate-900 tracking-tight mb-6">Required Documents</h3>
                    <ul class="space-y-3.5">
                        <li v-for="d in documents" :key="d" class="flex gap-3 items-start text-sm text-slate-700 leading-relaxed">
                            <CheckCircleIcon class="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" />
                            <span>{{ d }}</span>
                        </li>
                    </ul>
                </div>
                <div class="reveal-stagger bg-white rounded-3xl shadow-sm border border-stone-100 p-10" style="--delay: 150ms">
                    <BanknotesIcon class="w-10 h-10 text-amber-600 mb-6" />
                    <h3 class="text-2xl font-black text-slate-900 tracking-tight mb-6">Fee Structure</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-[11px] uppercase tracking-[0.1em] text-slate-500 border-b border-stone-200">
                                <tr><th class="text-left py-3 font-semibold">Level</th><th class="text-right py-3 font-semibold">Tuition</th><th class="text-right py-3 font-semibold">Misc.</th><th class="text-right py-3 font-semibold">Total</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="f in fees" :key="f.level" class="border-b border-stone-100 last:border-0">
                                    <td class="py-4 font-semibold text-slate-900">{{ f.level }}</td>
                                    <td class="py-4 text-right text-slate-600">{{ f.tuition }}</td>
                                    <td class="py-4 text-right text-slate-600">{{ f.misc }}</td>
                                    <td class="py-4 text-right font-bold text-emerald-700">{{ f.total }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-slate-500 mt-6 leading-relaxed">💡 <b>Scholarships available</b> for top-performing students and children of shuhada. Contact the school office for details.</p>
                </div>
            </div>
        </section>

        <!-- Timeline -->
        <section class="py-24 bg-white">
            <div class="max-w-3xl mx-auto px-6 lg:px-10">
                <div class="mb-14 text-center reveal">
                    <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">Important Dates</div>
                    <h2 class="text-4xl lg:text-5xl font-black text-slate-900 tracking-tight">Admissions 2026–27.</h2>
                </div>
                <div class="bg-white rounded-3xl shadow-sm border border-stone-100 overflow-hidden reveal">
                    <div v-for="(d, i) in dates" :key="d.event"
                         class="flex items-center gap-5 p-6 border-b border-stone-50 last:border-0 hover:bg-stone-50 transition-colors duration-300">
                        <div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0 transition-colors duration-300"
                             :class="d.status === 'open' ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-slate-400'">
                            <ClockIcon class="w-5 h-5" />
                        </div>
                        <div class="flex-1">
                            <div class="font-bold text-slate-900">{{ d.event }}</div>
                            <div class="text-sm text-slate-500 flex items-center gap-1 mt-0.5"><CalendarDaysIcon class="w-3.5 h-3.5" /> {{ d.date }}</div>
                        </div>
                        <span v-if="d.status === 'open'" class="px-3 py-1 bg-emerald-500 text-white text-[10px] font-bold uppercase tracking-[0.1em] rounded-full animate-pulse">Live Now</span>
                        <span v-else class="px-3 py-1 bg-stone-100 text-slate-500 text-[10px] font-bold uppercase tracking-[0.1em] rounded-full">Upcoming</span>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
