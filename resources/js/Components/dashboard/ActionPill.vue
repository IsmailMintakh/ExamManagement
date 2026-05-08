<script setup>
/**
 * Top-of-page pill that summarizes setup-checklist progress + needs-attention
 * counts. Clicks open an inline drawer that shows the full lists. Replaces
 * the old big "Setup Checklist" + "Needs Attention" sections so the user's
 * daily-driver dashboard isn't dominated by first-run UI.
 *
 * Props:
 *   - setupStatus: { is_complete, done_count, total_count, steps: [...] } | null
 *   - attentionItems: [{ key, severity, title, description, action_label, action_url, count }]
 */
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import {
    SparklesIcon, ExclamationTriangleIcon, ChevronDownIcon,
    CheckCircleIcon, ArrowRightIcon, BoltIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    setupStatus: { type: Object, default: null },
    attentionItems: { type: Array, default: () => [] },
})

const open = ref(false)

const setupRemaining = computed(() => {
    const s = props.setupStatus
    if (!s || s.is_complete) return 0
    return (s.total_count || 0) - (s.done_count || 0)
})
const totalActions = computed(() => setupRemaining.value + (props.attentionItems?.length || 0))

const severityColor = (sev) => ({
    error: 'bg-rose-500/15 text-rose-700 dark:text-rose-300',
    warning: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    info: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
}[sev] || 'bg-base-200')
</script>

<template>
    <div v-if="totalActions > 0" class="rounded-2xl bg-base-100 ring-1 ring-amber-500/20 overflow-hidden">
        <button @click="open = !open" type="button"
            class="w-full flex items-center gap-3 px-4 py-3 hover:bg-amber-500/5 transition-colors">
            <div class="w-9 h-9 rounded-xl bg-amber-500/15 text-amber-700 dark:text-amber-300 flex items-center justify-center shrink-0">
                <BoltIcon class="w-4.5 h-4.5" />
            </div>
            <div class="flex-1 min-w-0 text-left">
                <p class="font-bold text-sm">
                    {{ totalActions }} {{ totalActions === 1 ? 'item needs' : 'items need' }} your attention
                </p>
                <p class="text-[11px] text-base-content/55 truncate">
                    <template v-if="setupRemaining > 0">{{ setupRemaining }} setup step{{ setupRemaining === 1 ? '' : 's' }} remaining</template>
                    <template v-if="setupRemaining > 0 && attentionItems?.length"> · </template>
                    <template v-if="attentionItems?.length">{{ attentionItems.length }} alert{{ attentionItems.length === 1 ? '' : 's' }}</template>
                </p>
            </div>
            <ChevronDownIcon class="w-4 h-4 text-base-content/45 transition-transform shrink-0"
                :class="open ? 'rotate-180' : ''" />
        </button>

        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="max-h-0 opacity-0"
            enter-to-class="max-h-[800px] opacity-100"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="max-h-[800px] opacity-100"
            leave-to-class="max-h-0 opacity-0">
            <div v-if="open" class="overflow-hidden border-t border-amber-500/15">
                <!-- Setup steps -->
                <div v-if="setupStatus && !setupStatus.is_complete" class="divide-y divide-base-200">
                    <Link v-for="step in setupStatus.steps" :key="step.key"
                        :href="step.action_url || '#'"
                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-base-200/40 transition-colors">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0"
                            :class="step.done ? 'bg-emerald-500/15 text-emerald-600' : 'bg-base-200 text-base-content/55'">
                            <CheckCircleIcon v-if="step.done" class="w-4 h-4" />
                            <SparklesIcon v-else class="w-3.5 h-3.5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium" :class="step.done ? 'text-base-content/55 line-through' : ''">
                                {{ step.label }}
                            </p>
                        </div>
                        <span v-if="step.count" class="text-[11px] font-mono tabular-nums text-base-content/55">
                            {{ step.count }}
                        </span>
                        <ArrowRightIcon v-if="!step.done" class="w-3.5 h-3.5 text-base-content/40" />
                    </Link>
                </div>

                <!-- Attention items -->
                <div v-if="attentionItems?.length" class="divide-y divide-base-200">
                    <Link v-for="item in attentionItems" :key="item.key"
                        :href="item.action_url || '#'"
                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-base-200/40 transition-colors">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0"
                            :class="severityColor(item.severity)">
                            <ExclamationTriangleIcon class="w-3.5 h-3.5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold leading-tight">{{ item.title }}</p>
                            <p class="text-[11px] text-base-content/55 truncate">{{ item.description }}</p>
                        </div>
                        <span v-if="item.count" class="text-xs font-bold tabular-nums px-1.5 py-0.5 rounded-md"
                            :class="severityColor(item.severity)">
                            {{ item.count }}
                        </span>
                        <ArrowRightIcon class="w-3.5 h-3.5 text-base-content/40" />
                    </Link>
                </div>
            </div>
        </Transition>
    </div>
</template>
