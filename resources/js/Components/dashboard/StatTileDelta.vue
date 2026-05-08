<script setup>
/**
 * Compact stat tile with optional delta + sparkline. Replaces the bigger
 * generic stat card on the dashboard.
 *
 * Props:
 *   label, value, icon, color: 'primary'|'emerald'|'amber'|'sky'|'rose'|'violet',
 *   delta: number|null  (signed, e.g. -3 or +5),
 *   deltaSuffix: '%' default,
 *   spark: number[] optional
 */
import { computed } from 'vue'
import { ArrowTrendingUpIcon, ArrowTrendingDownIcon, MinusIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    label: String,
    value: [String, Number],
    icon: { type: [Object, Function], default: null },
    color: { type: String, default: 'primary' },
    delta: { type: [Number, null], default: null },
    deltaSuffix: { type: String, default: '%' },
    spark: { type: Array, default: () => [] },
    href: String,
})

const colorMap = {
    primary: { ring: 'ring-primary/15', icon: 'bg-primary/10 text-primary', accent: '#0d9488' },
    emerald: { ring: 'ring-emerald-500/15', icon: 'bg-emerald-500/10 text-emerald-600', accent: '#10b981' },
    amber:   { ring: 'ring-amber-500/15', icon: 'bg-amber-500/10 text-amber-600', accent: '#f59e0b' },
    sky:     { ring: 'ring-sky-500/15', icon: 'bg-sky-500/10 text-sky-600', accent: '#0ea5e9' },
    rose:    { ring: 'ring-rose-500/15', icon: 'bg-rose-500/10 text-rose-600', accent: '#f43f5e' },
    violet:  { ring: 'ring-violet-500/15', icon: 'bg-violet-500/10 text-violet-600', accent: '#8b5cf6' },
}
const c = computed(() => colorMap[props.color] || colorMap.primary)

const deltaSign = computed(() => {
    if (props.delta === null || props.delta === undefined) return null
    if (props.delta > 0) return 'up'
    if (props.delta < 0) return 'down'
    return 'flat'
})

// Sparkline path
const SPARK_W = 90, SPARK_H = 28
const sparkPath = computed(() => {
    const pts = props.spark || []
    if (pts.length < 2) return ''
    const max = Math.max(...pts, 1)
    const min = Math.min(...pts, 0)
    const range = (max - min) || 1
    const stepX = SPARK_W / (pts.length - 1)
    return pts.map((v, i) => {
        const x = i * stepX
        const y = SPARK_H - 3 - ((v - min) / range) * (SPARK_H - 6)
        return `${i === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${y.toFixed(1)}`
    }).join(' ')
})

const Wrapper = computed(() => props.href ? 'a' : 'div')
</script>

<template>
    <component :is="Wrapper"
        :href="href"
        class="block rounded-2xl bg-base-100 ring-1 p-3.5 sm:p-4 transition-all hover:ring-2 hover:-translate-y-0.5"
        :class="c.ring">
        <div class="flex items-start justify-between gap-2">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                 :class="c.icon">
                <component v-if="icon" :is="icon" class="w-4.5 h-4.5" />
            </div>
            <span v-if="deltaSign" class="inline-flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded-md"
                :class="deltaSign === 'up' ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                      : deltaSign === 'down' ? 'bg-rose-500/15 text-rose-700 dark:text-rose-300'
                      : 'bg-base-200 text-base-content/55'">
                <ArrowTrendingUpIcon v-if="deltaSign === 'up'" class="w-2.5 h-2.5" />
                <ArrowTrendingDownIcon v-else-if="deltaSign === 'down'" class="w-2.5 h-2.5" />
                <MinusIcon v-else class="w-2.5 h-2.5" />
                {{ delta > 0 ? '+' : '' }}{{ delta }}{{ deltaSuffix }}
            </span>
        </div>
        <div class="mt-2.5 flex items-end justify-between gap-2">
            <div class="min-w-0">
                <p class="text-2xl sm:text-3xl font-extrabold tabular-nums leading-none">{{ value }}</p>
                <p class="text-[10.5px] uppercase tracking-wider font-bold text-base-content/55 mt-1.5">{{ label }}</p>
            </div>
            <svg v-if="spark?.length > 1" :viewBox="`0 0 ${SPARK_W} ${SPARK_H}`"
                 class="w-16 sm:w-20 shrink-0" :style="{ height: SPARK_H + 'px' }">
                <path :d="sparkPath" fill="none" :stroke="c.accent" stroke-width="1.6"
                      stroke-linecap="round" stroke-linejoin="round" opacity="0.85" />
            </svg>
        </div>
    </component>
</template>
