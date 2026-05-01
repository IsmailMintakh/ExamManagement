<script setup>
/**
 * Public-side renderer for an array of PageBlock rows. Each block's `type`
 * picks the appropriate visual treatment. Designed to match the existing
 * About/Academics page aesthetic — alternating backgrounds, reveal animations,
 * emerald + amber accents.
 */
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import {
    AcademicCapIcon, BeakerIcon, BookOpenIcon, BuildingLibraryIcon,
    CheckCircleIcon, ComputerDesktopIcon, EyeIcon, FlagIcon,
    GlobeAltIcon, HeartIcon, LightBulbIcon, MapPinIcon,
    ShieldCheckIcon, SparklesIcon, StarIcon, TrophyIcon,
    UserGroupIcon, UsersIcon, ArrowRightIcon, CheckBadgeIcon,
    ChatBubbleLeftRightIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    blocks: { type: Array, default: () => [] },
})

// Map of icon name → component for block types that store icon names as strings
const ICONS = {
    AcademicCapIcon, BeakerIcon, BookOpenIcon, BuildingLibraryIcon,
    CheckCircleIcon, ComputerDesktopIcon, EyeIcon, FlagIcon,
    GlobeAltIcon, HeartIcon, LightBulbIcon, MapPinIcon,
    ShieldCheckIcon, SparklesIcon, StarIcon, TrophyIcon,
    UserGroupIcon, UsersIcon,
}
function iconFor(name) { return ICONS[name] || StarIcon }

// Active blocks only, preserving order
const visible = computed(() => (props.blocks || []).filter(b => b.is_active !== false))

// Alternating bg per visible block — even = white/stone, odd = subtle gradient
function bgFor(i) {
    return i % 2 === 0 ? 'bg-white' : 'bg-stone-50'
}

// Render simple bold-marker markdown: **word** → <strong>word</strong>
function renderText(text) {
    if (!text) return ''
    return String(text).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
}
</script>

