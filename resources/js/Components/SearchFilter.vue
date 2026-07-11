<script setup>
import { ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Search...' },
    filters: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'filter'])

const search = ref(props.modelValue)
const activeFilters = ref({})
const inputEl = ref(null)

props.filters.forEach((f) => { activeFilters.value[f.key] = '' })

// Sync incoming modelValue → local, only when genuinely different, to
// avoid redundant reactive round-trips that used to nudge Vue into
// blurring the input.
watch(() => props.modelValue, (val) => {
    if (val !== search.value) search.value = val
})

// Emit on every keystroke — the parent's useDebouncedSearch handles
// the debounce before firing the Inertia request. A second debounce
// here caused 600ms lag AND double reactive round-trips.
function onSearchInput() {
    emit('update:modelValue', search.value)
}

function onFilterChange(key, value) {
    activeFilters.value[key] = value
    emit('filter', { ...activeFilters.value })
}

// ─── Focus preservation across Inertia partial reloads ───
// When the parent debounce fires and Inertia refetches, the DOM re-renders
// and the input can lose focus. Hooking into the actual router lifecycle
// (not the prop change) means we restore focus AFTER the DOM settles, which
// is when the blur actually happens.
let hadFocus = false
let caretPos = 0
let selectionEnd = 0
let unsubStart = null
let unsubFinish = null

onMounted(() => {
    unsubStart = router.on('start', () => {
        // Snapshot focus + caret at the moment Inertia starts the request,
        // BEFORE any DOM churn from the response can steal it.
        hadFocus = document.activeElement === inputEl.value
        if (hadFocus && inputEl.value) {
            caretPos = inputEl.value.selectionStart ?? inputEl.value.value.length
            selectionEnd = inputEl.value.selectionEnd ?? caretPos
        }
    })

    unsubFinish = router.on('finish', async () => {
        if (!hadFocus) return
        await nextTick()
        // Wait one more paint frame — the empty-state DOM tree can mount
        // AFTER nextTick when the reload changes the visible block.
        requestAnimationFrame(() => {
            const el = inputEl.value
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
</script>

<template>
    <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-base-content/35" />
            <input
                ref="inputEl"
                v-model="search"
                type="text"
                :placeholder="placeholder"
                class="input input-bordered w-full pl-9 text-sm"
                @input="onSearchInput"
            />
        </div>
        <div v-for="filter in filters" :key="filter.key">
            <select
                :value="activeFilters[filter.key]"
                class="select select-bordered text-sm"
                @change="onFilterChange(filter.key, $event.target.value)"
            >
                <option value="">{{ filter.label }}</option>
                <option v-for="opt in filter.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
        </div>
        <slot />
    </div>
</template>
