<script setup>
import { computed } from 'vue'

const props = defineProps({
    // Optional small label above the title — usually the parent area
    // ("Workflow", "Administration"). Renders uppercase + tracked.
    eyebrow: { type: String, default: '' },
    title: { type: String, required: true },
    // Optional one-line description under the title.
    subtitle: { type: String, default: '' },
    // Optional icon-pill on the left (component, e.g. AcademicCapIcon).
    icon: { type: [Object, Function], default: null },
    // Tone for the icon-pill and card tint:
    //   emerald | sky | amber | violet | rose | primary
    tone: { type: String, default: 'primary' },
    // Drops the tinted card wrapper — use on nested pages where the
    // surrounding layout already frames the header.
    compact: { type: Boolean, default: false },
})

// Tone → concrete class strings. Keeps template markup readable and
// makes it easy to add new tones without duplicating the pattern.
const wrapClass = computed(() => ({
    primary: 'border-primary/15 bg-gradient-to-br from-primary/[0.08] via-primary/[0.03] to-transparent',
    emerald: 'border-emerald-500/15 bg-gradient-to-br from-emerald-500/[0.09] via-emerald-500/[0.03] to-transparent',
    sky:     'border-sky-500/15 bg-gradient-to-br from-sky-500/[0.09] via-sky-500/[0.03] to-transparent',
    amber:   'border-amber-500/15 bg-gradient-to-br from-amber-500/[0.09] via-amber-500/[0.03] to-transparent',
    violet:  'border-violet-500/15 bg-gradient-to-br from-violet-500/[0.09] via-violet-500/[0.03] to-transparent',
    rose:    'border-rose-500/15 bg-gradient-to-br from-rose-500/[0.09] via-rose-500/[0.03] to-transparent',
}[props.tone] || ''))

const iconClass = computed(() => ({
    primary: 'bg-primary text-primary-content shadow-primary/25',
    emerald: 'bg-emerald-500 text-white shadow-emerald-500/25',
    sky:     'bg-sky-500 text-white shadow-sky-500/25',
    amber:   'bg-amber-500 text-white shadow-amber-500/25',
    violet:  'bg-violet-500 text-white shadow-violet-500/25',
    rose:    'bg-rose-500 text-white shadow-rose-500/25',
}[props.tone] || ''))
</script>

<template>
    <!-- Page header shared across every list/show screen.
         The tinted card wrapper gives mobile users a strong "you are on
         X" anchor now that the sidebar is off-canvas on phones. Icon
         renders at every breakpoint (was hidden < sm before). -->
    <header
        :class="[
            'relative mb-4 sm:mb-5',
            compact ? '' : ['rounded-2xl border px-4 py-4 sm:px-6 sm:py-5', wrapClass],
        ]"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3 sm:gap-4 min-w-0">
                <div v-if="icon"
                    :class="[
                        'flex h-11 w-11 sm:h-12 sm:w-12 shrink-0 items-center justify-center rounded-xl sm:rounded-2xl shadow-sm',
                        iconClass,
                    ]">
                    <component :is="icon" class="w-5 h-5 sm:w-6 sm:h-6" />
                </div>
                <div class="min-w-0 flex-1">
                    <p v-if="eyebrow"
                        class="text-[10px] uppercase tracking-[0.18em] font-bold text-base-content/55 mb-0.5">
                        {{ eyebrow }}
                    </p>
                    <h1 class="text-lg sm:text-2xl font-extrabold tracking-tight leading-tight truncate">
                        {{ title }}
                    </h1>
                    <p v-if="subtitle"
                       class="text-[13px] sm:text-sm text-base-content/60 mt-0.5 sm:mt-1 line-clamp-2 sm:line-clamp-1">
                        {{ subtitle }}
                    </p>
                </div>
            </div>

            <div v-if="$slots.actions"
                class="flex flex-wrap items-center gap-2 sm:flex-shrink-0 -mx-1 px-1 sm:mx-0 sm:px-0">
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>
