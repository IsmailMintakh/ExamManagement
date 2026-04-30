<script setup>
defineProps({
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
});

const sizeClass = {
    sm: 'loading-sm',
    md: 'loading-md',
    lg: 'loading-lg',
};
</script>

<template>
    <span class="loading loading-spinner" :class="sizeClass[size]"></span>
</template>
