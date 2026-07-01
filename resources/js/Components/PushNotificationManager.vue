<script setup>
/**
 * Push notification permission UI — drop into a profile / settings page.
 *
 * State machine:
 *   - 'unsupported'    — browser/device can't do push (iOS Safari < 16.4 etc.)
 *   - 'permission_denied' — user has previously blocked notifications
 *   - 'idle'           — eligible but not yet subscribed
 *   - 'subscribing'    — in progress
 *   - 'subscribed'     — currently active on this device
 *   - 'error'          — something went wrong (msg in `errorMsg`)
 *
 * Why this is a component, not a global modal:
 * we don't want to nag users on every page. Profile/Settings is the
 * appropriate place — they came here to change preferences.
 */
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import {
    BellAlertIcon, BellSlashIcon, CheckCircleIcon, XCircleIcon,
    ExclamationTriangleIcon, PaperAirplaneIcon,
} from '@heroicons/vue/24/outline'

const status = ref('idle')
const errorMsg = ref('')
const subscription = ref(null)        // current PushSubscription object
const sendingTest = ref(false)
const lastTestResult = ref(null)      // { ok: bool, msg: string }

// ─── Browser capability check ────────────────────────────
const isSupported = computed(() =>
    typeof window !== 'undefined' &&
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'Notification' in window
)

// VAPID public key in URL-safe base64 needs to be converted to a Uint8Array
// before passing to subscribe().
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
    const rawData = atob(base64)
    return Uint8Array.from([...rawData].map((c) => c.charCodeAt(0)))
}

// ─── Initial state probe ────────────────────────────
async function refreshState() {
    if (!isSupported.value) {
        status.value = 'unsupported'
        return
    }
    if (Notification.permission === 'denied') {
        status.value = 'permission_denied'
        return
    }
    try {
        const reg = await navigator.serviceWorker.ready
        const sub = await reg.pushManager.getSubscription()
        subscription.value = sub
        status.value = sub ? 'subscribed' : 'idle'
    } catch (e) {
        // SW not registered yet (e.g. dev mode) — show as idle, will work after deploy
        status.value = 'idle'
    }
}

onMounted(refreshState)

// ─── Subscribe ────────────────────────────
async function enable() {
    if (!isSupported.value) return
    status.value = 'subscribing'
    errorMsg.value = ''

    try {
        const permission = await Notification.requestPermission()
        if (permission !== 'granted') {
            status.value = permission === 'denied' ? 'permission_denied' : 'idle'
            return
        }

        // Fetch our VAPID public key from the server
        const { data } = await axios.get(route('push.public-key'))
        const applicationServerKey = urlBase64ToUint8Array(data.public_key)

        // Subscribe via the browser's PushManager
        const reg = await navigator.serviceWorker.ready
        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey,
        })

        // Hand the subscription details to our backend
        const subJson = sub.toJSON()
        await axios.post(route('push.subscribe'), {
            endpoint:         subJson.endpoint,
            public_key:       subJson.keys.p256dh,
            auth_token:       subJson.keys.auth,
            content_encoding: PushManager.supportedContentEncodings?.[0] || 'aesgcm',
        })

        subscription.value = sub
        status.value = 'subscribed'
    } catch (e) {
        console.error('Push subscribe failed', e)
        errorMsg.value = e.message || 'Could not enable notifications.'
        status.value = 'error'
    }
}

// ─── Unsubscribe ────────────────────────────
async function disable() {
    if (!subscription.value) return
    try {
        const endpoint = subscription.value.endpoint
        await subscription.value.unsubscribe()
        await axios.post(route('push.unsubscribe'), { endpoint })
        subscription.value = null
        status.value = 'idle'
    } catch (e) {
        console.error('Push unsubscribe failed', e)
        errorMsg.value = e.message || 'Could not disable notifications.'
        status.value = 'error'
    }
}

// ─── Send test notification ────────────────────────────
async function sendTest() {
    sendingTest.value = true
    lastTestResult.value = null
    try {
        const { data } = await axios.post(route('push.test'))
        lastTestResult.value = {
            ok: true,
            msg: `Sent to ${data.sent_to_devices} device(s). Check your screen — if no notification arrived, check browser permissions.`,
        }
    } catch (e) {
        lastTestResult.value = {
            ok: false,
            msg: e.response?.data?.error || e.message || "Test didn't send — tap to retry.",
        }
    } finally {
        sendingTest.value = false
        setTimeout(() => { lastTestResult.value = null }, 8000)
    }
}
</script>

