<script setup>
import { ArrowTrendingUpIcon, ArrowTrendingDownIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    title: { type: String, required: true },
    value: { type: [String, Number], required: true },
    subtitle: { type: String, default: '' },
    color: { type: String, default: 'primary' },
    href: { type: String, default: '' },
    trend: { type: String, default: '' }, // 'up' | 'down'
    trendValue: { type: String, default: '' },
})

const colorMap = {
    primary: { bg: 'bg-primary/10', text: 'text-primary', ring: 'ring-primary/15', glow: 'from-primary/20 to-transparent' },
    secondary: { bg: 'bg-secondary/10', text: 'text-secondary', ring: 'ring-secondary/15', glow: 'from-secondary/20 to-transparent' },
    accent: { bg: 'bg-accent/10', text: 'text-accent', ring: 'ring-accent/15', glow: 'from-accent/20 to-transparent' },
    success: { bg: 'bg-success/10', text: 'text-success', ring: 'ring-success/15', glow: 'from-success/20 to-transparent' },
    warning: { bg: 'bg-warning/10', text: 'text-warning', ring: 'ring-warning/15', glow: 'from-warning/20 to-transparent' },
    error: { bg: 'bg-error/10', text: 'text-error', ring: 'ring-error/15', glow: 'from-error/20 to-transparent' },
    info: { bg: 'bg-info/10', text: 'text-info', ring: 'ring-info/15', glow: 'from-info/20 to-transparent' },
}

const c = colorMap[props.color] || colorMap.primary
</script>

<template>
    <component
        :is="href ? 'a' : 'div'"
        :href="href || undefined"
        class="stat-card group"
        :class="{ 'cursor-pointer': href }"
    >
        <div class="relative z-10 flex items-start justify-between gap-3">
            <div class="space-y-1.5 min-w-0 flex-1">
                <p class="text-[11px] font-bold uppercase tracking-wider text-base-content/45 truncate">{{ title }}</p>
                <div class="flex items-baseline gap-2">
                    <p class="text-3xl font-extrabold tracking-tight" :class="c.text">{{ value }}</p>
                    <span v-if="trend === 'up'" class="inline-flex items-center gap-0.5 text-xs font-bold text-success">
                        <ArrowTrendingUpIcon class="h-3 w-3" />
                        {{ trendValue }}
                    </span>
                    <span v-else-if="trend === 'down'" class="inline-flex items-center gap-0.5 text-xs font-bold text-error">
                        <ArrowTrendingDownIcon class="h-3 w-3" />
                        {{ trendValue }}
                    </span>
                </div>
                <p v-if="subtitle" class="text-[11.5px] text-base-content/55">{{ subtitle }}</p>
            </div>
            <div
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ring-1 transition-transform group-hover:scale-110"
                :class="[c.bg, c.ring]"
            >
                <slot name="icon" />
            </div>
        </div>
        <!-- Decorative glow -->
        <div class="absolute -right-12 -top-12 h-32 w-32 rounded-full bg-gradient-to-br opacity-50" :class="c.glow" />
    </component>
</template>
