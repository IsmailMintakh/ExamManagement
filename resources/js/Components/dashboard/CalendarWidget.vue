<script setup>
/**
 * Mini month calendar with marker dots for exam dates / events.
 * Today highlighted, hover any day with markers for an inline preview.
 *
 * Props:
 *   - month_label, today (YYYY-MM-DD), markers: [{ date, label, kind, status }]
 */
import { computed, ref } from 'vue'

const props = defineProps({
    month: String,         // 'YYYY-MM'
    monthLabel: String,    // 'May 2026'
    today: String,         // 'YYYY-MM-DD'
    markers: { type: Array, default: () => [] },
})

// Group markers by date for fast lookup
const markersByDate = computed(() => {
    const m = {}
    for (const x of props.markers || []) {
        if (!x.date) continue
        ;(m[x.date] = m[x.date] || []).push(x)
    }
    return m
})

// Build the 6×7 grid for the month — ISO week (Mon..Sun)
const grid = computed(() => {
    const [y, m] = (props.month || '').split('-').map(Number)
    if (!y || !m) return []
    const first = new Date(y, m - 1, 1)
    const daysInMonth = new Date(y, m, 0).getDate()

    // Mon=0, Tue=1, ..., Sun=6
    const startWeekday = (first.getDay() + 6) % 7
    const cells = []
    // Leading empty cells
    for (let i = 0; i < startWeekday; i++) cells.push(null)
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`
        cells.push({
            day: d,
            date: dateStr,
            is_today: dateStr === props.today,
            markers: markersByDate.value[dateStr] || [],
        })
    }
    // Pad to multiples of 7
    while (cells.length % 7 !== 0) cells.push(null)
    return cells
})

const DAY_HEADERS = ['M', 'T', 'W', 'T', 'F', 'S', 'S']

const hovered = ref(null)
function showHover(c, e) {
    if (!c?.markers?.length) return
    hovered.value = c
}
function clearHover() {
    hovered.value = null
}
</script>

<template>
    <div class="select-none">
        <header class="flex items-center justify-between mb-2.5">
            <h3 class="text-sm font-bold">{{ monthLabel }}</h3>
            <span class="text-[10px] text-base-content/55 font-mono">{{ markers.length }} event{{ markers.length === 1 ? '' : 's' }}</span>
        </header>
        <div class="grid grid-cols-7 gap-1 text-center">
            <div v-for="(d, i) in DAY_HEADERS" :key="i" class="text-[9px] font-bold text-base-content/45 uppercase pb-1">
                {{ d }}
            </div>
            <div v-for="(c, i) in grid" :key="i"
                @mouseenter="c && showHover(c, $event)"
                @mouseleave="clearHover"
                class="relative aspect-square rounded-md flex items-center justify-center text-[11px] tabular-nums transition-colors"
                :class="!c
                    ? 'bg-transparent'
                    : c.is_today
                        ? 'bg-primary text-primary-content font-bold ring-2 ring-primary/40'
                        : c.markers.length
                            ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300 font-bold cursor-pointer hover:bg-amber-500/25'
                            : 'text-base-content/65 hover:bg-base-200/50'">
                <span v-if="c">{{ c.day }}</span>
                <!-- marker dots stacked at bottom -->
                <div v-if="c?.markers?.length" class="absolute bottom-0.5 left-1/2 -translate-x-1/2 flex gap-0.5">
                    <span v-for="(_, idx) in Math.min(3, c.markers.length)" :key="idx"
                        class="w-1 h-1 rounded-full bg-amber-600 dark:bg-amber-400"></span>
                </div>
            </div>
        </div>

        <!-- Hover preview -->
        <div v-if="hovered" class="mt-2 rounded-lg bg-amber-500/10 border border-amber-500/30 px-2.5 py-1.5">
            <p class="text-[10px] uppercase tracking-wider font-bold text-amber-700 dark:text-amber-300">{{ hovered.date }}</p>
            <ul class="mt-0.5 space-y-0.5">
                <li v-for="(m, i) in hovered.markers" :key="i" class="text-xs text-amber-900 dark:text-amber-200 truncate">
                    <span class="font-semibold">{{ m.label }}</span>
                    <span class="text-[9px] uppercase ml-1 opacity-70">{{ m.status }}</span>
                </li>
            </ul>
        </div>
        <p v-else-if="!markers.length" class="text-[10px] text-base-content/45 text-center mt-2 italic">
            No exams or events this month
        </p>
    </div>
</template>
