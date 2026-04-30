<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import {
    AcademicCapIcon, EyeIcon, FlagIcon, HeartIcon, LightBulbIcon,
    ShieldCheckIcon, UserGroupIcon, CheckCircleIcon, SparklesIcon,
    BuildingLibraryIcon,
} from '@heroicons/vue/24/outline'

const values = [
    { icon: ShieldCheckIcon, title: 'Integrity', desc: 'Honesty in word and deed — in the classroom, on the field, and in life.' },
    { icon: LightBulbIcon, title: 'Innovation', desc: 'Balancing timeless traditions with modern methods of learning.' },
    { icon: HeartIcon, title: 'Service', desc: 'Our sons serve Baltistan, Pakistan, and the Ummah — with humility.' },
    { icon: UserGroupIcon, title: 'Brotherhood', desc: 'Boys who walk these halls become brothers for life.' },
]

const milestones = [
    { year: '1954', title: 'Foundation Laid', desc: 'Established as Government Middle School, Skardu by the Ministry of Education, shortly after the inclusion of Baltistan into Pakistan. Initial enrollment: 47 students.' },
    { year: '1967', title: 'Upgraded to High School', desc: 'Matriculation classes (9th and 10th) introduced. Affiliation with the Federal Board of Intermediate and Secondary Education (FBISE) secured.' },
    { year: '1981', title: 'Higher Secondary Status', desc: 'FSc Pre-Medical and Pre-Engineering introduced. The school became the region\'s premier institution for post-matric education.' },
    { year: '1998', title: 'Science Block Inaugurated', desc: 'Dedicated physics, chemistry, and biology laboratories built with federal grants. Over 200 alumni would go on to become medical doctors and engineers.' },
    { year: '2014', title: 'Computer Science Launched', desc: 'ICS (Intermediate in Computer Science) stream added. Computer lab with 40 workstations and satellite internet installed — a first in Baltistan.' },
    { year: '2022', title: 'Golden Jubilee of Excellence', desc: 'Celebrated 50 years since upgrading to higher secondary. New library wing and sports complex inaugurated by the Chief Secretary, Gilgit-Baltistan.' },
    { year: '2026', title: 'Present Day', desc: '1,248 students. 68 qualified teachers. Ranked #2 in Gilgit-Baltistan by FBISE Matric results. Seventy-two years strong.' },
]

const leadership = [
    { name: 'Wazir Zamin Ali', role: 'District Drawing Officer · Skardu', bio: 'Appointed 2019. Over 22 years in education administration. Leads all district-wide initiatives and academic policy for government schools in Skardu.', initials: 'WZ', color: 'from-emerald-600 to-emerald-900' },
    { name: 'Muhammad Ismail Khan', role: 'Principal · GBHSS No.1 Skardu', bio: 'M.Phil Mathematics, University of Karachi. 18 years as senior math teacher, now serving third year as principal. Known for his open-door policy.', initials: 'MI', color: 'from-amber-600 to-amber-900' },
    { name: 'Prof. Ghulam Hussain Baltistani', role: 'Vice Principal · Academics', bio: 'M.A Urdu Literature. Author of three books on Balti folk history. Oversees curriculum quality and teacher development.', initials: 'GH', color: 'from-sky-600 to-indigo-900' },
    { name: 'Syed Ali Mehdi', role: 'Dean of Student Affairs', bio: 'M.Sc Physics. Twenty-four years of teaching experience. Leads student discipline, sports, and co-curricular activities.', initials: 'SA', color: 'from-rose-600 to-pink-900' },
]
</script>

