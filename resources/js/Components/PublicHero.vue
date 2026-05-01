<script setup>
/**
 * Reusable public-page hero banner.
 *
 * Receives a `hero` prop (PageMeta record). Falls back to safe defaults
 * if any field is missing — every page gets a hero even before the admin
 * configures one.
 *
 * Style themes are CSS gradient classes; ensure these match PageMeta::STYLES.
 */
import { computed } from 'vue'

const props = defineProps({
    hero: { type: Object, default: () => ({}) },
    /** Override per-page-time fallbacks if the hero meta hasn't been seeded yet. */
    fallback: { type: Object, default: () => ({}) },
})

const eyebrow = computed(() => props.hero?.hero_eyebrow ?? props.fallback.eyebrow ?? '')
const title   = computed(() => props.hero?.hero_title   ?? props.fallback.title   ?? '')
const accent  = computed(() => props.hero?.hero_title_accent ?? props.fallback.accent ?? '')
const subtitle= computed(() => props.hero?.hero_subtitle ?? props.fallback.subtitle ?? '')
const styleKey= computed(() => props.hero?.hero_style    ?? 'emerald-night')

// Style preset → background gradient + accent text + glow colors
const themes = {
    'emerald-night': {
        bg:   'from-slate-950 via-emerald-950 to-slate-900',
        glow: 'bg-emerald-600/20',
        glow2:'bg-amber-500/10',
    },
    'amber-dawn': {
        bg:   'from-amber-900 via-orange-950 to-slate-900',
        glow: 'bg-amber-500/25',
        glow2:'bg-rose-500/15',
    },
    'sky-twilight': {
        bg:   'from-slate-950 via-sky-950 to-indigo-900',
        glow: 'bg-sky-500/20',
        glow2:'bg-amber-500/10',
    },
    'violet-deep': {
        bg:   'from-slate-950 via-violet-950 to-slate-900',
        glow: 'bg-violet-500/20',
        glow2:'bg-amber-500/10',
    },
    'rose-warm': {
        bg:   'from-slate-950 via-rose-950 to-slate-900',
        glow: 'bg-rose-500/20',
        glow2:'bg-amber-500/10',
    },
}
const theme = computed(() => themes[styleKey.value] || themes['emerald-night'])
</script>

<template>
    <section class="relative bg-slate-950 text-white py-28 overflow-hidden">
        <div class="absolute inset-0">
            <div class="absolute inset-0 bg-gradient-to-br" :class="theme.bg"></div>
            <div class="absolute top-0 right-0 w-[40rem] h-[40rem] rounded-full blur-[140px] animate-float" :class="theme.glow"></div>
            <div class="absolute bottom-0 left-0 w-[30rem] h-[30rem] rounded-full blur-[120px] animate-float" :class="theme.glow2" style="animation-delay: 2s"></div>
        </div>
        <div class="relative max-w-5xl mx-auto px-6 lg:px-10 text-center reveal">
            <div v-if="eyebrow" class="text-[11px] uppercase tracking-[0.25em] text-amber-400 font-semibold mb-5">
                {{ eyebrow }}
            </div>
            <h1 class="text-5xl lg:text-7xl font-black tracking-tight leading-[1.0]">
                {{ title }}
                <template v-if="accent">
                    <br />
                    <span class="bg-gradient-to-r from-amber-300 to-emerald-300 bg-clip-text text-transparent">{{ accent }}</span>
                </template>
            </h1>
            <p v-if="subtitle" class="mt-7 text-lg text-stone-300 max-w-2xl mx-auto font-light leading-relaxed">
                {{ subtitle }}
            </p>
        </div>
    </section>
</template>
