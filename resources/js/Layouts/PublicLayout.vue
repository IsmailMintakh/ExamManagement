<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
    AcademicCapIcon, Bars3Icon, XMarkIcon, PhoneIcon, EnvelopeIcon,
    MapPinIcon, ArrowRightIcon, ChevronDownIcon, HeartIcon,
} from '@heroicons/vue/24/outline'
import PWAManager from '@/Components/PWAManager.vue'

const page = usePage()
const currentPath = computed(() => page.url)
const scrolled = ref(false)
const mobileOpen = ref(false)

// All site settings shared globally via HandleInertiaRequests
const site = computed(() => page.props.site || {})
const s = (key, fallback = '') => site.value?.[key] || fallback
const logoUrl = computed(() => {
    const path = site.value?.logo_path
    if (!path) return null
    return path.startsWith('http') ? path : `/storage/${path}`
})

function onScroll() { scrolled.value = window.scrollY > 40 }

onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }))
onUnmounted(() => window.removeEventListener('scroll', onScroll))

// Top-level navigation. The two result-related pages collapse into one
// "Results" dropdown below — keeping them as separate flat items cluttered
// the header (parents had three result entry points to choose between).
const navItems = [
    { label: 'Home', href: '/' },
    { label: 'About', href: '/about' },
    { label: 'Academics', href: '/academics' },
    { label: 'Admissions', href: '/admissions' },
    { label: 'Faculty', href: '/faculty' },
    { label: 'Gallery', href: '/gallery' },
    { label: 'News', href: '/news' },
    { label: 'Contact', href: '/contact' },
]

// Single dropdown that holds both result entry points. Inserted into the
// nav between News and Contact (see desktop template below).
const resultsMenu = [
    { label: 'Check Your Result', href: '/check-result', desc: 'Look up an individual student result by admission number' },
    { label: 'Board Results', href: '/board-results', desc: 'School-level board exam results announcements' },
]

const isActive = (href) => href === '/' ? currentPath.value === '/' : currentPath.value?.startsWith(href)
// True when the current page matches any result page — used to highlight
// the Results dropdown trigger.
const resultsActive = computed(() => resultsMenu.some(r => currentPath.value?.startsWith(r.href)))

// Hover/click control for the desktop dropdown.
const resultsOpen = ref(false)
let resultsCloseTimer = null
function openResults() {
    if (resultsCloseTimer) { clearTimeout(resultsCloseTimer); resultsCloseTimer = null }
    resultsOpen.value = true
}
function closeResultsSoon() {
    // Small delay so moving cursor from trigger to menu doesn't dismiss.
    resultsCloseTimer = setTimeout(() => { resultsOpen.value = false }, 150)
}

// Footer social links — only show ones the admin has set
const socialLinks = computed(() => [
    { url: s('social_facebook'),  label: 'f' },
    { url: s('social_instagram'), label: '⊙' },
    { url: s('social_youtube'),   label: '▶' },
].filter(l => l.url))
</script>