<template>
    <Head title="About — GBHSS No.1 Skardu" />
    <PublicLayout>
        <!-- Hero -->
        <section class="relative bg-slate-950 text-white py-28 overflow-hidden">
            <div class="absolute inset-0">
                <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-emerald-950 to-slate-900"></div>
                <div class="absolute top-0 right-0 w-[40rem] h-[40rem] bg-emerald-600/20 rounded-full blur-[140px] animate-float"></div>
                <div class="absolute bottom-0 left-0 w-[30rem] h-[30rem] bg-amber-500/10 rounded-full blur-[120px] animate-float" style="animation-delay: 2s"></div>
            </div>
            <div class="relative max-w-5xl mx-auto px-6 lg:px-10 text-center reveal">
                <div class="text-[11px] uppercase tracking-[0.25em] text-amber-400 font-semibold mb-5">Our Heritage</div>
                <h1 class="text-5xl lg:text-7xl font-black tracking-tight leading-[1.0]">
                    Seventy-Two Years<br />
                    <span class="bg-gradient-to-r from-amber-300 to-emerald-300 bg-clip-text text-transparent">of Learning.</span>
                </h1>
                <p class="mt-7 text-lg text-stone-300 max-w-2xl mx-auto font-light leading-relaxed">
                    From a modest middle school in 1954 to Skardu's most prestigious institution — this is our story.
                </p>
            </div>
        </section>

        <!-- Mission / Vision -->
        <section class="py-24 bg-white">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10 grid grid-cols-1 md:grid-cols-2 gap-6 mb-20">
                <div class="reveal p-10 rounded-3xl bg-gradient-to-br from-emerald-50 via-white to-emerald-50 border border-emerald-100 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-40 h-40 bg-emerald-200 rounded-full blur-3xl opacity-40 group-hover:scale-125 transition-transform duration-700"></div>
                    <FlagIcon class="relative w-10 h-10 text-emerald-700 mb-6" />
                    <div class="text-[11px] uppercase tracking-[0.2em] text-emerald-700 font-bold mb-3">Our Mission</div>
                    <h3 class="relative text-3xl font-black text-slate-900 leading-tight mb-5 tracking-tight">To educate, elevate, and empower.</h3>
                    <p class="relative text-slate-700 leading-[1.75]">To provide the young men of Baltistan with a rigorous academic foundation, strong moral character, and a worldview that prepares them to lead — whether in the mountains of Skardu or the halls of global universities.</p>
                </div>
                <div class="reveal-stagger p-10 rounded-3xl bg-gradient-to-br from-amber-50 via-white to-amber-50 border border-amber-100 relative overflow-hidden group" style="--delay: 150ms">
                    <div class="absolute top-0 right-0 w-40 h-40 bg-amber-200 rounded-full blur-3xl opacity-40 group-hover:scale-125 transition-transform duration-700"></div>
                    <EyeIcon class="relative w-10 h-10 text-amber-700 mb-6" />
                    <div class="text-[11px] uppercase tracking-[0.2em] text-amber-700 font-bold mb-3">Our Vision</div>
                    <h3 class="relative text-3xl font-black text-slate-900 leading-tight mb-5 tracking-tight">The finest boys' school in Gilgit-Baltistan.</h3>
                    <p class="relative text-slate-700 leading-[1.75]">To be recognized — by parents, alumni, and the educational community — as the institution that sets the benchmark for academic excellence, character development, and regional pride in Gilgit-Baltistan.</p>
                </div>
            </div>

            <!-- Values -->
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="text-center mb-14 reveal">
                    <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">Our Values</div>
                    <h2 class="text-4xl lg:text-5xl font-black text-slate-900 tracking-tight">Four pillars, one school.</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div v-for="(v, i) in values" :key="v.title"
                         class="group p-8 rounded-3xl border border-stone-200 hover:border-emerald-300 hover:shadow-xl transition-all duration-500 hover:-translate-y-1 reveal-stagger"
                         :style="`--delay: ${i * 100}ms`">
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center mb-5 group-hover:bg-emerald-600 group-hover:text-white group-hover:rotate-6 transition-all duration-500">
                            <component :is="v.icon" class="w-6 h-6" />
                        </div>
                        <h3 class="font-bold text-slate-900 mb-2 text-lg tracking-tight">{{ v.title }}</h3>
                        <p class="text-sm text-slate-600 leading-relaxed">{{ v.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Timeline -->
        <section class="py-24 bg-slate-50">
            <div class="max-w-5xl mx-auto px-6 lg:px-10">
                <div class="text-center mb-16 reveal">
                    <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">Our Journey</div>
                    <h2 class="text-4xl lg:text-5xl font-black text-slate-900 tracking-tight">From 1954 to today.</h2>
                </div>
                <div class="relative">
                    <div class="absolute left-5 md:left-1/2 top-0 bottom-0 w-px bg-gradient-to-b from-emerald-200 via-emerald-400 to-emerald-200 -translate-x-0.5"></div>
                    <div class="space-y-14">
                        <div v-for="(m, i) in milestones" :key="m.year"
                             class="relative flex items-start gap-6 reveal-stagger"
                             :class="i % 2 === 0 ? 'md:flex-row-reverse md:text-right' : 'md:flex-row'"
                             :style="`--delay: ${i * 80}ms`">
                            <div class="flex-shrink-0 w-11 h-11 md:absolute md:left-1/2 md:-translate-x-1/2 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-full flex items-center justify-center text-white shadow-xl z-10 border-[3px] border-white">
                                <CheckCircleIcon class="w-5 h-5" />
                            </div>
                            <div class="md:w-1/2 md:px-12 flex-1">
                                <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100 hover:shadow-xl transition-shadow duration-500">
                                    <div class="text-amber-600 font-black text-2xl mb-1 tracking-tight">{{ m.year }}</div>
                                    <h3 class="font-bold text-slate-900 mb-2 text-lg">{{ m.title }}</h3>
                                    <p class="text-sm text-slate-600 leading-relaxed">{{ m.desc }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Leadership -->
        <section class="py-24 bg-white">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="text-center mb-16 reveal">
                    <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">Leadership</div>
                    <h2 class="text-4xl lg:text-5xl font-black text-slate-900 tracking-tight">The people who guide us.</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div v-for="(l, i) in leadership" :key="l.name"
                         class="group reveal-stagger"
                         :style="`--delay: ${i * 100}ms`">
                        <div class="aspect-[3/4] rounded-3xl bg-gradient-to-br relative overflow-hidden mb-5 shadow-lg"
                             :class="l.color">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                            <span class="absolute inset-0 flex items-center justify-center text-[5rem] font-black text-white/90 tracking-tighter group-hover:scale-110 transition-transform duration-700">{{ l.initials }}</span>
                            <div class="absolute bottom-0 left-0 right-0 p-5 text-white">
                                <div class="text-[10px] uppercase tracking-[0.15em] text-amber-300 font-bold mb-1">{{ l.role.split('·')[0]?.trim() }}</div>
                            </div>
                        </div>
                        <h3 class="font-bold text-slate-900 text-base tracking-tight">{{ l.name }}</h3>
                        <p class="text-xs text-emerald-700 font-semibold mt-0.5 mb-3">{{ l.role }}</p>
                        <p class="text-xs text-slate-600 leading-[1.7]">{{ l.bio }}</p>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
