<script setup>
import { computed } from 'vue'

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
})

const emit = defineEmits(['update:modelValue'])

const selectId = computed(() => `fs-${props.label?.toLowerCase().replace(/\s+/g, '-') || Math.random().toString(36).slice(2)}`)

const sizeClasses = {
    sm: 'select-sm text-xs',
    md: 'text-sm',
    lg: 'select-lg text-base',
}

function onChange(e) {
    if (props.multiple) {
        emit('update:modelValue', Array.from(e.target.selectedOptions, (opt) => opt.value))
    } else {
        emit('update:modelValue', e.target.value)
    }
}
</script>

<template>
    <div class="w-full">
        <label v-if="label" :for="selectId" class="mb-1.5 flex items-center gap-1 text-[12px] font-semibold text-base-content/75">
            {{ label }}
            <span v-if="required" class="text-error">*</span>
        </label>
        <select
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
