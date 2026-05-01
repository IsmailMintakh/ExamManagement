<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import PublicHero from '@/Components/PublicHero.vue'
import PageBlocks from '@/Components/PageBlocks.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    PhoneIcon, EnvelopeIcon, MapPinIcon, ClockIcon,
    PaperAirplaneIcon, ShieldCheckIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    site:   { type: Object, default: () => ({}) },
    hero:   { type: Object, default: () => ({}) },
    blocks: { type: Array,  default: () => [] },
})

const page = usePage()
const flash = computed(() => page.props.flash || {})

const s = (k, fb = '') => props.site?.[k] || fb

const form = useForm({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
    website: '',  // honeypot — must stay empty
})

function submit() {
    form.post(route('public.contact.submit'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    })
}

// Quick-info cards built from site_settings
const cards = computed(() => {
    const out = []
    const phones = [s('phone_primary'), s('phone_secondary')].filter(Boolean)
    if (phones.length) out.push({ icon: PhoneIcon, label: 'Phone', lines: phones, type: 'tel' })

    const emails = [s('email_primary'), s('email_admissions')].filter(Boolean)
    if (emails.length) out.push({ icon: EnvelopeIcon, label: 'Email', lines: emails, type: 'mailto' })

    if (s('address')) out.push({ icon: MapPinIcon, label: 'Address', lines: [s('address')], type: 'address' })
    if (s('office_hours')) out.push({ icon: ClockIcon, label: 'Office Hours', lines: [s('office_hours')], type: 'text' })
    return out
})
</script>

