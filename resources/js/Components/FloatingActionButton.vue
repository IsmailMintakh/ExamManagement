<script setup>
/**
 * Floating Action Button — circular primary action pinned bottom-right on
 * mobile. Sits above the MobileBottomNav (uses bottom-24) so they don't
 * overlap. Auto-hides on lg+ where there's a header button instead.
 *
 * Use as either a Link (when `href` is set) or a button (emits @click).
 */
import { Link } from '@inertiajs/vue3'
import { PlusIcon } from '@heroicons/vue/24/outline'

defineProps({
    href:  { type: String, default: null },
    icon:  { type: [Object, Function], default: () => PlusIcon },
    label: { type: String, default: 'Add' },
    /** "primary" (emerald), "amber", or "rose" */
    color: { type: String, default: 'primary' },
})

const colors = {
    primary: 'bg-gradient-to-br from-primary to-primary/85 shadow-primary/40 text-white',
    amber:   'bg-gradient-to-br from-amber-400 to-amber-600 shadow-amber-500/40 text-slate-950',
    rose:    'bg-gradient-to-br from-rose-500 to-rose-700 shadow-rose-500/40 text-white',
}
</script>

<template>
    <component
        :is="href ? Link : 'button'"
        :href="href"
        type="button"
        class="lg:hidden fixed right-5 z-30 flex items-center gap-2 rounded-full font-bold text-sm pr-5 pl-4 py-3.5 shadow-2xl active:scale-95 transition-all touch-manipulation"
        :class="colors[color] || colors.primary"
        style="bottom: calc(88px + env(safe-area-inset-bottom));"
        :aria-label="label"
    >
        <component :is="icon" class="w-5 h-5 stroke-[2.5]" />
        <span>{{ label }}</span>
    </component>
</template>
