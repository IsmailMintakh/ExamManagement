<script setup>
/**
 * Line chart — pure SVG, animated, with hover tooltips.
 * Plots a series of points → smooth line + area gradient + dot markers.
 * Hovering a point reveals an exact-value tooltip (cleaner than the old
 * always-visible labels).
 *
 * Props:
 *   - points: [{ label, value }]
 *   - color: 'primary'|'emerald'|'sky' default 'primary'
 *   - height: px (default 160)
 *   - unit: suffix on values (e.g. '%')
 */
import { computed, ref } from 'vue'

const props = defineProps({
    points: { type: Array, default: () => [] },
    color: { type: String, default: 'primary' },
    height: { type: Number, default: 160 },
    unit: { type: String, default: '' },
})

const W = 600
const H = computed(() => props.height)
const PAD_X = 36
const PAD_Y = 26

const colorHex = computed(() => ({
    primary: '#0d9488',
    emerald: '#10b981',
    sky: '#0ea5e9',
    violet: '#8b5cf6',
    amber: '#f59e0b',
}[props.color] || '#0d9488'))

const max = computed(() => {
    if (!props.points.length) return 100
    const m = Math.max(...props.points.map(p => Number(p.value) || 0))
    return m === 0 ? 100 : Math.ceil(m / 10) * 10
})

const coords = computed(() => {
    const n = props.points.length
    if (n === 0) return []
    const step = (W - PAD_X * 2) / Math.max(1, n - 1)
    return props.points.map((p, i) => ({
        ...p,
        x: PAD_X + step * i,
        y: H.value - PAD_Y - ((Number(p.value) || 0) / max.value) * (H.value - PAD_Y * 2),
    }))
})

const linePath = computed(() => {
    if (coords.value.length === 0) return ''
    return coords.value.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ')
})
const areaPath = computed(() => {
    if (coords.value.length === 0) return ''
    const baseY = H.value - PAD_Y
    const startX = coords.value[0].x
    const endX = coords.value[coords.value.length - 1].x
    return `M ${startX} ${baseY} ` +
        coords.value.map(p => `L ${p.x} ${p.y}`).join(' ') +
        ` L ${endX} ${baseY} Z`
})

const yTicks = computed(() => [0, max.value / 2, max.value].map(v => ({
    value: v,
    y: H.value - PAD_Y - (v / max.value) * (H.value - PAD_Y * 2),
})))

// ── Hover state ──
const hoverIdx = ref(null)
const hoverPoint = computed(() => hoverIdx.value !== null ? coords.value[hoverIdx.value] : null)
function onPointEnter(i) { hoverIdx.value = i }
function onPointLeave() { hoverIdx.value = null }
</script>

<template>
    <div class="w-full relative">
        <svg :viewBox="`0 0 ${W} ${H}`" class="w-full" :style="{ height: H + 'px' }" preserveAspectRatio="none">
            <defs>
                <linearGradient :id="`grad-${color}`" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="colorHex" stop-opacity="0.28" />
                    <stop offset="100%" :stop-color="colorHex" stop-opacity="0.02" />
                </linearGradient>
            </defs>

            <!-- Y gridlines -->
            <line v-for="(t, idx) in yTicks" :key="`ty${idx}`"
                :x1="PAD_X" :x2="W - PAD_X" :y1="t.y" :y2="t.y"
                stroke="oklch(var(--bc) / 0.06)" stroke-dasharray="3 4" />
            <text v-for="(t, idx) in yTicks" :key="`tt${idx}`"
                :x="PAD_X - 6" :y="t.y + 3"
                font-size="9" text-anchor="end" fill="oklch(var(--bc) / 0.45)" font-family="monospace">
                {{ Math.round(t.value) }}{{ unit }}
            </text>

            <!-- Area fill -->
            <path :d="areaPath" :fill="`url(#grad-${color})`" />

            <!-- Line -->
            <path :d="linePath" fill="none" :stroke="colorHex" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round" />

            <!-- Hover guide (vertical line + bigger dot) -->
            <g v-if="hoverPoint">
                <line :x1="hoverPoint.x" :x2="hoverPoint.x" :y1="PAD_Y" :y2="H - PAD_Y"
                    stroke="oklch(var(--bc) / 0.18)" stroke-dasharray="3 3" />
                <circle :cx="hoverPoint.x" :cy="hoverPoint.y" r="7"
                    fill="white" :stroke="colorHex" stroke-width="2.5" />
            </g>

            <!-- Dots — hoverable, transparent overlay for easier targeting -->
            <g v-for="(p, idx) in coords" :key="`d${idx}`">
                <circle :cx="p.x" :cy="p.y" r="4" fill="white" :stroke="colorHex" stroke-width="2" />
                <!-- Hover hit-box -->
                <circle :cx="p.x" :cy="p.y" r="14" fill="transparent"
                    @mouseenter="onPointEnter(idx)" @mouseleave="onPointLeave" />
            </g>

            <!-- X labels -->
            <text v-for="(p, idx) in coords" :key="`x${idx}`"
                :x="p.x" :y="H - 6" font-size="10" text-anchor="middle"
                fill="oklch(var(--bc) / 0.55)" font-weight="600">
                {{ p.label }}
            </text>
        </svg>

        <!-- Tooltip -->
        <div v-if="hoverPoint"
            class="absolute pointer-events-none px-2.5 py-1.5 rounded-md shadow-lg text-xs font-bold tabular-nums whitespace-nowrap"
            style="background: #0f172a; color: #fff; transform: translate(-50%, -120%);"
            :style="{
                left: `calc(${(hoverPoint.x / W) * 100}% + 0px)`,
                top: `calc(${(hoverPoint.y / H) * 100}% + 0px)`,
            }">
            <div class="text-[10px] font-medium opacity-70 mb-0.5">{{ hoverPoint.label }}</div>
            <div>{{ hoverPoint.value }}{{ unit }}</div>
        </div>

        <p v-if="!points.length" class="text-center text-sm text-base-content/45 py-2 italic">No trend data</p>
    </div>
</template>
