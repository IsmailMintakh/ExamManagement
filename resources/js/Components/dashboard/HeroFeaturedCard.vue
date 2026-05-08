<script setup>
/**
 * Dashboard hero — gradient teal-deep-teal background with the user's
 * greeting on one half and a featured big stat (pass rate) on the other.
 * Includes a tiny inline sparkline below the headline number.
 *
 * Replaces the plain "Good morning, {name}" strip with a 2-zone layout
 * that gives the dashboard an immediate "I'm a real product" feel.
 *
 * Props:
 *   userName, role, sessionName, todayLabel, userPhoto
 *   hero: { value, delta, sparkline:[..], sample_size, prev_session_name }
 */
import { computed } from 'vue'
import { ArrowTrendingUpIcon, ArrowTrendingDownIcon, MinusIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    userName: String,
    role: String,
    sessionName: String,
    todayLabel: String,
    userPhoto: String,
    hero: { type: Object, default: () => ({ value: 0, delta: null, sparkline: [], sample_size: 0 }) },
    greeting: String,
})

const showHero = computed(() => (props.hero?.sample_size || 0) > 0)
const deltaSign = computed(() => {
    if (props.hero?.delta === null || props.hero?.delta === undefined) return null
    if (props.hero.delta > 0) return 'up'
    if (props.hero.delta < 0) return 'down'
    return 'flat'
})

// Sparkline path computation from hero.sparkline values
const SPARK_W = 120, SPARK_H = 36
const sparkPath = computed(() => {
    const pts = props.hero?.sparkline || []
    if (pts.length < 2) return ''
    const max = Math.max(...pts, 1)
    const min = Math.min(...pts, 0)
    const range = (max - min) || 1
    const stepX = SPARK_W / (pts.length - 1)
    return pts.map((v, i) => {
        const x = i * stepX
        const y = SPARK_H - 4 - ((v - min) / range) * (SPARK_H - 8)
        return `${i === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${y.toFixed(1)}`
    }).join(' ')
})
</script>

<template>
    <div class="relative overflow-hidden rounded-2xl shadow-lg shadow-teal-900/15"
         style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 45%, #115e59 100%);">
        <!-- Decorative gradient blobs -->
        <div class="absolute -top-20 -right-16 w-72 h-72 bg-cyan-300/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-16 w-72 h-72 bg-emerald-300/15 rounded-full blur-3xl pointer-events-none"></div>
        <!-- Subtle dot pattern -->
        <div class="absolute inset-0 opacity-[0.07] pointer-events-none"
             style="background-image: radial-gradient(circle, white 1px, transparent 1px); background-size: 24px 24px;"></div>

        <div class="relative px-4 py-4 sm:px-6 sm:py-6 grid grid-cols-1 lg:grid-cols-5 gap-4 lg:gap-6">
            <!-- Greeting (LG: 3 of 5 cols) -->
            <div class="lg:col-span-3 flex items-center gap-3 sm:gap-4">
                <div class="relative shrink-0">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl text-white flex items-center justify-center font-bold text-lg overflow-hidden ring-2 ring-white/40 shadow-md"
                         style="background: linear-gradient(135deg, rgba(255,255,255,0.15), rgba(255,255,255,0.05));">
                        <img v-if="userPhoto" :src="userPhoto" :alt="userName" class="w-full h-full object-cover" />
                        <span v-else>{{ userName?.charAt(0)?.toUpperCase() || 'U' }}</span>
                    </div>
                    <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full bg-emerald-400 ring-2 ring-teal-700"></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-cyan-100/80">{{ todayLabel }}</p>
                    <h1 class="text-xl sm:text-2xl lg:text-[1.65rem] font-extrabold tracking-tight truncate text-white mt-1 leading-tight">
                        {{ greeting }}, {{ userName }}
                    </h1>
                    <p class="text-xs text-cyan-100/80 mt-1 truncate">
                        <span class="font-semibold text-white/95">{{ role }}</span>
                        <span v-if="sessionName"> · Session {{ sessionName }}</span>
                    </p>
                </div>
            </div>

            <!-- Featured stat (LG: 2 of 5 cols) -->
            <div v-if="showHero"
                 class="lg:col-span-2 rounded-xl px-4 py-3 sm:px-5 sm:py-4"
                 style="background: rgba(255,255,255,0.13); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.18);">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-cyan-100/80">Pass Rate</p>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span class="text-3xl sm:text-4xl font-extrabold tabular-nums text-white leading-none">{{ hero.value }}%</span>
                            <span v-if="deltaSign" class="inline-flex items-center gap-0.5 text-[11px] font-bold px-1.5 py-0.5 rounded-md"
                                :class="deltaSign === 'up'
                                    ? 'bg-emerald-300/25 text-emerald-50'
                                    : deltaSign === 'down'
                                        ? 'bg-rose-300/25 text-rose-50'
                                        : 'bg-white/15 text-cyan-50'">
                                <ArrowTrendingUpIcon v-if="deltaSign === 'up'" class="w-3 h-3" />
                                <ArrowTrendingDownIcon v-else-if="deltaSign === 'down'" class="w-3 h-3" />
                                <MinusIcon v-else class="w-3 h-3" />
                                {{ hero.delta > 0 ? '+' : '' }}{{ hero.delta }}%
                            </span>
                        </div>
                        <p class="text-[10.5px] text-cyan-100/70 mt-1">
                            {{ hero.sample_size }} results
                            <span v-if="hero.prev_session_name"> · vs {{ hero.prev_session_name }}</span>
                        </p>
                    </div>
                    <!-- Sparkline -->
                    <svg v-if="hero.sparkline?.length > 1" :viewBox="`0 0 ${SPARK_W} ${SPARK_H}`"
                         class="w-24 sm:w-28 shrink-0" :style="{ height: SPARK_H + 'px' }">
                        <defs>
                            <linearGradient id="hero-spark-grad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#a7f3d0" stop-opacity="0.5" />
                                <stop offset="100%" stop-color="#a7f3d0" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <path :d="sparkPath + ` L ${SPARK_W} ${SPARK_H} L 0 ${SPARK_H} Z`"
                              fill="url(#hero-spark-grad)" />
                        <path :d="sparkPath" fill="none" stroke="#fff" stroke-width="1.8"
                              stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
            </div>

            <!-- Empty hero (no results yet) -->
            <div v-else class="lg:col-span-2 rounded-xl px-4 py-3 sm:px-5 sm:py-4 flex items-center"
                 style="background: rgba(255,255,255,0.13); border: 1px solid rgba(255,255,255,0.18);">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-cyan-100/80">Pass Rate</p>
                    <p class="text-white/85 text-sm mt-1">Will appear after the first exam result.</p>
                </div>
            </div>
        </div>
    </div>
</template>