<template>
    <template v-for="(block, i) in visible" :key="block.id">

        <!-- ════════ rich_text ════════ -->
        <section v-if="block.type === 'rich_text'" class="py-20" :class="bgFor(i)">
            <div class="max-w-4xl mx-auto px-6 lg:px-10 reveal">
                <div v-if="block.data.eyebrow" class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">
                    {{ block.data.eyebrow }}
                </div>
                <h2 v-if="block.data.heading" class="text-3xl lg:text-5xl font-black text-slate-900 tracking-tight leading-[1.1] mb-8">
                    {{ block.data.heading }}
                </h2>
                <div v-if="block.data.body" class="prose prose-slate max-w-none prose-lg whitespace-pre-line text-slate-700 leading-[1.85]"
                     v-html="renderText(block.data.body)">
                </div>
            </div>
        </section>

        <!-- ════════ mission_vision ════════ -->
        <section v-else-if="block.type === 'mission_vision'" class="py-20" :class="bgFor(i)">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="reveal-stagger relative bg-gradient-to-br from-emerald-700 via-emerald-800 to-emerald-950 text-white p-10 lg:p-12 rounded-3xl shadow-2xl overflow-hidden">
                        <div class="absolute top-0 right-0 w-40 h-40 bg-emerald-400/20 rounded-full -translate-y-12 translate-x-12 blur-2xl"></div>
                        <FlagIcon class="w-10 h-10 text-amber-300 mb-6 relative" />
                        <h3 class="text-2xl lg:text-3xl font-black mb-4 tracking-tight relative">{{ block.data.mission_title || 'Our Mission' }}</h3>
                        <p class="text-stone-200 leading-[1.8] relative whitespace-pre-line">{{ block.data.mission_body }}</p>
                    </div>
                    <div class="reveal-stagger relative bg-gradient-to-br from-amber-600 via-amber-700 to-orange-900 text-white p-10 lg:p-12 rounded-3xl shadow-2xl overflow-hidden" style="--delay: 150ms">
                        <div class="absolute top-0 right-0 w-40 h-40 bg-amber-400/20 rounded-full -translate-y-12 translate-x-12 blur-2xl"></div>
                        <EyeIcon class="w-10 h-10 text-amber-200 mb-6 relative" />
                        <h3 class="text-2xl lg:text-3xl font-black mb-4 tracking-tight relative">{{ block.data.vision_title || 'Our Vision' }}</h3>
                        <p class="text-stone-100 leading-[1.8] relative whitespace-pre-line">{{ block.data.vision_body }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ════════ feature_grid ════════ -->
        <section v-else-if="block.type === 'feature_grid'" class="py-20" :class="bgFor(i)">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div v-if="block.data.eyebrow || block.data.heading" class="max-w-2xl mb-12 reveal">
                    <div v-if="block.data.eyebrow" class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">{{ block.data.eyebrow }}</div>
                    <h2 v-if="block.data.heading" class="text-3xl lg:text-5xl font-black text-slate-900 tracking-tight leading-[1.1]">{{ block.data.heading }}</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-px bg-stone-200 border border-stone-200 rounded-3xl overflow-hidden">
                    <div v-for="(item, idx) in (block.data.items || [])" :key="idx"
                         class="bg-white p-8 lg:p-10 hover:bg-stone-50 transition-colors duration-500 group reveal-stagger"
                         :style="`--delay: ${idx * 80}ms`">
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center mb-6 group-hover:bg-emerald-600 group-hover:text-white group-hover:-translate-y-0.5 transition-all duration-500">
                            <component :is="iconFor(item.icon)" class="w-6 h-6" />
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 mb-3 tracking-tight">{{ item.title }}</h3>
                        <p class="text-[14px] text-slate-600 leading-[1.7]">{{ item.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ════════ stats_strip ════════ -->
        <section v-else-if="block.type === 'stats_strip'" class="py-14 border-y border-stone-100" :class="bgFor(i)">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <h3 v-if="block.data.heading" class="text-center text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-10">
                    {{ block.data.heading }}
                </h3>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-4">
                    <div v-for="(stat, idx) in (block.data.items || [])" :key="idx"
                         class="text-center lg:text-left lg:pl-8 lg:border-l lg:first:pl-0 lg:first:border-l-0 border-stone-200 reveal-stagger"
                         :style="`--delay: ${idx * 80}ms`">
                        <component :is="iconFor(stat.icon)" class="w-6 h-6 text-emerald-600 mx-auto lg:mx-0 mb-3" />
                        <div class="text-4xl lg:text-5xl font-black text-slate-900 tabular-nums tracking-tight">
                            {{ stat.value }}<span class="text-amber-600">{{ stat.suffix }}</span>
                        </div>
                        <div class="text-xs uppercase tracking-[0.15em] text-slate-500 font-semibold mt-2">{{ stat.label }}</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ════════ timeline ════════ -->
        <section v-else-if="block.type === 'timeline'" class="py-20" :class="bgFor(i)">
            <div class="max-w-4xl mx-auto px-6 lg:px-10">
                <div v-if="block.data.eyebrow || block.data.heading" class="text-center mb-14 reveal">
                    <div v-if="block.data.eyebrow" class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">{{ block.data.eyebrow }}</div>
                    <h2 v-if="block.data.heading" class="text-3xl lg:text-5xl font-black text-slate-900 tracking-tight leading-[1.1]">{{ block.data.heading }}</h2>
                </div>
                <div class="relative">
                    <div class="absolute left-6 top-0 bottom-0 w-px bg-gradient-to-b from-emerald-200 via-emerald-300 to-transparent"></div>
                    <div v-for="(m, idx) in (block.data.items || [])" :key="idx"
                         class="relative pl-20 pb-10 reveal-stagger" :style="`--delay: ${idx * 80}ms`">
                        <div class="absolute left-0 top-0 w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-600 to-emerald-800 text-white flex items-center justify-center shadow-lg font-black text-xs tracking-wide">
                            {{ m.year }}
                        </div>
                        <h3 class="text-xl font-black text-slate-900 tracking-tight">{{ m.title }}</h3>
                        <p class="mt-2 text-sm text-slate-600 leading-[1.75]">{{ m.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ════════ image_text ════════ -->
        <section v-else-if="block.type === 'image_text'" class="py-20" :class="bgFor(i)">
            <div class="max-w-[1400px] mx-auto px-6 lg:px-10">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center"
                     :class="block.data.layout === 'image-right' ? 'lg:flex-row-reverse' : ''">
                    <div class="reveal" :class="block.data.layout === 'image-right' ? 'lg:order-2' : ''">
                        <div class="aspect-[4/3] rounded-3xl overflow-hidden bg-gradient-to-br from-emerald-700 to-slate-900 shadow-2xl">
                            <img v-if="block.data.image_url" :src="block.data.image_url" :alt="block.data.heading || 'Image'"
                                class="w-full h-full object-cover" />
                        </div>
                    </div>
                    <div class="reveal-stagger" :class="block.data.layout === 'image-right' ? 'lg:order-1' : ''" style="--delay: 150ms">
                        <h2 class="text-3xl lg:text-4xl font-black text-slate-900 tracking-tight leading-[1.15]">{{ block.data.heading }}</h2>
                        <p class="mt-6 text-[15px] text-slate-700 leading-[1.85] whitespace-pre-line">{{ block.data.body }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ════════ testimonials ════════ -->
        <section v-else-if="block.type === 'testimonials'" class="py-24 bg-slate-950 text-white relative overflow-hidden">
            <div class="absolute inset-0 opacity-20 pointer-events-none">
                <div class="absolute top-0 left-1/4 w-96 h-96 bg-emerald-600 rounded-full blur-[100px]"></div>
                <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-amber-600 rounded-full blur-[100px]"></div>
            </div>
            <div class="relative max-w-[1400px] mx-auto px-6 lg:px-10">
                <div v-if="block.data.eyebrow || block.data.heading" class="text-center max-w-2xl mx-auto mb-16 reveal">
                    <div v-if="block.data.eyebrow" class="text-[11px] uppercase tracking-[0.2em] text-amber-400 font-bold mb-4">{{ block.data.eyebrow }}</div>
                    <h2 v-if="block.data.heading" class="text-4xl lg:text-5xl font-black tracking-tight leading-[1.1]">
                        <span class="bg-gradient-to-r from-amber-300 to-emerald-300 bg-clip-text text-transparent">{{ block.data.heading }}</span>
                    </h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="(t, idx) in (block.data.items || [])" :key="idx"
                         class="group relative bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-8 hover:bg-white/[0.08] hover:border-emerald-400/30 hover:-translate-y-1 transition-all duration-500 reveal-stagger"
                         :style="`--delay: ${idx * 120}ms`">
                        <ChatBubbleLeftRightIcon class="w-8 h-8 text-amber-400/60 mb-5" />
                        <p class="text-[15px] leading-[1.7] text-stone-100 italic">"{{ t.quote }}"</p>
                        <div class="mt-7 pt-6 border-t border-white/10 flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center text-white font-bold text-lg">
                                {{ (t.name || '?').split(' ').map(n => n[0]).slice(0, 2).join('') }}
                            </div>
                            <div>
                                <div class="font-bold text-white text-sm">{{ t.name }}</div>
                                <div v-if="t.role" class="text-xs text-stone-400 mt-0.5">{{ t.role }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ════════ toppers_table ════════ -->
        <section v-else-if="block.type === 'toppers_table'" class="py-20" :class="bgFor(i)">
            <div class="max-w-5xl mx-auto px-6 lg:px-10">
                <div v-if="block.data.eyebrow || block.data.heading" class="mb-10 reveal">
                    <div v-if="block.data.eyebrow" class="text-[11px] uppercase tracking-[0.2em] text-amber-600 font-bold mb-4">{{ block.data.eyebrow }}</div>
                    <h2 v-if="block.data.heading" class="text-3xl lg:text-5xl font-black text-slate-900 tracking-tight leading-[1.1]">{{ block.data.heading }}</h2>
                </div>
                <div class="overflow-hidden rounded-3xl bg-white shadow-xl border border-stone-100">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gradient-to-r from-emerald-700 to-emerald-900 text-white text-[11px] uppercase tracking-[0.15em]">
                                <th class="px-4 py-4 text-center w-16">Rank</th>
                                <th class="px-4 py-4 text-left">Name</th>
                                <th class="px-4 py-4 text-left">Class / Stream</th>
                                <th class="px-4 py-4 text-right">Marks</th>
                                <th class="px-4 py-4 text-right w-24">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(t, idx) in (block.data.items || [])" :key="idx"
                                class="border-b border-stone-100 hover:bg-emerald-50/40 transition-colors">
                                <td class="px-4 py-4 text-center">
                                    <span class="inline-flex w-8 h-8 rounded-full items-center justify-center font-black text-sm"
                                        :class="idx < 3 ? 'bg-amber-400 text-slate-900' : 'bg-stone-100 text-slate-700'">
                                        {{ t.rank || idx + 1 }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 font-bold text-slate-900">{{ t.name }}</td>
                                <td class="px-4 py-4 text-sm text-slate-600">{{ t.class }}</td>
                                <td class="px-4 py-4 text-right font-mono text-sm text-slate-700">{{ t.marks }}</td>
                                <td class="px-4 py-4 text-right font-bold text-emerald-700">{{ t.percent }}{{ t.percent && !String(t.percent).includes('%') ? '%' : '' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ════════ cta ════════ -->
        <section v-else-if="block.type === 'cta'" class="relative overflow-hidden py-24 bg-gradient-to-br from-emerald-900 via-slate-900 to-emerald-950 text-white">
            <div class="absolute inset-0 opacity-30 pointer-events-none">
                <div class="absolute top-0 left-0 w-96 h-96 bg-amber-500 rounded-full blur-[140px] animate-float"></div>
                <div class="absolute bottom-0 right-0 w-[30rem] h-[30rem] bg-emerald-500 rounded-full blur-[140px] animate-float" style="animation-delay: 3s"></div>
            </div>
            <div class="relative max-w-4xl mx-auto px-6 lg:px-10 text-center reveal">
                <div v-if="block.data.eyebrow" class="text-[11px] uppercase tracking-[0.3em] text-amber-400 font-bold mb-6">{{ block.data.eyebrow }}</div>
                <h2 class="text-4xl lg:text-6xl font-black leading-[1.05] tracking-tight">
                    <span class="bg-gradient-to-r from-amber-300 via-amber-400 to-emerald-300 bg-clip-text text-transparent animate-gradient">
                        {{ block.data.heading }}
                    </span>
                </h2>
                <p v-if="block.data.body" class="mt-7 text-lg lg:text-xl text-stone-300 max-w-2xl mx-auto font-light leading-relaxed">{{ block.data.body }}</p>
                <div v-if="block.data.cta_label" class="mt-10">
                    <Link v-if="block.data.cta_url?.startsWith('/')" :href="block.data.cta_url"
                        class="group inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-slate-950 px-8 py-4 rounded-full font-bold text-sm shadow-2xl shadow-amber-500/30 hover:shadow-amber-500/50 hover:-translate-y-0.5 transition-all duration-300">
                        {{ block.data.cta_label }}
                        <ArrowRightIcon class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                    </Link>
                    <a v-else :href="block.data.cta_url || '#'"
                        class="group inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-slate-950 px-8 py-4 rounded-full font-bold text-sm shadow-2xl shadow-amber-500/30 hover:shadow-amber-500/50 hover:-translate-y-0.5 transition-all duration-300">
                        {{ block.data.cta_label }}
                        <ArrowRightIcon class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                    </a>
                </div>
            </div>
        </section>

    </template>
</template>
