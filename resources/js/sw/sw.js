/**
 * Custom service worker for the GBHSS Skardu PWA.
 *
 * Compiled by `vite-plugin-pwa` in injectManifest mode. The plugin
 * replaces `self.__WB_MANIFEST` with the precache manifest at build
 * time and bundles workbox helpers.
 *
 * What this SW does:
 *   1. Precaches the app shell (CSS, JS, icons, manifest) — files
 *      survive offline and load instantly.
 *   2. Runtime caching for /build/ assets, /storage/ uploads, and
 *      navigation HTML — lets the app boot offline.
 *   3. `push` event — receives encrypted server pushes and shows
 *      a native-style notification.
 *   4. `notificationclick` — opens the linked URL or focuses an
 *      existing tab.
 */

import { precacheAndRoute, cleanupOutdatedCaches } from 'workbox-precaching'
import { registerRoute, NavigationRoute, setDefaultHandler } from 'workbox-routing'
import { CacheFirst, NetworkFirst, StaleWhileRevalidate } from 'workbox-strategies'
import { ExpirationPlugin } from 'workbox-expiration'
import { CacheableResponsePlugin } from 'workbox-cacheable-response'

// ──────────────── Precache (filled by Vite at build time) ────────────────
precacheAndRoute(self.__WB_MANIFEST || [])
cleanupOutdatedCaches()

// ──────────────── Bypass SW for non-GET requests ────────────────
// File uploads, form submissions, and any mutation must go straight to
// the network. registerRoute() with a method:'GET' filter is the cleanest
// way: every other registerRoute below also uses GET by default, so non-GET
// requests fall through Workbox entirely (handled by the browser directly).
//
// Critical for PWA file uploads — multipart/form-data (e.g. student photos)
// gets corrupted if a Workbox strategy clones or re-issues the request.

// ──────────────── Runtime caching ────────────────
// Build assets — instant from cache, refresh in background.
registerRoute(
    ({ url }) => url.pathname.startsWith('/build/'),
    new CacheFirst({
        cacheName: 'static-assets',
        plugins: [new ExpirationPlugin({ maxEntries: 200, maxAgeSeconds: 60 * 60 * 24 * 30 })],
    })
)

// Public uploads (logos, photos, news, gallery).
//
// Match BOTH /storage/* (symlink-served) and /uploads/* (Laravel-route-served).
// CacheableResponsePlugin filters out 404/500 responses so a transient
// missing-file response doesn't stick in the cache.
const uploadsRouteHandler = new StaleWhileRevalidate({
    cacheName: 'user-uploads',
    plugins: [
        new ExpirationPlugin({ maxEntries: 200, maxAgeSeconds: 60 * 60 * 24 * 14 }),
        new CacheableResponsePlugin({ statuses: [0, 200] }),  // skip 4xx/5xx
    ],
})

registerRoute(
    ({ url, request }) => request.method === 'GET' && (
        url.pathname.startsWith('/storage/') ||
        url.pathname.startsWith('/uploads/')
    ),
    uploadsRouteHandler
)

// HTML page navigations — try network, fall back to cache. 4s timeout
// keeps things snappy on flaky mobile. If both network AND cache fail,
// hand back the precached /offline.html as a friendly fallback.
const navStrategy = new NetworkFirst({
    cacheName: 'pages',
    networkTimeoutSeconds: 4,
    plugins: [new ExpirationPlugin({ maxEntries: 50, maxAgeSeconds: 60 * 60 * 24 })],
})

// Static HTML offline shell — always precached, always available.
// We import it as a precache URL so Workbox includes it in __WB_MANIFEST.
import { matchPrecache } from 'workbox-precaching'

registerRoute(
    new NavigationRoute(
        async (args) => {
            try {
                return await navStrategy.handle(args)
            } catch (e) {
                // Try the cached version of /dashboard first (most useful).
                const cache = await caches.open('pages')
                const dashboard = await cache.match('/dashboard')
                if (dashboard) return dashboard

                // Fall back to the static offline.html shell — precached
                // so it's ALWAYS available, even on the very first offline open.
                const offline = await matchPrecache('/offline.html')
                if (offline) return offline

                return new Response('You are offline. Reconnect and try again.', {
                    status: 503,
                    headers: { 'Content-Type': 'text/plain' },
                })
            }
        },
        {
            denylist: [
                /^\/build\//,
                /^\/storage\//,
                /^\/api\//,
                /^\/login/,
                /^\/logout/,
                /^\/register/,
                /^\/_debugbar/,
            ],
        }
    )
)

/**
 * INERTIA REQUESTS — the critical bit for offline.
 *
 * Inertia internal navigations (clicking a <Link>) are XHR, NOT navigations.
 * They're identified by the `X-Inertia: true` request header. Workbox's
 * NavigationRoute matcher misses them, so without this handler, internal
 * page clicks would always fail offline.
 *
 * Strategy: NetworkFirst (3s timeout) → cache → fake offline Inertia
 * response that redirects to /offline.
 */
