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

// ──────────────── Precache (filled by Vite at build time) ────────────────
precacheAndRoute(self.__WB_MANIFEST || [])
cleanupOutdatedCaches()

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
registerRoute(
    ({ url }) => url.pathname.startsWith('/storage/'),
    new StaleWhileRevalidate({
        cacheName: 'user-uploads',
        plugins: [new ExpirationPlugin({ maxEntries: 200, maxAgeSeconds: 60 * 60 * 24 * 14 })],
    })
)

// HTML page navigations — try network, fall back to cache. 4s timeout
// keeps things snappy on flaky mobile. If both network AND cache fail,
// hand back the precached /offline.html as a friendly fallback.
const navStrategy = new NetworkFirst({
    cacheName: 'pages',
    networkTimeoutSeconds: 4,
    plugins: [new ExpirationPlugin({ maxEntries: 50, maxAgeSeconds: 60 * 60 * 24 })],
})

registerRoute(
    new NavigationRoute(
        async (args) => {
            try {
                return await navStrategy.handle(args)
            } catch (e) {
                const cache = await caches.open('pages')
                const offline = await cache.match('/offline')
                return offline || new Response('You are offline.', {
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
        request.headers.get('x-inertia') === 'true' ||
        request.headers.get('X-Inertia') === 'true',
    async (args) => {
        try {
            return await inertiaStrategy.handle(args)
        } catch (e) {
            // Both network AND cache miss. Return a synthetic Inertia
            // response telling the SPA to navigate to /offline so the
            // user sees a friendly screen instead of a hard error.
            return new Response(
                JSON.stringify({
                    component: 'Offline',
                    props: { url: args.request.url },
                    url: '/offline',
                    version: null,
                }),
                {
                    status: 200,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Inertia': 'true',
                    },
                }
            )
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

// Allow the page to trigger SKIP_WAITING when the user accepts an update.
self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') self.skipWaiting()
})
