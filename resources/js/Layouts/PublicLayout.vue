<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
    AcademicCapIcon, Bars3Icon, XMarkIcon, PhoneIcon, EnvelopeIcon,
    MapPinIcon, ArrowRightIcon, ChevronDownIcon, HeartIcon,
} from '@heroicons/vue/24/outline'

const page = usePage()
const currentPath = computed(() => page.url)
const scrolled = ref(false)
const mobileOpen = ref(false)

function onScroll() { scrolled.value = window.scrollY > 40 }

onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }))
onUnmounted(() => window.removeEventListener('scroll', onScroll))

const navItems = [
    { label: 'Home', href: '/' },
    { label: 'About', href: '/about' },
    { label: 'Academics', href: '/academics' },
    { label: 'Admissions', href: '/admissions' },
    { label: 'Faculty', href: '/faculty' },
    { label: 'Gallery', href: '/gallery' },
    { label: 'News', href: '/news' },
    { label: 'Results', href: '/board-results' },
    { label: 'Contact', href: '/contact' },
]

const isActive = (href) => href === '/' ? currentPath.value === '/' : currentPath.value?.startsWith(href)
</script>

<template>
    <div class="min-h-screen flex flex-col bg-stone-50 text-slate-900 antialiased selection:bg-emerald-500/20 selection:text-emerald-900">
        <!-- Announcement / utility bar -->
        <div class="bg-slate-950 text-stone-300 text-[11px] py-2.5 hidden md:block border-b border-slate-900">
            <div class="max-w-[1400px] mx-auto px-8 flex items-center justify-between">
                <div class="flex items-center gap-7">
                    <span class="flex items-center gap-1.5"><PhoneIcon class="w-3 h-3 text-emerald-400" /> +92-5815-920234</span>
                    <span class="flex items-center gap-1.5"><EnvelopeIcon class="w-3 h-3 text-emerald-400" /> info@gbhss1-skardu.edu.pk</span>
                    <span class="flex items-center gap-1.5"><MapPinIcon class="w-3 h-3 text-emerald-400" /> College Road, Skardu · Gilgit-Baltistan</span>
                </div>
                <div class="flex items-center gap-5 font-medium">
                    <span class="flex items-center gap-1.5 text-emerald-400"><span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Admissions Open 2026–27</span>
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
                        <div class="relative w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-600 via-emerald-700 to-emerald-900 flex items-center justify-center shadow-lg shadow-emerald-900/20 transition-all duration-500 group-hover:rotate-6 group-hover:scale-105">
                            <AcademicCapIcon class="w-6 h-6 text-white" />
                            <span class="absolute -top-1 -right-1 w-3 h-3 rounded-full bg-amber-400 ring-2 ring-white"></span>
                        </div>
                        <div>
                            <div class="text-[14px] font-bold text-slate-900 leading-tight tracking-tight">GBHSS No.1 Skardu</div>
                            <div class="text-[10px] text-emerald-700 font-semibold uppercase tracking-[0.15em] leading-none mt-1">Est. 1954 · Excellence Since</div>
                        </div>
                    </Link>

                    <!-- Desktop nav -->
                    <nav class="hidden lg:flex items-center gap-0.5">
                        <Link
                            v-for="item in navItems"
                            :key="item.href"
                            :href="item.href"
                            class="px-4 py-2 text-[13px] font-medium rounded-full transition-all duration-300 relative"
                            :class="isActive(item.href)
                                ? 'text-emerald-700'
                                : 'text-slate-600 hover:text-slate-900'"
                        >
                            {{ item.label }}
                            <span v-if="isActive(item.href)" class="absolute left-1/2 -translate-x-1/2 -bottom-0.5 w-1 h-1 rounded-full bg-amber-500"></span>
                        </Link>
                    </nav>

                    <!-- CTA + Mobile -->
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
                <div v-if="mobileOpen" class="lg:hidden border-t border-stone-200 bg-white/95 backdrop-blur-xl">
                    <div class="px-4 py-4 space-y-0.5">
                        <Link
                            v-for="(item, i) in navItems"
                            :key="item.href"
                            :href="item.href"
                            @click="mobileOpen = false"
                            class="block px-4 py-3 text-sm font-medium rounded-xl transition-colors"
                            :class="isActive(item.href) ? 'text-emerald-700 bg-emerald-50' : 'text-slate-700 hover:bg-stone-50'"
                            :style="`animation: slideIn 0.3s ease ${i * 30}ms both`"
                        >
                            {{ item.label }}
                        </Link>
                        <Link href="/login" class="block px-4 py-3 text-sm font-medium rounded-xl text-slate-700 hover:bg-stone-50 mt-2 border-t border-stone-100 pt-4">Admin Login</Link>
                    </div>
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
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 via-emerald-600 to-emerald-800 flex items-center justify-center shadow-xl">
                                <AcademicCapIcon class="w-7 h-7 text-white" />
                            </div>
                            <div>
                                <div class="text-lg font-bold text-white tracking-tight">GBHSS No.1 Skardu</div>
                                <div class="text-[10px] text-amber-400 uppercase tracking-[0.2em] font-semibold">Government Boys · Since 1954</div>
                            </div>
                        </div>
                        <p class="text-[15px] leading-[1.75] text-stone-400 max-w-md">
                            Nestled in the heart of Baltistan, our institution has shaped generations of leaders — blending mountain spirit with world-class education for over seven decades.
                        </p>
                        <div class="flex items-center gap-2 mt-6">
                            <a v-for="s in ['f', '𝕏', 'in', '▶']" :key="s"
                               class="w-10 h-10 rounded-full bg-slate-900 hover:bg-emerald-600 border border-slate-800 hover:border-emerald-500 flex items-center justify-center text-sm font-bold cursor-pointer transition-all duration-300 hover:-translate-y-0.5">{{ s }}</a>
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
                            <li class="flex gap-3 items-start">
                                <MapPinIcon class="w-4 h-4 flex-shrink-0 text-amber-400 mt-0.5" />
                                <span class="leading-relaxed">College Road, Skardu<br />Gilgit-Baltistan, 16100<br />Pakistan</span>
                            </li>
                            <li class="flex gap-3 items-center">
                                <PhoneIcon class="w-4 h-4 flex-shrink-0 text-amber-400" />
                                <span>+92-5815-920234</span>
                            </li>
                            <li class="flex gap-3 items-center">
                                <EnvelopeIcon class="w-4 h-4 flex-shrink-0 text-amber-400" />
                                <span>info@gbhss1-skardu.edu.pk</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Bottom bar -->
                <div class="flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-stone-500">
                    <p class="flex items-center gap-2">
                        <span>&copy; {{ new Date().getFullYear() }} Government Boys Higher Secondary School No.1, Skardu.</span>
                        <span class="hidden md:inline">·</span>
                        <span class="hidden md:inline">Crafted with <HeartIcon class="inline w-3 h-3 text-rose-400" /> in Baltistan</span>
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