<template>
    <!--
        color-scheme: light  →  opts out of Android Chrome's "Force dark on web
        contents" inversion. Without this, Chrome auto-flips bg-white to dark
        and our white menu drawer renders as a dark inverted nightmare on
        users who have system dark mode + force-dark flag enabled. We're a
        light-themed public site so this is the right declaration.

        forced-color-adjust: none on the public chrome (the rest of the page
        keeps whatever colors we set, but the menu specifically refuses any
        further auto-adjustment).
    -->
    <div class="min-h-screen flex flex-col bg-stone-50 text-slate-900 antialiased selection:bg-emerald-500/20 selection:text-emerald-900"
         style="color-scheme: light;">
        <!-- Announcement / utility bar -->
        <div class="bg-slate-950 text-stone-300 text-[11px] py-2.5 hidden md:block border-b border-slate-900">
            <div class="max-w-[1400px] mx-auto px-8 flex items-center justify-between">
                <div class="flex items-center gap-7">
                    <a v-if="s('phone_primary')" :href="`tel:${s('phone_primary')}`" class="flex items-center gap-1.5 hover:text-amber-400 transition-colors">
                        <PhoneIcon class="w-3 h-3 text-emerald-400" /> {{ s('phone_primary') }}
                    </a>
                    <a v-if="s('email_primary')" :href="`mailto:${s('email_primary')}`" class="flex items-center gap-1.5 hover:text-amber-400 transition-colors">
                        <EnvelopeIcon class="w-3 h-3 text-emerald-400" /> {{ s('email_primary') }}
                    </a>
                    <span v-if="s('address')" class="flex items-center gap-1.5">
                        <MapPinIcon class="w-3 h-3 text-emerald-400" /> {{ s('address') }}
                    </span>
                </div>
                <div class="flex items-center gap-5 font-medium">
                    <span v-if="s('announcement_message')" class="flex items-center gap-1.5 text-emerald-400">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        {{ s('announcement_message') }}
                    </span>
                    <Link href="/login" class="hover:text-amber-400 transition-colors">Staff Portal</Link>
                </div>
            </div>
        </div>

        <!-- Main navigation -->
        <header
            class="sticky top-0 z-50 transition-all duration-500"
            :class="scrolled
                ? 'bg-white/80 backdrop-blur-xl shadow-[0_2px_30px_-10px_rgba(0,0,0,0.1)] border-b border-stone-200/60'
                : 'bg-white border-b border-stone-100'"
        >
            <div class="max-w-[1400px] mx-auto px-6 lg:px-8">
                <div class="flex items-center justify-between h-[72px] lg:h-20">
                    <!-- Logo -->
                    <Link href="/" class="flex items-center gap-3 group">
                        <div v-if="logoUrl" class="relative w-12 h-12 rounded-2xl overflow-hidden shadow-lg shadow-emerald-900/20 transition-all duration-500 group-hover:rotate-3 group-hover:scale-105 bg-white">
                            <img :src="logoUrl" :alt="s('school_short_name', 'Logo')" class="w-full h-full object-contain p-1" />
                        </div>
                        <div v-else class="relative w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-600 via-emerald-700 to-emerald-900 flex items-center justify-center shadow-lg shadow-emerald-900/20 transition-all duration-500 group-hover:rotate-6 group-hover:scale-105">
                            <AcademicCapIcon class="w-6 h-6 text-white" />
                            <span class="absolute -top-1 -right-1 w-3 h-3 rounded-full bg-amber-400 ring-2 ring-white"></span>
                        </div>
                        <div>
                            <div class="text-[14px] font-bold text-slate-900 leading-tight tracking-tight">{{ s('school_short_name', 'School Name') }}</div>
                            <div v-if="s('established_year') || s('tagline')" class="text-[10px] text-emerald-700 font-semibold uppercase tracking-[0.15em] leading-none mt-1">
                                <template v-if="s('established_year')">Est. {{ s('established_year') }}</template>
                                <template v-if="s('established_year') && s('tagline')"> · </template>
                                <template v-if="s('tagline')">{{ s('tagline') }}</template>
                            </div>
                        </div>
                    </Link>

                    <!-- Desktop nav.
                         The Results dropdown is rendered manually after News
                         and before Contact so we don't have to special-case
                         the v-for. Keeps mobile flat (rendered separately
                         below) without complicating the data model. -->
                    <nav class="hidden lg:flex items-center gap-0.5">
                        <template v-for="item in navItems" :key="item.href">
                            <Link
                                :href="item.href"
                                class="px-4 py-2 text-[13px] font-medium rounded-full transition-all duration-300 relative"
                                :class="isActive(item.href)
                                    ? 'text-emerald-700'
                                    : 'text-slate-600 hover:text-slate-900'"
                            >
                                {{ item.label }}
                                <span v-if="isActive(item.href)" class="absolute left-1/2 -translate-x-1/2 -bottom-0.5 w-1 h-1 rounded-full bg-amber-500"></span>
                            </Link>

                            <!-- Inject Results dropdown right after News -->
                            <div
                                v-if="item.href === '/news'"
                                class="relative"
                                @mouseenter="openResults"
                                @mouseleave="closeResultsSoon"
                            >
                                <button
                                    type="button"
                                    @click="resultsOpen = !resultsOpen"
                                    class="px-4 py-2 text-[13px] font-medium rounded-full transition-all duration-300 relative inline-flex items-center gap-1"
                                    :class="resultsActive
                                        ? 'text-emerald-700'
                                        : 'text-slate-600 hover:text-slate-900'"
                                    :aria-expanded="resultsOpen"
                                >
                                    Results
                                    <ChevronDownIcon class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': resultsOpen }" />
                                    <span v-if="resultsActive" class="absolute left-1/2 -translate-x-1/2 -bottom-0.5 w-1 h-1 rounded-full bg-amber-500"></span>
                                </button>

                                <Transition
                                    enter-active-class="transition duration-150 ease-out"
                                    enter-from-class="opacity-0 -translate-y-1"
                                    enter-to-class="opacity-100 translate-y-0"
                                    leave-active-class="transition duration-100 ease-in"
                                    leave-from-class="opacity-100"
                                    leave-to-class="opacity-0 -translate-y-1"
                                >
                                    <div
                                        v-if="resultsOpen"
                                        class="absolute left-1/2 -translate-x-1/2 top-full mt-2 w-72 bg-white rounded-2xl shadow-xl border border-stone-200 overflow-hidden z-50"
                                    >
                                        <Link
                                            v-for="r in resultsMenu"
                                            :key="r.href"
                                            :href="r.href"
                                            @click="resultsOpen = false"
                                            class="block px-4 py-3 hover:bg-emerald-50 transition-colors group"
                                            :class="isActive(r.href) ? 'bg-emerald-50' : ''"
                                        >
                                            <div class="text-sm font-semibold text-slate-900 group-hover:text-emerald-700">
                                                {{ r.label }}
                                            </div>
                                            <div class="text-[11px] text-slate-500 mt-0.5 leading-tight">
                                                {{ r.desc }}
                                            </div>
                                        </Link>
                                    </div>
                                </Transition>
                            </div>
                        </template>
                    </nav>

                    <!-- CTA + Mobile.
                         Result entry points live in the nav dropdown above —
                         no separate Check Result button so the header stops
                         showing three different ways to reach results. -->
                    <div class="flex items-center gap-3">
                        <Link
                            href="/admissions"
                            class="hidden sm:inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-full text-[13px] font-semibold shadow-sm hover:shadow-lg transition-all duration-300 group"
                        >
                            Apply Now
                            <ArrowRightIcon class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" />
                        </Link>
                        <button @click="mobileOpen = !mobileOpen" class="lg:hidden p-2 rounded-lg hover:bg-stone-100 transition-colors">
                            <Bars3Icon v-if="!mobileOpen" class="w-6 h-6" />
                            <XMarkIcon v-else class="w-6 h-6" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile menu -->
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-4"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <!-- ─── Mobile menu drawer ───
                     Defensive measures (each layer matters because Android
                     Chrome's force-dark feature can override single layers):
                       1. color-scheme: light — opts out of force-dark
                       2. forced-color-adjust: none — refuses any browser color
                          inversion or accent color override
                       3. Inline `background:` and `color:` styles — survive
                          purge, dark mode, and theme overrides
                       4. divide-y for visible row separators
                       5. No animations (broken on iOS PWA before) -->
                <div v-if="mobileOpen"
                    class="lg:hidden border-t border-stone-200"
                    style="background: #ffffff !important; color-scheme: light; forced-color-adjust: none;">
                    <nav class="divide-y divide-stone-100"
                         style="background: #ffffff !important;">
                        <Link v-for="item in navItems" :key="item.href"
                            :href="item.href"
                            @click="mobileOpen = false"
                            class="flex items-center px-5 min-h-[48px] text-[15px] font-semibold"
                            :style="{
                                color: isActive(item.href) ? '#047857' : '#1e293b',
                                background: isActive(item.href) ? '#ecfdf5' : '#ffffff',
                                forcedColorAdjust: 'none',
                            }">
                            {{ item.label }}
                        </Link>

                        <!-- Results section heading -->
                        <div class="px-5 pt-3 pb-1 text-[11px] font-bold uppercase tracking-[0.15em]"
                             style="color: #64748b; background: #ffffff;">
                            Results
                        </div>
                        <Link v-for="r in resultsMenu" :key="r.href"
                            :href="r.href"
                            @click="mobileOpen = false"
                            class="flex items-center px-5 min-h-[48px] text-[15px] font-semibold"
                            :style="{
                                color: isActive(r.href) ? '#047857' : '#1e293b',
                                background: isActive(r.href) ? '#ecfdf5' : '#ffffff',
                                forcedColorAdjust: 'none',
                            }">
                            {{ r.label }}
                        </Link>

                        <Link href="/login"
                            class="flex items-center px-5 min-h-[48px] text-[15px] font-semibold"
                            style="color: #1e293b; background: #ffffff; forced-color-adjust: none;">
                            Admin Login
                        </Link>
                    </nav>
                </div>
            </Transition>
        </header>

        <!-- Page content -->
        <main class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="relative bg-slate-950 text-stone-400 overflow-hidden">
            <!-- Decorative gradient blur -->
            <div class="absolute inset-0 opacity-30 pointer-events-none">
                <div class="absolute top-0 right-0 w-[40rem] h-[40rem] bg-emerald-900/40 rounded-full blur-[120px]"></div>
                <div class="absolute bottom-0 left-0 w-[30rem] h-[30rem] bg-amber-900/30 rounded-full blur-[100px]"></div>
            </div>

            <div class="relative max-w-[1400px] mx-auto px-8 py-20">
                <!-- Top: big brand statement -->
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-12 mb-14 pb-12 border-b border-slate-900">
                    <div class="lg:col-span-2">
                        <div class="flex items-center gap-3 mb-5">
                            <div v-if="logoUrl" class="w-14 h-14 rounded-2xl overflow-hidden bg-white shadow-xl">
                                <img :src="logoUrl" :alt="s('school_short_name', 'Logo')" class="w-full h-full object-contain p-1.5" />
                            </div>
                            <div v-else class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 via-emerald-600 to-emerald-800 flex items-center justify-center shadow-xl">
                                <AcademicCapIcon class="w-7 h-7 text-white" />
                            </div>
                            <div>
                                <div class="text-lg font-bold text-white tracking-tight">{{ s('school_short_name', 'School Name') }}</div>
                                <div v-if="s('established_year')" class="text-[10px] text-amber-400 uppercase tracking-[0.2em] font-semibold">Since {{ s('established_year') }}</div>
                            </div>
                        </div>
                        <p v-if="s('footer_description')" class="text-[15px] leading-[1.75] text-stone-400 max-w-md">
                            {{ s('footer_description') }}
                        </p>
                        <div v-if="socialLinks.length" class="flex items-center gap-2 mt-6">
                            <a v-for="link in socialLinks" :key="link.url"
                               :href="link.url" target="_blank" rel="noopener"
                               class="w-10 h-10 rounded-full bg-slate-900 hover:bg-emerald-600 border border-slate-800 hover:border-emerald-500 flex items-center justify-center text-sm font-bold cursor-pointer transition-all duration-300 hover:-translate-y-0.5">{{ link.label }}</a>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-white font-semibold mb-5 text-xs uppercase tracking-[0.2em]">Explore</h3>
                        <ul class="space-y-3 text-sm">
                            <li><Link href="/about" class="hover:text-amber-400 transition-colors">About the School</Link></li>
                            <li><Link href="/academics" class="hover:text-amber-400 transition-colors">Academic Programs</Link></li>
                            <li><Link href="/admissions" class="hover:text-amber-400 transition-colors">Admissions</Link></li>
                            <li><Link href="/faculty" class="hover:text-amber-400 transition-colors">Our Faculty</Link></li>
                            <li><Link href="/board-results" class="hover:text-amber-400 transition-colors">Board Results</Link></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-white font-semibold mb-5 text-xs uppercase tracking-[0.2em]">Resources</h3>
                        <ul class="space-y-3 text-sm">
                            <li><Link href="/news" class="hover:text-amber-400 transition-colors">News &amp; Events</Link></li>
                            <li><Link href="/gallery" class="hover:text-amber-400 transition-colors">Photo Gallery</Link></li>
                            <li><a href="#" class="hover:text-amber-400 transition-colors">Scholarships</a></li>
                            <li><a href="#" class="hover:text-amber-400 transition-colors">Alumni Network</a></li>
                            <li><Link href="/login" class="hover:text-amber-400 transition-colors">Staff Portal</Link></li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-white font-semibold mb-5 text-xs uppercase tracking-[0.2em]">Visit Us</h3>
                        <ul class="space-y-4 text-[13px]">
                            <li v-if="s('address')" class="flex gap-3 items-start">
                                <MapPinIcon class="w-4 h-4 flex-shrink-0 text-amber-400 mt-0.5" />
                                <span class="leading-relaxed whitespace-pre-line">{{ s('address') }}</span>
                            </li>
                            <li v-if="s('phone_primary')" class="flex gap-3 items-center">
                                <PhoneIcon class="w-4 h-4 flex-shrink-0 text-amber-400" />
                                <a :href="`tel:${s('phone_primary')}`" class="hover:text-amber-400 transition-colors">{{ s('phone_primary') }}</a>
                            </li>
                            <li v-if="s('email_primary')" class="flex gap-3 items-center">
                                <EnvelopeIcon class="w-4 h-4 flex-shrink-0 text-amber-400" />
                                <a :href="`mailto:${s('email_primary')}`" class="hover:text-amber-400 transition-colors break-all">{{ s('email_primary') }}</a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Bottom bar -->
                <div class="flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-stone-500">
                    <p class="flex items-center gap-2 text-center md:text-left">
                        <span>&copy; {{ new Date().getFullYear() }} {{ s('school_name', s('school_short_name', 'Our School')) }}.</span>
                        <span class="hidden md:inline">·</span>
                        <span class="hidden md:inline">All rights reserved.</span>
                    </p>
                    <div class="flex items-center gap-6">
                        <a href="#" class="hover:text-amber-400 transition-colors">Privacy</a>
                        <a href="#" class="hover:text-amber-400 transition-colors">Terms</a>
                        <a href="#" class="hover:text-amber-400 transition-colors">Sitemap</a>
                        <span class="px-2 py-0.5 rounded-full border border-stone-800 text-stone-600">v2.0</span>
                    </div>
                </div>
            </div>
        </footer>

        <!-- PWA install prompt + offline indicator -->
        <PWAManager />
    </div>
