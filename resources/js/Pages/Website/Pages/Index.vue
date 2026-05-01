<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    DocumentTextIcon, ArrowRightIcon, EyeIcon, Squares2X2Icon,
    SparklesIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline'

defineProps({
    pages: { type: Array, default: () => [] },
})
</script>

<template>
    <Head title="Pages Content" />
    <AppLayout :breadcrumbs="[{ label: 'Website' }, { label: 'Pages Content' }]">
        <div class="max-w-5xl mx-auto space-y-6">
            <div>
                <h1 class="text-2xl font-bold">Pages Content</h1>
                <p class="text-sm text-base-content/60 mt-1">
                    Manage the hero banner and content blocks shown on each public page.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link v-for="p in pages" :key="p.key"
                      :href="route('website.pages.show', p.key)"
                      class="group card bg-base-100 shadow-sm border border-base-200 hover:shadow-md hover:border-primary/30 transition-all">
                    <div class="card-body p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white transition-all">
                                <Squares2X2Icon class="w-5 h-5" />
                            </div>
                            <a :href="p.route" target="_blank" rel="noopener"
                                @click.stop
                                class="btn btn-ghost btn-xs gap-1 text-base-content/55 hover:text-emerald-700">
                                <EyeIcon class="w-3.5 h-3.5" /> Preview
                            </a>
                        </div>
                        <h2 class="text-base font-bold mt-3">{{ p.label }}</h2>
                        <p class="text-xs text-base-content/55 font-mono mt-1">{{ p.route }}</p>

                        <div class="mt-4 space-y-1.5">
                            <div class="flex items-center gap-2 text-[11px]">
                                <SparklesIcon class="w-3.5 h-3.5"
                                    :class="p.has_hero ? 'text-amber-500' : 'text-base-content/30'" />
                                <span :class="p.has_hero ? 'text-base-content/75 font-semibold' : 'text-base-content/40'">
                                    Hero {{ p.has_hero ? 'configured' : 'using defaults' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 text-[11px]">
                                <CheckCircleIcon class="w-3.5 h-3.5"
                                    :class="p.active > 0 ? 'text-emerald-500' : 'text-base-content/30'" />
                                <span :class="p.active > 0 ? 'text-base-content/75' : 'text-base-content/40'">
                                    {{ p.active }} custom block{{ p.active === 1 ? '' : 's' }}
                                    <span v-if="p.total > p.active" class="text-base-content/40">· {{ p.total - p.active }} hidden</span>
                                </span>
                            </div>
                            <div v-if="p.auto_content" class="flex items-start gap-2 text-[11px] text-base-content/55">
                                <span class="w-3.5 h-3.5 rounded-full bg-sky-100 text-sky-700 flex items-center justify-center text-[8px] font-bold shrink-0 mt-0.5">A</span>
                                <span>Plus auto: {{ p.auto_content }}</span>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-base-200 flex items-center justify-end">
                            <span class="text-xs font-semibold text-emerald-700 inline-flex items-center gap-1 group-hover:gap-2 transition-all">
                                Manage <ArrowRightIcon class="w-3.5 h-3.5" />
                            </span>
                        </div>
                    </div>
                </Link>
            </div>

            <div class="card bg-base-100 border border-base-200 shadow-sm">
                <div class="card-body p-5 flex-row items-start gap-4">
                    <DocumentTextIcon class="w-6 h-6 text-amber-500 shrink-0" />
                    <div>
                        <h3 class="text-sm font-bold">How it works</h3>
                        <p class="text-xs text-base-content/60 mt-1.5 leading-relaxed">
                            <b>Every page</b> has a <b>hero banner</b> (eyebrow, title, accent, subtitle, theme) and supports
                            <b>custom content blocks</b> — drag-reorderable, hideable, deletable. Pages like News, Gallery, Faculty,
                            and Contact also render an <b>auto section</b> (the listings/forms) — your custom blocks render
                            right after, ideal for intros, FAQs, or calls-to-action.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
