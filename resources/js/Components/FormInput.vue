<script setup>
import { computed } from 'vue'

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: '' },
    type: { type: String, default: 'text' },
    error: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    helpText: { type: String, default: '' },
    icon: { type: [Object, Function], default: null },
    size: { type: String, default: 'md' }, // sm | md | lg
    // HTML5 constraint passthrough — used by date/number inputs to enforce
    // logical bounds (e.g. end_date min=start_date, dob max=today).
    min: { type: [String, Number], default: null },
    max: { type: [String, Number], default: null },
    step: { type: [String, Number], default: null },
})

const emit = defineEmits(['update:modelValue'])

const inputId = computed(() => `fi-${props.label?.toLowerCase().replace(/\s+/g, '-') || Math.random().toString(36).slice(2)}`)

const sizeClasses = {
    sm: 'input-sm text-xs',
    md: 'text-sm',
    lg: 'input-lg text-base',
}
</script>

<template>
    <div class="w-full">
        <label v-if="label" :for="inputId" class="mb-1.5 flex items-center gap-1 text-[12px] font-semibold text-base-content/75">
            {{ label }}
            <span v-if="required" class="text-error">*</span>
        </label>
        <div class="relative">
            <component v-if="icon" :is="icon" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-base-content/35" />
            <input
                :id="inputId"
                :type="type"
                :value="modelValue"
                :placeholder="placeholder"
                :required="required"
                :disabled="disabled"
                :min="min ?? undefined"
                :max="max ?? undefined"
                :step="step ?? undefined"
                class="input input-bordered w-full"
                :class="[
                    sizeClasses[size],
                    error ? 'input-error' : '',
                    icon ? 'pl-9' : '',
                ]"
                @input="emit('update:modelValue', $event.target.value)"
            />
        </div>
        <p v-if="error" class="mt-1.5 text-xs text-error">{{ error }}</p>
        <p v-else-if="helpText" class="mt-1.5 text-xs text-base-content/45">{{ helpText }}</p>
    </div>
</template>
