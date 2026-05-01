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
    <Head :title="hero.meta_title || 'Board Results'">
        <meta v-if="hero.meta_description" name="description" :content="hero.meta_description" />
    </Head>
    <PublicLayout>
        <PublicHero :hero="hero" :fallback="{
            eyebrow: 'FBISE Board Results',
            title: 'Excellence',
            accent: 'on the board.',
            subtitle: `Year after year, our students rise to the top — both at home and across Pakistan.`,
        }" />

        <PageBlocks :blocks="blocks" />

        <section v-if="!blocks.length" class="py-32 bg-white">
            <div class="max-w-2xl mx-auto px-6 text-center">
                <p class="text-slate-500">Board results are being compiled. Please check back soon.</p>
            </div>
        </section>
    </PublicLayout>
</template>
