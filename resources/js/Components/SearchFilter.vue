<script setup>
import { ref, watch } from 'vue'
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import { usePreservedFocus } from '@/Composables/usePreservedFocus'

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
// avoid redundant reactive round-trips that used to blur the input.
watch(() => props.modelValue, (val) => {
    if (val !== search.value) search.value = val
})

// Emit on every keystroke — the parent's useDebouncedSearch handles
// the 300ms debounce. Double-debouncing here caused 600ms lag and
// double reactive round-trips that fought focus preservation.
function onSearchInput() {
    emit('update:modelValue', search.value)
}

function onFilterChange(key, value) {
    activeFilters.value[key] = value
    emit('filter', { ...activeFilters.value })
}

// Focus preservation across Inertia partial reloads — see composable.
usePreservedFocus(inputEl)
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
