<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import {
    PhoneIcon, EnvelopeIcon, MapPinIcon, ClockIcon,
    PaperAirplaneIcon, BuildingOfficeIcon, UserCircleIcon,
} from '@heroicons/vue/24/outline'

const form = ref({ name: '', email: '', phone: '', subject: '', message: '' })
const sent = ref(false)

function submit() {
    sent.value = true
    form.value = { name: '', email: '', phone: '', subject: '', message: '' }
    setTimeout(() => sent.value = false, 4500)
}

const directory = [
    { dept: 'General Enquiries', person: 'Reception · Main Office', phone: '+92-5815-920234', email: 'info@gbhss1-skardu.edu.pk', hours: 'Mon–Sat · 8:00 AM – 2:00 PM' },
    { dept: 'Admissions Office', person: 'Mr. Sultan Mehdi', phone: '+92-5815-920240', email: 'admissions@gbhss1-skardu.edu.pk', hours: 'Mon–Fri · 9:00 AM – 2:00 PM' },
    { dept: 'Principal\'s Office', person: 'Muhammad Ismail Khan', phone: '+92-5815-920245', email: 'principal@gbhss1-skardu.edu.pk', hours: 'Mon–Fri · 10:00 AM – 12:00 PM (by appointment)' },
    { dept: 'Academic Affairs', person: 'Prof. Ghulam Hussain · VP Academics', phone: '+92-5815-920250', email: 'academics@gbhss1-skardu.edu.pk', hours: 'Mon–Fri · 9:00 AM – 1:00 PM' },
    { dept: 'DDO Office · Skardu', person: 'Wazir Zamin Ali · DDO', phone: '+92-5815-920200', email: 'ddo.skardu@gb-education.gov.pk', hours: 'Mon–Fri · 9:00 AM – 4:00 PM' },
    { dept: 'Student Welfare', person: 'Syed Ali Mehdi · Dean', phone: '+92-5815-920255', email: 'welfare@gbhss1-skardu.edu.pk', hours: 'Mon–Fri · 9:00 AM – 2:00 PM' },
]
</script>

