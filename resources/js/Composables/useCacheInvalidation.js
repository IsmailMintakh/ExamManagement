/**
 * Tiny helper to ask the service worker to drop its page cache after a
 * mutation (form save, file upload, delete, etc.).
 *
 * Why we need this:
 *   The SW caches GET HTML/Inertia responses to enable offline. After an
 *   upload (e.g. new student photo), the next visit to the same page
 *   would serve the cached version — showing the OLD photo URL — even
 *   though the server has the new one.
 *
 * Usage in a form's onSuccess callback:
 *   import { invalidatePageCache } from '@/Composables/useCacheInvalidation'
 *   form.post(url, {
 *       onSuccess: () => invalidatePageCache({ alsoUploads: true })
 *   })
 *
 * Safe to call on browsers without service workers (becomes a no-op).
 */
export function invalidatePageCache({ alsoUploads = false } = {}) {
    if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return
    if (!navigator.serviceWorker.controller) return

    navigator.serviceWorker.controller.postMessage({
        type: 'INVALIDATE_PAGES',
        alsoUploads,
    })
}
