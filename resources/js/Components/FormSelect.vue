<script setup>
import { computed } from 'vue'
import SearchableSelect from './SearchableSelect.vue'

const props = defineProps({
    modelValue: { type: [String, Number, Array], default: '' },
    label: { type: String, default: '' },
    options: { type: Array, default: () => [] },
    error: { type: String, default: '' },
    placeholder: { type: String, default: 'Select an option' },
    required: { type: Boolean, default: false },
    multiple: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    helpText: { type: String, default: '' },
    size: { type: String, default: 'md' },
    // When undefined, single-select lists auto-switch to the combobox
    // when there are more than 8 options. Set `searchable="true"` to force on,
    // or `searchable="false"` to force the native `<select>`.
    searchable: { type: [Boolean, String], default: undefined },
})

const emit = defineEmits(['update:modelValue'])

const selectId = computed(() => `fs-${props.label?.toLowerCase().replace(/\s+/g, '-') || Math.random().toString(36).slice(2)}`)

const sizeClasses = {
    sm: 'select-sm text-xs',
    md: 'text-sm',
    lg: 'select-lg text-base',
}

// Auto-decide: long single-select lists get the combobox so the user can
// type to filter instead of scrolling. Multi-select keeps the native
// element (combobox UX for multi is a separate redesign).
const useSearchable = computed(() => {
    if (props.multiple) return false
    if (props.searchable === false || props.searchable === 'false') return false
    if (props.searchable === true || props.searchable === 'true') return true
    return (props.options?.length || 0) > 8
})

function onChange(e) {
    if (props.multiple) {
        emit('update:modelValue', Array.from(e.target.selectedOptions, (opt) => opt.value))
    } else {
        emit('update:modelValue', e.target.value)
    }
}

function onSearchablePick(value) {
    emit('update:modelValue', value)
}
</script>

<template>
    <div class="w-full">
        <label v-if="label" :for="selectId" class="mb-1.5 flex items-center gap-1 text-[12px] font-semibold text-base-content/75">
            {{ label }}
            <span v-if="required" class="text-error">*</span>
        </label>

        <!-- Long single-select → combobox with built-in search. -->
        <SearchableSelect
            v-if="useSearchable"
            :model-value="modelValue"
            :options="options"
            :placeholder="placeholder"
            :disabled="disabled"
            :error="error"
            :size="size === 'md' ? 'md' : size"
            @update:model-value="onSearchablePick"
        />

        <!-- Native select for short lists / multi-select (faster, more familiar). -->
        <select
            v-else
            :id="selectId"
            :value="modelValue"
            :required="required"
            :multiple="multiple"
            :disabled="disabled"
            class="select select-bordered w-full"
            :class="[sizeClasses[size], error ? 'select-error' : '']"
            @change="onChange"
        >
            <option v-if="!multiple" value="" disabled>{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>

        <p v-if="error" class="mt-1.5 text-xs text-error">{{ error }}</p>
        <p v-else-if="helpText" class="mt-1.5 text-xs text-base-content/45">{{ helpText }}</p>
    </div>
</template>
