<script setup>
defineProps({
    variant: { type: String, default: 'text' }, // text | circle | rect | card
    width: { type: String, default: '' },
    height: { type: String, default: '' },
    lines: { type: Number, default: 1 },
})
</script>

<template>
    <!-- Card skeleton -->
    <div v-if="variant === 'card'" class="surface p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="space-y-2 flex-1">
                <div class="skeleton h-3 w-24" />
                <div class="skeleton h-7 w-16" />
                <div class="skeleton h-3 w-32" />
            </div>
            <div class="skeleton h-11 w-11 rounded-xl" />
        </div>
    </div>

    <!-- Circle (avatar) -->
    <div v-else-if="variant === 'circle'" class="skeleton rounded-full"
        :style="{ width: width || '2.5rem', height: height || width || '2.5rem' }" />

    <!-- Rect -->
    <div v-else-if="variant === 'rect'" class="skeleton"
        :style="{ width: width || '100%', height: height || '6rem' }" />

    <!-- Multi-line text -->
    <div v-else-if="lines > 1" class="space-y-2">
        <div v-for="i in lines" :key="i" class="skeleton h-3"
            :style="{ width: i === lines ? '70%' : '100%' }" />
    </div>

    <!-- Single text line -->
    <div v-else class="skeleton h-3"
        :style="{ width: width || '100%', height: height || '0.75rem' }" />
</template>