<template>
    <Head :title="hero.meta_title || 'Contact Us'">
        <meta v-if="hero.meta_description" name="description" :content="hero.meta_description" />
    </Head>
    <PublicLayout>
        <PublicHero :hero="hero" :fallback="{
            eyebrow: 'Get in Touch',
            title: 'We\'d love to',
            accent: 'hear from you.',
            subtitle: `Questions about admissions, academics, or anything else? Reach out — we typically respond within 1–2 business days.`,
        }" />

        <!-- Quick-info cards -->
        <section class="py-16 bg-white">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div v-for="(card, i) in cards" :key="i"
                         class="reveal-stagger group p-6 rounded-2xl border border-stone-200 hover:border-emerald-300 hover:shadow-lg transition-all duration-300"
                         :style="`--delay: ${i * 80}ms`">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center mb-4 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                            <component :is="card.icon" class="w-5 h-5" />
                        </div>
                        <div class="text-[10px] uppercase tracking-[0.18em] text-amber-600 font-bold mb-2">{{ card.label }}</div>
                        <div class="space-y-0.5">
                            <a v-for="(line, idx) in card.lines" :key="idx"
                               :href="card.type === 'tel' ? `tel:${line}`
                                    : card.type === 'mailto' ? `mailto:${line}`
                                    : '#'"
                               :class="card.type !== 'text' && card.type !== 'address' ? 'block text-sm font-semibold text-slate-900 hover:text-emerald-700 transition-colors' : 'block text-sm text-slate-700 leading-snug'"
                               @click="card.type === 'text' || card.type === 'address' ? $event.preventDefault() : null">
                                {{ line }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact form + map -->
        <section class="py-20 bg-stone-50">
            <div class="max-w-[1200px] mx-auto px-6 lg:px-10 grid grid-cols-1 lg:grid-cols-5 gap-10">

                <!-- Form -->
                <div class="lg:col-span-3">
                    <div class="bg-white rounded-3xl p-8 lg:p-10 shadow-xl border border-stone-100">
                        <div class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-3">Send a Message</div>
                        <h2 class="text-3xl lg:text-4xl font-black text-slate-900 leading-tight tracking-tight mb-3">Tell us what's on your mind.</h2>
                        <p class="text-sm text-slate-600 mb-8">All fields marked with <span class="text-amber-600 font-bold">*</span> are required.</p>

                        <!-- Success message -->
                        <div v-if="flash.success" class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-start gap-3">
                            <CheckCircleIcon class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
                            <div>
                                <h3 class="text-sm font-bold text-emerald-900">Message sent</h3>
                                <p class="text-xs text-emerald-800/80 mt-1">{{ flash.success }}</p>
                            </div>
                        </div>

                        <form @submit.prevent="submit" class="space-y-5">
                            <!-- Honeypot — hidden from users, traps bots -->
                            <input type="text" v-model="form.website" tabindex="-1" autocomplete="off" name="website"
                                class="absolute -left-[9999px]" aria-hidden="true" />

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Name <span class="text-amber-600">*</span></label>
                                    <input v-model="form.name" type="text" required
                                        placeholder="Your full name"
                                        class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition-colors text-sm" />
                                    <p v-if="form.errors.name" class="mt-1.5 text-xs text-red-600">{{ form.errors.name }}</p>
                                </div>
                                <div>
                                    <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Email <span class="text-amber-600">*</span></label>
                                    <input v-model="form.email" type="email" required
                                        placeholder="you@example.com"
                                        class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition-colors text-sm" />
                                    <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600">{{ form.errors.email }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Phone (optional)</label>
                                    <input v-model="form.phone" type="tel"
                                        placeholder="+92 300 0000000"
                                        class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition-colors text-sm" />
                                </div>
                                <div>
                                    <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Subject (optional)</label>
                                    <input v-model="form.subject" type="text"
                                        placeholder="Admissions enquiry, etc."
                                        class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition-colors text-sm" />
                                </div>
                            </div>

                            <div>
                                <label class="block text-[12px] font-bold text-slate-700 mb-1.5">Message <span class="text-amber-600">*</span></label>
                                <textarea v-model="form.message" required rows="6"
                                    placeholder="Tell us how we can help. We respond to all enquiries within 1–2 business days."
                                    class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition-colors text-sm resize-none"></textarea>
                                <p v-if="form.errors.message" class="mt-1.5 text-xs text-red-600">{{ form.errors.message }}</p>
                            </div>

                            <div class="flex items-center justify-between gap-4 flex-wrap pt-2">
                                <p class="text-[11px] text-slate-500 inline-flex items-center gap-1.5">
                                    <ShieldCheckIcon class="w-3.5 h-3.5" />
                                    Your information is private and never shared.
                                </p>
                                <button type="submit"
                                    :disabled="form.processing"
                                    class="group inline-flex items-center gap-2 bg-gradient-to-br from-amber-400 to-amber-600 hover:from-amber-300 hover:to-amber-500 text-slate-950 px-7 py-3.5 rounded-full text-sm font-bold shadow-2xl shadow-amber-500/30 hover:shadow-amber-500/50 hover:-translate-y-0.5 transition-all duration-300 disabled:opacity-60 disabled:cursor-not-allowed">
                                    {{ form.processing ? 'Sending…' : 'Send Message' }}
                                    <PaperAirplaneIcon class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Side: school info + map -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-gradient-to-br from-emerald-700 via-emerald-800 to-slate-900 text-white p-8 rounded-3xl shadow-xl">
                        <h3 class="text-xl font-black mb-3 tracking-tight">{{ s('school_name', 'Our School') }}</h3>
                        <p v-if="s('address')" class="text-stone-200 text-sm leading-relaxed mb-5 inline-flex items-start gap-2">
                            <MapPinIcon class="w-4 h-4 mt-0.5 shrink-0" />
                            <span>{{ s('address') }}</span>
                        </p>
                        <div class="space-y-2 text-sm">
                            <a v-if="s('phone_primary')" :href="`tel:${s('phone_primary')}`" class="flex items-center gap-2 text-stone-100 hover:text-amber-300 transition-colors">
                                <PhoneIcon class="w-4 h-4" /> {{ s('phone_primary') }}
                            </a>
                            <a v-if="s('email_primary')" :href="`mailto:${s('email_primary')}`" class="flex items-center gap-2 text-stone-100 hover:text-amber-300 transition-colors">
                                <EnvelopeIcon class="w-4 h-4" /> {{ s('email_primary') }}
                            </a>
                        </div>
                    </div>

                    <a v-if="s('google_maps_url')" :href="s('google_maps_url')" target="_blank" rel="noopener"
                        class="block aspect-[4/3] rounded-3xl overflow-hidden bg-gradient-to-br from-stone-200 to-stone-300 relative shadow-xl group">
                        <div class="absolute inset-0 flex items-center justify-center text-slate-700 group-hover:text-emerald-700 transition-colors">
                            <div class="text-center">
                                <MapPinIcon class="w-10 h-10 mx-auto mb-2" />
                                <div class="text-sm font-semibold">View on Google Maps</div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <!-- Custom admin-managed blocks (FAQ, departments, hours table, etc.) -->
        <PageBlocks :blocks="blocks" />
    </PublicLayout>
</template>