</template>

<style>
/* ==========================================================================
   ELEGANT ANIMATION SYSTEM — CSS-first, no-JS fallbacks, reduced-motion safe
   ========================================================================== */

/* Intro reveal on mount — applied via .reveal class, content stays visible */
.reveal {
    animation: revealUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
}
@keyframes revealUp {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Staggered reveals — use inline style="--delay: 100ms" */
.reveal-stagger {
    animation: revealUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--delay, 0ms);
}

/* Premium gradient animation */
@keyframes gradient-shift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}
.animate-gradient {
    background-size: 200% 200%;
    animation: gradient-shift 8s ease infinite;
}

/* Floating elements */
@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-12px) rotate(1deg); }
}
.animate-float { animation: float 4s ease-in-out infinite; }

/* Subtle pulse for live indicators */
@keyframes pulse-ring {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); }
    70% { box-shadow: 0 0 0 12px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
.animate-pulse-ring { animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }

/* Shimmer effect for premium cards */
@keyframes shimmer {
    0% { background-position: -1000px 0; }
    100% { background-position: 1000px 0; }
}
.animate-shimmer {
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
    background-size: 1000px 100%;
    animation: shimmer 3s linear infinite;
}

/* Slide-in from left (mobile menu items) */
@keyframes slideIn {
    from { opacity: 0; transform: translateX(-20px); }
    to { opacity: 1; transform: translateX(0); }
}

/* Smooth scroll behavior */
html { scroll-behavior: smooth; }

/* Elegant focus states */
*:focus-visible {
    outline: 2px solid #059669;
    outline-offset: 2px;
    border-radius: 4px;
}

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}

/* Custom scrollbar */
::-webkit-scrollbar { width: 10px; height: 10px; }
::-webkit-scrollbar-track { background: #f5f5f4; }
::-webkit-scrollbar-thumb { background: #d6d3d1; border-radius: 5px; }
::-webkit-scrollbar-thumb:hover { background: #a8a29e; }
</style>
