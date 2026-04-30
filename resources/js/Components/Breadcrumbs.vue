<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="breadcrumbs text-sm">
        <ul>
            <li v-for="(item, index) in items" :key="index">
                <Link
                    v-if="item.href && index < items.length - 1"
                    :href="item.href"
                    class="hover:text-primary"
                >
                    {{ item.label }}
                </Link>
                <span v-else class="text-base-content/70">{{ item.label }}</span>
            </li>
        </ul>
    </div>
</template>
