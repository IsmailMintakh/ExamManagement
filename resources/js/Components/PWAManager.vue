<script setup>
/**
 * Single mount-point that handles ALL Progressive Web App UX:
 *
 *   1. Install prompt — shows a friendly banner when Chrome/Edge fires
 *      `beforeinstallprompt` (Android, desktop). Tracks the choice in
 *      localStorage so we don't pester users who've already installed
 *      or dismissed.
 *
 *   2. Offline indicator — listens to navigator online/offline events
 *      and shows a coloured pill ("You're offline" / "Back online").
 *
 *   3. Update prompt — when Workbox detects a new service worker,
 *      shows a "New version available" toast with a "Reload" button.
 *
 * All three are tiny floating elements — they never push page content.
 * Hidden when not needed (status: idle).
 */
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRegisterSW } from 'virtual:pwa-register/vue'
import {
    ArrowDownTrayIcon, XMarkIcon, ArrowPathIcon,
    WifiIcon, CheckCircleIcon, ExclamationCircleIcon,
} from '@heroicons/vue/24/outline'

// ─── Online / offline tracking ─────────────────────────────
const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true)
const showOnlineToast = ref(false)
let onlineToastTimer = null

function handleOnline() {
    isOnline.value = true
    showOnlineToast.value = true
    clearTimeout(onlineToastTimer)
    onlineToastTimer = setTimeout(() => { showOnlineToast.value = false }, 3000)
}
function handleOffline() {
    isOnline.value = false
    showOnlineToast.value = false
    clearTimeout(onlineToastTimer)
}

// ─── Install prompt (beforeinstallprompt event) ────────────
const installEvent = ref(null)
const showInstallBanner = ref(false)
const STORAGE_KEY_INSTALL = 'pwa-install-dismissed-at'

function handleBeforeInstall(e) {
    e.preventDefault()
    installEvent.value = e
    // Show only if the user hasn't dismissed in the last 7 days
    const lastDismiss = Number(localStorage.getItem(STORAGE_KEY_INSTALL) || 0)
    const sevenDays = 7 * 24 * 60 * 60 * 1000
    if (Date.now() - lastDismiss > sevenDays) {
        showInstallBanner.value = true
    }
}
async function installApp() {
    if (!installEvent.value) return
    installEvent.value.prompt()
    const { outcome } = await installEvent.value.userChoice
    if (outcome === 'accepted') {
        showInstallBanner.value = false
    }
    installEvent.value = null
}
function dismissInstall() {
    localStorage.setItem(STORAGE_KEY_INSTALL, String(Date.now()))
    showInstallBanner.value = false
}

// ─── Service worker update prompt ──────────────────────────
// useRegisterSW from vite-plugin-pwa wires up the workbox-window glue.
// `needRefresh` becomes true when a new SW has been downloaded and is
// waiting to take control. We give the user the choice to apply it.
const { needRefresh, updateServiceWorker } = useRegisterSW({
    onRegistered(swReg) {
        // Optional: poll for updates every hour while the app is open.
        if (swReg) {
            setInterval(() => swReg.update(), 60 * 60 * 1000)
        }
    },
})

function applyUpdate() {
    updateServiceWorker(true)
}
function dismissUpdate() {
    needRefresh.value = false
}

// ─── Lifecycle ─────────────────────────────────────────────
onMounted(() => {
    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)
    window.addEventListener('beforeinstallprompt', handleBeforeInstall)
})
onUnmounted(() => {
    window.removeEventListener('online', handleOnline)
    window.removeEventListener('offline', handleOffline)
    window.removeEventListener('beforeinstallprompt', handleBeforeInstall)
    clearTimeout(onlineToastTimer)
})
</script>

<template>
    <!-- ════════ OFFLINE BAR (sticky top, only when offline) ════════ -->
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="-translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="-translate-y-full opacity-0"
    >
        <div v-if="!isOnline"
             class="fixed top-0 inset-x-0 z-[60] bg-amber-500 text-amber-950 px-4 py-2.5 text-xs font-semibold flex items-center justify-center gap-2 shadow-lg"
             role="status" aria-live="polite">
            <ExclamationCircleIcon class="w-4 h-4 shrink-0" />
            <span>You're offline — changes will sync when you reconnect.</span>
        </div>
    </Transition>

    <!-- "Back online" success toast — auto-fades after 3s -->
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="-translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition-all duration-300 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="-translate-y-full opacity-0"
    >
        <div v-if="isOnline && showOnlineToast"
             class="fixed top-0 inset-x-0 z-[60] bg-emerald-600 text-white px-4 py-2.5 text-xs font-semibold flex items-center justify-center gap-2 shadow-lg"
             role="status" aria-live="polite">
            <CheckCircleIcon class="w-4 h-4 shrink-0" />
            <span>Back online — syncing your changes…</span>
        </div>
    </Transition>

    <!-- ════════ INSTALL BANNER (bottom-floating card) ════════ -->
    <Transition
        enter-active-class="transition-all duration-400 ease-out"
        enter-from-class="translate-y-12 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-4 opacity-0"
    >
        <div v-if="showInstallBanner"
             class="fixed inset-x-3 z-[55] mx-auto max-w-md rounded-2xl bg-gradient-to-br from-emerald-600 to-emerald-800 text-white shadow-2xl p-4 flex items-center gap-3"
             style="bottom: calc(96px + env(safe-area-inset-bottom));">
            <div class="w-11 h-11 rounded-xl bg-white/15 backdrop-blur flex items-center justify-center shrink-0">
                <ArrowDownTrayIcon class="w-5 h-5" />
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-bold leading-tight">Install GBHSS Skardu</div>
                <div class="text-[11.5px] text-emerald-100 leading-snug mt-0.5">
                    Add to home screen for offline access &amp; faster loading.
                </div>
            </div>
            <button @click="installApp"
                    class="shrink-0 px-3 py-2 rounded-xl bg-white text-emerald-700 text-xs font-bold hover:bg-emerald-50 active:scale-95 transition-transform">
                Install
            </button>
            <button @click="dismissInstall"
                    class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-white/70 hover:bg-white/10 active:scale-95 transition-transform"
                    aria-label="Dismiss">
                <XMarkIcon class="w-4 h-4" />
            </button>
        </div>
    </Transition>

    <!-- ════════ UPDATE PROMPT (sw-update available) ════════ -->
    <Transition
        enter-active-class="transition-all duration-400 ease-out"
        enter-from-class="translate-y-12 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-4 opacity-0"
    >
        <div v-if="needRefresh"
             class="fixed inset-x-3 z-[55] mx-auto max-w-md rounded-2xl bg-base-100 border border-base-200 shadow-2xl p-4 flex items-center gap-3"
             style="bottom: calc(96px + env(safe-area-inset-bottom));">
            <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                <ArrowPathIcon class="w-5 h-5" />
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-bold leading-tight">New version available</div>
                <div class="text-[11.5px] text-base-content/60 leading-snug mt-0.5">
                    Reload to get the latest features and fixes.
                </div>
            </div>
            <button @click="applyUpdate"
                    class="shrink-0 px-3 py-2 rounded-xl bg-primary text-primary-content text-xs font-bold hover:bg-primary/90 active:scale-95 transition-transform">
                Reload
            </button>
            <button @click="dismissUpdate"
                    class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-base-content/50 hover:bg-base-200 active:scale-95 transition-transform"
                    aria-label="Dismiss">
                <XMarkIcon class="w-4 h-4" />
            </button>
        </div>
    </Transition>
</template>