<template>
    <div class="card bg-base-100 shadow-sm border border-base-200">
        <div class="card-body p-5 sm:p-6 space-y-4">
            <div class="flex items-start gap-4">
                <div class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0"
                     :class="status === 'subscribed' ? 'bg-emerald-100 text-emerald-700' : 'bg-base-200 text-base-content/60'">
                    <BellAlertIcon v-if="status === 'subscribed'" class="w-6 h-6" />
                    <BellSlashIcon v-else class="w-6 h-6" />
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold">Browser Notifications</h3>
                    <p class="text-xs text-base-content/60 mt-1 leading-relaxed">
                        Get instant alerts on this device when results are approved, marks are returned for correction,
                        new messages arrive, or other important events happen. Works even when the browser is closed.
                    </p>
                </div>
            </div>

            <!-- Status-driven UI -->
            <div v-if="status === 'unsupported'"
                 class="rounded-xl bg-amber-50 border border-amber-200 p-3 flex items-start gap-2.5 text-xs text-amber-900">
                <ExclamationTriangleIcon class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                <div>
                    <div class="font-bold">This device doesn't support push notifications</div>
                    <p class="mt-1 text-amber-900/80">
                        Try a recent version of Chrome, Edge, Firefox, or Safari (iOS 16.4+).
                        On iPhone, you must <b>install this app to your home screen first</b> (Share → Add to Home Screen),
                        then return here to enable notifications.
                    </p>
                </div>
            </div>

            <div v-else-if="status === 'permission_denied'"
                 class="rounded-xl bg-rose-50 border border-rose-200 p-3 flex items-start gap-2.5 text-xs text-rose-900">
                <XCircleIcon class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                <div>
                    <div class="font-bold">Notifications are blocked for this site</div>
                    <p class="mt-1 text-rose-900/80">
                        To re-enable: tap the lock icon in your browser's address bar →
                        Notifications → Allow. Then refresh this page.
                    </p>
                </div>
            </div>

            <div v-else-if="status === 'subscribed'"
                 class="rounded-xl bg-emerald-50 border border-emerald-200 p-3 flex items-start gap-2.5 text-xs text-emerald-900">
                <CheckCircleIcon class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                <div>
                    <div class="font-bold">Notifications are enabled on this device</div>
                    <p class="mt-1 text-emerald-900/80">
                        You'll receive alerts here even when the app is closed.
                    </p>
                </div>
            </div>

            <div v-else-if="status === 'error'"
                 class="rounded-xl bg-rose-50 border border-rose-200 p-3 flex items-start gap-2.5 text-xs text-rose-900">
                <XCircleIcon class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                <div>
                    <div class="font-bold">Something went wrong</div>
                    <p class="mt-1 text-rose-900/80">{{ errorMsg }}</p>
                </div>
            </div>

            <!-- Action row -->
            <div class="flex flex-wrap items-center gap-2">
                <button v-if="status === 'idle' || status === 'error'"
                    @click="enable"
                    :disabled="!isSupported"
                    class="btn btn-primary btn-sm gap-1.5 rounded-xl"
                    :class="{ 'btn-disabled': !isSupported }">
                    <BellAlertIcon class="w-4 h-4" /> Enable Notifications
                </button>

                <button v-if="status === 'subscribing'"
                    disabled
                    class="btn btn-primary btn-sm gap-1.5 rounded-xl loading">
                    Enabling…
                </button>

                <template v-if="status === 'subscribed'">
                    <button @click="sendTest" :disabled="sendingTest"
                        class="btn btn-outline btn-sm gap-1.5 rounded-xl">
                        <PaperAirplaneIcon class="w-4 h-4" />
                        {{ sendingTest ? 'Sending…' : 'Send Test' }}
                    </button>
                    <button @click="disable"
                        class="btn btn-ghost btn-sm gap-1.5 rounded-xl text-base-content/65 hover:text-error">
                        <BellSlashIcon class="w-4 h-4" /> Turn Off
                    </button>
                </template>
            </div>

            <!-- Test result toast -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="lastTestResult"
                    class="rounded-xl p-3 flex items-start gap-2 text-xs"
                    :class="lastTestResult.ok ? 'bg-emerald-50 border border-emerald-200 text-emerald-900' : 'bg-rose-50 border border-rose-200 text-rose-900'">
                    <CheckCircleIcon v-if="lastTestResult.ok" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                    <XCircleIcon v-else class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                    <span>{{ lastTestResult.msg }}</span>
                </div>
            </Transition>
        </div>
    </div>
</template>
