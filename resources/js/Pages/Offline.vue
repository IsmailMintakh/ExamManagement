<script setup>
/**
 * Friendly fallback shown when a navigation fails because the user is
 * offline AND the requested page wasn't already cached.
 *
 * Tries to retry as soon as the network comes back. We don't auto-redirect
 * to the previous URL because we don't know if THAT page is cached either —
 * better to land on /dashboard which is almost certainly cached.
 */
import { Head, router } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { WifiIcon, ArrowPathIcon, HomeIcon } from '@heroicons/vue/24/outline'

const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true)
const lastTriedUrl = computed(() => {
    // The SW passed the original URL via the props payload — useful as
    // a "go back to what I was doing" link once we're back online.
    const u = (typeof window !== 'undefined' ? window.location.search : '')
    return u
})

function refresh() {
    window.location.reload()
}
function goHome() {
    router.visit('/dashboard')
}

function onOnline() {
    isOnline.value = true
    // Auto-retry the home page after a brief moment.
    setTimeout(() => router.visit('/dashboard'), 600)
}
function onOffline() { isOnline.value = false }

onMounted(() => {
    window.addEventListener('online', onOnline)
    window.addEventListener('offline', onOffline)
})
onUnmounted(() => {
    window.removeEventListener('online', onOnline)
    window.removeEventListener('offline', onOffline)
})
</script>

<template>
    <Head title="Offline" />
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-stone-100 flex items-center justify-center p-6">
        <div class="max-w-md w-full bg-white rounded-3xl shadow-xl border border-stone-200 p-8 text-center">
            <div class="w-20 h-20 mx-auto rounded-2xl flex items-center justify-center mb-6"
                 :class="isOnline ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                <WifiIcon class="w-10 h-10" />
            </div>

            <template v-if="isOnline">
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">You're back online</h1>
                <p class="mt-3 text-sm text-slate-600">
                    Reconnecting to the app…
                </p>
            </template>

            <template v-else>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">You're offline</h1>
                <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                    This page hasn't been cached yet, so it can't load without internet.
                    Pages you've already visited will work — try the dashboard.
                </p>

                <div class="mt-7 flex flex-col gap-2.5">
                    <button @click="goHome"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm py-3 transition-colors">
                        <HomeIcon class="w-4 h-4" />
                        Go to Dashboard
                    </button>
                    <button @click="refresh"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-stone-100 hover:bg-stone-200 text-slate-700 font-medium text-sm py-3 transition-colors">
                        <ArrowPathIcon class="w-4 h-4" />
                        Try Again
                    </button>
                </div>

                <p class="mt-6 text-[11px] text-slate-400">
                    The app will auto-reload when your connection is back.
                </p>
            </template>
        </div>
    </div>
</template>
