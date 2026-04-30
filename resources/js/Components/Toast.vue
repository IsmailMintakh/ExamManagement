<script setup>
import { useToast } from '@/Composables/useToast'
import { XMarkIcon, CheckCircleIcon, ExclamationTriangleIcon, InformationCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'

const { toasts, removeToast } = useToast()

const config = {
    success: { icon: CheckCircleIcon, accent: 'border-l-success', iconBg: 'bg-success/10', iconColor: 'text-success' },
    error: { icon: XCircleIcon, accent: 'border-l-error', iconBg: 'bg-error/10', iconColor: 'text-error' },
    warning: { icon: ExclamationTriangleIcon, accent: 'border-l-warning', iconBg: 'bg-warning/10', iconColor: 'text-warning' },
    info: { icon: InformationCircleIcon, accent: 'border-l-info', iconBg: 'bg-info/10', iconColor: 'text-info' },
}

const getConfig = (type) => config[type] || config.info
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed right-4 top-20 z-[100] flex w-full max-w-sm flex-col gap-2 sm:right-6">
            <TransitionGroup
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="translate-x-full opacity-0"
                enter-to-class="translate-x-0 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="translate-x-0 opacity-100"
                leave-to-class="translate-x-full opacity-0"
                move-class="transition-all duration-300"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto flex items-start gap-3 rounded-xl border-l-4 bg-base-100 p-4 shadow-lifted"
                    :class="getConfig(toast.type).accent"
                    style="border-top: 1px solid oklch(var(--bc) / 0.08); border-right: 1px solid oklch(var(--bc) / 0.08); border-bottom: 1px solid oklch(var(--bc) / 0.08);"
                >
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg" :class="getConfig(toast.type).iconBg">
                        <component :is="getConfig(toast.type).icon" class="h-4 w-4" :class="getConfig(toast.type).iconColor" />
                    </div>
                    <p class="flex-1 pt-0.5 text-[13px] leading-relaxed">{{ toast.message }}</p>
                    <button @click="removeToast(toast.id)" class="shrink-0 rounded-lg p-1 transition-colors hover:bg-base-200" aria-label="Dismiss">
                        <XMarkIcon class="h-3.5 w-3.5 text-base-content/40" />
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
