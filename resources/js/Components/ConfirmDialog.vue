<script setup>
import { computed } from 'vue'
import { ExclamationTriangleIcon, InformationCircleIcon, ShieldExclamationIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Confirm Action' },
    message: { type: String, default: 'Are you sure you want to proceed?' },
    confirmText: { type: String, default: 'Confirm' },
    cancelText: { type: String, default: 'Cancel' },
    type: { type: String, default: 'warning', validator: (v) => ['danger', 'warning', 'info'].includes(v) },
})

const emit = defineEmits(['confirm', 'cancel'])

const cfg = computed(() => ({
    danger: { icon: ShieldExclamationIcon, iconBg: 'bg-error/10', iconText: 'text-error', btn: 'btn-error', glow: 'shadow-error/30' },
    warning: { icon: ExclamationTriangleIcon, iconBg: 'bg-warning/10', iconText: 'text-warning', btn: 'btn-warning', glow: 'shadow-warning/30' },
    info: { icon: InformationCircleIcon, iconBg: 'bg-info/10', iconText: 'text-info', btn: 'btn-info', glow: 'shadow-info/30' },
}[props.type]))
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
            <div v-if="show" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="emit('cancel')" />
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                >
                    <div v-if="show" class="relative z-10 w-full max-w-sm overflow-hidden rounded-2xl bg-base-100 shadow-2xl" style="border: 1px solid oklch(var(--bc) / 0.1);">
                        <div class="p-6 text-center">
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl shadow-lg" :class="[cfg.iconBg, cfg.glow]">
                                <component :is="cfg.icon" class="h-7 w-7" :class="cfg.iconText" />
                            </div>
                            <h3 class="text-lg font-bold">{{ title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-base-content/60">{{ message }}</p>
                        </div>
                        <div class="flex gap-2 px-6 py-4" style="border-top: 1px solid oklch(var(--bc) / 0.06); background: oklch(var(--bc) / 0.02);">
                            <button class="btn btn-ghost flex-1" @click="emit('cancel')">{{ cancelText }}</button>
                            <button class="btn flex-1" :class="cfg.btn" @click="emit('confirm')">{{ confirmText }}</button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
