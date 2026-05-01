<script setup>
/**
 * Modal — centered card on desktop, **bottom sheet** on mobile.
 *
 * On screens below sm, the modal slides up from the bottom of the screen
 * (like Apple Maps / Spotify), pinned to the bottom edge with a drag-down
 * handle and rounded top corners. Tapping the handle or backdrop closes
 * the modal. This is far more thumb-friendly than a centered card.
 *
 * On sm+ it falls back to the original centered modal.
 */
import { computed, onMounted, onUnmounted, watch, ref } from 'vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
        validator: (v) => ['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl'].includes(v),
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);

const close = () => {
    if (props.closeable) emit('close');
};

const closeOnEscape = (e) => {
    if (e.key === 'Escape' && props.show) {
        e.preventDefault();
        close();
    }
};

watch(
    () => props.show,
    (val) => {
        document.body.style.overflow = val ? 'hidden' : '';
    },
);

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    document.body.style.overflow = '';
});

const maxWidthClass = computed(() => ({
    sm:    'sm:max-w-sm',
    md:    'sm:max-w-md',
    lg:    'sm:max-w-lg',
    xl:    'sm:max-w-xl',
    '2xl': 'sm:max-w-2xl',
    '3xl': 'sm:max-w-3xl',
    '4xl': 'sm:max-w-4xl',
}[props.maxWidth]));

// ─── Drag-to-dismiss for the mobile sheet ────────────────────
const sheetEl = ref(null)
const dragY = ref(0)
let dragStartY = 0
let dragging = false

function onTouchStart(e) {
    dragging = true
    dragStartY = e.touches[0].clientY
    dragY.value = 0
}
function onTouchMove(e) {
    if (!dragging) return
    const delta = e.touches[0].clientY - dragStartY
    if (delta > 0) dragY.value = delta
}
function onTouchEnd() {
    dragging = false
    if (dragY.value > 100) {
        close()
    }
    dragY.value = 0
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center overflow-hidden">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="close"></div>

                <!-- Modal content — bottom sheet on mobile, centered card on sm+ -->
                <Transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="translate-y-full sm:translate-y-0 sm:scale-95 sm:opacity-0"
                    enter-to-class="translate-y-0 sm:scale-100 sm:opacity-100"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="translate-y-0 sm:scale-100 sm:opacity-100"
                    leave-to-class="translate-y-full sm:translate-y-0 sm:scale-95 sm:opacity-0"
                >
                    <div
                        v-if="show"
                        ref="sheetEl"
                        class="relative z-10 w-full bg-base-100 shadow-2xl flex flex-col
                               max-h-[92vh] sm:max-h-[88vh]
                               rounded-t-3xl sm:rounded-2xl"
                        :class="maxWidthClass"
                        :style="dragY ? `transform: translateY(${dragY}px); transition: none;` : ''"
                    >
                        <!-- Drag handle (mobile only) -->
                        <div
                            class="sm:hidden flex flex-col items-center pt-2.5 pb-1 cursor-grab active:cursor-grabbing select-none touch-none"
                            @touchstart="onTouchStart"
                            @touchmove.prevent="onTouchMove"
                            @touchend="onTouchEnd"
                        >
                            <div class="w-11 h-1.5 rounded-full bg-base-300"></div>
                        </div>

                        <!-- Close button (top-right) -->
                        <button
                            v-if="closeable"
                            type="button"
                            class="hidden sm:inline-flex btn btn-ghost btn-sm btn-circle absolute right-3 top-3 z-20"
                            @click="close"
                            aria-label="Close"
                        >
                            <XMarkIcon class="h-5 w-5" />
                        </button>

                        <!-- Body — scrollable inner area -->
                        <div class="flex-1 overflow-y-auto overscroll-contain">
                            <slot />
                        </div>

                        <!-- Footer (sticks at bottom of sheet) -->
                        <div v-if="$slots.footer" class="border-t border-base-300 px-6 py-4 shrink-0"
                             style="padding-bottom: max(1rem, env(safe-area-inset-bottom));">
                            <slot name="footer" />
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
