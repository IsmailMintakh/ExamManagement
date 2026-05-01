<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import PublicHero from '@/Components/PublicHero.vue'
import PageBlocks from '@/Components/PageBlocks.vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
    site:   { type: Object, default: () => ({}) },
    hero:   { type: Object, default: () => ({}) },
    blocks: { type: Array,  default: () => [] },
})

const s = (k, fb = '') => props.site?.[k] || fb
</script>

<template>
    <Head :title="hero.meta_title || 'About'">
        <meta v-if="hero.meta_description" name="description" :content="hero.meta_description" />
    </Head>
    <PublicLayout>
        <PublicHero :hero="hero" :fallback="{
            eyebrow: 'Our Heritage',
            title: `${s('stat_years_legacy', '72')}+ Years`,
            accent: 'of Learning.',
            subtitle: `From a modest middle school in ${s('established_year', '1954')} to Skardu's most prestigious institution — this is our story.`,
        }" />

        <PageBlocks :blocks="blocks" />

        <section v-if="!blocks.length" class="py-32 bg-white">
            <div class="max-w-2xl mx-auto px-6 text-center">
                <p class="text-slate-500">More content about our school is coming soon.</p>
            </div>
        </section>
    </PublicLayout>
</template>
