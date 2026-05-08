<script setup>
/**
 * Horizontal bar chart — animated, theme-aware, pure SVG.
 * Use for school/class comparison: each row is a label + value bar.
 *
 * Props:
 *   - rows: [{ label, value, color?: 'primary'|'emerald'|'amber'|'rose'|'sky', sub? }]
 *   - max:  number (axis ceiling) — defaults to max(value) so bars use full width
 *   - unit: string suffix on the value display (e.g. '%')
 */
import { computed } from 'vue'

const props = defineProps({
    rows: { type: Array, default: () => [] },
    max: { type: Number, default: 0 },
    unit: { type: String, default: '' },
})

const ceiling = computed(() => {
    if (props.max > 0) return props.max
    const m = Math.max(...props.rows.map(r => Number(r.value) || 0), 0)
    return m === 0 ? 100 : m
})

const colorClass = (key) => ({
    primary: 'bg-primary',
    emerald: 'bg-emerald-500',
    amber:   'bg-amber-500',
    rose:    'bg-rose-500',
    sky:     'bg-sky-500',
    violet:  'bg-violet-500',
}[key] || 'bg-primary')

function pct(value) {
    return Math.min(100, Math.max(0, (Number(value) || 0) / ceiling.value * 100))
}
</script>

<template>
    <div class="space-y-2.5">
        <div v-for="(r, idx) in rows" :key="idx" class="group">
            <div class="flex items-center justify-between mb-1">
                <div class="min-w-0 flex-1">
                    <p class="text-[12px] font-bold truncate">{{ r.label }}</p>
                    <p v-if="r.sub" class="text-[10px] text-base-content/55 truncate">{{ r.sub }}</p>
                </div>
                <span class="text-[12px] font-bold tabular-nums shrink-0 ml-2">
                    {{ r.value }}{{ unit }}
                </span>
            </div>
            <div class="h-2.5 rounded-full bg-base-200 overflow-hidden relative">
                <div class="h-full rounded-full transition-all duration-700 ease-out group-hover:brightness-110"
                    :class="colorClass(r.color)"
                    :style="{ width: pct(r.value) + '%' }"></div>
            </div>
        </div>
        <p v-if="!rows.length" class="text-center text-sm text-base-content/45 py-4 italic">No data</p>
    </div>
</template>