const inertiaStrategy = new NetworkFirst({
    cacheName: 'inertia-pages',
    networkTimeoutSeconds: 3,
    plugins: [new ExpirationPlugin({ maxEntries: 80, maxAgeSeconds: 60 * 60 * 24 })],
})

registerRoute(
    ({ request }) =>
        // GET only — POST/PUT/PATCH/DELETE Inertia requests (form submits,
        // file uploads) must go straight to network without SW interference.
        request.method === 'GET' && (
            request.headers.get('x-inertia') === 'true' ||
            request.headers.get('X-Inertia') === 'true'
        ),
    async (args) => {
        try {
            return await inertiaStrategy.handle(args)
        } catch (e) {
            // Inertia XHR with no cached response and no network.
            // Returning a 409 with X-Inertia-Location triggers Inertia to
            // do a full page reload at the given URL — which the SW will
            // intercept again as a navigation, ending up at /offline.html.
            return new Response('', {
                status: 409,
                headers: {
                    'X-Inertia-Location': '/offline.html',
                },
            })
        }
    }
)

// External fonts.
registerRoute(
    ({ url }) =>
        url.origin === 'https://fonts.googleapis.com' ||
        url.origin === 'https://fonts.gstatic.com' ||
        url.origin === 'https://fonts.bunny.net',
    new StaleWhileRevalidate({
        cacheName: 'fonts',
        plugins: [new ExpirationPlugin({ maxEntries: 30, maxAgeSeconds: 60 * 60 * 24 * 365 })],
    })
)

// ──────────────── Push notifications ────────────────
/**
 * Server pushes a JSON payload like:
 *   { title, body, icon, badge, tag, url, image, actions: [{action, title}], data }
 *
 * We unpack it and call self.registration.showNotification with sensible
 * defaults so even minimal payloads still display nicely.
 */
self.addEventListener('push', (event) => {
    let payload = {}
    try {
        payload = event.data ? event.data.json() : {}
    } catch (e) {
        // Fallback for plain-text payloads
        payload = { title: 'GBHSS Skardu', body: event.data?.text() || 'You have a new notification.' }
    }

    const title = payload.title || 'GBHSS Skardu'
    const options = {
        body:        payload.body || '',
        icon:        payload.icon || '/pwa-192x192.png',
        badge:       payload.badge || '/pwa-64x64.png',
        image:       payload.image,
        tag:         payload.tag,                    // pushes with same tag replace each other
        renotify:    !!payload.tag,                  // re-alert if same tag
        requireInteraction: payload.requireInteraction || false,
        actions:     payload.actions || [],
        data:        {
            url: payload.url || '/',
            ...(payload.data || {}),
        },
        vibrate:     [200, 100, 200],                // Android: gentle buzz
    }

    event.waitUntil(self.registration.showNotification(title, options))
})

/**
 * User tapped the notification (or one of its action buttons).
 * Focus an existing window of our app if open; otherwise open a new one
 * pointed at the URL embedded in the notification's data payload.
 */
self.addEventListener('notificationclick', (event) => {
    event.notification.close()

    // Action button-specific URL takes precedence over the default URL
    let targetUrl = event.notification.data?.url || '/'
    if (event.action && event.notification.data?.actions?.[event.action]) {
        targetUrl = event.notification.data.actions[event.action]
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            // Prefer focusing an existing window already on the target URL
            for (const win of windows) {
                if (win.url.includes(targetUrl) && 'focus' in win) return win.focus()
            }
            // Else focus the first window and navigate it
            for (const win of windows) {
                if ('navigate' in win && 'focus' in win) {
                    return win.navigate(targetUrl).then(() => win.focus())
                }
            }
            // Else open a new tab
            return clients.openWindow(targetUrl)
        })
    )
})

// Messages from the page:
//   SKIP_WAITING — adopt the new SW immediately (called after user clicks
//                  "Reload" on the update prompt)
//   INVALIDATE_PAGES — clear cached HTML/Inertia pages. Call after a
//                  successful save/update so the next page load fetches
//                  fresh data (e.g. the newly uploaded student photo).
self.addEventListener('message', async (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting()
    }
    if (event.data?.type === 'INVALIDATE_PAGES') {
        await Promise.all([
            caches.delete('pages'),
            caches.delete('inertia-pages'),
        ])
        // Also clear cached uploads so re-uploaded files (same URL? rare,
        // but possible) are re-fetched.
        if (event.data?.alsoUploads) {
            await caches.delete('user-uploads')
        }
    }
})

// Take control of all open tabs immediately on activation, so the new SW
// version doesn't sit waiting for every tab to be closed before kicking in.
// Combined with skipWaiting (triggered by the user via the update prompt),
// this gives a snappy update flow.
self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim())
})
