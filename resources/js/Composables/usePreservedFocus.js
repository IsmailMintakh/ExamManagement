import { nextTick, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'

/**
 * Keeps an input focused across Inertia partial reloads.
 *
 * Any search input that triggers `router.get()` (directly or via
 * useDebouncedSearch) can lose focus when the response arrives and Vue
 * re-renders. Snapshotting focus + caret on router.on('start') and
 * restoring on router.on('finish') is a reliable fix that works
 * regardless of whether the parent component patches or briefly swaps
 * the input node.
 *
 * Usage:
 *   const searchInput = ref(null)
 *   usePreservedFocus(searchInput)
 *   ...
 *   <input ref="searchInput" v-model="search" />
 *
 * Requires the AppLayout fix that keys content by pathname (not full URL)
 * so the input isn't unmounted between start and finish — see AppLayout.vue
 * `pagePathKey`. This composable is the second layer of belt-and-braces
 * protection for cases where the parent still triggers a wider re-render.
 */
export function usePreservedFocus(inputRef) {
    let hadFocus = false
    let caretPos = 0
    let selectionEnd = 0
    let unsubStart = null
    let unsubFinish = null

    onMounted(() => {
        unsubStart = router.on('start', () => {
            hadFocus = document.activeElement === inputRef.value
            if (hadFocus && inputRef.value) {
                caretPos = inputRef.value.selectionStart ?? inputRef.value.value.length
                selectionEnd = inputRef.value.selectionEnd ?? caretPos
            }
        })

        unsubFinish = router.on('finish', async () => {
            if (!hadFocus) return
            await nextTick()
            requestAnimationFrame(() => {
                const el = inputRef.value
                if (el && document.activeElement !== el) {
                    el.focus()
                    try { el.setSelectionRange(caretPos, selectionEnd) } catch (e) { /* ignore */ }
                }
                hadFocus = false
            })
        })
    })

    onBeforeUnmount(() => {
        if (typeof unsubStart === 'function') unsubStart()
        if (typeof unsubFinish === 'function') unsubFinish()
    })
}
