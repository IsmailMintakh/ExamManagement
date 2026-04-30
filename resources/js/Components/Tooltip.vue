<script setup>
import { ref } from 'vue'

defineProps({
    content: { type: String, required: true },
    position: { type: String, default: 'top' }, // top | bottom | left | right
})

const show = ref(false)
let timeout = null

function showTip() {
    timeout = setTimeout(() => { show.value = true }, 300)
}
function hideTip() {
    clearTimeout(timeout)
    show.value = false
}

const positionClasses = {
    top: 'bottom-full left-1/2 -translate-x-1/2 mb-2',
    bottom: 'top-full left-1/2 -translate-x-1/2 mt-2',
    left: 'right-full top-1/2 -translate-y-1/2 mr-2',
    right: 'left-full top-1/2 -translate-y-1/2 ml-2',
}
</script>

<template>
    <span class="relative inline-flex" @mouseenter="showTip" @mouseleave="hideTip" @focusin="showTip" @focusout="hideTip">
        <slot />
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-90"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <span v-if="show"
                class="pointer-events-none absolute z-[100] whitespace-nowrap rounded-md px-2 py-1 text-[11px] font-semibold shadow-lg"
                :class="positionClasses[position]"
                style="background: oklch(var(--n)); color: oklch(var(--nc));"
            >
                {{ content }}
            </span>
        </Transition>
    </span>
</template>