<template>
    <Head title="Contact — GBHSS No.1 Skardu" />
    <PublicLayout>
        <section class="relative bg-slate-950 text-white py-28 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-emerald-950 to-slate-900"></div>
            <div class="absolute top-0 right-0 w-[40rem] h-[40rem] bg-emerald-600/20 rounded-full blur-[140px] animate-float"></div>
            <div class="relative max-w-5xl mx-auto px-6 lg:px-10 text-center reveal">
                <div class="text-[11px] uppercase tracking-[0.25em] text-amber-400 font-semibold mb-5">Get in Touch</div>
                <h1 class="text-5xl lg:text-7xl font-black tracking-tight leading-[1.0]">
                    Visit us in<br />
                    <span class="bg-gradient-to-r from-amber-300 to-emerald-300 bg-clip-text text-transparent">Skardu.</span>
                </h1>
                <p class="mt-7 text-lg text-stone-300 max-w-2xl mx-auto font-light">At the gateway to the Karakoram — our doors are always open to parents, students, and friends of the school.</p>
            </div>
        </section>

        <!-- Contact cards -->
        <section class="py-14 bg-white -mt-10 relative z-10">
            <div class="max-w-5xl mx-auto px-6 lg:px-10 grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="reveal bg-white rounded-3xl p-8 shadow-xl border border-stone-100 text-center hover:shadow-2xl hover:-translate-y-1 transition-all duration-500">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center mb-5"><MapPinIcon class="w-7 h-7" /></div>
                    <h3 class="font-bold text-slate-900 mb-3 tracking-tight">Visit Us</h3>
                    <p class="text-sm text-slate-600 leading-[1.7]">College Road<br />Skardu, Gilgit-Baltistan<br />16100, Pakistan</p>
                </div>
                <div class="reveal-stagger bg-white rounded-3xl p-8 shadow-xl border border-stone-100 text-center hover:shadow-2xl hover:-translate-y-1 transition-all duration-500" style="--delay: 100ms">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center mb-5"><PhoneIcon class="w-7 h-7" /></div>
                    <h3 class="font-bold text-slate-900 mb-3 tracking-tight">Call Us</h3>
                    <p class="text-sm text-slate-600 leading-[1.7]">+92-5815-920234<br />+92-5815-920240<br />+92-300-1234567</p>
                </div>
                <div class="reveal-stagger bg-white rounded-3xl p-8 shadow-xl border border-stone-100 text-center hover:shadow-2xl hover:-translate-y-1 transition-all duration-500" style="--delay: 200ms">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-rose-50 text-rose-700 flex items-center justify-center mb-5"><EnvelopeIcon class="w-7 h-7" /></div>
                    <h3 class="font-bold text-slate-900 mb-3 tracking-tight">Email Us</h3>
                    <p class="text-sm text-slate-600 leading-[1.7]">info@gbhss1-skardu.edu.pk<br />admissions@gbhss1-skardu.edu.pk<br />principal@gbhss1-skardu.edu.pk</p>
                </div>
            </div>
        </section>

        <!-- Form + hours -->
        <section class="py-24 bg-stone-50">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10 grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="reveal bg-white rounded-3xl shadow-sm border border-stone-100 p-10">
                    <h3 class="text-3xl font-black text-slate-900 tracking-tight mb-2">Send a message</h3>
                    <p class="text-sm text-slate-600 mb-7">We'll respond within 2 working days.</p>

                    <Transition enter-active-class="transition duration-500" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
                        <div v-if="sent" class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            Thank you! Your message has been received.
                        </div>
                    </Transition>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="text-[11px] font-bold text-slate-700 uppercase tracking-[0.1em]">Name *</label>
                                <input v-model="form.name" required type="text" placeholder="Muhammad Ali"
                                       class="mt-2 w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 text-sm transition-all" />
                            </div>
                            <div>
                                <label class="text-[11px] font-bold text-slate-700 uppercase tracking-[0.1em]">Email *</label>
                                <input v-model="form.email" required type="email" placeholder="you@example.com"
                                       class="mt-2 w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 text-sm transition-all" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="text-[11px] font-bold text-slate-700 uppercase tracking-[0.1em]">Phone</label>
                                <input v-model="form.phone" type="tel" placeholder="+92-3XX-XXXXXXX"
                                       class="mt-2 w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 text-sm transition-all" />
                            </div>
                            <div>
                                <label class="text-[11px] font-bold text-slate-700 uppercase tracking-[0.1em]">Subject *</label>
                                <select v-model="form.subject" required class="mt-2 w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 text-sm bg-white transition-all">
                                    <option value="">Select topic...</option>
                                    <option>Admissions Enquiry</option>
                                    <option>Academic Information</option>
                                    <option>Parent/Student Concern</option>
                                    <option>Scholarship Enquiry</option>
                                    <option>Employment</option>
                                    <option>Other</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-slate-700 uppercase tracking-[0.1em]">Message *</label>
                            <textarea v-model="form.message" required rows="5" placeholder="How may we help you?"
                                      class="mt-2 w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 text-sm resize-none transition-all"></textarea>
                        </div>
                        <button type="submit"
                                class="group inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-7 py-3.5 rounded-full font-semibold text-sm shadow-lg hover:shadow-xl transition-all duration-300">
                            <PaperAirplaneIcon class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" />
                            Send Message
                        </button>
                    </form>
                </div>

                <div class="space-y-6">
                    <div class="reveal-stagger relative overflow-hidden bg-gradient-to-br from-emerald-800 via-slate-900 to-emerald-950 text-white rounded-3xl p-10 shadow-2xl" style="--delay: 150ms">
                        <div class="absolute top-0 right-0 w-60 h-60 bg-amber-500/10 rounded-full blur-[100px]"></div>
                        <ClockIcon class="relative w-10 h-10 text-amber-400 mb-5" />
                        <h3 class="relative text-2xl font-black tracking-tight mb-6">Office hours</h3>
                        <div class="relative space-y-3 text-sm">
                            <div class="flex justify-between pb-3 border-b border-white/10"><span class="text-stone-300">Monday — Thursday</span><span class="font-bold">8:00 AM – 2:00 PM</span></div>
                            <div class="flex justify-between pb-3 border-b border-white/10"><span class="text-stone-300">Friday</span><span class="font-bold">8:00 AM – 12:00 PM</span></div>
                            <div class="flex justify-between pb-3 border-b border-white/10"><span class="text-stone-300">Saturday</span><span class="font-bold">9:00 AM – 12:00 PM</span></div>
                            <div class="flex justify-between"><span class="text-stone-300">Sunday &amp; Holidays</span><span class="text-amber-300 font-bold">Closed</span></div>
                        </div>
                    </div>

                    <div class="reveal-stagger rounded-3xl overflow-hidden shadow-xl border border-stone-100 h-72 bg-gradient-to-br from-slate-300 via-slate-400 to-slate-600 relative" style="--delay: 250ms">
                        <div class="absolute inset-0 opacity-30"
                             style="background-image: repeating-linear-gradient(0deg, transparent 0, transparent 40px, rgba(255,255,255,0.4) 40px, rgba(255,255,255,0.4) 41px), repeating-linear-gradient(90deg, transparent 0, transparent 40px, rgba(255,255,255,0.4) 40px, rgba(255,255,255,0.4) 41px);"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="text-center bg-white rounded-3xl px-8 py-6 shadow-2xl">
                                <MapPinIcon class="w-10 h-10 text-rose-600 mx-auto mb-3 animate-pulse-ring rounded-full" />
                                <div class="font-bold text-slate-900">GBHSS No.1 Skardu</div>
                                <div class="text-xs text-slate-500 mt-1">College Road · Skardu</div>
                                <a href="#" class="mt-4 inline-block text-xs text-emerald-700 font-semibold hover:underline">Open in Google Maps →</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Directory -->
        <section class="py-24 bg-white">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="text-center mb-14 reveal">
                    <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">Directory</div>
                    <h2 class="text-4xl lg:text-5xl font-black text-slate-900 tracking-tight">Who to contact.</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div v-for="(d, i) in directory" :key="d.dept"
                         class="group bg-white rounded-3xl p-7 border border-stone-200 hover:border-emerald-300 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-500 reveal-stagger"
                         :style="`--delay: ${(i % 6) * 60}ms`">
                        <div class="flex items-start gap-3 mb-4">
                            <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center flex-shrink-0 group-hover:bg-emerald-600 group-hover:text-white transition-all duration-500"><BuildingOfficeIcon class="w-5 h-5" /></div>
                            <div>
                                <h3 class="font-bold text-slate-900 text-sm tracking-tight" v-html="d.dept"></h3>
                                <p class="text-[11px] text-slate-500 mt-0.5 flex items-center gap-1"><UserCircleIcon class="w-3.5 h-3.5" /><span v-html="d.person"></span></p>
                            </div>
                        </div>
                        <div class="space-y-2 text-xs text-slate-600">
                            <div class="flex items-center gap-2"><PhoneIcon class="w-3.5 h-3.5 flex-shrink-0 text-emerald-600" /><span>{{ d.phone }}</span></div>
                            <div class="flex items-center gap-2"><EnvelopeIcon class="w-3.5 h-3.5 flex-shrink-0 text-emerald-600" /><span class="break-all">{{ d.email }}</span></div>
                            <div class="flex items-center gap-2"><ClockIcon class="w-3.5 h-3.5 flex-shrink-0 text-emerald-600" /><span v-html="d.hours"></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
