<script setup>
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, default: '' },
    description: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    size: { type: String, default: 'md' }, // sm | md | lg
})

const emit = defineEmits(['update:modelValue'])

const sizeMap = {
    sm: { track: 'h-5 w-9', thumb: 'h-4 w-4', translate: 'translate-x-4' },
    md: { track: 'h-6 w-11', thumb: 'h-5 w-5', translate: 'translate-x-5' },
    lg: { track: 'h-7 w-12', thumb: 'h-6 w-6', translate: 'translate-x-5' },
}

function toggle() {
    if (!props.disabled) emit('update:modelValue', !props.modelValue)
}
</script>

<template>
    <label class="flex cursor-pointer items-start gap-3" :class="{ 'cursor-not-allowed opacity-50': disabled }">
        <button
            type="button"
            role="switch"
            :aria-checked="modelValue"
            :disabled="disabled"
            @click="toggle"
            class="relative inline-flex shrink-0 rounded-full border-2 border-transparent focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
            :class="[
                sizeMap[size].track,
                modelValue ? 'bg-primary' : 'bg-base-300',
            ]"
            style="transition: background-color 0.2s cubic-bezier(0.16, 1, 0.3, 1);"
        >
            <span
                class="inline-block transform rounded-full bg-white shadow ring-0"
                :class="[sizeMap[size].thumb, modelValue ? sizeMap[size].translate : 'translate-x-0']"
                style="transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);"
            />
        </button>
        <div v-if="label || description" class="select-none">
            <p v-if="label" class="text-sm font-semibold leading-tight">{{ label }}</p>
            <p v-if="description" class="mt-0.5 text-xs text-base-content/55">{{ description }}</p>
        </div>
    </label>
</template>
