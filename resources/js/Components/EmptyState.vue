<script setup>
import { Link } from '@inertiajs/vue3'
import { InboxIcon, PlusIcon } from '@heroicons/vue/24/outline'
import { computed } from 'vue'

const props = defineProps({
    title: { type: String, default: 'Nothing here yet' },
    description: { type: String, default: '' },
    actionText: { type: String, default: '' },
    actionHref: { type: String, default: '' },
    secondaryText: { type: String, default: '' },
    secondaryHref: { type: String, default: '' },
    icon: { type: [Object, Function], default: null },
    // Visual size: 'sm' for inline empty cards, 'md' default, 'lg' for full-page.
    size: { type: String, default: 'md' },
    // Color accent: emerald | sky | amber | violet | rose. Only tints the
    // icon halo; rest of the layout stays neutral.
    tone: { type: String, default: 'neutral' },
})

// Map tone → opacity-based tinted halo (works in light + dark mode).
const toneClasses = computed(() => ({
    neutral: 'bg-base-content/5 text-base-content/40',
    emerald: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    sky: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
    amber: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    violet: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
    rose: 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
}[props.tone] || 'bg-base-content/5 text-base-content/40'))

const sizeClasses = computed(() => ({
    sm: { wrap: 'py-8 px-4', halo: 'h-12 w-12', icon: 'h-5 w-5', title: 'text-sm', desc: 'text-xs' },
    md: { wrap: 'py-12 px-6', halo: 'h-16 w-16', icon: 'h-7 w-7', title: 'text-base', desc: 'text-sm' },
    lg: { wrap: 'py-20 px-8', halo: 'h-20 w-20', icon: 'h-9 w-9', title: 'text-lg', desc: 'text-sm' },
}[props.size] || { wrap: 'py-12 px-6', halo: 'h-16 w-16', icon: 'h-7 w-7', title: 'text-base', desc: 'text-sm' }))
</script>

<template>
    <div class="flex flex-col items-center justify-center text-center" :class="sizeClasses.wrap">
        <!-- Icon halo with optional concentric ring for depth -->
        <div class="relative mb-4">
            <div class="absolute inset-0 rounded-2xl opacity-60 blur-md" :class="toneClasses"></div>
            <div class="relative flex items-center justify-center rounded-2xl ring-1 ring-base-content/5"
                :class="[sizeClasses.halo, toneClasses]">
                <component :is="icon || InboxIcon" :class="sizeClasses.icon" />
            </div>
        </div>

        <h3 class="font-bold tracking-tight" :class="sizeClasses.title">{{ title }}</h3>
        <p v-if="description" class="mt-2 max-w-sm text-base-content/60 leading-relaxed" :class="sizeClasses.desc">
            {{ description }}
        </p>

        <div v-if="actionText || $slots.actions" class="mt-5 flex flex-wrap items-center justify-center gap-2">
            <!-- Slot lets callers drop in custom buttons / links. -->
            <slot name="actions">
                <Link v-if="actionText && actionHref" :href="actionHref" class="btn btn-primary btn-sm rounded-xl gap-1.5">
                    <PlusIcon class="w-4 h-4" />
                    {{ actionText }}
                </Link>
                <Link v-if="secondaryText && secondaryHref" :href="secondaryHref" class="btn btn-ghost btn-sm rounded-xl">
                    {{ secondaryText }}
                </Link>
            </slot>
        </div>
    </div>
</template>
