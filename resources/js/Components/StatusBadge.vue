<script setup>
import { computed } from 'vue'

const props = defineProps({
    status: { type: String, required: true },
    type: { type: String, default: 'exam' },
})

const config = computed(() => {
    const maps = {
        exam: {
            draft: { label: 'Draft', cls: 'badge-ghost' },
            published: { label: 'Published', cls: 'badge-info' },
            marks_entry: { label: 'Marks Entry', cls: 'badge-warning' },
            processing: { label: 'Processing', cls: 'badge-accent' },
            completed: { label: 'Completed', cls: 'badge-success' },
            archived: { label: 'Archived', cls: 'badge-neutral' },
            scheduled: { label: 'Scheduled', cls: 'badge-info' },
            ongoing: { label: 'Ongoing', cls: 'badge-warning' },
            cancelled: { label: 'Cancelled', cls: 'badge-error' },
        },
        marks: {
            pending: { label: 'Pending', cls: 'badge-ghost' },
            draft: { label: 'Draft', cls: 'badge-info' },
            entered: { label: 'Entered', cls: 'badge-info' },
            submitted: { label: 'Submitted', cls: 'badge-success' },
            verified: { label: 'Verified', cls: 'badge-success' },
            rejected: { label: 'Rejected', cls: 'badge-error' },
            locked: { label: 'Locked', cls: 'badge-warning' },
        },
        result: {
            pending: { label: 'Pending', cls: 'badge-ghost' },
            generated: { label: 'Generated', cls: 'badge-info' },
            submitted: { label: 'Submitted', cls: 'badge-warning' },
            finalized: { label: 'Finalized', cls: 'badge-success' },
            published: { label: 'Published', cls: 'badge-primary' },
            withheld: { label: 'Withheld', cls: 'badge-error' },
            draft: { label: 'Draft', cls: 'badge-ghost' },
        },
    }
    const map = maps[props.type] || maps.exam
    return map[props.status?.toLowerCase()] || { label: props.status?.replace(/_/g, ' ') || '—', cls: 'badge-ghost' }
})
</script>

<template>
    <span class="badge badge-sm capitalize" :class="config.cls">{{ config.label }}</span>
</template>
