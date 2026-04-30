<script setup>
import { Link } from '@inertiajs/vue3'
import { InboxIcon } from '@heroicons/vue/24/outline'

defineProps({
    title: { type: String, default: 'No data found' },
    description: { type: String, default: '' },
    actionText: { type: String, default: '' },
    actionHref: { type: String, default: '' },
    icon: { type: [Object, Function], default: null },
})
</script>

<template>
    <div class="empty-state">
        <div class="empty-state-icon">
            <component :is="icon || InboxIcon" class="h-7 w-7 text-base-content/30" />
        </div>
        <h3 class="text-base font-bold">{{ title }}</h3>
        <p v-if="description" class="mt-1.5 max-w-sm text-sm text-base-content/55">{{ description }}</p>
        <Link v-if="actionText && actionHref" :href="actionHref" class="btn btn-primary btn-sm mt-5">
            {{ actionText }}
        </Link>
    </div>
</template>
