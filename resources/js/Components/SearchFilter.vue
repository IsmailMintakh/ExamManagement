<script setup>
import { ref, watch } from 'vue'
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Search...' },
    filters: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'filter'])

const search = ref(props.modelValue)
const activeFilters = ref({})
let debounceTimer = null

props.filters.forEach((f) => { activeFilters.value[f.key] = '' })

watch(() => props.modelValue, (val) => { search.value = val })

function onSearchInput() {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => { emit('update:modelValue', search.value) }, 300)
}

function onFilterChange(key, value) {
    activeFilters.value[key] = value
    emit('filter', { ...activeFilters.value })
}
</script>

<template>
    <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-base-content/35" />
            <input
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
