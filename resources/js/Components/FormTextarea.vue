<script setup>
import { computed } from 'vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: '' },
    rows: { type: [String, Number], default: 3 },
    error: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    helpText: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])
const textareaId = computed(() => `ft-${props.label?.toLowerCase().replace(/\s+/g, '-') || Math.random().toString(36).slice(2)}`)
</script>

<template>
    <div class="w-full">
        <label v-if="label" :for="textareaId" class="mb-1.5 flex items-center gap-1 text-[12px] font-semibold text-base-content/75">
            {{ label }}
            <span v-if="required" class="text-error">*</span>
        </label>
        <textarea
            :id="textareaId"
            :value="modelValue"
            :rows="rows"
            :placeholder="placeholder"
            :required="required"
            :disabled="disabled"
            class="textarea textarea-bordered w-full text-sm"
            :class="{ 'textarea-error': error }"
            @input="emit('update:modelValue', $event.target.value)"
        ></textarea>
        <p v-if="error" class="mt-1.5 text-xs text-error">{{ error }}</p>
        <p v-else-if="helpText" class="mt-1.5 text-xs text-base-content/45">{{ helpText }}</p>
    </div>
</template>
