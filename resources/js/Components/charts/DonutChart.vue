<script setup>
/**
 * Donut chart — animated SVG. Each segment colored by `color` key.
 * Shows total in the middle. Use for pass/fail or grade distribution.
 *
 * Props:
 *   - segments: [{ label, value, color: 'emerald'|'rose'|'amber'|'sky'|'violet'|'primary' }]
 *   - centerLabel: string at center top
 *   - centerValue: number/string at center large
 */
import { computed } from 'vue'

const props = defineProps({
    segments: { type: Array, default: () => [] },
    centerLabel: { type: String, default: '' },
    centerValue: { type: [String, Number], default: '' },
    size: { type: Number, default: 180 },
    stroke: { type: Number, default: 24 },
})

const SIZE = computed(() => props.size)
const STROKE = computed(() => props.stroke)
const RADIUS = computed(() => (SIZE.value / 2) - (STROKE.value / 2) - 2)
const C = computed(() => 2 * Math.PI * RADIUS.value)

const total = computed(() => props.segments.reduce((a, s) => a + (Number(s.value) || 0), 0))

const arcs = computed(() => {
    if (total.value === 0) return []
    let offset = 0
    return props.segments.map(s => {
        const v = Number(s.value) || 0
        const fraction = v / total.value
        const length = fraction * C.value
        const arc = {
            label: s.label,
            value: v,
            color: s.color,
            length,
            offset,
        }
        offset += length
        return arc
    })
})

const colorHex = (key) => ({
    primary: '#0d9488',
    emerald: '#10b981',
    rose:    '#f43f5e',
    amber:   '#f59e0b',
    sky:     '#0ea5e9',
    violet:  '#8b5cf6',
}[key] || '#0d9488')
</script>

<template>
    <div class="flex items-center gap-4 sm:gap-6">
        <!-- Donut SVG -->
        <div class="relative shrink-0" :style="{ width: SIZE + 'px', height: SIZE + 'px' }">
            <svg :viewBox="`0 0 ${SIZE} ${SIZE}`" :width="SIZE" :height="SIZE">
                <!-- Track -->
                <circle :cx="SIZE / 2" :cy="SIZE / 2" :r="RADIUS"
                    fill="none"
                    stroke="oklch(var(--bc) / 0.08)"
                    :stroke-width="STROKE" />
                <!-- Segments — rotate -90deg via transform-origin so we start at 12 o'clock -->
                <g :transform="`rotate(-90 ${SIZE / 2} ${SIZE / 2})`">
                    <circle v-for="(arc, idx) in arcs" :key="idx"
                        :cx="SIZE / 2" :cy="SIZE / 2" :r="RADIUS"
                        fill="none"
                        :stroke="colorHex(arc.color)"
                        :stroke-width="STROKE"
                        :stroke-dasharray="`${arc.length} ${C}`"
                        :stroke-dashoffset="-arc.offset"
                        stroke-linecap="butt"
                        class="transition-all duration-700 ease-out" />
                </g>
            </svg>
            <!-- Center label -->
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                <p v-if="centerLabel" class="text-[9px] uppercase tracking-wider font-bold text-base-content/55">
                    {{ centerLabel }}
                </p>
                <p class="text-2xl font-extrabold tabular-nums leading-none mt-0.5">{{ centerValue }}</p>
            </div>
        </div>
        <!-- Legend -->
        <div class="flex-1 min-w-0 space-y-2">
            <div v-for="(arc, idx) in arcs" :key="idx" class="flex items-center gap-2 text-sm">
                <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: colorHex(arc.color) }"></span>
                <span class="text-base-content/75 flex-1 truncate">{{ arc.label }}</span>
                <span class="font-bold tabular-nums">{{ arc.value }}</span>
                <span class="text-[10px] text-base-content/45 tabular-nums w-10 text-right">
                    {{ total > 0 ? ((arc.value / total) * 100).toFixed(1) : 0 }}%
                </span>
            </div>
            <p v-if="!arcs.length" class="text-sm text-base-content/45 italic">No data yet</p>
        </div>
    </div>
</template>
