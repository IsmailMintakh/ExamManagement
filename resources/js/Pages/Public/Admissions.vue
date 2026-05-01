<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import PublicHero from '@/Components/PublicHero.vue'
import PageBlocks from '@/Components/PageBlocks.vue'
import { Head } from '@inertiajs/vue3'

defineProps({
    site:   { type: Object, default: () => ({}) },
    hero:   { type: Object, default: () => ({}) },
    blocks: { type: Array,  default: () => [] },
})
</script>

<template>
    <Head :title="hero.meta_title || 'Admissions'">
        <meta v-if="hero.meta_description" name="description" :content="hero.meta_description" />
    </Head>
    <PublicLayout>
        <PublicHero :hero="hero" :fallback="{
            eyebrow: 'Applications Open · 2026–27',
            title: 'Begin your',
            accent: 'journey.',
            subtitle: 'Five steps. One of the finest schools in Gilgit-Baltistan.',
        }" />

        <PageBlocks :blocks="blocks" />

        <section v-if="!blocks.length" class="py-32 bg-white">
            <div class="max-w-2xl mx-auto px-6 text-center">
                <p class="text-slate-500">Admissions information is being updated. Please check back soon.</p>
            </div>
        </section>
    </PublicLayout>
</template>
