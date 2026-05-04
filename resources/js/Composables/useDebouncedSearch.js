import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

/**
 * Debounced search input bound to an Inertia partial reload.
 *
 * Why debounce: each keystroke would otherwise fire a full controller/SQL hit.
 * 300ms is the sweet spot — fast enough to feel live, slow enough that typing
 * "first term examination" sends one request, not 21.
 *
 * Usage:
 *   const search = useDebouncedSearch({
 *       routeName: 'exams.index',
 *       initial: filters?.search,
 *       only: ['exams', 'filters'],   // partial reload — keep the layout cached
 *   })
 *   <input v-model="search.value" />
 */
export function useDebouncedSearch({ routeName, initial = '', only = null, delay = 300, extra = () => ({}) }) {
    const value = ref(initial)
    let timer = null

    watch(value, (val) => {
        if (timer) clearTimeout(timer)
        timer = setTimeout(() => {
            const params = { search: val || undefined, ...extra() }
            router.get(route(routeName), params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: only || undefined,
            })
        }, delay)
    })

    return value
}
