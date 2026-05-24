<script setup>
// Combobox-style dropdown with type-to-filter — drop-in for `<select>` when
// the list is long enough that scrolling becomes a chore (subjects, classes,
// teachers, students, etc.). API-compatible with FormSelect so callers can
// pass an `options: [{value, label, disabled?}]` array.
//
// Keyboard: ↑ / ↓ to move, Enter to pick, Esc to close. Click outside closes.

import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ChevronUpDownIcon, MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { CheckIcon } from '@heroicons/vue/20/solid'

const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    options: { type: Array, default: () => [] }, // [{value, label, disabled?, sublabel?}]
    placeholder: { type: String, default: 'Select…' },
    searchPlaceholder: { type: String, default: 'Search…' },
    disabled: { type: Boolean, default: false },
    size: { type: String, default: 'md' }, // 'xs' | 'sm' | 'md' | 'lg'
    clearable: { type: Boolean, default: false },
    emptyText: { type: String, default: 'No matches' },
    error: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue', 'change'])

const open = ref(false)
const query = ref('')
const cursor = ref(-1)
const wrapperEl = ref(null)
const inputEl = ref(null)
const listEl = ref(null)

const sizeBtn = computed(() => ({
    xs: 'h-7 text-[11px] px-2.5',
    sm: 'h-8 text-xs px-3',
    md: 'h-10 text-sm px-3.5',
    lg: 'h-12 text-base px-4',
})[props.size] || 'h-10 text-sm px-3.5')

const selectedOption = computed(() =>
    (props.options || []).find(o => String(o.value) === String(props.modelValue))
)

const displayLabel = computed(() => selectedOption.value?.label ?? '')

// Filter options as the user types. Match against both label and sublabel,
// case-insensitive, no regex magic so users can type punctuation safely.
const filtered = computed(() => {
    const q = query.value.trim().toLowerCase()
    if (!q) return props.options
    return (props.options || []).filter((o) => {
        const hay = `${o.label || ''} ${o.sublabel || ''}`.toLowerCase()
        return hay.includes(q)
    })
})

function openMenu() {
    if (props.disabled) return
    open.value = true
    // Pre-select the row matching the current value so Enter picks it.
    nextTick(() => {
        const idx = filtered.value.findIndex(o => String(o.value) === String(props.modelValue))
        cursor.value = idx >= 0 ? idx : (filtered.value.length ? 0 : -1)
        inputEl.value?.focus()
        scrollCursorIntoView()
    })
}
function closeMenu() {
    open.value = false
    query.value = ''
    cursor.value = -1
}
function toggleMenu() {
    open.value ? closeMenu() : openMenu()
}
function pick(option) {
    if (option.disabled) return
    emit('update:modelValue', option.value)
    emit('change', option.value)
    closeMenu()
}
function clear() {
    emit('update:modelValue', '')
    emit('change', '')
}

function onKey(e) {
    if (!open.value) {
        if (['ArrowDown', 'Enter', ' '].includes(e.key)) {
            e.preventDefault()
            openMenu()
        }
        return
    }
    switch (e.key) {
        case 'ArrowDown':
            e.preventDefault()
            cursor.value = Math.min(cursor.value + 1, filtered.value.length - 1)
            scrollCursorIntoView()
            break
        case 'ArrowUp':
            e.preventDefault()
            cursor.value = Math.max(cursor.value - 1, 0)
            scrollCursorIntoView()
            break
        case 'Enter':
            e.preventDefault()
            if (cursor.value >= 0 && filtered.value[cursor.value]) pick(filtered.value[cursor.value])
            break
        case 'Escape':
            e.preventDefault()
            closeMenu()
            break
    }
}

function scrollCursorIntoView() {
    nextTick(() => {
        const el = listEl.value?.children?.[cursor.value]
        if (el && typeof el.scrollIntoView === 'function') {
            el.scrollIntoView({ block: 'nearest' })
        }
    })
}

function onDocClick(e) {
    if (!wrapperEl.value) return
    if (!wrapperEl.value.contains(e.target)) closeMenu()
}

onMounted(() => document.addEventListener('mousedown', onDocClick))
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocClick))

// Re-evaluate cursor when filter changes so the highlight stays sensible.
watch(query, () => {
    cursor.value = filtered.value.length ? 0 : -1
})
</script>

<template>
    <div ref="wrapperEl" class="relative w-full">
        <!-- Trigger button (matches daisyUI `select select-bordered` look) -->
        <button
            type="button"
            :disabled="disabled"
            @click="toggleMenu"
            @keydown="onKey"
            class="w-full inline-flex items-center justify-between gap-2 rounded-lg border bg-base-100 text-left transition-colors focus:outline-none focus:ring-2 focus:ring-primary/50"
            :class="[
                sizeBtn,
                error ? 'border-error' : 'border-base-300 hover:border-base-content/30',
                disabled ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer',
            ]"
        >
            <span class="truncate flex-1" :class="!displayLabel ? 'text-base-content/45' : ''">
                {{ displayLabel || placeholder }}
            </span>
            <button
                v-if="clearable && displayLabel && !disabled"
                type="button"
                @click.stop="clear"
                class="p-0.5 rounded text-base-content/40 hover:text-rose-600 hover:bg-base-200"
                tabindex="-1"
                aria-label="Clear"
            >
                <XMarkIcon class="w-3.5 h-3.5" />
            </button>
            <ChevronUpDownIcon class="w-4 h-4 text-base-content/40 shrink-0" />
        </button>

        <!-- Dropdown -->
        <div v-if="open"
            class="absolute z-40 mt-1 w-full rounded-xl border border-base-300 bg-base-100 shadow-2xl">
            <!-- Search input -->
            <div class="relative border-b border-base-200 p-1.5">
                <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-base-content/40" />
                <input
                    ref="inputEl"
                    v-model="query"
                    type="text"
                    :placeholder="searchPlaceholder"
                    class="input input-sm input-bordered w-full pl-8 text-sm"
                    @keydown="onKey"
                />
            </div>
            <!-- Options list -->
            <ul ref="listEl" class="max-h-64 overflow-y-auto py-1">
                <li v-if="!filtered.length" class="px-3 py-3 text-xs text-base-content/50 italic text-center">
                    {{ emptyText }}
                </li>
                <li v-for="(opt, i) in filtered" :key="opt.value ?? i"
                    @click="pick(opt)"
                    @mouseenter="cursor = i"
                    class="px-3 py-2 cursor-pointer flex items-center gap-2 text-sm transition-colors"
                    :class="[
                        opt.disabled ? 'opacity-40 cursor-not-allowed' : '',
                        cursor === i ? 'bg-primary/15 text-primary' : 'hover:bg-base-200/60',
                        String(opt.value) === String(modelValue) ? 'font-semibold' : '',
                    ]">
                    <CheckIcon v-if="String(opt.value) === String(modelValue)"
                        class="w-4 h-4 text-primary shrink-0" />
                    <span v-else class="w-4 h-4 shrink-0"></span>
                    <span class="flex-1 truncate">{{ opt.label }}</span>
                    <span v-if="opt.sublabel" class="text-[10px] text-base-content/50 shrink-0">{{ opt.sublabel }}</span>
                </li>
            </ul>
        </div>
        <p v-if="error" class="mt-1.5 text-xs text-error">{{ error }}</p>
    </div>
</template>
