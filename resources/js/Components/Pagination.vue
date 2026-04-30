<script setup>
import { router } from '@inertiajs/vue3'

defineProps({
    links: { type: Array, default: () => [] },
    from: { type: Number, default: 0 },
    to: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
})

function visit(url) {
    if (url) router.visit(url, { preserveState: true, preserveScroll: true })
}
</script>

<template>
    <div v-if="links && links.length > 3" class="flex flex-col items-center justify-between gap-3 sm:flex-row">
        <p v-if="total" class="text-xs text-base-content/50">
            Showing <span class="font-semibold text-base-content/75">{{ from }}</span>
            to <span class="font-semibold text-base-content/75">{{ to }}</span>
            of <span class="font-semibold text-base-content/75">{{ total }}</span>
        </p>
        <div class="flex items-center gap-1">
            <button
                v-for="(link, i) in links"
                :key="i"
                :disabled="!link.url"
                @click="visit(link.url)"
                class="flex h-8 min-w-8 items-center justify-center rounded-lg px-2.5 text-xs font-semibold transition-all"
                :class="[
                    link.active
                        ? 'bg-primary text-primary-content shadow-md shadow-primary/25'
                        : link.url
                            ? 'hover:bg-base-200 text-base-content/65'
                            : 'text-base-content/20 cursor-not-allowed',
                ]"
                v-html="link.label"
            />
        </div>
    </div>
</template>
