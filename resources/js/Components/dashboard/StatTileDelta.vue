<script setup>
/**
 * Stat tile — big-number card with optional delta pill + background
 * sparkline. Redesigned to be visually striking at the top of the
 * Dashboard (this is the first thing users see when they open the app).
 *
 * Key changes vs previous version:
 *   • Gradient icon-tile (was pale ghost)
 *   • Number is 3xl→4xl instead of 2xl→3xl — reads at a glance on mobile
 *   • Sparkline sits behind the number as a soft area, not next to it
 *   • Tighter type hierarchy, more premium feel
 *
 * Props:
 *   label, value, icon, color: 'primary'|'emerald'|'amber'|'sky'|'rose'|'violet',
 *   delta: number|null  (signed, e.g. -3 or +5),
 *   deltaSuffix: '%' default,
 *   spark: number[] optional — renders as a subtle area behind the number,
 *   href: optional link — turns the tile into a tappable card.
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

// Full mapping: card ring + gradient icon-tile + sparkline colours. Each
// tone is paired so the whole tile feels cohesive at a glance.
const colorMap = {
    primary: {
        ring: 'ring-primary/15 hover:ring-primary/30',
        icon: 'bg-gradient-to-br from-primary to-teal-600 text-primary-content shadow-primary/25',
        stroke: 'oklch(var(--p))',
        fill:   'oklch(var(--p) / 0.10)',
    },
    emerald: {
        ring: 'ring-emerald-500/15 hover:ring-emerald-500/30',
        icon: 'bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-emerald-500/25',
        stroke: '#10b981',
        fill:   'rgba(16, 185, 129, 0.10)',
    },
    amber: {
        ring: 'ring-amber-500/15 hover:ring-amber-500/30',
        icon: 'bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-amber-500/25',
        stroke: '#f59e0b',
        fill:   'rgba(245, 158, 11, 0.10)',
    },
    sky: {
        ring: 'ring-sky-500/15 hover:ring-sky-500/30',
        icon: 'bg-gradient-to-br from-sky-500 to-indigo-600 text-white shadow-sky-500/25',
        stroke: '#0ea5e9',
        fill:   'rgba(14, 165, 233, 0.10)',
    },
    rose: {
        ring: 'ring-rose-500/15 hover:ring-rose-500/30',
        icon: 'bg-gradient-to-br from-rose-500 to-pink-600 text-white shadow-rose-500/25',
        stroke: '#f43f5e',
        fill:   'rgba(244, 63, 94, 0.10)',
    },
    violet: {
        ring: 'ring-violet-500/15 hover:ring-violet-500/30',
        icon: 'bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white shadow-violet-500/25',
        stroke: '#8b5cf6',
        fill:   'rgba(139, 92, 246, 0.10)',
    },
}
const c = computed(() => colorMap[props.color] || colorMap.primary)

const deltaSign = computed(() => {
    if (props.delta === null || props.delta === undefined) return null
    if (props.delta > 0) return 'up'
    if (props.delta < 0) return 'down'
    return 'flat'
})

// Sparkline rendered as a soft SVG area BEHIND the number.
const SPARK_W = 200, SPARK_H = 60
const sparkPath = computed(() => {
    const pts = props.spark || []
    if (pts.length < 2) return null
    const max = Math.max(...pts, 1)
    const min = Math.min(...pts, 0)
    const range = (max - min) || 1
    const stepX = SPARK_W / (pts.length - 1)
    const line = pts.map((v, i) => {
        const x = i * stepX
        const y = SPARK_H - 4 - ((v - min) / range) * (SPARK_H - 8)
        return `${i === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${y.toFixed(1)}`
    }).join(' ')
    return { line, area: `${line} L ${SPARK_W} ${SPARK_H} L 0 ${SPARK_H} Z` }
})

const Wrapper = computed(() => props.href ? 'a' : 'div')
</script>

<template>
    <component :is="Wrapper"
        :href="href"
        :class="[
            'group relative block overflow-hidden rounded-2xl bg-base-100 ring-1 p-4 sm:p-5',
            'transition-all duration-200 will-change-transform',
            href ? 'hover:-translate-y-0.5 hover:shadow-lg active:translate-y-0' : '',
            c.ring,
        ]">
        <!-- Sparkline as a soft background: gives a sense of trend
             without competing with the number for attention. -->
        <svg v-if="sparkPath"
             :viewBox="`0 0 ${SPARK_W} ${SPARK_H}`"
             class="absolute inset-x-0 bottom-0 w-full h-14 sm:h-16 opacity-70 pointer-events-none"
             preserveAspectRatio="none">
            <path :d="sparkPath.area" :fill="c.fill" />
            <path :d="sparkPath.line" :stroke="c.stroke" stroke-width="1.5" fill="none"
                  stroke-linecap="round" stroke-linejoin="round" />
        </svg>

        <div class="relative flex items-start justify-between gap-2 mb-3 sm:mb-4">
            <div v-if="icon"
                :class="['w-10 h-10 sm:w-11 sm:h-11 rounded-xl flex items-center justify-center shadow-sm', c.icon]">
                <component :is="icon" class="w-5 h-5" />
            </div>
            <span v-if="deltaSign"
                :class="[
                    'inline-flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-1 rounded-lg shadow-sm',
                    deltaSign === 'up'   ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                  : deltaSign === 'down' ? 'bg-rose-500/15 text-rose-700 dark:text-rose-300'
                  :                        'bg-base-200 text-base-content/55'
                ]">
                <ArrowTrendingUpIcon   v-if="deltaSign === 'up'"       class="w-2.5 h-2.5" />
                <ArrowTrendingDownIcon v-else-if="deltaSign === 'down'" class="w-2.5 h-2.5" />
                <MinusIcon             v-else                          class="w-2.5 h-2.5" />
                {{ delta > 0 ? '+' : '' }}{{ delta }}{{ deltaSuffix }}
            </span>
        </div>

        <div class="relative">
            <p class="text-3xl sm:text-4xl font-extrabold tracking-tight tabular-nums leading-none">
                {{ value }}
            </p>
            <p class="text-[11px] sm:text-xs uppercase tracking-wider font-semibold text-base-content/55 mt-2">
                {{ label }}
            </p>
        </div>
    </component>
</template>
